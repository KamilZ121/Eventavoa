<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';
require_once __DIR__ . '/../config/dataHandler.php';
bootstrapApi();

$dataHandler = new DataHandler();
$conn = $dataHandler->getConnection();
$method = requestMethod();
$userId = requireLogin();

if ($method === 'placeOrder') {
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        respond(['success' => false, 'message' => 'Ihr Warenkorb ist leer.'], 422);
    }

    ensureCompleteCustomerData($conn, $userId);

    $paymentId = max(0, (int) ($_POST['payment_method_id'] ?? 0));
    $voucherCode = strtoupper(trim((string) ($_POST['voucher_code'] ?? '')));
    if ($paymentId === 0 && $voucherCode === '') {
        respond(['success' => false, 'message' => 'Bitte Zahlungsart oder Gutschein angeben.'], 422);
    }
    if ($paymentId > 0) {
        $check = $conn->prepare('SELECT id FROM zahlungsmoeglichkeiten WHERE id = ? AND user_id = ?');
        $check->bind_param('ii', $paymentId, $userId);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            respond(['success' => false, 'message' => 'Ungültige Zahlungsart.'], 422);
        }
    }

    $stmt = $conn->prepare('SELECT id, name, price FROM products WHERE id = ? AND is_active = 1');
    $items = [];
    $subtotal = 0.0;
    foreach ($cart as $productId => $qty) {
        $productId = (int) $productId;
        $qty = max(0, (int) $qty);
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        if ($product && $qty > 0) {
            $price = (float) $product['price'];
            $subtotal += $price * $qty;
            $items[] = ['id' => $productId, 'name' => $product['name'], 'qty' => $qty, 'price' => $price];
        }
    }
    if (!$items) {
        respond(['success' => false, 'message' => 'Keine gültigen Produkte im Warenkorb.'], 422);
    }

    $conn->begin_transaction();
    try {
        $voucherId = null;
        $voucherAmount = 0.0;
        if ($voucherCode !== '') {
            $voucherStmt = $conn->prepare('SELECT id, remaining_value FROM vouchers WHERE code = ? AND expires_at >= CURRENT_DATE AND remaining_value > 0 FOR UPDATE');
            $voucherStmt->bind_param('s', $voucherCode);
            $voucherStmt->execute();
            $voucher = $voucherStmt->get_result()->fetch_assoc();
            if (!$voucher) {
                throw new DomainException('Der Gutschein ist ungültig, abgelaufen oder aufgebraucht.');
            }
            $voucherId = (int) $voucher['id'];
            $voucherAmount = min($subtotal, (float) $voucher['remaining_value']);
            $remaining = (float) $voucher['remaining_value'] - $voucherAmount;
            $updateVoucher = $conn->prepare('UPDATE vouchers SET remaining_value = ? WHERE id = ?');
            $updateVoucher->bind_param('di', $remaining, $voucherId);
            $updateVoucher->execute();
        }
        $total = max(0, $subtotal - $voucherAmount);
        if ($total > 0 && $paymentId === 0) {
            throw new DomainException('Der Gutschein deckt nicht den Gesamtbetrag. Bitte zusätzlich eine Zahlungsart wählen.');
        }
        $paymentValue = $paymentId ?: null;
        $insert = $conn->prepare('INSERT INTO orders (user_id, zahlung_id, gutschein_id, zwischensumme, gutscheinbetrag, gesamt) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->bind_param('iiiddd', $userId, $paymentValue, $voucherId, $subtotal, $voucherAmount, $total);
        $insert->execute();
        $orderId = $conn->insert_id;
        $invoice = 'RE-' . date('Y') . '-' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
        $invoiceStmt = $conn->prepare('UPDATE orders SET rechnungsnummer = ? WHERE id = ?');
        $invoiceStmt->bind_param('si', $invoice, $orderId);
        $invoiceStmt->execute();

        $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, produktname, menge, einzelpreis) VALUES (?, ?, ?, ?, ?)');
        foreach ($items as $item) {
            $itemStmt->bind_param('iisid', $orderId, $item['id'], $item['name'], $item['qty'], $item['price']);
            $itemStmt->execute();
        }
        $conn->commit();
        $_SESSION['cart'] = [];
        respond([
            'success' => true,
            'orderId' => $orderId,
            'invoiceNumber' => $invoice,
            'zwischensumme' => $subtotal,
            'gutscheinbetrag' => $voucherAmount,
            'gesamt' => $total
        ]);
    } catch (DomainException $exception) {
        $conn->rollback();
        respond(['success' => false, 'message' => $exception->getMessage()], 422);
    } catch (Throwable $exception) {
        $conn->rollback();
        respond(['success' => false, 'message' => 'Bestellung konnte nicht gespeichert werden.'], 500);
    }
}

if ($method === 'getOrders') {
    $stmt = $conn->prepare('SELECT id, rechnungsnummer, zwischensumme, gutscheinbetrag, gesamt, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at ASC, id ASC');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemStmt = $conn->prepare('SELECT produktname AS name, menge, einzelpreis FROM order_items WHERE order_id = ? ORDER BY id');
    foreach ($orders as &$order) {
        $order['id'] = (int) $order['id'];
        $order['zwischensumme'] = (float) $order['zwischensumme'];
        $order['gutscheinbetrag'] = (float) $order['gutscheinbetrag'];
        $order['gesamt'] = (float) $order['gesamt'];
        $itemStmt->bind_param('i', $order['id']);
        $itemStmt->execute();
        $order['items'] = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($order['items'] as &$item) {
            $item['menge'] = (int) $item['menge'];
            $item['einzelpreis'] = (float) $item['einzelpreis'];
        }
    }
    respond(['success' => true, 'orders' => $orders]);
}

function ensureCompleteCustomerData(mysqli $conn, int $userId): void
{
    $stmt = $conn->prepare("SELECT u.anrede, u.vorname, u.nachname, u.email, u.benutzername,
                                   a.strasse, a.plz, a.ort
                            FROM users u
                            LEFT JOIN addresses a ON a.user_id = u.id
                                AND a.address_type = 'shipping'
                                AND a.is_default = 1
                            WHERE u.id = ? AND u.aktiv = 1
                            LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data) {
        respond(['success' => false, 'message' => 'Benutzerkonto nicht gefunden oder deaktiviert.'], 403);
    }

    foreach (['anrede', 'vorname', 'nachname', 'email', 'benutzername', 'strasse', 'plz', 'ort'] as $field) {
        if (trim((string) ($data[$field] ?? '')) === '') {
            respond(['success' => false, 'message' => 'Bitte vervollstaendigen Sie zuerst Ihre Kontodaten. Die Produkte bleiben im Warenkorb.'], 422);
        }
    }

    if (!filter_var((string) $data['email'], FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{4}$/', (string) $data['plz'])) {
        respond(['success' => false, 'message' => 'Bitte pruefen Sie Ihre Kontodaten. Die Produkte bleiben im Warenkorb.'], 422);
    }
}

if ($method === 'getInvoice') {
    $orderId = (int) ($_GET['order_id'] ?? 0);
    $stmt = $conn->prepare("SELECT o.id, o.rechnungsnummer, o.zwischensumme, o.gutscheinbetrag, o.gesamt, o.created_at,
                                  u.anrede, u.vorname, u.nachname, a.strasse, a.plz, a.ort
                           FROM orders o JOIN users u ON u.id = o.user_id
                           LEFT JOIN addresses a ON a.user_id = u.id AND a.address_type = 'shipping' AND a.is_default = 1
                           WHERE o.id = ? AND o.user_id = ? LIMIT 1");
    $stmt->bind_param('ii', $orderId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        respond(['success' => false, 'message' => 'Bestellung nicht gefunden.'], 404);
    }
    $itemsStmt = $conn->prepare('SELECT produktname AS name, menge, einzelpreis FROM order_items WHERE order_id = ? ORDER BY id');
    $itemsStmt->bind_param('i', $orderId);
    $itemsStmt->execute();
    $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($items as &$item) {
        $item['menge'] = (int) $item['menge'];
        $item['einzelpreis'] = (float) $item['einzelpreis'];
    }
    respond(['success' => true,
        'order' => ['id' => (int) $row['id'], 'rechnungsnummer' => $row['rechnungsnummer'], 'zwischensumme' => (float) $row['zwischensumme'], 'gutscheinbetrag' => (float) $row['gutscheinbetrag'], 'gesamt' => (float) $row['gesamt'], 'created_at' => $row['created_at']],
        'kunde' => ['anrede' => $row['anrede'], 'vorname' => $row['vorname'], 'nachname' => $row['nachname']],
        'adresse' => ['strasse' => $row['strasse'], 'plz' => $row['plz'], 'ort' => $row['ort']], 'items' => $items]);
}

respond(['success' => false, 'message' => 'Ungültige Aktion.'], 400);

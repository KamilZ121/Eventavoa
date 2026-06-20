<?php

session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/DBAccess.php";

$conn = DBAccess::getInstance()->getConnection();
$action = $_REQUEST['action'] ?? '';

// Bestellung aufgeben wenn eingeloggt
if ($action === 'placeOrder') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'needsLogin' => true, 'message' => 'Bitte zuerst einloggen.']);
        exit;
    }

    $userId = (int)$_SESSION['user_id'];
    $cart = $_SESSION['cart'] ?? [];

    if (empty($cart)) {
        echo json_encode(['success' => false, 'message' => 'Ihr Warenkorb ist leer.']);
        exit;
    }

    // Zahlungsmöglichkeit muss ausgewählt sein
    $zahlungId = (int)($_POST['payment_method_id'] ?? 0);
    $pcheck = mysqli_prepare($conn, "SELECT id FROM zahlungsmoeglichkeiten WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($pcheck, "ii", $zahlungId, $userId);
    mysqli_stmt_execute($pcheck);
    mysqli_stmt_store_result($pcheck);
    if (mysqli_stmt_num_rows($pcheck) === 0) {
        echo json_encode(['success' => false, 'needsPayment' => true, 'message' => 'Bitte eine Zahlungsmöglichkeit auswählen.']);
        exit;
    }

    // Preise von der DB holen
    $priceStmt = mysqli_prepare($conn, "SELECT price FROM products WHERE id = ? AND is_active = 1");
    $items = [];
    $total = 0.0;

    foreach ($cart as $productId => $qty) {
        $productId = (int)$productId;
        $qty = (int)$qty;
        if ($qty < 1) {
            continue;
        }

        mysqli_stmt_bind_param($priceStmt, "i", $productId);
        mysqli_stmt_execute($priceStmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($priceStmt));
        if (!$row) {
            continue; // falls produkt nicht aktiv ist
        }

        $price = (float)$row['price'];
        $total += $price * $qty;
        $items[] = ['product_id' => $productId, 'menge' => $qty, 'einzelpreis' => $price];
    }

    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Keine gültigen Produkte im Warenkorb.']);
        exit;
    }

    // Bestellung speichern
    mysqli_begin_transaction($conn);
    try {
        $ins = mysqli_prepare($conn, "INSERT INTO orders (user_id, zahlung_id, gesamt) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($ins, "iid", $userId, $zahlungId, $total);
        mysqli_stmt_execute($ins);
        $orderId = mysqli_insert_id($conn);

        $insItem = mysqli_prepare($conn, "INSERT INTO order_items (order_id, product_id, menge, einzelpreis) VALUES (?, ?, ?, ?)");
        foreach ($items as $it) {
            mysqli_stmt_bind_param($insItem, "iiid", $orderId, $it['product_id'], $it['menge'], $it['einzelpreis']);
            mysqli_stmt_execute($insItem);
        }

        mysqli_commit($conn);
    } catch (Exception $e) {
        mysqli_rollback($conn); // Damit nur ganze Bestellungen gespeichert werden
        echo json_encode(['success' => false, 'message' => 'Bestellung fehlgeschlagen. Bitte später erneut versuchen.']);
        exit;
    }

    // Warenkorb leeren
    $_SESSION['cart'] = [];

    echo json_encode(['success' => true, 'orderId' => $orderId, 'gesamt' => $total]);
    exit;
}

// Eigene Bestellungen
if ($action === 'getOrders') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt.']);
        exit;
    }
    $userId = (int)$_SESSION['user_id'];

    $ostmt = mysqli_prepare($conn, "SELECT id, gesamt, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC");
    mysqli_stmt_bind_param($ostmt, "i", $userId);
    mysqli_stmt_execute($ostmt);
    $ores = mysqli_stmt_get_result($ostmt);

    $istmt = mysqli_prepare($conn, "SELECT p.name, oi.menge, oi.einzelpreis FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
    $orders = [];
    while ($o = mysqli_fetch_assoc($ores)) {
        $orderId = (int)$o['id'];
        mysqli_stmt_bind_param($istmt, "i", $orderId);
        mysqli_stmt_execute($istmt);
        $ires = mysqli_stmt_get_result($istmt);

        $items = [];
        while ($it = mysqli_fetch_assoc($ires)) {
            $items[] = ['name' => $it['name'], 'menge' => (int)$it['menge'], 'einzelpreis' => (float)$it['einzelpreis']];
        }

        $orders[] = [
            'id' => $orderId,
            'gesamt' => (float)$o['gesamt'],
            'status' => $o['status'],
            'created_at' => $o['created_at'],
            'items' => $items
        ];
    }

    echo json_encode(['success' => true, 'orders' => $orders]);
    exit;
}

// Rechnungsdaten von eigenen Bestellungen
if ($action === 'getInvoice') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt.']);
        exit;
    }
    $userId = (int)$_SESSION['user_id'];
    $orderId = (int)($_GET['order_id'] ?? 0);

    // Check ob bestellung zum user gehört
    $ostmt = mysqli_prepare($conn, "SELECT id, gesamt, created_at FROM orders WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($ostmt, "ii", $orderId, $userId);
    mysqli_stmt_execute($ostmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($ostmt));
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Bestellung nicht gefunden.']);
        exit;
    }

    // Kundendaten
    $ustmt = mysqli_prepare($conn, "SELECT anrede, vorname, nachname FROM users WHERE id = ?");
    mysqli_stmt_bind_param($ustmt, "i", $userId);
    mysqli_stmt_execute($ustmt);
    $kunde = mysqli_fetch_assoc(mysqli_stmt_get_result($ustmt));

    // Lieferadresse
    $astmt = mysqli_prepare($conn, "SELECT strasse, plz, ort FROM addresses WHERE user_id = ? AND address_type = 'shipping' ORDER BY is_default DESC, id ASC LIMIT 1");
    mysqli_stmt_bind_param($astmt, "i", $userId);
    mysqli_stmt_execute($astmt);
    $adresse = mysqli_fetch_assoc(mysqli_stmt_get_result($astmt));

    // Positionen
    $istmt = mysqli_prepare($conn, "SELECT p.name, oi.menge, oi.einzelpreis FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
    mysqli_stmt_bind_param($istmt, "i", $orderId);
    mysqli_stmt_execute($istmt);
    $ires = mysqli_stmt_get_result($istmt);
    $items = [];
    while ($it = mysqli_fetch_assoc($ires)) {
        $items[] = ['name' => $it['name'], 'menge' => (int)$it['menge'], 'einzelpreis' => (float)$it['einzelpreis']];
    }

    echo json_encode([
        'success' => true,
        'order' => ['id' => (int)$order['id'], 'gesamt' => (float)$order['gesamt'], 'created_at' => $order['created_at']],
        'kunde' => $kunde,
        'adresse' => $adresse,
        'items' => $items
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);

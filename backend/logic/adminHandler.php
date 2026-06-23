<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';
require_once __DIR__ . '/../config/dataHandler.php';
bootstrapApi();
requireAdmin();

$dataHandler = new DataHandler();
$conn = $dataHandler->getConnection();
$method = requestMethod();

if ($method === 'getProducts') {
    $result = $conn->query("SELECT p.id, p.category_id, p.name, p.description, p.price, p.rating, p.is_active, pi.image_path
                            FROM products p LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1 ORDER BY p.id DESC");
    respond(['success' => true, 'products' => $result->fetch_all(MYSQLI_ASSOC)]);
}

if ($method === 'saveProduct') {
    $id = max(0, (int) ($_POST['id'] ?? 0));
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($categoryId < 1 || $name === '' || $description === '' || $price === false || $price < 0 || $rating === false || $rating < 0 || $rating > 5) {
        respond(['success' => false, 'message' => 'Bitte gültige Produktdaten angeben.'], 422);
    }
    $conn->begin_transaction();
    try {
        if ($id > 0) {
            $stmt = $conn->prepare('UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, rating = ? WHERE id = ?');
            $stmt->bind_param('issddi', $categoryId, $name, $description, $price, $rating, $id);
            $stmt->execute();
        } else {
            $slug = slugify($name) . '-' . bin2hex(random_bytes(3));
            $sku = 'EV-' . strtoupper(bin2hex(random_bytes(4)));
            $stmt = $conn->prepare('INSERT INTO products (category_id, sku, name, slug, description, price, rating, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, 999)');
            $stmt->bind_param('issssdd', $categoryId, $sku, $name, $slug, $description, $price, $rating);
            $stmt->execute();
            $id = $conn->insert_id;
        }
        if (!empty($_FILES['image']['name'])) {
            saveProductImage($conn, $id, $_FILES['image'], $name);
        }
        $conn->commit();
        respond(['success' => true, 'id' => $id]);
    } catch (Throwable $exception) {
        $conn->rollback();
        respond(['success' => false, 'message' => $exception->getMessage()], 422);
    }
}

if ($method === 'setProductActive') {
    $id = (int) ($_POST['id'] ?? 0);
    $active = (int) (bool) ($_POST['active'] ?? false);
    $stmt = $conn->prepare('UPDATE products SET is_active = ? WHERE id = ?');
    $stmt->bind_param('ii', $active, $id);
    $stmt->execute();
    respond(['success' => true]);
}

if ($method === 'deleteProduct') {
    $id = max(0, (int) ($_POST['id'] ?? 0));
    if ($id === 0) {
        respond(['success' => false, 'message' => 'Ungültiges Produkt.'], 422);
    }

    $imageStmt = $conn->prepare('SELECT image_path FROM product_images WHERE product_id = ?');
    $imageStmt->bind_param('i', $id);
    $imageStmt->execute();
    $imagePaths = array_column($imageStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'image_path');

    $delete = $conn->prepare('DELETE FROM products WHERE id = ?');
    $delete->bind_param('i', $id);
    $delete->execute();
    if ($delete->affected_rows === 0) {
        respond(['success' => false, 'message' => 'Produkt nicht gefunden.'], 404);
    }

    $productImageDirectory = realpath(__DIR__ . '/../../frontend/assets/products');
    foreach ($imagePaths as $imagePath) {
        $file = realpath(__DIR__ . '/../../frontend/' . $imagePath);
        if ($file && $productImageDirectory && str_starts_with($file, $productImageDirectory . DIRECTORY_SEPARATOR)) {
            @unlink($file);
        }
    }

    respond(['success' => true]);
}

if ($method === 'getCustomers') {
    $result = $conn->query("SELECT u.id, u.vorname, u.nachname, u.email, u.benutzername, u.aktiv,
                                  COUNT(DISTINCT o.id) AS orders_count
                           FROM users u LEFT JOIN orders o ON o.user_id = u.id
                           WHERE u.rolle = 'user' GROUP BY u.id ORDER BY u.nachname, u.vorname");
    respond(['success' => true, 'customers' => $result->fetch_all(MYSQLI_ASSOC)]);
}

if ($method === 'setCustomerActive') {
    $id = (int) ($_POST['id'] ?? 0);
    $active = (int) (bool) ($_POST['active'] ?? false);
    $stmt = $conn->prepare("UPDATE users SET aktiv = ?, remember_token = IF(? = 0, NULL, remember_token) WHERE id = ? AND rolle = 'user'");
    $stmt->bind_param('iii', $active, $active, $id);
    $stmt->execute();
    respond(['success' => true]);
}

if ($method === 'getCustomerOrders') {
    $customerId = (int) ($_GET['customer_id'] ?? 0);
    $stmt = $conn->prepare('SELECT o.id order_id, o.created_at, o.gesamt, oi.id item_id, oi.produktname, oi.menge, oi.einzelpreis FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE o.user_id = ? ORDER BY o.created_at DESC, oi.id');
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    respond(['success' => true, 'items' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

if ($method === 'removeOrderItem') {
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT order_id, menge, einzelpreis FROM order_items WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        if (!$item) throw new DomainException('Position nicht gefunden.');
        $orderId = (int) $item['order_id'];
        $orderStmt = $conn->prepare('SELECT gutschein_id, zwischensumme, gutscheinbetrag FROM orders WHERE id = ? FOR UPDATE');
        $orderStmt->bind_param('i', $orderId);
        $orderStmt->execute();
        $order = $orderStmt->get_result()->fetch_assoc();
        if (!$order) throw new DomainException('Bestellung nicht gefunden.');

        $delete = $conn->prepare('DELETE FROM order_items WHERE id = ?');
        $delete->bind_param('i', $itemId);
        $delete->execute();

        $difference = (float) $item['menge'] * (float) $item['einzelpreis'];
        $newSubtotal = max(0.0, (float) $order['zwischensumme'] - $difference);
        $oldVoucherAmount = (float) $order['gutscheinbetrag'];
        $newVoucherAmount = min($oldVoucherAmount, $newSubtotal);
        $voucherRefund = max(0.0, $oldVoucherAmount - $newVoucherAmount);
        $newTotal = max(0.0, $newSubtotal - $newVoucherAmount);

        if ($voucherRefund > 0 && $order['gutschein_id'] !== null) {
            $voucherId = (int) $order['gutschein_id'];
            $voucherUpdate = $conn->prepare('UPDATE vouchers SET remaining_value = LEAST(initial_value, remaining_value + ?) WHERE id = ?');
            $voucherUpdate->bind_param('di', $voucherRefund, $voucherId);
            $voucherUpdate->execute();
        }

        $update = $conn->prepare('UPDATE orders SET zwischensumme = ?, gutscheinbetrag = ?, gesamt = ? WHERE id = ?');
        $update->bind_param('dddi', $newSubtotal, $newVoucherAmount, $newTotal, $orderId);
        $update->execute();
        $conn->commit();
        respond(['success' => true]);
    } catch (Throwable $exception) {
        $conn->rollback();
        respond(['success' => false, 'message' => $exception->getMessage()], 422);
    }
}

if ($method === 'getVouchers') {
    $result = $conn->query("SELECT id, code, initial_value, remaining_value, expires_at,
                                  CASE WHEN expires_at < CURRENT_DATE THEN 'abgelaufen' WHEN remaining_value <= 0 THEN 'eingelöst' ELSE 'aktiv' END status
                           FROM vouchers ORDER BY created_at DESC");
    respond(['success' => true, 'vouchers' => $result->fetch_all(MYSQLI_ASSOC)]);
}

if ($method === 'createVoucher') {
    $value = filter_var($_POST['value'] ?? null, FILTER_VALIDATE_FLOAT);
    $expires = trim((string) ($_POST['expires_at'] ?? ''));
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $expires);
    if ($value === false || $value <= 0) {
        respond(['success' => false, 'message' => 'Der Wert ist ungültig.'], 422);
    }
    if (!$date || $date->format('Y-m-d') !== $expires || $date < new DateTimeImmutable('today')) {
        respond(['success' => false, 'message' => 'Das Ablaufdatum ist ungültig.'], 422);
    }
    do {
        $code = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 5);
        $stmt = $conn->prepare('SELECT id FROM vouchers WHERE code = ?');
        $stmt->bind_param('s', $code);
        $stmt->execute();
    } while ($stmt->get_result()->fetch_assoc());
    $insert = $conn->prepare('INSERT INTO vouchers (code, initial_value, remaining_value, expires_at) VALUES (?, ?, ?, ?)');
    $insert->bind_param('sdds', $code, $value, $value, $expires);
    $insert->execute();
    respond(['success' => true, 'code' => $code]);
}

respond(['success' => false, 'message' => 'Ungültige Aktion.'], 400);

function slugify(string $value): string
{
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    return trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $value)), '-');
}

function saveProductImage(mysqli $conn, int $productId, array $file, string $name): void
{
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) throw new DomainException('Bild-Upload fehlgeschlagen oder Datei zu groß.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) throw new DomainException('Nur JPG, PNG und WebP sind erlaubt.');
    $filename = 'product-' . $productId . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $target = __DIR__ . '/../../frontend/assets/products/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) throw new RuntimeException('Bild konnte nicht gespeichert werden.');
    $path = 'assets/products/' . $filename;
    $conn->query('UPDATE product_images SET is_primary = 0 WHERE product_id = ' . $productId);
    $stmt = $conn->prepare('INSERT INTO product_images (product_id, image_path, alt_text, is_primary) VALUES (?, ?, ?, 1)');
    $stmt->bind_param('iss', $productId, $path, $name);
    $stmt->execute();
}

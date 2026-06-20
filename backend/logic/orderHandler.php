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
        $ins = mysqli_prepare($conn, "INSERT INTO orders (user_id, gesamt) VALUES (?, ?)");
        mysqli_stmt_bind_param($ins, "id", $userId, $total);
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

echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);

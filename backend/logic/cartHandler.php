<?php

session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/DBAccess.php";

$conn = DBAccess::getInstance()->getConnection();
$action = $_REQUEST['action'] ?? '';

// Warenkorb in der Session initialisieren
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Produkt in den Warenkorb legen 
if ($action === 'addToCart') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);
    if ($qty < 1) {
        $qty = 1;
    }

    if ($productId > 0) {
        $current = $_SESSION['cart'][$productId] ?? 0;
        $_SESSION['cart'][$productId] = $current + $qty;
    }

    echo json_encode(['count' => getCartCount()]);
    exit;
}

// Menge eines Produkts setzen im Warenkorb (mindestens 1)
if ($action === 'updateCart') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);

    if ($productId > 0 && isset($_SESSION['cart'][$productId])) {
        if ($qty < 1) {
            $qty = 1;
        }
        $_SESSION['cart'][$productId] = $qty;
    }

    echo json_encode(getCart($conn));
    exit;
}

// Produkt aus dem Warenkorb entfernen
if ($action === 'removeFromCart') {
    $productId = (int)($_POST['product_id'] ?? 0);
    unset($_SESSION['cart'][$productId]);

    echo json_encode(getCart($conn));
    exit;
}

// Warenkorb liefern
if ($action === 'getCart') {
    echo json_encode(getCart($conn));
    exit;
}

// cardCount für die Zahl neben Warenkorb
if ($action === 'getCartCount') {
    echo json_encode(['count' => getCartCount()]);
    exit;
}

echo json_encode(['error' => 'Ungültige Aktion']);


// Summe 
function getCartCount() {
    $count = 0;
    foreach ($_SESSION['cart'] as $qty) {
        $count += $qty;
    }
    return $count;
}

// Baut den Warenkorb Inhalt 
function getCart($conn) {
    $items = [];
    $total = 0.0;

    $stmt = mysqli_prepare($conn, "
        SELECT p.id, p.name, p.price, p.currency, pi.image_path
        FROM products p
        LEFT JOIN product_images pi
            ON p.id = pi.product_id AND pi.is_primary = 1
        WHERE p.id = ? AND p.is_active = 1
    ");

    foreach ($_SESSION['cart'] as $productId => $qty) {
        mysqli_stmt_bind_param($stmt, "i", $productId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        // Produkt nicht mehr aktiv -> entfernen
        if (!$row) {
            unset($_SESSION['cart'][$productId]);
            continue;
        }

        $lineTotal = (float)$row['price'] * $qty;
        $total += $lineTotal;

        $items[] = [
            'product_id' => (int)$row['id'],
            'name' => $row['name'],
            'price' => (float)$row['price'],
            'currency' => $row['currency'],
            'image_path' => $row['image_path'],
            'qty' => $qty,
            'line_total' => $lineTotal
        ];
    }

    return [
        'items' => $items,
        'total' => $total,
        'count' => getCartCount()
    ];
}
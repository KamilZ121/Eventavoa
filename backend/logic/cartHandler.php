<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';
require_once __DIR__ . '/../config/dataHandler.php';
bootstrapApi();

$dataHandler = new DataHandler();
$method = requestMethod();
$_SESSION['cart'] ??= [];

if ($method === 'addToCart') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $quantity = max(1, (int) ($_POST['qty'] ?? 1));
    if ($productId < 1 || !$dataHandler->getProductById($productId)) {
        respond(['success' => false, 'message' => 'Produkt nicht verfügbar.'], 404);
    }
    $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + $quantity;
    respond(['success' => true, 'count' => cartCount()]);
}

if ($method === 'updateCart') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $quantity = (int) ($_POST['qty'] ?? 1);
    if (isset($_SESSION['cart'][$productId])) {
        if ($quantity > 0) {
            $_SESSION['cart'][$productId] = $quantity;
        } else {
            unset($_SESSION['cart'][$productId]);
        }
    }
    respond(cartContents($dataHandler));
}

if ($method === 'removeFromCart') {
    unset($_SESSION['cart'][(int) ($_POST['product_id'] ?? 0)]);
    respond(cartContents($dataHandler));
}

if ($method === 'getCart') respond(cartContents($dataHandler));
if ($method === 'getCartCount') respond(['success' => true, 'count' => cartCount()]);
respond(['success' => false, 'message' => 'Ungültige Aktion.'], 400);

function cartCount(): int
{
    return array_sum(array_map('intval', $_SESSION['cart']));
}

function cartContents(DataHandler $dataHandler): array
{
    $items = []; $total = 0.0;
    foreach ($_SESSION['cart'] as $productId => $quantity) {
        $product = $dataHandler->getProductById((int) $productId);
        if (!$product) { unset($_SESSION['cart'][$productId]); continue; }
        $quantity = (int) $quantity;
        $lineTotal = $product->price * $quantity;
        $total += $lineTotal;
        $items[] = ['product_id' => $product->id, 'name' => $product->name, 'price' => $product->price,
            'currency' => $product->currency, 'image_path' => $product->image_path,
            'qty' => $quantity, 'line_total' => $lineTotal];
    }
    return ['success' => true, 'items' => $items, 'total' => $total, 'count' => cartCount()];
}

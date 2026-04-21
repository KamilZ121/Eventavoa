<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/DBAccess.php";
require_once __DIR__ . "/../models/product.class.php";

$conn = DBAccess::getInstance()->getConnection();
$action = $_GET['action'] ?? '';

if ($action === 'getCategories') {
    $sql = "SELECT id, name, slug FROM categories ORDER BY name";
    $result = mysqli_query($conn, $sql);

    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }

    echo json_encode($categories);
    exit;
}

if ($action === 'getProducts') {
    $categoryId = $_GET['category_id'] ?? '';
    $search = $_GET['search'] ?? '';

    $sql = "
        SELECT 
            p.id,
            p.category_id,
            p.sku,
            p.name,
            p.slug,
            p.description,
            p.price,
            p.currency,
            p.stock_quantity,
            pi.image_path
        FROM products p
        LEFT JOIN product_images pi
            ON p.id = pi.product_id AND pi.is_primary = 1
        WHERE p.is_active = 1
    ";

    if ($categoryId !== '') {
        $categoryId = (int)$categoryId;
        $sql .= " AND p.category_id = $categoryId";
    }

    if ($search !== '') {
        $search = mysqli_real_escape_string($conn, $search);
        $sql .= " AND (p.name LIKE '%$search%' OR p.description LIKE '%$search%')";
    }

    $sql .= " ORDER BY p.name ASC";

    $result = mysqli_query($conn, $sql);

    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $product = new Product($row);

        $products[] = [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'price' => $product->price,
            'currency' => $product->currency,
            'stock_quantity' => $product->stock_quantity,
            'image_path' => $product->image_path
        ];
    }

    echo json_encode($products);
    exit;
}

echo json_encode(['error' => 'Ungültige Aktion']);
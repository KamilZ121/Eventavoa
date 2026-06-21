<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/DBAccess.php";
require_once __DIR__ . "/../models/product.class.php";

$conn = DBAccess::getInstance()->getConnection();
$action = $_GET['action'] ?? '';

if ($action === 'getCategories') {
    $sql = "SELECT id, name FROM categories ORDER BY name";
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
            p.name,
            p.description,
            p.price,
            p.currency,
            pi.image_path
        FROM products p
        LEFT JOIN product_images pi
            ON p.id = pi.product_id AND pi.is_primary = 1
        WHERE p.is_active = 1
    ";


    $types = "";
    $params = [];

    if ($categoryId !== '') {
        $sql .= " AND p.category_id = ?";
        $types .= "i";
        $params[] = (int)$categoryId;
    }

    if ($search !== '') {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $like = "%" . $search . "%";
        $types .= "ss";
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY p.name ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== "") {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {

        $products[] = new Product($row);
    }

    // public Properties der Objekte werden automatisch zu JSON
    echo json_encode($products);
    exit;
}

echo json_encode(['error' => 'Ungültige Aktion']);
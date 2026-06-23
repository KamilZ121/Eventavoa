<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';
require_once __DIR__ . '/../config/dataHandler.php';
bootstrapApi();

$dataHandler = new DataHandler();
$method = requestMethod();

if ($method === 'getCategories') {
    respond(['success' => true, 'categories' => $dataHandler->getCategories()]);
}

if ($method === 'getProducts') {
    $categoryId = max(0, (int) ($_GET['category_id'] ?? 0));
    $search = trim((string) ($_GET['search'] ?? ''));
    respond(['success' => true, 'products' => $dataHandler->getProducts($categoryId, $search)]);
}

respond(['success' => false, 'message' => 'Ungültige Aktion.'], 400);

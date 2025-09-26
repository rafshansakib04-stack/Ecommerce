<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$db = Database::getInstance();

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$lowStockOnly = isset($_GET['low_stock']) && $_GET['low_stock'] == '1';

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($category)) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $category;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$products = $db->fetchAll("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $whereClause
    ORDER BY p.created_at DESC
");

// Filter low stock if requested
if ($lowStockOnly) {
    $products = array_values(array_filter($products, function ($p) {
        return (int)$p['stock_quantity'] <= (int)$p['min_stock_level'];
    }));
}

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="products_' . date('Y-m-d') . '.xls"');

echo "Products Export\n";
echo "Generated on: " . date('Y-m-d H:i:s') . "\n\n";

echo "Name\tSKU\tCategory\tPrice\tCost Price\tStock\tMin Stock\tUnit\tStatus\tCreated\n";

foreach ($products as $product) {
    echo ($product['name'] ?? '') . "\t";
    echo ($product['sku'] ?? '') . "\t";
    echo ($product['category_name'] ?? '') . "\t";
    echo '₹' . number_format((float)$product['price'], 2) . "\t";
    echo '₹' . number_format((float)($product['cost_price'] ?? 0), 2) . "\t";
    echo (string)$product['stock_quantity'] . "\t";
    echo (string)$product['min_stock_level'] . "\t";
    echo ($product['unit'] ?? '') . "\t";
    echo ($product['status'] ?? '') . "\t";
    echo ($product['created_at'] ?? '') . "\n";
}

exit();
?>


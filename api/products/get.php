<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $productId = $_GET['id'] ?? null;
    if (!$productId) {
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        exit();
    }

    $db = Database::getInstance();

    $product = $db->fetchOne("
        SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ", [$productId]);

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }

    $totals = $db->fetchOne("
        SELECT 
            COALESCE(SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE 0 END), 0) AS total_in,
            COALESCE(SUM(CASE WHEN movement_type = 'out' THEN quantity ELSE 0 END), 0) AS total_out
        FROM stock_movements
        WHERE product_id = ?
    ", [$productId]);

    $product['total_in'] = $totals['total_in'] ?? 0;
    $product['total_out'] = $totals['total_out'] ?? 0;

    echo json_encode(['success' => true, 'data' => $product]);
} catch (Exception $e) {
    error_log('Get product error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>


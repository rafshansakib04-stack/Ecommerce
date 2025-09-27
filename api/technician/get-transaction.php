<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is technician
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'technician') {
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
    $transactionId = $_GET['id'] ?? null;
    
    if (!$transactionId) {
        echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
        exit();
    }
    
    $db = Database::getInstance();
    
    // Get transaction details
    $transaction = $db->fetchOne("
        SELECT * FROM daily_cashbook 
        WHERE id = ? AND user_id = ?
    ", [$transactionId, $_SESSION['user_id']]);
    
    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $transaction
    ]);
    
} catch (Exception $e) {
    error_log('Get transaction error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
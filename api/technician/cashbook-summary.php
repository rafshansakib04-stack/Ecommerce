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
    $db = Database::getInstance();
    
    $today = date('Y-m-d');
    $month = date('Y-m');
    
    // Get summary data
    $summary = [
        'today_income' => $db->fetchOne("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM daily_cashbook 
            WHERE user_id = ? AND transaction_type = 'income' AND DATE(transaction_date) = ?
        ", [$_SESSION['user_id'], $today])['total'],
        
        'today_expense' => $db->fetchOne("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM daily_cashbook 
            WHERE user_id = ? AND transaction_type = 'expense' AND DATE(transaction_date) = ?
        ", [$_SESSION['user_id'], $today])['total'],
        
        'monthly_income' => $db->fetchOne("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM daily_cashbook 
            WHERE user_id = ? AND transaction_type = 'income' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
        ", [$_SESSION['user_id'], $month])['total'],
        
        'monthly_expense' => $db->fetchOne("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM daily_cashbook 
            WHERE user_id = ? AND transaction_type = 'expense' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
        ", [$_SESSION['user_id'], $month])['total']
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $summary
    ]);
    
} catch (Exception $e) {
    error_log('Cashbook summary error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
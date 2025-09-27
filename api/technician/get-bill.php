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
    $billId = $_GET['id'] ?? null;
    
    if (!$billId) {
        echo json_encode(['success' => false, 'message' => 'Bill ID is required']);
        exit();
    }
    
    $db = Database::getInstance();
    
    // Get technician ID
    $technician = $db->fetchOne("SELECT id FROM technicians WHERE user_id = ?", [$_SESSION['user_id']]);
    
    if (!$technician) {
        echo json_encode(['success' => false, 'message' => 'Technician not found']);
        exit();
    }
    
    // Get bill details
    $bill = $db->fetchOne("
        SELECT * FROM technician_bills 
        WHERE id = ? AND technician_id = ?
    ", [$billId, $technician['id']]);
    
    if (!$bill) {
        echo json_encode(['success' => false, 'message' => 'Bill not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $bill
    ]);
    
} catch (Exception $e) {
    error_log('Get bill error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $serviceIds = $_POST['service_ids'] ?? [];
    
    if (empty($serviceIds) || !is_array($serviceIds)) {
        echo json_encode(['success' => true, 'has_updates' => false]);
        exit();
    }
    
    $db = Database::getInstance();
    
    // Get customer ID
    $customer = $db->fetchOne("SELECT id FROM customers WHERE user_id = ?", [$_SESSION['user_id']]);
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit();
    }
    
    // Check for updates in the last 5 minutes
    $fiveMinutesAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    
    $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
    $params = array_merge([$customer['id']], $serviceIds);
    
    $updatedServices = $db->fetchAll("
        SELECT id, status, updated_at 
        FROM service_requests 
        WHERE customer_id = ? AND id IN ($placeholders) 
        AND updated_at > ?
        ORDER BY updated_at DESC
    ", array_merge($params, [$fiveMinutesAgo]));
    
    $hasUpdates = !empty($updatedServices);
    
    echo json_encode([
        'success' => true,
        'has_updates' => $hasUpdates,
        'updated_services' => $updatedServices
    ]);
    
} catch (Exception $e) {
    error_log('Check updates error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $serviceId = $_GET['id'] ?? null;
    
    if (!$serviceId) {
        echo json_encode(['success' => false, 'message' => 'Service ID is required']);
        exit();
    }
    
    $db = Database::getInstance();
    
    // Get customer ID
    $customer = $db->fetchOne("SELECT id FROM customers WHERE user_id = ?", [$_SESSION['user_id']]);
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit();
    }
    
    // Get service request details
    $service = $db->fetchOne("
        SELECT sr.*, t.full_name as technician_name, t.phone as technician_phone
        FROM service_requests sr 
        LEFT JOIN technicians t ON sr.assigned_technician_id = t.id 
        WHERE sr.id = ? AND sr.customer_id = ?
    ", [$serviceId, $customer['id']]);
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Service request not found']);
        exit();
    }
    
    // Get attachments
    $attachments = $db->fetchAll("
        SELECT * FROM service_attachments 
        WHERE service_request_id = ? 
        ORDER BY uploaded_at DESC
    ", [$serviceId]);
    
    $service['attachments'] = $attachments;
    
    echo json_encode([
        'success' => true,
        'data' => $service
    ]);
    
} catch (Exception $e) {
    error_log('Get service request error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
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
    $serviceId = $_GET['service_id'] ?? null;
    
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
    $serviceRequest = $db->fetchOne("
        SELECT sr.*, t.id as technician_id
        FROM service_requests sr 
        LEFT JOIN technicians t ON sr.assigned_technician_id = t.id 
        WHERE sr.id = ? AND sr.customer_id = ?
    ", [$serviceId, $customer['id']]);
    
    if (!$serviceRequest) {
        echo json_encode(['success' => false, 'message' => 'Service request not found']);
        exit();
    }
    
    if (!$serviceRequest['technician_id']) {
        echo json_encode(['success' => false, 'message' => 'Technician not assigned yet']);
        exit();
    }
    
    // Get technician's latest location
    $location = $db->fetchOne("
        SELECT * FROM technician_locations 
        WHERE technician_id = ? AND service_request_id = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ", [$serviceRequest['technician_id'], $serviceId]);
    
    if (!$location) {
        echo json_encode(['success' => false, 'message' => 'Location data not available']);
        exit();
    }
    
    // Get recent status updates
    $statusUpdates = $db->fetchAll("
        SELECT * FROM service_status_updates 
        WHERE service_request_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ", [$serviceId]);
    
    // Calculate estimated arrival (simplified calculation)
    $estimatedArrival = null;
    if ($location['speed'] > 0) {
        // This is a simplified calculation - in real implementation, you'd use Google Maps API
        $estimatedArrival = date('H:i', strtotime('+30 minutes')); // Placeholder
    }
    
    $response = [
        'success' => true,
        'data' => [
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'address' => $location['address'],
            'speed' => $location['speed'],
            'heading' => $location['heading'],
            'accuracy' => $location['accuracy'],
            'timestamp' => $location['created_at'],
            'estimated_arrival' => $estimatedArrival
        ],
        'status_updates' => $statusUpdates
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('Get technician location error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
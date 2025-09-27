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
    $serviceId = $_POST['service_id'] ?? null;
    $rating = $_POST['rating'] ?? null;
    $feedback = $_POST['feedback'] ?? '';
    
    if (!$serviceId || !$rating) {
        echo json_encode(['success' => false, 'message' => 'Service ID and rating are required']);
        exit();
    }
    
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
        exit();
    }
    
    $db = Database::getInstance();
    
    // Get customer ID
    $customer = $db->fetchOne("SELECT id FROM customers WHERE user_id = ?", [$_SESSION['user_id']]);
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit();
    }
    
    // Check if service request exists and belongs to customer
    $service = $db->fetchOne("
        SELECT id, status FROM service_requests 
        WHERE id = ? AND customer_id = ? AND status = 'completed'
    ", [$serviceId, $customer['id']]);
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Service request not found or not completed']);
        exit();
    }
    
    // Update service request with rating
    $db->update('service_requests', [
        'customer_rating' => $rating,
        'customer_feedback' => $feedback,
        'rated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$serviceId]);
    
    // Update technician rating if assigned
    $technician = $db->fetchOne("
        SELECT assigned_technician_id FROM service_requests WHERE id = ?
    ", [$serviceId]);
    
    if ($technician && $technician['assigned_technician_id']) {
        // Calculate new average rating for technician
        $avgRating = $db->fetchOne("
            SELECT AVG(customer_rating) as avg_rating 
            FROM service_requests 
            WHERE assigned_technician_id = ? AND customer_rating IS NOT NULL
        ", [$technician['assigned_technician_id']])['avg_rating'];
        
        // Update technician's average rating
        $db->update('technicians', [
            'average_rating' => $avgRating
        ], 'id = ?', [$technician['assigned_technician_id']]);
    }
    
    logActivity($_SESSION['user_id'], 'service_rated', "Rated service request ID: $serviceId with rating: $rating");
    
    echo json_encode([
        'success' => true,
        'message' => 'Rating submitted successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Rate service error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
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
    $technicianId = $_GET['id'] ?? null;
    
    if (!$technicianId) {
        echo json_encode(['success' => false, 'message' => 'Technician ID is required']);
        exit();
    }
    
    $db = Database::getInstance();
    
    // Get technician details
    $technician = $db->fetchOne("
        SELECT t.*, u.username, u.email, u.phone, u.status as user_status,
               (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_technician_id = t.id) as total_services,
               (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_technician_id = t.id AND sr.status = 'completed') as completed_services,
               (SELECT AVG(sr.customer_rating) FROM service_requests sr WHERE sr.assigned_technician_id = t.id AND sr.customer_rating IS NOT NULL) as avg_rating
        FROM technicians t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.id = ?
    ", [$technicianId]);
    
    if (!$technician) {
        echo json_encode(['success' => false, 'message' => 'Technician not found']);
        exit();
    }
    
    // Get recent services
    $recentServices = $db->fetchAll("
        SELECT sr.*, c.company_name, c.contact_person
        FROM service_requests sr 
        JOIN customers c ON sr.customer_id = c.id 
        WHERE sr.assigned_technician_id = ? 
        ORDER BY sr.created_at DESC 
        LIMIT 10
    ", [$technicianId]);
    
    $technician['recent_services'] = $recentServices;
    
    echo json_encode([
        'success' => true,
        'data' => $technician
    ]);
    
} catch (Exception $e) {
    error_log('Get technician error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
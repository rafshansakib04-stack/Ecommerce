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
    $taskId = $_GET['id'] ?? null;
    
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Task ID is required']);
        exit();
    }
    
    $db = Database::getInstance();
    
    // Get task details
    $task = $db->fetchOne("
        SELECT sr.*, c.company_name, c.contact_person, c.phone as customer_phone, c.address
        FROM service_requests sr 
        JOIN customers c ON sr.customer_id = c.id 
        WHERE sr.id = ? AND sr.assigned_technician_id = (
            SELECT id FROM technicians WHERE user_id = ?
        )
    ", [$taskId, $_SESSION['user_id']]);
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found or not assigned to you']);
        exit();
    }
    
    // Get task attachments
    $attachments = $db->fetchAll("
        SELECT * FROM service_attachments 
        WHERE service_request_id = ? 
        ORDER BY uploaded_at DESC
    ", [$taskId]);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'task' => $task,
            'attachments' => $attachments
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Get task error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
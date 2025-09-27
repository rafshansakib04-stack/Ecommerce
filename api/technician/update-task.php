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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $taskId = $input['task_id'] ?? null;
    $status = $input['status'] ?? null;
    $notes = $input['notes'] ?? '';
    
    if (!$taskId || !$status) {
        echo json_encode(['success' => false, 'message' => 'Task ID and status are required']);
        exit();
    }
    
    $db = Database::getInstance();
    
    // Verify task belongs to this technician
    $task = $db->fetchOne("
        SELECT * FROM service_requests 
        WHERE id = ? AND assigned_technician_id = (
            SELECT id FROM technicians WHERE user_id = ?
        )
    ", [$taskId, $_SESSION['user_id']]);
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found or not assigned to you']);
        exit();
    }
    
    // Update task status
    $updateData = [
        'status' => $status,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Update timestamps based on status
    if ($status === 'in_progress' && !$task['actual_start_time']) {
        $updateData['actual_start_time'] = date('Y-m-d H:i:s');
    } elseif ($status === 'completed') {
        $updateData['actual_end_time'] = date('Y-m-d H:i:s');
    }
    
    $db->update('service_requests', $updateData, 'id = ?', [$taskId]);
    
    // Add note to activity log if provided
    if (!empty($notes)) {
        logActivity($_SESSION['user_id'], 'task_note_added', "Task $taskId: " . $notes);
    }
    
    // Log status change
    logActivity($_SESSION['user_id'], 'task_status_updated', "Task $taskId status changed to: " . $status);
    
    // Send real-time notification to admin
    $firebase = new FirebaseService();
    $firebase->updateRealtimeData('notifications/admin', [
        'title' => 'Task Status Updated',
        'message' => 'Task ' . $task['ticket_number'] . ' status updated to: ' . $status,
        'timestamp' => time(),
        'type' => 'info'
    ]);
    
    // Send notification to customer if completed
    if ($status === 'completed') {
        $customer = $db->fetchOne("
            SELECT c.*, u.email, u.phone 
            FROM customers c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.id = ?
        ", [$task['customer_id']]);
        
        if ($customer) {
            // Send email notification
            $emailSubject = "Service Completed - " . $task['ticket_number'];
            $emailMessage = "
            <html>
            <head><title>Service Completed</title></head>
            <body>
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #0d6efd;'>Service Completed</h2>
                    <p>Dear " . $customer['contact_person'] . ",</p>
                    <p>Your service request has been completed successfully.</p>
                    <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                        <p><strong>Ticket Number:</strong> " . $task['ticket_number'] . "</p>
                        <p><strong>Service Type:</strong> " . ucfirst($task['service_type']) . "</p>
                        <p><strong>Completed Date:</strong> " . date('d M Y H:i') . "</p>
                    </div>
                    <p>Please rate your service experience in your customer portal.</p>
                    <p>Thank you for choosing our services!</p>
                </div>
            </body>
            </html>";
            
            sendEmail($customer['email'], $emailSubject, $emailMessage);
            
            // Send SMS notification
            $smsMessage = "Service completed for ticket " . $task['ticket_number'] . ". Please rate your experience.";
            sendSMS($customer['phone'], $smsMessage);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Task status updated successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Update task error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
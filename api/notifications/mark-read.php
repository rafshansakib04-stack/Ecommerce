<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = Database::getInstance();

try {
    $user_id = $_SESSION['user_id'];
    $notification_id = $_POST['notification_id'] ?? null;
    $mark_all = $_POST['mark_all'] ?? false;
    
    if ($mark_all) {
        // Mark all notifications as read
        $db->execute("
            UPDATE notifications 
            SET is_read = 1 
            WHERE (user_id = ? OR user_id IS NULL) 
            AND is_read = 0
        ", [$user_id]);
    } elseif ($notification_id) {
        // Mark specific notification as read
        $db->execute("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? AND (user_id = ? OR user_id IS NULL)
        ", [$notification_id, $user_id]);
    } else {
        throw new Exception('Invalid request');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Notifications marked as read'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to mark notifications as read: ' . $e->getMessage()
    ]);
}
?>
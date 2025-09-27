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
    $clear_all = $_POST['clear_all'] ?? false;
    
    if ($clear_all) {
        // Clear all notifications
        $db->execute("
            DELETE FROM notifications 
            WHERE user_id = ? OR user_id IS NULL
        ", [$user_id]);
    } elseif ($notification_id) {
        // Clear specific notification
        $db->execute("
            DELETE FROM notifications 
            WHERE id = ? AND (user_id = ? OR user_id IS NULL)
        ", [$notification_id, $user_id]);
    } else {
        throw new Exception('Invalid request');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Notifications cleared'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to clear notifications: ' . $e->getMessage()
    ]);
}
?>
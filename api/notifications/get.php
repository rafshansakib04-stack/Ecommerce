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
    $limit = intval($_GET['limit'] ?? 20);
    $offset = intval($_GET['offset'] ?? 0);
    
    // Get notifications
    $notifications = $db->fetchAll("
        SELECT n.*, u.username as from_user
        FROM notifications n
        LEFT JOIN users u ON n.from_user_id = u.id
        WHERE n.user_id = ? OR n.user_id IS NULL
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ", [$user_id, $limit, $offset]);
    
    // Get unread count
    $unread_count = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE (user_id = ? OR user_id IS NULL) 
        AND is_read = 0
    ", [$user_id])['count'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch notifications: ' . $e->getMessage()
    ]);
}
?>
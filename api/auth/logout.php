<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

try {
    // Log activity
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    
    // Clear remember token if exists
    if (isset($_COOKIE['remember_token'])) {
        $db = Database::getInstance();
        $db->update('users', 
            ['remember_token' => null], 
            'remember_token = ?', 
            [$_COOKIE['remember_token']]
        );
        
        // Clear cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    // Destroy session
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Logout error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred during logout']);
}
?>
<?php
/**
 * Clear Logs Script
 * Clears error logs (admin only)
 */

require_once 'config/database.php';
require_once 'config/environment.php';

// Only allow in local environment
if (!Environment::isLocal()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input['action'] === 'clear_logs') {
        try {
            ErrorLogger::clearLogs();
            echo json_encode(['success' => true, 'message' => 'Logs cleared successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error clearing logs: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
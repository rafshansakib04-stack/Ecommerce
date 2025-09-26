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
    $customerId = $_GET['id'] ?? null;
    
    if (!$customerId) {
        echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
        exit();
    }
    
    $db = Database::getInstance();
    
    // Get customer details with user information
    $customer = $db->fetchOne("
        SELECT c.*, u.username, u.email, u.phone, u.status as user_status,
               (SELECT COUNT(*) FROM service_requests sr WHERE sr.customer_id = c.id) as total_services,
               (SELECT COALESCE(SUM(i.total_amount), 0) FROM invoices i WHERE i.customer_id = c.id AND i.status = 'paid') as total_paid
        FROM customers c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ", [$customerId]);
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit();
    }
    
    // Get recent service requests
    $recentServices = $db->fetchAll("
        SELECT sr.*, t.full_name as technician_name
        FROM service_requests sr 
        LEFT JOIN technicians t ON sr.assigned_technician_id = t.id
        WHERE sr.customer_id = ? 
        ORDER BY sr.created_at DESC 
        LIMIT 5
    ", [$customerId]);
    
    // Get recent invoices
    $recentInvoices = $db->fetchAll("
        SELECT * FROM invoices 
        WHERE customer_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ", [$customerId]);
    
    // Get payment history
    $paymentHistory = $db->fetchAll("
        SELECT p.*, i.invoice_number
        FROM payments p 
        LEFT JOIN invoices i ON p.invoice_id = i.id
        WHERE p.customer_id = ? 
        ORDER BY p.payment_date DESC 
        LIMIT 10
    ", [$customerId]);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'customer' => $customer,
            'recent_services' => $recentServices,
            'recent_invoices' => $recentInvoices,
            'payment_history' => $paymentHistory
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Get customer error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
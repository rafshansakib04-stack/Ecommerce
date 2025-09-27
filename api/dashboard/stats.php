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
    // Get date filters
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    
    $stats = [];
    
    // Total customers
    $stats['total_customers'] = $db->fetchOne("SELECT COUNT(*) as count FROM customers")['count'];
    
    // Total technicians
    $stats['total_technicians'] = $db->fetchOne("SELECT COUNT(*) as count FROM technicians WHERE status = 'active'")['count'];
    
    // Pending service requests
    $stats['pending_services'] = $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE status = 'pending'")['count'];
    
    // Today's services
    $stats['today_services'] = $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE DATE(created_at) = CURDATE()")['count'];
    
    // Monthly revenue
    $stats['monthly_revenue'] = $db->fetchOne("
        SELECT COALESCE(SUM(total_amount), 0) as revenue 
        FROM invoices 
        WHERE status = 'paid' 
        AND DATE(created_at) BETWEEN ? AND ?
    ", [$date_from, $date_to])['revenue'];
    
    // Low stock items
    $stats['low_stock'] = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level")['count'];
    
    // Revenue chart data
    $revenue_data = $db->fetchAll("
        SELECT DATE(created_at) as date, SUM(total_amount) as revenue 
        FROM invoices 
        WHERE status = 'paid' 
        AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at) 
        ORDER BY date
    ", [$date_from, $date_to]);
    
    // Service status distribution
    $service_status = $db->fetchAll("
        SELECT status, COUNT(*) as count 
        FROM service_requests 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY status
    ", [$date_from, $date_to]);
    
    // Top performing technicians
    $top_technicians = $db->fetchAll("
        SELECT t.name, COUNT(sr.id) as completed_services
        FROM technicians t
        LEFT JOIN service_requests sr ON t.id = sr.assigned_technician_id 
        AND sr.status = 'completed' 
        AND DATE(sr.created_at) BETWEEN ? AND ?
        GROUP BY t.id, t.name
        ORDER BY completed_services DESC
        LIMIT 5
    ", [$date_from, $date_to]);
    
    // Recent activities
    $recent_activities = $db->fetchAll("
        SELECT al.*, u.username 
        FROM activity_logs al 
        JOIN users u ON al.user_id = u.id 
        ORDER BY al.created_at DESC 
        LIMIT 10
    ");
    
    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'revenue_data' => $revenue_data,
            'service_status' => $service_status,
            'top_technicians' => $top_technicians,
            'recent_activities' => $recent_activities
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch dashboard statistics: ' . $e->getMessage()
    ]);
}
?>
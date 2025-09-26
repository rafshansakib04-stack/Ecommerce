<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = Database::getInstance();

try {
    // Get date filters
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    
    // Get dashboard data
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
        LIMIT 20
    ");
    
    // Set headers for CSV download
    $filename = 'dashboard_export_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // Write header
    fputcsv($output, ['Dashboard Export - ' . $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    
    // Write statistics
    fputcsv($output, ['STATISTICS']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Customers', $stats['total_customers']]);
    fputcsv($output, ['Active Technicians', $stats['total_technicians']]);
    fputcsv($output, ['Pending Services', $stats['pending_services']]);
    fputcsv($output, ['Today\'s Services', $stats['today_services']]);
    fputcsv($output, ['Monthly Revenue', '₹' . number_format($stats['monthly_revenue'])]);
    fputcsv($output, ['Low Stock Items', $stats['low_stock']]);
    fputcsv($output, []);
    
    // Write revenue data
    fputcsv($output, ['REVENUE DATA']);
    fputcsv($output, ['Date', 'Revenue']);
    foreach ($revenue_data as $row) {
        fputcsv($output, [$row['date'], '₹' . number_format($row['revenue'])]);
    }
    fputcsv($output, []);
    
    // Write service status
    fputcsv($output, ['SERVICE STATUS DISTRIBUTION']);
    fputcsv($output, ['Status', 'Count']);
    foreach ($service_status as $row) {
        fputcsv($output, [ucfirst($row['status']), $row['count']]);
    }
    fputcsv($output, []);
    
    // Write top technicians
    fputcsv($output, ['TOP PERFORMING TECHNICIANS']);
    fputcsv($output, ['Technician', 'Completed Services']);
    foreach ($top_technicians as $row) {
        fputcsv($output, [$row['name'], $row['completed_services']]);
    }
    fputcsv($output, []);
    
    // Write recent activities
    fputcsv($output, ['RECENT ACTIVITIES']);
    fputcsv($output, ['Timestamp', 'User', 'Action', 'Details']);
    foreach ($recent_activities as $row) {
        fputcsv($output, [
            $row['created_at'],
            $row['username'],
            $row['action'],
            $row['details']
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to export dashboard data: ' . $e->getMessage()
    ]);
}
?>
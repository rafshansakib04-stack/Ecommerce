<?php
/**
 * Admin Dashboard Metrics API
 * Real-time business metrics for admin dashboard
 */

header('Content-Type: application/json');
require_once '../../includes/functions.php';

// Require admin access
if (!hasAnyRole(['admin', 'super_admin'])) {
    errorResponse('Access denied', 403);
}

try {
    $db = getDB();
    
    // Get comprehensive business metrics
    $stmt = $db->prepare("
        SELECT 
            -- Today's metrics
            (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()) as today_orders,
            (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled') as today_sales,
            (SELECT COUNT(*) FROM users WHERE role = 'customer' AND DATE(created_at) = CURDATE()) as today_customers,
            (SELECT COUNT(*) FROM service_requests WHERE DATE(created_at) = CURDATE()) as today_services,
            
            -- This week's metrics
            (SELECT COUNT(*) FROM orders WHERE WEEK(created_at) = WEEK(NOW()) AND YEAR(created_at) = YEAR(NOW())) as week_orders,
            (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE WEEK(created_at) = WEEK(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status != 'cancelled') as week_sales,
            
            -- This month's metrics
            (SELECT COUNT(*) FROM orders WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())) as month_orders,
            (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status != 'cancelled') as month_sales,
            
            -- Total metrics
            (SELECT COUNT(*) FROM users WHERE role = 'customer') as total_customers,
            (SELECT COUNT(*) FROM products WHERE is_active = TRUE) as total_products,
            (SELECT COUNT(*) FROM orders) as total_orders,
            (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status != 'cancelled') as total_sales,
            
            -- Status breakdowns
            (SELECT COUNT(*) FROM orders WHERE status = 'pending') as pending_orders,
            (SELECT COUNT(*) FROM orders WHERE status = 'confirmed') as confirmed_orders,
            (SELECT COUNT(*) FROM orders WHERE status = 'processing') as processing_orders,
            (SELECT COUNT(*) FROM orders WHERE status = 'shipped') as shipped_orders,
            (SELECT COUNT(*) FROM orders WHERE status = 'delivered') as delivered_orders,
            
            -- Inventory alerts
            (SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_level AND is_active = TRUE) as low_stock_products,
            (SELECT COUNT(*) FROM products WHERE stock_quantity = 0 AND is_active = TRUE) as out_of_stock_products,
            
            -- Service metrics
            (SELECT COUNT(*) FROM service_requests WHERE status = 'pending') as pending_services,
            (SELECT COUNT(*) FROM service_requests WHERE status = 'in_progress') as active_services,
            (SELECT COUNT(*) FROM service_requests WHERE status = 'completed' AND DATE(created_at) = CURDATE()) as completed_services_today,
            
            -- Financial metrics
            (SELECT COALESCE(AVG(total_amount), 0) FROM orders WHERE status = 'delivered' AND MONTH(created_at) = MONTH(NOW())) as avg_order_value,
            (SELECT COUNT(DISTINCT user_id) FROM orders WHERE MONTH(created_at) = MONTH(NOW())) as active_customers_month
    ");
    $stmt->execute();
    $metrics = $stmt->fetch();
    
    // Calculate growth percentages (compared to previous period)
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)) as yesterday_orders,
            (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND status != 'cancelled') as yesterday_sales,
            (SELECT COUNT(*) FROM orders WHERE WEEK(created_at) = WEEK(NOW()) - 1 AND YEAR(created_at) = YEAR(NOW())) as last_week_orders,
            (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE WEEK(created_at) = WEEK(NOW()) - 1 AND YEAR(created_at) = YEAR(NOW()) AND status != 'cancelled') as last_week_sales
    ");
    $stmt->execute();
    $previousMetrics = $stmt->fetch();
    
    // Calculate trends
    $trends = [
        'orders_trend' => calculateGrowthPercentage($metrics['today_orders'], $previousMetrics['yesterday_orders']),
        'sales_trend' => calculateGrowthPercentage($metrics['today_sales'], $previousMetrics['yesterday_sales']),
        'weekly_orders_trend' => calculateGrowthPercentage($metrics['week_orders'], $previousMetrics['last_week_orders']),
        'weekly_sales_trend' => calculateGrowthPercentage($metrics['week_sales'], $previousMetrics['last_week_sales'])
    ];
    
    // Get hourly sales data for today
    $stmt = $db->prepare("
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as orders,
            COALESCE(SUM(total_amount), 0) as sales
        FROM orders 
        WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'
        GROUP BY HOUR(created_at)
        ORDER BY hour ASC
    ");
    $stmt->execute();
    $hourlySales = $stmt->fetchAll();
    
    // Get top customers this month
    $stmt = $db->prepare("
        SELECT 
            u.id,
            CONCAT(u.first_name, ' ', u.last_name) as name,
            u.email,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_spent
        FROM users u
        JOIN orders o ON u.id = o.user_id
        WHERE o.status != 'cancelled' 
        AND MONTH(o.created_at) = MONTH(NOW()) 
        AND YEAR(o.created_at) = YEAR(NOW())
        GROUP BY u.id
        ORDER BY total_spent DESC
        LIMIT 5
    ");
    $stmt->execute();
    $topCustomers = $stmt->fetchAll();
    
    // Get conversion funnel data
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(DISTINCT session_id) FROM analytics_events WHERE event_type = 'page_view' AND DATE(created_at) = CURDATE()) as website_visitors,
            (SELECT COUNT(DISTINCT session_id) FROM analytics_events WHERE event_type = 'product_view' AND DATE(created_at) = CURDATE()) as product_viewers,
            (SELECT COUNT(DISTINCT session_id) FROM analytics_events WHERE event_type = 'add_to_cart' AND DATE(created_at) = CURDATE()) as cart_additions,
            (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()) as orders_placed
    ");
    $stmt->execute();
    $conversionData = $stmt->fetch();
    
    successResponse('Dashboard metrics retrieved successfully', [
        'metrics' => $metrics,
        'trends' => $trends,
        'hourly_sales' => $hourlySales,
        'top_customers' => $topCustomers,
        'conversion_data' => $conversionData,
        'last_updated' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Dashboard metrics error: " . $e->getMessage());
    errorResponse('Failed to retrieve dashboard metrics');
}

function calculateGrowthPercentage($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    
    return round((($current - $previous) / $previous) * 100, 1);
}
?>
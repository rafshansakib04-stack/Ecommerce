<?php
/**
 * Admin Real-Time Dashboard
 * Comprehensive business analytics and management interface
 */

require_once '../includes/functions.php';

// Require admin access
requireRole('admin');

$user = getCurrentUser();
$db = getDB();

// Get real-time business metrics
$stmt = $db->prepare("
    SELECT 
        -- Sales metrics
        (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()) as today_orders,
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled') as today_sales,
        (SELECT COUNT(*) FROM orders WHERE WEEK(created_at) = WEEK(NOW()) AND YEAR(created_at) = YEAR(NOW())) as week_orders,
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE WEEK(created_at) = WEEK(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status != 'cancelled') as week_sales,
        (SELECT COUNT(*) FROM orders WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())) as month_orders,
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status != 'cancelled') as month_sales,
        
        -- Customer metrics
        (SELECT COUNT(*) FROM users WHERE role = 'customer') as total_customers,
        (SELECT COUNT(*) FROM users WHERE role = 'customer' AND DATE(created_at) = CURDATE()) as new_customers_today,
        
        -- Product metrics
        (SELECT COUNT(*) FROM products WHERE is_active = TRUE) as total_products,
        (SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_level) as low_stock_products,
        (SELECT COUNT(*) FROM products WHERE stock_quantity = 0) as out_of_stock_products,
        
        -- Service metrics
        (SELECT COUNT(*) FROM service_requests WHERE status = 'pending') as pending_services,
        (SELECT COUNT(*) FROM service_requests WHERE DATE(created_at) = CURDATE()) as today_service_requests,
        
        -- Order status breakdown
        (SELECT COUNT(*) FROM orders WHERE status = 'pending') as pending_orders,
        (SELECT COUNT(*) FROM orders WHERE status = 'confirmed') as confirmed_orders,
        (SELECT COUNT(*) FROM orders WHERE status = 'processing') as processing_orders,
        (SELECT COUNT(*) FROM orders WHERE status = 'shipped') as shipped_orders
");
$stmt->execute();
$metrics = $stmt->fetch();

// Get recent orders
$stmt = $db->prepare("
    SELECT o.*, CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.phone as customer_phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recentOrders = $stmt->fetchAll();

// Get low stock alerts
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.stock_quantity <= p.min_stock_level AND p.is_active = TRUE
    ORDER BY (p.stock_quantity / p.min_stock_level) ASC
    LIMIT 10
");
$stmt->execute();
$lowStockProducts = $stmt->fetchAll();

// Get top selling products this month
$stmt = $db->prepare("
    SELECT p.name, p.price, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE MONTH(o.created_at) = MONTH(NOW()) AND YEAR(o.created_at) = YEAR(NOW())
    AND o.status != 'cancelled'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
");
$stmt->execute();
$topProducts = $stmt->fetchAll();

// Get sales data for chart (last 7 days)
$stmt = $db->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as orders,
        COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total_amount ELSE 0 END), 0) as sales
    FROM orders 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute();
$salesData = $stmt->fetchAll();

// Get recent customer registrations
$stmt = $db->prepare("
    SELECT CONCAT(first_name, ' ', last_name) as name, email, created_at
    FROM users 
    WHERE role = 'customer'
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute();
$newCustomers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-Time Dashboard - PureFit Admin</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/main.css" rel="stylesheet">
    <link href="/assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-body">
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="admin-brand">
                        <img src="/assets/images/logo.png" alt="PureFit" height="35">
                        <span class="brand-text">PureFit Admin</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="admin-nav">
                        <!-- Real-time indicators -->
                        <div class="status-indicators">
                            <div class="status-item" title="System Status">
                                <i class="fas fa-circle text-success"></i>
                                <span>Online</span>
                            </div>
                            <div class="status-item" title="Last Update">
                                <i class="fas fa-sync-alt"></i>
                                <span id="last-update">Just now</span>
                            </div>
                        </div>
                        
                        <!-- Admin user menu -->
                        <div class="dropdown">
                            <button class="btn btn-link dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-2"></i>
                                <?= $user['first_name'] ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="/admin/profile.php">Profile Settings</a></li>
                                <li><a class="dropdown-item" href="/admin/system-settings.php">System Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/">View Website</a></li>
                                <li><a class="dropdown-item text-danger" href="/customer/logout.php">Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="admin-wrapper">
        <!-- Admin Sidebar -->
        <div class="admin-sidebar">
            <nav class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="/admin/real-time-dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    
                    <li class="nav-section">
                        <span class="nav-section-title">Sales & Orders</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/orders.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Orders</span>
                            <?php if ($metrics['pending_orders'] > 0): ?>
                            <span class="badge bg-warning"><?= $metrics['pending_orders'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/invoices.php">
                            <i class="fas fa-file-invoice"></i>
                            <span>Invoices</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/payments.php">
                            <i class="fas fa-credit-card"></i>
                            <span>Payments</span>
                        </a>
                    </li>
                    
                    <li class="nav-section">
                        <span class="nav-section-title">Products & Inventory</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/products.php">
                            <i class="fas fa-box"></i>
                            <span>Products</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/categories.php">
                            <i class="fas fa-tags"></i>
                            <span>Categories</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/inventory.php">
                            <i class="fas fa-warehouse"></i>
                            <span>Inventory</span>
                            <?php if ($metrics['low_stock_products'] > 0): ?>
                            <span class="badge bg-danger"><?= $metrics['low_stock_products'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="nav-section">
                        <span class="nav-section-title">Customers & Service</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/customers.php">
                            <i class="fas fa-users"></i>
                            <span>Customers</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/service-requests.php">
                            <i class="fas fa-tools"></i>
                            <span>Service Requests</span>
                            <?php if ($metrics['pending_services'] > 0): ?>
                            <span class="badge bg-warning"><?= $metrics['pending_services'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/warranties.php">
                            <i class="fas fa-shield-alt"></i>
                            <span>Warranties</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/technicians.php">
                            <i class="fas fa-user-hard-hat"></i>
                            <span>Technicians</span>
                        </a>
                    </li>
                    
                    <li class="nav-section">
                        <span class="nav-section-title">Marketing & Analytics</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/reports.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/promotions.php">
                            <i class="fas fa-percentage"></i>
                            <span>Promotions</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/email-campaigns.php">
                            <i class="fas fa-envelope-bulk"></i>
                            <span>Email Campaigns</span>
                        </a>
                    </li>
                    
                    <li class="nav-section">
                        <span class="nav-section-title">System</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/system-settings.php">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/activity-logs.php">
                            <i class="fas fa-history"></i>
                            <span>Activity Logs</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="admin-main">
            <div class="admin-content">
                <!-- Dashboard Header -->
                <div class="dashboard-header mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h1 class="dashboard-title">Real-Time Dashboard</h1>
                            <p class="dashboard-subtitle">
                                Live business metrics and insights • Last updated: <span id="last-updated">just now</span>
                            </p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="dashboard-actions">
                                <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                                    <i class="fas fa-sync-alt me-2"></i>Refresh
                                </button>
                                <button class="btn btn-primary" onclick="exportReport()">
                                    <i class="fas fa-download me-2"></i>Export Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Key Metrics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="metric-card animate__animated animate__fadeInUp">
                            <div class="metric-header">
                                <div class="metric-icon bg-primary">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <div class="metric-trend">
                                    <i class="fas fa-arrow-up text-success"></i>
                                    <span class="trend-value" id="orders-trend">+12%</span>
                                </div>
                            </div>
                            <div class="metric-content">
                                <div class="metric-number" data-metric="today_orders"><?= $metrics['today_orders'] ?></div>
                                <div class="metric-label">Orders Today</div>
                                <div class="metric-subtitle">
                                    <?= $metrics['week_orders'] ?> this week • <?= $metrics['month_orders'] ?> this month
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="metric-card animate__animated animate__fadeInUp" data-delay="100">
                            <div class="metric-header">
                                <div class="metric-icon bg-success">
                                    <i class="fas fa-taka-sign"></i>
                                </div>
                                <div class="metric-trend">
                                    <i class="fas fa-arrow-up text-success"></i>
                                    <span class="trend-value" id="sales-trend">+18%</span>
                                </div>
                            </div>
                            <div class="metric-content">
                                <div class="metric-number" data-metric="today_sales"><?= formatCurrency($metrics['today_sales']) ?></div>
                                <div class="metric-label">Sales Today</div>
                                <div class="metric-subtitle">
                                    <?= formatCurrency($metrics['week_sales']) ?> this week
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="metric-card animate__animated animate__fadeInUp" data-delay="200">
                            <div class="metric-header">
                                <div class="metric-icon bg-info">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="metric-trend">
                                    <i class="fas fa-arrow-up text-success"></i>
                                    <span class="trend-value" id="customers-trend">+5%</span>
                                </div>
                            </div>
                            <div class="metric-content">
                                <div class="metric-number" data-metric="total_customers"><?= $metrics['total_customers'] ?></div>
                                <div class="metric-label">Total Customers</div>
                                <div class="metric-subtitle">
                                    +<?= $metrics['new_customers_today'] ?> new today
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="metric-card animate__animated animate__fadeInUp" data-delay="300">
                            <div class="metric-header">
                                <div class="metric-icon bg-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="metric-trend">
                                    <?php if ($metrics['low_stock_products'] > 0): ?>
                                    <i class="fas fa-arrow-up text-danger"></i>
                                    <span class="trend-value text-danger">Alert</span>
                                    <?php else: ?>
                                    <i class="fas fa-check text-success"></i>
                                    <span class="trend-value text-success">Good</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="metric-content">
                                <div class="metric-number" data-metric="low_stock_products"><?= $metrics['low_stock_products'] ?></div>
                                <div class="metric-label">Low Stock Items</div>
                                <div class="metric-subtitle">
                                    <?= $metrics['out_of_stock_products'] ?> out of stock
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-lg-8 mb-4">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h5 class="card-title">Sales Overview (Last 7 Days)</h5>
                                <div class="card-tools">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary active" data-period="7">7 Days</button>
                                        <button class="btn btn-outline-primary" data-period="30">30 Days</button>
                                        <button class="btn btn-outline-primary" data-period="90">90 Days</button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <canvas id="salesChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 mb-4">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h5 class="card-title">Order Status Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="orderStatusChart" height="300"></canvas>
                                
                                <div class="status-legend mt-3">
                                    <div class="legend-item">
                                        <span class="legend-color bg-warning"></span>
                                        <span>Pending (<?= $metrics['pending_orders'] ?>)</span>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color bg-info"></span>
                                        <span>Confirmed (<?= $metrics['confirmed_orders'] ?>)</span>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color bg-primary"></span>
                                        <span>Processing (<?= $metrics['processing_orders'] ?>)</span>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color bg-success"></span>
                                        <span>Shipped (<?= $metrics['shipped_orders'] ?>)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Data Tables Row -->
                <div class="row mb-4">
                    <!-- Recent Orders -->
                    <div class="col-lg-8 mb-4">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h5 class="card-title">Recent Orders</h5>
                                <a href="/admin/orders.php" class="btn btn-sm btn-outline-primary">View All Orders</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Order #</th>
                                                <th>Customer</th>
                                                <th>Date</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentOrders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong class="text-primary"><?= $order['order_number'] ?></strong>
                                                </td>
                                                <td>
                                                    <div class="customer-info">
                                                        <div class="customer-name"><?= $order['customer_name'] ?></div>
                                                        <small class="text-muted"><?= $order['customer_phone'] ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="order-date">
                                                        <?= formatDate($order['created_at']) ?>
                                                        <small class="text-muted d-block"><?= formatDateTime($order['created_at'], 'h:i A') ?></small>
                                                    </div>
                                                </td>
                                                <td><strong><?= formatCurrency($order['total_amount']) ?></strong></td>
                                                <td>
                                                    <span class="badge bg-<?= getOrderStatusColor($order['status']) ?>">
                                                        <?= ucfirst($order['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="/admin/order-details.php?id=<?= $order['id'] ?>" 
                                                           class="btn btn-outline-primary" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button class="btn btn-outline-success" 
                                                                onclick="updateOrderStatus(<?= $order['id'] ?>, 'confirmed')" 
                                                                title="Confirm Order"
                                                                <?= $order['status'] !== 'pending' ? 'disabled' : '' ?>>
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Alerts & Notifications -->
                    <div class="col-lg-4 mb-4">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h5 class="card-title">System Alerts</h5>
                                <button class="btn btn-sm btn-outline-secondary" onclick="refreshAlerts()">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="alerts-list" id="alerts-list">
                                    <!-- Low Stock Alerts -->
                                    <?php if (!empty($lowStockProducts)): ?>
                                    <div class="alert-item alert-warning">
                                        <div class="alert-icon">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <div class="alert-content">
                                            <div class="alert-title">Low Stock Alert</div>
                                            <div class="alert-message">
                                                <?= count($lowStockProducts) ?> products are running low on stock
                                            </div>
                                            <div class="alert-actions">
                                                <button class="btn btn-sm btn-warning" onclick="viewLowStock()">
                                                    View Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Pending Services Alert -->
                                    <?php if ($metrics['pending_services'] > 0): ?>
                                    <div class="alert-item alert-info">
                                        <div class="alert-icon">
                                            <i class="fas fa-tools"></i>
                                        </div>
                                        <div class="alert-content">
                                            <div class="alert-title">Pending Services</div>
                                            <div class="alert-message">
                                                <?= $metrics['pending_services'] ?> service requests need attention
                                            </div>
                                            <div class="alert-actions">
                                                <a href="/admin/service-requests.php" class="btn btn-sm btn-info">
                                                    Assign Technicians
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- New Customers Alert -->
                                    <?php if ($metrics['new_customers_today'] > 0): ?>
                                    <div class="alert-item alert-success">
                                        <div class="alert-icon">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                        <div class="alert-content">
                                            <div class="alert-title">New Customers</div>
                                            <div class="alert-message">
                                                <?= $metrics['new_customers_today'] ?> new customers registered today
                                            </div>
                                            <div class="alert-actions">
                                                <a href="/admin/customers.php?filter=new" class="btn btn-sm btn-success">
                                                    View Customers
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Data -->
                <div class="row">
                    <!-- Top Products -->
                    <div class="col-lg-6 mb-4">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h5 class="card-title">Top Selling Products (This Month)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($topProducts)): ?>
                                <div class="empty-state text-center py-3">
                                    <i class="fas fa-chart-bar fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No sales data available</p>
                                </div>
                                <?php else: ?>
                                <div class="top-products-list">
                                    <?php foreach ($topProducts as $index => $product): ?>
                                    <div class="top-product-item">
                                        <div class="product-rank">#<?= $index + 1 ?></div>
                                        <div class="product-details">
                                            <div class="product-name"><?= $product['name'] ?></div>
                                            <div class="product-stats">
                                                <span class="stat-item">
                                                    <i class="fas fa-shopping-cart me-1"></i>
                                                    <?= $product['total_sold'] ?> sold
                                                </span>
                                                <span class="stat-item">
                                                    <i class="fas fa-taka-sign me-1"></i>
                                                    <?= formatCurrency($product['total_revenue']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="product-progress">
                                            <div class="progress">
                                                <div class="progress-bar bg-primary" 
                                                     style="width: <?= ($product['total_sold'] / $topProducts[0]['total_sold']) * 100 ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="col-lg-6 mb-4">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h5 class="card-title">Recent Activity</h5>
                                <a href="/admin/activity-logs.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="activity-feed" id="activity-feed">
                                    <!-- Activity items will be loaded via AJAX -->
                                    <div class="text-center p-3">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <div class="mt-2">Loading activity...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Low Stock Details Modal -->
    <div class="modal fade" id="lowStockModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Low Stock Products</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Current Stock</th>
                                    <th>Min Level</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $product): ?>
                                <tr>
                                    <td><?= $product['name'] ?></td>
                                    <td><?= $product['category_name'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $product['stock_quantity'] == 0 ? 'danger' : 'warning' ?>">
                                            <?= $product['stock_quantity'] ?>
                                        </span>
                                    </td>
                                    <td><?= $product['min_stock_level'] ?></td>
                                    <td>
                                        <a href="/admin/product-edit.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-primary">
                                            Update Stock
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Firebase SDK -->
    <?= FirebaseConfig::renderFirebaseScript() ?>
    
    <!-- Custom JavaScript -->
    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/admin.js"></script>
    
    <script>
        let salesChart, orderStatusChart;
        
        $(document).ready(function() {
            // Initialize dashboard
            initializeAdminDashboard();
            
            // Initialize charts
            initializeCharts();
            
            // Start real-time updates
            startRealTimeUpdates();
            
            // Load recent activity
            loadRecentActivity();
        });
        
        function initializeAdminDashboard() {
            // Animate metric cards
            $('.metric-card').each(function(index) {
                $(this).delay(index * 100).queue(function() {
                    $(this).addClass('animate__fadeInUp').dequeue();
                });
            });
            
            // Initialize DataTables
            $('.data-table').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[2, 'desc']], // Sort by date descending
                language: {
                    search: "Search orders:",
                    lengthMenu: "Show _MENU_ orders per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ orders"
                }
            });
        }
        
        function initializeCharts() {
            // Sales chart
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            const salesData = <?= json_encode($salesData) ?>;
            
            salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: salesData.map(item => new Date(item.date).toLocaleDateString('en-BD')),
                    datasets: [{
                        label: 'Sales (BDT)',
                        data: salesData.map(item => item.sales),
                        borderColor: '#0066cc',
                        backgroundColor: 'rgba(0, 102, 204, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Orders',
                        data: salesData.map(item => item.orders),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Sales (BDT)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Orders Count'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.datasetIndex === 0) {
                                        return 'Sales: ৳' + context.parsed.y.toLocaleString();
                                    } else {
                                        return 'Orders: ' + context.parsed.y;
                                    }
                                }
                            }
                        }
                    }
                }
            });
            
            // Order status chart
            const statusCtx = document.getElementById('orderStatusChart').getContext('2d');
            
            orderStatusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Confirmed', 'Processing', 'Shipped'],
                    datasets: [{
                        data: [
                            <?= $metrics['pending_orders'] ?>,
                            <?= $metrics['confirmed_orders'] ?>,
                            <?= $metrics['processing_orders'] ?>,
                            <?= $metrics['shipped_orders'] ?>
                        ],
                        backgroundColor: ['#ffc107', '#17a2b8', '#0066cc', '#28a745'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function startRealTimeUpdates() {
            // Update every 30 seconds
            setInterval(function() {
                updateDashboardMetrics();
                updateRecentOrders();
                updateAlerts();
                updateLastUpdatedTime();
            }, 30000);
        }
        
        function updateDashboardMetrics() {
            fetch('/api/admin/dashboard-metrics.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update metric cards
                        Object.keys(data.metrics).forEach(key => {
                            const element = document.querySelector(`[data-metric="${key}"]`);
                            if (element) {
                                animateNumberUpdate(element, data.metrics[key]);
                            }
                        });
                        
                        // Update charts if needed
                        if (data.chartData) {
                            updateChartsData(data.chartData);
                        }
                    }
                })
                .catch(error => {
                    console.error('Metrics update error:', error);
                });
        }
        
        function updateRecentOrders() {
            fetch('/api/admin/recent-orders.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.orders.length > 0) {
                        // Check for new orders
                        const currentOrderIds = Array.from(document.querySelectorAll('tbody tr')).map(row => 
                            row.querySelector('.text-primary').textContent.trim()
                        );
                        
                        const newOrders = data.orders.filter(order => 
                            !currentOrderIds.includes(order.order_number)
                        );
                        
                        if (newOrders.length > 0) {
                            // Show notification for new orders
                            showToast('New Order', `${newOrders.length} new order(s) received!`, 'success');
                            
                            // Play notification sound
                            playNotificationSound();
                            
                            // Refresh the orders table
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        }
                    }
                })
                .catch(error => {
                    console.error('Orders update error:', error);
                });
        }
        
        function loadRecentActivity() {
            fetch('/api/admin/recent-activity.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateActivityFeed(data.activities);
                    }
                })
                .catch(error => {
                    console.error('Activity feed error:', error);
                    document.getElementById('activity-feed').innerHTML = 
                        '<div class="text-center text-muted">Failed to load activity</div>';
                });
        }
        
        function updateActivityFeed(activities) {
            const feed = document.getElementById('activity-feed');
            
            if (activities.length === 0) {
                feed.innerHTML = '<div class="text-center text-muted py-3">No recent activity</div>';
                return;
            }
            
            let html = '';
            activities.forEach(activity => {
                html += `
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-${getActivityIcon(activity.action)}"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-description">${activity.description}</div>
                            <div class="activity-time">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    ${timeAgo(activity.created_at)}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            feed.innerHTML = html;
        }
        
        function animateNumberUpdate(element, newValue) {
            const currentValue = parseInt(element.textContent.replace(/[^0-9]/g, '')) || 0;
            
            if (currentValue !== newValue) {
                // Add pulse animation
                element.classList.add('animate__pulse');
                
                // Animate number change
                const increment = (newValue - currentValue) / 20;
                let current = currentValue;
                
                const timer = setInterval(() => {
                    current += increment;
                    if ((increment > 0 && current >= newValue) || (increment < 0 && current <= newValue)) {
                        current = newValue;
                        clearInterval(timer);
                        element.classList.remove('animate__pulse');
                    }
                    
                    // Format based on element content
                    if (element.textContent.includes('৳')) {
                        element.textContent = formatCurrency(Math.round(current));
                    } else {
                        element.textContent = Math.round(current);
                    }
                }, 50);
            }
        }
        
        function updateOrderStatus(orderId, status) {
            if (!confirm(`Are you sure you want to mark this order as ${status}?`)) {
                return;
            }
            
            fetch('/api/admin/update-order-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Success', 'Order status updated successfully', 'success');
                    
                    // Update the status badge in the table
                    const statusBadge = document.querySelector(`tr[data-order-id="${orderId}"] .badge`);
                    if (statusBadge) {
                        statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                        statusBadge.className = `badge bg-${getOrderStatusColor(status)}`;
                    }
                } else {
                    showToast('Error', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Update order status error:', error);
                showToast('Error', 'Failed to update order status', 'error');
            });
        }
        
        function viewLowStock() {
            const modal = new bootstrap.Modal(document.getElementById('lowStockModal'));
            modal.show();
        }
        
        function refreshDashboard() {
            // Show loading state
            const refreshBtn = event.target;
            const originalHTML = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Refreshing...';
            refreshBtn.disabled = true;
            
            // Refresh all dashboard data
            Promise.all([
                updateDashboardMetrics(),
                loadRecentActivity(),
                updateAlerts()
            ]).then(() => {
                showToast('Success', 'Dashboard refreshed successfully', 'success');
            }).catch(error => {
                console.error('Dashboard refresh error:', error);
                showToast('Error', 'Failed to refresh dashboard', 'error');
            }).finally(() => {
                refreshBtn.innerHTML = originalHTML;
                refreshBtn.disabled = false;
                updateLastUpdatedTime();
            });
        }
        
        function exportReport() {
            window.open('/api/admin/export-dashboard-report.php', '_blank');
        }
        
        function updateLastUpdatedTime() {
            document.getElementById('last-updated').textContent = 'just now';
            document.getElementById('last-update').textContent = new Date().toLocaleTimeString();
        }
        
        function playNotificationSound() {
            // Create audio element for notification sound
            const audio = new Audio('/assets/sounds/notification.mp3');
            audio.volume = 0.3;
            audio.play().catch(e => {
                // Ignore audio play errors (user hasn't interacted with page yet)
            });
        }
        
        function getActivityIcon(action) {
            const icons = {
                'order_created': 'shopping-cart',
                'order_updated': 'edit',
                'user_registered': 'user-plus',
                'product_added': 'box',
                'service_requested': 'tools',
                'payment_received': 'credit-card',
                'login': 'sign-in-alt',
                'logout': 'sign-out-alt'
            };
            
            return icons[action] || 'circle';
        }
        
        function getOrderStatusColor(status) {
            const colors = {
                'pending': 'warning',
                'confirmed': 'info',
                'processing': 'primary',
                'shipped': 'info',
                'delivered': 'success',
                'cancelled': 'danger',
                'refunded': 'secondary'
            };
            
            return colors[status] || 'secondary';
        }
        
        // Real-time WebSocket connection (if available)
        if (window.WebSocket) {
            const ws = new WebSocket('wss://your-websocket-server.com');
            
            ws.onmessage = function(event) {
                const data = JSON.parse(event.data);
                
                switch (data.type) {
                    case 'new_order':
                        showToast('New Order', `Order #${data.order_number} received!`, 'success');
                        playNotificationSound();
                        updateDashboardMetrics();
                        break;
                        
                    case 'low_stock_alert':
                        showToast('Stock Alert', `${data.product_name} is running low on stock`, 'warning');
                        break;
                        
                    case 'service_request':
                        showToast('Service Request', 'New service request received', 'info');
                        break;
                }
            };
        }
        
        // Track admin dashboard usage
        if (window.FirebaseHelpers) {
            window.FirebaseHelpers.trackEvent('admin_dashboard_view', {
                admin_id: <?= $user['id'] ?>,
                total_orders_today: <?= $metrics['today_orders'] ?>,
                total_sales_today: <?= $metrics['today_sales'] ?>
            });
        }
    </script>
</body>
</html>
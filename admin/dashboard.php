<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$db = Database::getInstance();

// Get dashboard statistics
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
$stats['monthly_revenue'] = $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM invoices WHERE status = 'paid' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")['revenue'];

// Low stock items
$stats['low_stock'] = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= min_stock_level")['count'];

// Recent activities
$recent_activities = $db->fetchAll("
    SELECT al.*, u.username 
    FROM activity_logs al 
    JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 10
");

// Recent service requests
$recent_services = $db->fetchAll("
    SELECT sr.*, c.contact_person, c.company_name 
    FROM service_requests sr 
    JOIN customers c ON sr.customer_id = c.id 
    ORDER BY sr.created_at DESC 
    LIMIT 5
");

// Chart data for revenue
$revenue_data = $db->fetchAll("
    SELECT DATE(created_at) as date, SUM(total_amount) as revenue 
    FROM invoices 
    WHERE status = 'paid' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at) 
    ORDER BY date
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Water Purifier ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tint me-2"></i>Water Purifier ERP
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-users me-1"></i>Users
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="customers.php">Customers</a></li>
                            <li><a class="dropdown-item" href="technicians.php">Technicians</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cogs me-1"></i>Services
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="service-requests.php">Service Requests</a></li>
                            <li><a class="dropdown-item" href="service-history.php">Service History</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-shopping-cart me-1"></i>Sales
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="sales-orders.php">Sales Orders</a></li>
                            <li><a class="dropdown-item" href="invoices.php">Invoices</a></li>
                            <li><a class="dropdown-item" href="products.php">Products</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-chart-line me-1"></i>Reports
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="reports/sales.php">Sales Reports</a></li>
                            <li><a class="dropdown-item" href="reports/services.php">Service Reports</a></li>
                            <li><a class="dropdown-item" href="reports/financial.php">Financial Reports</a></li>
                        </ul>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <button class="btn btn-link nav-link position-relative" data-bs-toggle="offcanvas" data-bs-target="#notificationDrawer">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="navNotificationCount">
                                0
                            </span>
                        </button>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['username']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                            <li><a class="dropdown-item" href="audit-log.php">Audit Log</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../api/auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Controls -->
    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4 class="mb-0">
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                    <span class="badge bg-primary ms-2" id="liveIndicator">
                                        <i class="fas fa-circle text-success me-1"></i>Live
                                    </span>
                                </h4>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-outline-primary" id="refreshBtn">
                                        <i class="fas fa-sync-alt me-1"></i>Refresh
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" id="exportBtn">
                                        <i class="fas fa-download me-1"></i>Export
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" id="darkModeToggle">
                                        <i class="fas fa-moon me-1"></i>Dark Mode
                                    </button>
                                </div>
                                <div class="btn-group" role="group">
                                    <input type="date" class="form-control" id="dateFrom" value="<?php echo date('Y-m-01'); ?>">
                                    <input type="date" class="form-control" id="dateTo" value="<?php echo date('Y-m-d'); ?>">
                                    <button type="button" class="btn btn-primary" id="applyDateFilter">
                                        <i class="fas fa-filter me-1"></i>Apply
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification Center -->
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 1050;">
            <div id="notificationContainer"></div>
        </div>

        <!-- Notification Center Drawer -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="notificationDrawer">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">
                    <i class="fas fa-bell me-2"></i>Notifications
                    <span class="badge bg-danger ms-2" id="notificationCount">0</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <div class="d-flex justify-content-between mb-3">
                    <button class="btn btn-sm btn-outline-primary" id="markAllRead">
                        <i class="fas fa-check-double me-1"></i>Mark All Read
                    </button>
                    <button class="btn btn-sm btn-outline-danger" id="clearAll">
                        <i class="fas fa-trash me-1"></i>Clear All
                    </button>
                </div>
                <div id="notificationList">
                    <!-- Notifications will be loaded here -->
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="dashboard-card success" data-widget="customers">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['total_customers']; ?></div>
                            <div class="stat-label">Total Customers</div>
                            <div class="stat-change text-success">
                                <i class="fas fa-arrow-up me-1"></i>+12% this month
                            </div>
                        </div>
                        <div class="stat-icon text-success">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="btn btn-sm btn-outline-success" onclick="viewCustomers()">
                            <i class="fas fa-eye me-1"></i>View
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="dashboard-card info" data-widget="technicians">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['total_technicians']; ?></div>
                            <div class="stat-label">Active Technicians</div>
                            <div class="stat-change text-info">
                                <i class="fas fa-arrow-up me-1"></i>+3 this week
                            </div>
                        </div>
                        <div class="stat-icon text-info">
                            <i class="fas fa-tools"></i>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="btn btn-sm btn-outline-info" onclick="viewTechnicians()">
                            <i class="fas fa-eye me-1"></i>View
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="dashboard-card warning" data-widget="pending-services">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['pending_services']; ?></div>
                            <div class="stat-label">Pending Services</div>
                            <div class="stat-change text-warning">
                                <i class="fas fa-clock me-1"></i>Needs attention
                            </div>
                        </div>
                        <div class="stat-icon text-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="btn btn-sm btn-outline-warning" onclick="viewPendingServices()">
                            <i class="fas fa-eye me-1"></i>View
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="dashboard-card danger" data-widget="low-stock">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['low_stock']; ?></div>
                            <div class="stat-label">Low Stock Items</div>
                            <div class="stat-change text-danger">
                                <i class="fas fa-exclamation-triangle me-1"></i>Restock needed
                            </div>
                        </div>
                        <div class="stat-icon text-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="btn btn-sm btn-outline-danger" onclick="viewLowStock()">
                            <i class="fas fa-eye me-1"></i>View
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Revenue Chart -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Revenue</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart" height="100"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quick Stats</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <div class="h4 text-primary">₹<?php echo number_format($stats['monthly_revenue']); ?></div>
                                    <small class="text-muted">Monthly Revenue</small>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <div class="h4 text-success"><?php echo $stats['today_services']; ?></div>
                                    <small class="text-muted">Today's Services</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Service Requests -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent Service Requests</h5>
                        <a href="service-requests.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ticket #</th>
                                        <th>Customer</th>
                                        <th>Service Type</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_services as $service): ?>
                                    <tr>
                                        <td><strong><?php echo $service['ticket_number']; ?></strong></td>
                                        <td><?php echo $service['company_name'] ?: $service['contact_person']; ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo ucfirst($service['service_type']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadge($service['priority']); ?>">
                                                <?php echo ucfirst($service['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadge($service['status']); ?>">
                                                <?php echo ucfirst($service['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($service['created_at']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        <div class="activity-feed">
                            <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item mb-3">
                                <div class="d-flex">
                                    <div class="activity-icon me-3">
                                        <i class="fas fa-circle text-primary"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-text">
                                            <strong><?php echo $activity['username']; ?></strong>
                                            <?php echo $activity['action']; ?>
                                        </div>
                                        <div class="activity-time text-muted">
                                            <?php echo getTimeAgo($activity['created_at']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Assistant Modal -->
    <div class="modal fade" id="aiAssistantModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-robot me-2"></i>AI Assistant
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="aiChatContainer" style="height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 1rem; margin-bottom: 1rem;">
                        <div class="ai-message">
                            <div class="d-flex">
                                <div class="ai-avatar me-2">
                                    <i class="fas fa-robot text-primary"></i>
                                </div>
                                <div class="ai-content">
                                    <div class="ai-bubble">
                                        Hello! I'm your AI assistant. How can I help you today?
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="input-group">
                        <input type="text" class="form-control" id="aiInput" placeholder="Ask me anything...">
                        <button class="btn btn-primary" type="button" id="aiSendBtn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating AI Button -->
    <button class="btn btn-primary rounded-circle position-fixed" style="bottom: 20px; right: 20px; width: 60px; height: 60px; z-index: 1000;" data-bs-toggle="modal" data-bs-target="#aiAssistantModal">
        <i class="fas fa-robot fa-lg"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Revenue Chart
        const revenueData = <?php echo json_encode($revenue_data); ?>;
        const ctx = document.getElementById('revenueChart').getContext('2d');
        window.revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: revenueData.map(item => item.date),
                datasets: [{
                    label: 'Revenue (₹)',
                    data: revenueData.map(item => item.revenue),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
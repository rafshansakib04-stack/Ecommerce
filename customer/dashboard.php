<?php
/**
 * Customer Dashboard
 * Complete customer account management with real-time data
 */

require_once '../includes/functions.php';

// Require customer login
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Get customer statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END), 0) as total_spent,
        COUNT(DISTINCT CASE WHEN o.status = 'pending' THEN o.id END) as pending_orders,
        COUNT(DISTINCT w.id) as active_warranties,
        COUNT(DISTINCT sr.id) as service_requests,
        COUNT(DISTINCT wi.id) as wishlist_items
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    LEFT JOIN warranties w ON u.id = w.user_id AND w.status = 'active'
    LEFT JOIN service_requests sr ON u.id = sr.user_id
    LEFT JOIN wishlist_items wi ON u.id = wi.user_id
    WHERE u.id = ?
");
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

// Get recent orders
$stmt = $db->prepare("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$recentOrders = $stmt->fetchAll();

// Get active warranties
$stmt = $db->prepare("
    SELECT w.*, p.name as product_name, p.image
    FROM warranties w
    JOIN products p ON w.product_id = p.id
    WHERE w.user_id = ? AND w.status = 'active'
    ORDER BY w.warranty_end_date ASC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$warranties = $stmt->fetchAll();

// Get recent service requests
$stmt = $db->prepare("
    SELECT sr.*, st.name as service_type_name, p.name as product_name
    FROM service_requests sr
    JOIN service_types st ON sr.service_type_id = st.id
    LEFT JOIN products p ON sr.product_id = p.id
    WHERE sr.user_id = ?
    ORDER BY sr.created_at DESC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$serviceRequests = $stmt->fetchAll();

// Get notifications
$stmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND status IN ('pending', 'sent')
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

// Get wishlist items
$stmt = $db->prepare("
    SELECT wi.*, p.name, p.price, p.sale_price, p.slug,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = TRUE LIMIT 1) as image
    FROM wishlist_items wi
    JOIN products p ON wi.product_id = p.id
    WHERE wi.user_id = ? AND p.is_active = TRUE
    ORDER BY wi.added_at DESC
    LIMIT 8
");
$stmt->execute([$user['id']]);
$wishlistItems = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - PureFit Bangladesh</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/main.css" rel="stylesheet">
    <link href="/assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <?php include '../includes/header.php'; ?>

    <!-- Dashboard Content -->
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <div class="dashboard-sidebar">
            <div class="sidebar-header">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php if ($user['profile_image']): ?>
                            <img src="<?= $user['profile_image'] ?>" alt="Profile">
                        <?php else: ?>
                            <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <h6><?= $user['first_name'] . ' ' . $user['last_name'] ?></h6>
                        <small class="text-muted"><?= ucfirst($user['role']) ?></small>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="/customer/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/customer/orders.php">
                            <i class="fas fa-shopping-bag"></i>
                            <span>My Orders</span>
                            <?php if ($stats['pending_orders'] > 0): ?>
                            <span class="badge bg-warning"><?= $stats['pending_orders'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/customer/wishlist.php">
                            <i class="fas fa-heart"></i>
                            <span>Wishlist</span>
                            <?php if ($stats['wishlist_items'] > 0): ?>
                            <span class="badge bg-primary"><?= $stats['wishlist_items'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/customer/warranties.php">
                            <i class="fas fa-shield-alt"></i>
                            <span>Warranties</span>
                            <?php if ($stats['active_warranties'] > 0): ?>
                            <span class="badge bg-success"><?= $stats['active_warranties'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/customer/service-requests.php">
                            <i class="fas fa-tools"></i>
                            <span>Service Requests</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/customer/addresses.php">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Addresses</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/customer/profile.php">
                            <i class="fas fa-user-cog"></i>
                            <span>Profile Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/customer/support.php">
                            <i class="fas fa-headset"></i>
                            <span>Support</span>
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <a class="nav-link text-danger" href="/customer/logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="dashboard-main">
            <div class="dashboard-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h2 class="dashboard-title">Welcome back, <?= $user['first_name'] ?>!</h2>
                        <p class="dashboard-subtitle">Manage your orders, warranties, and account settings</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="dashboard-actions">
                            <a href="/products.php" class="btn btn-primary">
                                <i class="fas fa-shopping-cart me-2"></i>Shop Now
                            </a>
                            <a href="/customer/service-request.php" class="btn btn-outline-primary">
                                <i class="fas fa-tools me-2"></i>Book Service
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card animate__animated animate__fadeInUp">
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $stats['total_orders'] ?></div>
                            <div class="stat-label">Total Orders</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card animate__animated animate__fadeInUp" data-delay="100">
                        <div class="stat-icon bg-success">
                            <i class="fas fa-taka-sign"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= formatCurrency($stats['total_spent']) ?></div>
                            <div class="stat-label">Total Spent</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card animate__animated animate__fadeInUp" data-delay="200">
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $stats['active_warranties'] ?></div>
                            <div class="stat-label">Active Warranties</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stat-card animate__animated animate__fadeInUp" data-delay="300">
                        <div class="stat-icon bg-info">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $stats['wishlist_items'] ?></div>
                            <div class="stat-label">Wishlist Items</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Recent Orders -->
                <div class="col-lg-8 mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title">Recent Orders</h5>
                            <a href="/customer/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentOrders)): ?>
                            <div class="empty-state text-center py-4">
                                <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                                <h6>No orders yet</h6>
                                <p class="text-muted">Start shopping for water purifiers</p>
                                <a href="/products.php" class="btn btn-primary">Browse Products</a>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Date</th>
                                            <th>Items</th>
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
                                            <td><?= formatDate($order['created_at']) ?></td>
                                            <td><?= $order['item_count'] ?> items</td>
                                            <td><strong><?= formatCurrency($order['total_amount']) ?></strong></td>
                                            <td>
                                                <span class="badge bg-<?= getOrderStatusColor($order['status']) ?>">
                                                    <?= ucfirst($order['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="/customer/order-details.php?id=<?= $order['id'] ?>" 
                                                       class="btn btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($order['status'] === 'delivered'): ?>
                                                    <button class="btn btn-outline-success" 
                                                            onclick="downloadInvoice(<?= $order['id'] ?>)" title="Download Invoice">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="cancelOrder(<?= $order['id'] ?>)" title="Cancel Order">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="col-lg-4 mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions">
                                <a href="/customer/service-request.php" class="quick-action-item">
                                    <div class="action-icon bg-primary">
                                        <i class="fas fa-tools"></i>
                                    </div>
                                    <div class="action-content">
                                        <h6>Book Service</h6>
                                        <small>Schedule maintenance or repair</small>
                                    </div>
                                </a>
                                
                                <a href="/customer/warranty-register.php" class="quick-action-item">
                                    <div class="action-icon bg-success">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <div class="action-content">
                                        <h6>Register Warranty</h6>
                                        <small>Register your new purchase</small>
                                    </div>
                                </a>
                                
                                <a href="/customer/support.php" class="quick-action-item">
                                    <div class="action-icon bg-info">
                                        <i class="fas fa-headset"></i>
                                    </div>
                                    <div class="action-content">
                                        <h6>Get Support</h6>
                                        <small>Contact customer service</small>
                                    </div>
                                </a>
                                
                                <a href="/customer/track-order.php" class="quick-action-item">
                                    <div class="action-icon bg-warning">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <div class="action-content">
                                        <h6>Track Order</h6>
                                        <small>Check delivery status</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Active Warranties -->
                <div class="col-lg-6 mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title">Active Warranties</h5>
                            <a href="/customer/warranties.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($warranties)): ?>
                            <div class="empty-state text-center py-3">
                                <i class="fas fa-shield-alt fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No active warranties</p>
                            </div>
                            <?php else: ?>
                            <div class="warranties-list">
                                <?php foreach ($warranties as $warranty): ?>
                                <div class="warranty-item">
                                    <div class="warranty-product">
                                        <img src="/uploads/products/<?= $warranty['image'] ?? 'placeholder.jpg' ?>" 
                                             alt="<?= $warranty['product_name'] ?>" class="warranty-image">
                                        <div class="warranty-details">
                                            <h6><?= $warranty['product_name'] ?></h6>
                                            <small class="text-muted">Warranty #<?= $warranty['warranty_number'] ?></small>
                                        </div>
                                    </div>
                                    <div class="warranty-status">
                                        <?php 
                                        $daysLeft = (strtotime($warranty['warranty_end_date']) - time()) / (24 * 60 * 60);
                                        $warningClass = $daysLeft < 30 ? 'text-warning' : 'text-success';
                                        ?>
                                        <small class="<?= $warningClass ?>">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= $daysLeft > 0 ? ceil($daysLeft) . ' days left' : 'Expired' ?>
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Wishlist Preview -->
                <div class="col-lg-6 mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title">My Wishlist</h5>
                            <a href="/customer/wishlist.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($wishlistItems)): ?>
                            <div class="empty-state text-center py-3">
                                <i class="fas fa-heart fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No items in wishlist</p>
                            </div>
                            <?php else: ?>
                            <div class="wishlist-preview">
                                <?php foreach (array_slice($wishlistItems, 0, 4) as $item): ?>
                                <div class="wishlist-item">
                                    <img src="/uploads/products/<?= $item['image'] ?? 'placeholder.jpg' ?>" 
                                         alt="<?= $item['name'] ?>" class="wishlist-image">
                                    <div class="wishlist-details">
                                        <h6 class="mb-1"><?= $item['name'] ?></h6>
                                        <div class="wishlist-price">
                                            <?= formatCurrency($item['sale_price'] ?: $item['price']) ?>
                                        </div>
                                    </div>
                                    <div class="wishlist-actions">
                                        <button class="btn btn-sm btn-primary" onclick="addToCart(<?= $item['product_id'] ?>)">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromWishlist(<?= $item['product_id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Service Requests -->
                <div class="col-lg-6 mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title">Recent Service Requests</h5>
                            <a href="/customer/service-requests.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($serviceRequests)): ?>
                            <div class="empty-state text-center py-3">
                                <i class="fas fa-tools fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No service requests</p>
                            </div>
                            <?php else: ?>
                            <div class="service-requests-list">
                                <?php foreach ($serviceRequests as $request): ?>
                                <div class="service-request-item">
                                    <div class="request-header">
                                        <strong>#<?= $request['request_number'] ?></strong>
                                        <span class="badge bg-<?= getServiceStatusColor($request['status']) ?>">
                                            <?= ucfirst($request['status']) ?>
                                        </span>
                                    </div>
                                    <div class="request-details">
                                        <div class="request-service"><?= $request['service_type_name'] ?></div>
                                        <?php if ($request['product_name']): ?>
                                        <div class="request-product">Product: <?= $request['product_name'] ?></div>
                                        <?php endif; ?>
                                        <div class="request-date">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?= formatDate($request['created_at']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications -->
                <div class="col-lg-6 mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title">Notifications</h5>
                            <button class="btn btn-sm btn-outline-secondary" onclick="markAllNotificationsRead()">
                                Mark All Read
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($notifications)): ?>
                            <div class="empty-state text-center py-3">
                                <i class="fas fa-bell fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No new notifications</p>
                            </div>
                            <?php else: ?>
                            <div class="notifications-list" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?= $notification['read_at'] ? '' : 'unread' ?>">
                                    <div class="notification-icon">
                                        <i class="fas fa-<?= getNotificationIcon($notification['type']) ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title"><?= $notification['title'] ?></div>
                                        <div class="notification-message"><?= $notification['message'] ?></div>
                                        <div class="notification-time">
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?= timeAgo($notification['created_at']) ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php if (!$notification['read_at']): ?>
                                    <div class="notification-actions">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="markNotificationRead(<?= $notification['id'] ?>)">
                                            Mark Read
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Account Completion -->
            <?php if (!$user['email_verified'] || !$user['phone_verified']): ?>
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="alert alert-warning">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                            <div class="flex-grow-1">
                                <h6 class="alert-heading">Complete Your Account Setup</h6>
                                <p class="mb-2">Please verify your contact information to ensure smooth order processing and service delivery.</p>
                                <div class="verification-status">
                                    <?php if (!$user['email_verified']): ?>
                                    <span class="badge bg-warning me-2">Email Not Verified</span>
                                    <?php endif; ?>
                                    <?php if (!$user['phone_verified']): ?>
                                    <span class="badge bg-warning me-2">Phone Not Verified</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="alert-actions">
                                <a href="/customer/verify-account.php" class="btn btn-warning">
                                    Complete Setup
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    
    <!-- Firebase SDK -->
    <?= FirebaseConfig::renderFirebaseScript() ?>
    
    <!-- Custom JavaScript -->
    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/dashboard.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize dashboard
            initializeDashboard();
            
            // Load real-time updates
            startRealTimeUpdates();
            
            // Initialize notifications
            initializeNotifications();
        });
        
        function initializeDashboard() {
            // Animate stat cards
            $('.stat-card').each(function(index) {
                $(this).delay(index * 100).queue(function() {
                    $(this).addClass('animate__fadeInUp').dequeue();
                });
            });
            
            // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();
        }
        
        function startRealTimeUpdates() {
            // Update dashboard data every 30 seconds
            setInterval(function() {
                updateDashboardStats();
                updateNotifications();
            }, 30000);
        }
        
        function updateDashboardStats() {
            fetch('/api/customer/dashboard-stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update stat numbers with animation
                        Object.keys(data.stats).forEach(key => {
                            const element = document.querySelector(`[data-stat="${key}"]`);
                            if (element) {
                                animateNumber(element, data.stats[key]);
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Dashboard stats update error:', error);
                });
        }
        
        function updateNotifications() {
            fetch('/api/notifications/recent.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.notifications.length > 0) {
                        // Update notifications list
                        updateNotificationsList(data.notifications);
                        
                        // Show new notification toast
                        const unreadCount = data.notifications.filter(n => !n.read_at).length;
                        if (unreadCount > 0) {
                            showToast('Info', `You have ${unreadCount} new notification(s)`, 'info');
                        }
                    }
                })
                .catch(error => {
                    console.error('Notifications update error:', error);
                });
        }
        
        function animateNumber(element, newValue) {
            const currentValue = parseInt(element.textContent.replace(/[^0-9]/g, '')) || 0;
            
            if (currentValue !== newValue) {
                const increment = (newValue - currentValue) / 20;
                let current = currentValue;
                
                const timer = setInterval(() => {
                    current += increment;
                    if ((increment > 0 && current >= newValue) || (increment < 0 && current <= newValue)) {
                        current = newValue;
                        clearInterval(timer);
                    }
                    element.textContent = Math.round(current);
                }, 50);
            }
        }
        
        function cancelOrder(orderId) {
            if (!confirm('Are you sure you want to cancel this order?')) {
                return;
            }
            
            fetch('/api/orders/cancel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ order_id: orderId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Success', 'Order cancelled successfully', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast('Error', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Cancel order error:', error);
                showToast('Error', 'Failed to cancel order', 'error');
            });
        }
        
        function downloadInvoice(orderId) {
            window.open(`/api/orders/invoice.php?id=${orderId}`, '_blank');
        }
        
        function markNotificationRead(notificationId) {
            fetch('/api/notifications/mark-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
                    if (notificationElement) {
                        notificationElement.classList.remove('unread');
                        notificationElement.querySelector('.notification-actions').remove();
                    }
                }
            })
            .catch(error => {
                console.error('Mark notification read error:', error);
            });
        }
        
        function markAllNotificationsRead() {
            fetch('/api/notifications/mark-all-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        const actions = item.querySelector('.notification-actions');
                        if (actions) actions.remove();
                    });
                    showToast('Success', 'All notifications marked as read', 'success');
                }
            })
            .catch(error => {
                console.error('Mark all notifications read error:', error);
            });
        }
        
        // Track dashboard visit
        if (window.FirebaseHelpers) {
            window.FirebaseHelpers.trackEvent('dashboard_view', {
                user_id: <?= $user['id'] ?>,
                total_orders: <?= $stats['total_orders'] ?>,
                total_spent: <?= $stats['total_spent'] ?>
            });
        }
    </script>
</body>
</html>

<?php
// Helper functions for status colors
function getOrderStatusColor($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'confirmed': return 'info';
        case 'processing': return 'primary';
        case 'shipped': return 'info';
        case 'delivered': return 'success';
        case 'cancelled': return 'danger';
        case 'refunded': return 'secondary';
        default: return 'secondary';
    }
}

function getServiceStatusColor($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'assigned': return 'info';
        case 'in_progress': return 'primary';
        case 'completed': return 'success';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
}

function getNotificationIcon($type) {
    switch ($type) {
        case 'order': return 'shopping-bag';
        case 'service': return 'tools';
        case 'warranty': return 'shield-alt';
        case 'promotion': return 'tag';
        case 'system': return 'cog';
        default: return 'bell';
    }
}
?>
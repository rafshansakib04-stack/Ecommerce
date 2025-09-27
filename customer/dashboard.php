<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header('Location: ../index.php');
    exit();
}

$db = Database::getInstance();

// Get customer details
$customer = $db->fetchOne("
    SELECT c.*, u.username, u.email, u.phone 
    FROM customers c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.user_id = ?
", [$_SESSION['user_id']]);

if (!$customer) {
    header('Location: ../index.php');
    exit();
}

// Get dashboard statistics
$stats = [];

// Total services
$stats['total_services'] = $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE customer_id = ?", [$customer['id']])['count'];

// Pending services
$stats['pending_services'] = $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE customer_id = ? AND status IN ('pending', 'assigned', 'in_progress')", [$customer['id']])['count'];

// Completed services
$stats['completed_services'] = $db->fetchOne("SELECT COUNT(*) as count FROM service_requests WHERE customer_id = ? AND status = 'completed'", [$customer['id']])['count'];

// Total amount paid
$stats['total_paid'] = $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as amount FROM invoices WHERE customer_id = ? AND status = 'paid'", [$customer['id']])['amount'];

// Outstanding amount
$stats['outstanding'] = $db->fetchOne("SELECT COALESCE(SUM(balance_amount), 0) as amount FROM invoices WHERE customer_id = ? AND status != 'paid'", [$customer['id']])['amount'];

// Recent service requests
$recentServices = $db->fetchAll("
    SELECT sr.*, t.full_name as technician_name, t.phone as technician_phone
    FROM service_requests sr 
    LEFT JOIN technicians t ON sr.assigned_technician_id = t.id
    WHERE sr.customer_id = ? 
    ORDER BY sr.created_at DESC 
    LIMIT 5
", [$customer['id']]);

// Recent invoices
$recentInvoices = $db->fetchAll("
    SELECT * FROM invoices 
    WHERE customer_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
", [$customer['id']]);

// Upcoming services (scheduled)
$upcomingServices = $db->fetchAll("
    SELECT sr.*, t.full_name as technician_name
    FROM service_requests sr 
    LEFT JOIN technicians t ON sr.assigned_technician_id = t.id
    WHERE sr.customer_id = ? 
    AND sr.preferred_date >= CURDATE()
    AND sr.status IN ('assigned', 'in_progress')
    ORDER BY sr.preferred_date ASC
", [$customer['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Water Purifier ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
                    <li class="nav-item">
                        <a class="nav-link" href="service-request.php">
                            <i class="fas fa-plus me-1"></i>Request Service
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="service-history.php">
                            <i class="fas fa-history me-1"></i>Service History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="invoices.php">
                            <i class="fas fa-file-invoice me-1"></i>Invoices
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user me-1"></i>Profile
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo $customer['contact_person']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../api/auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Welcome Message -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h4 class="mb-1">Welcome back, <?php echo htmlspecialchars($customer['contact_person']); ?>!</h4>
                        <p class="mb-0">Here's what's happening with your water purifier services.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['total_services']; ?></div>
                            <div class="stat-label">Total Services</div>
                        </div>
                        <div class="stat-icon text-success">
                            <i class="fas fa-tools"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['pending_services']; ?></div>
                            <div class="stat-label">Pending Services</div>
                        </div>
                        <div class="stat-icon text-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['completed_services']; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                        <div class="stat-icon text-info">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card danger">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number">₹<?php echo number_format($stats['outstanding']); ?></div>
                            <div class="stat-label">Outstanding</div>
                        </div>
                        <div class="stat-icon text-danger">
                            <i class="fas fa-exclamation-triangle"></i>
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
                        <a href="service-request.php" class="btn btn-sm btn-primary">New Request</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ticket #</th>
                                        <th>Service Type</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Technician</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentServices as $service): ?>
                                    <tr>
                                        <td><strong><?php echo $service['ticket_number']; ?></strong></td>
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
                                        <td>
                                            <?php if ($service['technician_name']): ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($service['technician_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($service['technician_phone']); ?></small>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatDate($service['created_at']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewService(<?php echo $service['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Services & Quick Actions -->
            <div class="col-lg-4 mb-4">
                <!-- Upcoming Services -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Upcoming Services</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($upcomingServices)): ?>
                            <?php foreach ($upcomingServices as $service): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-calendar-check text-primary"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold"><?php echo $service['ticket_number']; ?></div>
                                    <small class="text-muted">
                                        <?php echo formatDate($service['preferred_date']); ?>
                                        <?php if ($service['preferred_time']): ?>
                                            at <?php echo date('H:i', strtotime($service['preferred_time'])); ?>
                                        <?php endif; ?>
                                    </small>
                                    <?php if ($service['technician_name']): ?>
                                    <br><small class="text-info">Technician: <?php echo htmlspecialchars($service['technician_name']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No upcoming services scheduled</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="service-request.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Request Service
                            </a>
                            <a href="service-history.php" class="btn btn-outline-primary">
                                <i class="fas fa-history me-2"></i>View History
                            </a>
                            <a href="invoices.php" class="btn btn-outline-success">
                                <i class="fas fa-file-invoice me-2"></i>View Invoices
                            </a>
                            <a href="profile.php" class="btn btn-outline-info">
                                <i class="fas fa-user me-2"></i>Update Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Invoices -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Recent Invoices</h5>
                        <a href="invoices.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentInvoices as $invoice): ?>
                                    <tr>
                                        <td><strong><?php echo $invoice['invoice_number']; ?></strong></td>
                                        <td><?php echo formatDate($invoice['invoice_date']); ?></td>
                                        <td><strong><?php echo formatCurrency($invoice['total_amount']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadge($invoice['status']); ?>">
                                                <?php echo ucfirst($invoice['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($invoice['due_date']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewInvoice(<?php echo $invoice['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($invoice['status'] !== 'paid'): ?>
                                            <button class="btn btn-sm btn-outline-success" onclick="payInvoice(<?php echo $invoice['id']; ?>)">
                                                <i class="fas fa-credit-card"></i>
                                            </button>
                                            <?php endif; ?>
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
    </div>

    <!-- View Service Modal -->
    <div class="modal fade" id="viewServiceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tools me-2"></i>Service Request Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="serviceDetails">
                    <!-- Service details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Invoice Modal -->
    <div class="modal fade" id="viewInvoiceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-invoice me-2"></i>Invoice Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="invoiceDetails">
                    <!-- Invoice details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../assets/js/customer-dashboard.js"></script>
</body>
</html>
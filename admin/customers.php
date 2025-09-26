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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_customer':
            $result = addCustomer($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'update_customer':
            $result = updateCustomer($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'delete_customer':
            $result = deleteCustomer($db, $_POST['id']);
            echo json_encode($result);
            exit();
            
        case 'send_credentials':
            $result = sendCustomerCredentials($db, $_POST['id']);
            echo json_encode($result);
            exit();
    }
}

// Get customers with pagination, search, and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$segment_filter = $_GET['segment'] ?? '';
$credit_filter = $_GET['credit'] ?? '';
$status_filter = $_GET['status'] ?? '';

$whereClause = '';
$params = [];

$whereConditions = [];
if (!empty($search)) {
    $whereConditions[] = "(c.company_name LIKE ? OR c.contact_person LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($segment_filter)) {
    $whereConditions[] = "c.segment = ?";
    $params[] = $segment_filter;
}

if (!empty($credit_filter)) {
    switch ($credit_filter) {
        case 'high':
            $whereConditions[] = "c.credit_limit >= 100000";
            break;
        case 'medium':
            $whereConditions[] = "c.credit_limit BETWEEN 50000 AND 99999";
            break;
        case 'low':
            $whereConditions[] = "c.credit_limit < 50000";
            break;
    }
}

if (!empty($status_filter)) {
    $whereConditions[] = "u.status = ?";
    $params[] = $status_filter;
}

if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(' AND ', $whereConditions);
}

$customers = $db->fetchAll("
    SELECT c.*, u.username, u.email, u.phone, u.status as user_status,
           (SELECT COUNT(*) FROM service_requests sr WHERE sr.customer_id = c.id) as total_services,
           (SELECT COALESCE(SUM(i.total_amount), 0) FROM invoices i WHERE i.customer_id = c.id AND i.status = 'paid') as total_paid,
           (SELECT COALESCE(SUM(cl.balance), 0) FROM customer_ledger cl WHERE cl.customer_id = c.id) as current_balance,
           (SELECT COUNT(*) FROM service_requests sr WHERE sr.customer_id = c.id AND sr.status = 'completed' AND DATE(sr.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as recent_services
    FROM customers c 
    JOIN users u ON c.user_id = u.id 
    $whereClause
    ORDER BY c.created_at DESC 
    LIMIT $limit OFFSET $offset
", $params);

$totalCustomers = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM customers c 
    JOIN users u ON c.user_id = u.id 
    $whereClause
", $params)['count'];

$totalPages = ceil($totalCustomers / $limit);

// Get customer analytics
$customerAnalytics = $db->fetchAll("
    SELECT 
        c.segment,
        COUNT(*) as customer_count,
        AVG(c.credit_limit) as avg_credit_limit,
        SUM(COALESCE(cl.balance, 0)) as total_balance
    FROM customers c
    LEFT JOIN customer_ledger cl ON c.id = cl.customer_id
    GROUP BY c.segment
    ORDER BY customer_count DESC
");

// Get top customers by revenue
$topCustomers = $db->fetchAll("
    SELECT c.company_name, c.contact_person, 
           COALESCE(SUM(i.total_amount), 0) as total_revenue,
           COUNT(sr.id) as total_services
    FROM customers c
    LEFT JOIN invoices i ON c.id = i.customer_id AND i.status = 'paid'
    LEFT JOIN service_requests sr ON c.id = sr.customer_id
    GROUP BY c.id, c.company_name, c.contact_person
    ORDER BY total_revenue DESC
    LIMIT 10
");

// Get customer segments for filter dropdown
$segments = $db->fetchAll("SELECT DISTINCT segment FROM customers WHERE segment IS NOT NULL ORDER BY segment");

function addCustomer($db, $data) {
    try {
        $db->beginTransaction();
        
        // Generate username and password
        $username = generateUsername($data['contact_person'], time());
        $password = generatePassword();
        
        // Create user account
        $userId = $db->insert('users', [
            'username' => $username,
            'password' => hashPassword($password),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => 'customer',
            'status' => 'active'
        ]);
        
        // Create customer record
        $customerId = $db->insert('customers', [
            'user_id' => $userId,
            'company_name' => $data['company_name'],
            'contact_person' => $data['contact_person'],
            'address' => $data['address'],
            'city' => $data['city'],
            'state' => $data['state'],
            'pincode' => $data['pincode'],
            'gst_number' => $data['gst_number'],
            'pan_number' => $data['pan_number'],
            'credit_limit' => $data['credit_limit'] ?? 0,
            'payment_terms' => $data['payment_terms'] ?? 30
        ]);
        
        // Send welcome email with credentials
        $emailSubject = "Welcome to Water Purifier ERP System";
        $emailMessage = "
        <html>
        <head><title>Welcome to Water Purifier ERP</title></head>
        <body>
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #0d6efd;'>Welcome to Water Purifier ERP System</h2>
                <p>Dear " . $data['contact_person'] . ",</p>
                <p>Your account has been created successfully. Here are your login credentials:</p>
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                    <p><strong>Username:</strong> $username</p>
                    <p><strong>Password:</strong> $password</p>
                    <p><strong>Login URL:</strong> <a href='https://your-domain.com'>https://your-domain.com</a></p>
                </div>
                <p>Please change your password after your first login for security purposes.</p>
                <p>If you have any questions, please contact our support team.</p>
                <hr>
                <p style='color: #666; font-size: 12px;'>This is an automated message from Water Purifier ERP System.</p>
            </div>
        </body>
        </html>";
        
        $emailSent = sendEmail($data['email'], $emailSubject, $emailMessage);
        
        // Send SMS with credentials
        $smsMessage = "Welcome to Water Purifier ERP! Username: $username, Password: $password. Login: https://your-domain.com";
        $smsSent = sendSMS($data['phone'], $smsMessage);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'customer_added', "Added customer: " . $data['contact_person']);
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Customer added successfully',
            'data' => [
                'customer_id' => $customerId,
                'username' => $username,
                'password' => $password,
                'email_sent' => $emailSent,
                'sms_sent' => $smsSent
            ]
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'Error adding customer: ' . $e->getMessage()];
    }
}

function updateCustomer($db, $data) {
    try {
        // Update customer details
        $db->update('customers', [
            'company_name' => $data['company_name'],
            'contact_person' => $data['contact_person'],
            'address' => $data['address'],
            'city' => $data['city'],
            'state' => $data['state'],
            'pincode' => $data['pincode'],
            'gst_number' => $data['gst_number'],
            'pan_number' => $data['pan_number'],
            'credit_limit' => $data['credit_limit'],
            'payment_terms' => $data['payment_terms']
        ], 'id = ?', [$data['id']]);
        
        // Update user details
        $db->update('users', [
            'email' => $data['email'],
            'phone' => $data['phone']
        ], 'id = (SELECT user_id FROM customers WHERE id = ?)', [$data['id']]);
        
        logActivity($_SESSION['user_id'], 'customer_updated', "Updated customer ID: " . $data['id']);
        
        return ['success' => true, 'message' => 'Customer updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating customer: ' . $e->getMessage()];
    }
}

function deleteCustomer($db, $customerId) {
    try {
        $db->beginTransaction();
        
        // Get user ID
        $customer = $db->fetchOne('SELECT user_id FROM customers WHERE id = ?', [$customerId]);
        
        // Delete customer record
        $db->delete('customers', 'id = ?', [$customerId]);
        
        // Delete user account
        $db->delete('users', 'id = ?', [$customer['user_id']]);
        
        logActivity($_SESSION['user_id'], 'customer_deleted', "Deleted customer ID: " . $customerId);
        
        $db->commit();
        
        return ['success' => true, 'message' => 'Customer deleted successfully'];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'Error deleting customer: ' . $e->getMessage()];
    }
}

function sendCustomerCredentials($db, $customerId) {
    try {
        $customer = $db->fetchOne("
            SELECT c.*, u.username, u.email, u.phone 
            FROM customers c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.id = ?
        ", [$customerId]);
        
        if (!$customer) {
            return ['success' => false, 'message' => 'Customer not found'];
        }
        
        // Generate new password
        $newPassword = generatePassword();
        $db->update('users', ['password' => hashPassword($newPassword)], 'id = ?', [$customer['user_id']]);
        
        // Send email with new credentials
        $emailSubject = "Updated Login Credentials - Water Purifier ERP";
        $emailMessage = "
        <html>
        <head><title>Updated Credentials</title></head>
        <body>
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #0d6efd;'>Updated Login Credentials</h2>
                <p>Dear " . $customer['contact_person'] . ",</p>
                <p>Your login credentials have been updated:</p>
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                    <p><strong>Username:</strong> " . $customer['username'] . "</p>
                    <p><strong>New Password:</strong> $newPassword</p>
                    <p><strong>Login URL:</strong> <a href='https://your-domain.com'>https://your-domain.com</a></p>
                </div>
                <p>Please change your password after your next login.</p>
            </div>
        </body>
        </html>";
        
        $emailSent = sendEmail($customer['email'], $emailSubject, $emailMessage);
        
        // Send SMS
        $smsMessage = "Updated credentials - Username: " . $customer['username'] . ", Password: $newPassword";
        $smsSent = sendSMS($customer['phone'], $smsMessage);
        
        logActivity($_SESSION['user_id'], 'credentials_sent', "Sent credentials to customer ID: " . $customerId);
        
        return [
            'success' => true, 
            'message' => 'Credentials sent successfully',
            'data' => [
                'username' => $customer['username'],
                'password' => $newPassword,
                'email_sent' => $emailSent,
                'sms_sent' => $smsSent
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error sending credentials: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Water Purifier ERP</title>
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
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-users me-2"></i>Customer Management
                        </h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                            <i class="fas fa-plus me-1"></i>Add Customer
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Customer Analytics Dashboard -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h5 class="card-title">Total Customers</h5>
                                                <h3><?php echo $totalCustomers; ?></h3>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-users fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h5 class="card-title">Active Customers</h5>
                                                <h3><?php echo count(array_filter($customers, function($c) { return $c['user_status'] === 'active'; })); ?></h3>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-user-check fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h5 class="card-title">Avg Credit Limit</h5>
                                                <h3>₹<?php echo number_format(array_sum(array_column($customers, 'credit_limit')) / max(count($customers), 1)); ?></h3>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-credit-card fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h5 class="card-title">Top Revenue</h5>
                                                <h3>₹<?php echo number_format(max(array_column($customers, 'total_paid'))); ?></h3>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-chart-line fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Search and Filters -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="searchInput" placeholder="Search customers..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="segmentFilter">
                                    <option value="">All Segments</option>
                                    <?php foreach ($segments as $segment): ?>
                                    <option value="<?php echo htmlspecialchars($segment['segment']); ?>" <?php echo $segment_filter === $segment['segment'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($segment['segment']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="creditFilter">
                                    <option value="">All Credit Levels</option>
                                    <option value="high" <?php echo $credit_filter === 'high' ? 'selected' : ''; ?>>High (₹1L+)</option>
                                    <option value="medium" <?php echo $credit_filter === 'medium' ? 'selected' : ''; ?>>Medium (₹50K-₹1L)</option>
                                    <option value="low" <?php echo $credit_filter === 'low' ? 'selected' : ''; ?>>Low (<₹50K)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2 text-end">
                                <button class="btn btn-outline-primary me-2" onclick="exportCustomers()">
                                    <i class="fas fa-download me-1"></i>Export
                                </button>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#analyticsModal">
                                    <i class="fas fa-chart-bar me-1"></i>Analytics
                                </button>
                            </div>
                        </div>

                        <!-- Customers Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Contact</th>
                                        <th>Segment</th>
                                        <th>Credit Limit</th>
                                        <th>Balance</th>
                                        <th>Services</th>
                                        <th>Revenue</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($customer['company_name'] ?: $customer['contact_person']); ?></strong>
                                                <?php if ($customer['company_name']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($customer['contact_person']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($customer['email']); ?><br>
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($customer['phone']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getSegmentBadge($customer['segment'] ?? 'Standard'); ?>">
                                                <?php echo htmlspecialchars($customer['segment'] ?? 'Standard'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo formatCurrency($customer['credit_limit'] ?? 0); ?></strong>
                                        </td>
                                        <td>
                                            <span class="<?php echo ($customer['current_balance'] ?? 0) < 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo formatCurrency($customer['current_balance'] ?? 0); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $customer['total_services']; ?> Services</span>
                                            <?php if ($customer['recent_services'] > 0): ?>
                                            <br><small class="text-success">+<?php echo $customer['recent_services']; ?> this month</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo formatCurrency($customer['total_paid']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadge($customer['user_status']); ?>">
                                                <?php echo ucfirst($customer['user_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewCustomer(<?php echo $customer['id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" onclick="editCustomer(<?php echo $customer['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" onclick="sendCredentials(<?php echo $customer['id']; ?>)" title="Send Credentials">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteCustomer(<?php echo $customer['id']; ?>)" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Customer pagination">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Add New Customer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addCustomerForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="company_name" name="company_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_person" class="form-label">Contact Person *</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="state" class="form-label">State</label>
                                <input type="text" class="form-control" id="state" name="state">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="pincode" class="form-label">Pincode</label>
                                <input type="text" class="form-control" id="pincode" name="pincode">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="gst_number" class="form-label">GST Number</label>
                                <input type="text" class="form-control" id="gst_number" name="gst_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="pan_number" class="form-label">PAN Number</label>
                                <input type="text" class="form-control" id="pan_number" name="pan_number">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="credit_limit" class="form-label">Credit Limit (₹)</label>
                                <input type="number" class="form-control" id="credit_limit" name="credit_limit" value="0" step="0.01">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="payment_terms" class="form-label">Payment Terms (Days)</label>
                                <input type="number" class="form-control" id="payment_terms" name="payment_terms" value="30">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Add Customer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div class="modal fade" id="editCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>Edit Customer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editCustomerForm">
                    <input type="hidden" id="edit_customer_id" name="id">
                    <div class="modal-body">
                        <!-- Same form fields as add customer -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="edit_company_name" name="company_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_contact_person" class="form-label">Contact Person *</label>
                                <input type="text" class="form-control" id="edit_contact_person" name="contact_person" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_phone" class="form-label">Phone *</label>
                                <input type="tel" class="form-control" id="edit_phone" name="phone" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_address" class="form-label">Address</label>
                            <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_city" class="form-label">City</label>
                                <input type="text" class="form-control" id="edit_city" name="city">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_state" class="form-label">State</label>
                                <input type="text" class="form-control" id="edit_state" name="state">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_pincode" class="form-label">Pincode</label>
                                <input type="text" class="form-control" id="edit_pincode" name="pincode">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_gst_number" class="form-label">GST Number</label>
                                <input type="text" class="form-control" id="edit_gst_number" name="gst_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_pan_number" class="form-label">PAN Number</label>
                                <input type="text" class="form-control" id="edit_pan_number" name="pan_number">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_credit_limit" class="form-label">Credit Limit (₹)</label>
                                <input type="number" class="form-control" id="edit_credit_limit" name="credit_limit" step="0.01">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_payment_terms" class="form-label">Payment Terms (Days)</label>
                                <input type="number" class="form-control" id="edit_payment_terms" name="payment_terms">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-1"></i>Update Customer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Customer Modal -->
    <div class="modal fade" id="viewCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i>Customer Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="customerDetails">
                    <!-- Customer details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Analytics Modal -->
    <div class="modal fade" id="analyticsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-bar me-2"></i>Customer Analytics
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Segment Distribution -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Customer Segments</h6>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($customerAnalytics as $analytics): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-<?php echo getSegmentBadge($analytics['segment']); ?>">
                                            <?php echo htmlspecialchars($analytics['segment']); ?>
                                        </span>
                                        <div class="text-end">
                                            <strong><?php echo $analytics['customer_count']; ?> customers</strong><br>
                                            <small class="text-muted">Avg Credit: ₹<?php echo number_format($analytics['avg_credit_limit']); ?></small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Top Customers -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Top Customers by Revenue</h6>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($topCustomers as $index => $customer): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong><?php echo htmlspecialchars($customer['company_name'] ?: $customer['contact_person']); ?></strong><br>
                                            <small class="text-muted"><?php echo $customer['total_services']; ?> services</small>
                                        </div>
                                        <div class="text-end">
                                            <strong>₹<?php echo number_format($customer['total_revenue']); ?></strong>
                                        </div>
                                    </div>
                                    <?php if ($index < count($topCustomers) - 1): ?>
                                    <hr class="my-2">
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Growth Chart -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Customer Growth Over Time</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="customerGrowthChart" height="100"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="exportAnalytics()">
                        <i class="fas fa-download me-1"></i>Export Analytics
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../assets/js/customers.js"></script>
</body>
</html>
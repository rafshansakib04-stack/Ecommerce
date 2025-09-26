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

// Get customers with pagination and search
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';

$whereClause = '';
$params = [];

if (!empty($search)) {
    $whereClause = "WHERE (c.company_name LIKE ? OR c.contact_person LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$customers = $db->fetchAll("
    SELECT c.*, u.username, u.email, u.phone, u.status as user_status,
           (SELECT COUNT(*) FROM service_requests sr WHERE sr.customer_id = c.id) as total_services,
           (SELECT COALESCE(SUM(i.total_amount), 0) FROM invoices i WHERE i.customer_id = c.id AND i.status = 'paid') as total_paid
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
                        <!-- Search and Filters -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="searchInput" placeholder="Search customers..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <button class="btn btn-outline-primary" onclick="exportCustomers()">
                                    <i class="fas fa-download me-1"></i>Export
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
                                        <th>Services</th>
                                        <th>Total Paid</th>
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
                                            <span class="badge bg-info"><?php echo $customer['total_services']; ?> Services</span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../assets/js/customers.js"></script>
</body>
</html>
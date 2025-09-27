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
        case 'add_technician':
            $result = addTechnician($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'update_technician':
            $result = updateTechnician($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'delete_technician':
            $result = deleteTechnician($db, $_POST['id']);
            echo json_encode($result);
            exit();
            
        case 'update_status':
            $result = updateTechnicianStatus($db, $_POST);
            echo json_encode($result);
            exit();
    }
}

// Get technicians with pagination and search
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(t.full_name LIKE ? OR t.employee_id LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($status)) {
    $whereConditions[] = "t.status = ?";
    $params[] = $status;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$technicians = $db->fetchAll("
    SELECT t.*, u.username, u.email, u.phone, u.status as user_status,
           (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_technician_id = t.id) as total_services,
           (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_technician_id = t.id AND sr.status = 'completed') as completed_services,
           (SELECT AVG(sr.customer_rating) FROM service_requests sr WHERE sr.assigned_technician_id = t.id AND sr.customer_rating IS NOT NULL) as avg_rating
    FROM technicians t 
    JOIN users u ON t.user_id = u.id 
    $whereClause
    ORDER BY t.created_at DESC 
    LIMIT $limit OFFSET $offset
", $params);

$totalTechnicians = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM technicians t 
    JOIN users u ON t.user_id = u.id 
    $whereClause
", $params)['count'];

$totalPages = ceil($totalTechnicians / $limit);

function addTechnician($db, $data) {
    try {
        $db->beginTransaction();
        
        // Generate username and password
        $username = generateUsername($data['full_name'], time());
        $password = generatePassword();
        
        // Create user account
        $userId = $db->insert('users', [
            'username' => $username,
            'password' => hashPassword($password),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => 'technician',
            'status' => 'active'
        ]);
        
        // Create technician record
        $technicianId = $db->insert('technicians', [
            'user_id' => $userId,
            'employee_id' => $data['employee_id'],
            'full_name' => $data['full_name'],
            'skills' => $data['skills'],
            'service_areas' => $data['service_areas'],
            'photo' => $data['photo'] ?? '',
            'emergency_contact' => $data['emergency_contact'],
            'address' => $data['address'],
            'joining_date' => $data['joining_date'],
            'salary' => $data['salary'] ?? 0,
            'commission_rate' => $data['commission_rate'] ?? 0,
            'status' => 'active'
        ]);
        
        // Send welcome email with credentials
        $emailSubject = "Welcome to Water Purifier ERP - Technician Account";
        $emailMessage = "
        <html>
        <head><title>Technician Account Created</title></head>
        <body>
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #0d6efd;'>Welcome to Water Purifier ERP System</h2>
                <p>Dear " . $data['full_name'] . ",</p>
                <p>Your technician account has been created successfully.</p>
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                    <p><strong>Employee ID:</strong> " . $data['employee_id'] . "</p>
                    <p><strong>Username:</strong> $username</p>
                    <p><strong>Password:</strong> $password</p>
                    <p><strong>Login URL:</strong> <a href='https://your-domain.com'>https://your-domain.com</a></p>
                </div>
                <p>Please change your password after your first login.</p>
                <p>You can now access the technician portal to view your assigned tasks.</p>
            </div>
        </body>
        </html>";
        
        $emailSent = sendEmail($data['email'], $emailSubject, $emailMessage);
        
        // Send SMS with credentials
        $smsMessage = "Welcome! Employee ID: " . $data['employee_id'] . ", Username: $username, Password: $password. Login: https://your-domain.com";
        $smsSent = sendSMS($data['phone'], $smsMessage);
        
        logActivity($_SESSION['user_id'], 'technician_added', "Added technician: " . $data['full_name']);
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Technician added successfully',
            'data' => [
                'technician_id' => $technicianId,
                'username' => $username,
                'password' => $password,
                'email_sent' => $emailSent,
                'sms_sent' => $smsSent
            ]
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'Error adding technician: ' . $e->getMessage()];
    }
}

function updateTechnician($db, $data) {
    try {
        // Update technician details
        $db->update('technicians', [
            'employee_id' => $data['employee_id'],
            'full_name' => $data['full_name'],
            'skills' => $data['skills'],
            'service_areas' => $data['service_areas'],
            'emergency_contact' => $data['emergency_contact'],
            'address' => $data['address'],
            'salary' => $data['salary'],
            'commission_rate' => $data['commission_rate']
        ], 'id = ?', [$data['id']]);
        
        // Update user details
        $db->update('users', [
            'email' => $data['email'],
            'phone' => $data['phone']
        ], 'id = (SELECT user_id FROM technicians WHERE id = ?)', [$data['id']]);
        
        logActivity($_SESSION['user_id'], 'technician_updated', "Updated technician ID: " . $data['id']);
        
        return ['success' => true, 'message' => 'Technician updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating technician: ' . $e->getMessage()];
    }
}

function deleteTechnician($db, $technicianId) {
    try {
        $db->beginTransaction();
        
        // Get user ID
        $technician = $db->fetchOne('SELECT user_id FROM technicians WHERE id = ?', [$technicianId]);
        
        // Delete technician record
        $db->delete('technicians', 'id = ?', [$technicianId]);
        
        // Delete user account
        $db->delete('users', 'id = ?', [$technician['user_id']]);
        
        logActivity($_SESSION['user_id'], 'technician_deleted', "Deleted technician ID: " . $technicianId);
        
        $db->commit();
        
        return ['success' => true, 'message' => 'Technician deleted successfully'];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'Error deleting technician: ' . $e->getMessage()];
    }
}

function updateTechnicianStatus($db, $data) {
    try {
        $db->update('technicians', [
            'status' => $data['status']
        ], 'id = ?', [$data['id']]);
        
        logActivity($_SESSION['user_id'], 'technician_status_updated', "Updated technician status ID: " . $data['id']);
        
        return ['success' => true, 'message' => 'Technician status updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Management - Water Purifier ERP</title>
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
                            <i class="fas fa-tools me-2"></i>Technician Management
                        </h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTechnicianModal">
                            <i class="fas fa-plus me-1"></i>Add Technician
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Search and Filters -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="searchInput" placeholder="Search technicians..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="on_leave" <?php echo $status === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                                </select>
                            </div>
                            <div class="col-md-3 text-end">
                                <button class="btn btn-outline-primary" onclick="exportTechnicians()">
                                    <i class="fas fa-download me-1"></i>Export
                                </button>
                            </div>
                        </div>

                        <!-- Technicians Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Technician</th>
                                        <th>Employee ID</th>
                                        <th>Contact</th>
                                        <th>Services</th>
                                        <th>Rating</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($technicians as $technician): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($technician['photo']): ?>
                                                <img src="<?php echo htmlspecialchars($technician['photo']); ?>" class="rounded-circle me-2" width="40" height="40" alt="Photo">
                                                <?php else: ?>
                                                <div class="bg-primary rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user text-white"></i>
                                                </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($technician['full_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($technician['skills']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($technician['employee_id']); ?></code></td>
                                        <td>
                                            <div>
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($technician['email']); ?><br>
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($technician['phone']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="badge bg-info"><?php echo $technician['total_services']; ?> Total</span>
                                                <br><span class="badge bg-success"><?php echo $technician['completed_services']; ?> Completed</span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($technician['avg_rating']): ?>
                                                <div class="d-flex align-items-center">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $technician['avg_rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ms-1">(<?php echo number_format($technician['avg_rating'], 1); ?>)</span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">No rating</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadge($technician['status']); ?>">
                                                <?php echo ucfirst($technician['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewTechnician(<?php echo $technician['id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" onclick="editTechnician(<?php echo $technician['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" onclick="updateStatus(<?php echo $technician['id']; ?>)" title="Update Status">
                                                    <i class="fas fa-toggle-on"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteTechnician(<?php echo $technician['id']; ?>)" title="Delete">
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
                        <nav aria-label="Technician pagination">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>">
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

    <!-- Add Technician Modal -->
    <div class="modal fade" id="addTechnicianModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Add New Technician
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addTechnicianForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="employee_id" class="form-label">Employee ID *</label>
                                <input type="text" class="form-control" id="employee_id" name="employee_id" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
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
                            <label for="skills" class="form-label">Skills</label>
                            <textarea class="form-control" id="skills" name="skills" rows="2" placeholder="e.g., Water purifier installation, maintenance, repair"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="service_areas" class="form-label">Service Areas</label>
                            <textarea class="form-control" id="service_areas" name="service_areas" rows="2" placeholder="e.g., North Delhi, Central Delhi, East Delhi"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="emergency_contact" class="form-label">Emergency Contact</label>
                                <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="joining_date" class="form-label">Joining Date</label>
                                <input type="date" class="form-control" id="joining_date" name="joining_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="salary" class="form-label">Salary (₹)</label>
                                <input type="number" class="form-control" id="salary" name="salary" step="0.01" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="commission_rate" class="form-label">Commission Rate (%)</label>
                                <input type="number" class="form-control" id="commission_rate" name="commission_rate" step="0.01" min="0" max="100">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Add Technician
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Technician Modal -->
    <div class="modal fade" id="viewTechnicianModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i>Technician Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="technicianDetails"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-toggle-on me-2"></i>Update Status
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="updateStatusForm">
                    <input type="hidden" id="status_technician_id" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="technician_status" class="form-label">Status</label>
                            <select class="form-select" id="technician_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="on_leave">On Leave</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-1"></i>Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Technician Modal -->
    <div class="modal fade" id="editTechnicianModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>Edit Technician
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editTechnicianForm">
                    <input type="hidden" id="edit_technician_id" name="id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_employee_id" class="form-label">Employee ID *</label>
                                <input type="text" class="form-control" id="edit_employee_id" name="employee_id" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
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
                            <label for="edit_skills" class="form-label">Skills</label>
                            <textarea class="form-control" id="edit_skills" name="skills" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_service_areas" class="form-label">Service Areas</label>
                            <textarea class="form-control" id="edit_service_areas" name="service_areas" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_emergency_contact" class="form-label">Emergency Contact</label>
                                <input type="tel" class="form-control" id="edit_emergency_contact" name="emergency_contact">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_joining_date" class="form-label">Joining Date</label>
                                <input type="date" class="form-control" id="edit_joining_date" name="joining_date">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_salary" class="form-label">Salary (₹)</label>
                                <input type="number" class="form-control" id="edit_salary" name="salary" step="0.01" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_commission_rate" class="form-label">Commission Rate (%)</label>
                                <input type="number" class="form-control" id="edit_commission_rate" name="commission_rate" step="0.01" min="0" max="100">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-1"></i>Update Technician
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../assets/js/technicians.js"></script>
</body>
</html>
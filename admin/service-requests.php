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
        case 'assign_technician':
            $result = assignTechnician($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'update_status':
            $result = updateServiceStatus($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'add_note':
            $result = addServiceNote($db, $_POST);
            echo json_encode($result);
            exit();
    }
}

// Get service requests with filters
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$technician = $_GET['technician'] ?? '';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$whereConditions = [];
$params = [];

if (!empty($status)) {
    $whereConditions[] = "sr.status = ?";
    $params[] = $status;
}

if (!empty($priority)) {
    $whereConditions[] = "sr.priority = ?";
    $params[] = $priority;
}

if (!empty($technician)) {
    $whereConditions[] = "sr.assigned_technician_id = ?";
    $params[] = $technician;
}

if (!empty($search)) {
    $whereConditions[] = "(c.company_name LIKE ? OR c.contact_person LIKE ? OR sr.ticket_number LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$serviceRequests = $db->fetchAll("
    SELECT sr.*, c.company_name, c.contact_person, c.phone as customer_phone,
           t.full_name as technician_name, t.phone as technician_phone
    FROM service_requests sr 
    JOIN customers c ON sr.customer_id = c.id 
    LEFT JOIN technicians t ON sr.assigned_technician_id = t.id
    $whereClause
    ORDER BY sr.created_at DESC 
    LIMIT $limit OFFSET $offset
", $params);

$totalRequests = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM service_requests sr 
    JOIN customers c ON sr.customer_id = c.id 
    $whereClause
", $params)['count'];

$totalPages = ceil($totalRequests / $limit);

// Get technicians for filter
$technicians = $db->fetchAll("SELECT id, full_name FROM technicians WHERE status = 'active' ORDER BY full_name");

function assignTechnician($db, $data) {
    try {
        $db->update('service_requests', [
            'assigned_technician_id' => $data['technician_id'],
            'status' => 'assigned',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$data['service_id']]);
        
        // Get service and technician details
        $service = $db->fetchOne("
            SELECT sr.*, c.contact_person, c.phone, t.full_name, t.phone as tech_phone
            FROM service_requests sr 
            JOIN customers c ON sr.customer_id = c.id 
            JOIN technicians t ON t.id = ?
            WHERE sr.id = ?
        ", [$data['technician_id'], $data['service_id']]);
        
        // Send notification to technician
        $firebase = new FirebaseService();
        $firebase->updateRealtimeData('notifications/' . $service['assigned_technician_id'], [
            'title' => 'New Service Assignment',
            'message' => 'You have been assigned to service request: ' . $service['ticket_number'],
            'timestamp' => time(),
            'type' => 'info'
        ]);
        
        // Send SMS to technician
        $smsMessage = "New service assigned: " . $service['ticket_number'] . " - " . $service['contact_person'] . " (" . $service['phone'] . ")";
        sendSMS($service['tech_phone'], $smsMessage);
        
        logActivity($_SESSION['user_id'], 'technician_assigned', "Assigned technician to service: " . $service['ticket_number']);
        
        return ['success' => true, 'message' => 'Technician assigned successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error assigning technician: ' . $e->getMessage()];
    }
}

function updateServiceStatus($db, $data) {
    try {
        $db->update('service_requests', [
            'status' => $data['status'],
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$data['service_id']]);
        
        // Update timestamps based on status
        if ($data['status'] === 'in_progress') {
            $db->update('service_requests', [
                'actual_start_time' => date('Y-m-d H:i:s')
            ], 'id = ?', [$data['service_id']]);
        } elseif ($data['status'] === 'completed') {
            $db->update('service_requests', [
                'actual_end_time' => date('Y-m-d H:i:s')
            ], 'id = ?', [$data['service_id']]);
        }
        
        logActivity($_SESSION['user_id'], 'service_status_updated', "Updated service status to: " . $data['status']);
        
        return ['success' => true, 'message' => 'Service status updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()];
    }
}

function addServiceNote($db, $data) {
    try {
        // Add note to activity log
        logActivity($_SESSION['user_id'], 'service_note_added', $data['note'] . " (Service ID: " . $data['service_id'] . ")");
        
        return ['success' => true, 'message' => 'Note added successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error adding note: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Requests - Water Purifier ERP</title>
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
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-tools me-2"></i>Service Requests Management
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="assigned" <?php echo $status === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                                    <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="priorityFilter">
                                    <option value="">All Priority</option>
                                    <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="normal" <?php echo $priority === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="urgent" <?php echo $priority === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="technicianFilter">
                                    <option value="">All Technicians</option>
                                    <?php foreach ($technicians as $tech): ?>
                                    <option value="<?php echo $tech['id']; ?>" <?php echo $technician == $tech['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tech['full_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="searchInput" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Service Requests Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ticket #</th>
                                        <th>Customer</th>
                                        <th>Service Type</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Technician</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($serviceRequests as $request): ?>
                                    <tr data-service-id="<?php echo $request['id']; ?>">
                                        <td>
                                            <strong><?php echo $request['ticket_number']; ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($request['company_name'] ?: $request['contact_person']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($request['customer_phone']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo ucfirst($request['service_type']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadge($request['priority']); ?>">
                                                <?php echo ucfirst($request['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadge($request['status']); ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($request['technician_name']): ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($request['technician_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($request['technician_phone']); ?></small>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo formatDate($request['created_at']); ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewService(<?php echo $request['id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-outline-success" onclick="assignTechnician(<?php echo $request['id']; ?>)" title="Assign">
                                                    <i class="fas fa-user-plus"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-warning" onclick="updateStatus(<?php echo $request['id']; ?>)" title="Update Status">
                                                    <i class="fas fa-edit"></i>
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
                        <nav aria-label="Service requests pagination">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&priority=<?php echo urlencode($priority); ?>&technician=<?php echo urlencode($technician); ?>&search=<?php echo urlencode($search); ?>">
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

    <!-- Assign Technician Modal -->
    <div class="modal fade" id="assignTechnicianModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Assign Technician
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="assignTechnicianForm">
                    <input type="hidden" id="assign_service_id" name="service_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="assign_technician_id" class="form-label">Select Technician</label>
                            <select class="form-select" id="assign_technician_id" name="technician_id" required>
                                <option value="">Choose technician...</option>
                                <?php foreach ($technicians as $tech): ?>
                                <option value="<?php echo $tech['id']; ?>">
                                    <?php echo htmlspecialchars($tech['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-user-plus me-1"></i>Assign Technician
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Update Status
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="updateStatusForm">
                    <input type="hidden" id="update_service_id" name="service_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="update_status" class="form-label">New Status</label>
                            <select class="form-select" id="update_status" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="assigned">Assigned</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="status_note" class="form-label">Note (Optional)</label>
                            <textarea class="form-control" id="status_note" name="note" rows="3"></textarea>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../assets/js/service-requests.js"></script>
</body>
</html>
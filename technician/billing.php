<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is technician
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'technician') {
    header('Location: ../index.php');
    exit();
}

$db = Database::getInstance();

// Get technician details
$technician = $db->fetchOne("
    SELECT t.*, u.username, u.email, u.phone 
    FROM technicians t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.user_id = ?
", [$_SESSION['user_id']]);

if (!$technician) {
    header('Location: ../index.php');
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'create_bill':
            $result = createBill($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'update_bill':
            $result = updateBill($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'mark_paid':
            $result = markBillPaid($db, $_POST);
            echo json_encode($result);
            exit();
    }
}

// Get bills with pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$whereConditions = ['technician_id = ?'];
$params = [$technician['id']];

if (!empty($status)) {
    $whereConditions[] = 'status = ?';
    $params[] = $status;
}

if (!empty($search)) {
    $whereConditions[] = '(bill_number LIKE ? OR customer_name LIKE ? OR service_type LIKE ?)';
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

$bills = $db->fetchAll("
    SELECT * FROM technician_bills 
    $whereClause
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
", $params);

$totalBills = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM technician_bills 
    $whereClause
", $params)['count'];

$totalPages = ceil($totalBills / $limit);

// Get summary statistics
$summary = [
    'total_bills' => $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM technician_bills 
        WHERE technician_id = ?
    ", [$technician['id']])['count'],
    
    'total_amount' => $db->fetchOne("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM technician_bills 
        WHERE technician_id = ?
    ", [$technician['id']])['total'],
    
    'paid_amount' => $db->fetchOne("
        SELECT COALESCE(SUM(paid_amount), 0) as total 
        FROM technician_bills 
        WHERE technician_id = ? AND status = 'paid'
    ", [$technician['id']])['total'],
    
    'pending_amount' => $db->fetchOne("
        SELECT COALESCE(SUM(balance_amount), 0) as total 
        FROM technician_bills 
        WHERE technician_id = ? AND status != 'paid'
    ", [$technician['id']])['total']
];

function createBill($db, $data) {
    try {
        $db->beginTransaction();
        
        // Generate bill number
        $billNumber = generateBillNumber();
        
        // Create bill
        $billId = $db->insert('technician_bills', [
            'bill_number' => $billNumber,
            'technician_id' => $_SESSION['user_id'],
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_address' => $data['customer_address'],
            'service_type' => $data['service_type'],
            'service_description' => $data['service_description'],
            'service_date' => $data['service_date'],
            'labor_charges' => $data['labor_charges'],
            'parts_cost' => $data['parts_cost'],
            'travel_charges' => $data['travel_charges'],
            'other_charges' => $data['other_charges'],
            'subtotal' => $data['subtotal'],
            'tax_amount' => $data['tax_amount'],
            'total_amount' => $data['total_amount'],
            'balance_amount' => $data['total_amount'],
            'status' => 'pending',
            'notes' => $data['notes'] ?? ''
        ]);
        
        logActivity($_SESSION['user_id'], 'bill_created', "Created bill: " . $billNumber);
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Bill created successfully',
            'bill_id' => $billId,
            'bill_number' => $billNumber
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'Error creating bill: ' . $e->getMessage()];
    }
}

function updateBill($db, $data) {
    try {
        $db->update('technician_bills', [
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_address' => $data['customer_address'],
            'service_type' => $data['service_type'],
            'service_description' => $data['service_description'],
            'service_date' => $data['service_date'],
            'labor_charges' => $data['labor_charges'],
            'parts_cost' => $data['parts_cost'],
            'travel_charges' => $data['travel_charges'],
            'other_charges' => $data['other_charges'],
            'subtotal' => $data['subtotal'],
            'tax_amount' => $data['tax_amount'],
            'total_amount' => $data['total_amount'],
            'balance_amount' => $data['total_amount'],
            'notes' => $data['notes'] ?? ''
        ], 'id = ? AND technician_id = ?', [$data['id'], $_SESSION['user_id']]);
        
        logActivity($_SESSION['user_id'], 'bill_updated', "Updated bill ID: " . $data['id']);
        
        return ['success' => true, 'message' => 'Bill updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating bill: ' . $e->getMessage()];
    }
}

function markBillPaid($db, $data) {
    try {
        $db->beginTransaction();
        
        // Get bill details
        $bill = $db->fetchOne('SELECT * FROM technician_bills WHERE id = ? AND technician_id = ?', [$data['id'], $_SESSION['user_id']]);
        
        if (!$bill) {
            return ['success' => false, 'message' => 'Bill not found'];
        }
        
        // Update bill status
        $db->update('technician_bills', [
            'status' => 'paid',
            'paid_amount' => $bill['total_amount'],
            'balance_amount' => 0,
            'payment_date' => date('Y-m-d'),
            'payment_method' => $data['payment_method'],
            'payment_notes' => $data['payment_notes'] ?? ''
        ], 'id = ?', [$data['id']]);
        
        // Add to daily cashbook
        $db->insert('daily_cashbook', [
            'user_id' => $_SESSION['user_id'],
            'transaction_date' => date('Y-m-d'),
            'transaction_type' => 'income',
            'category' => 'Service Payment',
            'description' => 'Payment received for bill ' . $bill['bill_number'],
            'amount' => $bill['total_amount'],
            'payment_method' => $data['payment_method'],
            'reference_number' => $data['reference_number'] ?? '',
            'notes' => $data['payment_notes'] ?? ''
        ]);
        
        logActivity($_SESSION['user_id'], 'bill_paid', "Marked bill as paid: " . $bill['bill_number']);
        
        $db->commit();
        
        return ['success' => true, 'message' => 'Bill marked as paid successfully'];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'Error marking bill as paid: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing - Water Purifier ERP</title>
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
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $summary['total_bills']; ?></div>
                            <div class="stat-label">Total Bills</div>
                        </div>
                        <div class="stat-icon text-primary">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number">₹<?php echo number_format($summary['total_amount']); ?></div>
                            <div class="stat-label">Total Amount</div>
                        </div>
                        <div class="stat-icon text-success">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number">₹<?php echo number_format($summary['paid_amount']); ?></div>
                            <div class="stat-label">Paid Amount</div>
                        </div>
                        <div class="stat-icon text-info">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number">₹<?php echo number_format($summary['pending_amount']); ?></div>
                            <div class="stat-label">Pending Amount</div>
                        </div>
                        <div class="stat-icon text-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-file-invoice me-2"></i>Billing Management
                        </h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBillModal">
                            <i class="fas fa-plus me-1"></i>Create Bill
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="overdue" <?php echo $status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="searchInput" placeholder="Search bills..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-outline-primary" onclick="exportBills()">
                                    <i class="fas fa-download me-1"></i>Export
                                </button>
                            </div>
                        </div>

                        <!-- Bills Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Bill #</th>
                                        <th>Customer</th>
                                        <th>Service</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bills as $bill): ?>
                                    <tr>
                                        <td><strong><?php echo $bill['bill_number']; ?></strong></td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($bill['customer_name']); ?></strong>
                                                <?php if ($bill['customer_phone']): ?>
                                                <br><small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($bill['customer_phone']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($bill['service_type']); ?></strong>
                                                <?php if ($bill['service_description']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($bill['service_description'], 0, 50)) . '...'; ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo formatDate($bill['service_date']); ?></td>
                                        <td>
                                            <div>
                                                <strong>₹<?php echo number_format($bill['total_amount'], 2); ?></strong>
                                                <?php if ($bill['balance_amount'] < $bill['total_amount']): ?>
                                                <br><small class="text-success">Paid: ₹<?php echo number_format($bill['total_amount'] - $bill['balance_amount'], 2); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadge($bill['status']); ?>">
                                                <?php echo ucfirst($bill['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewBill(<?php echo $bill['id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" onclick="editBill(<?php echo $bill['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" onclick="printBill(<?php echo $bill['id']; ?>)" title="Print">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                                <?php if ($bill['status'] !== 'paid'): ?>
                                                <button class="btn btn-sm btn-outline-success" onclick="markPaid(<?php echo $bill['id']; ?>)" title="Mark Paid">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Bill pagination">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">
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

    <!-- View Bill Modal -->
    <div class="modal fade" id="viewBillModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>Bill Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="billDetails"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Bill Modal -->
    <div class="modal fade" id="editBillModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Bill
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editBillForm">
                    <input type="hidden" id="edit_bill_id" name="id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_customer_name" class="form-label">Customer Name *</label>
                                <input type="text" class="form-control" id="edit_customer_name" name="customer_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_customer_phone" class="form-label">Customer Phone</label>
                                <input type="tel" class="form-control" id="edit_customer_phone" name="customer_phone">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_customer_address" class="form-label">Customer Address</label>
                            <textarea class="form-control" id="edit_customer_address" name="customer_address" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_service_type" class="form-label">Service Type *</label>
                                <select class="form-select" id="edit_service_type" name="service_type" required>
                                    <option value="Installation">Installation</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Repair">Repair</option>
                                    <option value="Replacement">Replacement</option>
                                    <option value="Service">Service</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_service_date" class="form-label">Service Date *</label>
                                <input type="date" class="form-control" id="edit_service_date" name="service_date" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_service_description" class="form-label">Service Description</label>
                            <textarea class="form-control" id="edit_service_description" name="service_description" rows="3"></textarea>
                        </div>
                        
                        <h6 class="mt-4 mb-3">Charges Breakdown</h6>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="edit_labor_charges" class="form-label">Labor Charges (₹)</label>
                                <input type="number" class="form-control" id="edit_labor_charges" name="labor_charges" step="0.01" min="0" value="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="edit_parts_cost" class="form-label">Parts Cost (₹)</label>
                                <input type="number" class="form-control" id="edit_parts_cost" name="parts_cost" step="0.01" min="0" value="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="edit_travel_charges" class="form-label">Travel Charges (₹)</label>
                                <input type="number" class="form-control" id="edit_travel_charges" name="travel_charges" step="0.01" min="0" value="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="edit_other_charges" class="form-label">Other Charges (₹)</label>
                                <input type="number" class="form-control" id="edit_other_charges" name="other_charges" step="0.01" min="0" value="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>Bill Summary</h6>
                                        <div class="d-flex justify-content-between">
                                            <span>Subtotal:</span>
                                            <span id="edit_bill_subtotal">₹0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Tax (18%):</span>
                                            <span id="edit_bill_tax">₹0.00</span>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between fw-bold">
                                            <span>Total:</span>
                                            <span id="edit_bill_total">₹0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="edit_notes" name="notes" rows="4"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-1"></i>Update Bill
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Create Bill Modal -->
    <div class="modal fade" id="createBillModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-invoice me-2"></i>Create New Bill
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createBillForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="customer_name" class="form-label">Customer Name *</label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="customer_phone" class="form-label">Customer Phone</label>
                                <input type="tel" class="form-control" id="customer_phone" name="customer_phone">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="customer_address" class="form-label">Customer Address</label>
                            <textarea class="form-control" id="customer_address" name="customer_address" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="service_type" class="form-label">Service Type *</label>
                                <select class="form-select" id="service_type" name="service_type" required>
                                    <option value="">Select service type...</option>
                                    <option value="Installation">Installation</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Repair">Repair</option>
                                    <option value="Replacement">Replacement</option>
                                    <option value="Service">Service</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="service_date" class="form-label">Service Date *</label>
                                <input type="date" class="form-control" id="service_date" name="service_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="service_description" class="form-label">Service Description</label>
                            <textarea class="form-control" id="service_description" name="service_description" rows="3"></textarea>
                        </div>
                        
                        <h6 class="mt-4 mb-3">Charges Breakdown</h6>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="labor_charges" class="form-label">Labor Charges (₹)</label>
                                <input type="number" class="form-control" id="labor_charges" name="labor_charges" step="0.01" min="0" value="0" onchange="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="parts_cost" class="form-label">Parts Cost (₹)</label>
                                <input type="number" class="form-control" id="parts_cost" name="parts_cost" step="0.01" min="0" value="0" onchange="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="travel_charges" class="form-label">Travel Charges (₹)</label>
                                <input type="number" class="form-control" id="travel_charges" name="travel_charges" step="0.01" min="0" value="0" onchange="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="other_charges" class="form-label">Other Charges (₹)</label>
                                <input type="number" class="form-control" id="other_charges" name="other_charges" step="0.01" min="0" value="0" onchange="calculateTotal()">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>Bill Summary</h6>
                                        <div class="d-flex justify-content-between">
                                            <span>Subtotal:</span>
                                            <span id="billSubtotal">₹0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Tax (18%):</span>
                                            <span id="billTax">₹0.00</span>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between fw-bold">
                                            <span>Total:</span>
                                            <span id="billTotal">₹0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bill_notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="bill_notes" name="notes" rows="4"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Create Bill
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mark Paid Modal -->
    <div class="modal fade" id="markPaidModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check me-2"></i>Mark Bill as Paid
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="markPaidForm">
                    <input type="hidden" id="paid_bill_id" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="card">Card</option>
                                <option value="online">Online</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="reference_number" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number">
                        </div>
                        <div class="mb-3">
                            <label for="payment_notes" class="form-label">Payment Notes</label>
                            <textarea class="form-control" id="payment_notes" name="payment_notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i>Mark as Paid
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../assets/js/billing.js"></script>
</body>
</html>
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
        case 'add_expense':
            $result = addExpense($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'add_income':
            $result = addIncome($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'update_transaction':
            $result = updateTransaction($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'delete_transaction':
            $result = deleteTransaction($db, $_POST['id']);
            echo json_encode($result);
            exit();
    }
}

// Get cashbook entries with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$type = $_GET['type'] ?? '';
$date = $_GET['date'] ?? '';

$whereConditions = ['user_id = ?'];
$params = [$_SESSION['user_id']];

if (!empty($type)) {
    $whereConditions[] = 'transaction_type = ?';
    $params[] = $type;
}

if (!empty($date)) {
    $whereConditions[] = 'DATE(transaction_date) = ?';
    $params[] = $date;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

$transactions = $db->fetchAll("
    SELECT * FROM daily_cashbook 
    $whereClause
    ORDER BY transaction_date DESC, created_at DESC 
    LIMIT $limit OFFSET $offset
", $params);

$totalTransactions = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM daily_cashbook 
    $whereClause
", $params)['count'];

$totalPages = ceil($totalTransactions / $limit);

// Get summary statistics
$today = date('Y-m-d');
$month = date('Y-m');

$summary = [
    'today_income' => $db->fetchOne("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM daily_cashbook 
        WHERE user_id = ? AND transaction_type = 'income' AND DATE(transaction_date) = ?
    ", [$_SESSION['user_id'], $today])['total'],
    
    'today_expense' => $db->fetchOne("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM daily_cashbook 
        WHERE user_id = ? AND transaction_type = 'expense' AND DATE(transaction_date) = ?
    ", [$_SESSION['user_id'], $today])['total'],
    
    'monthly_income' => $db->fetchOne("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM daily_cashbook 
        WHERE user_id = ? AND transaction_type = 'income' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
    ", [$_SESSION['user_id'], $month])['total'],
    
    'monthly_expense' => $db->fetchOne("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM daily_cashbook 
        WHERE user_id = ? AND transaction_type = 'expense' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
    ", [$_SESSION['user_id'], $month])['total']
];

function addExpense($db, $data) {
    try {
        $db->insert('daily_cashbook', [
            'user_id' => $_SESSION['user_id'],
            'transaction_date' => $data['transaction_date'],
            'transaction_type' => 'expense',
            'category' => $data['category'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'reference_number' => $data['reference_number'] ?? '',
            'receipt_path' => $data['receipt_path'] ?? ''
        ]);
        
        logActivity($_SESSION['user_id'], 'expense_added', "Added expense: " . $data['description']);
        
        return ['success' => true, 'message' => 'Expense added successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error adding expense: ' . $e->getMessage()];
    }
}

function addIncome($db, $data) {
    try {
        $db->insert('daily_cashbook', [
            'user_id' => $_SESSION['user_id'],
            'transaction_date' => $data['transaction_date'],
            'transaction_type' => 'income',
            'category' => $data['category'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'reference_number' => $data['reference_number'] ?? ''
        ]);
        
        logActivity($_SESSION['user_id'], 'income_added', "Added income: " . $data['description']);
        
        return ['success' => true, 'message' => 'Income added successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error adding income: ' . $e->getMessage()];
    }
}

function updateTransaction($db, $data) {
    try {
        $db->update('daily_cashbook', [
            'transaction_date' => $data['transaction_date'],
            'category' => $data['category'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'reference_number' => $data['reference_number'] ?? ''
        ], 'id = ? AND user_id = ?', [$data['id'], $_SESSION['user_id']]);
        
        logActivity($_SESSION['user_id'], 'transaction_updated', "Updated transaction ID: " . $data['id']);
        
        return ['success' => true, 'message' => 'Transaction updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating transaction: ' . $e->getMessage()];
    }
}

function deleteTransaction($db, $transactionId) {
    try {
        $db->delete('daily_cashbook', 'id = ? AND user_id = ?', [$transactionId, $_SESSION['user_id']]);
        
        logActivity($_SESSION['user_id'], 'transaction_deleted', "Deleted transaction ID: " . $transactionId);
        
        return ['success' => true, 'message' => 'Transaction deleted successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error deleting transaction: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Cashbook - Water Purifier ERP</title>
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
                <div class="dashboard-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number">₹<?php echo number_format($summary['today_income']); ?></div>
                            <div class="stat-label">Today's Income</div>
                        </div>
                        <div class="stat-icon text-success">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card danger">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number">₹<?php echo number_format($summary['today_expense']); ?></div>
                            <div class="stat-label">Today's Expenses</div>
                        </div>
                        <div class="stat-icon text-danger">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number">₹<?php echo number_format($summary['monthly_income']); ?></div>
                            <div class="stat-label">Monthly Income</div>
                        </div>
                        <div class="stat-icon text-info">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number">₹<?php echo number_format($summary['monthly_expense']); ?></div>
                            <div class="stat-label">Monthly Expenses</div>
                        </div>
                        <div class="stat-icon text-warning">
                            <i class="fas fa-chart-line"></i>
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
                            <i class="fas fa-book me-2"></i>Daily Cashbook
                        </h4>
                        <div class="btn-group">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
                                <i class="fas fa-plus me-1"></i>Add Income
                            </button>
                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                <i class="fas fa-minus me-1"></i>Add Expense
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <select class="form-select" id="typeFilter">
                                    <option value="">All Transactions</option>
                                    <option value="income" <?php echo $type === 'income' ? 'selected' : ''; ?>>Income Only</option>
                                    <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>>Expenses Only</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="date" class="form-control" id="dateFilter" value="<?php echo htmlspecialchars($date); ?>">
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-primary" onclick="exportCashbook()">
                                    <i class="fas fa-download me-1"></i>Export
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-info" onclick="showSummary()">
                                    <i class="fas fa-chart-bar me-1"></i>Summary
                                </button>
                            </div>
                        </div>

                        <!-- Transactions Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo formatDate($transaction['transaction_date']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $transaction['transaction_type'] === 'income' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($transaction['transaction_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($transaction['category']); ?></td>
                                        <td>
                                            <div>
                                                <?php echo htmlspecialchars($transaction['description']); ?>
                                                <?php if ($transaction['reference_number']): ?>
                                                <br><small class="text-muted">Ref: <?php echo htmlspecialchars($transaction['reference_number']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="<?php echo $transaction['transaction_type'] === 'income' ? 'text-success' : 'text-danger'; ?> fw-bold">
                                                <?php echo $transaction['transaction_type'] === 'income' ? '+' : '-'; ?>₹<?php echo number_format($transaction['amount'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo ucfirst($transaction['payment_method']); ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewTransaction(<?php echo $transaction['id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" onclick="editTransaction(<?php echo $transaction['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteTransaction(<?php echo $transaction['id']; ?>)" title="Delete">
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
                        <nav aria-label="Transaction pagination">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo urlencode($type); ?>&date=<?php echo urlencode($date); ?>">
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

    <!-- View Transaction Modal -->
    <div class="modal fade" id="viewTransactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>Transaction Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="transactionDetails"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Transaction Modal -->
    <div class="modal fade" id="editTransactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Transaction
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editTransactionForm">
                    <input type="hidden" id="edit_transaction_id" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_transaction_date" class="form-label">Date *</label>
                            <input type="date" class="form-control" id="edit_transaction_date" name="transaction_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_category" class="form-label">Category *</label>
                            <input type="text" class="form-control" id="edit_category" name="category" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description *</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_amount" class="form-label">Amount (₹) *</label>
                            <input type="number" class="form-control" id="edit_amount" name="amount" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" id="edit_payment_method" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="card">Card</option>
                                <option value="online">Online</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_reference_number" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="edit_reference_number" name="reference_number">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-1"></i>Update Transaction
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Add Income Modal -->
    <div class="modal fade" id="addIncomeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add Income
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addIncomeForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="income_date" class="form-label">Date *</label>
                            <input type="date" class="form-control" id="income_date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="income_category" class="form-label">Category *</label>
                            <select class="form-select" id="income_category" name="category" required>
                                <option value="">Select category...</option>
                                <option value="Service Payment">Service Payment</option>
                                <option value="Installation Fee">Installation Fee</option>
                                <option value="Maintenance Fee">Maintenance Fee</option>
                                <option value="Commission">Commission</option>
                                <option value="Bonus">Bonus</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="income_description" class="form-label">Description *</label>
                            <textarea class="form-control" id="income_description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="income_amount" class="form-label">Amount (₹) *</label>
                            <input type="number" class="form-control" id="income_amount" name="amount" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="income_payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" id="income_payment_method" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="card">Card</option>
                                <option value="online">Online</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="income_reference" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="income_reference" name="reference_number">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>Add Income
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-minus me-2"></i>Add Expense
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addExpenseForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="expense_date" class="form-label">Date *</label>
                            <input type="date" class="form-control" id="expense_date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="expense_category" class="form-label">Category *</label>
                            <select class="form-select" id="expense_category" name="category" required>
                                <option value="">Select category...</option>
                                <option value="Fuel">Fuel</option>
                                <option value="Transportation">Transportation</option>
                                <option value="Food">Food</option>
                                <option value="Tools">Tools</option>
                                <option value="Parts">Parts</option>
                                <option value="Communication">Communication</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="expense_description" class="form-label">Description *</label>
                            <textarea class="form-control" id="expense_description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="expense_amount" class="form-label">Amount (₹) *</label>
                            <input type="number" class="form-control" id="expense_amount" name="amount" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="expense_payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" id="expense_payment_method" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="card">Card</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="expense_reference" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="expense_reference" name="reference_number">
                        </div>
                        <div class="mb-3">
                            <label for="expense_receipt" class="form-label">Receipt (Optional)</label>
                            <input type="file" class="form-control" id="expense_receipt" name="receipt" accept="image/*,.pdf">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-save me-1"></i>Add Expense
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../assets/js/cashbook.js"></script>
</body>
</html>
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
        case 'create_invoice':
            $result = createInvoice($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'update_invoice':
            $result = updateInvoice($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'mark_paid':
            $result = markInvoicePaid($db, $_POST);
            echo json_encode($result);
            exit();
            
        case 'send_invoice':
            $result = sendInvoice($db, $_POST);
            echo json_encode($result);
            exit();
    }
}

// Get invoices with pagination and filters
$status = $_GET['status'] ?? '';
$customer = $_GET['customer'] ?? '';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$whereConditions = [];
$params = [];

if (!empty($status)) {
    $whereConditions[] = "i.status = ?";
    $params[] = $status;
}

if (!empty($customer)) {
    $whereConditions[] = "i.customer_id = ?";
    $params[] = $customer;
}

if (!empty($search)) {
    $whereConditions[] = "(i.invoice_number LIKE ? OR c.company_name LIKE ? OR c.contact_person LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$invoices = $db->fetchAll("
    SELECT i.*, c.company_name, c.contact_person, c.email, c.phone,
           (SELECT COUNT(*) FROM payments p WHERE p.invoice_id = i.id) as payment_count
    FROM invoices i 
    JOIN customers c ON i.customer_id = c.id 
    $whereClause
    ORDER BY i.created_at DESC 
    LIMIT $limit OFFSET $offset
", $params);

$totalInvoices = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM invoices i 
    JOIN customers c ON i.customer_id = c.id 
    $whereClause
", $params)['count'];

$totalPages = ceil($totalInvoices / $limit);

// Get customers for filter
$customers = $db->fetchAll("SELECT id, company_name, contact_person FROM customers ORDER BY company_name");

function createInvoice($db, $data) {
    try {
        $db->beginTransaction();
        
        // Generate invoice number
        $invoiceNumber = generateInvoiceNumber();
        
        // Create invoice
        $invoiceId = $db->insert('invoices', [
            'invoice_number' => $invoiceNumber,
            'customer_id' => $data['customer_id'],
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'],
            'subtotal' => $data['subtotal'],
            'tax_amount' => $data['tax_amount'],
            'discount_amount' => $data['discount_amount'] ?? 0,
            'total_amount' => $data['total_amount'],
            'balance_amount' => $data['total_amount'],
            'payment_terms' => $data['payment_terms'] ?? 30,
            'notes' => $data['notes'] ?? '',
            'status' => 'draft',
            'created_by' => $_SESSION['user_id']
        ]);
        
        // Add invoice items
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $db->insert('invoice_items', [
                    'invoice_id' => $invoiceId,
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price']
                ]);
            }
        }
        
        logActivity($_SESSION['user_id'], 'invoice_created', "Created invoice: " . $invoiceNumber);
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Invoice created successfully',
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'Error creating invoice: ' . $e->getMessage()];
    }
}

function updateInvoice($db, $data) {
    try {
        $db->update('invoices', [
            'status' => $data['status'],
            'notes' => $data['notes'] ?? ''
        ], 'id = ?', [$data['id']]);
        
        logActivity($_SESSION['user_id'], 'invoice_updated', "Updated invoice ID: " . $data['id']);
        
        return ['success' => true, 'message' => 'Invoice updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating invoice: ' . $e->getMessage()];
    }
}

function markInvoicePaid($db, $data) {
    try {
        $db->beginTransaction();
        
        // Get invoice details
        $invoice = $db->fetchOne('SELECT * FROM invoices WHERE id = ?', [$data['id']]);
        
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found'];
        }
        
        // Update invoice status
        $db->update('invoices', [
            'status' => 'paid',
            'paid_amount' => $invoice['total_amount'],
            'balance_amount' => 0
        ], 'id = ?', [$data['id']]);
        
        // Add payment record
        $db->insert('payments', [
            'invoice_id' => $data['id'],
            'customer_id' => $invoice['customer_id'],
            'payment_date' => date('Y-m-d'),
            'amount' => $invoice['total_amount'],
            'payment_method' => $data['payment_method'] ?? 'cash',
            'reference_number' => $data['reference_number'] ?? '',
            'notes' => $data['notes'] ?? 'Payment received',
            'created_by' => $_SESSION['user_id']
        ]);
        
        // Update customer ledger
        $db->insert('customer_ledger', [
            'customer_id' => $invoice['customer_id'],
            'transaction_date' => date('Y-m-d'),
            'transaction_type' => 'payment',
            'reference_id' => $data['id'],
            'description' => 'Payment received for invoice ' . $invoice['invoice_number'],
            'credit_amount' => $invoice['total_amount'],
            'balance' => 0 // This would need to be calculated from previous balance
        ]);
        
        logActivity($_SESSION['user_id'], 'invoice_paid', "Marked invoice as paid: " . $invoice['invoice_number']);
        
        $db->commit();
        
        return ['success' => true, 'message' => 'Invoice marked as paid successfully'];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'Error marking invoice as paid: ' . $e->getMessage()];
    }
}

function sendInvoice($db, $data) {
    try {
        $invoice = $db->fetchOne("
            SELECT i.*, c.company_name, c.contact_person, c.email 
            FROM invoices i 
            JOIN customers c ON i.customer_id = c.id 
            WHERE i.id = ?
        ", [$data['id']]);
        
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found'];
        }
        
        // Update invoice status
        $db->update('invoices', ['status' => 'sent'], 'id = ?', [$data['id']]);
        
        // Send email
        $emailSubject = "Invoice " . $invoice['invoice_number'] . " - Payment Due";
        $emailMessage = "
        <html>
        <head><title>Invoice</title></head>
        <body>
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #0d6efd;'>Invoice " . $invoice['invoice_number'] . "</h2>
                <p>Dear " . $invoice['contact_person'] . ",</p>
                <p>Please find attached your invoice for the services provided.</p>
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                    <p><strong>Invoice Number:</strong> " . $invoice['invoice_number'] . "</p>
                    <p><strong>Invoice Date:</strong> " . date('d M Y', strtotime($invoice['invoice_date'])) . "</p>
                    <p><strong>Due Date:</strong> " . date('d M Y', strtotime($invoice['due_date'])) . "</p>
                    <p><strong>Total Amount:</strong> ₹" . number_format($invoice['total_amount'], 2) . "</p>
                </div>
                <p>Please make payment by the due date to avoid any late fees.</p>
                <p>Thank you for your business!</p>
            </div>
        </body>
        </html>";
        
        $emailSent = sendEmail($invoice['email'], $emailSubject, $emailMessage);
        
        logActivity($_SESSION['user_id'], 'invoice_sent', "Sent invoice: " . $invoice['invoice_number']);
        
        return [
            'success' => true,
            'message' => 'Invoice sent successfully',
            'email_sent' => $emailSent
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error sending invoice: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Management - Water Purifier ERP</title>
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
                            <i class="fas fa-file-invoice me-2"></i>Invoice Management
                        </h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                            <i class="fas fa-plus me-1"></i>Create Invoice
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="sent" <?php echo $status === 'sent' ? 'selected' : ''; ?>>Sent</option>
                                    <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="overdue" <?php echo $status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="customerFilter">
                                    <option value="">All Customers</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" <?php echo $customer == $customer['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['company_name'] ?: $customer['contact_person']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="searchInput" placeholder="Search invoices..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-primary w-100" onclick="exportInvoices()">
                                    <i class="fas fa-download me-1"></i>Export
                                </button>
                            </div>
                        </div>

                        <!-- Invoices Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $invoice['invoice_number']; ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($invoice['company_name'] ?: $invoice['contact_person']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($invoice['email']); ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo formatDate($invoice['invoice_date']); ?></td>
                                        <td>
                                            <?php 
                                            $dueDate = strtotime($invoice['due_date']);
                                            $today = time();
                                            $isOverdue = $dueDate < $today && $invoice['status'] !== 'paid';
                                            ?>
                                            <span class="<?php echo $isOverdue ? 'text-danger fw-bold' : ''; ?>">
                                                <?php echo formatDate($invoice['due_date']); ?>
                                            </span>
                                            <?php if ($isOverdue): ?>
                                            <br><small class="text-danger">Overdue</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <strong>₹<?php echo number_format($invoice['total_amount'], 2); ?></strong>
                                                <?php if ($invoice['balance_amount'] < $invoice['total_amount']): ?>
                                                <br><small class="text-success">Paid: ₹<?php echo number_format($invoice['total_amount'] - $invoice['balance_amount'], 2); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadge($invoice['status']); ?>">
                                                <?php echo ucfirst($invoice['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewInvoice(<?php echo $invoice['id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" onclick="printInvoice(<?php echo $invoice['id']; ?>)" title="Print">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                                <?php if ($invoice['status'] === 'draft'): ?>
                                                <button class="btn btn-sm btn-outline-warning" onclick="sendInvoice(<?php echo $invoice['id']; ?>)" title="Send">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if ($invoice['status'] !== 'paid'): ?>
                                                <button class="btn btn-sm btn-outline-success" onclick="markPaid(<?php echo $invoice['id']; ?>)" title="Mark Paid">
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
                        <nav aria-label="Invoice pagination">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&customer=<?php echo urlencode($customer); ?>&search=<?php echo urlencode($search); ?>">
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

    <!-- Create Invoice Modal -->
    <div class="modal fade" id="createInvoiceModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-invoice me-2"></i>Create New Invoice
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createInvoiceForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="invoice_customer" class="form-label">Customer *</label>
                                <select class="form-select" id="invoice_customer" name="customer_id" required>
                                    <option value="">Select customer...</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>">
                                        <?php echo htmlspecialchars($customer['company_name'] ?: $customer['contact_person']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="invoice_date" class="form-label">Invoice Date *</label>
                                <input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="due_date" class="form-label">Due Date *</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                            </div>
                        </div>
                        
                        <!-- Invoice Items -->
                        <div class="mb-3">
                            <label class="form-label">Invoice Items</label>
                            <div class="table-responsive">
                                <table class="table table-sm" id="invoiceItemsTable">
                                    <thead>
                                        <tr>
                                            <th>Product/Service</th>
                                            <th>Description</th>
                                            <th>Qty</th>
                                            <th>Rate</th>
                                            <th>Amount</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="invoiceItemsBody">
                                        <tr>
                                            <td>
                                                <select class="form-select form-select-sm" name="items[0][product_id]">
                                                    <option value="">Select product...</option>
                                                    <!-- Products will be loaded via AJAX -->
                                                </select>
                                            </td>
                                            <td><input type="text" class="form-control form-control-sm" name="items[0][description]"></td>
                                            <td><input type="number" class="form-control form-control-sm" name="items[0][quantity]" min="1" value="1"></td>
                                            <td><input type="number" class="form-control form-control-sm" name="items[0][unit_price]" step="0.01" min="0"></td>
                                            <td><input type="number" class="form-control form-control-sm" name="items[0][total_price]" step="0.01" readonly></td>
                                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(this)"><i class="fas fa-trash"></i></button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()">
                                <i class="fas fa-plus me-1"></i>Add Item
                            </button>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="invoice_notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="invoice_notes" name="notes" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>Invoice Summary</h6>
                                        <div class="d-flex justify-content-between">
                                            <span>Subtotal:</span>
                                            <span id="invoiceSubtotal">₹0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Tax (18%):</span>
                                            <span id="invoiceTax">₹0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Discount:</span>
                                            <span id="invoiceDiscount">₹0.00</span>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between fw-bold">
                                            <span>Total:</span>
                                            <span id="invoiceTotal">₹0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Create Invoice
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
                        <i class="fas fa-check me-2"></i>Mark Invoice as Paid
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="markPaidForm">
                    <input type="hidden" id="paid_invoice_id" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method</label>
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
                            <label for="payment_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="payment_notes" name="notes" rows="3"></textarea>
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
    <script src="../assets/js/invoices.js"></script>
</body>
</html>
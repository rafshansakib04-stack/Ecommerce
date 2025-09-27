<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$invoiceId = $_GET['id'] ?? null;
if (!$invoiceId) {
    die('Invoice ID is required');
}

$db = Database::getInstance();

$invoice = $db->fetchOne("
    SELECT i.*, c.company_name, c.contact_person, c.email, c.phone, c.address
    FROM invoices i
    JOIN customers c ON i.customer_id = c.id
    WHERE i.id = ?
", [$invoiceId]);

if (!$invoice) {
    die('Invoice not found');
}

$items = $db->fetchAll("
    SELECT ii.*, p.name AS product_name
    FROM invoice_items ii
    LEFT JOIN products p ON ii.product_id = p.id
    WHERE ii.invoice_id = ?
", [$invoiceId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .invoice-container { max-width: 900px; margin: 20px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .header { border-bottom: 2px solid #0d6efd; margin-bottom: 20px; padding-bottom: 10px; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .badge-draft { background: #e9ecef; color: #6c757d; }
        .badge-sent { background: #cff4fc; color: #055160; }
        .badge-paid { background: #d1e7dd; color: #0f5132; }
        .badge-overdue { background: #f8d7da; color: #842029; }
    </style>
    <script>
        function printPage() { window.print(); }
    </script>
    </head>
<body>
    <div class="invoice-container">
        <div class="d-flex justify-content-between align-items-start header">
            <div>
                <h3 class="mb-0">Water Purifier ERP</h3>
                <small class="text-muted">Professional Water Purifier Services</small>
            </div>
            <div class="text-end">
                <div class="status-badge badge-<?php echo htmlspecialchars($invoice['status']); ?>"><?php echo ucfirst($invoice['status']); ?></div>
                <button class="btn btn-outline-primary btn-sm ms-2" onclick="printPage()">Print</button>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <h6>Bill To</h6>
                <div><strong><?php echo htmlspecialchars($invoice['company_name'] ?: $invoice['contact_person']); ?></strong></div>
                <?php if ($invoice['address']): ?>
                <div class="text-muted"><?php echo nl2br(htmlspecialchars($invoice['address'])); ?></div>
                <?php endif; ?>
                <div class="text-muted">Email: <?php echo htmlspecialchars($invoice['email']); ?></div>
                <div class="text-muted">Phone: <?php echo htmlspecialchars($invoice['phone']); ?></div>
            </div>
            <div class="col-md-6 text-md-end">
                <h6>Invoice Details</h6>
                <div><strong>Invoice #:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                <div><strong>Invoice Date:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($invoice['invoice_date']))); ?></div>
                <div><strong>Due Date:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($invoice['due_date']))); ?></div>
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:45%">Item</th>
                        <th>Description</th>
                        <th class="text-end" style="width:10%">Qty</th>
                        <th class="text-end" style="width:15%">Rate (₹)</th>
                        <th class="text-end" style="width:15%">Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['product_name'] ?: 'Service'); ?></td>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td class="text-end"><?php echo (float)$item['quantity']; ?></td>
                        <td class="text-end"><?php echo number_format((float)$item['unit_price'], 2); ?></td>
                        <td class="text-end"><?php echo number_format((float)$item['total_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row">
            <div class="col-md-6">
                <?php if (!empty($invoice['notes'])): ?>
                <h6>Notes</h6>
                <p class="text-muted"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <table class="table">
                    <tr>
                        <td class="text-end"><strong>Subtotal:</strong></td>
                        <td class="text-end">₹<?php echo number_format((float)$invoice['subtotal'], 2); ?></td>
                    </tr>
                    <tr>
                        <td class="text-end"><strong>Tax:</strong></td>
                        <td class="text-end">₹<?php echo number_format((float)$invoice['tax_amount'], 2); ?></td>
                    </tr>
                    <?php if ((float)$invoice['discount_amount'] > 0): ?>
                    <tr>
                        <td class="text-end"><strong>Discount:</strong></td>
                        <td class="text-end">₹<?php echo number_format((float)$invoice['discount_amount'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="table-light">
                        <td class="text-end"><strong>Total:</strong></td>
                        <td class="text-end"><strong>₹<?php echo number_format((float)$invoice['total_amount'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-end"><strong>Paid:</strong></td>
                        <td class="text-end text-success">₹<?php echo number_format((float)($invoice['paid_amount'] ?? 0), 2); ?></td>
                    </tr>
                    <tr>
                        <td class="text-end"><strong>Balance:</strong></td>
                        <td class="text-end <?php echo ((float)$invoice['balance_amount'] > 0 ? 'text-danger' : 'text-success'); ?>">₹<?php echo number_format((float)$invoice['balance_amount'], 2); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="mt-4 text-center text-muted">
            Thank you for your business!
        </div>
    </div>
</body>
</html>


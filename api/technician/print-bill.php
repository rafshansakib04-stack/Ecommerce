<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is technician
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'technician') {
    header('Location: ../../index.php');
    exit();
}

$billId = $_GET['id'] ?? null;

if (!$billId) {
    die('Bill ID is required');
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
    die('Technician not found');
}

// Get bill details
$bill = $db->fetchOne("
    SELECT * FROM technician_bills 
    WHERE id = ? AND technician_id = ?
", [$billId, $technician['id']]);

if (!$bill) {
    die('Bill not found');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill - <?php echo $bill['bill_number']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-name {
            font-size: 28px;
            font-weight: bold;
            color: #0d6efd;
            margin-bottom: 10px;
        }
        .company-tagline {
            color: #6c757d;
            font-size: 14px;
        }
        .bill-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .bill-details {
            flex: 1;
        }
        .customer-details {
            flex: 1;
        }
        .section-title {
            font-weight: bold;
            color: #0d6efd;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .details-table th,
        .details-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .details-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #495057;
        }
        .charges-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .charges-table th,
        .charges-table td {
            padding: 10px;
            text-align: right;
            border-bottom: 1px solid #dee2e6;
        }
        .charges-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #495057;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
            border-top: 2px solid #0d6efd;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-paid {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .status-overdue {
            background-color: #f8d7da;
            color: #721c24;
        }
        @media print {
            body {
                background-color: white;
                padding: 0;
            }
            .invoice-container {
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">Water Purifier ERP</div>
            <div class="company-tagline">Professional Water Purifier Services</div>
        </div>

        <!-- Bill Information -->
        <div class="bill-info">
            <div class="bill-details">
                <div class="section-title">Bill Information</div>
                <table class="details-table">
                    <tr>
                        <td><strong>Bill Number:</strong></td>
                        <td><?php echo $bill['bill_number']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Bill Date:</strong></td>
                        <td><?php echo date('d M Y', strtotime($bill['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Service Date:</strong></td>
                        <td><?php echo date('d M Y', strtotime($bill['service_date'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                            <span class="status-badge status-<?php echo $bill['status']; ?>">
                                <?php echo ucfirst($bill['status']); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="customer-details">
                <div class="section-title">Customer Details</div>
                <table class="details-table">
                    <tr>
                        <td><strong>Name:</strong></td>
                        <td><?php echo htmlspecialchars($bill['customer_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Phone:</strong></td>
                        <td><?php echo htmlspecialchars($bill['customer_phone']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Address:</strong></td>
                        <td><?php echo htmlspecialchars($bill['customer_address']); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Service Details -->
        <div class="section-title">Service Details</div>
        <table class="details-table">
            <tr>
                <td><strong>Service Type:</strong></td>
                <td><?php echo htmlspecialchars($bill['service_type']); ?></td>
            </tr>
            <tr>
                <td><strong>Description:</strong></td>
                <td><?php echo htmlspecialchars($bill['service_description']); ?></td>
            </tr>
        </table>

        <!-- Charges Breakdown -->
        <div class="section-title">Charges Breakdown</div>
        <table class="charges-table">
            <tr>
                <th>Description</th>
                <th>Amount (₹)</th>
            </tr>
            <tr>
                <td>Labor Charges</td>
                <td><?php echo number_format($bill['labor_charges'], 2); ?></td>
            </tr>
            <tr>
                <td>Parts Cost</td>
                <td><?php echo number_format($bill['parts_cost'], 2); ?></td>
            </tr>
            <tr>
                <td>Travel Charges</td>
                <td><?php echo number_format($bill['travel_charges'], 2); ?></td>
            </tr>
            <tr>
                <td>Other Charges</td>
                <td><?php echo number_format($bill['other_charges'], 2); ?></td>
            </tr>
            <tr>
                <td><strong>Subtotal</strong></td>
                <td><strong>₹<?php echo number_format($bill['subtotal'], 2); ?></strong></td>
            </tr>
            <tr>
                <td>Tax (18%)</td>
                <td>₹<?php echo number_format($bill['tax_amount'], 2); ?></td>
            </tr>
            <tr class="total-row">
                <td><strong>Total Amount</strong></td>
                <td><strong>₹<?php echo number_format($bill['total_amount'], 2); ?></strong></td>
            </tr>
            <?php if ($bill['paid_amount'] > 0): ?>
            <tr>
                <td>Paid Amount</td>
                <td>₹<?php echo number_format($bill['paid_amount'], 2); ?></td>
            </tr>
            <tr>
                <td>Balance Amount</td>
                <td>₹<?php echo number_format($bill['balance_amount'], 2); ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <!-- Payment Information -->
        <?php if ($bill['status'] === 'paid'): ?>
        <div class="section-title">Payment Information</div>
        <table class="details-table">
            <tr>
                <td><strong>Payment Date:</strong></td>
                <td><?php echo date('d M Y', strtotime($bill['payment_date'])); ?></td>
            </tr>
            <tr>
                <td><strong>Payment Method:</strong></td>
                <td><?php echo ucfirst($bill['payment_method']); ?></td>
            </tr>
            <?php if ($bill['payment_notes']): ?>
            <tr>
                <td><strong>Payment Notes:</strong></td>
                <td><?php echo htmlspecialchars($bill['payment_notes']); ?></td>
            </tr>
            <?php endif; ?>
        </table>
        <?php endif; ?>

        <!-- Notes -->
        <?php if ($bill['notes']): ?>
        <div class="section-title">Notes</div>
        <p><?php echo htmlspecialchars($bill['notes']); ?></p>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Technician:</strong> <?php echo htmlspecialchars($technician['full_name']); ?></p>
            <p><strong>Contact:</strong> <?php echo htmlspecialchars($technician['phone']); ?> | <?php echo htmlspecialchars($technician['email']); ?></p>
            <p>Thank you for choosing our services!</p>
            <p>Generated on: <?php echo date('d M Y H:i:s'); ?></p>
        </div>
    </div>

    <script>
        // Auto-print when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
    </script>
</body>
</html>
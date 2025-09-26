<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header('Location: ../index.php');
    exit();
}

$invoiceId = $_GET['id'] ?? null;
if (!$invoiceId) {
    header('Location: dashboard.php');
    exit();
}

$db = Database::getInstance();

// Verify invoice belongs to this customer
$customer = $db->fetchOne('SELECT id FROM customers WHERE user_id = ?', [$_SESSION['user_id']]);
$invoice = $db->fetchOne('SELECT * FROM invoices WHERE id = ? AND customer_id = ?', [$invoiceId, $customer['id']]);

if (!$invoice) {
    header('Location: dashboard.php');
    exit();
}

// Get Razorpay key
$keyId = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'razorpay_key_id'")['setting_value'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Invoice - Water Purifier ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Pay Invoice</h5>
                        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">Back</a>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div><strong>Invoice #:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                            <div><strong>Total Amount:</strong> ₹<?php echo number_format($invoice['total_amount'], 2); ?></div>
                            <div><strong>Balance:</strong> ₹<?php echo number_format($invoice['balance_amount'], 2); ?></div>
                        </div>
                        <?php if ((float)$invoice['balance_amount'] <= 0): ?>
                            <div class="alert alert-success">This invoice is already paid.</div>
                        <?php else: ?>
                            <button class="btn btn-primary w-100" id="payBtn">Pay Now</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $('#payBtn').on('click', function() {
            $.ajax({
                url: '../api/payments/create_order.php',
                method: 'POST',
                dataType: 'json',
                data: { invoice_id: <?php echo (int)$invoiceId; ?> },
                success: function(resp) {
                    if (!resp.success) {
                        alert(resp.message || 'Failed to initiate payment');
                        return;
                    }

                    const order = resp.data.order;
                    const keyId = resp.data.key_id;
                    const options = {
                        key: keyId,
                        amount: order.amount,
                        currency: order.currency,
                        name: 'Water Purifier ERP',
                        description: 'Invoice ' + <?php echo json_encode($invoice['invoice_number']); ?>,
                        order_id: order.id,
                        handler: function (response){
                            // Confirmation handled by webhook; show thank you
                            alert('Payment successful! Payment ID: ' + response.razorpay_payment_id);
                            setTimeout(function(){ window.location.href = 'dashboard.php'; }, 1000);
                        },
                        prefill: {},
                        theme: { color: '#0d6efd' }
                    };
                    const rzp1 = new Razorpay(options);
                    rzp1.open();
                },
                error: function() {
                    alert('Error creating payment order.');
                }
            });
        });
    </script>
</body>
</html>

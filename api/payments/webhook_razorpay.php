<?php
// Razorpay webhook handler
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

$db = Database::getInstance();
$webhookSecret = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'razorpay_webhook_secret'")['setting_value'] ?? '';

if (empty($webhookSecret)) {
    http_response_code(500);
    echo 'Webhook not configured';
    exit();
}

// Validate signature
$expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(403);
    echo 'Invalid signature';
    exit();
}

$event = json_decode($payload, true);
if (!$event || !isset($event['event'])) {
    http_response_code(400);
    echo 'Invalid payload';
    exit();
}

try {
    if ($event['event'] === 'payment.captured') {
        $payment = $event['payload']['payment']['entity'];
        $orderId = $payment['order_id'];
        $amount = ((int)$payment['amount']) / 100.0;

        // Find payment order mapping
        $orderRow = $db->fetchOne('SELECT * FROM payment_orders WHERE order_id = ?', [$orderId]);
        if ($orderRow) {
            $invoiceId = $orderRow['invoice_id'];

            // Update invoice as paid (partial/full based on amount)
            $invoice = $db->fetchOne('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
            if ($invoice) {
                $newPaid = ($invoice['paid_amount'] ?? 0) + $amount;
                $newBalance = max(0, $invoice['total_amount'] - $newPaid);
                $status = $newBalance <= 0.0001 ? 'paid' : $invoice['status'];

                $db->update('invoices', [
                    'paid_amount' => $newPaid,
                    'balance_amount' => $newBalance,
                    'status' => $status
                ], 'id = ?', [$invoiceId]);

                // Record payment
                $db->insert('payments', [
                    'invoice_id' => $invoiceId,
                    'customer_id' => $invoice['customer_id'],
                    'payment_date' => date('Y-m-d'),
                    'amount' => $amount,
                    'payment_method' => 'online',
                    'reference_number' => $payment['id'],
                    'notes' => 'Razorpay payment',
                    'created_by' => null
                ]);

                // Update order mapping
                $db->update('payment_orders', [
                    'status' => 'captured',
                    'payment_id' => $payment['id']
                ], 'id = ?', [$orderRow['id']]);
            }
        }
    }

    http_response_code(200);
    echo 'ok';
} catch (Exception $e) {
    error_log('Webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo 'error';
}
?>


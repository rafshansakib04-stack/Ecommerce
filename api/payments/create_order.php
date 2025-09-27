<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $invoiceId = $_POST['invoice_id'] ?? null;
    if (!$invoiceId) {
        echo json_encode(['success' => false, 'message' => 'Invoice ID is required']);
        exit();
    }

    $db = Database::getInstance();

    // Load invoice
    $invoice = $db->fetchOne('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit();
    }
    if ((float)$invoice['balance_amount'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invoice already paid']);
        exit();
    }

    // Load Razorpay settings
    $keyId = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'razorpay_key_id'")['setting_value'] ?? '';
    $keySecret = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'razorpay_key_secret'")['setting_value'] ?? '';

    if (empty($keyId) || empty($keySecret)) {
        echo json_encode(['success' => false, 'message' => 'Payment gateway not configured']);
        exit();
    }

    // Create Razorpay order using REST API
    $amountPaise = (int)round($invoice['balance_amount'] * 100);
    $orderPayload = [
        'amount' => $amountPaise,
        'currency' => 'INR',
        'receipt' => 'INV_' . $invoice['invoice_number'],
        'payment_capture' => 1
    ];

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderPayload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false || $httpCode >= 400) {
        $err = curl_error($ch);
        curl_close($ch);
        error_log('Razorpay order error: ' . $err . ' response: ' . $response);
        echo json_encode(['success' => false, 'message' => 'Failed to create payment order']);
        exit();
    }
    curl_close($ch);
    $order = json_decode($response, true);

    // Persist mapping
    $db->insert('payment_orders', [
        'invoice_id' => $invoiceId,
        'gateway' => 'razorpay',
        'order_id' => $order['id'],
        'amount' => $invoice['balance_amount'],
        'currency' => 'INR',
        'status' => 'created'
    ]);

    echo json_encode([
        'success' => true,
        'data' => [
            'order' => $order,
            'key_id' => $keyId
        ]
    ]);
} catch (Exception $e) {
    error_log('Create order error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>


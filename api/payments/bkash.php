<?php
/**
 * bKash Payment Gateway Integration
 * Handles bKash payments for Bangladesh customers
 */

header('Content-Type: application/json');
require_once '../../includes/functions.php';

class BkashPayment {
    private $appKey;
    private $appSecret;
    private $username;
    private $password;
    private $baseUrl;
    private $accessToken;
    
    public function __construct() {
        $this->appKey = BKASH_APP_KEY;
        $this->appSecret = BKASH_APP_SECRET;
        $this->username = BKASH_USERNAME;
        $this->password = BKASH_PASSWORD;
        $this->baseUrl = BKASH_BASE_URL;
    }
    
    public function getAccessToken() {
        if ($this->accessToken) {
            return $this->accessToken;
        }
        
        $url = $this->baseUrl . '/checkout/token/grant';
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'username: ' . $this->username,
            'password: ' . $this->password
        ];
        
        $data = [
            'app_key' => $this->appKey,
            'app_secret' => $this->appSecret
        ];
        
        $response = $this->makeRequest($url, $data, $headers);
        
        if ($response && isset($response['id_token'])) {
            $this->accessToken = $response['id_token'];
            return $this->accessToken;
        }
        
        throw new Exception('Failed to get bKash access token');
    }
    
    public function createPayment($amount, $orderId, $intent = 'sale') {
        $token = $this->getAccessToken();
        
        $url = $this->baseUrl . '/checkout/payment/create';
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $token,
            'X-APP-Key: ' . $this->appKey
        ];
        
        $data = [
            'amount' => (string)$amount,
            'currency' => 'BDT',
            'intent' => $intent,
            'merchantInvoiceNumber' => 'PF' . $orderId . '_' . time(),
            'callbackURL' => APP_URL . '/api/payments/bkash-callback.php'
        ];
        
        $response = $this->makeRequest($url, $data, $headers);
        
        if ($response && $response['statusCode'] === '0000') {
            return [
                'success' => true,
                'payment_id' => $response['paymentID'],
                'bkash_url' => $response['bkashURL'],
                'callback_url' => $response['callbackURL'],
                'amount' => $response['amount'],
                'intent' => $response['intent'],
                'currency' => $response['currency']
            ];
        }
        
        throw new Exception($response['statusMessage'] ?? 'Payment creation failed');
    }
    
    public function executePayment($paymentId) {
        $token = $this->getAccessToken();
        
        $url = $this->baseUrl . '/checkout/payment/execute';
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $token,
            'X-APP-Key: ' . $this->appKey
        ];
        
        $data = [
            'paymentID' => $paymentId
        ];
        
        $response = $this->makeRequest($url, $data, $headers);
        
        if ($response && $response['statusCode'] === '0000') {
            return [
                'success' => true,
                'payment_id' => $response['paymentID'],
                'transaction_id' => $response['trxID'],
                'amount' => $response['amount'],
                'currency' => $response['currency'],
                'customer_msisdn' => $response['customerMsisdn'],
                'payment_execute_time' => $response['paymentExecuteTime']
            ];
        }
        
        throw new Exception($response['statusMessage'] ?? 'Payment execution failed');
    }
    
    public function queryPayment($paymentId) {
        $token = $this->getAccessToken();
        
        $url = $this->baseUrl . '/checkout/payment/query';
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $token,
            'X-APP-Key: ' . $this->appKey
        ];
        
        $data = [
            'paymentID' => $paymentId
        ];
        
        $response = $this->makeRequest($url, $data, $headers);
        
        return $response;
    }
    
    public function refundPayment($paymentId, $amount, $reason = '') {
        $token = $this->getAccessToken();
        
        $url = $this->baseUrl . '/checkout/payment/refund';
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $token,
            'X-APP-Key: ' . $this->appKey
        ];
        
        $data = [
            'paymentID' => $paymentId,
            'amount' => (string)$amount,
            'trxID' => '',
            'sku' => 'refund',
            'reason' => $reason
        ];
        
        $response = $this->makeRequest($url, $data, $headers);
        
        if ($response && $response['statusCode'] === '0000') {
            return [
                'success' => true,
                'refund_trx_id' => $response['refundTrxID'],
                'original_trx_id' => $response['originalTrxID'],
                'amount' => $response['amount'],
                'currency' => $response['currency'],
                'charge' => $response['charge']
            ];
        }
        
        throw new Exception($response['statusMessage'] ?? 'Refund failed');
    }
    
    private function makeRequest($url, $data, $headers) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: $httpCode");
        }
        
        return json_decode($response, true);
    }
}

// Handle different actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        handleCreatePayment();
        break;
        
    case 'execute':
        handleExecutePayment();
        break;
        
    case 'query':
        handleQueryPayment();
        break;
        
    case 'refund':
        handleRefundPayment();
        break;
        
    default:
        errorResponse('Invalid action');
}

function handleCreatePayment() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $orderId = (int)($input['order_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    
    if ($orderId <= 0 || $amount <= 0) {
        errorResponse('Invalid order ID or amount');
    }
    
    // Verify order exists and belongs to current user
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        errorResponse('User not logged in');
    }
    
    $order = getRecord('orders', ['id' => $orderId, 'user_id' => $userId]);
    
    if (!$order) {
        errorResponse('Order not found');
    }
    
    if ($order['payment_status'] === 'paid') {
        errorResponse('Order is already paid');
    }
    
    if (abs($order['total_amount'] - $amount) > 0.01) {
        errorResponse('Amount mismatch');
    }
    
    try {
        $bkash = new BkashPayment();
        $payment = $bkash->createPayment($amount, $orderId);
        
        if ($payment['success']) {
            // Save payment record
            $paymentData = [
                'order_id' => $orderId,
                'payment_method' => 'bkash',
                'gateway' => 'bkash',
                'transaction_id' => $payment['payment_id'],
                'amount' => $amount,
                'currency' => 'BDT',
                'status' => 'pending',
                'gateway_response' => json_encode($payment)
            ];
            
            $paymentId = insertRecord('payments', $paymentData);
            
            // Log activity
            logActivity($userId, 'payment_initiated', 'payment', $paymentId, 'bKash payment initiated', [
                'order_id' => $orderId,
                'amount' => $amount,
                'payment_id' => $payment['payment_id']
            ]);
            
            successResponse('Payment created successfully', [
                'payment_id' => $payment['payment_id'],
                'bkash_url' => $payment['bkash_url'],
                'amount' => $amount,
                'currency' => 'BDT'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("bKash payment creation error: " . $e->getMessage());
        errorResponse('Payment creation failed: ' . $e->getMessage());
    }
}

function handleExecutePayment() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $paymentId = $input['payment_id'] ?? '';
    
    if (empty($paymentId)) {
        errorResponse('Payment ID is required');
    }
    
    try {
        // Get payment record
        $payment = getRecord('payments', ['transaction_id' => $paymentId]);
        
        if (!$payment) {
            errorResponse('Payment record not found');
        }
        
        $bkash = new BkashPayment();
        $result = $bkash->executePayment($paymentId);
        
        if ($result['success']) {
            $db = getDB();
            $db->beginTransaction();
            
            try {
                // Update payment record
                updateRecord('payments', [
                    'status' => 'completed',
                    'gateway_transaction_id' => $result['transaction_id'],
                    'processed_at' => date('Y-m-d H:i:s'),
                    'gateway_response' => json_encode($result)
                ], ['id' => $payment['id']]);
                
                // Update order payment status
                updateRecord('orders', [
                    'payment_status' => 'paid',
                    'status' => 'confirmed'
                ], ['id' => $payment['order_id']]);
                
                // Log activity
                logActivity($payment['user_id'] ?? null, 'payment_completed', 'payment', $payment['id'], 'bKash payment completed', [
                    'transaction_id' => $result['transaction_id'],
                    'amount' => $result['amount']
                ]);
                
                // Send confirmation notifications
                $order = getRecord('orders', ['id' => $payment['order_id']]);
                if ($order) {
                    // Email notification
                    $user = getRecord('users', ['id' => $order['user_id']]);
                    if ($user) {
                        createNotification(
                            $user['id'],
                            'payment_success',
                            'Payment Successful',
                            "Your payment of {$result['amount']} BDT has been received. Order #{$order['order_number']} is now confirmed.",
                            ['order_id' => $order['id'], 'transaction_id' => $result['transaction_id']],
                            ['email', 'sms', 'push']
                        );
                    }
                }
                
                $db->commit();
                
                successResponse('Payment executed successfully', [
                    'transaction_id' => $result['transaction_id'],
                    'amount' => $result['amount'],
                    'order_id' => $payment['order_id']
                ]);
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        }
        
    } catch (Exception $e) {
        error_log("bKash payment execution error: " . $e->getMessage());
        errorResponse('Payment execution failed: ' . $e->getMessage());
    }
}

function handleQueryPayment() {
    $paymentId = $_GET['payment_id'] ?? '';
    
    if (empty($paymentId)) {
        errorResponse('Payment ID is required');
    }
    
    try {
        $bkash = new BkashPayment();
        $result = $bkash->queryPayment($paymentId);
        
        successResponse('Payment status retrieved', $result);
        
    } catch (Exception $e) {
        error_log("bKash payment query error: " . $e->getMessage());
        errorResponse('Payment query failed: ' . $e->getMessage());
    }
}

function handleRefundPayment() {
    // Require admin access for refunds
    if (!hasAnyRole(['admin', 'super_admin'])) {
        errorResponse('Access denied', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $paymentId = $input['payment_id'] ?? '';
    $amount = (float)($input['amount'] ?? 0);
    $reason = sanitizeInput($input['reason'] ?? '');
    
    if (empty($paymentId) || $amount <= 0) {
        errorResponse('Invalid payment ID or amount');
    }
    
    try {
        // Get payment record
        $payment = getRecord('payments', ['transaction_id' => $paymentId, 'status' => 'completed']);
        
        if (!$payment) {
            errorResponse('Payment not found or not eligible for refund');
        }
        
        if ($amount > $payment['amount']) {
            errorResponse('Refund amount cannot exceed original payment amount');
        }
        
        $bkash = new BkashPayment();
        $result = $bkash->refundPayment($paymentId, $amount, $reason);
        
        if ($result['success']) {
            $db = getDB();
            $db->beginTransaction();
            
            try {
                // Update payment status
                updateRecord('payments', [
                    'status' => 'refunded',
                    'gateway_response' => json_encode($result)
                ], ['id' => $payment['id']]);
                
                // Update order status
                updateRecord('orders', [
                    'payment_status' => 'refunded',
                    'status' => 'refunded'
                ], ['id' => $payment['order_id']]);
                
                // Log activity
                $adminId = $_SESSION['user_id'];
                logActivity($adminId, 'payment_refunded', 'payment', $payment['id'], 'bKash payment refunded', [
                    'refund_trx_id' => $result['refund_trx_id'],
                    'amount' => $amount,
                    'reason' => $reason
                ]);
                
                // Notify customer
                $order = getRecord('orders', ['id' => $payment['order_id']]);
                if ($order) {
                    createNotification(
                        $order['user_id'],
                        'payment_refunded',
                        'Payment Refunded',
                        "Your payment of {$amount} BDT has been refunded. Refund ID: {$result['refund_trx_id']}",
                        ['order_id' => $order['id'], 'refund_trx_id' => $result['refund_trx_id']],
                        ['email', 'sms']
                    );
                }
                
                $db->commit();
                
                successResponse('Payment refunded successfully', [
                    'refund_trx_id' => $result['refund_trx_id'],
                    'amount' => $amount
                ]);
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        }
        
    } catch (Exception $e) {
        error_log("bKash refund error: " . $e->getMessage());
        errorResponse('Refund failed: ' . $e->getMessage());
    }
}

function updateProductStock($productId, $variantId, $quantity, $movementType, $referenceType, $referenceId, $performedBy, $notes) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Update stock in products or variants table
        if ($variantId) {
            $sql = "UPDATE product_variants SET stock_quantity = stock_quantity " . 
                   ($movementType === 'in' ? '+' : '-') . " ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$quantity, $variantId]);
        } else {
            $sql = "UPDATE products SET stock_quantity = stock_quantity " . 
                   ($movementType === 'in' ? '+' : '-') . " ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$quantity, $productId]);
        }
        
        // Record inventory movement
        insertRecord('inventory_movements', [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'performed_by' => $performedBy
        ]);
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}
?>
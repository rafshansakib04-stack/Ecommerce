<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Admin or Technician can issue credit notes
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'technician'])) {
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
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $invoiceId = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : null;
    $reason = trim($_POST['reason'] ?? 'Warranty/Return Credit');
    $amount = (float)($_POST['amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($customerId <= 0 || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit();
    }

    $db = Database::getInstance();
    $creditNumber = 'CN' . date('Ymd') . strtoupper(substr(md5(uniqid('', true)), 0, 6));

    // Create credit note
    $creditId = $db->insert('credit_notes', [
        'credit_note_number' => $creditNumber,
        'customer_id' => $customerId,
        'invoice_id' => $invoiceId,
        'reason' => $reason,
        'amount' => $amount,
        'notes' => $notes,
        'created_by' => $_SESSION['user_id']
    ]);

    // Ledger entry as credit
    $db->insert('customer_ledger', [
        'customer_id' => $customerId,
        'transaction_date' => date('Y-m-d'),
        'transaction_type' => 'credit',
        'reference_type' => 'credit_note',
        'reference_id' => $creditId,
        'description' => 'Credit Note ' . $creditNumber . ' - ' . $reason,
        'amount' => $amount
    ]);

    logActivity($_SESSION['user_id'], 'credit_note_created', 'Credit Note ' . $creditNumber . ' for customer ' . $customerId);

    echo json_encode(['success' => true, 'message' => 'Credit note issued', 'credit_note_number' => $creditNumber]);
} catch (Exception $e) {
    error_log('Create credit note error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>


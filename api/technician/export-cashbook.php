<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is technician
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'technician') {
    header('Location: ../../index.php');
    exit();
}

$db = Database::getInstance();

// Get filter parameters
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

// Get transactions
$transactions = $db->fetchAll("
    SELECT * FROM daily_cashbook 
    $whereClause
    ORDER BY transaction_date DESC, created_at DESC
", $params);

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="cashbook_' . date('Y-m-d') . '.xls"');

echo "Daily Cashbook Report\n";
echo "Generated on: " . date('Y-m-d H:i:s') . "\n\n";

echo "Date\tType\tCategory\tDescription\tAmount\tPayment Method\tReference\n";

foreach ($transactions as $transaction) {
    echo $transaction['transaction_date'] . "\t";
    echo $transaction['transaction_type'] . "\t";
    echo $transaction['category'] . "\t";
    echo $transaction['description'] . "\t";
    echo "₹" . number_format($transaction['amount'], 2) . "\t";
    echo $transaction['payment_method'] . "\t";
    echo $transaction['reference_number'] . "\n";
}

exit();
?>
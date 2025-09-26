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

// Get technician ID
$technician = $db->fetchOne("SELECT id FROM technicians WHERE user_id = ?", [$_SESSION['user_id']]);

if (!$technician) {
    die('Technician not found');
}

// Get filter parameters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$whereConditions = ['technician_id = ?'];
$params = [$technician['id']];

if (!empty($status)) {
    $whereConditions[] = 'status = ?';
    $params[] = $status;
}

if (!empty($search)) {
    $whereConditions[] = '(bill_number LIKE ? OR customer_name LIKE ? OR service_type LIKE ?)';
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get bills
$bills = $db->fetchAll("
    SELECT * FROM technician_bills 
    $whereClause
    ORDER BY created_at DESC
", $params);

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="bills_' . date('Y-m-d') . '.xls"');

echo "Technician Bills Report\n";
echo "Generated on: " . date('Y-m-d H:i:s') . "\n\n";

echo "Bill #\tCustomer Name\tPhone\tService Type\tService Date\tLabor Charges\tParts Cost\tTravel Charges\tOther Charges\tSubtotal\tTax\tTotal Amount\tPaid Amount\tBalance\tStatus\tCreated\n";

foreach ($bills as $bill) {
    echo $bill['bill_number'] . "\t";
    echo $bill['customer_name'] . "\t";
    echo $bill['customer_phone'] . "\t";
    echo $bill['service_type'] . "\t";
    echo $bill['service_date'] . "\t";
    echo "₹" . number_format($bill['labor_charges'], 2) . "\t";
    echo "₹" . number_format($bill['parts_cost'], 2) . "\t";
    echo "₹" . number_format($bill['travel_charges'], 2) . "\t";
    echo "₹" . number_format($bill['other_charges'], 2) . "\t";
    echo "₹" . number_format($bill['subtotal'], 2) . "\t";
    echo "₹" . number_format($bill['tax_amount'], 2) . "\t";
    echo "₹" . number_format($bill['total_amount'], 2) . "\t";
    echo "₹" . number_format($bill['paid_amount'], 2) . "\t";
    echo "₹" . number_format($bill['balance_amount'], 2) . "\t";
    echo $bill['status'] . "\t";
    echo $bill['created_at'] . "\n";
}

exit();
?>
<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

$db = Database::getInstance();

$status = $_GET['status'] ?? '';
$customer = $_GET['customer'] ?? '';
$search = $_GET['search'] ?? '';

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
    SELECT i.*, c.company_name, c.contact_person
    FROM invoices i
    JOIN customers c ON i.customer_id = c.id
    $whereClause
    ORDER BY i.created_at DESC
", $params);

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="invoices_' . date('Y-m-d') . '.xls"');

echo "Invoices Export\n";
echo "Generated on: " . date('Y-m-d H:i:s') . "\n\n";

echo "Invoice #\tCustomer\tDate\tDue Date\tSubtotal\tTax\tDiscount\tTotal\tPaid\tBalance\tStatus\n";

foreach ($invoices as $invoice) {
    echo $invoice['invoice_number'] . "\t";
    echo ($invoice['company_name'] ?: $invoice['contact_person']) . "\t";
    echo $invoice['invoice_date'] . "\t";
    echo $invoice['due_date'] . "\t";
    echo '₹' . number_format((float)$invoice['subtotal'], 2) . "\t";
    echo '₹' . number_format((float)$invoice['tax_amount'], 2) . "\t";
    echo '₹' . number_format((float)($invoice['discount_amount'] ?? 0), 2) . "\t";
    echo '₹' . number_format((float)$invoice['total_amount'], 2) . "\t";
    echo '₹' . number_format((float)($invoice['paid_amount'] ?? 0), 2) . "\t";
    echo '₹' . number_format((float)$invoice['balance_amount'], 2) . "\t";
    echo $invoice['status'] . "\n";
}

exit();
?>


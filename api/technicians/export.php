<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../index.php');
    exit();
}

$db = Database::getInstance();

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(t.full_name LIKE ? OR t.employee_id LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($status)) {
    $whereConditions[] = "t.status = ?";
    $params[] = $status;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get technicians
$technicians = $db->fetchAll("
    SELECT t.*, u.username, u.email, u.phone, u.status as user_status,
           (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_technician_id = t.id) as total_services,
           (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_technician_id = t.id AND sr.status = 'completed') as completed_services,
           (SELECT AVG(sr.customer_rating) FROM service_requests sr WHERE sr.assigned_technician_id = t.id AND sr.customer_rating IS NOT NULL) as avg_rating
    FROM technicians t 
    JOIN users u ON t.user_id = u.id 
    $whereClause
    ORDER BY t.created_at DESC
", $params);

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="technicians_' . date('Y-m-d') . '.xls"');

echo "Technicians Report\n";
echo "Generated on: " . date('Y-m-d H:i:s') . "\n\n";

echo "Employee ID\tFull Name\tEmail\tPhone\tSkills\tService Areas\tJoining Date\tSalary\tCommission Rate\tTotal Services\tCompleted Services\tAverage Rating\tStatus\tCreated\n";

foreach ($technicians as $technician) {
    echo $technician['employee_id'] . "\t";
    echo $technician['full_name'] . "\t";
    echo $technician['email'] . "\t";
    echo $technician['phone'] . "\t";
    echo $technician['skills'] . "\t";
    echo $technician['service_areas'] . "\t";
    echo $technician['joining_date'] . "\t";
    echo "₹" . number_format($technician['salary'], 2) . "\t";
    echo $technician['commission_rate'] . "%\t";
    echo $technician['total_services'] . "\t";
    echo $technician['completed_services'] . "\t";
    echo number_format($technician['avg_rating'], 2) . "\t";
    echo $technician['status'] . "\t";
    echo $technician['created_at'] . "\n";
}

exit();
?>
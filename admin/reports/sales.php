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

// Get report parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$customerId = $_GET['customer_id'] ?? '';
$reportType = $_GET['report_type'] ?? 'summary';

// Get sales data
$whereConditions = ["i.created_at BETWEEN ? AND ?"];
$params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

if (!empty($customerId)) {
    $whereConditions[] = 'i.customer_id = ?';
    $params[] = $customerId;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Summary statistics
$summary = [
    'total_invoices' => $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM invoices i 
        $whereClause
    ", $params)['count'],
    
    'total_amount' => $db->fetchOne("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM invoices i 
        $whereClause
    ", $params)['total'],
    
    'paid_amount' => $db->fetchOne("
        SELECT COALESCE(SUM(paid_amount), 0) as total 
        FROM invoices i 
        $whereClause AND status = 'paid'
    ", $params)['total'],
    
    'pending_amount' => $db->fetchOne("
        SELECT COALESCE(SUM(balance_amount), 0) as total 
        FROM invoices i 
        $whereClause AND status != 'paid'
    ", $params)['total']
];

// Detailed sales data
$salesData = $db->fetchAll("
    SELECT i.*, c.company_name, c.contact_person,
           (SELECT COUNT(*) FROM payments p WHERE p.invoice_id = i.id) as payment_count
    FROM invoices i 
    JOIN customers c ON i.customer_id = c.id 
    $whereClause
    ORDER BY i.created_at DESC
", $params);

// Monthly sales trend
$monthlyTrend = $db->fetchAll("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
           COUNT(*) as invoice_count,
           COALESCE(SUM(total_amount), 0) as total_amount,
           COALESCE(SUM(paid_amount), 0) as paid_amount
    FROM invoices 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
", [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

// Top customers
$topCustomers = $db->fetchAll("
    SELECT c.company_name, c.contact_person,
           COUNT(i.id) as invoice_count,
           COALESCE(SUM(i.total_amount), 0) as total_amount
    FROM customers c 
    LEFT JOIN invoices i ON c.id = i.customer_id 
    $whereClause
    GROUP BY c.id 
    ORDER BY total_amount DESC 
    LIMIT 10
", $params);

// Get customers for filter
$customers = $db->fetchAll("SELECT id, company_name, contact_person FROM customers ORDER BY company_name");

// Handle export
if (isset($_GET['export'])) {
    exportSalesReport($salesData, $summary, $startDate, $endDate);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Water Purifier ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-tint me-2"></i>Water Purifier ERP
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>Sales Reports
                        </h4>
                        <div class="btn-group">
                            <button class="btn btn-outline-primary" onclick="exportReport()">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
                            <button class="btn btn-outline-info" onclick="printReport()">
                                <i class="fas fa-print me-1"></i>Print
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Report Filters -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" value="<?php echo $startDate; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" value="<?php echo $endDate; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="customer_filter" class="form-label">Customer</label>
                                <select class="form-select" id="customer_filter">
                                    <option value="">All Customers</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" <?php echo $customerId == $customer['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['company_name'] ?: $customer['contact_person']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-select" id="report_type">
                                    <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>>Summary</option>
                                    <option value="detailed" <?php echo $reportType === 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                                    <option value="trend" <?php echo $reportType === 'trend' ? 'selected' : ''; ?>>Trend Analysis</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <button class="btn btn-primary" onclick="generateReport()">
                                    <i class="fas fa-chart-bar me-1"></i>Generate Report
                                </button>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="dashboard-card primary">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="stat-number"><?php echo $summary['total_invoices']; ?></div>
                                            <div class="stat-label">Total Invoices</div>
                                        </div>
                                        <div class="stat-icon text-primary">
                                            <i class="fas fa-file-invoice"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="dashboard-card success">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="stat-number">₹<?php echo number_format($summary['total_amount']); ?></div>
                                            <div class="stat-label">Total Sales</div>
                                        </div>
                                        <div class="stat-icon text-success">
                                            <i class="fas fa-rupee-sign"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="dashboard-card info">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="stat-number">₹<?php echo number_format($summary['paid_amount']); ?></div>
                                            <div class="stat-label">Paid Amount</div>
                                        </div>
                                        <div class="stat-icon text-info">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="dashboard-card warning">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="stat-number">₹<?php echo number_format($summary['pending_amount']); ?></div>
                                            <div class="stat-label">Pending Amount</div>
                                        </div>
                                        <div class="stat-icon text-warning">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts -->
                        <?php if ($reportType === 'trend' || $reportType === 'summary'): ?>
                        <div class="row mb-4">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Monthly Sales Trend</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="salesTrendChart" height="100"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Top Customers</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($topCustomers as $customer): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <strong><?php echo htmlspecialchars($customer['company_name'] ?: $customer['contact_person']); ?></strong>
                                                <br><small class="text-muted"><?php echo $customer['invoice_count']; ?> invoices</small>
                                            </div>
                                            <div class="text-end">
                                                <strong>₹<?php echo number_format($customer['total_amount']); ?></strong>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Detailed Sales Table -->
                        <?php if ($reportType === 'detailed' || $reportType === 'summary'): ?>
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Sales Details</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Invoice #</th>
                                                        <th>Customer</th>
                                                        <th>Date</th>
                                                        <th>Amount</th>
                                                        <th>Paid</th>
                                                        <th>Balance</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($salesData as $sale): ?>
                                                    <tr>
                                                        <td><strong><?php echo $sale['invoice_number']; ?></strong></td>
                                                        <td>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($sale['company_name'] ?: $sale['contact_person']); ?></strong>
                                                            </div>
                                                        </td>
                                                        <td><?php echo formatDate($sale['invoice_date']); ?></td>
                                                        <td><strong>₹<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                                                        <td class="text-success">₹<?php echo number_format($sale['paid_amount'], 2); ?></td>
                                                        <td class="<?php echo $sale['balance_amount'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                            ₹<?php echo number_format($sale['balance_amount'], 2); ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo getStatusBadge($sale['status']); ?>">
                                                                <?php echo ucfirst($sale['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        // Sales Trend Chart
        const monthlyData = <?php echo json_encode($monthlyTrend); ?>;
        const ctx = document.getElementById('salesTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month),
                datasets: [{
                    label: 'Total Sales (₹)',
                    data: monthlyData.map(item => item.total_amount),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Paid Amount (₹)',
                    data: monthlyData.map(item => item.paid_amount),
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        function generateReport() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const customer = document.getElementById('customer_filter').value;
            const reportType = document.getElementById('report_type').value;
            
            const params = new URLSearchParams();
            params.set('start_date', startDate);
            params.set('end_date', endDate);
            if (customer) params.set('customer_id', customer);
            params.set('report_type', reportType);
            
            window.location.href = 'sales.php?' + params.toString();
        }

        function exportReport() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const customer = document.getElementById('customer_filter').value;
            const reportType = document.getElementById('report_type').value;
            
            const params = new URLSearchParams();
            params.set('start_date', startDate);
            params.set('end_date', endDate);
            if (customer) params.set('customer_id', customer);
            params.set('report_type', reportType);
            params.set('export', '1');
            
            window.open('sales.php?' + params.toString(), '_blank');
        }

        function printReport() {
            window.print();
        }
    </script>
</body>
</html>

<?php
function exportSalesReport($salesData, $summary, $startDate, $endDate) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="sales_report_' . $startDate . '_to_' . $endDate . '.xls"');
    
    echo "Sales Report\n";
    echo "Period: $startDate to $endDate\n\n";
    
    echo "Summary:\n";
    echo "Total Invoices: " . $summary['total_invoices'] . "\n";
    echo "Total Amount: ₹" . number_format($summary['total_amount'], 2) . "\n";
    echo "Paid Amount: ₹" . number_format($summary['paid_amount'], 2) . "\n";
    echo "Pending Amount: ₹" . number_format($summary['pending_amount'], 2) . "\n\n";
    
    echo "Detailed Sales:\n";
    echo "Invoice #\tCustomer\tDate\tAmount\tPaid\tBalance\tStatus\n";
    
    foreach ($salesData as $sale) {
        echo $sale['invoice_number'] . "\t";
        echo ($sale['company_name'] ?: $sale['contact_person']) . "\t";
        echo $sale['invoice_date'] . "\t";
        echo "₹" . number_format($sale['total_amount'], 2) . "\t";
        echo "₹" . number_format($sale['paid_amount'], 2) . "\t";
        echo "₹" . number_format($sale['balance_amount'], 2) . "\t";
        echo $sale['status'] . "\n";
    }
    
    exit();
}
?>
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
$reportType = $_GET['report_type'] ?? 'summary';

// Get financial data
$whereConditions = ["created_at BETWEEN ? AND ?"];
$params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Summary statistics
$summary = [
    'total_revenue' => $db->fetchOne("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM invoices 
        $whereClause
    ", $params)['total'],
    
    'paid_revenue' => $db->fetchOne("
        SELECT COALESCE(SUM(paid_amount), 0) as total 
        FROM invoices 
        $whereClause AND status = 'paid'
    ", $params)['total'],
    
    'pending_revenue' => $db->fetchOne("
        SELECT COALESCE(SUM(balance_amount), 0) as total 
        FROM invoices 
        $whereClause AND status != 'paid'
    ", $params)['total'],
    
    'total_expenses' => $db->fetchOne("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM daily_cashbook 
        WHERE transaction_type = 'expense' AND transaction_date BETWEEN ? AND ?
    ", [$startDate, $endDate])['total'],
    
    'total_income' => $db->fetchOne("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM daily_cashbook 
        WHERE transaction_type = 'income' AND transaction_date BETWEEN ? AND ?
    ", [$startDate, $endDate])['total']
];

// Monthly revenue trend
$monthlyRevenue = $db->fetchAll("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
           COUNT(*) as invoice_count,
           COALESCE(SUM(total_amount), 0) as total_revenue,
           COALESCE(SUM(paid_amount), 0) as paid_revenue
    FROM invoices 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
", [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

// Expense breakdown by category
$expenseBreakdown = $db->fetchAll("
    SELECT category,
           COUNT(*) as transaction_count,
           COALESCE(SUM(amount), 0) as total_amount
    FROM daily_cashbook 
    WHERE transaction_type = 'expense' AND transaction_date BETWEEN ? AND ?
    GROUP BY category
    ORDER BY total_amount DESC
", [$startDate, $endDate]);

// Income breakdown by category
$incomeBreakdown = $db->fetchAll("
    SELECT category,
           COUNT(*) as transaction_count,
           COALESCE(SUM(amount), 0) as total_amount
    FROM daily_cashbook 
    WHERE transaction_type = 'income' AND transaction_date BETWEEN ? AND ?
    GROUP BY category
    ORDER BY total_amount DESC
", [$startDate, $endDate]);

// Recent transactions
$recentTransactions = $db->fetchAll("
    SELECT transaction_date, transaction_type, category, description, amount, payment_method
    FROM daily_cashbook 
    WHERE transaction_date BETWEEN ? AND ?
    ORDER BY transaction_date DESC, created_at DESC
    LIMIT 20
", [$startDate, $endDate]);

// Handle export
if (isset($_GET['export'])) {
    exportFinancialReport($summary, $monthlyRevenue, $expenseBreakdown, $incomeBreakdown, $recentTransactions, $startDate, $endDate);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - Water Purifier ERP</title>
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
                            <i class="fas fa-chart-pie me-2"></i>Financial Reports
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
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-select" id="report_type">
                                    <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>>Summary</option>
                                    <option value="detailed" <?php echo $reportType === 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                                    <option value="trend" <?php echo $reportType === 'trend' ? 'selected' : ''; ?>>Trend Analysis</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button class="btn btn-primary d-block w-100" onclick="generateReport()">
                                    <i class="fas fa-chart-bar me-1"></i>Generate Report
                                </button>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card success">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="stat-number">₹<?php echo number_format($summary['total_revenue']); ?></div>
                                            <div class="stat-label">Total Revenue</div>
                                        </div>
                                        <div class="stat-icon text-success">
                                            <i class="fas fa-rupee-sign"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card info">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="stat-number">₹<?php echo number_format($summary['paid_revenue']); ?></div>
                                            <div class="stat-label">Paid Revenue</div>
                                        </div>
                                        <div class="stat-icon text-info">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card warning">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="stat-number">₹<?php echo number_format($summary['pending_revenue']); ?></div>
                                            <div class="stat-label">Pending Revenue</div>
                                        </div>
                                        <div class="stat-icon text-warning">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card danger">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="stat-number">₹<?php echo number_format($summary['total_expenses']); ?></div>
                                            <div class="stat-label">Total Expenses</div>
                                        </div>
                                        <div class="stat-icon text-danger">
                                            <i class="fas fa-arrow-down"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card primary">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="stat-number">₹<?php echo number_format($summary['total_income']); ?></div>
                                            <div class="stat-label">Total Income</div>
                                        </div>
                                        <div class="stat-icon text-primary">
                                            <i class="fas fa-arrow-up"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card <?php echo ($summary['total_income'] - $summary['total_expenses']) >= 0 ? 'success' : 'danger'; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="stat-number">₹<?php echo number_format($summary['total_income'] - $summary['total_expenses']); ?></div>
                                            <div class="stat-label">Net Profit</div>
                                        </div>
                                        <div class="stat-icon <?php echo ($summary['total_income'] - $summary['total_expenses']) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <i class="fas fa-chart-line"></i>
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
                                        <h5 class="mb-0">Monthly Revenue Trend</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="revenueChart" height="100"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Expense Breakdown</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="expenseChart" height="100"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Detailed Tables -->
                        <?php if ($reportType === 'detailed' || $reportType === 'summary'): ?>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Expense Breakdown by Category</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Category</th>
                                                        <th>Count</th>
                                                        <th>Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($expenseBreakdown as $expense): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($expense['category']); ?></td>
                                                        <td><?php echo $expense['transaction_count']; ?></td>
                                                        <td>₹<?php echo number_format($expense['total_amount'], 2); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Income Breakdown by Category</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Category</th>
                                                        <th>Count</th>
                                                        <th>Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($incomeBreakdown as $income): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($income['category']); ?></td>
                                                        <td><?php echo $income['transaction_count']; ?></td>
                                                        <td>₹<?php echo number_format($income['total_amount'], 2); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Transactions -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Recent Transactions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Type</th>
                                                        <th>Category</th>
                                                        <th>Description</th>
                                                        <th>Amount</th>
                                                        <th>Method</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recentTransactions as $transaction): ?>
                                                    <tr>
                                                        <td><?php echo formatDate($transaction['transaction_date']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $transaction['transaction_type'] === 'income' ? 'success' : 'danger'; ?>">
                                                                <?php echo ucfirst($transaction['transaction_type']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($transaction['category']); ?></td>
                                                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                        <td class="<?php echo $transaction['transaction_type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo $transaction['transaction_type'] === 'income' ? '+' : '-'; ?>₹<?php echo number_format($transaction['amount'], 2); ?>
                                                        </td>
                                                        <td><?php echo ucfirst($transaction['payment_method']); ?></td>
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
        // Revenue Chart
        const monthlyData = <?php echo json_encode($monthlyRevenue); ?>;
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month),
                datasets: [{
                    label: 'Total Revenue (₹)',
                    data: monthlyData.map(item => item.total_revenue),
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Paid Revenue (₹)',
                    data: monthlyData.map(item => item.paid_revenue),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
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

        // Expense Chart
        const expenseData = <?php echo json_encode($expenseBreakdown); ?>;
        const expenseCtx = document.getElementById('expenseChart').getContext('2d');
        new Chart(expenseCtx, {
            type: 'doughnut',
            data: {
                labels: expenseData.map(item => item.category),
                datasets: [{
                    data: expenseData.map(item => item.total_amount),
                    backgroundColor: [
                        '#dc3545',
                        '#fd7e14',
                        '#ffc107',
                        '#198754',
                        '#0d6efd',
                        '#6f42c1',
                        '#e83e8c',
                        '#20c997'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        function generateReport() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const reportType = document.getElementById('report_type').value;
            
            const params = new URLSearchParams();
            params.set('start_date', startDate);
            params.set('end_date', endDate);
            params.set('report_type', reportType);
            
            window.location.href = 'financial.php?' + params.toString();
        }

        function exportReport() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const reportType = document.getElementById('report_type').value;
            
            const params = new URLSearchParams();
            params.set('start_date', startDate);
            params.set('end_date', endDate);
            params.set('report_type', reportType);
            params.set('export', '1');
            
            window.open('financial.php?' + params.toString(), '_blank');
        }

        function printReport() {
            window.print();
        }
    </script>
</body>
</html>

<?php
function exportFinancialReport($summary, $monthlyRevenue, $expenseBreakdown, $incomeBreakdown, $recentTransactions, $startDate, $endDate) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="financial_report_' . $startDate . '_to_' . $endDate . '.xls"');
    
    echo "Financial Report\n";
    echo "Period: $startDate to $endDate\n\n";
    
    echo "Summary:\n";
    echo "Total Revenue: ₹" . number_format($summary['total_revenue'], 2) . "\n";
    echo "Paid Revenue: ₹" . number_format($summary['paid_revenue'], 2) . "\n";
    echo "Pending Revenue: ₹" . number_format($summary['pending_revenue'], 2) . "\n";
    echo "Total Expenses: ₹" . number_format($summary['total_expenses'], 2) . "\n";
    echo "Total Income: ₹" . number_format($summary['total_income'], 2) . "\n";
    echo "Net Profit: ₹" . number_format($summary['total_income'] - $summary['total_expenses'], 2) . "\n\n";
    
    echo "Monthly Revenue:\n";
    echo "Month\tInvoice Count\tTotal Revenue\tPaid Revenue\n";
    foreach ($monthlyRevenue as $month) {
        echo $month['month'] . "\t" . $month['invoice_count'] . "\t₹" . number_format($month['total_revenue'], 2) . "\t₹" . number_format($month['paid_revenue'], 2) . "\n";
    }
    
    echo "\nExpense Breakdown:\n";
    echo "Category\tCount\tAmount\n";
    foreach ($expenseBreakdown as $expense) {
        echo $expense['category'] . "\t" . $expense['transaction_count'] . "\t₹" . number_format($expense['total_amount'], 2) . "\n";
    }
    
    echo "\nIncome Breakdown:\n";
    echo "Category\tCount\tAmount\n";
    foreach ($incomeBreakdown as $income) {
        echo $income['category'] . "\t" . $income['transaction_count'] . "\t₹" . number_format($income['total_amount'], 2) . "\n";
    }
    
    echo "\nRecent Transactions:\n";
    echo "Date\tType\tCategory\tDescription\tAmount\tMethod\n";
    foreach ($recentTransactions as $transaction) {
        echo $transaction['transaction_date'] . "\t" . $transaction['transaction_type'] . "\t" . $transaction['category'] . "\t" . $transaction['description'] . "\t₹" . number_format($transaction['amount'], 2) . "\t" . $transaction['payment_method'] . "\n";
    }
    
    exit();
}
?>
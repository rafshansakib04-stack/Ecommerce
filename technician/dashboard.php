<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is technician
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'technician') {
    header('Location: ../index.php');
    exit();
}

$db = Database::getInstance();

// Get technician details
$technician = $db->fetchOne("
    SELECT t.*, u.username, u.email, u.phone 
    FROM technicians t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.user_id = ?
", [$_SESSION['user_id']]);

if (!$technician) {
    header('Location: ../index.php');
    exit();
}

// Get dashboard statistics
$stats = [];

// Today's tasks
$stats['today_tasks'] = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM service_requests 
    WHERE assigned_technician_id = ? 
    AND DATE(created_at) = CURDATE()
", [$technician['id']])['count'];

// Pending tasks
$stats['pending_tasks'] = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM service_requests 
    WHERE assigned_technician_id = ? 
    AND status IN ('assigned', 'in_progress')
", [$technician['id']])['count'];

// Completed this month
$stats['monthly_completed'] = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM service_requests 
    WHERE assigned_technician_id = ? 
    AND status = 'completed' 
    AND MONTH(created_at) = MONTH(CURDATE()) 
    AND YEAR(created_at) = YEAR(CURDATE())
", [$technician['id']])['count'];

// Average rating
$stats['avg_rating'] = $db->fetchOne("
    SELECT AVG(customer_rating) as rating 
    FROM service_requests 
    WHERE assigned_technician_id = ? 
    AND customer_rating IS NOT NULL
", [$technician['id']])['rating'] ?? 0;

// Today's assigned tasks
$todaysTasks = $db->fetchAll("
    SELECT sr.*, c.company_name, c.contact_person, c.phone as customer_phone, c.address
    FROM service_requests sr 
    JOIN customers c ON sr.customer_id = c.id 
    WHERE sr.assigned_technician_id = ? 
    AND sr.status IN ('assigned', 'in_progress')
    ORDER BY sr.priority DESC, sr.created_at ASC
", [$technician['id']]);

// Recent completed tasks
$recentCompleted = $db->fetchAll("
    SELECT sr.*, c.company_name, c.contact_person
    FROM service_requests sr 
    JOIN customers c ON sr.customer_id = c.id 
    WHERE sr.assigned_technician_id = ? 
    AND sr.status = 'completed'
    ORDER BY sr.actual_end_time DESC 
    LIMIT 5
", [$technician['id']]);

// Monthly earnings
$monthlyEarnings = $db->fetchOne("
    SELECT COALESCE(SUM(amount), 0) as earnings 
    FROM daily_cashbook 
    WHERE user_id = ? 
    AND transaction_type = 'income' 
    AND MONTH(transaction_date) = MONTH(CURDATE()) 
    AND YEAR(transaction_date) = YEAR(CURDATE())
", [$technician['user_id']])['earnings'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard - Water Purifier ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tint me-2"></i>Water Purifier ERP
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tasks.php">
                            <i class="fas fa-tasks me-1"></i>My Tasks
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="billing.php">
                            <i class="fas fa-money-bill me-1"></i>Billing
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cashbook.php">
                            <i class="fas fa-book me-1"></i>Cashbook
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user me-1"></i>Profile
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo $technician['full_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../api/auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Welcome Message -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h4 class="mb-1">Welcome back, <?php echo htmlspecialchars($technician['full_name']); ?>!</h4>
                        <p class="mb-0">Here's your task overview and performance metrics.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['today_tasks']; ?></div>
                            <div class="stat-label">Today's Tasks</div>
                        </div>
                        <div class="stat-icon text-info">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['pending_tasks']; ?></div>
                            <div class="stat-label">Pending Tasks</div>
                        </div>
                        <div class="stat-icon text-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo $stats['monthly_completed']; ?></div>
                            <div class="stat-label">Completed This Month</div>
                        </div>
                        <div class="stat-icon text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo number_format($stats['avg_rating'], 1); ?>/5</div>
                            <div class="stat-label">Average Rating</div>
                        </div>
                        <div class="stat-icon text-primary">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Today's Tasks -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Today's Tasks</h5>
                        <span class="badge bg-primary"><?php echo count($todaysTasks); ?> Tasks</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($todaysTasks)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ticket #</th>
                                            <th>Customer</th>
                                            <th>Service Type</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($todaysTasks as $task): ?>
                                        <tr>
                                            <td><strong><?php echo $task['ticket_number']; ?></strong></td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($task['company_name'] ?: $task['contact_person']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($task['customer_phone']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo ucfirst($task['service_type']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusBadge($task['priority']); ?>">
                                                    <?php echo ucfirst($task['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusBadge($task['status']); ?>">
                                                    <?php echo ucfirst($task['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewTask(<?php echo $task['id']; ?>)" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success" onclick="startTask(<?php echo $task['id']; ?>)" title="Start Task">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="updateTask(<?php echo $task['id']; ?>)" title="Update Status">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No tasks assigned for today</h5>
                                <p class="text-muted">Check back later for new assignments.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats & Actions -->
            <div class="col-lg-4 mb-4">
                <!-- Monthly Earnings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-money-bill me-2"></i>Monthly Earnings</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="h2 text-success">₹<?php echo number_format($monthlyEarnings); ?></div>
                        <p class="text-muted">This Month</p>
                        <a href="billing.php" class="btn btn-outline-success btn-sm">View Details</a>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" onclick="checkIn()">
                                <i class="fas fa-sign-in-alt me-2"></i>Check In
                            </button>
                            <button class="btn btn-success" onclick="checkOut()">
                                <i class="fas fa-sign-out-alt me-2"></i>Check Out
                            </button>
                            <a href="cashbook.php" class="btn btn-outline-primary">
                                <i class="fas fa-book me-2"></i>Add Expense
                            </a>
                            <a href="profile.php" class="btn btn-outline-info">
                                <i class="fas fa-user me-2"></i>Update Profile
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Performance Metrics -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <div class="h4 text-primary"><?php echo $stats['monthly_completed']; ?></div>
                                    <small class="text-muted">Completed</small>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <div class="h4 text-warning"><?php echo number_format($stats['avg_rating'], 1); ?></div>
                                    <small class="text-muted">Rating</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Completed Tasks -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Recent Completed Tasks</h5>
                        <a href="tasks.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentCompleted)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ticket #</th>
                                            <th>Customer</th>
                                            <th>Service Type</th>
                                            <th>Completed Date</th>
                                            <th>Rating</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentCompleted as $task): ?>
                                        <tr>
                                            <td><strong><?php echo $task['ticket_number']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($task['company_name'] ?: $task['contact_person']); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo ucfirst($task['service_type']); ?></span>
                                            </td>
                                            <td><?php echo formatDate($task['actual_end_time']); ?></td>
                                            <td>
                                                <?php if ($task['customer_rating']): ?>
                                                    <div class="d-flex align-items-center">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= $task['customer_rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                        <?php endfor; ?>
                                                        <span class="ms-1">(<?php echo $task['customer_rating']; ?>)</span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">No rating</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewTask(<?php echo $task['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No completed tasks yet</h5>
                                <p class="text-muted">Your completed tasks will appear here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Task Modal -->
    <div class="modal fade" id="viewTaskModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tools me-2"></i>Task Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="taskDetails">
                    <!-- Task details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Task Status Modal -->
    <div class="modal fade" id="updateTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Update Task Status
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="updateTaskForm">
                    <input type="hidden" id="update_task_id" name="task_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="task_status" class="form-label">Status</label>
                            <select class="form-select" id="task_status" name="status" required>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="task_notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="task_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-1"></i>Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../assets/js/technician-dashboard.js"></script>
</body>
</html>
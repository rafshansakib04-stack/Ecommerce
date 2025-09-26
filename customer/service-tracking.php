<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header('Location: ../index.php');
    exit();
}

$db = Database::getInstance();

// Get customer details
$customer = $db->fetchOne("
    SELECT c.*, u.username, u.email, u.phone 
    FROM customers c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.user_id = ?
", [$_SESSION['user_id']]);

if (!$customer) {
    header('Location: ../index.php');
    exit();
}

// Get service requests for this customer
$serviceRequests = $db->fetchAll("
    SELECT sr.*, t.full_name as technician_name, t.phone as technician_phone,
           (SELECT COUNT(*) FROM service_attachments sa WHERE sa.service_request_id = sr.id) as attachment_count
    FROM service_requests sr 
    LEFT JOIN technicians t ON sr.assigned_technician_id = t.id 
    WHERE sr.customer_id = ? 
    ORDER BY sr.created_at DESC
", [$customer['id']]);

// Get recent service history
$recentServices = $db->fetchAll("
    SELECT sr.*, t.full_name as technician_name
    FROM service_requests sr 
    LEFT JOIN technicians t ON sr.assigned_technician_id = t.id 
    WHERE sr.customer_id = ? AND sr.status = 'completed'
    ORDER BY sr.completed_at DESC 
    LIMIT 5
", [$customer['id']]);

// Get upcoming services
$upcomingServices = $db->fetchAll("
    SELECT sr.*, t.full_name as technician_name, t.phone as technician_phone
    FROM service_requests sr 
    LEFT JOIN technicians t ON sr.assigned_technician_id = t.id 
    WHERE sr.customer_id = ? AND sr.status IN ('pending', 'assigned', 'in_progress')
    ORDER BY sr.scheduled_date ASC
", [$customer['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Tracking - Water Purifier ERP</title>
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
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Service Status Overview -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo count($serviceRequests); ?></div>
                            <div class="stat-label">Total Services</div>
                        </div>
                        <div class="stat-icon text-primary">
                            <i class="fas fa-tools"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo count(array_filter($serviceRequests, function($sr) { return $sr['status'] === 'completed'; })); ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                        <div class="stat-icon text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo count(array_filter($serviceRequests, function($sr) { return in_array($sr['status'], ['pending', 'assigned', 'in_progress']); })); ?></div>
                            <div class="stat-label">In Progress</div>
                        </div>
                        <div class="stat-icon text-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo count($upcomingServices); ?></div>
                            <div class="stat-label">Upcoming</div>
                        </div>
                        <div class="stat-icon text-info">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Current Services -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-list me-2"></i>Service Requests
                        </h4>
                        <a href="service-request.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>New Request
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($serviceRequests)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No service requests yet</h5>
                            <p class="text-muted">Create your first service request to get started.</p>
                            <a href="service-request.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Create Service Request
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Request #</th>
                                        <th>Service Type</th>
                                        <th>Status</th>
                                        <th>Technician</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($serviceRequests as $request): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo $request['id']; ?></strong>
                                            <?php if ($request['attachment_count'] > 0): ?>
                                            <br><small class="text-info"><i class="fas fa-paperclip me-1"></i><?php echo $request['attachment_count']; ?> files</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($request['service_type']); ?></strong>
                                                <?php if ($request['problem_description']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($request['problem_description'], 0, 50)) . '...'; ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadge($request['status']); ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($request['technician_name']): ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($request['technician_name']); ?></strong>
                                                <?php if ($request['technician_phone']): ?>
                                                <br><small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($request['technician_phone']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo formatDate($request['created_at']); ?></strong>
                                                <?php if ($request['scheduled_date']): ?>
                                                <br><small class="text-muted">Scheduled: <?php echo formatDate($request['scheduled_date']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewServiceRequest(<?php echo $request['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($request['status'] === 'completed'): ?>
                                                <button class="btn btn-sm btn-outline-success" onclick="rateService(<?php echo $request['id']; ?>)" title="Rate Service">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if (in_array($request['status'], ['assigned', 'in_progress'])): ?>
                                                <button class="btn btn-sm btn-outline-info" onclick="trackService(<?php echo $request['id']; ?>)" title="Track Service">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Upcoming Services -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar me-2"></i>Upcoming Services
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingServices)): ?>
                        <p class="text-muted">No upcoming services scheduled.</p>
                        <?php else: ?>
                        <?php foreach ($upcomingServices as $service): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                            <div>
                                <strong><?php echo htmlspecialchars($service['service_type']); ?></strong>
                                <br><small class="text-muted"><?php echo formatDate($service['scheduled_date']); ?></small>
                            </div>
                            <span class="badge bg-<?php echo getStatusBadge($service['status']); ?>">
                                <?php echo ucfirst($service['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Services -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Recent Services
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentServices)): ?>
                        <p class="text-muted">No recent services completed.</p>
                        <?php else: ?>
                        <?php foreach ($recentServices as $service): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?php echo htmlspecialchars($service['service_type']); ?></strong>
                                <br><small class="text-muted"><?php echo formatDate($service['completed_at']); ?></small>
                            </div>
                            <div class="text-end">
                                <?php if ($service['customer_rating']): ?>
                                <div class="text-warning">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $service['customer_rating'] ? '' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-warning" onclick="rateService(<?php echo $service['id']; ?>)">
                                    <i class="fas fa-star"></i> Rate
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Details Modal -->
    <div class="modal fade" id="serviceDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tools me-2"></i>Service Request Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="serviceDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Rate Service Modal -->
    <div class="modal fade" id="rateServiceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-star me-2"></i>Rate Service
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="rateServiceForm">
                    <input type="hidden" id="rate_service_id" name="service_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <div class="rating-input">
                                <input type="radio" name="rating" value="5" id="star5">
                                <label for="star5" class="fas fa-star"></label>
                                <input type="radio" name="rating" value="4" id="star4">
                                <label for="star4" class="fas fa-star"></label>
                                <input type="radio" name="rating" value="3" id="star3">
                                <label for="star3" class="fas fa-star"></label>
                                <input type="radio" name="rating" value="2" id="star2">
                                <label for="star2" class="fas fa-star"></label>
                                <input type="radio" name="rating" value="1" id="star1">
                                <label for="star1" class="fas fa-star"></label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="rating_feedback" class="form-label">Feedback (Optional)</label>
                            <textarea class="form-control" id="rating_feedback" name="feedback" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-star me-1"></i>Submit Rating
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../assets/js/service-tracking.js"></script>
</body>
</html>
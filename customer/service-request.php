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

// Handle service request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_request') {
    header('Content-Type: application/json');
    
    try {
        $db->beginTransaction();
        
        // Generate ticket number
        $ticketNumber = generateTicketNumber();
        
        // Create service request
        $serviceId = $db->insert('service_requests', [
            'ticket_number' => $ticketNumber,
            'customer_id' => $customer['id'],
            'service_type' => $_POST['service_type'],
            'problem_description' => $_POST['problem_description'],
            'priority' => $_POST['priority'],
            'preferred_date' => $_POST['preferred_date'],
            'preferred_time' => $_POST['preferred_time'],
            'location_lat' => $_POST['latitude'] ?? null,
            'location_lng' => $_POST['longitude'] ?? null,
            'status' => 'pending'
        ]);
        
        // Handle file uploads
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            $uploadDir = '../uploads/service_attachments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileCount = count($_FILES['attachments']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileName = $_FILES['attachments']['name'][$i];
                    $fileTmp = $_FILES['attachments']['tmp_name'][$i];
                    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $newFileName = uniqid() . '.' . $fileExt;
                    $uploadPath = $uploadDir . $newFileName;
                    
                    if (move_uploaded_file($fileTmp, $uploadPath)) {
                        $db->insert('service_attachments', [
                            'service_request_id' => $serviceId,
                            'file_name' => $fileName,
                            'file_path' => $uploadPath,
                            'file_type' => $fileExt,
                            'file_size' => $_FILES['attachments']['size'][$i]
                        ]);
                    }
                }
            }
        }
        
        // Send notification to admin
        $firebase = new FirebaseService();
        $firebase->updateRealtimeData('notifications/admin', [
            'title' => 'New Service Request',
            'message' => 'New service request received: ' . $ticketNumber,
            'timestamp' => time(),
            'type' => 'info'
        ]);
        
        // Send confirmation email to customer
        $emailSubject = "Service Request Confirmation - " . $ticketNumber;
        $emailMessage = "
        <html>
        <head><title>Service Request Confirmation</title></head>
        <body>
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #0d6efd;'>Service Request Confirmation</h2>
                <p>Dear " . $customer['contact_person'] . ",</p>
                <p>Your service request has been received and is being processed.</p>
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                    <p><strong>Ticket Number:</strong> $ticketNumber</p>
                    <p><strong>Service Type:</strong> " . ucfirst($_POST['service_type']) . "</p>
                    <p><strong>Priority:</strong> " . ucfirst($_POST['priority']) . "</p>
                    <p><strong>Preferred Date:</strong> " . $_POST['preferred_date'] . "</p>
                    <p><strong>Status:</strong> Pending</p>
                </div>
                <p>We will contact you soon to schedule the service. You can track your request status in your customer portal.</p>
                <p>Thank you for choosing our services!</p>
            </div>
        </body>
        </html>";
        
        sendEmail($customer['email'], $emailSubject, $emailMessage);
        
        // Send SMS confirmation
        $smsMessage = "Service request confirmed. Ticket: $ticketNumber. We'll contact you soon.";
        sendSMS($customer['phone'], $smsMessage);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'service_request_submitted', "Submitted service request: " . $ticketNumber);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Service request submitted successfully!',
            'ticket_number' => $ticketNumber
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Error submitting service request: ' . $e->getMessage()
        ]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Service - Water Purifier ERP</title>
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

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-plus me-2"></i>Request Service
                        </h4>
                    </div>
                    <div class="card-body">
                        <form id="serviceRequestForm" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="submit_request">
                            
                            <!-- Service Type -->
                            <div class="mb-3">
                                <label for="service_type" class="form-label">Service Type *</label>
                                <select class="form-select" id="service_type" name="service_type" required>
                                    <option value="">Select service type...</option>
                                    <option value="installation">Installation</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="repair">Repair</option>
                                    <option value="emergency">Emergency Service</option>
                                    <option value="annual">Annual Maintenance</option>
                                </select>
                            </div>
                            
                            <!-- Problem Description -->
                            <div class="mb-3">
                                <label for="problem_description" class="form-label">Problem Description *</label>
                                <textarea class="form-control" id="problem_description" name="problem_description" rows="4" required placeholder="Please describe the issue or service required..."></textarea>
                            </div>
                            
                            <!-- Priority -->
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority Level</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            
                            <!-- Preferred Date and Time -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="preferred_date" class="form-label">Preferred Date</label>
                                    <input type="date" class="form-control" id="preferred_date" name="preferred_date" min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="preferred_time" class="form-label">Preferred Time</label>
                                    <select class="form-select" id="preferred_time" name="preferred_time">
                                        <option value="">Any time</option>
                                        <option value="09:00">9:00 AM</option>
                                        <option value="10:00">10:00 AM</option>
                                        <option value="11:00">11:00 AM</option>
                                        <option value="12:00">12:00 PM</option>
                                        <option value="14:00">2:00 PM</option>
                                        <option value="15:00">3:00 PM</option>
                                        <option value="16:00">4:00 PM</option>
                                        <option value="17:00">5:00 PM</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Location -->
                            <div class="mb-3">
                                <label class="form-label">Service Location</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" id="location_address" placeholder="Enter address or landmark..." readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <button type="button" class="btn btn-outline-primary w-100" onclick="getCurrentLocation()">
                                            <i class="fas fa-map-marker-alt me-1"></i>Use Current Location
                                        </button>
                                    </div>
                                </div>
                                <input type="hidden" id="latitude" name="latitude">
                                <input type="hidden" id="longitude" name="longitude">
                                <small class="text-muted">Location helps us assign the nearest technician</small>
                            </div>
                            
                            <!-- File Attachments -->
                            <div class="mb-3">
                                <label for="attachments" class="form-label">Attachments (Optional)</label>
                                <input type="file" class="form-control" id="attachments" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx">
                                <small class="text-muted">Upload photos or documents related to the issue (Max 5MB per file)</small>
                            </div>
                            
                            <!-- Contact Information -->
                            <div class="mb-3">
                                <label class="form-label">Contact Information</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($customer['contact_person']); ?>" readonly>
                                        <small class="text-muted">Contact Person</small>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="tel" class="form-control" value="<?php echo htmlspecialchars($customer['phone']); ?>" readonly>
                                        <small class="text-muted">Phone Number</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Terms and Conditions -->
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms_agreement" required>
                                <label class="form-check-label" for="terms_agreement">
                                    I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">terms and conditions</a> *
                                </label>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Service Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Service Terms and Conditions</h6>
                    <ol>
                        <li>Service requests are subject to availability of technicians.</li>
                        <li>Emergency services may incur additional charges.</li>
                        <li>All services are covered by our standard warranty terms.</li>
                        <li>Payment terms are as per the agreed contract.</li>
                        <li>We reserve the right to reschedule services due to unforeseen circumstances.</li>
                        <li>Customer must be present during service delivery.</li>
                        <li>Any additional work beyond the original request will be quoted separately.</li>
                    </ol>
                    <p><strong>By submitting this request, you agree to these terms and conditions.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-check-circle fa-4x text-success"></i>
                    </div>
                    <h4 class="text-success">Service Request Submitted!</h4>
                    <p class="text-muted">Your service request has been received and is being processed.</p>
                    <div class="alert alert-info">
                        <strong>Ticket Number:</strong> <span id="ticketNumber"></span>
                    </div>
                    <p>You will receive a confirmation email shortly. You can track your request status in your dashboard.</p>
                    <div class="d-grid gap-2">
                        <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                        <a href="service-history.php" class="btn btn-outline-primary">View Service History</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../assets/js/service-request.js"></script>
</body>
</html>
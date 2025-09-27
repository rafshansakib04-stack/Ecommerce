<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header('Location: ../index.php');
    exit();
}

$serviceId = $_GET['id'] ?? null;

if (!$serviceId) {
    header('Location: service-tracking.php');
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

// Get service request details
$serviceRequest = $db->fetchOne("
    SELECT sr.*, t.full_name as technician_name, t.phone as technician_phone, t.employee_id
    FROM service_requests sr 
    LEFT JOIN technicians t ON sr.assigned_technician_id = t.id 
    WHERE sr.id = ? AND sr.customer_id = ?
", [$serviceId, $customer['id']]);

if (!$serviceRequest) {
    header('Location: service-tracking.php');
    exit();
}

// Get technician location updates
$locationUpdates = $db->fetchAll("
    SELECT * FROM technician_locations 
    WHERE technician_id = ? AND service_request_id = ?
    ORDER BY created_at DESC 
    LIMIT 50
", [$serviceRequest['assigned_technician_id'], $serviceId]);
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
    <?php
    // Load Google Maps API key from settings
    $gmaps = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'google_maps_api_key'");
    $gmapsKey = $gmaps && !empty($gmaps['setting_value']) ? $gmaps['setting_value'] : '';
    ?>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode($gmapsKey); ?>&libraries=geometry"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tint me-2"></i>Water Purifier ERP
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="service-tracking.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Tracking
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
                            <i class="fas fa-map-marker-alt me-2"></i>Service Tracking - Request #<?php echo $serviceId; ?>
                        </h4>
                        <div class="btn-group">
                            <button class="btn btn-outline-primary" onclick="refreshLocation()">
                                <i class="fas fa-sync me-1"></i>Refresh
                            </button>
                            <button class="btn btn-outline-info" onclick="centerMap()">
                                <i class="fas fa-crosshairs me-1"></i>Center Map
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Service Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-muted">Service Information</h6>
                                <p><strong>Service Type:</strong> <?php echo htmlspecialchars($serviceRequest['service_type']); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo getStatusBadge($serviceRequest['status']); ?>">
                                        <?php echo ucfirst($serviceRequest['status']); ?>
                                    </span>
                                </p>
                                <p><strong>Priority:</strong> 
                                    <span class="badge bg-<?php echo getPriorityBadge($serviceRequest['priority']); ?>">
                                        <?php echo ucfirst($serviceRequest['priority']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Technician Information</h6>
                                <?php if ($serviceRequest['technician_name']): ?>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($serviceRequest['technician_name']); ?></p>
                                <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($serviceRequest['employee_id']); ?></p>
                                <p><strong>Phone:</strong> 
                                    <a href="tel:<?php echo htmlspecialchars($serviceRequest['technician_phone']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($serviceRequest['technician_phone']); ?>
                                    </a>
                                </p>
                                <?php else: ?>
                                <p class="text-muted">Technician not assigned yet</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Map Container -->
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Live Tracking Map</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div id="map" style="height: 500px; width: 100%;"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4">
                                <!-- Status Updates -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">Status Updates</h6>
                                    </div>
                                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                        <div id="statusUpdates">
                                            <!-- Status updates will be loaded here -->
                                        </div>
                                    </div>
                                </div>

                                <!-- Location History -->
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Location History</h6>
                                    </div>
                                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                        <div id="locationHistory">
                                            <?php foreach ($locationUpdates as $update): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                                <div>
                                                    <small class="text-muted"><?php echo formatDateTime($update['created_at']); ?></small>
                                                    <br><strong><?php echo htmlspecialchars($update['address']); ?></strong>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-info"><?php echo $update['speed'] ?? 0; ?> km/h</span>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Estimated Arrival -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Estimated Arrival:</strong>
                                            <span id="estimatedArrival">Calculating...</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Distance:</strong>
                                            <span id="distance">Calculating...</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Last Update:</strong>
                                            <span id="lastUpdate"><?php echo formatDateTime($serviceRequest['updated_at']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        let map;
        let technicianMarker;
        let customerMarker;
        let directionsService;
        let directionsRenderer;
        let watchId;

        // Initialize map
        function initMap() {
            // Default center (you can set this to your business location)
            const defaultCenter = { lat: 28.6139, lng: 77.2090 }; // Delhi coordinates
            
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 13,
                center: defaultCenter
            });

            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({
                draggable: false,
                suppressMarkers: true
            });
            directionsRenderer.setMap(map);

            // Add customer location marker
            customerMarker = new google.maps.Marker({
                position: defaultCenter, // You should get this from customer address
                map: map,
                title: 'Your Location',
                icon: {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="16" cy="16" r="12" fill="#0d6efd" stroke="white" stroke-width="2"/>
                            <text x="16" y="20" text-anchor="middle" fill="white" font-size="12" font-weight="bold">C</text>
                        </svg>
                    `)
                }
            });

            // Start tracking
            startTracking();
        }

        function startTracking() {
            // Get technician's current location
            getTechnicianLocation();
            
            // Update location every 30 seconds
            setInterval(function() {
                getTechnicianLocation();
            }, 30000);
        }

        function getTechnicianLocation() {
            $.ajax({
                url: '../api/customer/get-technician-location.php',
                method: 'GET',
                data: { service_id: <?php echo $serviceId; ?> },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        updateTechnicianLocation(response.data);
                        updateStatusUpdates(response.status_updates);
                    }
                },
                error: function() {
                    console.log('Error getting technician location');
                }
            });
        }

        function updateTechnicianLocation(locationData) {
            const position = {
                lat: parseFloat(locationData.latitude),
                lng: parseFloat(locationData.longitude)
            };

            if (technicianMarker) {
                technicianMarker.setPosition(position);
            } else {
                technicianMarker = new google.maps.Marker({
                    position: position,
                    map: map,
                    title: 'Technician Location',
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="16" cy="16" r="12" fill="#198754" stroke="white" stroke-width="2"/>
                                <text x="16" y="20" text-anchor="middle" fill="white" font-size="12" font-weight="bold">T</text>
                            </svg>
                        `)
                    }
                });
            }

            // Update directions
            if (customerMarker && technicianMarker) {
                calculateRoute();
            }

            // Update estimated arrival
            updateEstimatedArrival(locationData);
        }

        function calculateRoute() {
            const customerPos = customerMarker.getPosition();
            const technicianPos = technicianMarker.getPosition();

            directionsService.route({
                origin: technicianPos,
                destination: customerPos,
                travelMode: google.maps.TravelMode.DRIVING
            }, function(response, status) {
                if (status === 'OK') {
                    directionsRenderer.setDirections(response);
                    
                    const route = response.routes[0];
                    const leg = route.legs[0];
                    
                    $('#distance').text(leg.distance.text);
                    $('#estimatedArrival').text(leg.duration.text);
                }
            });
        }

        function updateEstimatedArrival(locationData) {
            if (locationData.estimated_arrival) {
                $('#estimatedArrival').text(locationData.estimated_arrival);
            }
        }

        function updateStatusUpdates(statusUpdates) {
            let updatesHtml = '';
            
            statusUpdates.forEach(function(update) {
                updatesHtml += `
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                        <div>
                            <small class="text-muted">${formatDateTime(update.created_at)}</small>
                            <br><strong>${update.status}</strong>
                            ${update.notes ? `<br><small>${update.notes}</small>` : ''}
                        </div>
                        <span class="badge bg-${getStatusBadge(update.status)}">${update.status}</span>
                    </div>
                `;
            });
            
            $('#statusUpdates').html(updatesHtml);
        }

        function refreshLocation() {
            getTechnicianLocation();
            showAlert('Location refreshed!', 'info');
        }

        function centerMap() {
            if (technicianMarker && customerMarker) {
                const bounds = new google.maps.LatLngBounds();
                bounds.extend(technicianMarker.getPosition());
                bounds.extend(customerMarker.getPosition());
                map.fitBounds(bounds);
            }
        }

        function getStatusBadge(status) {
            const badges = {
                'pending': 'warning',
                'assigned': 'info',
                'in_progress': 'primary',
                'completed': 'success',
                'cancelled': 'danger'
            };
            return badges[status] || 'secondary';
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-IN', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function showAlert(message, type) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'info' ? 'info-circle' : 'exclamation-triangle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            $('body').append(alertHtml);
            
            setTimeout(function() {
                $('.alert').fadeOut();
            }, 3000);
        }

        // Initialize map when page loads
        $(document).ready(function() {
            if (typeof google !== 'undefined') {
                initMap();
            } else {
                showAlert('Google Maps failed to load. Please check your internet connection.', 'danger');
            }
        });
    </script>
</body>
</html>
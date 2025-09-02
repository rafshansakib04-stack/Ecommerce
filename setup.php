<?php
/**
 * PureFit Bangladesh - System Setup Script
 * Automated setup for production deployment
 */

// Security check - only run if not in production
if (defined('APP_ENV') && APP_ENV === 'production') {
    die('Setup script cannot be run in production environment');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureFit Bangladesh - System Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .setup-container { max-width: 800px; margin: 2rem auto; }
        .setup-card { background: white; border-radius: 1rem; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .setup-header { background: linear-gradient(135deg, #0066cc, #004d99); color: white; padding: 2rem; border-radius: 1rem 1rem 0 0; }
        .step-card { border: 2px solid #e9ecef; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1rem; }
        .step-completed { border-color: #28a745; background: #f8fff9; }
        .step-error { border-color: #dc3545; background: #fff8f8; }
        .step-pending { border-color: #ffc107; background: #fffbf0; }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="setup-header text-center">
                <h1><i class="fas fa-cog me-3"></i>PureFit Bangladesh Setup</h1>
                <p class="mb-0">Complete Water Purifier Business Management System</p>
            </div>
            
            <div class="p-4">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Welcome!</strong> This setup script will help you configure your PureFit business management system.
                </div>

                <!-- Setup Steps -->
                <div id="setup-steps">
                    
                    <!-- Step 1: Database Connection -->
                    <div class="step-card" id="step-database">
                        <div class="d-flex align-items-center">
                            <div class="step-icon me-3">
                                <i class="fas fa-database fa-2x text-primary"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5>Database Connection</h5>
                                <p class="mb-0 text-muted">Testing database connectivity and schema</p>
                            </div>
                            <div class="step-status">
                                <button class="btn btn-primary" onclick="testDatabase()">Test Connection</button>
                            </div>
                        </div>
                        <div class="step-result mt-3" id="database-result"></div>
                    </div>

                    <!-- Step 2: Firebase Configuration -->
                    <div class="step-card" id="step-firebase">
                        <div class="d-flex align-items-center">
                            <div class="step-icon me-3">
                                <i class="fab fa-google fa-2x text-warning"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5>Firebase Integration</h5>
                                <p class="mb-0 text-muted">Verifying Firebase configuration and OAuth setup</p>
                            </div>
                            <div class="step-status">
                                <button class="btn btn-warning" onclick="testFirebase()">Test Firebase</button>
                            </div>
                        </div>
                        <div class="step-result mt-3" id="firebase-result"></div>
                    </div>

                    <!-- Step 3: File Permissions -->
                    <div class="step-card" id="step-permissions">
                        <div class="d-flex align-items-center">
                            <div class="step-icon me-3">
                                <i class="fas fa-folder-open fa-2x text-info"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5>File Permissions</h5>
                                <p class="mb-0 text-muted">Checking upload directories and file permissions</p>
                            </div>
                            <div class="step-status">
                                <button class="btn btn-info" onclick="checkPermissions()">Check Permissions</button>
                            </div>
                        </div>
                        <div class="step-result mt-3" id="permissions-result"></div>
                    </div>

                    <!-- Step 4: Sample Data -->
                    <div class="step-card" id="step-sample-data">
                        <div class="d-flex align-items-center">
                            <div class="step-icon me-3">
                                <i class="fas fa-box fa-2x text-success"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5>Sample Data</h5>
                                <p class="mb-0 text-muted">Load sample products and categories for testing</p>
                            </div>
                            <div class="step-status">
                                <button class="btn btn-success" onclick="loadSampleData()">Load Sample Data</button>
                            </div>
                        </div>
                        <div class="step-result mt-3" id="sample-data-result"></div>
                    </div>

                    <!-- Step 5: Admin Account -->
                    <div class="step-card" id="step-admin">
                        <div class="d-flex align-items-center">
                            <div class="step-icon me-3">
                                <i class="fas fa-user-shield fa-2x text-danger"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5>Admin Account</h5>
                                <p class="mb-0 text-muted">Create your admin account for system management</p>
                            </div>
                            <div class="step-status">
                                <button class="btn btn-danger" onclick="createAdmin()">Create Admin</button>
                            </div>
                        </div>
                        <div class="step-result mt-3" id="admin-result"></div>
                    </div>
                </div>

                <!-- Setup Complete -->
                <div class="setup-complete text-center p-4" id="setup-complete" style="display: none;">
                    <div class="mb-4">
                        <i class="fas fa-check-circle fa-4x text-success"></i>
                    </div>
                    <h3 class="text-success">Setup Complete!</h3>
                    <p class="text-muted mb-4">Your PureFit business management system is ready to use.</p>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <a href="/admin/real-time-dashboard.php" class="btn btn-primary w-100">
                                <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="/" class="btn btn-success w-100">
                                <i class="fas fa-store me-2"></i>Customer Website
                            </a>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mt-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Delete or rename this setup.php file for security.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
        let completedSteps = 0;
        const totalSteps = 5;

        function testDatabase() {
            updateStepStatus('database', 'testing');
            
            $.post('/api/setup/test-database.php')
                .done(function(response) {
                    if (response.success) {
                        updateStepStatus('database', 'completed', 'Database connection successful!');
                        checkSetupCompletion();
                    } else {
                        updateStepStatus('database', 'error', response.message);
                    }
                })
                .fail(function() {
                    updateStepStatus('database', 'error', 'Failed to test database connection');
                });
        }

        function testFirebase() {
            updateStepStatus('firebase', 'testing');
            
            // Test Firebase configuration
            if (typeof firebase !== 'undefined') {
                updateStepStatus('firebase', 'completed', 'Firebase configuration is valid!');
                checkSetupCompletion();
            } else {
                updateStepStatus('firebase', 'error', 'Firebase SDK not loaded');
            }
        }

        function checkPermissions() {
            updateStepStatus('permissions', 'testing');
            
            $.post('/api/setup/check-permissions.php')
                .done(function(response) {
                    if (response.success) {
                        updateStepStatus('permissions', 'completed', 'All directories are writable!');
                        checkSetupCompletion();
                    } else {
                        updateStepStatus('permissions', 'error', response.message);
                    }
                })
                .fail(function() {
                    updateStepStatus('permissions', 'error', 'Failed to check permissions');
                });
        }

        function loadSampleData() {
            updateStepStatus('sample-data', 'testing');
            
            $.post('/api/setup/load-sample-data.php')
                .done(function(response) {
                    if (response.success) {
                        updateStepStatus('sample-data', 'completed', 'Sample data loaded successfully!');
                        checkSetupCompletion();
                    } else {
                        updateStepStatus('sample-data', 'error', response.message);
                    }
                })
                .fail(function() {
                    updateStepStatus('sample-data', 'error', 'Failed to load sample data');
                });
        }

        function createAdmin() {
            const email = prompt('Enter admin email:', 'admin@purefitbd.com');
            const password = prompt('Enter admin password (min 8 characters):', '');
            
            if (!email || !password || password.length < 8) {
                updateStepStatus('admin', 'error', 'Please provide valid email and password (min 8 characters)');
                return;
            }
            
            updateStepStatus('admin', 'testing');
            
            $.post('/api/setup/create-admin.php', {
                email: email,
                password: password
            })
            .done(function(response) {
                if (response.success) {
                    updateStepStatus('admin', 'completed', `Admin account created! Email: ${email}`);
                    checkSetupCompletion();
                } else {
                    updateStepStatus('admin', 'error', response.message);
                }
            })
            .fail(function() {
                updateStepStatus('admin', 'error', 'Failed to create admin account');
            });
        }

        function updateStepStatus(step, status, message = '') {
            const stepCard = $(`#step-${step}`);
            const resultDiv = $(`#${step}-result`);
            const button = stepCard.find('button');
            
            // Remove existing classes
            stepCard.removeClass('step-completed step-error step-pending');
            
            switch (status) {
                case 'testing':
                    stepCard.addClass('step-pending');
                    button.html('<i class="fas fa-spinner fa-spin me-2"></i>Testing...').prop('disabled', true);
                    resultDiv.html('<div class="text-info"><i class="fas fa-clock me-2"></i>Testing in progress...</div>');
                    break;
                    
                case 'completed':
                    stepCard.addClass('step-completed');
                    button.html('<i class="fas fa-check me-2"></i>Completed').removeClass('btn-primary btn-warning btn-info btn-success btn-danger').addClass('btn-success').prop('disabled', true);
                    resultDiv.html(`<div class="text-success"><i class="fas fa-check-circle me-2"></i>${message}</div>`);
                    completedSteps++;
                    break;
                    
                case 'error':
                    stepCard.addClass('step-error');
                    button.html('<i class="fas fa-exclamation-triangle me-2"></i>Retry').prop('disabled', false);
                    resultDiv.html(`<div class="text-danger"><i class="fas fa-times-circle me-2"></i>${message}</div>`);
                    break;
            }
        }

        function checkSetupCompletion() {
            if (completedSteps >= totalSteps) {
                $('#setup-steps').fadeOut(500, function() {
                    $('#setup-complete').fadeIn(500);
                });
            }
        }

        // Auto-run setup checks
        $(document).ready(function() {
            setTimeout(testDatabase, 1000);
            setTimeout(testFirebase, 2000);
            setTimeout(checkPermissions, 3000);
        });
    </script>
</body>
</html>
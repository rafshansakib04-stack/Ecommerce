<?php
session_start();
require_once 'config/database.php';
require_once 'config/firebase.php';
require_once 'includes/functions.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    $user_role = $_SESSION['user_role'];
    switch ($user_role) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'customer':
            header('Location: customer/dashboard.php');
            break;
        case 'technician':
            header('Location: technician/dashboard.php');
            break;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Purifier ERP System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="login-body">
    <div class="container-fluid">
        <div class="row min-vh-100">
            <div class="col-lg-6 d-flex align-items-center justify-content-center bg-primary">
                <div class="text-center text-white">
                    <i class="fas fa-tint fa-5x mb-4"></i>
                    <h1 class="display-4 fw-bold">Water Purifier ERP</h1>
                    <p class="lead">Complete Sales & Service Management System</p>
                    <div class="mt-5">
                        <div class="row text-center">
                            <div class="col-4">
                                <i class="fas fa-user-shield fa-2x mb-2"></i>
                                <h5>Admin Panel</h5>
                            </div>
                            <div class="col-4">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h5>Customer Portal</h5>
                            </div>
                            <div class="col-4">
                                <i class="fas fa-tools fa-2x mb-2"></i>
                                <h5>Technician App</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 d-flex align-items-center justify-content-center">
                <div class="card shadow-lg border-0" style="width: 100%; max-width: 400px;">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-lock fa-3x text-primary mb-3"></i>
                            <h3 class="fw-bold">Login to System</h3>
                            <p class="text-muted">Enter your credentials to access your panel</p>
                        </div>
                        
                        <!-- Alert Messages -->
                        <div id="loginAlert" class="alert d-none" role="alert"></div>
                        
                        <form id="loginForm">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                                <label class="form-check-label" for="rememberMe">Remember Me</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                            
                            <div class="text-center">
                                <a href="#" class="text-decoration-none" id="forgotPassword">Forgot Password?</a>
                            </div>
                        </form>
                        
                        <div id="loginAlert" class="alert d-none mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5>Authenticating...</h5>
                    <p class="text-muted">Please wait while we verify your credentials</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="assets/js/auth.js"></script>
</body>
</html>
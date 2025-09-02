<?php
/**
 * Customer Login Page
 * Multi-provider authentication with Firebase integration
 */

require_once '../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /customer/dashboard.php');
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_POST) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } elseif (!isValidEmail($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            $db = getDB();
            
            // Check login attempts
            $stmt = $db->prepare("SELECT login_attempts, locked_until FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $error = 'Account is temporarily locked. Please try again later.';
            } else {
                // Verify credentials
                $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && verifyPassword($password, $user['password_hash'])) {
                    // Successful login
                    if (loginUser($user['id'], $rememberMe)) {
                        // Redirect based on role
                        $redirectTo = $_GET['redirect'] ?? '/customer/dashboard.php';
                        if ($user['role'] === 'admin' || $user['role'] === 'super_admin') {
                            $redirectTo = '/admin/real-time-dashboard.php';
                        }
                        
                        header("Location: $redirectTo");
                        exit();
                    } else {
                        $error = 'Login failed. Please try again.';
                    }
                } else {
                    // Failed login
                    if ($user) {
                        $attempts = $user['login_attempts'] + 1;
                        $lockUntil = null;
                        
                        if ($attempts >= 5) {
                            $lockUntil = date('Y-m-d H:i:s', time() + (15 * 60)); // Lock for 15 minutes
                        }
                        
                        $stmt = $db->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE email = ?");
                        $stmt->execute([$attempts, $lockUntil, $email]);
                        
                        if ($lockUntil) {
                            $error = 'Too many failed attempts. Account locked for 15 minutes.';
                        } else {
                            $error = 'Invalid email or password.';
                        }
                    } else {
                        $error = 'Invalid email or password.';
                    }
                    
                    // Log failed attempt
                    logActivity(null, 'login_failed', 'user', null, "Failed login attempt for email: $email");
                }
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PureFit Bangladesh</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/main.css" rel="stylesheet">
    <link href="/assets/css/auth.css" rel="stylesheet">
</head>
<body class="auth-body">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="/">
                <img src="/assets/images/logo.png" alt="PureFit Bangladesh" height="40">
                <span class="brand-text">PureFit</span>
            </a>
            
            <div class="navbar-nav ms-auto">
                <a href="/" class="nav-link">
                    <i class="fas fa-home me-1"></i>Back to Home
                </a>
            </div>
        </div>
    </nav>

    <!-- Login Section -->
    <section class="auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">
                    <div class="auth-card animate__animated animate__fadeInUp">
                        <div class="auth-header">
                            <h2 class="auth-title">Welcome Back</h2>
                            <p class="auth-subtitle">Sign in to your PureFit account</p>
                        </div>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger animate__animated animate__shakeX">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success animate__animated animate__fadeIn">
                            <i class="fas fa-check-circle me-2"></i><?= $success ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Social Login -->
                        <div class="social-login mb-4">
                            <button type="button" class="btn btn-google w-100 mb-3" onclick="signInWithGoogle()">
                                <i class="fab fa-google me-2"></i>Continue with Google
                            </button>
                            <button type="button" class="btn btn-facebook w-100 mb-3" onclick="signInWithFacebook()">
                                <i class="fab fa-facebook-f me-2"></i>Continue with Facebook
                            </button>
                        </div>
                        
                        <div class="divider">
                            <span>or sign in with email</span>
                        </div>
                        
                        <!-- Login Form -->
                        <form method="POST" class="auth-form needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                           placeholder="Enter your email" required>
                                    <div class="invalid-feedback">
                                        Please enter a valid email address.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter your password" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <div class="invalid-feedback">
                                        Please enter your password.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                                <label class="form-check-label" for="remember_me">
                                    Remember me for 30 days
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                        </form>
                        
                        <!-- Links -->
                        <div class="auth-links text-center">
                            <a href="/customer/forgot-password.php" class="link-primary">
                                Forgot your password?
                            </a>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-0">Don't have an account? 
                                <a href="/customer/register.php" class="link-primary fw-semibold">Create Account</a>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Quick Login for Demo -->
                    <?php if (APP_DEBUG): ?>
                    <div class="demo-login mt-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Quick Login (Demo)</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <button class="btn btn-outline-primary btn-sm w-100" onclick="quickLogin('admin')">
                                            Admin Login
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-outline-success btn-sm w-100" onclick="quickLogin('customer')">
                                            Customer Login
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Firebase SDK -->
    <?= FirebaseConfig::renderFirebaseScript() ?>
    
    <!-- Custom JavaScript -->
    <script src="/assets/js/main.js"></script>
    
    <script>
        // Social login functions
        async function signInWithGoogle() {
            try {
                showLoadingButton('google');
                
                const user = await window.FirebaseHelpers.signInWithGoogle();
                
                // Send user data to backend
                const response = await fetch('/api/auth/social-login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        provider: 'google',
                        uid: user.uid,
                        email: user.email,
                        name: user.displayName,
                        photo: user.photoURL
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Success', 'Login successful!', 'success');
                    setTimeout(() => {
                        window.location.href = result.redirect || '/customer/dashboard.php';
                    }, 1000);
                } else {
                    showToast('Error', result.message, 'error');
                }
                
            } catch (error) {
                console.error('Google login error:', error);
                showToast('Error', 'Google login failed. Please try again.', 'error');
            } finally {
                hideLoadingButton('google');
            }
        }
        
        async function signInWithFacebook() {
            try {
                showLoadingButton('facebook');
                
                const user = await window.FirebaseHelpers.signInWithFacebook();
                
                // Send user data to backend
                const response = await fetch('/api/auth/social-login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        provider: 'facebook',
                        uid: user.uid,
                        email: user.email,
                        name: user.displayName,
                        photo: user.photoURL
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Success', 'Login successful!', 'success');
                    setTimeout(() => {
                        window.location.href = result.redirect || '/customer/dashboard.php';
                    }, 1000);
                } else {
                    showToast('Error', result.message, 'error');
                }
                
            } catch (error) {
                console.error('Facebook login error:', error);
                showToast('Error', 'Facebook login failed. Please try again.', 'error');
            } finally {
                hideLoadingButton('facebook');
            }
        }
        
        function showLoadingButton(provider) {
            const button = document.querySelector(`.btn-${provider}`);
            if (button) {
                button.disabled = true;
                button.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>Signing in...`;
            }
        }
        
        function hideLoadingButton(provider) {
            const button = document.querySelector(`.btn-${provider}`);
            if (button) {
                button.disabled = false;
                if (provider === 'google') {
                    button.innerHTML = '<i class="fab fa-google me-2"></i>Continue with Google';
                } else if (provider === 'facebook') {
                    button.innerHTML = '<i class="fab fa-facebook-f me-2"></i>Continue with Facebook';
                }
            }
        }
        
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = event.target.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        function quickLogin(role) {
            const email = role === 'admin' ? 'admin@purefitbd.com' : 'customer@purefitbd.com';
            
            document.getElementById('email').value = email;
            document.getElementById('password').value = 'password123';
            
            showToast('Info', `Demo ${role} credentials loaded. Click Sign In to continue.`, 'info');
        }
        
        // Form validation
        document.querySelector('.auth-form').addEventListener('submit', function(e) {
            const form = this;
            
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            } else {
                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
            }
            
            form.classList.add('was-validated');
        });
        
        // Auto-focus email field
        document.getElementById('email').focus();
        
        // Track page view
        if (window.FirebaseHelpers) {
            window.FirebaseHelpers.trackEvent('page_view', {
                page_title: 'Login',
                page_location: window.location.href
            });
        }
    </script>
    
    <style>
        .auth-body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .auth-section {
            padding: 2rem 0;
            min-height: calc(100vh - 76px);
            display: flex;
            align-items: center;
        }
        
        .auth-card {
            background: white;
            border-radius: 1rem;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: none;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .auth-title {
            color: #333;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .auth-subtitle {
            color: #666;
            margin-bottom: 0;
        }
        
        .btn-google {
            background-color: #db4437;
            border-color: #db4437;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-google:hover {
            background-color: #c23321;
            border-color: #c23321;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-facebook {
            background-color: #3b5998;
            border-color: #3b5998;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-facebook:hover {
            background-color: #2d4373;
            border-color: #2d4373;
            color: white;
            transform: translateY(-2px);
        }
        
        .divider {
            position: relative;
            text-align: center;
            margin: 1.5rem 0;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #dee2e6;
        }
        
        .divider span {
            background: white;
            padding: 0 1rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            color: #666;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
        
        .auth-links a {
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .auth-links a:hover {
            text-decoration: underline;
        }
        
        .demo-login {
            opacity: 0.8;
        }
        
        .demo-login .card {
            border: 2px dashed #dee2e6;
            background: rgba(255, 255, 255, 0.9);
        }
        
        @media (max-width: 768px) {
            .auth-card {
                margin: 1rem;
                padding: 2rem;
            }
            
            .auth-section {
                padding: 1rem 0;
            }
        }
    </style>
</body>
</html>
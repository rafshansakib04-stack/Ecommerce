<?php
/**
 * Customer Registration Page
 * User registration with email verification and social login
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
        $firstName = sanitizeInput($_POST['first_name'] ?? '');
        $lastName = sanitizeInput($_POST['last_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $agreeTerms = isset($_POST['agree_terms']);
        
        // Validation
        $errors = [];
        
        if (empty($firstName)) $errors[] = 'First name is required';
        if (empty($lastName)) $errors[] = 'Last name is required';
        if (empty($email)) $errors[] = 'Email is required';
        if (empty($phone)) $errors[] = 'Phone number is required';
        if (empty($password)) $errors[] = 'Password is required';
        if (!$agreeTerms) $errors[] = 'You must agree to the terms and conditions';
        
        if (!isValidEmail($email)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        if (!isValidBDPhone($phone)) {
            $errors[] = 'Please enter a valid Bangladesh phone number';
        }
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }
        
        // Check if email/phone already exists
        if (empty($errors)) {
            $db = getDB();
            
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
            $stmt->execute([$email, $phone]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                $errors[] = 'An account with this email or phone number already exists';
            }
        }
        
        if (empty($errors)) {
            try {
                $db = getDB();
                
                // Create user account
                $userData = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                    'password_hash' => hashPassword($password),
                    'role' => 'customer',
                    'status' => 'pending_verification'
                ];
                
                $userId = insertRecord('users', $userData);
                
                if ($userId) {
                    // Generate email verification token
                    $verificationToken = bin2hex(random_bytes(32));
                    
                    // Save verification token (you might want a separate table for this)
                    updateRecord('users', 
                        ['verification_token' => $verificationToken], 
                        ['id' => $userId]
                    );
                    
                    // Send verification email
                    $verificationLink = APP_URL . "/customer/verify-email.php?token=$verificationToken";
                    $emailBody = "
                        <h2>Welcome to PureFit Bangladesh!</h2>
                        <p>Thank you for registering with us. Please click the link below to verify your email address:</p>
                        <p><a href='$verificationLink' style='background: #0066cc; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px;'>Verify Email Address</a></p>
                        <p>If the button doesn't work, copy and paste this link into your browser:</p>
                        <p>$verificationLink</p>
                        <p>This link will expire in 24 hours.</p>
                        <p>Best regards,<br>PureFit Bangladesh Team</p>
                    ";
                    
                    if (sendEmail($email, 'Verify Your Email - PureFit Bangladesh', $emailBody, true)) {
                        $success = 'Registration successful! Please check your email to verify your account.';
                        
                        // Log activity
                        logActivity($userId, 'user_registered', 'user', $userId, 'New user registered');
                        
                        // Clear form data
                        $_POST = [];
                    } else {
                        $error = 'Registration successful but failed to send verification email. Please contact support.';
                    }
                } else {
                    $error = 'Registration failed. Please try again.';
                }
                
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $error = 'Registration failed. Please try again later.';
            }
        } else {
            $error = implode('<br>', $errors);
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
    <title>Create Account - PureFit Bangladesh</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/main.css" rel="stylesheet">
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

    <!-- Registration Section -->
    <section class="auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="auth-card animate__animated animate__fadeInUp">
                        <div class="auth-header">
                            <h2 class="auth-title">Create Account</h2>
                            <p class="auth-subtitle">Join PureFit Bangladesh for pure water solutions</p>
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
                        
                        <!-- Social Registration -->
                        <div class="social-login mb-4">
                            <button type="button" class="btn btn-google w-100 mb-3" onclick="signUpWithGoogle()">
                                <i class="fab fa-google me-2"></i>Sign up with Google
                            </button>
                            <button type="button" class="btn btn-facebook w-100 mb-3" onclick="signUpWithFacebook()">
                                <i class="fab fa-facebook-f me-2"></i>Sign up with Facebook
                            </button>
                        </div>
                        
                        <div class="divider">
                            <span>or create account with email</span>
                        </div>
                        
                        <!-- Registration Form -->
                        <form method="POST" class="auth-form needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="first_name" class="form-label">First Name *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-user"></i>
                                            </span>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                   value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" 
                                                   placeholder="First name" required>
                                            <div class="invalid-feedback">
                                                Please enter your first name.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="last_name" class="form-label">Last Name *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-user"></i>
                                            </span>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                   value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" 
                                                   placeholder="Last name" required>
                                            <div class="invalid-feedback">
                                                Please enter your last name.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email Address *</label>
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
                                <div class="form-text">We'll send order updates and service notifications to this email.</div>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="phone" class="form-label">Phone Number *</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-phone"></i>
                                    </span>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                                           placeholder="01XXXXXXXXX" required>
                                    <div class="invalid-feedback">
                                        Please enter a valid Bangladesh phone number.
                                    </div>
                                </div>
                                <div class="form-text">We'll use this for order confirmations and service scheduling.</div>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Create a strong password" required minlength="8">
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <div class="invalid-feedback">
                                        Password must be at least 8 characters long.
                                    </div>
                                </div>
                                <div class="password-strength" id="password-strength"></div>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Confirm your password" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <div class="invalid-feedback">
                                        Passwords do not match.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="agree_terms" name="agree_terms" required>
                                <label class="form-check-label" for="agree_terms">
                                    I agree to the <a href="/terms.php" target="_blank">Terms & Conditions</a> 
                                    and <a href="/privacy.php" target="_blank">Privacy Policy</a> *
                                </label>
                                <div class="invalid-feedback">
                                    You must agree to the terms and conditions.
                                </div>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="newsletter_subscribe" name="newsletter_subscribe" checked>
                                <label class="form-check-label" for="newsletter_subscribe">
                                    Subscribe to our newsletter for exclusive offers and water care tips
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-0">Already have an account? 
                                <a href="/customer/login.php" class="link-primary fw-semibold">Sign In</a>
                            </p>
                        </div>
                    </div>
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
        // Social registration functions
        async function signUpWithGoogle() {
            try {
                showLoadingButton('google');
                
                const user = await window.FirebaseHelpers.signInWithGoogle();
                
                // Send user data to backend for registration
                const response = await fetch('/api/auth/social-register.php', {
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
                    showToast('Success', 'Account created successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = '/customer/dashboard.php';
                    }, 1000);
                } else {
                    showToast('Error', result.message, 'error');
                }
                
            } catch (error) {
                console.error('Google registration error:', error);
                showToast('Error', 'Google registration failed. Please try again.', 'error');
            } finally {
                hideLoadingButton('google');
            }
        }
        
        async function signUpWithFacebook() {
            try {
                showLoadingButton('facebook');
                
                const user = await window.FirebaseHelpers.signInWithFacebook();
                
                // Send user data to backend for registration
                const response = await fetch('/api/auth/social-register.php', {
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
                    showToast('Success', 'Account created successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = '/customer/dashboard.php';
                    }, 1000);
                } else {
                    showToast('Error', result.message, 'error');
                }
                
            } catch (error) {
                console.error('Facebook registration error:', error);
                showToast('Error', 'Facebook registration failed. Please try again.', 'error');
            } finally {
                hideLoadingButton('facebook');
            }
        }
        
        function showLoadingButton(provider) {
            const button = document.querySelector(`.btn-${provider}`);
            if (button) {
                button.disabled = true;
                button.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>Creating account...`;
            }
        }
        
        function hideLoadingButton(provider) {
            const button = document.querySelector(`.btn-${provider}`);
            if (button) {
                button.disabled = false;
                if (provider === 'google') {
                    button.innerHTML = '<i class="fab fa-google me-2"></i>Sign up with Google';
                } else if (provider === 'facebook') {
                    button.innerHTML = '<i class="fab fa-facebook-f me-2"></i>Sign up with Facebook';
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
        
        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 8) strength++;
            else feedback.push('At least 8 characters');
            
            if (/[a-z]/.test(password)) strength++;
            else feedback.push('Lowercase letter');
            
            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('Uppercase letter');
            
            if (/[0-9]/.test(password)) strength++;
            else feedback.push('Number');
            
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            else feedback.push('Special character');
            
            return { strength, feedback };
        }
        
        function updatePasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthDiv = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            const { strength, feedback } = checkPasswordStrength(password);
            
            let strengthText = '';
            let strengthClass = '';
            
            switch (strength) {
                case 0:
                case 1:
                    strengthText = 'Very Weak';
                    strengthClass = 'text-danger';
                    break;
                case 2:
                    strengthText = 'Weak';
                    strengthClass = 'text-warning';
                    break;
                case 3:
                    strengthText = 'Fair';
                    strengthClass = 'text-info';
                    break;
                case 4:
                    strengthText = 'Good';
                    strengthClass = 'text-success';
                    break;
                case 5:
                    strengthText = 'Strong';
                    strengthClass = 'text-success fw-bold';
                    break;
            }
            
            const progressWidth = (strength / 5) * 100;
            
            strengthDiv.innerHTML = `
                <div class="password-strength-bar mt-2">
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar ${strengthClass.replace('text-', 'bg-')}" 
                             style="width: ${progressWidth}%"></div>
                    </div>
                    <small class="${strengthClass}">Password strength: ${strengthText}</small>
                </div>
            `;
            
            if (feedback.length > 0 && strength < 4) {
                strengthDiv.innerHTML += `
                    <small class="text-muted d-block mt-1">
                        Missing: ${feedback.join(', ')}
                    </small>
                `;
            }
        }
        
        // Password validation
        function validatePasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const confirmInput = document.getElementById('confirm_password');
            
            if (confirmPassword && password !== confirmPassword) {
                confirmInput.setCustomValidity('Passwords do not match');
            } else {
                confirmInput.setCustomValidity('');
            }
        }
        
        // Phone number formatting
        function formatPhoneNumber(input) {
            let value = input.value.replace(/\D/g, '');
            
            // Add Bangladesh country code if needed
            if (value.length === 11 && value.startsWith('01')) {
                // Keep as is - local format
            } else if (value.length === 13 && value.startsWith('880')) {
                // International format
                value = value.substring(3); // Remove 880 prefix
            }
            
            // Format as 01X-XXXX-XXXX
            if (value.length >= 3) {
                value = value.substring(0, 3) + '-' + value.substring(3);
            }
            if (value.length >= 8) {
                value = value.substring(0, 8) + '-' + value.substring(8, 12);
            }
            
            input.value = value;
        }
        
        // Event listeners
        document.getElementById('password').addEventListener('input', updatePasswordStrength);
        document.getElementById('confirm_password').addEventListener('input', validatePasswordMatch);
        document.getElementById('phone').addEventListener('input', function() {
            formatPhoneNumber(this);
        });
        
        // Form submission
        document.querySelector('.auth-form').addEventListener('submit', function(e) {
            const form = this;
            
            // Custom validation
            validatePasswordMatch();
            
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            } else {
                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
            }
            
            form.classList.add('was-validated');
        });
        
        // Auto-focus first name field
        document.getElementById('first_name').focus();
        
        // Track page view
        if (window.FirebaseHelpers) {
            window.FirebaseHelpers.trackEvent('page_view', {
                page_title: 'Register',
                page_location: window.location.href
            });
        }
    </script>
</body>
</html>
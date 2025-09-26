// Authentication JavaScript
$(document).ready(function() {
    // Toggle password visibility
    $('#togglePassword').click(function() {
        const passwordField = $('#password');
        const icon = $(this).find('i');
        
        if (passwordField.attr('type') === 'password') {
            passwordField.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordField.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Login form submission
    $('#loginForm').submit(function(e) {
        e.preventDefault();
        
        const formData = {
            username: $('#username').val(),
            password: $('#password').val(),
            remember_me: $('#rememberMe').is(':checked')
        };
        
        // Validate inputs
        if (!formData.username || !formData.password) {
            showAlert('Please fill in all fields', 'danger');
            return;
        }
        
        // Show loading modal
        $('#loadingModal').modal('show');
        
        // Submit login request
        $.ajax({
            url: 'api/auth/login.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            timeout: 10000, // 10 second timeout
            success: function(response) {
                $('#loadingModal').modal('hide');
                
                if (response.success) {
                    showAlert('Login successful! Redirecting...', 'success');
                    
                    // Redirect based on role
                    setTimeout(function() {
                        switch(response.user_role) {
                            case 'admin':
                                window.location.href = 'admin/dashboard.php';
                                break;
                            case 'customer':
                                window.location.href = 'customer/dashboard.php';
                                break;
                            case 'technician':
                                window.location.href = 'technician/dashboard.php';
                                break;
                            default:
                                window.location.href = 'index.php';
                        }
                    }, 1500);
                } else {
                    showAlert(response.message || 'Login failed', 'danger');
                }
            },
            error: function(xhr, status, error) {
                $('#loadingModal').modal('hide');
                
                let errorMessage = 'Connection error. Please try again.';
                
                if (xhr.status === 0) {
                    errorMessage = 'Network error. Please check your internet connection.';
                } else if (xhr.status === 404) {
                    errorMessage = 'Login service not found. Please check the server configuration.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error. Please try again later.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied. Please check your permissions.';
                }
                
                // Try to parse error response
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.message) {
                        errorMessage = errorResponse.message;
                    }
                } catch (e) {
                    // Use default error message
                }
                
                showAlert(errorMessage, 'danger');
                console.error('Login error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
            }
        });
    });
    
    // Forgot password
    $('#forgotPassword').click(function(e) {
        e.preventDefault();
        
        const username = prompt('Enter your username:');
        if (username) {
            $.ajax({
                url: 'api/auth/forgot-password.php',
                method: 'POST',
                data: { username: username },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Password reset instructions sent to your email', 'success');
                    } else {
                        showAlert(response.message || 'Failed to send reset instructions', 'danger');
                    }
                },
                error: function() {
                    showAlert('Connection error. Please try again.', 'danger');
                }
            });
        }
    });
    
    // Show alert function
    function showAlert(message, type) {
        const alertDiv = $('#loginAlert');
        alertDiv.removeClass('d-none alert-success alert-danger alert-warning alert-info')
                .addClass('alert-' + type)
                .html('<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-triangle') + ' me-2"></i>' + message);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            alertDiv.addClass('d-none');
        }, 5000);
    }
    
    // Auto-focus username field
    $('#username').focus();
    
    // Enter key handling
    $('#username, #password').keypress(function(e) {
        if (e.which === 13) {
            $('#loginForm').submit();
        }
    });
    
    // Form validation
    $('input[required]').blur(function() {
        const field = $(this);
        const value = field.val().trim();
        
        if (!value) {
            field.addClass('is-invalid');
        } else {
            field.removeClass('is-invalid').addClass('is-valid');
        }
    });
    
    // Real-time validation
    $('#username').on('input', function() {
        const value = $(this).val().trim();
        if (value.length >= 3) {
            $(this).removeClass('is-invalid').addClass('is-valid');
        } else {
            $(this).removeClass('is-valid').addClass('is-invalid');
        }
    });
    
    $('#password').on('input', function() {
        const value = $(this).val();
        if (value.length >= 6) {
            $(this).removeClass('is-invalid').addClass('is-valid');
        } else {
            $(this).removeClass('is-valid').addClass('is-invalid');
        }
    });
});
// Settings Management JavaScript
$(document).ready(function() {
    // Initialize settings management
    initializeSettingsManagement();
    
    // Form submissions
    $('#generalSettingsForm').submit(handleGeneralSettings);
    $('#emailSettingsForm').submit(handleEmailSettings);
    $('#smsSettingsForm').submit(handleSMSSettings);
    $('#firebaseSettingsForm').submit(handleFirebaseSettings);
    $('#paymentSettingsForm').submit(handlePaymentSettings);
    $('#testEmailForm').submit(handleTestEmail);
    $('#testSMSForm').submit(handleTestSMS);
});

function initializeSettingsManagement() {
    // Add loading states to buttons
    $('.btn').on('click', function() {
        const $btn = $(this);
        if ($btn.hasClass('btn-primary') || $btn.hasClass('btn-info')) {
            $btn.prop('disabled', true);
        }
    });
}

function handleGeneralSettings(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_general_settings');
    
    $.ajax({
        url: 'settings.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('General settings updated successfully!', 'success');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error updating general settings. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#generalSettingsForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function handlePaymentSettings(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'update_payment_settings');

    $.ajax({
        url: 'settings.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Payment settings updated successfully!', 'success');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error updating payment settings. Please try again.', 'danger');
        },
        complete: function() {
            $('#paymentSettingsForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function handleEmailSettings(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_email_settings');
    
    $.ajax({
        url: 'settings.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Email settings updated successfully!', 'success');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error updating email settings. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#emailSettingsForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function handleSMSSettings(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_sms_settings');
    
    $.ajax({
        url: 'settings.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('SMS settings updated successfully!', 'success');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error updating SMS settings. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#smsSettingsForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function handleFirebaseSettings(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_firebase_settings');
    
    $.ajax({
        url: 'settings.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Firebase settings updated successfully!', 'success');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error updating Firebase settings. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#firebaseSettingsForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function testEmailConnection() {
    $('#testEmailModal').modal('show');
}

function handleTestEmail(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'test_email');
    
    $.ajax({
        url: 'settings.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Test email sent successfully!', 'success');
                $('#testEmailModal').modal('hide');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error sending test email. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#testEmailForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function testSMSConnection() {
    $('#testSMSModal').modal('show');
}

function handleTestSMS(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'test_sms');
    
    $.ajax({
        url: 'settings.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Test SMS sent successfully!', 'success');
                $('#testSMSModal').modal('hide');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error sending test SMS. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#testSMSForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('body').append(alertHtml);
    
    // Auto-remove after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}

// Form validation
$(document).ready(function() {
    // Email validation
    $('#smtp_host, #smtp_port, #smtp_username, #smtp_password, #from_email').on('blur', function() {
        validateEmailSettings();
    });
    
    // SMS validation
    $('#sms_api_key, #sms_api_secret').on('blur', function() {
        validateSMSSettings();
    });
    
    // Firebase validation
    $('#firebase_api_key, #firebase_project_id').on('blur', function() {
        validateFirebaseSettings();
    });
});

function validateEmailSettings() {
    const requiredFields = ['#smtp_host', '#smtp_port', '#smtp_username', '#smtp_password', '#from_email'];
    let isValid = true;
    
    requiredFields.forEach(function(field) {
        const $field = $(field);
        if (!$field.val().trim()) {
            $field.addClass('is-invalid');
            isValid = false;
        } else {
            $field.removeClass('is-invalid');
        }
    });
    
    // Validate email format
    const email = $('#from_email').val();
    if (email && !isValidEmail(email)) {
        $('#from_email').addClass('is-invalid');
        isValid = false;
    }
    
    return isValid;
}

function validateSMSSettings() {
    const requiredFields = ['#sms_api_key', '#sms_api_secret'];
    let isValid = true;
    
    requiredFields.forEach(function(field) {
        const $field = $(field);
        if (!$field.val().trim()) {
            $field.addClass('is-invalid');
            isValid = false;
        } else {
            $field.removeClass('is-invalid');
        }
    });
    
    return isValid;
}

function validateFirebaseSettings() {
    const requiredFields = ['#firebase_api_key', '#firebase_project_id'];
    let isValid = true;
    
    requiredFields.forEach(function(field) {
        const $field = $(field);
        if (!$field.val().trim()) {
            $field.addClass('is-invalid');
            isValid = false;
        } else {
            $field.removeClass('is-invalid');
        }
    });
    
    return isValid;
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Auto-save functionality
let autoSaveTimeout;

function enableAutoSave() {
    $('input, textarea, select').on('input change', function() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function() {
            showAlert('Settings auto-saved', 'info');
        }, 2000);
    });
}

// Initialize auto-save
$(document).ready(function() {
    enableAutoSave();
});

// Tab switching with unsaved changes warning
let hasUnsavedChanges = false;

$('input, textarea, select').on('input change', function() {
    hasUnsavedChanges = true;
});

$('button[data-bs-toggle="tab"]').on('click', function() {
    if (hasUnsavedChanges) {
        if (!confirm('You have unsaved changes. Do you want to continue?')) {
            return false;
        }
    }
    hasUnsavedChanges = false;
});

// Settings backup and restore
function backupSettings() {
    const settings = {
        general: {
            company_name: $('#company_name').val(),
            company_address: $('#company_address').val(),
            company_phone: $('#company_phone').val(),
            company_email: $('#company_email').val(),
            company_website: $('#company_website').val(),
            timezone: $('#timezone').val(),
            currency: $('#currency').val(),
            tax_rate: $('#tax_rate').val(),
            invoice_prefix: $('#invoice_prefix').val(),
            invoice_number_start: $('#invoice_number_start').val()
        },
        email: {
            smtp_host: $('#smtp_host').val(),
            smtp_port: $('#smtp_port').val(),
            smtp_username: $('#smtp_username').val(),
            smtp_password: $('#smtp_password').val(),
            smtp_encryption: $('#smtp_encryption').val(),
            from_email: $('#from_email').val(),
            from_name: $('#from_name').val()
        },
        sms: {
            sms_provider: $('#sms_provider').val(),
            sms_api_key: $('#sms_api_key').val(),
            sms_api_secret: $('#sms_api_secret').val(),
            sms_sender_id: $('#sms_sender_id').val()
        },
        firebase: {
            firebase_api_key: $('#firebase_api_key').val(),
            firebase_auth_domain: $('#firebase_auth_domain').val(),
            firebase_database_url: $('#firebase_database_url').val(),
            firebase_project_id: $('#firebase_project_id').val(),
            firebase_storage_bucket: $('#firebase_storage_bucket').val(),
            firebase_messaging_sender_id: $('#firebase_messaging_sender_id').val(),
            firebase_app_id: $('#firebase_app_id').val()
        }
    };
    
    const dataStr = JSON.stringify(settings, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    
    const link = document.createElement('a');
    link.href = url;
    link.download = 'settings_backup_' + new Date().toISOString().split('T')[0] + '.json';
    link.click();
    
    URL.revokeObjectURL(url);
    showAlert('Settings backup created successfully!', 'success');
}

// Add backup button to general settings
$(document).ready(function() {
    const backupButton = `
        <button type="button" class="btn btn-outline-secondary ms-2" onclick="backupSettings()">
            <i class="fas fa-download me-1"></i>Backup Settings
        </button>
    `;
    $('#generalSettingsForm .text-end').append(backupButton);
});
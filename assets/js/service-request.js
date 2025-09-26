// Service Request JavaScript
$(document).ready(function() {
    // Initialize service request form
    initializeServiceRequestForm();
    
    // Form submission
    $('#serviceRequestForm').submit(handleServiceRequestSubmission);
    
    // Location services
    initializeLocationServices();
    
    // Form validation
    initializeFormValidation();
});

function initializeServiceRequestForm() {
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    $('#preferred_date').attr('min', today);
    
    // Set default date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    $('#preferred_date').val(tomorrow.toISOString().split('T')[0]);
}

function initializeLocationServices() {
    // Check if geolocation is supported
    if (navigator.geolocation) {
        // Geolocation is supported
        console.log('Geolocation is supported');
    } else {
        console.log('Geolocation is not supported');
        $('#location_address').attr('placeholder', 'Enter your address manually...');
    }
}

function getCurrentLocation() {
    if (!navigator.geolocation) {
        showAlert('Geolocation is not supported by this browser.', 'warning');
        return;
    }
    
    const $btn = $('button[onclick="getCurrentLocation()"]');
    const originalText = $btn.html();
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Getting Location...');
    
    navigator.geolocation.getCurrentPosition(
        function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            $('#latitude').val(lat);
            $('#longitude').val(lng);
            
            // Reverse geocoding to get address
            getAddressFromCoordinates(lat, lng);
            
            $btn.prop('disabled', false).html(originalText);
            showAlert('Location captured successfully!', 'success');
        },
        function(error) {
            let errorMessage = 'Unable to get your location. ';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage += 'Location access denied by user.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage += 'Location information unavailable.';
                    break;
                case error.TIMEOUT:
                    errorMessage += 'Location request timed out.';
                    break;
                default:
                    errorMessage += 'An unknown error occurred.';
                    break;
            }
            
            showAlert(errorMessage, 'warning');
            $btn.prop('disabled', false).html(originalText);
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 60000
        }
    );
}

function getAddressFromCoordinates(lat, lng) {
    // Using a simple reverse geocoding service
    // In production, you might want to use Google Maps API or similar
    fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=en`)
        .then(response => response.json())
        .then(data => {
            if (data.localityInfo && data.localityInfo.administrative) {
                const address = [
                    data.localityInfo.administrative[0].name,
                    data.localityInfo.administrative[1].name,
                    data.localityInfo.administrative[2].name
                ].filter(Boolean).join(', ');
                
                $('#location_address').val(address);
            }
        })
        .catch(error => {
            console.log('Reverse geocoding failed:', error);
            $('#location_address').val('Location captured (coordinates: ' + lat.toFixed(4) + ', ' + lng.toFixed(4) + ')');
        });
}

function initializeFormValidation() {
    // Real-time validation
    $('#service_type').change(function() {
        validateField($(this));
    });
    
    $('#problem_description').on('input', function() {
        validateField($(this));
    });
    
    $('#preferred_date').change(function() {
        validateField($(this));
    });
    
    // File upload validation
    $('#attachments').change(function() {
        validateFileUpload($(this));
    });
}

function validateField($field) {
    const value = $field.val().trim();
    const isRequired = $field.prop('required');
    
    if (isRequired && !value) {
        $field.addClass('is-invalid').removeClass('is-valid');
        return false;
    } else if (value) {
        $field.addClass('is-valid').removeClass('is-invalid');
        return true;
    } else {
        $field.removeClass('is-invalid is-valid');
        return true;
    }
}

function validateFileUpload($input) {
    const files = $input[0].files;
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        if (file.size > maxSize) {
            showAlert(`File "${file.name}" is too large. Maximum size is 5MB.`, 'danger');
            $input.val('');
            return false;
        }
        
        if (!allowedTypes.includes(file.type)) {
            showAlert(`File "${file.name}" has an invalid type. Allowed types: JPG, PNG, GIF, PDF, DOC, DOCX`, 'danger');
            $input.val('');
            return false;
        }
    }
    
    return true;
}

function handleServiceRequestSubmission(e) {
    e.preventDefault();
    
    // Validate form
    if (!validateForm()) {
        showAlert('Please fill in all required fields correctly.', 'danger');
        return;
    }
    
    // Validate terms agreement
    if (!$('#terms_agreement').is(':checked')) {
        showAlert('Please agree to the terms and conditions.', 'danger');
        return;
    }
    
    // Show loading state
    const $submitBtn = $('#serviceRequestForm button[type="submit"]');
    const originalText = $submitBtn.html();
    $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Submitting...');
    
    // Submit form
    const formData = new FormData($('#serviceRequestForm')[0]);
    
    $.ajax({
        url: 'service-request.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Show success modal
                $('#ticketNumber').text(response.ticket_number);
                $('#successModal').modal('show');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Submission error:', error);
            showAlert('Error submitting service request. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $submitBtn.prop('disabled', false).html(originalText);
        }
    });
}

function validateForm() {
    let isValid = true;
    
    // Validate required fields
    const requiredFields = ['#service_type', '#problem_description'];
    
    requiredFields.forEach(function(fieldSelector) {
        if (!validateField($(fieldSelector))) {
            isValid = false;
        }
    });
    
    // Validate file upload if files are selected
    if ($('#attachments')[0].files.length > 0) {
        if (!validateFileUpload($('#attachments'))) {
            isValid = false;
        }
    }
    
    return isValid;
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

// Auto-save form data to localStorage
function autoSaveForm() {
    const formData = {
        service_type: $('#service_type').val(),
        problem_description: $('#problem_description').val(),
        priority: $('#priority').val(),
        preferred_date: $('#preferred_date').val(),
        preferred_time: $('#preferred_time').val(),
        latitude: $('#latitude').val(),
        longitude: $('#longitude').val()
    };
    
    localStorage.setItem('serviceRequestDraft', JSON.stringify(formData));
}

function loadDraftForm() {
    const draft = localStorage.getItem('serviceRequestDraft');
    if (draft) {
        try {
            const formData = JSON.parse(draft);
            
            if (formData.service_type) $('#service_type').val(formData.service_type);
            if (formData.problem_description) $('#problem_description').val(formData.problem_description);
            if (formData.priority) $('#priority').val(formData.priority);
            if (formData.preferred_date) $('#preferred_date').val(formData.preferred_date);
            if (formData.preferred_time) $('#preferred_time').val(formData.preferred_time);
            if (formData.latitude) $('#latitude').val(formData.latitude);
            if (formData.longitude) $('#longitude').val(formData.longitude);
            
            if (formData.latitude && formData.longitude) {
                $('#location_address').val('Location captured (coordinates: ' + parseFloat(formData.latitude).toFixed(4) + ', ' + parseFloat(formData.longitude).toFixed(4) + ')');
            }
        } catch (e) {
            console.log('Error loading draft:', e);
        }
    }
}

// Auto-save on form changes
$(document).ready(function() {
    // Load draft on page load
    loadDraftForm();
    
    // Auto-save on form changes
    $('#serviceRequestForm input, #serviceRequestForm select, #serviceRequestForm textarea').on('change input', function() {
        autoSaveForm();
    });
    
    // Clear draft on successful submission
    $('#successModal').on('shown.bs.modal', function() {
        localStorage.removeItem('serviceRequestDraft');
    });
});
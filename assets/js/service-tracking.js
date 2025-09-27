// Service Tracking JavaScript
$(document).ready(function() {
    // Initialize service tracking
    initializeServiceTracking();
    
    // Form submissions
    $('#rateServiceForm').submit(handleRateService);
    
    // Initialize real-time updates
    initializeRealTimeUpdates();
});

function initializeServiceTracking() {
    // Add loading states to buttons
    $('.btn').on('click', function() {
        const $btn = $(this);
        if ($btn.hasClass('btn-warning')) {
            $btn.prop('disabled', true);
        }
    });
}

function initializeRealTimeUpdates() {
    // Check for service updates every 30 seconds
    setInterval(function() {
        checkForUpdates();
    }, 30000);
}

function checkForUpdates() {
    // Get current service request IDs
    const serviceIds = [];
    $('button[onclick*="viewServiceRequest"]').each(function() {
        const onclick = $(this).attr('onclick');
        const match = onclick.match(/viewServiceRequest\((\d+)\)/);
        if (match) {
            serviceIds.push(match[1]);
        }
    });
    
    if (serviceIds.length > 0) {
        $.ajax({
            url: '../api/customer/check-updates.php',
            method: 'POST',
            data: {
                service_ids: serviceIds
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.has_updates) {
                    showAlert('Service status updated!', 'info');
                    setTimeout(() => location.reload(), 2000);
                }
            },
            error: function() {
                // Silently fail for background updates
            }
        });
    }
}

function viewServiceRequest(serviceId) {
    $.ajax({
        url: '../api/customer/get-service-request.php',
        method: 'GET',
        data: { id: serviceId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayServiceDetails(response.data);
                $('#serviceDetailsModal').modal('show');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading service details.', 'danger');
        }
    });
}

function displayServiceDetails(service) {
    const statusBadge = getStatusBadge(service.status);
    const statusColor = getStatusColor(service.status);
    
    const detailsHtml = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Service Information</h6>
                <p><strong>Request #:</strong> #${service.id}</p>
                <p><strong>Service Type:</strong> ${service.service_type}</p>
                <p><strong>Priority:</strong> <span class="badge bg-${getPriorityBadge(service.priority)}">${service.priority}</span></p>
                <p><strong>Status:</strong> <span class="badge bg-${statusBadge}">${service.status}</span></p>
                <p><strong>Created:</strong> ${formatDateTime(service.created_at)}</p>
                ${service.scheduled_date ? `<p><strong>Scheduled:</strong> ${formatDate(service.scheduled_date)}</p>` : ''}
                ${service.completed_at ? `<p><strong>Completed:</strong> ${formatDateTime(service.completed_at)}</p>` : ''}
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Problem Description</h6>
                <div class="bg-light p-3 rounded">${service.problem_description || 'No description provided'}</div>
                
                ${service.technician_name ? `
                <h6 class="text-muted mt-3">Assigned Technician</h6>
                <p><strong>Name:</strong> ${service.technician_name}</p>
                ${service.technician_phone ? `<p><strong>Phone:</strong> <a href="tel:${service.technician_phone}">${service.technician_phone}</a></p>` : ''}
                ` : ''}
            </div>
        </div>
        
        ${service.attachments && service.attachments.length > 0 ? `
        <hr>
        <h6 class="text-muted">Attachments</h6>
        <div class="row">
            ${service.attachments.map(attachment => `
            <div class="col-md-4 mb-2">
                <div class="card">
                    <div class="card-body p-2">
                        <i class="fas fa-file me-2"></i>
                        <a href="${attachment.file_path}" target="_blank" class="text-decoration-none">
                            ${attachment.original_filename}
                        </a>
                    </div>
                </div>
            </div>
            `).join('')}
        </div>
        ` : ''}
        
        ${service.customer_rating ? `
        <hr>
        <h6 class="text-muted">Your Rating</h6>
        <div class="d-flex align-items-center">
            ${getStarRating(service.customer_rating)}
            ${service.customer_feedback ? `<span class="ms-3">"${service.customer_feedback}"</span>` : ''}
        </div>
        ` : ''}
        
        ${service.status === 'in_progress' ? `
        <hr>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Your service is currently in progress. You can track the technician's location in real-time.
        </div>
        ` : ''}
    `;
    
    $('#serviceDetailsContent').html(detailsHtml);
}

function trackService(serviceId) {
    // Open tracking page in new window
    window.open(`service-tracking-map.php?id=${serviceId}`, '_blank');
}

function rateService(serviceId) {
    $('#rate_service_id').val(serviceId);
    $('#rateServiceModal').modal('show');
}

function handleRateService(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    $.ajax({
        url: '../api/customer/rate-service.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Thank you for your rating!', 'success');
                $('#rateServiceModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error submitting rating. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#rateServiceForm button[type="submit"]').prop('disabled', false);
        }
    });
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

function getStatusColor(status) {
    const colors = {
        'pending': '#ffc107',
        'assigned': '#0dcaf0',
        'in_progress': '#0d6efd',
        'completed': '#198754',
        'cancelled': '#dc3545'
    };
    return colors[status] || '#6c757d';
}

function getPriorityBadge(priority) {
    const badges = {
        'low': 'success',
        'medium': 'warning',
        'high': 'danger',
        'urgent': 'danger'
    };
    return badges[priority] || 'secondary';
}

function getStarRating(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            stars += '<i class="fas fa-star text-warning"></i>';
        } else {
            stars += '<i class="far fa-star text-muted"></i>';
        }
    }
    return stars;
}

function formatDate(dateString) {
    if (!dateString) return 'Not specified';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatDateTime(dateString) {
    if (!dateString) return 'Not specified';
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
    
    // Auto-remove after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}

// Rating input styling
$(document).ready(function() {
    $('.rating-input input[type="radio"]').change(function() {
        const rating = $(this).val();
        const labels = $('.rating-input label');
        
        labels.removeClass('text-warning').addClass('text-muted');
        
        for (let i = 1; i <= rating; i++) {
            $(`#star${i}`).next('label').removeClass('text-muted').addClass('text-warning');
        }
    });
});

// Auto-refresh for in-progress services
function checkInProgressServices() {
    const inProgressServices = $('tr').filter(function() {
        return $(this).find('.badge').text().toLowerCase().includes('in progress');
    });
    
    if (inProgressServices.length > 0) {
        // Refresh the page every 2 minutes for in-progress services
        setTimeout(() => {
            location.reload();
        }, 120000);
    }
}

// Initialize auto-refresh
$(document).ready(function() {
    checkInProgressServices();
});
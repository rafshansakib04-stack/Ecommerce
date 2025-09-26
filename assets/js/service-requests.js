// Service Requests Management JavaScript
$(document).ready(function() {
    // Initialize service requests management
    initializeServiceRequests();
    
    // Filter functionality
    $('#statusFilter, #priorityFilter, #technicianFilter').change(function() {
        applyFilters();
    });
    
    // Search functionality
    $('#searchInput').on('input', function() {
        debounceSearch($(this).val());
    });
    
    // Form submissions
    $('#assignTechnicianForm').submit(handleAssignTechnician);
    $('#updateStatusForm').submit(handleUpdateStatus);
});

function initializeServiceRequests() {
    // Add loading states to buttons
    $('.btn').on('click', function() {
        const $btn = $(this);
        if ($btn.hasClass('btn-success') || $btn.hasClass('btn-warning')) {
            $btn.prop('disabled', true);
        }
    });
}

function debounceSearch(searchTerm) {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(function() {
        applyFilters();
    }, 500);
}

function applyFilters() {
    const status = $('#statusFilter').val();
    const priority = $('#priorityFilter').val();
    const technician = $('#technicianFilter').val();
    const search = $('#searchInput').val();
    
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (priority) params.set('priority', priority);
    if (technician) params.set('technician', technician);
    if (search) params.set('search', search);
    
    window.location.href = 'service-requests.php?' + params.toString();
}

function assignTechnician(serviceId) {
    $('#assign_service_id').val(serviceId);
    $('#assignTechnicianModal').modal('show');
}

function handleAssignTechnician(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'assign_technician');
    
    $.ajax({
        url: 'service-requests.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Technician assigned successfully!', 'success');
                $('#assignTechnicianModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error assigning technician. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#assignTechnicianForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function updateStatus(serviceId) {
    $('#update_service_id').val(serviceId);
    $('#updateStatusModal').modal('show');
}

function handleUpdateStatus(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_status');
    
    $.ajax({
        url: 'service-requests.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Status updated successfully!', 'success');
                $('#updateStatusModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error updating status. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#updateStatusForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function viewService(serviceId) {
    $.ajax({
        url: '../api/services/get.php',
        method: 'GET',
        data: { id: serviceId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayServiceDetails(response.data);
                $('#viewServiceModal').modal('show');
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
    const detailsHtml = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Service Information</h6>
                <p><strong>Ticket Number:</strong> ${service.ticket_number}</p>
                <p><strong>Service Type:</strong> <span class="badge bg-info">${service.service_type}</span></p>
                <p><strong>Priority:</strong> <span class="badge bg-${getPriorityBadge(service.priority)}">${service.priority}</span></p>
                <p><strong>Status:</strong> <span class="badge bg-${getStatusBadge(service.status)}">${service.status}</span></p>
                <p><strong>Problem Description:</strong></p>
                <div class="bg-light p-3 rounded">${service.problem_description || 'No description provided'}</div>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Customer Information</h6>
                <p><strong>Customer:</strong> ${service.company_name || service.contact_person}</p>
                <p><strong>Contact Person:</strong> ${service.contact_person}</p>
                <p><strong>Phone:</strong> ${service.customer_phone}</p>
                <p><strong>Preferred Date:</strong> ${service.preferred_date || 'Not specified'}</p>
                <p><strong>Preferred Time:</strong> ${service.preferred_time || 'Not specified'}</p>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Technician Information</h6>
                ${service.technician_name ? `
                    <p><strong>Assigned Technician:</strong> ${service.technician_name}</p>
                    <p><strong>Technician Phone:</strong> ${service.technician_phone}</p>
                ` : '<p class="text-muted">No technician assigned</p>'}
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Timing Information</h6>
                <p><strong>Created:</strong> ${formatDateTime(service.created_at)}</p>
                <p><strong>Last Updated:</strong> ${formatDateTime(service.updated_at)}</p>
                ${service.actual_start_time ? `<p><strong>Started:</strong> ${formatDateTime(service.actual_start_time)}</p>` : ''}
                ${service.actual_end_time ? `<p><strong>Completed:</strong> ${formatDateTime(service.actual_end_time)}</p>` : ''}
            </div>
        </div>
        ${service.customer_rating ? `
        <hr>
        <div class="row">
            <div class="col-12">
                <h6 class="text-muted">Customer Feedback</h6>
                <p><strong>Rating:</strong> ${getStarRating(service.customer_rating)}</p>
                <p><strong>Feedback:</strong> ${service.customer_feedback || 'No feedback provided'}</p>
            </div>
        </div>
        ` : ''}
    `;
    
    $('#serviceDetails').html(detailsHtml);
}

function getPriorityBadge(priority) {
    const badges = {
        'low': 'secondary',
        'normal': 'info',
        'high': 'warning',
        'urgent': 'danger'
    };
    return badges[priority] || 'secondary';
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

function getStarRating(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            stars += '<i class="fas fa-star text-warning"></i>';
        } else {
            stars += '<i class="far fa-star text-muted"></i>';
        }
    }
    return stars + ` (${rating}/5)`;
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
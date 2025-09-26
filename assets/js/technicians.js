// Technician Management JavaScript
$(document).ready(function() {
    // Initialize technician management
    initializeTechnicianManagement();
    
    // Search functionality
    $('#searchInput').on('input', function() {
        const searchTerm = $(this).val();
        if (searchTerm.length >= 3 || searchTerm.length === 0) {
            debounceSearch(searchTerm);
        }
    });
    
    // Filter functionality
    $('#statusFilter').change(function() {
        applyFilters();
    });
    
    // Form submissions
    $('#addTechnicianForm').submit(handleAddTechnician);
    $('#updateStatusForm').submit(handleUpdateStatus);
});

function initializeTechnicianManagement() {
    // Add loading states to buttons
    $('.btn').on('click', function() {
        const $btn = $(this);
        if ($btn.hasClass('btn-primary') || $btn.hasClass('btn-warning')) {
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
    const search = $('#searchInput').val();
    const status = $('#statusFilter').val();
    
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (status) params.set('status', status);
    
    window.location.href = 'technicians.php?' + params.toString();
}

function handleAddTechnician(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_technician');
    
    $.ajax({
        url: 'technicians.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Technician added successfully!', 'success');
                
                // Show credentials if provided
                if (response.data) {
                    showCredentialsModal(response.data);
                }
                
                $('#addTechnicianModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error adding technician. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#addTechnicianForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function handleUpdateStatus(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_status');
    
    $.ajax({
        url: 'technicians.php',
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

function viewTechnician(technicianId) {
    $.ajax({
        url: '../api/technicians/get.php',
        method: 'GET',
        data: { id: technicianId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayTechnicianDetails(response.data);
                $('#viewTechnicianModal').modal('show');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading technician details.', 'danger');
        }
    });
}

function displayTechnicianDetails(technician) {
    const detailsHtml = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Personal Information</h6>
                <p><strong>Name:</strong> ${technician.full_name}</p>
                <p><strong>Employee ID:</strong> <code>${technician.employee_id}</code></p>
                <p><strong>Email:</strong> ${technician.email}</p>
                <p><strong>Phone:</strong> ${technician.phone}</p>
                <p><strong>Emergency Contact:</strong> ${technician.emergency_contact || 'Not provided'}</p>
                <p><strong>Address:</strong> ${technician.address || 'Not provided'}</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Professional Information</h6>
                <p><strong>Skills:</strong></p>
                <div class="bg-light p-3 rounded">${technician.skills || 'No skills specified'}</div>
                <p><strong>Service Areas:</strong></p>
                <div class="bg-light p-3 rounded">${technician.service_areas || 'No areas specified'}</div>
                <p><strong>Joining Date:</strong> ${formatDate(technician.joining_date)}</p>
                <p><strong>Status:</strong> <span class="badge bg-${getStatusBadge(technician.status)}">${technician.status}</span></p>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Performance</h6>
                <p><strong>Total Services:</strong> ${technician.total_services}</p>
                <p><strong>Completed Services:</strong> ${technician.completed_services}</p>
                <p><strong>Average Rating:</strong> ${technician.avg_rating ? getStarRating(technician.avg_rating) : 'No rating'}</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Financial</h6>
                <p><strong>Salary:</strong> ₹${technician.salary || 'Not set'}</p>
                <p><strong>Commission Rate:</strong> ${technician.commission_rate || 0}%</p>
            </div>
        </div>
    `;
    
    $('#technicianDetails').html(detailsHtml);
}

function editTechnician(technicianId) {
    $.ajax({
        url: '../api/technicians/get.php',
        method: 'GET',
        data: { id: technicianId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                populateEditForm(response.data);
                $('#editTechnicianModal').modal('show');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading technician details.', 'danger');
        }
    });
}

function populateEditForm(technician) {
    $('#edit_technician_id').val(technician.id);
    $('#edit_employee_id').val(technician.employee_id);
    $('#edit_full_name').val(technician.full_name);
    $('#edit_email').val(technician.email);
    $('#edit_phone').val(technician.phone);
    $('#edit_skills').val(technician.skills);
    $('#edit_service_areas').val(technician.service_areas);
    $('#edit_emergency_contact').val(technician.emergency_contact);
    $('#edit_address').val(technician.address);
    $('#edit_joining_date').val(technician.joining_date);
    $('#edit_salary').val(technician.salary);
    $('#edit_commission_rate').val(technician.commission_rate);
}

function updateStatus(technicianId) {
    $('#status_technician_id').val(technicianId);
    $('#updateStatusModal').modal('show');
}

function deleteTechnician(technicianId) {
    if (confirm('Are you sure you want to delete this technician? This action cannot be undone.')) {
        $.ajax({
            url: 'technicians.php',
            method: 'POST',
            data: {
                action: 'delete_technician',
                id: technicianId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('Technician deleted successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error deleting technician. Please try again.', 'danger');
            }
        });
    }
}

function showCredentialsModal(data) {
    const modalHtml = `
        <div class="modal fade" id="credentialsModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-key me-2"></i>Technician Credentials
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Technician credentials have been generated and sent.
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Username:</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="${data.username}" readonly>
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('${data.username}')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password:</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="${data.password}" readonly>
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('${data.password}')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-envelope me-1"></i>Email sent: ${data.email_sent ? 'Yes' : 'No'}<br>
                                <i class="fas fa-sms me-1"></i>SMS sent: ${data.sms_sent ? 'Yes' : 'No'}
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    $('#credentialsModal').remove();
    
    // Add new modal
    $('body').append(modalHtml);
    
    // Show modal
    $('#credentialsModal').modal('show');
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showAlert('Copied to clipboard!', 'success');
    }, function() {
        showAlert('Failed to copy to clipboard', 'danger');
    });
}

function exportTechnicians() {
    const search = $('#searchInput').val();
    const status = $('#statusFilter').val();
    const exportUrl = `../api/technicians/export.php?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`;
    window.open(exportUrl, '_blank');
}

function getStatusBadge(status) {
    const badges = {
        'active': 'success',
        'inactive': 'secondary',
        'on_leave': 'warning',
        'pending': 'warning',
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

function formatDate(dateString) {
    if (!dateString) return 'Not specified';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
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

// Auto-generate Employee ID
$('#full_name').on('input', function() {
    const name = $(this).val();
    if (name && !$('#employee_id').val()) {
        const employeeId = 'TECH' + name.replace(/\s+/g, '').toUpperCase().substring(0, 6) + Math.random().toString(36).substring(2, 4).toUpperCase();
        $('#employee_id').val(employeeId);
    }
});
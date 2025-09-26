// Customer Management JavaScript
$(document).ready(function() {
    // Initialize customer management
    initializeCustomerManagement();
    
    // Search functionality
    $('#searchInput').on('input', function() {
        const searchTerm = $(this).val();
        if (searchTerm.length >= 3 || searchTerm.length === 0) {
            debounceSearch(searchTerm);
        }
    });
    
    // Filter functionality
    $('#segmentFilter, #creditFilter, #statusFilter').on('change', function() {
        applyFilters();
    });
    
    // Form submissions
    $('#addCustomerForm').submit(handleAddCustomer);
    $('#editCustomerForm').submit(handleEditCustomer);
});

function initializeCustomerManagement() {
    // Add loading states to buttons
    $('.btn').on('click', function() {
        const $btn = $(this);
        if ($btn.hasClass('btn-primary') || $btn.hasClass('btn-warning')) {
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Processing...');
        }
    });
}

function debounceSearch(searchTerm) {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(function() {
        performSearch(searchTerm);
    }, 500);
}

function performSearch(searchTerm) {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('search', searchTerm);
    currentUrl.searchParams.delete('page'); // Reset to first page
    window.location.href = currentUrl.toString();
}

function applyFilters() {
    const search = $('#searchInput').val();
    const segment = $('#segmentFilter').val();
    const credit = $('#creditFilter').val();
    const status = $('#statusFilter').val();
    
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (segment) params.set('segment', segment);
    if (credit) params.set('credit', credit);
    if (status) params.set('status', status);
    
    window.location.href = 'customers.php?' + params.toString();
}

function exportCustomers() {
    const search = $('#searchInput').val();
    const segment = $('#segmentFilter').val();
    const credit = $('#creditFilter').val();
    const status = $('#statusFilter').val();
    
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (segment) params.set('segment', segment);
    if (credit) params.set('credit', credit);
    if (status) params.set('status', status);
    params.set('export', '1');
    
    window.open('customers.php?' + params.toString(), '_blank');
}

function exportAnalytics() {
    window.open('../api/customers/analytics-export.php', '_blank');
}

function handleAddCustomer(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_customer');
    
    $.ajax({
        url: 'customers.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Customer added successfully!', 'success');
                
                // Show credentials if provided
                if (response.data) {
                    showCredentialsModal(response.data);
                }
                
                // Close modal and reload page
                $('#addCustomerModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error adding customer. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#addCustomerForm button[type="submit"]').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Add Customer');
        }
    });
}

function handleEditCustomer(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_customer');
    
    $.ajax({
        url: 'customers.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Customer updated successfully!', 'success');
                $('#editCustomerModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error updating customer. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#editCustomerForm button[type="submit"]').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Update Customer');
        }
    });
}

function viewCustomer(customerId) {
    $.ajax({
        url: '../api/customers/get.php',
        method: 'GET',
        data: { id: customerId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayCustomerDetails(response.data);
                $('#viewCustomerModal').modal('show');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading customer details.', 'danger');
        }
    });
}

function displayCustomerDetails(customer) {
    const detailsHtml = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Company Information</h6>
                <p><strong>Company:</strong> ${customer.company_name || 'N/A'}</p>
                <p><strong>Contact Person:</strong> ${customer.contact_person}</p>
                <p><strong>Address:</strong> ${customer.address || 'N/A'}</p>
                <p><strong>City:</strong> ${customer.city || 'N/A'}</p>
                <p><strong>State:</strong> ${customer.state || 'N/A'}</p>
                <p><strong>Pincode:</strong> ${customer.pincode || 'N/A'}</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Contact Information</h6>
                <p><strong>Email:</strong> ${customer.email}</p>
                <p><strong>Phone:</strong> ${customer.phone}</p>
                <p><strong>Username:</strong> ${customer.username}</p>
                <p><strong>Status:</strong> <span class="badge bg-${getStatusBadge(customer.user_status)}">${customer.user_status}</span></p>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Business Information</h6>
                <p><strong>GST Number:</strong> ${customer.gst_number || 'N/A'}</p>
                <p><strong>PAN Number:</strong> ${customer.pan_number || 'N/A'}</p>
                <p><strong>Credit Limit:</strong> ₹${customer.credit_limit}</p>
                <p><strong>Payment Terms:</strong> ${customer.payment_terms} days</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Statistics</h6>
                <p><strong>Total Services:</strong> ${customer.total_services}</p>
                <p><strong>Total Paid:</strong> ₹${customer.total_paid}</p>
                <p><strong>Member Since:</strong> ${formatDate(customer.created_at)}</p>
            </div>
        </div>
    `;
    
    $('#customerDetails').html(detailsHtml);
}

function editCustomer(customerId) {
    $.ajax({
        url: '../api/customers/get.php',
        method: 'GET',
        data: { id: customerId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                populateEditForm(response.data);
                $('#editCustomerModal').modal('show');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading customer details.', 'danger');
        }
    });
}

function populateEditForm(customer) {
    $('#edit_customer_id').val(customer.id);
    $('#edit_company_name').val(customer.company_name || '');
    $('#edit_contact_person').val(customer.contact_person);
    $('#edit_email').val(customer.email);
    $('#edit_phone').val(customer.phone);
    $('#edit_address').val(customer.address || '');
    $('#edit_city').val(customer.city || '');
    $('#edit_state').val(customer.state || '');
    $('#edit_pincode').val(customer.pincode || '');
    $('#edit_gst_number').val(customer.gst_number || '');
    $('#edit_pan_number').val(customer.pan_number || '');
    $('#edit_credit_limit').val(customer.credit_limit);
    $('#edit_payment_terms').val(customer.payment_terms);
}

function deleteCustomer(customerId) {
    if (confirm('Are you sure you want to delete this customer? This action cannot be undone.')) {
        $.ajax({
            url: 'customers.php',
            method: 'POST',
            data: {
                action: 'delete_customer',
                id: customerId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('Customer deleted successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error deleting customer. Please try again.', 'danger');
            }
        });
    }
}

function sendCredentials(customerId) {
    if (confirm('Send new login credentials to this customer?')) {
        $.ajax({
            url: 'customers.php',
            method: 'POST',
            data: {
                action: 'send_credentials',
                id: customerId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('Credentials sent successfully!', 'success');
                    
                    // Show credentials if provided
                    if (response.data) {
                        showCredentialsModal(response.data);
                    }
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error sending credentials. Please try again.', 'danger');
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
                            <i class="fas fa-key me-2"></i>Customer Credentials
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Customer credentials have been generated and sent.
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

function exportCustomers() {
    const searchTerm = $('#searchInput').val();
    const exportUrl = `../api/customers/export.php?search=${encodeURIComponent(searchTerm)}`;
    window.open(exportUrl, '_blank');
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

function getStatusBadge(status) {
    const badges = {
        'active': 'success',
        'inactive': 'secondary',
        'pending': 'warning',
        'completed': 'success',
        'cancelled': 'danger'
    };
    return badges[status] || 'secondary';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}
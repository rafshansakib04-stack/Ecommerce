// Billing Management JavaScript
$(document).ready(function() {
    // Initialize billing management
    initializeBillingManagement();
    
    // Filter functionality
    $('#statusFilter').change(function() {
        applyFilters();
    });
    
    // Search functionality
    $('#searchInput').on('input', function() {
        const searchTerm = $(this).val();
        if (searchTerm.length >= 3 || searchTerm.length === 0) {
            debounceSearch(searchTerm);
        }
    });
    
    // Form submissions
    $('#createBillForm').submit(handleCreateBill);
    $('#markPaidForm').submit(handleMarkPaid);
    
    // Initialize calculations
    calculateTotal();
});

function initializeBillingManagement() {
    // Add loading states to buttons
    $('.btn').on('click', function() {
        const $btn = $(this);
        if ($btn.hasClass('btn-primary') || $btn.hasClass('btn-success')) {
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
    const search = $('#searchInput').val();
    
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (search) params.set('search', search);
    
    window.location.href = 'billing.php?' + params.toString();
}

function calculateTotal() {
    const laborCharges = parseFloat($('#labor_charges').val()) || 0;
    const partsCost = parseFloat($('#parts_cost').val()) || 0;
    const travelCharges = parseFloat($('#travel_charges').val()) || 0;
    const otherCharges = parseFloat($('#other_charges').val()) || 0;
    
    const subtotal = laborCharges + partsCost + travelCharges + otherCharges;
    const tax = subtotal * 0.18; // 18% tax
    const total = subtotal + tax;
    
    $('#billSubtotal').text('₹' + subtotal.toFixed(2));
    $('#billTax').text('₹' + tax.toFixed(2));
    $('#billTotal').text('₹' + total.toFixed(2));
    
    // Update hidden fields
    $('input[name="subtotal"]').val(subtotal);
    $('input[name="tax_amount"]').val(tax);
    $('input[name="total_amount"]').val(total);
}

function handleCreateBill(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'create_bill');
    
    // Calculate totals
    const subtotal = parseFloat($('#billSubtotal').text().replace('₹', ''));
    const tax = parseFloat($('#billTax').text().replace('₹', ''));
    const total = parseFloat($('#billTotal').text().replace('₹', ''));
    
    formData.append('subtotal', subtotal);
    formData.append('tax_amount', tax);
    formData.append('total_amount', total);
    
    $.ajax({
        url: 'billing.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Bill created successfully!', 'success');
                $('#createBillModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error creating bill. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#createBillForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function handleMarkPaid(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'mark_paid');
    
    $.ajax({
        url: 'billing.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Bill marked as paid successfully!', 'success');
                $('#markPaidModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error marking bill as paid. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#markPaidForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function viewBill(billId) {
    $.ajax({
        url: '../api/technician/get-bill.php',
        method: 'GET',
        data: { id: billId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayBillDetails(response.data);
                $('#viewBillModal').modal('show');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading bill details.', 'danger');
        }
    });
}

function displayBillDetails(bill) {
    const detailsHtml = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Bill Information</h6>
                <p><strong>Bill Number:</strong> ${bill.bill_number}</p>
                <p><strong>Service Type:</strong> ${bill.service_type}</p>
                <p><strong>Service Date:</strong> ${formatDate(bill.service_date)}</p>
                <p><strong>Status:</strong> <span class="badge bg-${getStatusBadge(bill.status)}">${bill.status}</span></p>
                <p><strong>Created:</strong> ${formatDateTime(bill.created_at)}</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Customer Information</h6>
                <p><strong>Name:</strong> ${bill.customer_name}</p>
                <p><strong>Phone:</strong> ${bill.customer_phone || 'Not provided'}</p>
                <p><strong>Address:</strong></p>
                <div class="bg-light p-3 rounded">${bill.customer_address || 'Not provided'}</div>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Service Description</h6>
                <div class="bg-light p-3 rounded">${bill.service_description || 'No description provided'}</div>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Financial Summary</h6>
                <div class="d-flex justify-content-between">
                    <span>Labor Charges:</span>
                    <span>₹${bill.labor_charges}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Parts Cost:</span>
                    <span>₹${bill.parts_cost}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Travel Charges:</span>
                    <span>₹${bill.travel_charges}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Other Charges:</span>
                    <span>₹${bill.other_charges}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span>Subtotal:</span>
                    <span>₹${bill.subtotal}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Tax (18%):</span>
                    <span>₹${bill.tax_amount}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total Amount:</span>
                    <span>₹${bill.total_amount}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Paid Amount:</span>
                    <span class="text-success">₹${bill.paid_amount || 0}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Balance:</span>
                    <span class="${bill.balance_amount > 0 ? 'text-danger' : 'text-success'}">₹${bill.balance_amount}</span>
                </div>
            </div>
        </div>
        ${bill.notes ? `
        <hr>
        <h6 class="text-muted">Notes</h6>
        <div class="bg-light p-3 rounded">${bill.notes}</div>
        ` : ''}
    `;
    
    $('#billDetails').html(detailsHtml);
}

function editBill(billId) {
    $.ajax({
        url: '../api/technician/get-bill.php',
        method: 'GET',
        data: { id: billId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                populateEditForm(response.data);
                $('#editBillModal').modal('show');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading bill details.', 'danger');
        }
    });
}

function populateEditForm(bill) {
    $('#edit_bill_id').val(bill.id);
    $('#edit_customer_name').val(bill.customer_name);
    $('#edit_customer_phone').val(bill.customer_phone);
    $('#edit_customer_address').val(bill.customer_address);
    $('#edit_service_type').val(bill.service_type);
    $('#edit_service_date').val(bill.service_date);
    $('#edit_service_description').val(bill.service_description);
    $('#edit_labor_charges').val(bill.labor_charges);
    $('#edit_parts_cost').val(bill.parts_cost);
    $('#edit_travel_charges').val(bill.travel_charges);
    $('#edit_other_charges').val(bill.other_charges);
    $('#edit_notes').val(bill.notes);
}

function markPaid(billId) {
    $('#paid_bill_id').val(billId);
    $('#markPaidModal').modal('show');
}

function printBill(billId) {
    window.open(`../api/technician/print-bill.php?id=${billId}`, '_blank');
}

function exportBills() {
    const status = $('#statusFilter').val();
    const search = $('#searchInput').val();
    const exportUrl = `../api/technician/export-bills.php?status=${encodeURIComponent(status)}&search=${encodeURIComponent(search)}`;
    window.open(exportUrl, '_blank');
}

function getStatusBadge(status) {
    const badges = {
        'pending': 'warning',
        'paid': 'success',
        'overdue': 'danger',
        'cancelled': 'secondary'
    };
    return badges[status] || 'secondary';
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

// Auto-calculate on input change
$('#labor_charges, #parts_cost, #travel_charges, #other_charges').on('input', function() {
    calculateTotal();
});

// Service type change handler
$('#service_type').change(function() {
    const serviceType = $(this).val();
    
    // Set default charges based on service type
    switch(serviceType) {
        case 'Installation':
            $('#labor_charges').val('500');
            $('#travel_charges').val('100');
            break;
        case 'Maintenance':
            $('#labor_charges').val('300');
            $('#travel_charges').val('50');
            break;
        case 'Repair':
            $('#labor_charges').val('400');
            $('#travel_charges').val('75');
            break;
        case 'Replacement':
            $('#labor_charges').val('600');
            $('#travel_charges').val('100');
            break;
        case 'Service':
            $('#labor_charges').val('200');
            $('#travel_charges').val('50');
            break;
        default:
            $('#labor_charges').val('0');
            $('#travel_charges').val('0');
    }
    
    calculateTotal();
});
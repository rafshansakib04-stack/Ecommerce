// Invoice Management JavaScript
$(document).ready(function() {
    // Initialize invoice management
    initializeInvoiceManagement();
    
    // Filter functionality
    $('#statusFilter, #customerFilter').change(function() {
        applyFilters();
    });
    
    // Search functionality
    $('#searchInput').on('input', function() {
        debounceSearch($(this).val());
    });
    
    // Form submissions
    $('#createInvoiceForm').submit(handleCreateInvoice);
    $('#markPaidForm').submit(handleMarkPaid);
    setupRecordPaymentModal();
    
    // Load products for invoice creation
    loadProducts();
});

function initializeInvoiceManagement() {
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

function setupRecordPaymentModal() {
    // Create modal HTML once
    if ($('#recordPaymentModal').length) return;
    const modal = `
    <div class="modal fade" id="recordPaymentModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-credit-card me-2"></i>Record Payment</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form id="recordPaymentForm">
            <input type="hidden" id="record_invoice_id" name="id">
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Amount (₹)</label>
                <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Payment Method</label>
                <select class="form-select" name="payment_method">
                  <option value="cash">Cash</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="cheque">Cheque</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Reference Number</label>
                <input type="text" class="form-control" name="reference_number">
              </div>
              <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" rows="3"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Record</button>
            </div>
          </form>
        </div>
      </div>
    </div>`;
    $('body').append(modal);

    $('#recordPaymentForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'record_payment');
        $.ajax({
            url: 'invoices.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    showAlert('Payment recorded successfully!', 'success');
                    $('#recordPaymentModal').modal('hide');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showAlert(resp.message || 'Failed to record payment', 'danger');
                }
            },
            error: function() {
                showAlert('Error recording payment. Please try again.', 'danger');
            }
        });
    });
}

function openRecordPayment(invoiceId) {
    $('#record_invoice_id').val(invoiceId);
    $('#recordPaymentModal').modal('show');
}

function applyFilters() {
    const status = $('#statusFilter').val();
    const customer = $('#customerFilter').val();
    const search = $('#searchInput').val();
    
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (customer) params.set('customer', customer);
    if (search) params.set('search', search);
    
    window.location.href = 'invoices.php?' + params.toString();
}

function loadProducts() {
    $.ajax({
        url: '../api/products/list.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                populateProductSelects(response.data);
            }
        },
        error: function() {
            console.log('Error loading products');
        }
    });
}

function populateProductSelects(products) {
    const selects = $('select[name*="[product_id]"]');
    selects.each(function() {
        const $select = $(this);
        $select.empty().append('<option value="">Select product...</option>');
        
        products.forEach(function(product) {
            $select.append(`<option value="${product.id}" data-price="${product.price}">${product.name} - ₹${product.price}</option>`);
        });
    });
}

function handleCreateInvoice(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'create_invoice');
    
    // Calculate totals
    const subtotal = calculateSubtotal();
    const tax = subtotal * 0.18; // 18% tax
    const total = subtotal + tax;
    
    formData.append('subtotal', subtotal);
    formData.append('tax_amount', tax);
    formData.append('total_amount', total);
    
    $.ajax({
        url: 'invoices.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Invoice created successfully!', 'success');
                $('#createInvoiceModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error creating invoice. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#createInvoiceForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function handleMarkPaid(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'mark_paid');
    
    $.ajax({
        url: 'invoices.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Invoice marked as paid successfully!', 'success');
                $('#markPaidModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error marking invoice as paid. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#markPaidForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function viewInvoice(invoiceId) {
    window.open(`../api/invoices/view.php?id=${invoiceId}`, '_blank');
}

function printInvoice(invoiceId) {
    window.open(`../api/invoices/print.php?id=${invoiceId}`, '_blank');
}

function sendInvoice(invoiceId) {
    if (confirm('Send this invoice to the customer?')) {
        $.ajax({
            url: 'invoices.php',
            method: 'POST',
            data: {
                action: 'send_invoice',
                id: invoiceId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('Invoice sent successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error sending invoice. Please try again.', 'danger');
            }
        });
    }
}

function markPaid(invoiceId) {
    $('#paid_invoice_id').val(invoiceId);
    $('#markPaidModal').modal('show');
}

function exportInvoices() {
    const status = $('#statusFilter').val();
    const customer = $('#customerFilter').val();
    const search = $('#searchInput').val();
    
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (customer) params.set('customer', customer);
    if (search) params.set('search', search);
    
    window.open(`../api/invoices/export.php?${params.toString()}`, '_blank');
}

// Invoice item management
let itemIndex = 0;

function addItem() {
    itemIndex++;
    const newRow = `
        <tr>
            <td>
                <select class="form-select form-select-sm" name="items[${itemIndex}][product_id]" onchange="updateItemPrice(this)">
                    <option value="">Select product...</option>
                </select>
            </td>
            <td><input type="text" class="form-control form-control-sm" name="items[${itemIndex}][description]"></td>
            <td><input type="number" class="form-control form-control-sm" name="items[${itemIndex}][quantity]" min="1" value="1" onchange="calculateItemTotal(this)"></td>
            <td><input type="number" class="form-control form-control-sm" name="items[${itemIndex}][unit_price]" step="0.01" min="0" onchange="calculateItemTotal(this)"></td>
            <td><input type="number" class="form-control form-control-sm" name="items[${itemIndex}][total_price]" step="0.01" readonly></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(this)"><i class="fas fa-trash"></i></button></td>
        </tr>
    `;
    
    $('#invoiceItemsBody').append(newRow);
    
    // Populate product options for new row
    loadProducts();
}

function removeItem(button) {
    $(button).closest('tr').remove();
    calculateTotals();
}

function updateItemPrice(select) {
    const price = $(select).find('option:selected').data('price') || 0;
    const row = $(select).closest('tr');
    row.find('input[name*="[unit_price]"]').val(price);
    calculateItemTotal(row.find('input[name*="[quantity]"]')[0]);
}

function calculateItemTotal(input) {
    const row = $(input).closest('tr');
    const quantity = parseFloat(row.find('input[name*="[quantity]"]').val()) || 0;
    const unitPrice = parseFloat(row.find('input[name*="[unit_price]"]').val()) || 0;
    const total = quantity * unitPrice;
    
    row.find('input[name*="[total_price]"]').val(total.toFixed(2));
    calculateTotals();
}

function calculateSubtotal() {
    let subtotal = 0;
    $('#invoiceItemsBody tr').each(function() {
        const total = parseFloat($(this).find('input[name*="[total_price]"]').val()) || 0;
        subtotal += total;
    });
    return subtotal;
}

function calculateTotals() {
    const subtotal = calculateSubtotal();
    const tax = subtotal * 0.18;
    const discount = 0; // Can be added later
    const total = subtotal + tax - discount;
    
    $('#invoiceSubtotal').text('₹' + subtotal.toFixed(2));
    $('#invoiceTax').text('₹' + tax.toFixed(2));
    $('#invoiceDiscount').text('₹' + discount.toFixed(2));
    $('#invoiceTotal').text('₹' + total.toFixed(2));
}

// Initialize first row
$(document).ready(function() {
    // Set up event handlers for the first row
    $('#invoiceItemsBody tr').each(function() {
        const $row = $(this);
        $row.find('select[name*="[product_id]"]').on('change', function() {
            updateItemPrice(this);
        });
        $row.find('input[name*="[quantity]"], input[name*="[unit_price]"]').on('change', function() {
            calculateItemTotal(this);
        });
    });
});

function getStatusBadge(status) {
    const badges = {
        'draft': 'secondary',
        'sent': 'info',
        'paid': 'success',
        'overdue': 'danger',
        'cancelled': 'danger'
    };
    return badges[status] || 'secondary';
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
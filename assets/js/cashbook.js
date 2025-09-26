// Cashbook Management JavaScript
$(document).ready(function() {
    // Initialize cashbook management
    initializeCashbookManagement();
    
    // Filter functionality
    $('#typeFilter, #dateFilter').change(function() {
        applyFilters();
    });
    
    // Form submissions
    $('#addIncomeForm').submit(handleAddIncome);
    $('#addExpenseForm').submit(handleAddExpense);
});

function initializeCashbookManagement() {
    // Add loading states to buttons
    $('.btn').on('click', function() {
        const $btn = $(this);
        if ($btn.hasClass('btn-success') || $btn.hasClass('btn-danger')) {
            $btn.prop('disabled', true);
        }
    });
}

function applyFilters() {
    const type = $('#typeFilter').val();
    const date = $('#dateFilter').val();
    
    const params = new URLSearchParams();
    if (type) params.set('type', type);
    if (date) params.set('date', date);
    
    window.location.href = 'cashbook.php?' + params.toString();
}

function handleAddIncome(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_income');
    
    $.ajax({
        url: 'cashbook.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Income added successfully!', 'success');
                $('#addIncomeModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error adding income. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#addIncomeForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function handleAddExpense(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_expense');
    
    $.ajax({
        url: 'cashbook.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Expense added successfully!', 'success');
                $('#addExpenseModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error adding expense. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#addExpenseForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function viewTransaction(transactionId) {
    $.ajax({
        url: '../api/technician/get-transaction.php',
        method: 'GET',
        data: { id: transactionId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayTransactionDetails(response.data);
                $('#viewTransactionModal').modal('show');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading transaction details.', 'danger');
        }
    });
}

function displayTransactionDetails(transaction) {
    const detailsHtml = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Transaction Information</h6>
                <p><strong>Date:</strong> ${formatDate(transaction.transaction_date)}</p>
                <p><strong>Type:</strong> <span class="badge bg-${transaction.transaction_type === 'income' ? 'success' : 'danger'}">${transaction.transaction_type}</span></p>
                <p><strong>Category:</strong> ${transaction.category}</p>
                <p><strong>Description:</strong></p>
                <div class="bg-light p-3 rounded">${transaction.description}</div>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Financial Details</h6>
                <p><strong>Amount:</strong> <span class="${transaction.transaction_type === 'income' ? 'text-success' : 'text-danger'} fw-bold">${transaction.transaction_type === 'income' ? '+' : '-'}₹${transaction.amount}</span></p>
                <p><strong>Payment Method:</strong> <span class="badge bg-info">${transaction.payment_method}</span></p>
                <p><strong>Reference Number:</strong> ${transaction.reference_number || 'Not provided'}</p>
                <p><strong>Receipt:</strong> ${transaction.receipt_path ? '<a href="' + transaction.receipt_path + '" target="_blank">View Receipt</a>' : 'No receipt'}</p>
            </div>
        </div>
    `;
    
    $('#transactionDetails').html(detailsHtml);
}

function editTransaction(transactionId) {
    $.ajax({
        url: '../api/technician/get-transaction.php',
        method: 'GET',
        data: { id: transactionId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                populateEditForm(response.data);
                $('#editTransactionModal').modal('show');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading transaction details.', 'danger');
        }
    });
}

function populateEditForm(transaction) {
    $('#edit_transaction_id').val(transaction.id);
    $('#edit_transaction_date').val(transaction.transaction_date);
    $('#edit_category').val(transaction.category);
    $('#edit_description').val(transaction.description);
    $('#edit_amount').val(transaction.amount);
    $('#edit_payment_method').val(transaction.payment_method);
    $('#edit_reference_number').val(transaction.reference_number);
}

function deleteTransaction(transactionId) {
    if (confirm('Are you sure you want to delete this transaction? This action cannot be undone.')) {
        $.ajax({
            url: 'cashbook.php',
            method: 'POST',
            data: {
                action: 'delete_transaction',
                id: transactionId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('Transaction deleted successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error deleting transaction. Please try again.', 'danger');
            }
        });
    }
}

function exportCashbook() {
    const type = $('#typeFilter').val();
    const date = $('#dateFilter').val();
    
    const params = new URLSearchParams();
    if (type) params.set('type', type);
    if (date) params.set('date', date);
    
    window.open(`../api/technician/export-cashbook.php?${params.toString()}`, '_blank');
}

function showSummary() {
    $.ajax({
        url: '../api/technician/cashbook-summary.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displaySummary(response.data);
                $('#summaryModal').modal('show');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading summary.', 'danger');
        }
    });
}

function displaySummary(summary) {
    const summaryHtml = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Today's Summary</h6>
                <div class="d-flex justify-content-between">
                    <span>Income:</span>
                    <span class="text-success fw-bold">₹${summary.today_income}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Expenses:</span>
                    <span class="text-danger fw-bold">₹${summary.today_expense}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Net:</span>
                    <span class="fw-bold ${summary.today_income - summary.today_expense >= 0 ? 'text-success' : 'text-danger'}">₹${summary.today_income - summary.today_expense}</span>
                </div>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Monthly Summary</h6>
                <div class="d-flex justify-content-between">
                    <span>Income:</span>
                    <span class="text-success fw-bold">₹${summary.monthly_income}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Expenses:</span>
                    <span class="text-danger fw-bold">₹${summary.monthly_expense}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Net:</span>
                    <span class="fw-bold ${summary.monthly_income - summary.monthly_expense >= 0 ? 'text-success' : 'text-danger'}">₹${summary.monthly_income - summary.monthly_expense}</span>
                </div>
            </div>
        </div>
    `;
    
    $('#summaryContent').html(summaryHtml);
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

// Auto-fill today's date
$(document).ready(function() {
    const today = new Date().toISOString().split('T')[0];
    $('#income_date, #expense_date').val(today);
});

// Form validation
$('#addIncomeForm, #addExpenseForm').on('submit', function(e) {
    const amount = parseFloat($(this).find('input[name="amount"]').val());
    if (amount <= 0) {
        e.preventDefault();
        showAlert('Amount must be greater than 0', 'warning');
        return false;
    }
});

// Quick add buttons
function quickAddIncome(amount, description) {
    $('#income_amount').val(amount);
    $('#income_description').val(description);
    $('#addIncomeModal').modal('show');
}

function quickAddExpense(amount, description) {
    $('#expense_amount').val(amount);
    $('#expense_description').val(description);
    $('#addExpenseModal').modal('show');
}
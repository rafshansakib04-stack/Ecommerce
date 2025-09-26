// Product Management JavaScript
$(document).ready(function() {
    // Initialize product management
    initializeProductManagement();
    
    // Search functionality
    $('#searchInput').on('input', function() {
        const searchTerm = $(this).val();
        if (searchTerm.length >= 3 || searchTerm.length === 0) {
            debounceSearch(searchTerm);
        }
    });
    
    // Category filter
    $('#categoryFilter').change(function() {
        applyFilters();
    });
    
    // Form submissions
    $('#addProductForm').submit(handleAddProduct);
    $('#updateStockForm').submit(handleUpdateStock);
    $('#editProductForm').submit(handleEditProduct);
});

function initializeProductManagement() {
    // Add loading states to buttons
    $('.btn').on('click', function() {
        const $btn = $(this);
        if ($btn.hasClass('btn-primary') || $btn.hasClass('btn-warning')) {
            $btn.prop('disabled', true);
        }
    });
}

function handleEditProduct(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'update_product');

    $.ajax({
        url: 'products.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Product updated successfully!', 'success');
                $('#editProductModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error updating product. Please try again.', 'danger');
        },
        complete: function() {
            $('#editProductForm button[type="submit"]').prop('disabled', false);
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
    const category = $('#categoryFilter').val();
    
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (category) params.set('category', category);
    
    window.location.href = 'products.php?' + params.toString();
}

function handleAddProduct(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_product');
    
    $.ajax({
        url: 'products.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Product added successfully!', 'success');
                $('#addProductModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error adding product. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#addProductForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function handleUpdateStock(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_stock');
    
    $.ajax({
        url: 'products.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Stock updated successfully!', 'success');
                $('#updateStockModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error updating stock. Please try again.', 'danger');
        },
        complete: function() {
            // Re-enable button
            $('#updateStockForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function viewProduct(productId) {
    $.ajax({
        url: '../api/products/get.php',
        method: 'GET',
        data: { id: productId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayProductDetails(response.data);
                $('#viewProductModal').modal('show');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading product details.', 'danger');
        }
    });
}

function displayProductDetails(product) {
    const detailsHtml = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Product Information</h6>
                <p><strong>Name:</strong> ${product.name}</p>
                <p><strong>SKU:</strong> <code>${product.sku}</code></p>
                <p><strong>Category:</strong> ${product.category_name}</p>
                <p><strong>Description:</strong></p>
                <div class="bg-light p-3 rounded">${product.description || 'No description provided'}</div>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Pricing & Stock</h6>
                <p><strong>Selling Price:</strong> ₹${product.price}</p>
                <p><strong>Cost Price:</strong> ₹${product.cost_price || 'Not set'}</p>
                <p><strong>Current Stock:</strong> <span class="badge bg-${product.stock_quantity <= product.min_stock_level ? 'danger' : 'success'}">${product.stock_quantity} ${product.unit}</span></p>
                <p><strong>Min Stock Level:</strong> ${product.min_stock_level} ${product.unit}</p>
                <p><strong>Status:</strong> <span class="badge bg-${getStatusBadge(product.status)}">${product.status}</span></p>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Stock Movements</h6>
                <p><strong>Total In:</strong> ${product.total_in || 0}</p>
                <p><strong>Total Out:</strong> ${product.total_out || 0}</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Timing Information</h6>
                <p><strong>Created:</strong> ${formatDateTime(product.created_at)}</p>
                <p><strong>Last Updated:</strong> ${formatDateTime(product.updated_at)}</p>
            </div>
        </div>
    `;
    
    $('#productDetails').html(detailsHtml);
}

function editProduct(productId) {
    $.ajax({
        url: '../api/products/get.php',
        method: 'GET',
        data: { id: productId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                populateEditForm(response.data);
                $('#editProductModal').modal('show');
            } else {
                showAlert(response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error loading product details.', 'danger');
        }
    });
}

function populateEditForm(product) {
    $('#edit_product_id').val(product.id);
    $('#edit_product_name').val(product.name);
    $('#edit_product_sku').val(product.sku);
    $('#edit_product_description').val(product.description);
    $('#edit_product_category').val(product.category_id);
    $('#edit_product_price').val(product.price);
    $('#edit_product_cost').val(product.cost_price);
    $('#edit_product_unit').val(product.unit);
    $('#edit_min_stock').val(product.min_stock_level);
    $('#edit_product_status').val(product.status);
}

function deleteProduct(productId) {
    if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        $.ajax({
            url: 'products.php',
            method: 'POST',
            data: {
                action: 'delete_product',
                id: productId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('Product deleted successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error deleting product. Please try again.', 'danger');
            }
        });
    }
}

function updateStock(productId) {
    $('#stock_product_id').val(productId);
    $('#updateStockModal').modal('show');
}

function exportProducts() {
    const search = $('#searchInput').val();
    const category = $('#categoryFilter').val();
    const exportUrl = `../api/products/export.php?search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}`;
    window.open(exportUrl, '_blank');
}

function showLowStock() {
    // Filter to show only low stock items
    window.location.href = 'products.php?low_stock=1';
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

// Auto-generate SKU
$('#product_name').on('input', function() {
    const name = $(this).val();
    if (name && !$('#product_sku').val()) {
        const sku = name.replace(/\s+/g, '').toUpperCase().substring(0, 8) + Math.random().toString(36).substring(2, 6).toUpperCase();
        $('#product_sku').val(sku);
    }
});

// Stock level warning
$('#product_stock, #min_stock').on('input', function() {
    const stock = parseInt($('#product_stock').val()) || 0;
    const minStock = parseInt($('#min_stock').val()) || 0;
    
    if (stock <= minStock && stock > 0) {
        $(this).addClass('is-invalid');
        $(this).siblings('.invalid-feedback').remove();
        $(this).after('<div class="invalid-feedback">Stock is at or below minimum level</div>');
    } else {
        $(this).removeClass('is-invalid');
        $(this).siblings('.invalid-feedback').remove();
    }
});
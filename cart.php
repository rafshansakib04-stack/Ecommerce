<?php
/**
 * Shopping Cart Page
 * Real-time cart management with promo codes and shipping calculation
 */

require_once 'includes/functions.php';

$cartItems = getCartItems();
$subtotal = getCartTotal();

// Calculate shipping
$shippingThreshold = getSetting('free_shipping_threshold', 5000);
$shippingRate = getSetting('shipping_rate', 100);
$shippingCost = $subtotal >= $shippingThreshold ? 0 : $shippingRate;

// Apply promo code if exists
$promoDiscount = 0;
$promoCode = $_SESSION['applied_promo'] ?? null;

if ($promoCode) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM promo_codes 
        WHERE code = ? AND is_active = TRUE 
        AND start_date <= NOW() AND end_date >= NOW()
        AND (usage_limit IS NULL OR used_count < usage_limit)
    ");
    $stmt->execute([$promoCode]);
    $promo = $stmt->fetch();
    
    if ($promo && $subtotal >= $promo['minimum_order_amount']) {
        if ($promo['type'] === 'percentage') {
            $promoDiscount = min(($subtotal * $promo['value'] / 100), $promo['maximum_discount_amount'] ?? PHP_FLOAT_MAX);
        } elseif ($promo['type'] === 'fixed_amount') {
            $promoDiscount = min($promo['value'], $subtotal);
        } elseif ($promo['type'] === 'free_shipping') {
            $shippingCost = 0;
        }
    }
}

$total = $subtotal - $promoDiscount + $shippingCost;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'apply_promo':
            $code = sanitizeInput($_POST['code'] ?? '');
            $result = applyPromoCode($code, $subtotal);
            echo json_encode($result);
            exit();
            
        case 'remove_promo':
            unset($_SESSION['applied_promo']);
            echo json_encode(['success' => true, 'message' => 'Promo code removed']);
            exit();
    }
}

function applyPromoCode($code, $subtotal) {
    if (empty($code)) {
        return ['success' => false, 'message' => 'Please enter a promo code'];
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM promo_codes 
        WHERE code = ? AND is_active = TRUE 
        AND start_date <= NOW() AND end_date >= NOW()
        AND (usage_limit IS NULL OR used_count < usage_limit)
    ");
    $stmt->execute([$code]);
    $promo = $stmt->fetch();
    
    if (!$promo) {
        return ['success' => false, 'message' => 'Invalid or expired promo code'];
    }
    
    if ($subtotal < $promo['minimum_order_amount']) {
        return ['success' => false, 'message' => "Minimum order amount is " . formatCurrency($promo['minimum_order_amount'])];
    }
    
    // Check user usage limit
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId && $promo['user_limit'] > 0) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM promo_code_usage WHERE promo_code_id = ? AND user_id = ?");
        $stmt->execute([$promo['id'], $userId]);
        $userUsage = $stmt->fetchColumn();
        
        if ($userUsage >= $promo['user_limit']) {
            return ['success' => false, 'message' => 'You have already used this promo code'];
        }
    }
    
    $_SESSION['applied_promo'] = $code;
    
    return [
        'success' => true, 
        'message' => 'Promo code applied successfully!',
        'discount' => calculatePromoDiscount($promo, $subtotal)
    ];
}

function calculatePromoDiscount($promo, $subtotal) {
    switch ($promo['type']) {
        case 'percentage':
            return min(($subtotal * $promo['value'] / 100), $promo['maximum_discount_amount'] ?? PHP_FLOAT_MAX);
        case 'fixed_amount':
            return min($promo['value'], $subtotal);
        default:
            return 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - PureFit Bangladesh</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/main.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Cart Section -->
    <section class="cart-section py-5">
        <div class="container">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="page-title">Shopping Cart</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/">Home</a></li>
                            <li class="breadcrumb-item active">Shopping Cart</li>
                        </ol>
                    </nav>
                </div>
            </div>
            
            <?php if (empty($cartItems)): ?>
            <!-- Empty Cart -->
            <div class="empty-cart text-center py-5">
                <div class="empty-cart-icon mb-4">
                    <i class="fas fa-shopping-cart fa-4x text-muted"></i>
                </div>
                <h3>Your cart is empty</h3>
                <p class="text-muted mb-4">Looks like you haven't added any products to your cart yet.</p>
                <a href="/products.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-tint me-2"></i>Start Shopping
                </a>
            </div>
            <?php else: ?>
            
            <div class="row">
                <!-- Cart Items -->
                <div class="col-lg-8">
                    <div class="cart-items-card">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-shopping-cart me-2"></i>
                                    Cart Items (<?= count($cartItems) ?>)
                                </h5>
                                <button class="btn btn-sm btn-outline-danger" onclick="clearCart()">
                                    <i class="fas fa-trash me-1"></i>Clear Cart
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="cart-items-list">
                                    <?php foreach ($cartItems as $item): ?>
                                    <div class="cart-item" data-item-id="<?= $item['id'] ?>">
                                        <div class="item-image">
                                            <img src="/uploads/products/<?= $item['image'] ?? 'placeholder.jpg' ?>" 
                                                 alt="<?= $item['name'] ?>" class="img-fluid">
                                        </div>
                                        
                                        <div class="item-details">
                                            <h6 class="item-name">
                                                <a href="/product-details.php?id=<?= $item['product_id'] ?>">
                                                    <?= $item['name'] ?>
                                                </a>
                                            </h6>
                                            
                                            <?php if ($item['variant_name']): ?>
                                            <div class="item-variant">
                                                <small class="text-muted">Variant: <?= $item['variant_name'] ?></small>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="item-price">
                                                <?php 
                                                $price = $item['variant_price'] ?: ($item['sale_price'] ?: $item['price']);
                                                $originalPrice = $item['price'];
                                                ?>
                                                
                                                <?php if ($price < $originalPrice): ?>
                                                <span class="current-price"><?= formatCurrency($price) ?></span>
                                                <span class="original-price"><?= formatCurrency($originalPrice) ?></span>
                                                <?php else: ?>
                                                <span class="current-price"><?= formatCurrency($price) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Stock Status -->
                                            <div class="item-stock">
                                                <?php
                                                // Get current stock
                                                $db = getDB();
                                                if ($item['variant_id']) {
                                                    $stmt = $db->prepare("SELECT stock_quantity FROM product_variants WHERE id = ?");
                                                    $stmt->execute([$item['variant_id']]);
                                                    $stock = $stmt->fetchColumn();
                                                } else {
                                                    $stmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                                                    $stmt->execute([$item['product_id']]);
                                                    $stock = $stmt->fetchColumn();
                                                }
                                                ?>
                                                
                                                <?php if ($stock > 0): ?>
                                                    <?php if ($stock < 5): ?>
                                                    <small class="text-warning">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                                        Only <?= $stock ?> left in stock
                                                    </small>
                                                    <?php else: ?>
                                                    <small class="text-success">
                                                        <i class="fas fa-check-circle me-1"></i>In Stock
                                                    </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                <small class="text-danger">
                                                    <i class="fas fa-times-circle me-1"></i>Out of Stock
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="item-quantity">
                                            <div class="quantity-controls">
                                                <button class="btn btn-sm btn-outline-secondary qty-decrease" 
                                                        data-item-id="<?= $item['id'] ?>"
                                                        <?= $item['quantity'] <= 1 ? 'disabled' : '' ?>>
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" class="form-control quantity-input" 
                                                       value="<?= $item['quantity'] ?>" 
                                                       min="1" max="<?= $stock ?>"
                                                       data-item-id="<?= $item['id'] ?>">
                                                <button class="btn btn-sm btn-outline-secondary qty-increase" 
                                                        data-item-id="<?= $item['id'] ?>"
                                                        <?= $item['quantity'] >= $stock ? 'disabled' : '' ?>>
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="item-total">
                                            <div class="total-price">
                                                <?= formatCurrency($price * $item['quantity']) ?>
                                            </div>
                                            <button class="btn btn-sm btn-outline-danger remove-item" 
                                                    data-item-id="<?= $item['id'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Continue Shopping -->
                    <div class="mt-4">
                        <a href="/products.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                        </a>
                    </div>
                </div>
                
                <!-- Cart Summary -->
                <div class="col-lg-4">
                    <div class="cart-summary-card sticky-top">
                        <!-- Promo Code -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-tag me-2"></i>Promo Code
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if ($promoCode): ?>
                                <div class="applied-promo">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong class="text-success"><?= $promoCode ?></strong>
                                            <small class="text-muted d-block">
                                                Discount: <?= formatCurrency($promoDiscount) ?>
                                            </small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-danger" onclick="removePromoCode()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php else: ?>
                                <form id="promo-form">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="promo_code" 
                                               placeholder="Enter promo code" maxlength="50">
                                        <button class="btn btn-primary" type="submit">
                                            Apply
                                        </button>
                                    </div>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Order Summary -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-receipt me-2"></i>Order Summary
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="summary-row">
                                    <span>Subtotal (<?= count($cartItems) ?> items):</span>
                                    <span id="cart-subtotal"><?= formatCurrency($subtotal) ?></span>
                                </div>
                                
                                <?php if ($promoDiscount > 0): ?>
                                <div class="summary-row text-success">
                                    <span>Discount (<?= $promoCode ?>):</span>
                                    <span>-<?= formatCurrency($promoDiscount) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="summary-row">
                                    <span>Shipping:</span>
                                    <span id="shipping-cost">
                                        <?php if ($shippingCost > 0): ?>
                                            <?= formatCurrency($shippingCost) ?>
                                        <?php else: ?>
                                            <span class="text-success">Free</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php if ($subtotal < $shippingThreshold && $shippingCost > 0): ?>
                                <div class="shipping-notice">
                                    <small class="text-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Add <?= formatCurrency($shippingThreshold - $subtotal) ?> more for free shipping
                                    </small>
                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar bg-info" 
                                             style="width: <?= ($subtotal / $shippingThreshold) * 100 ?>%"></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <div class="summary-row total-row">
                                    <strong>
                                        <span>Total:</span>
                                        <span class="text-primary" id="cart-total"><?= formatCurrency($total) ?></span>
                                    </strong>
                                </div>
                                
                                <div class="cart-actions mt-4">
                                    <?php if (isLoggedIn()): ?>
                                    <a href="/checkout.php" class="btn btn-primary w-100 btn-lg">
                                        <i class="fas fa-lock me-2"></i>Proceed to Checkout
                                    </a>
                                    <?php else: ?>
                                    <a href="/customer/login.php?redirect=/checkout.php" class="btn btn-primary w-100 btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i>Login to Checkout
                                    </a>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-outline-primary w-100 mt-2" onclick="saveForLater()">
                                        <i class="fas fa-heart me-2"></i>Save for Later
                                    </button>
                                </div>
                                
                                <!-- Trust Badges -->
                                <div class="trust-badges mt-4">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <i class="fas fa-shield-alt text-success"></i>
                                            <small class="d-block">Secure</small>
                                        </div>
                                        <div class="col-4">
                                            <i class="fas fa-truck text-primary"></i>
                                            <small class="d-block">Fast Delivery</small>
                                        </div>
                                        <div class="col-4">
                                            <i class="fas fa-undo text-info"></i>
                                            <small class="d-block">Easy Returns</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Related Products -->
            <div class="row mt-5">
                <div class="col-12">
                    <h4 class="mb-4">You might also like</h4>
                    <div class="related-products" id="related-products">
                        <!-- Related products will be loaded via AJAX -->
                        <div class="text-center p-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </section>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Firebase SDK -->
    <?= FirebaseConfig::renderFirebaseScript() ?>
    
    <!-- Custom JavaScript -->
    <script src="/assets/js/main.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize cart functionality
            initializeCart();
            
            // Load related products
            loadRelatedProducts();
            
            // Auto-save cart changes
            setupAutoSave();
        });
        
        function initializeCart() {
            // Quantity controls
            $('.qty-increase').click(function() {
                const itemId = $(this).data('item-id');
                const input = $(this).siblings('.quantity-input');
                const currentQty = parseInt(input.val());
                const maxQty = parseInt(input.attr('max'));
                
                if (currentQty < maxQty) {
                    input.val(currentQty + 1);
                    updateCartItem(itemId, currentQty + 1);
                }
            });
            
            $('.qty-decrease').click(function() {
                const itemId = $(this).data('item-id');
                const input = $(this).siblings('.quantity-input');
                const currentQty = parseInt(input.val());
                
                if (currentQty > 1) {
                    input.val(currentQty - 1);
                    updateCartItem(itemId, currentQty - 1);
                }
            });
            
            // Direct quantity input
            $('.quantity-input').on('change', function() {
                const itemId = $(this).data('item-id');
                const quantity = parseInt($(this).val());
                const maxQty = parseInt($(this).attr('max'));
                
                if (quantity > maxQty) {
                    $(this).val(maxQty);
                    showToast('Warning', `Maximum quantity available: ${maxQty}`, 'warning');
                    return;
                }
                
                if (quantity < 1) {
                    $(this).val(1);
                    return;
                }
                
                updateCartItem(itemId, quantity);
            });
            
            // Remove item
            $('.remove-item').click(function() {
                const itemId = $(this).data('item-id');
                removeCartItem(itemId);
            });
            
            // Promo code form
            $('#promo-form').on('submit', function(e) {
                e.preventDefault();
                
                const code = $(this).find('input[name="promo_code"]').val().trim();
                applyPromoCode(code);
            });
        }
        
        function updateCartItem(itemId, quantity) {
            // Show loading state
            const itemRow = $(`.cart-item[data-item-id="${itemId}"]`);
            itemRow.addClass('updating');
            
            $.post('/api/cart/update.php', {
                item_id: itemId,
                quantity: quantity
            })
            .done(function(response) {
                if (response.success) {
                    // Update item total
                    const price = response.unit_price;
                    const total = price * quantity;
                    itemRow.find('.total-price').text(formatCurrency(total));
                    
                    // Update cart totals
                    updateCartTotals();
                    
                    // Update free shipping progress
                    updateShippingProgress();
                    
                } else {
                    showToast('Error', response.message, 'error');
                    // Revert quantity
                    location.reload();
                }
            })
            .fail(function() {
                showToast('Error', 'Failed to update cart item', 'error');
                location.reload();
            })
            .always(function() {
                itemRow.removeClass('updating');
            });
        }
        
        function removeCartItem(itemId) {
            if (!confirm('Are you sure you want to remove this item from your cart?')) {
                return;
            }
            
            const itemRow = $(`.cart-item[data-item-id="${itemId}"]`);
            itemRow.addClass('removing');
            
            $.post('/api/cart/remove.php', {
                item_id: itemId
            })
            .done(function(response) {
                if (response.success) {
                    // Animate removal
                    itemRow.slideUp(300, function() {
                        $(this).remove();
                        
                        // Check if cart is empty
                        if ($('.cart-item').length === 0) {
                            location.reload();
                        } else {
                            updateCartTotals();
                            updateCartCount();
                        }
                    });
                    
                    showToast('Success', 'Item removed from cart', 'success');
                } else {
                    showToast('Error', response.message, 'error');
                    itemRow.removeClass('removing');
                }
            })
            .fail(function() {
                showToast('Error', 'Failed to remove item', 'error');
                itemRow.removeClass('removing');
            });
        }
        
        function clearCart() {
            if (!confirm('Are you sure you want to clear your entire cart?')) {
                return;
            }
            
            $.post('/api/cart/clear.php')
                .done(function(response) {
                    if (response.success) {
                        showToast('Success', 'Cart cleared successfully', 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showToast('Error', response.message, 'error');
                    }
                })
                .fail(function() {
                    showToast('Error', 'Failed to clear cart', 'error');
                });
        }
        
        function applyPromoCode(code) {
            if (!code) {
                showToast('Error', 'Please enter a promo code', 'error');
                return;
            }
            
            const submitBtn = $('#promo-form button[type="submit"]');
            const originalText = submitBtn.html();
            
            submitBtn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
            
            $.post('/cart.php', {
                action: 'apply_promo',
                code: code
            })
            .done(function(response) {
                if (response.success) {
                    showToast('Success', response.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showToast('Error', response.message, 'error');
                }
            })
            .fail(function() {
                showToast('Error', 'Failed to apply promo code', 'error');
            })
            .always(function() {
                submitBtn.html(originalText).prop('disabled', false);
            });
        }
        
        function removePromoCode() {
            $.post('/cart.php', {
                action: 'remove_promo'
            })
            .done(function(response) {
                if (response.success) {
                    showToast('Success', 'Promo code removed', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }
            });
        }
        
        function updateCartTotals() {
            $.get('/api/cart/totals.php')
                .done(function(response) {
                    if (response.success) {
                        $('#cart-subtotal').text(formatCurrency(response.subtotal));
                        $('#cart-total').text(formatCurrency(response.total));
                        $('#shipping-cost').html(response.shipping_cost > 0 ? 
                            formatCurrency(response.shipping_cost) : 
                            '<span class="text-success">Free</span>'
                        );
                    }
                });
        }
        
        function updateShippingProgress() {
            $.get('/api/cart/shipping-progress.php')
                .done(function(response) {
                    if (response.success) {
                        const progressBar = $('.progress-bar');
                        const progressText = $('.shipping-notice small');
                        
                        if (response.free_shipping_achieved) {
                            $('.shipping-notice').hide();
                        } else {
                            progressBar.css('width', response.progress_percentage + '%');
                            progressText.html(`
                                <i class="fas fa-info-circle me-1"></i>
                                Add ${formatCurrency(response.amount_needed)} more for free shipping
                            `);
                        }
                    }
                });
        }
        
        function loadRelatedProducts() {
            // Get product IDs from cart
            const productIds = $('.cart-item').map(function() {
                return $(this).find('a').attr('href').match(/id=(\d+)/)[1];
            }).get();
            
            $.get('/api/products/related.php', {
                product_ids: productIds.join(','),
                limit: 4
            })
            .done(function(response) {
                if (response.success && response.products.length > 0) {
                    let html = '<div class="row">';
                    response.products.forEach(product => {
                        html += generateProductCard(product, 'col-lg-3 col-md-6 mb-3');
                    });
                    html += '</div>';
                    
                    $('#related-products').html(html);
                } else {
                    $('#related-products').html('<p class="text-muted text-center">No related products found</p>');
                }
            })
            .fail(function() {
                $('#related-products').html('<p class="text-muted text-center">Failed to load related products</p>');
            });
        }
        
        function saveForLater() {
            if (!isLoggedIn) {
                showToast('Info', 'Please login to save items for later', 'info');
                return;
            }
            
            $.post('/api/cart/save-for-later.php')
                .done(function(response) {
                    if (response.success) {
                        showToast('Success', 'Cart items saved to wishlist', 'success');
                        setTimeout(() => {
                            window.location.href = '/customer/wishlist.php';
                        }, 1500);
                    } else {
                        showToast('Error', response.message, 'error');
                    }
                })
                .fail(function() {
                    showToast('Error', 'Failed to save items', 'error');
                });
        }
        
        function setupAutoSave() {
            // Auto-save cart to Firebase for logged-in users
            if (isLoggedIn && window.FirebaseHelpers) {
                setInterval(function() {
                    const cartData = {
                        items: getCartItemsData(),
                        subtotal: <?= $subtotal ?>,
                        total: <?= $total ?>,
                        updated_at: Date.now()
                    };
                    
                    window.FirebaseHelpers.syncCartData(currentUser.id, cartData)
                        .catch(error => {
                            console.error('Cart sync error:', error);
                        });
                }, 30000); // Sync every 30 seconds
            }
        }
        
        function getCartItemsData() {
            const items = [];
            $('.cart-item').each(function() {
                const $item = $(this);
                items.push({
                    id: $item.data('item-id'),
                    product_id: $item.find('a').attr('href').match(/id=(\d+)/)[1],
                    quantity: parseInt($item.find('.quantity-input').val()),
                    price: parseFloat($item.find('.current-price').text().replace(/[^0-9.]/g, ''))
                });
            });
            return items;
        }
        
        // Track cart events
        if (window.FirebaseHelpers) {
            window.FirebaseHelpers.trackEvent('view_cart', {
                currency: 'BDT',
                value: <?= $total ?>,
                items: <?= json_encode(array_map(function($item) {
                    return [
                        'item_id' => $item['product_id'],
                        'item_name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['variant_price'] ?: ($item['sale_price'] ?: $item['price'])
                    ];
                }, $cartItems)) ?>
            });
        }
    </script>
    
    <style>
        .cart-section {
            background: var(--gray-100);
            min-height: calc(100vh - 76px);
        }
        
        .empty-cart {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 3rem;
        }
        
        .cart-items-card .card {
            box-shadow: var(--shadow-sm);
            border: none;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            transition: all var(--transition-normal);
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item.updating {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .cart-item.removing {
            background: #ffe6e6;
        }
        
        .item-image {
            width: 100px;
            height: 100px;
            border-radius: var(--border-radius);
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name a {
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 600;
        }
        
        .item-name a:hover {
            color: var(--primary-color);
        }
        
        .current-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .original-price {
            font-size: 0.9rem;
            color: var(--gray-500);
            text-decoration: line-through;
            margin-left: 0.5rem;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quantity-input {
            width: 70px;
            text-align: center;
            border: 1px solid var(--gray-300);
        }
        
        .item-total {
            text-align: right;
            min-width: 120px;
        }
        
        .total-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .cart-summary-card {
            top: 100px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .total-row {
            font-size: 1.2rem;
            padding-top: 0.75rem;
            border-top: 2px solid var(--primary-color);
        }
        
        .shipping-notice {
            background: rgba(23, 162, 184, 0.1);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin: 1rem 0;
        }
        
        .trust-badges {
            background: var(--gray-100);
            padding: 1rem;
            border-radius: var(--border-radius);
        }
        
        .trust-badges i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .applied-promo {
            background: rgba(40, 167, 69, 0.1);
            padding: 1rem;
            border-radius: var(--border-radius);
        }
        
        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .item-details,
            .item-quantity,
            .item-total {
                width: 100%;
            }
            
            .quantity-controls {
                justify-content: center;
            }
            
            .cart-summary-card {
                position: static !important;
                margin-top: 2rem;
            }
        }
    </style>
</body>
</html>
<?php
/**
 * Checkout Page
 * Complete checkout process with multiple payment options for Bangladesh
 */

require_once 'includes/functions.php';

// Require login
requireLogin();

$user = getCurrentUser();
$cartItems = getCartItems();

// Redirect if cart is empty
if (empty($cartItems)) {
    header('Location: /cart.php');
    exit();
}

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $price = $item['variant_price'] ?: ($item['sale_price'] ?: $item['price']);
    $subtotal += $price * $item['quantity'];
}

$shippingCost = $subtotal >= getSetting('free_shipping_threshold', 5000) ? 0 : getSetting('shipping_rate', 100);
$taxAmount = 0; // No tax for now
$total = $subtotal + $shippingCost + $taxAmount;

// Get user addresses
$addresses = getRecords('user_addresses', ['user_id' => $user['id']], '*', 'is_default DESC, created_at DESC');

$error = '';
$success = '';

// Handle form submission
if ($_POST) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $shippingAddressId = (int)($_POST['shipping_address_id'] ?? 0);
        $billingAddressId = (int)($_POST['billing_address_id'] ?? 0);
        $paymentMethod = sanitizeInput($_POST['payment_method'] ?? '');
        $orderNotes = sanitizeInput($_POST['order_notes'] ?? '');
        
        // Validate inputs
        $errors = [];
        
        if ($shippingAddressId <= 0) {
            $errors[] = 'Please select a shipping address';
        }
        
        if ($billingAddressId <= 0) {
            $errors[] = 'Please select a billing address';
        }
        
        if (empty($paymentMethod)) {
            $errors[] = 'Please select a payment method';
        }
        
        if (!in_array($paymentMethod, ['bkash', 'nagad', 'rocket', 'card', 'bank_transfer', 'cash_on_delivery'])) {
            $errors[] = 'Invalid payment method';
        }
        
        // Verify addresses belong to user
        $shippingAddress = getRecord('user_addresses', ['id' => $shippingAddressId, 'user_id' => $user['id']]);
        $billingAddress = getRecord('user_addresses', ['id' => $billingAddressId, 'user_id' => $user['id']]);
        
        if (!$shippingAddress) {
            $errors[] = 'Invalid shipping address';
        }
        
        if (!$billingAddress) {
            $errors[] = 'Invalid billing address';
        }
        
        if (empty($errors)) {
            try {
                // Create order
                $orderId = createOrderFromCart(
                    $user['id'],
                    $shippingAddress,
                    $billingAddress,
                    $paymentMethod
                );
                
                if ($orderId) {
                    // Add order notes if provided
                    if (!empty($orderNotes)) {
                        updateRecord('orders', ['notes' => $orderNotes], ['id' => $orderId]);
                    }
                    
                    // Redirect based on payment method
                    switch ($paymentMethod) {
                        case 'bkash':
                        case 'nagad':
                        case 'rocket':
                            header("Location: /payment.php?order_id=$orderId&method=$paymentMethod");
                            exit();
                            
                        case 'card':
                            header("Location: /payment.php?order_id=$orderId&method=card");
                            exit();
                            
                        case 'bank_transfer':
                            header("Location: /payment-instructions.php?order_id=$orderId&method=bank");
                            exit();
                            
                        case 'cash_on_delivery':
                            header("Location: /order-success.php?order_id=$orderId");
                            exit();
                            
                        default:
                            $error = 'Invalid payment method';
                    }
                } else {
                    $error = 'Failed to create order. Please try again.';
                }
                
            } catch (Exception $e) {
                error_log("Checkout error: " . $e->getMessage());
                $error = 'Checkout failed. Please try again.';
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - PureFit Bangladesh</title>
    
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

    <!-- Checkout Section -->
    <section class="checkout-section py-5">
        <div class="container">
            <!-- Checkout Progress -->
            <div class="checkout-progress mb-5">
                <div class="progress-steps">
                    <div class="step completed">
                        <div class="step-number">1</div>
                        <div class="step-label">Cart</div>
                    </div>
                    <div class="step active">
                        <div class="step-number">2</div>
                        <div class="step-label">Checkout</div>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-label">Payment</div>
                    </div>
                    <div class="step">
                        <div class="step-number">4</div>
                        <div class="step-label">Confirmation</div>
                    </div>
                </div>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger animate__animated animate__shakeX">
                <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="checkout-form needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                
                <div class="row">
                    <!-- Checkout Details -->
                    <div class="col-lg-8">
                        <!-- Shipping Address -->
                        <div class="checkout-section-card mb-4">
                            <div class="section-header">
                                <h4 class="section-title">
                                    <i class="fas fa-shipping-fast me-2 text-primary"></i>
                                    Shipping Address
                                </h4>
                                <a href="/customer/addresses.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-plus me-1"></i>Add New Address
                                </a>
                            </div>
                            
                            <div class="address-selection">
                                <?php if (empty($addresses)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Please add a delivery address to continue.
                                    <a href="/customer/addresses.php" class="btn btn-sm btn-info ms-2">Add Address</a>
                                </div>
                                <?php else: ?>
                                <div class="row">
                                    <?php foreach ($addresses as $address): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="address-card">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="shipping_address_id" 
                                                       value="<?= $address['id'] ?>" id="shipping_<?= $address['id'] ?>"
                                                       <?= $address['is_default'] ? 'checked' : '' ?> required>
                                                <label class="form-check-label" for="shipping_<?= $address['id'] ?>">
                                                    <div class="address-header">
                                                        <strong><?= $address['label'] ?: ucfirst($address['type']) ?></strong>
                                                        <?php if ($address['is_default']): ?>
                                                        <span class="badge bg-primary">Default</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="address-details">
                                                        <?= $address['address_line_1'] ?><br>
                                                        <?php if ($address['address_line_2']): ?>
                                                        <?= $address['address_line_2'] ?><br>
                                                        <?php endif; ?>
                                                        <?= $address['city'] ?>, <?= $address['district'] ?><br>
                                                        <?= $address['division'] ?> - <?= $address['postal_code'] ?>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Billing Address -->
                        <div class="checkout-section-card mb-4">
                            <div class="section-header">
                                <h4 class="section-title">
                                    <i class="fas fa-file-invoice me-2 text-primary"></i>
                                    Billing Address
                                </h4>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="same_as_shipping" checked>
                                    <label class="form-check-label" for="same_as_shipping">
                                        Same as shipping address
                                    </label>
                                </div>
                            </div>
                            
                            <div class="billing-address-selection" id="billing-selection" style="display: none;">
                                <div class="row">
                                    <?php foreach ($addresses as $address): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="address-card">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="billing_address_id" 
                                                       value="<?= $address['id'] ?>" id="billing_<?= $address['id'] ?>"
                                                       <?= $address['is_default'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="billing_<?= $address['id'] ?>">
                                                    <div class="address-header">
                                                        <strong><?= $address['label'] ?: ucfirst($address['type']) ?></strong>
                                                        <?php if ($address['is_default']): ?>
                                                        <span class="badge bg-primary">Default</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="address-details">
                                                        <?= $address['address_line_1'] ?><br>
                                                        <?php if ($address['address_line_2']): ?>
                                                        <?= $address['address_line_2'] ?><br>
                                                        <?php endif; ?>
                                                        <?= $address['city'] ?>, <?= $address['district'] ?><br>
                                                        <?= $address['division'] ?> - <?= $address['postal_code'] ?>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="checkout-section-card mb-4">
                            <div class="section-header">
                                <h4 class="section-title">
                                    <i class="fas fa-credit-card me-2 text-primary"></i>
                                    Payment Method
                                </h4>
                            </div>
                            
                            <div class="payment-methods">
                                <div class="row">
                                    <!-- Mobile Banking -->
                                    <div class="col-md-6 mb-3">
                                        <div class="payment-category">
                                            <h6 class="payment-category-title">Mobile Banking</h6>
                                            
                                            <div class="payment-option">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="payment_method" 
                                                           value="bkash" id="payment_bkash" required>
                                                    <label class="form-check-label" for="payment_bkash">
                                                        <div class="payment-card">
                                                            <img src="/assets/images/payments/bkash.png" alt="bKash" height="30">
                                                            <div class="payment-info">
                                                                <strong>bKash</strong>
                                                                <small class="text-muted">Instant & Secure</small>
                                                            </div>
                                                            <div class="payment-badge">
                                                                <i class="fas fa-bolt text-warning"></i>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="payment-option">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="payment_method" 
                                                           value="nagad" id="payment_nagad" required>
                                                    <label class="form-check-label" for="payment_nagad">
                                                        <div class="payment-card">
                                                            <img src="/assets/images/payments/nagad.png" alt="Nagad" height="30">
                                                            <div class="payment-info">
                                                                <strong>Nagad</strong>
                                                                <small class="text-muted">Digital Payment</small>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="payment-option">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="payment_method" 
                                                           value="rocket" id="payment_rocket" required>
                                                    <label class="form-check-label" for="payment_rocket">
                                                        <div class="payment-card">
                                                            <img src="/assets/images/payments/rocket.png" alt="Rocket" height="30">
                                                            <div class="payment-info">
                                                                <strong>Rocket</strong>
                                                                <small class="text-muted">DBBL Mobile Banking</small>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Cards & Others -->
                                    <div class="col-md-6 mb-3">
                                        <div class="payment-category">
                                            <h6 class="payment-category-title">Cards & Others</h6>
                                            
                                            <div class="payment-option">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="payment_method" 
                                                           value="card" id="payment_card" required>
                                                    <label class="form-check-label" for="payment_card">
                                                        <div class="payment-card">
                                                            <div class="card-logos">
                                                                <img src="/assets/images/payments/visa.png" alt="Visa" height="20">
                                                                <img src="/assets/images/payments/mastercard.png" alt="Mastercard" height="20">
                                                            </div>
                                                            <div class="payment-info">
                                                                <strong>Credit/Debit Card</strong>
                                                                <small class="text-muted">Visa, Mastercard</small>
                                                            </div>
                                                            <div class="payment-badge">
                                                                <i class="fas fa-shield-alt text-success"></i>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="payment-option">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="payment_method" 
                                                           value="bank_transfer" id="payment_bank" required>
                                                    <label class="form-check-label" for="payment_bank">
                                                        <div class="payment-card">
                                                            <i class="fas fa-university fa-2x text-primary"></i>
                                                            <div class="payment-info">
                                                                <strong>Bank Transfer</strong>
                                                                <small class="text-muted">Direct bank payment</small>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="payment-option">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="payment_method" 
                                                           value="cash_on_delivery" id="payment_cod" required>
                                                    <label class="form-check-label" for="payment_cod">
                                                        <div class="payment-card">
                                                            <i class="fas fa-money-bill-wave fa-2x text-success"></i>
                                                            <div class="payment-info">
                                                                <strong>Cash on Delivery</strong>
                                                                <small class="text-muted">Pay when you receive</small>
                                                            </div>
                                                            <div class="payment-badge">
                                                                <span class="badge bg-success">Popular</span>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Notes -->
                        <div class="checkout-section-card mb-4">
                            <div class="section-header">
                                <h4 class="section-title">
                                    <i class="fas fa-sticky-note me-2 text-primary"></i>
                                    Order Notes (Optional)
                                </h4>
                            </div>
                            
                            <div class="form-group">
                                <textarea class="form-control" name="order_notes" rows="3" 
                                          placeholder="Special instructions for delivery, installation preferences, etc."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="col-lg-4">
                        <div class="order-summary-card sticky-top">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-receipt me-2"></i>Order Summary
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <!-- Cart Items -->
                                    <div class="order-items">
                                        <?php foreach ($cartItems as $item): ?>
                                        <div class="order-item">
                                            <div class="item-image">
                                                <img src="/uploads/products/<?= $item['image'] ?? 'placeholder.jpg' ?>" 
                                                     alt="<?= $item['name'] ?>" class="img-fluid">
                                            </div>
                                            <div class="item-details">
                                                <h6 class="item-name"><?= $item['name'] ?></h6>
                                                <?php if ($item['variant_name']): ?>
                                                <small class="text-muted">Variant: <?= $item['variant_name'] ?></small>
                                                <?php endif; ?>
                                                <div class="item-quantity">Qty: <?= $item['quantity'] ?></div>
                                            </div>
                                            <div class="item-price">
                                                <?php 
                                                $price = $item['variant_price'] ?: ($item['sale_price'] ?: $item['price']);
                                                ?>
                                                <?= formatCurrency($price * $item['quantity']) ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <hr>
                                    
                                    <!-- Order Totals -->
                                    <div class="order-totals">
                                        <div class="total-row">
                                            <span>Subtotal:</span>
                                            <span><?= formatCurrency($subtotal) ?></span>
                                        </div>
                                        <div class="total-row">
                                            <span>Shipping:</span>
                                            <span>
                                                <?php if ($shippingCost > 0): ?>
                                                    <?= formatCurrency($shippingCost) ?>
                                                <?php else: ?>
                                                    <span class="text-success">Free</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php if ($taxAmount > 0): ?>
                                        <div class="total-row">
                                            <span>Tax:</span>
                                            <span><?= formatCurrency($taxAmount) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <hr>
                                        <div class="total-row total-final">
                                            <strong>
                                                <span>Total:</span>
                                                <span class="text-primary"><?= formatCurrency($total) ?></span>
                                            </strong>
                                        </div>
                                    </div>
                                    
                                    <!-- Savings Info -->
                                    <?php if ($shippingCost == 0 && $subtotal >= getSetting('free_shipping_threshold', 5000)): ?>
                                    <div class="savings-info mt-3">
                                        <div class="alert alert-success">
                                            <i class="fas fa-gift me-2"></i>
                                            You saved <?= formatCurrency(getSetting('shipping_rate', 100)) ?> on shipping!
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Security Info -->
                                    <div class="security-info mt-3">
                                        <div class="d-flex align-items-center text-muted">
                                            <i class="fas fa-lock me-2"></i>
                                            <small>Your payment information is secure and encrypted</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary w-100 btn-lg">
                                        <i class="fas fa-lock me-2"></i>
                                        Place Order - <?= formatCurrency($total) ?>
                                    </button>
                                    
                                    <div class="checkout-footer-links mt-3 text-center">
                                        <a href="/cart.php" class="text-muted">
                                            <i class="fas fa-arrow-left me-1"></i>Back to Cart
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
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
            // Initialize checkout
            initializeCheckout();
            
            // Handle same as shipping checkbox
            $('#same_as_shipping').change(function() {
                const billingSelection = $('#billing-selection');
                const shippingAddressId = $('input[name="shipping_address_id"]:checked').val();
                
                if (this.checked) {
                    billingSelection.hide();
                    // Set billing address same as shipping
                    $(`input[name="billing_address_id"][value="${shippingAddressId}"]`).prop('checked', true);
                } else {
                    billingSelection.show();
                }
            });
            
            // Update billing when shipping changes
            $('input[name="shipping_address_id"]').change(function() {
                if ($('#same_as_shipping').is(':checked')) {
                    $(`input[name="billing_address_id"][value="${this.value}"]`).prop('checked', true);
                }
            });
            
            // Payment method selection
            $('input[name="payment_method"]').change(function() {
                updatePaymentInfo(this.value);
            });
            
            // Form validation
            $('.checkout-form').on('submit', function(e) {
                const form = this;
                
                // Validate addresses
                if (!$('input[name="shipping_address_id"]:checked').length) {
                    e.preventDefault();
                    showToast('Error', 'Please select a shipping address', 'error');
                    return;
                }
                
                // Set billing address if same as shipping
                if ($('#same_as_shipping').is(':checked')) {
                    const shippingId = $('input[name="shipping_address_id"]:checked').val();
                    $(`<input type="hidden" name="billing_address_id" value="${shippingId}">`).appendTo(form);
                }
                
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                } else {
                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing Order...';
                }
                
                form.classList.add('was-validated');
            });
        });
        
        function initializeCheckout() {
            // Set default billing address same as shipping
            const defaultShipping = $('input[name="shipping_address_id"]:checked').val();
            if (defaultShipping) {
                $(`input[name="billing_address_id"][value="${defaultShipping}"]`).prop('checked', true);
            }
            
            // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();
        }
        
        function updatePaymentInfo(method) {
            // Remove existing payment info
            $('.payment-info-details').remove();
            
            let infoHtml = '';
            
            switch (method) {
                case 'bkash':
                    infoHtml = `
                        <div class="payment-info-details mt-3">
                            <div class="alert alert-info">
                                <i class="fab fa-bkash me-2"></i>
                                <strong>bKash Payment</strong><br>
                                You will be redirected to bKash app/website to complete payment.
                                <ul class="mt-2 mb-0">
                                    <li>Instant payment processing</li>
                                    <li>No additional charges</li>
                                    <li>Secure transaction</li>
                                </ul>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'nagad':
                    infoHtml = `
                        <div class="payment-info-details mt-3">
                            <div class="alert alert-info">
                                <i class="fas fa-mobile-alt me-2"></i>
                                <strong>Nagad Payment</strong><br>
                                Pay securely using your Nagad account.
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'rocket':
                    infoHtml = `
                        <div class="payment-info-details mt-3">
                            <div class="alert alert-info">
                                <i class="fas fa-rocket me-2"></i>
                                <strong>Rocket Payment</strong><br>
                                Pay using DBBL Rocket mobile banking.
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'card':
                    infoHtml = `
                        <div class="payment-info-details mt-3">
                            <div class="alert alert-info">
                                <i class="fas fa-credit-card me-2"></i>
                                <strong>Card Payment</strong><br>
                                Secure payment with your Visa or Mastercard.
                                <ul class="mt-2 mb-0">
                                    <li>SSL encrypted transaction</li>
                                    <li>International cards accepted</li>
                                    <li>Instant confirmation</li>
                                </ul>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'bank_transfer':
                    infoHtml = `
                        <div class="payment-info-details mt-3">
                            <div class="alert alert-warning">
                                <i class="fas fa-university me-2"></i>
                                <strong>Bank Transfer</strong><br>
                                Transfer money directly to our bank account. Order will be processed after payment verification.
                                <ul class="mt-2 mb-0">
                                    <li>Processing time: 1-2 business days</li>
                                    <li>Bank details will be provided after order</li>
                                </ul>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'cash_on_delivery':
                    infoHtml = `
                        <div class="payment-info-details mt-3">
                            <div class="alert alert-success">
                                <i class="fas fa-money-bill-wave me-2"></i>
                                <strong>Cash on Delivery</strong><br>
                                Pay in cash when your order is delivered.
                                <ul class="mt-2 mb-0">
                                    <li>No advance payment required</li>
                                    <li>Available in major cities</li>
                                    <li>Exact change preferred</li>
                                </ul>
                            </div>
                        </div>
                    `;
                    break;
            }
            
            if (infoHtml) {
                $('.payment-methods').append(infoHtml);
            }
        }
        
        // Track checkout initiation
        if (window.FirebaseHelpers) {
            window.FirebaseHelpers.trackEvent('begin_checkout', {
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
        .checkout-section {
            background: var(--gray-100);
            min-height: calc(100vh - 76px);
        }
        
        .checkout-progress {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--gray-300);
            z-index: 1;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gray-300);
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .step.completed .step-number {
            background: var(--success-color);
            color: white;
        }
        
        .step.active .step-number {
            background: var(--primary-color);
            color: white;
        }
        
        .step-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-600);
        }
        
        .step.completed .step-label,
        .step.active .step-label {
            color: var(--dark-color);
        }
        
        .checkout-section-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .address-selection,
        .payment-methods {
            padding: 1.5rem;
        }
        
        .address-card {
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 1rem;
            transition: all var(--transition-fast);
            cursor: pointer;
        }
        
        .address-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-sm);
        }
        
        .form-check-input:checked + .form-check-label .address-card {
            border-color: var(--primary-color);
            background: rgba(0, 102, 204, 0.05);
        }
        
        .address-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .payment-category-title {
            color: var(--gray-600);
            font-size: 0.9rem;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .payment-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            transition: all var(--transition-fast);
            cursor: pointer;
        }
        
        .payment-card:hover {
            border-color: var(--primary-color);
        }
        
        .form-check-input:checked + .form-check-label .payment-card {
            border-color: var(--primary-color);
            background: rgba(0, 102, 204, 0.05);
        }
        
        .payment-info {
            flex: 1;
        }
        
        .card-logos {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .order-summary-card {
            top: 100px;
        }
        
        .order-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .order-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .item-image {
            width: 60px;
            height: 60px;
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
        
        .item-name {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .item-price {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .total-final {
            font-size: 1.1rem;
            margin-bottom: 0;
        }
        
        @media (max-width: 768px) {
            .progress-steps {
                flex-wrap: wrap;
                gap: 1rem;
            }
            
            .step {
                flex-direction: row;
                gap: 0.5rem;
            }
            
            .step-number {
                width: 30px;
                height: 30px;
                margin-bottom: 0;
            }
            
            .order-summary-card {
                position: static !important;
                margin-top: 2rem;
            }
        }
    </style>
</body>
</html>
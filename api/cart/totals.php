<?php
/**
 * Cart Totals API
 * Returns cart totals including shipping and discounts
 */

header('Content-Type: application/json');
require_once '../../includes/functions.php';

try {
    $cartItems = getCartItems();
    
    if (empty($cartItems)) {
        successResponse('Cart totals calculated', [
            'subtotal' => 0,
            'shipping_cost' => 0,
            'discount' => 0,
            'total' => 0,
            'item_count' => 0
        ]);
        return;
    }
    
    // Calculate subtotal
    $subtotal = 0;
    foreach ($cartItems as $item) {
        $price = $item['variant_price'] ?: ($item['sale_price'] ?: $item['price']);
        $subtotal += $price * $item['quantity'];
    }
    
    // Calculate shipping
    $shippingThreshold = getSetting('free_shipping_threshold', 5000);
    $shippingRate = getSetting('shipping_rate', 100);
    $shippingCost = $subtotal >= $shippingThreshold ? 0 : $shippingRate;
    
    // Apply promo code discount
    $discount = 0;
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
                $discount = min(($subtotal * $promo['value'] / 100), $promo['maximum_discount_amount'] ?? PHP_FLOAT_MAX);
            } elseif ($promo['type'] === 'fixed_amount') {
                $discount = min($promo['value'], $subtotal);
            } elseif ($promo['type'] === 'free_shipping') {
                $shippingCost = 0;
            }
        }
    }
    
    $total = $subtotal - $discount + $shippingCost;
    
    successResponse('Cart totals calculated', [
        'subtotal' => $subtotal,
        'shipping_cost' => $shippingCost,
        'discount' => $discount,
        'total' => $total,
        'item_count' => count($cartItems),
        'total_quantity' => array_sum(array_column($cartItems, 'quantity')),
        'promo_code' => $promoCode,
        'free_shipping_threshold' => $shippingThreshold,
        'amount_for_free_shipping' => max(0, $shippingThreshold - $subtotal)
    ]);
    
} catch (Exception $e) {
    error_log("Cart totals error: " . $e->getMessage());
    errorResponse('Failed to calculate cart totals');
}
?>
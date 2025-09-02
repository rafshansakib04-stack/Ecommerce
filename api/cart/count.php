<?php
/**
 * Cart Count API Endpoint
 * Returns the number of items in cart
 */

header('Content-Type: application/json');
require_once '../../includes/functions.php';

try {
    $cartItems = getCartItems();
    $count = array_sum(array_column($cartItems, 'quantity'));
    
    successResponse('Cart count retrieved', [
        'count' => $count,
        'unique_items' => count($cartItems)
    ]);
    
} catch (Exception $e) {
    error_log("Cart count error: " . $e->getMessage());
    errorResponse('Failed to get cart count');
}
?>
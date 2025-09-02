<?php
/**
 * Remove Cart Item API
 * Removes items from shopping cart
 */

header('Content-Type: application/json');
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST; // Fallback to form data
}

$itemId = (int)($input['item_id'] ?? 0);

// Validate input
if ($itemId <= 0) {
    errorResponse('Invalid item ID');
}

try {
    $db = getDB();
    
    // Get cart item info before deletion
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = session_id();
    
    $sql = "SELECT ci.*, p.name
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            WHERE ci.id = ?";
    
    $params = [$itemId];
    
    if ($userId) {
        $sql .= " AND ci.user_id = ?";
        $params[] = $userId;
    } else {
        $sql .= " AND ci.session_id = ?";
        $params[] = $sessionId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $cartItem = $stmt->fetch();
    
    if (!$cartItem) {
        errorResponse('Cart item not found');
    }
    
    // Remove cart item
    $deleted = deleteRecord('cart_items', ['id' => $itemId]);
    
    if ($deleted) {
        // Get updated cart info
        $cartItems = getCartItems();
        $cartTotal = getCartTotal();
        $cartCount = array_sum(array_column($cartItems, 'quantity'));
        
        // Log activity
        if ($userId) {
            logActivity($userId, 'cart_item_removed', 'cart_item', $itemId, "Removed cart item: {$cartItem['name']}", [
                'product_id' => $cartItem['product_id'],
                'quantity' => $cartItem['quantity']
            ]);
        }
        
        successResponse('Item removed from cart', [
            'cart_count' => $cartCount,
            'cart_total' => $cartTotal,
            'remaining_items' => count($cartItems)
        ]);
    } else {
        errorResponse('Failed to remove cart item');
    }
    
} catch (Exception $e) {
    error_log("Cart remove error: " . $e->getMessage());
    errorResponse('Failed to remove cart item. Please try again.');
}
?>
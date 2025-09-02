<?php
/**
 * Update Cart Item API
 * Updates quantity of items in shopping cart
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
$quantity = (int)($input['quantity'] ?? 1);

// Validate input
if ($itemId <= 0) {
    errorResponse('Invalid item ID');
}

if ($quantity <= 0 || $quantity > 10) {
    errorResponse('Quantity must be between 1 and 10');
}

try {
    $db = getDB();
    
    // Get cart item with product info
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = session_id();
    
    $sql = "SELECT ci.*, p.name, p.stock_quantity, p.price, p.sale_price,
                   pv.stock_quantity as variant_stock, pv.price as variant_price
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            LEFT JOIN product_variants pv ON ci.variant_id = pv.id
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
    
    // Check stock availability
    $availableStock = $cartItem['variant_id'] ? $cartItem['variant_stock'] : $cartItem['stock_quantity'];
    
    if ($quantity > $availableStock) {
        errorResponse("Only $availableStock items available in stock");
    }
    
    // Update cart item
    $updated = updateRecord('cart_items', ['quantity' => $quantity], ['id' => $itemId]);
    
    if ($updated) {
        // Calculate new totals
        $cartItems = getCartItems();
        $cartTotal = getCartTotal();
        $cartCount = array_sum(array_column($cartItems, 'quantity'));
        
        // Get unit price
        $unitPrice = $cartItem['variant_price'] ?: ($cartItem['sale_price'] ?: $cartItem['price']);
        
        // Log activity
        if ($userId) {
            logActivity($userId, 'cart_updated', 'cart_item', $itemId, "Updated cart item quantity: {$cartItem['name']}", [
                'product_id' => $cartItem['product_id'],
                'old_quantity' => $cartItem['quantity'],
                'new_quantity' => $quantity
            ]);
        }
        
        successResponse('Cart updated successfully', [
            'item_id' => $itemId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'item_total' => $unitPrice * $quantity,
            'cart_count' => $cartCount,
            'cart_total' => $cartTotal
        ]);
    } else {
        errorResponse('Failed to update cart item');
    }
    
} catch (Exception $e) {
    error_log("Cart update error: " . $e->getMessage());
    errorResponse('Failed to update cart item. Please try again.');
}
?>
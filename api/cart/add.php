<?php
/**
 * Add to Cart API Endpoint
 * Handles adding products to shopping cart
 */

header('Content-Type: application/json');
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    errorResponse('Invalid JSON data');
}

$productId = (int)($input['product_id'] ?? 0);
$variantId = !empty($input['variant_id']) ? (int)$input['variant_id'] : null;
$quantity = (int)($input['quantity'] ?? 1);

// Validate input
if ($productId <= 0) {
    errorResponse('Invalid product ID');
}

if ($quantity <= 0 || $quantity > 10) {
    errorResponse('Quantity must be between 1 and 10');
}

try {
    $db = getDB();
    
    // Verify product exists and is active
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND is_active = TRUE AND status = 'active'");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        errorResponse('Product not found or unavailable');
    }
    
    // Check stock availability
    $availableStock = $product['stock_quantity'];
    
    if ($variantId) {
        $stmt = $db->prepare("SELECT stock_quantity FROM product_variants WHERE id = ? AND product_id = ? AND is_active = TRUE");
        $stmt->execute([$variantId, $productId]);
        $variant = $stmt->fetch();
        
        if (!$variant) {
            errorResponse('Product variant not found');
        }
        
        $availableStock = $variant['stock_quantity'];
    }
    
    if ($availableStock < $quantity) {
        errorResponse("Only $availableStock items available in stock");
    }
    
    // Check current cart quantity for this product
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = session_id();
    
    $sql = "SELECT SUM(quantity) as current_quantity FROM cart_items WHERE product_id = ? AND variant_id = ?";
    $params = [$productId, $variantId];
    
    if ($userId) {
        $sql .= " AND user_id = ?";
        $params[] = $userId;
    } else {
        $sql .= " AND session_id = ?";
        $params[] = $sessionId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $currentQuantity = (int)$stmt->fetchColumn();
    
    $totalQuantity = $currentQuantity + $quantity;
    
    if ($totalQuantity > $availableStock) {
        errorResponse("Cannot add $quantity items. Only " . ($availableStock - $currentQuantity) . " more available");
    }
    
    // Add to cart
    $cartItemId = addToCart($productId, $variantId, $quantity);
    
    if ($cartItemId) {
        // Get updated cart info
        $cartItems = getCartItems();
        $cartTotal = getCartTotal();
        $cartCount = count($cartItems);
        
        // Sync with Firebase for real-time updates
        if ($userId && function_exists('FirebaseAdmin::syncCartData')) {
            try {
                FirebaseAdmin::syncCartData($userId, [
                    'items' => $cartItems,
                    'total' => $cartTotal,
                    'count' => $cartCount,
                    'updated_at' => time()
                ]);
            } catch (Exception $e) {
                error_log("Firebase cart sync error: " . $e->getMessage());
            }
        }
        
        // Log activity
        if ($userId) {
            logActivity($userId, 'add_to_cart', 'product', $productId, "Added product to cart: {$product['name']}", [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity
            ]);
        }
        
        successResponse('Product added to cart successfully', [
            'cart_item_id' => $cartItemId,
            'cart_count' => $cartCount,
            'cart_total' => $cartTotal,
            'product' => [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $variantId ? $variant['price'] : $product['price']
            ]
        ]);
    } else {
        errorResponse('Failed to add product to cart');
    }
    
} catch (Exception $e) {
    error_log("Add to cart error: " . $e->getMessage());
    errorResponse('Failed to add product to cart. Please try again.');
}
?>
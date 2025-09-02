<?php
/**
 * Core Functions for PureFit Business Management System
 * Essential utility functions used throughout the application
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/firebase.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Security Functions
 */

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    // Check if token is expired
    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize input
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate phone number (Bangladesh format)
function isValidBDPhone($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check Bangladesh mobile number patterns
    return preg_match('/^(01[3-9]\d{8}|8801[3-9]\d{8})$/', $phone);
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Authentication Functions
 */

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    static $currentUser = null;
    
    if ($currentUser === null) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch();
    }
    
    return $currentUser;
}

// Check user role
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

// Check if user has any of the specified roles
function hasAnyRole($roles) {
    $user = getCurrentUser();
    return $user && in_array($user['role'], $roles);
}

// Require login
function requireLogin($redirectTo = '/customer/login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirectTo");
        exit();
    }
}

// Require specific role
function requireRole($role, $redirectTo = '/customer/login.php') {
    requireLogin($redirectTo);
    if (!hasRole($role)) {
        header("Location: /403.php");
        exit();
    }
}

// Require admin access
function requireAdmin() {
    requireRole('admin');
}

// Login user
function loginUser($userId, $rememberMe = false) {
    $db = getDB();
    
    // Get user data
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return false;
    }
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    
    // Update last login
    $stmt = $db->prepare("UPDATE users SET last_login = NOW(), login_attempts = 0 WHERE id = ?");
    $stmt->execute([$userId]);
    
    // Create session record
    $sessionToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
    
    $stmt = $db->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $sessionToken,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $expiresAt
    ]);
    
    $_SESSION['session_token'] = $sessionToken;
    
    // Set remember me cookie
    if ($rememberMe) {
        setcookie('remember_token', $sessionToken, time() + (30 * 24 * 60 * 60), '/', '', true, true); // 30 days
    }
    
    // Log activity
    logActivity($userId, 'login', 'user', $userId, 'User logged in');
    
    return true;
}

// Logout user
function logoutUser() {
    $userId = $_SESSION['user_id'] ?? null;
    
    // Invalidate session in database
    if (isset($_SESSION['session_token'])) {
        $db = getDB();
        $stmt = $db->prepare("UPDATE user_sessions SET is_active = FALSE WHERE session_token = ?");
        $stmt->execute([$_SESSION['session_token']]);
    }
    
    // Clear session
    session_destroy();
    
    // Clear remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    
    // Log activity
    if ($userId) {
        logActivity($userId, 'logout', 'user', $userId, 'User logged out');
    }
}

/**
 * Database Helper Functions
 */

// Execute query with error handling
function executeQuery($sql, $params = []) {
    try {
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        throw new Exception("Database operation failed");
    }
}

// Get single record
function getRecord($table, $conditions = [], $columns = '*') {
    $sql = "SELECT $columns FROM $table";
    $params = [];
    
    if (!empty($conditions)) {
        $whereClause = [];
        foreach ($conditions as $column => $value) {
            $whereClause[] = "$column = ?";
            $params[] = $value;
        }
        $sql .= " WHERE " . implode(' AND ', $whereClause);
    }
    
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

// Get multiple records
function getRecords($table, $conditions = [], $columns = '*', $orderBy = '', $limit = '') {
    $sql = "SELECT $columns FROM $table";
    $params = [];
    
    if (!empty($conditions)) {
        $whereClause = [];
        foreach ($conditions as $column => $value) {
            $whereClause[] = "$column = ?";
            $params[] = $value;
        }
        $sql .= " WHERE " . implode(' AND ', $whereClause);
    }
    
    if ($orderBy) {
        $sql .= " ORDER BY $orderBy";
    }
    
    if ($limit) {
        $sql .= " LIMIT $limit";
    }
    
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

// Insert record
function insertRecord($table, $data) {
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    
    $db = getDB();
    $stmt = $db->prepare($sql);
    
    foreach ($data as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    
    $stmt->execute();
    return $db->lastInsertId();
}

// Update record
function updateRecord($table, $data, $conditions) {
    $setClause = [];
    $params = [];
    
    foreach ($data as $column => $value) {
        $setClause[] = "$column = ?";
        $params[] = $value;
    }
    
    $whereClause = [];
    foreach ($conditions as $column => $value) {
        $whereClause[] = "$column = ?";
        $params[] = $value;
    }
    
    $sql = "UPDATE $table SET " . implode(', ', $setClause) . " WHERE " . implode(' AND ', $whereClause);
    
    $stmt = executeQuery($sql, $params);
    return $stmt->rowCount();
}

// Delete record
function deleteRecord($table, $conditions) {
    $whereClause = [];
    $params = [];
    
    foreach ($conditions as $column => $value) {
        $whereClause[] = "$column = ?";
        $params[] = $value;
    }
    
    $sql = "DELETE FROM $table WHERE " . implode(' AND ', $whereClause);
    
    $stmt = executeQuery($sql, $params);
    return $stmt->rowCount();
}

/**
 * Utility Functions
 */

// Generate unique slug
function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

// Format currency for Bangladesh
function formatCurrency($amount, $currency = 'BDT') {
    return $currency . ' ' . number_format($amount, 2);
}

// Format date for Bangladesh
function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

// Format date time
function formatDateTime($datetime, $format = 'd/m/Y h:i A') {
    return date($format, strtotime($datetime));
}

// Time ago function
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

// Generate random string
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)))), 1, $length);
}

// Log activity
function logActivity($userId, $action, $resourceType = null, $resourceId = null, $description = null, $data = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, ip_address, user_agent, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $resourceType,
            $resourceId,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $data ? json_encode($data) : null
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Send email
function sendEmail($to, $subject, $body, $isHTML = true, $attachments = []) {
    // Use PHPMailer or similar library
    // For now, basic mail function
    $headers = [];
    
    if ($isHTML) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
    }
    
    $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
    $headers[] = 'Reply-To: ' . FROM_EMAIL;
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

// Send SMS (Bangladesh SMS gateway)
function sendSMS($phone, $message) {
    // Format phone number for Bangladesh
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 2) === '01') {
        $phone = '880' . $phone;
    } elseif (substr($phone, 0, 3) !== '880') {
        $phone = '880' . $phone;
    }
    
    // Prepare SMS data
    $data = [
        'api_token' => SMS_API_TOKEN,
        'sid' => SMS_SENDER_ID,
        'msisdn' => $phone,
        'sms' => $message,
        'csms_id' => uniqid()
    ];
    
    // Send SMS via API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SMS_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . SMS_API_TOKEN
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

/**
 * File Upload Functions
 */

// Handle file upload
function handleFileUpload($file, $uploadDir, $allowedTypes = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error');
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File too large. Maximum size: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB');
    }
    
    // Get file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check allowed types
    if ($allowedTypes && !in_array($extension, $allowedTypes)) {
        throw new Exception('File type not allowed');
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . '/' . $filename;
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    return $filename;
}

// Handle multiple file uploads
function handleMultipleFileUploads($files, $uploadDir, $allowedTypes = null) {
    $uploadedFiles = [];
    
    if (is_array($files['name'])) {
        for ($i = 0; $i < count($files['name']); $i++) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            if ($file['error'] === UPLOAD_ERR_OK) {
                $uploadedFiles[] = handleFileUpload($file, $uploadDir, $allowedTypes);
            }
        }
    } else {
        $uploadedFiles[] = handleFileUpload($files, $uploadDir, $allowedTypes);
    }
    
    return $uploadedFiles;
}

/**
 * Product Functions
 */

// Get product by ID with all related data
function getProductDetails($productId) {
    $db = getDB();
    
    // Get main product data
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name, b.name as brand_name,
               COALESCE(AVG(pr.rating), 0) as average_rating,
               COUNT(pr.id) as review_count
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN product_reviews pr ON p.id = pr.product_id AND pr.is_approved = TRUE
        WHERE p.id = ? AND p.is_active = TRUE
        GROUP BY p.id
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        return null;
    }
    
    // Get product images
    $stmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, is_primary DESC");
    $stmt->execute([$productId]);
    $product['images'] = $stmt->fetchAll();
    
    // Get product variants
    $stmt = $db->prepare("SELECT * FROM product_variants WHERE product_id = ? AND is_active = TRUE ORDER BY price");
    $stmt->execute([$productId]);
    $product['variants'] = $stmt->fetchAll();
    
    // Get recent reviews
    $stmt = $db->prepare("
        SELECT pr.*, CONCAT(u.first_name, ' ', u.last_name) as reviewer_name
        FROM product_reviews pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.product_id = ? AND pr.is_approved = TRUE
        ORDER BY pr.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$productId]);
    $product['reviews'] = $stmt->fetchAll();
    
    return $product;
}

// Get products with filters
function getProducts($filters = [], $page = 1, $limit = 12) {
    $db = getDB();
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT p.*, c.name as category_name, b.name as brand_name,
                   COALESCE(AVG(pr.rating), 0) as average_rating,
                   COUNT(pr.id) as review_count
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN product_reviews pr ON p.id = pr.product_id AND pr.is_approved = TRUE
            WHERE p.is_active = TRUE";
    
    $params = [];
    
    // Apply filters
    if (!empty($filters['category'])) {
        $sql .= " AND p.category_id = ?";
        $params[] = $filters['category'];
    }
    
    if (!empty($filters['brand'])) {
        $sql .= " AND p.brand_id = ?";
        $params[] = $filters['brand'];
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($filters['min_price'])) {
        $sql .= " AND p.price >= ?";
        $params[] = $filters['min_price'];
    }
    
    if (!empty($filters['max_price'])) {
        $sql .= " AND p.price <= ?";
        $params[] = $filters['max_price'];
    }
    
    if (!empty($filters['featured'])) {
        $sql .= " AND p.is_featured = TRUE";
    }
    
    $sql .= " GROUP BY p.id";
    
    // Apply sorting
    $orderBy = $filters['sort'] ?? 'created_at';
    $direction = $filters['direction'] ?? 'DESC';
    
    switch ($orderBy) {
        case 'price_low':
            $sql .= " ORDER BY p.price ASC";
            break;
        case 'price_high':
            $sql .= " ORDER BY p.price DESC";
            break;
        case 'rating':
            $sql .= " ORDER BY average_rating DESC";
            break;
        case 'popularity':
            $sql .= " ORDER BY review_count DESC";
            break;
        default:
            $sql .= " ORDER BY p.created_at DESC";
    }
    
    $sql .= " LIMIT $limit OFFSET $offset";
    
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Cart Functions
 */

// Add item to cart
function addToCart($productId, $variantId = null, $quantity = 1) {
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = session_id();
    
    $db = getDB();
    
    // Check if item already exists in cart
    $sql = "SELECT * FROM cart_items WHERE product_id = ? AND variant_id = ?";
    $params = [$productId, $variantId];
    
    if ($userId) {
        $sql .= " AND user_id = ?";
        $params[] = $userId;
    } else {
        $sql .= " AND session_id = ?";
        $params[] = $sessionId;
    }
    
    $stmt = executeQuery($sql, $params);
    $existingItem = $stmt->fetch();
    
    if ($existingItem) {
        // Update quantity
        $newQuantity = $existingItem['quantity'] + $quantity;
        updateRecord('cart_items', ['quantity' => $newQuantity], ['id' => $existingItem['id']]);
        return $existingItem['id'];
    } else {
        // Add new item
        $data = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $quantity
        ];
        
        if ($userId) {
            $data['user_id'] = $userId;
        } else {
            $data['session_id'] = $sessionId;
        }
        
        return insertRecord('cart_items', $data);
    }
}

// Get cart items
function getCartItems() {
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = session_id();
    
    $db = getDB();
    
    $sql = "SELECT ci.*, p.name, p.price, p.sale_price, p.image, pv.price as variant_price, pv.name as variant_name
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            LEFT JOIN product_variants pv ON ci.variant_id = pv.id
            WHERE ";
    
    $params = [];
    
    if ($userId) {
        $sql .= "ci.user_id = ?";
        $params[] = $userId;
    } else {
        $sql .= "ci.session_id = ?";
        $params[] = $sessionId;
    }
    
    $sql .= " ORDER BY ci.added_at DESC";
    
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

// Calculate cart total
function getCartTotal() {
    $items = getCartItems();
    $total = 0;
    
    foreach ($items as $item) {
        $price = $item['variant_price'] ?: ($item['sale_price'] ?: $item['price']);
        $total += $price * $item['quantity'];
    }
    
    return $total;
}

/**
 * Order Functions
 */

// Create order from cart
function createOrderFromCart($userId, $shippingAddress, $billingAddress, $paymentMethod) {
    $db = getDB();
    $cartItems = getCartItems();
    
    if (empty($cartItems)) {
        throw new Exception('Cart is empty');
    }
    
    $db->beginTransaction();
    
    try {
        // Calculate totals
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $price = $item['variant_price'] ?: ($item['sale_price'] ?: $item['price']);
            $subtotal += $price * $item['quantity'];
        }
        
        $shippingAmount = $subtotal >= 5000 ? 0 : 100; // Free shipping over 5000 BDT
        $taxAmount = 0; // No tax for now
        $totalAmount = $subtotal + $shippingAmount + $taxAmount;
        
        // Create order
        $orderData = [
            'user_id' => $userId,
            'subtotal' => $subtotal,
            'shipping_amount' => $shippingAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'shipping_address' => json_encode($shippingAddress),
            'billing_address' => json_encode($billingAddress),
            'payment_method' => $paymentMethod,
            'status' => 'pending'
        ];
        
        $orderId = insertRecord('orders', $orderData);
        
        // Add order items
        foreach ($cartItems as $item) {
            $price = $item['variant_price'] ?: ($item['sale_price'] ?: $item['price']);
            $itemData = [
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $price,
                'total_price' => $price * $item['quantity'],
                'product_name' => $item['name'],
                'product_sku' => $item['sku'] ?? 'N/A'
            ];
            
            insertRecord('order_items', $itemData);
            
            // Update inventory
            updateProductStock($item['product_id'], $item['variant_id'], $item['quantity'], 'out', 'sale', $orderId, $userId, 'Order placement');
        }
        
        // Clear cart
        if ($userId) {
            deleteRecord('cart_items', ['user_id' => $userId]);
        } else {
            deleteRecord('cart_items', ['session_id' => session_id()]);
        }
        
        // Create invoice
        $invoiceData = [
            'order_id' => $orderId,
            'user_id' => $userId,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'status' => 'draft'
        ];
        
        insertRecord('invoices', $invoiceData);
        
        $db->commit();
        
        // Log activity
        logActivity($userId, 'order_created', 'order', $orderId, 'Order created successfully');
        
        return $orderId;
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Notification Functions
 */

// Create notification
function createNotification($userId, $type, $title, $message, $data = [], $channels = ['in_app']) {
    $notificationData = [
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'data' => json_encode($data),
        'channels' => json_encode($channels),
        'status' => 'pending'
    ];
    
    return insertRecord('notifications', $notificationData);
}

// Send notification
function sendNotification($notificationId) {
    $notification = getRecord('notifications', ['id' => $notificationId]);
    
    if (!$notification) {
        return false;
    }
    
    $channels = json_decode($notification['channels'], true);
    $success = true;
    
    foreach ($channels as $channel) {
        switch ($channel) {
            case 'email':
                $user = getRecord('users', ['id' => $notification['user_id']]);
                if ($user) {
                    $success &= sendEmail($user['email'], $notification['title'], $notification['message']);
                }
                break;
                
            case 'sms':
                $user = getRecord('users', ['id' => $notification['user_id']]);
                if ($user && $user['phone']) {
                    $success &= sendSMS($user['phone'], $notification['message']);
                }
                break;
                
            case 'push':
                // Implement push notification
                break;
        }
    }
    
    // Update notification status
    updateRecord('notifications', ['status' => $success ? 'sent' : 'failed', 'sent_at' => date('Y-m-d H:i:s')], ['id' => $notificationId]);
    
    return $success;
}

/**
 * System Settings Functions
 */

// Get system setting
function getSetting($key, $default = null) {
    static $settings = [];
    
    if (!isset($settings[$key])) {
        $setting = getRecord('system_settings', ['setting_key' => $key]);
        
        if ($setting) {
            $value = $setting['setting_value'];
            
            // Convert based on type
            switch ($setting['setting_type']) {
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'number':
                    $value = is_numeric($value) ? (float)$value : $default;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            $settings[$key] = $value;
        } else {
            $settings[$key] = $default;
        }
    }
    
    return $settings[$key];
}

// Update system setting
function updateSetting($key, $value, $type = 'string') {
    $userId = $_SESSION['user_id'] ?? null;
    
    // Convert value based on type
    switch ($type) {
        case 'boolean':
            $value = $value ? 'true' : 'false';
            break;
        case 'json':
            $value = is_array($value) ? json_encode($value) : $value;
            break;
        default:
            $value = (string)$value;
    }
    
    $existing = getRecord('system_settings', ['setting_key' => $key]);
    
    if ($existing) {
        return updateRecord('system_settings', [
            'setting_value' => $value,
            'setting_type' => $type,
            'updated_by' => $userId
        ], ['setting_key' => $key]);
    } else {
        return insertRecord('system_settings', [
            'setting_key' => $key,
            'setting_value' => $value,
            'setting_type' => $type,
            'updated_by' => $userId
        ]);
    }
}

/**
 * Error Handling and Logging
 */

// Handle errors gracefully
function handleError($message, $code = 500) {
    error_log("Application Error: $message");
    
    if (APP_DEBUG) {
        die("Error: $message");
    } else {
        header("HTTP/1.1 $code Internal Server Error");
        include __DIR__ . '/../error.php';
        exit();
    }
}

// JSON response helper
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Success response
function successResponse($message, $data = []) {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

// Error response
function errorResponse($message, $statusCode = 400) {
    jsonResponse([
        'success' => false,
        'message' => $message
    ], $statusCode);
}

/**
 * Validation Functions
 */

// Validate required fields
function validateRequired($data, $requiredFields) {
    $errors = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    return $errors;
}

// Validate product data
function validateProductData($data) {
    $errors = validateRequired($data, ['name', 'category_id', 'price']);
    
    if (!empty($data['email']) && !isValidEmail($data['email'])) {
        $errors[] = 'Invalid email format';
    }
    
    if (!empty($data['price']) && (!is_numeric($data['price']) || $data['price'] < 0)) {
        $errors[] = 'Price must be a valid positive number';
    }
    
    return $errors;
}

// Initialize application
function initializeApp() {
    // Set error reporting
    if (APP_DEBUG) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    } else {
        error_reporting(0);
        ini_set('display_errors', 0);
    }
    
    // Set session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check for remember me cookie
    if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
        $db = getDB();
        $stmt = $db->prepare("SELECT user_id FROM user_sessions WHERE session_token = ? AND expires_at > NOW() AND is_active = TRUE");
        $stmt->execute([$_COOKIE['remember_token']]);
        $session = $stmt->fetch();
        
        if ($session) {
            loginUser($session['user_id']);
        } else {
            // Invalid token, clear cookie
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    }
}

// Initialize the application
initializeApp();
?>
<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../config/firebase.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    // Validate input
    if (!$input || !is_array($input)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit();
    }
    
    $username = sanitizeInput($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $remember_me = isset($input['remember_me']) ? (bool)$input['remember_me'] : false;
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit();
    }
    
    // Test database connection
    try {
        $db = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection error in login: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again.']);
        exit();
    }
    
    // Get user by username or email
    $user = $db->fetchOne(
        "SELECT u.*, c.id as customer_id, t.id as technician_id 
         FROM users u 
         LEFT JOIN customers c ON u.id = c.user_id 
         LEFT JOIN technicians t ON u.id = t.user_id 
         WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'",
        [$username, $username]
    );
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit();
    }
    
    if (!verifyPassword($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit();
    }
    
    // Update last login
    $db->update('users', 
        ['last_login' => date('Y-m-d H:i:s')], 
        'id = ?', 
        [$user['id']]
    );
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['customer_id'] = $user['customer_id'];
    $_SESSION['technician_id'] = $user['technician_id'];
    
    // Set remember me cookie if requested
    if ($remember_me) {
        $token = generateToken();
        $db->update('users', 
            ['remember_token' => $token], 
            'id = ?', 
            [$user['id']]
        );
        
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
    }
    
    // Log activity
    logActivity($user['id'], 'login', 'User logged in successfully');
    
    // Send real-time notification via Firebase (optional)
    try {
        $firebase = new FirebaseService();
        $firebase->updateRealtimeData('notifications/' . $user['id'], [
            'title' => 'Login Successful',
            'message' => 'You have successfully logged in to the system',
            'timestamp' => time(),
            'type' => 'success'
        ]);
    } catch (Exception $e) {
        // Log Firebase error but don't fail login
        error_log("Firebase notification error: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user_role' => $user['role'],
        'redirect_url' => getRedirectUrl($user['role'])
    ]);
    
} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    error_log('Login error trace: ' . $e->getTraceAsString());
    
    // In local environment, show more detailed error
    if (Environment::isLocal()) {
        echo json_encode([
            'success' => false, 
            'message' => 'Login error: ' . $e->getMessage(),
            'debug' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
}

function getRedirectUrl($role) {
    switch ($role) {
        case 'admin':
            return 'admin/dashboard.php';
        case 'customer':
            return 'customer/dashboard.php';
        case 'technician':
            return 'technician/dashboard.php';
        default:
            return 'index.php';
    }
}
?>
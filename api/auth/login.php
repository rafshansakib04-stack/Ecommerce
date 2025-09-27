<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../config/firebase.php';
require_once '../../includes/functions.php';

// Log login attempt
ErrorLogger::log('LOGIN_ATTEMPT', 'Login attempt started', [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
    'timestamp' => date('Y-m-d H:i:s')
]);

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
    
    ErrorLogger::log('LOGIN_ATTEMPT', 'Processing login request', [
        'input_type' => $input ? 'json' : 'post',
        'has_input' => !empty($input),
        'input_keys' => $input ? array_keys($input) : []
    ]);
    
    // Validate input
    if (!$input || !is_array($input)) {
        ErrorLogger::log('LOGIN_ERROR', 'Invalid request data', [
            'input' => $input,
            'raw_input' => file_get_contents('php://input')
        ]);
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit();
    }
    
    $username = sanitizeInput($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $remember_me = isset($input['remember_me']) ? (bool)$input['remember_me'] : false;
    
    ErrorLogger::log('LOGIN_ATTEMPT', 'Login credentials received', [
        'username' => $username,
        'password_length' => strlen($password),
        'remember_me' => $remember_me,
        'has_username' => !empty($username),
        'has_password' => !empty($password)
    ]);
    
    if (empty($username) || empty($password)) {
        ErrorLogger::log('LOGIN_ERROR', 'Missing credentials', [
            'username_empty' => empty($username),
            'password_empty' => empty($password)
        ]);
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit();
    }
    
    // Test database connection
    try {
        ErrorLogger::log('LOGIN_ATTEMPT', 'Testing database connection');
        $db = Database::getInstance();
        ErrorLogger::log('LOGIN_ATTEMPT', 'Database connection successful');
    } catch (Exception $e) {
        ErrorLogger::log('LOGIN_ERROR', 'Database connection failed', [
            'error' => $e->getMessage(),
            'username' => $username
        ]);
        echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again.']);
        exit();
    }
    
    // Get user by username or email
    ErrorLogger::log('LOGIN_ATTEMPT', 'Looking up user in database', [
        'username' => $username,
        'query_type' => 'username_or_email'
    ]);
    
    $user = $db->fetchOne(
        "SELECT u.*, c.id as customer_id, t.id as technician_id 
         FROM users u 
         LEFT JOIN customers c ON u.id = c.user_id 
         LEFT JOIN technicians t ON u.id = t.user_id 
         WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'",
        [$username, $username]
    );
    
    if (!$user) {
        ErrorLogger::log('LOGIN_ERROR', 'User not found', [
            'username' => $username,
            'searched_as' => 'username_or_email'
        ]);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit();
    }
    
    ErrorLogger::log('LOGIN_ATTEMPT', 'User found in database', [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'],
        'status' => $user['status']
    ]);
    
    // Verify password
    ErrorLogger::log('LOGIN_ATTEMPT', 'Verifying password');
    $passwordValid = verifyPassword($password, $user['password']);
    
    if (!$passwordValid) {
        ErrorLogger::log('LOGIN_ERROR', 'Invalid password', [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'password_hash' => substr($user['password'], 0, 20) . '...'
        ]);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit();
    }
    
    ErrorLogger::log('LOGIN_SUCCESS', 'Password verification successful', [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role']
    ]);
    
    // Update last login
    ErrorLogger::log('LOGIN_SUCCESS', 'Updating last login timestamp', [
        'user_id' => $user['id']
    ]);
    
    $db->update('users', 
        ['last_login' => date('Y-m-d H:i:s')], 
        'id = ?', 
        [$user['id']]
    );
    
    // Set session variables
    ErrorLogger::log('LOGIN_SUCCESS', 'Setting session variables', [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'session_id' => session_id()
    ]);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['customer_id'] = $user['customer_id'];
    $_SESSION['technician_id'] = $user['technician_id'];
    
    // Set remember me cookie if requested
    if ($remember_me) {
        ErrorLogger::log('LOGIN_SUCCESS', 'Setting remember me cookie', [
            'user_id' => $user['id']
        ]);
        
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
        ErrorLogger::log('LOGIN_SUCCESS', 'Sending Firebase notification');
        $firebase = new FirebaseService();
        $firebase->updateRealtimeData('notifications/' . $user['id'], [
            'title' => 'Login Successful',
            'message' => 'You have successfully logged in to the system',
            'timestamp' => time(),
            'type' => 'success'
        ]);
        ErrorLogger::log('LOGIN_SUCCESS', 'Firebase notification sent successfully');
    } catch (Exception $e) {
        // Log Firebase error but don't fail login
        ErrorLogger::log('LOGIN_WARNING', 'Firebase notification failed', [
            'error' => $e->getMessage(),
            'user_id' => $user['id']
        ]);
    }
    
    $redirectUrl = getRedirectUrl($user['role']);
    
    ErrorLogger::log('LOGIN_SUCCESS', 'Login completed successfully', [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'redirect_url' => $redirectUrl,
        'session_id' => session_id()
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user_role' => $user['role'],
        'redirect_url' => $redirectUrl
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
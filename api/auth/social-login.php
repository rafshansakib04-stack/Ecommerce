<?php
/**
 * Social Login API Endpoint
 * Handles Google and Facebook OAuth login
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

$provider = $input['provider'] ?? '';
$uid = $input['uid'] ?? '';
$email = $input['email'] ?? '';
$name = $input['name'] ?? '';
$photo = $input['photo'] ?? '';

// Validate required fields
if (empty($provider) || empty($uid) || empty($email)) {
    errorResponse('Missing required fields');
}

if (!in_array($provider, ['google', 'facebook'])) {
    errorResponse('Invalid provider');
}

if (!isValidEmail($email)) {
    errorResponse('Invalid email address');
}

try {
    $db = getDB();
    
    // Check if user exists with this Firebase UID
    $stmt = $db->prepare("SELECT * FROM users WHERE firebase_uid = ? AND status != 'suspended'");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    
    if ($user) {
        // User exists, log them in
        if (loginUser($user['id'])) {
            // Update last login and profile photo if needed
            $updateData = ['last_login' => date('Y-m-d H:i:s')];
            if ($photo && empty($user['profile_image'])) {
                $updateData['profile_image'] = $photo;
            }
            
            updateRecord('users', $updateData, ['id' => $user['id']]);
            
            // Log activity
            logActivity($user['id'], 'social_login', 'user', $user['id'], "Logged in via $provider");
            
            successResponse('Login successful', [
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => $user['first_name'] . ' ' . $user['last_name'],
                    'role' => $user['role']
                ],
                'redirect' => $user['role'] === 'admin' ? '/admin/real-time-dashboard.php' : '/customer/dashboard.php'
            ]);
        } else {
            errorResponse('Login failed');
        }
    } else {
        // Check if user exists with this email (without Firebase UID)
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            // Link Firebase UID to existing account
            updateRecord('users', 
                ['firebase_uid' => $uid, 'email_verified' => true], 
                ['id' => $existingUser['id']]
            );
            
            if (loginUser($existingUser['id'])) {
                logActivity($existingUser['id'], 'account_linked', 'user', $existingUser['id'], "Linked $provider account");
                
                successResponse('Account linked and login successful', [
                    'user' => [
                        'id' => $existingUser['id'],
                        'email' => $existingUser['email'],
                        'name' => $existingUser['first_name'] . ' ' . $existingUser['last_name'],
                        'role' => $existingUser['role']
                    ],
                    'redirect' => '/customer/dashboard.php'
                ]);
            } else {
                errorResponse('Login failed');
            }
        } else {
            // New user - redirect to complete registration
            // Store temporary data in session for registration completion
            $_SESSION['social_registration'] = [
                'provider' => $provider,
                'uid' => $uid,
                'email' => $email,
                'name' => $name,
                'photo' => $photo
            ];
            
            successResponse('Please complete registration', [
                'redirect' => '/customer/complete-registration.php'
            ]);
        }
    }
    
} catch (Exception $e) {
    error_log("Social login error: " . $e->getMessage());
    errorResponse('Login failed. Please try again.');
}
?>
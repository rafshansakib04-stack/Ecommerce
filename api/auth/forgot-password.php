<?php
header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $username = sanitizeInput($input['username'] ?? '');
    
    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Username is required']);
        exit();
    }
    
    $db = Database::getInstance();
    
    // Get user by username or email
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'",
        [$username, $username]
    );
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Generate reset token
    $reset_token = generateToken();
    $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Store reset token in database
    $db->update('users', 
        [
            'remember_token' => $reset_token,
            'updated_at' => $token_expiry
        ], 
        'id = ?', 
        [$user['id']]
    );
    
    // Send reset email
    $reset_link = "https://your-domain.com/reset-password.php?token=" . $reset_token;
    
    $email_subject = "Password Reset Request - Water Purifier ERP";
    $email_message = "
    <html>
    <head>
        <title>Password Reset Request</title>
    </head>
    <body>
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #0d6efd;'>Password Reset Request</h2>
            <p>Hello " . $user['username'] . ",</p>
            <p>You have requested to reset your password for the Water Purifier ERP System.</p>
            <p>Click the link below to reset your password:</p>
            <p><a href='" . $reset_link . "' style='background-color: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a></p>
            <p>This link will expire in 1 hour.</p>
            <p>If you did not request this password reset, please ignore this email.</p>
            <hr>
            <p style='color: #666; font-size: 12px;'>This is an automated message from Water Purifier ERP System.</p>
        </div>
    </body>
    </html>";
    
    $email_sent = sendEmail($user['email'], $email_subject, $email_message);
    
    if ($email_sent) {
        // Log activity
        logActivity($user['id'], 'password_reset_requested', 'Password reset requested');
        
        echo json_encode(['success' => true, 'message' => 'Password reset instructions sent to your email']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send reset email. Please try again.']);
    }
    
} catch (Exception $e) {
    error_log('Forgot password error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
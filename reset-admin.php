<?php
/**
 * Reset Admin Password
 * This script resets the admin password to ensure login works
 */

require_once 'config/database.php';
require_once 'config/environment.php';
require_once 'includes/functions.php';

echo "<h2>Water Purifier ERP - Reset Admin Password</h2>";
echo "<p>Environment: " . Environment::detect() . "</p>";

try {
    $db = Database::getInstance();
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    
    // Check if admin user exists
    $adminUser = $db->fetchOne("SELECT * FROM users WHERE username = 'admin'");
    
    if (!$adminUser) {
        echo "<p style='color: orange;'>⚠ Admin user not found. Creating...</p>";
        
        // Create admin user
        $adminId = $db->insert('users', [
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'email' => 'admin@example.com',
            'phone' => '',
            'role' => 'admin',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "<p style='color: green;'>✓ Admin user created successfully!</p>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
        echo "<p><strong>Email:</strong> admin@example.com</p>";
        echo "<p><strong>Role:</strong> admin</p>";
        
    } else {
        echo "<p style='color: green;'>✓ Admin user found!</p>";
        echo "<p>Current username: " . htmlspecialchars($adminUser['username']) . "</p>";
        echo "<p>Current email: " . htmlspecialchars($adminUser['email']) . "</p>";
        echo "<p>Current status: " . htmlspecialchars($adminUser['status']) . "</p>";
        
        // Reset password
        $newPassword = 'admin123';
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $updated = $db->update('users', 
            [
                'password' => $hashedPassword,
                'status' => 'active',
                'email_verified' => 1
            ], 
            'id = ?', 
            [$adminUser['id']]
        );
        
        if ($updated) {
            echo "<p style='color: green;'>✓ Admin password reset successfully!</p>";
            echo "<p><strong>Username:</strong> admin</p>";
            echo "<p><strong>Password:</strong> admin123</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to reset password!</p>";
        }
    }
    
    // Test the login
    echo "<h3>Testing Login...</h3>";
    $testUser = $db->fetchOne("SELECT * FROM users WHERE username = 'admin' AND status = 'active'");
    
    if ($testUser) {
        $testPassword = 'admin123';
        $isValid = verifyPassword($testPassword, $testUser['password']);
        
        if ($isValid) {
            echo "<p style='color: green;'>✓ Login test successful!</p>";
            echo "<p>You can now login with:</p>";
            echo "<ul>";
            echo "<li><strong>Username:</strong> admin</li>";
            echo "<li><strong>Password:</strong> admin123</li>";
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>✗ Login test failed!</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Admin user not found after reset!</p>";
    }
    
    // Show all admin users
    echo "<h3>All Admin Users:</h3>";
    $allAdmins = $db->fetchAll("SELECT id, username, email, status, created_at FROM users WHERE role = 'admin'");
    
    if (empty($allAdmins)) {
        echo "<p>No admin users found.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Status</th><th>Created</th></tr>";
        foreach ($allAdmins as $admin) {
            echo "<tr>";
            echo "<td>" . $admin['id'] . "</td>";
            echo "<td>" . htmlspecialchars($admin['username']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['email']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['status']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration and try again.</p>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<p><a href='index.php'>Try Login Now</a></p>";
echo "<p><a href='debug-login.php'>Run Full Debug Test</a></p>";
echo "<p><a href='test-api-login.php'>Test API Directly</a></p>";
echo "<p><a href='install.php'>Run Full Installation</a></p>";
?>
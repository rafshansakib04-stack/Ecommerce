<?php
/**
 * Debug Login System
 * This script helps debug login issues
 */

require_once 'config/database.php';
require_once 'config/environment.php';
require_once 'includes/functions.php';

echo "<h2>Water Purifier ERP - Login Debug</h2>";
echo "<p>Environment: " . Environment::detect() . "</p>";

// Test database connection
echo "<h3>1. Database Connection Test</h3>";
try {
    $db = Database::getInstance();
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    
    // Test basic query
    $result = $db->fetchOne("SELECT 1 as test");
    if ($result['test'] == 1) {
        echo "<p style='color: green;'>✓ Database query test successful!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    exit();
}

// Check if users table exists
echo "<h3>2. Users Table Check</h3>";
try {
    $users = $db->fetchAll("SELECT COUNT(*) as count FROM users");
    $userCount = $users[0]['count'];
    echo "<p>Found {$userCount} users in database.</p>";
    
    if ($userCount == 0) {
        echo "<p style='color: red;'>✗ No users found! Please run installation first.</p>";
        echo "<p><a href='install.php'>Run Installation</a></p>";
        exit();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Users table error: " . $e->getMessage() . "</p>";
    exit();
}

// Check for admin user
echo "<h3>3. Admin User Check</h3>";
try {
    $adminUsers = $db->fetchAll("SELECT id, username, email, role, status FROM users WHERE role = 'admin'");
    
    if (empty($adminUsers)) {
        echo "<p style='color: red;'>✗ No admin users found!</p>";
        echo "<p>Creating default admin user...</p>";
        
        // Create default admin user
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
        
        echo "<p style='color: green;'>✓ Default admin user created!</p>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
    } else {
        echo "<p style='color: green;'>✓ Found " . count($adminUsers) . " admin user(s):</p>";
        foreach ($adminUsers as $admin) {
            echo "<p>Username: " . htmlspecialchars($admin['username']) . " | Email: " . htmlspecialchars($admin['email']) . " | Status: " . htmlspecialchars($admin['status']) . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Admin user check failed: " . $e->getMessage() . "</p>";
}

// Test password verification
echo "<h3>4. Password Verification Test</h3>";
try {
    $testUser = $db->fetchOne("SELECT * FROM users WHERE username = 'admin' AND status = 'active'");
    
    if ($testUser) {
        echo "<p>Testing password verification for user: " . htmlspecialchars($testUser['username']) . "</p>";
        
        // Test with default password
        $testPassword = 'admin123';
        $isValid = verifyPassword($testPassword, $testUser['password']);
        
        if ($isValid) {
            echo "<p style='color: green;'>✓ Password verification successful!</p>";
        } else {
            echo "<p style='color: red;'>✗ Password verification failed!</p>";
            echo "<p>Updating password...</p>";
            
            // Update password
            $db->update('users', 
                ['password' => password_hash($testPassword, PASSWORD_DEFAULT)], 
                'id = ?', 
                [$testUser['id']]
            );
            
            echo "<p style='color: green;'>✓ Password updated!</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Admin user not found!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Password verification test failed: " . $e->getMessage() . "</p>";
}

// Test login API
echo "<h3>5. Login API Test</h3>";
try {
    // Simulate login request
    $loginData = [
        'username' => 'admin',
        'password' => 'admin123',
        'remember_me' => false
    ];
    
    // Test the login logic
    $user = $db->fetchOne(
        "SELECT u.*, c.id as customer_id, t.id as technician_id 
         FROM users u 
         LEFT JOIN customers c ON u.id = c.user_id 
         LEFT JOIN technicians t ON u.id = t.user_id 
         WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'",
        [$loginData['username'], $loginData['username']]
    );
    
    if ($user) {
        echo "<p style='color: green;'>✓ User found in database!</p>";
        
        if (verifyPassword($loginData['password'], $user['password'])) {
            echo "<p style='color: green;'>✓ Password verification successful!</p>";
            echo "<p>User role: " . htmlspecialchars($user['role']) . "</p>";
            echo "<p>Redirect URL: " . getRedirectUrl($user['role']) . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Password verification failed!</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ User not found!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Login API test failed: " . $e->getMessage() . "</p>";
}

// Test session handling
echo "<h3>6. Session Test</h3>";
try {
    session_start();
    echo "<p>Session ID: " . session_id() . "</p>";
    echo "<p>Session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "</p>";
    echo "<p style='color: green;'>✓ Session handling working!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Session test failed: " . $e->getMessage() . "</p>";
}

// Test Firebase (optional)
echo "<h3>7. Firebase Test (Optional)</h3>";
try {
    $firebase = new FirebaseService();
    echo "<p style='color: green;'>✓ Firebase service loaded!</p>";
} catch (Exception $e) {
    echo "<p style='color: orange;'>⚠ Firebase service error: " . $e->getMessage() . "</p>";
    echo "<p>This is optional and won't affect login functionality.</p>";
}

// Show all users for reference
echo "<h3>8. All Users in Database</h3>";
try {
    $allUsers = $db->fetchAll("SELECT id, username, email, role, status, created_at FROM users ORDER BY created_at DESC");
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr>";
    foreach ($allUsers as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . htmlspecialchars($user['role']) . "</td>";
        echo "<td>" . htmlspecialchars($user['status']) . "</td>";
        echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Failed to fetch users: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Quick Login Test</h3>";
echo "<form method='POST' action='debug-login.php'>";
echo "<p>Username: <input type='text' name='test_username' value='admin'></p>";
echo "<p>Password: <input type='password' name='test_password' value='admin123'></p>";
echo "<p><button type='submit' name='test_login'>Test Login</button></p>";
echo "</form>";

// Handle test login
if (isset($_POST['test_login'])) {
    $username = $_POST['test_username'] ?? '';
    $password = $_POST['test_password'] ?? '';
    
    echo "<h4>Login Test Results:</h4>";
    
    try {
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'",
            [$username, $username]
        );
        
        if ($user) {
            echo "<p style='color: green;'>✓ User found: " . htmlspecialchars($user['username']) . "</p>";
            
            if (verifyPassword($password, $user['password'])) {
                echo "<p style='color: green;'>✓ Password correct!</p>";
                echo "<p style='color: green;'>✓ Login would be successful!</p>";
                echo "<p>Role: " . htmlspecialchars($user['role']) . "</p>";
                echo "<p>Redirect to: " . getRedirectUrl($user['role']) . "</p>";
            } else {
                echo "<p style='color: red;'>✗ Password incorrect!</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ User not found!</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Login test error: " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";
echo "<p><a href='index.php'>← Back to Login</a> | <a href='install.php'>Run Installation</a></p>";
?>
<?php
/**
 * Login System Test Script
 * This script tests the login functionality
 */

require_once 'config/database.php';
require_once 'config/environment.php';
require_once 'includes/functions.php';

echo "<h2>Water Purifier ERP - Login System Test</h2>";
echo "<p>Environment: " . Environment::detect() . "</p>";

try {
    $db = Database::getInstance();
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    
    // Test user query
    $users = $db->fetchAll("SELECT id, username, email, role, status FROM users WHERE status = 'active' LIMIT 5");
    
    if (empty($users)) {
        echo "<p style='color: red;'>✗ No active users found in database.</p>";
        echo "<p>Please run the installation script to create users.</p>";
        echo "<p><a href='install.php'>Run Installation</a></p>";
    } else {
        echo "<p style='color: green;'>✓ Found " . count($users) . " active users.</p>";
        
        echo "<h3>Available Users for Testing:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . htmlspecialchars($user['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>Test Login:</h3>";
        echo "<form method='POST' action='test-login.php'>";
        echo "<p>Username: <input type='text' name='test_username' value='" . htmlspecialchars($users[0]['username']) . "'></p>";
        echo "<p>Password: <input type='password' name='test_password' placeholder='Enter password'></p>";
        echo "<p><button type='submit' name='test_login'>Test Login</button></p>";
        echo "</form>";
    }
    
    // Test login if form submitted
    if (isset($_POST['test_login'])) {
        $username = $_POST['test_username'] ?? '';
        $password = $_POST['test_password'] ?? '';
        
        if (empty($username) || empty($password)) {
            echo "<p style='color: red;'>Please enter both username and password.</p>";
        } else {
            // Test password verification
            $user = $db->fetchOne(
                "SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'",
                [$username, $username]
            );
            
            if ($user) {
                if (verifyPassword($password, $user['password'])) {
                    echo "<p style='color: green;'>✓ Login successful! User: " . htmlspecialchars($user['username']) . " (Role: " . htmlspecialchars($user['role']) . ")</p>";
                } else {
                    echo "<p style='color: red;'>✗ Invalid password for user: " . htmlspecialchars($user['username']) . "</p>";
                }
            } else {
                echo "<p style='color: red;'>✗ User not found: " . htmlspecialchars($username) . "</p>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Back to Login</a> | <a href='setup-database.php'>Database Setup</a></p>";
?>
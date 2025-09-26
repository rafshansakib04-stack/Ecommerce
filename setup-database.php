<?php
/**
 * Database Setup Script
 * This script helps set up the database for the Water Purifier ERP System
 */

require_once 'config/database.php';
require_once 'config/environment.php';

// Check if we're in local environment
if (!Environment::isLocal()) {
    die("This setup script can only be run in local environment for security reasons.");
}

echo "<h2>Water Purifier ERP - Database Setup</h2>";
echo "<p>Environment: " . Environment::detect() . "</p>";

try {
    $db = Database::getInstance();
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    
    // Check if tables exist
    $tables = $db->fetchAll("SHOW TABLES");
    $tableCount = count($tables);
    
    echo "<p>Found {$tableCount} tables in database.</p>";
    
    if ($tableCount === 0) {
        echo "<p style='color: orange;'>⚠ No tables found. Please run the installation script first.</p>";
        echo "<p><a href='install.php' class='btn btn-primary'>Run Installation</a></p>";
    } else {
        echo "<p style='color: green;'>✓ Database tables found.</p>";
        
        // Check for users table
        $users = $db->fetchAll("SELECT COUNT(*) as count FROM users");
        $userCount = $users[0]['count'];
        
        if ($userCount === 0) {
            echo "<p style='color: orange;'>⚠ No users found. Please run the installation script to create admin user.</p>";
            echo "<p><a href='install.php' class='btn btn-primary'>Run Installation</a></p>";
        } else {
            echo "<p style='color: green;'>✓ Found {$userCount} users in database.</p>";
            
            // Show sample users
            $sampleUsers = $db->fetchAll("SELECT username, email, role, status FROM users LIMIT 5");
            echo "<h3>Sample Users:</h3>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>";
            foreach ($sampleUsers as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                echo "<td>" . htmlspecialchars($user['status']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Make sure MySQL is running</li>";
    echo "<li>Check database credentials in config/database.php</li>";
    echo "<li>Create database 'water_purifier_erp' if it doesn't exist</li>";
    echo "<li>Grant proper permissions to the database user</li>";
    echo "</ul>";
    
    echo "<h3>Quick Setup Commands:</h3>";
    echo "<pre>";
    echo "# Create database\n";
    echo "CREATE DATABASE water_purifier_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n\n";
    echo "# Create user (optional)\n";
    echo "CREATE USER 'erp_user'@'localhost' IDENTIFIED BY 'your_password';\n";
    echo "GRANT ALL PRIVILEGES ON water_purifier_erp.* TO 'erp_user'@'localhost';\n";
    echo "FLUSH PRIVILEGES;\n";
    echo "</pre>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Back to Login</a> | <a href='install.php'>Run Installation</a></p>";
?>
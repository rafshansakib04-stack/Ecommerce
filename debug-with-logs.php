<?php
/**
 * Debug with Logs
 * Comprehensive debugging with error log analysis
 */

require_once 'config/database.php';
require_once 'config/environment.php';
require_once 'includes/functions.php';

echo "<h2>Water Purifier ERP - Debug with Error Logs</h2>";
echo "<p>Environment: " . Environment::detect() . "</p>";

// Show log files status
echo "<h3>1. Error Log Files Status</h3>";
$logFiles = ErrorLogger::getLogFiles();
foreach ($logFiles as $type => $file) {
    echo "<p><strong>" . ucfirst($type) . " Log:</strong> ";
    if (file_exists($file)) {
        $size = filesize($file);
        $modified = date('Y-m-d H:i:s', filemtime($file));
        echo "<span style='color: green;'>✓ Exists</span> | Size: " . number_format($size) . " bytes | Modified: $modified</p>";
    } else {
        echo "<span style='color: red;'>✗ Not found</span></p>";
    }
}

// Test database connection with logging
echo "<h3>2. Database Connection Test (with logging)</h3>";
try {
    ErrorLogger::log('DEBUG', 'Testing database connection');
    $db = Database::getInstance();
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    
    // Test query with logging
    ErrorLogger::logDatabase('SELECT_TEST', 'SELECT 1 as test', true, null, ['test' => 'connection']);
    $result = $db->fetchOne("SELECT 1 as test");
    if ($result['test'] == 1) {
        echo "<p style='color: green;'>✓ Database query test successful!</p>";
    }
} catch (Exception $e) {
    ErrorLogger::log('DEBUG_ERROR', 'Database connection failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
}

// Test login system with logging
echo "<h3>3. Login System Test (with logging)</h3>";
try {
    // Check if admin user exists
    ErrorLogger::log('DEBUG', 'Checking for admin user');
    $adminUser = $db->fetchOne("SELECT * FROM users WHERE username = 'admin'");
    
    if (!$adminUser) {
        ErrorLogger::log('DEBUG', 'Admin user not found, creating...');
        echo "<p style='color: orange;'>⚠ Admin user not found. Creating...</p>";
        
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
        
        ErrorLogger::logLogin('admin', true, 'Admin user created successfully', [
            'user_id' => $adminId,
            'action' => 'create_user'
        ]);
        
        echo "<p style='color: green;'>✓ Admin user created!</p>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
    } else {
        ErrorLogger::log('DEBUG', 'Admin user found', [
            'user_id' => $adminUser['id'],
            'username' => $adminUser['username'],
            'status' => $adminUser['status']
        ]);
        echo "<p style='color: green;'>✓ Admin user found!</p>";
        echo "<p>Username: " . htmlspecialchars($adminUser['username']) . "</p>";
        echo "<p>Status: " . htmlspecialchars($adminUser['status']) . "</p>";
    }
    
    // Test password verification
    ErrorLogger::log('DEBUG', 'Testing password verification');
    $testPassword = 'admin123';
    $isValid = verifyPassword($testPassword, $adminUser['password']);
    
    if ($isValid) {
        ErrorLogger::logLogin('admin', true, 'Password verification successful', [
            'user_id' => $adminUser['id']
        ]);
        echo "<p style='color: green;'>✓ Password verification successful!</p>";
    } else {
        ErrorLogger::logLogin('admin', false, 'Password verification failed', [
            'user_id' => $adminUser['id']
        ]);
        echo "<p style='color: red;'>✗ Password verification failed!</p>";
        echo "<p>Updating password...</p>";
        
        $db->update('users', 
            ['password' => password_hash($testPassword, PASSWORD_DEFAULT)], 
            'id = ?', 
            [$adminUser['id']]
        );
        
        ErrorLogger::logLogin('admin', true, 'Password updated successfully', [
            'user_id' => $adminUser['id']
        ]);
        echo "<p style='color: green;'>✓ Password updated!</p>";
    }
    
} catch (Exception $e) {
    ErrorLogger::log('DEBUG_ERROR', 'Login system test failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    echo "<p style='color: red;'>✗ Login system test failed: " . $e->getMessage() . "</p>";
}

// Test API with logging
echo "<h3>4. API Test (with logging)</h3>";
try {
    $testData = [
        'username' => 'admin',
        'password' => 'admin123',
        'remember_me' => false
    ];
    
    ErrorLogger::logAPI('api/auth/login.php', 'POST', true, 'Testing login API', [
        'test_data' => $testData
    ]);
    
    echo "<p style='color: green;'>✓ API logging test successful!</p>";
} catch (Exception $e) {
    ErrorLogger::log('DEBUG_ERROR', 'API test failed', [
        'error' => $e->getMessage()
    ]);
    echo "<p style='color: red;'>✗ API test failed: " . $e->getMessage() . "</p>";
}

// Show recent log entries
echo "<h3>5. Recent Log Entries</h3>";
$recentLogs = ErrorLogger::getLogs(null, 10);
if (empty($recentLogs)) {
    echo "<p>No recent log entries found.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Timestamp</th><th>Category</th><th>Message</th><th>IP</th></tr>";
    foreach ($recentLogs as $log) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($log['timestamp']) . "</td>";
        echo "<td>" . htmlspecialchars($log['category']) . "</td>";
        echo "<td>" . htmlspecialchars($log['message']) . "</td>";
        echo "<td>" . htmlspecialchars($log['ip']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Show log file contents
echo "<h3>6. Log File Contents</h3>";
foreach ($logFiles as $type => $file) {
    if (file_exists($file)) {
        echo "<h4>" . ucfirst($type) . " Log (last 5 entries):</h4>";
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $recentLines = array_slice($lines, -5);
        
        if (empty($recentLines)) {
            echo "<p>No entries in this log file.</p>";
        } else {
            echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 0.9rem;'>";
            foreach ($recentLines as $line) {
                echo htmlspecialchars($line) . "\n";
            }
            echo "</pre>";
        }
    }
}

echo "<hr>";
echo "<h3>Quick Actions:</h3>";
echo "<p><a href='view-logs.php'>View All Logs</a> | <a href='index.php'>Try Login</a> | <a href='reset-admin.php'>Reset Admin</a></p>";
echo "<p><a href='debug-login.php'>Debug Login</a> | <a href='test-api-login.php'>Test API</a> | <a href='setup-database.php'>Database Setup</a></p>";
?>
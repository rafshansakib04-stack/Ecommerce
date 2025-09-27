<?php
/**
 * Test Error Logging System
 * Verify that error logs are being written properly
 */

require_once 'config/database.php';
require_once 'config/environment.php';
require_once 'includes/functions.php';

echo "<h2>Testing Error Logging System</h2>";
echo "<p>Environment: " . Environment::detect() . "</p>";

// Test 1: Basic logging
echo "<h3>1. Testing Basic Logging</h3>";
try {
    ErrorLogger::log('TEST', 'This is a test log entry', [
        'test_type' => 'basic',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    echo "<p style='color: green;'>✓ Basic logging test completed</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Basic logging failed: " . $e->getMessage() . "</p>";
}

// Test 2: Login logging
echo "<h3>2. Testing Login Logging</h3>";
try {
    ErrorLogger::logLogin('testuser', false, 'Test login attempt', [
        'test_type' => 'login_test',
        'ip' => '127.0.0.1'
    ]);
    ErrorLogger::logLogin('testuser', true, 'Test login success', [
        'test_type' => 'login_success',
        'user_id' => 999
    ]);
    echo "<p style='color: green;'>✓ Login logging test completed</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Login logging failed: " . $e->getMessage() . "</p>";
}

// Test 3: Database logging
echo "<h3>3. Testing Database Logging</h3>";
try {
    ErrorLogger::logDatabase('TEST_QUERY', 'SELECT 1 as test', true, null, [
        'test_type' => 'database_test'
    ]);
    echo "<p style='color: green;'>✓ Database logging test completed</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database logging failed: " . $e->getMessage() . "</p>";
}

// Test 4: API logging
echo "<h3>4. Testing API Logging</h3>";
try {
    ErrorLogger::logAPI('test/endpoint', 'POST', true, 'Test response', [
        'test_type' => 'api_test'
    ]);
    echo "<p style='color: green;'>✓ API logging test completed</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ API logging failed: " . $e->getMessage() . "</p>";
}

// Test 5: Error logging
echo "<h3>5. Testing Error Logging</h3>";
try {
    trigger_error("This is a test error", E_USER_WARNING);
    echo "<p style='color: green;'>✓ Error logging test completed</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error logging failed: " . $e->getMessage() . "</p>";
}

// Test 6: Exception logging
echo "<h3>6. Testing Exception Logging</h3>";
try {
    throw new Exception("This is a test exception");
} catch (Exception $e) {
    echo "<p style='color: green;'>✓ Exception logging test completed</p>";
}

// Show log files
echo "<h3>7. Log Files Status</h3>";
$logFiles = ErrorLogger::getLogFiles();
foreach ($logFiles as $type => $file) {
    echo "<p><strong>" . ucfirst($type) . " Log:</strong> ";
    if (file_exists($file)) {
        $size = filesize($file);
        $modified = date('Y-m-d H:i:s', filemtime($file));
        echo "<span style='color: green;'>✓ Exists</span> | Size: " . number_format($size) . " bytes | Modified: $modified</p>";
        
        // Show last few lines
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $recentLines = array_slice($lines, -3);
        if (!empty($recentLines)) {
            echo "<div style='background: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
            echo "<strong>Recent entries:</strong><br>";
            foreach ($recentLines as $line) {
                echo htmlspecialchars($line) . "<br>";
            }
            echo "</div>";
        }
    } else {
        echo "<span style='color: red;'>✗ Not found</span></p>";
    }
}

// Test 7: Read logs programmatically
echo "<h3>8. Reading Logs Programmatically</h3>";
try {
    $recentLogs = ErrorLogger::getLogs(null, 5);
    if (empty($recentLogs)) {
        echo "<p>No recent logs found.</p>";
    } else {
        echo "<p>Found " . count($recentLogs) . " recent log entries:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Timestamp</th><th>Category</th><th>Message</th></tr>";
        foreach ($recentLogs as $log) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($log['timestamp']) . "</td>";
            echo "<td>" . htmlspecialchars($log['category']) . "</td>";
            echo "<td>" . htmlspecialchars($log['message']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Reading logs failed: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Quick Actions:</h3>";
echo "<p><a href='view-logs.php'>View All Logs</a> | <a href='debug-with-logs.php'>Debug with Logs</a> | <a href='index.php'>Try Login</a></p>";
?>
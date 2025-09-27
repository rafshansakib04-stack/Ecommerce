<?php
/**
 * Test Login API Directly
 * This script tests the login API endpoint directly
 */

echo "<h2>Water Purifier ERP - API Login Test</h2>";

// Test data
$testData = [
    'username' => 'admin',
    'password' => 'admin123',
    'remember_me' => false
];

echo "<h3>Testing Login API with:</h3>";
echo "<p>Username: " . htmlspecialchars($testData['username']) . "</p>";
echo "<p>Password: " . htmlspecialchars($testData['password']) . "</p>";

// Make the API call
$url = 'api/auth/login.php';
$postData = json_encode($testData);

echo "<h3>Making API Call...</h3>";
echo "<p>URL: " . $url . "</p>";
echo "<p>Data: " . htmlspecialchars($postData) . "</p>";

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($postData)
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<h3>API Response:</h3>";
echo "<p>HTTP Code: " . $httpCode . "</p>";

if ($error) {
    echo "<p style='color: red;'>cURL Error: " . $error . "</p>";
} else {
    echo "<p>Response: " . htmlspecialchars($response) . "</p>";
    
    // Try to parse JSON response
    $jsonResponse = json_decode($response, true);
    if ($jsonResponse) {
        echo "<h4>Parsed Response:</h4>";
        echo "<pre>" . print_r($jsonResponse, true) . "</pre>";
        
        if (isset($jsonResponse['success']) && $jsonResponse['success']) {
            echo "<p style='color: green;'>✓ Login API working correctly!</p>";
            echo "<p>User Role: " . htmlspecialchars($jsonResponse['user_role'] ?? 'Unknown') . "</p>";
            echo "<p>Redirect URL: " . htmlspecialchars($jsonResponse['redirect_url'] ?? 'Unknown') . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Login API failed!</p>";
            echo "<p>Error: " . htmlspecialchars($jsonResponse['message'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Invalid JSON response!</p>";
    }
}

echo "<hr>";
echo "<h3>Alternative Test Methods:</h3>";
echo "<p><a href='debug-login.php'>Full Debug Test</a></p>";
echo "<p><a href='test-login.php'>Simple Login Test</a></p>";
echo "<p><a href='setup-database.php'>Database Setup Test</a></p>";
echo "<p><a href='index.php'>Back to Login Page</a></p>";
?>
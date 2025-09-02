<?php
/**
 * File Permissions Check API
 * Verifies directory permissions for uploads and cache
 */

header('Content-Type: application/json');

$requiredDirectories = [
    'uploads/products',
    'uploads/documents',
    'uploads/avatars',
    'cache',
    'logs'
];

$permissionResults = [];
$allGood = true;

foreach ($requiredDirectories as $dir) {
    $fullPath = "../../$dir";
    
    // Create directory if it doesn't exist
    if (!is_dir($fullPath)) {
        if (!mkdir($fullPath, 0755, true)) {
            $permissionResults[$dir] = [
                'status' => 'error',
                'message' => 'Cannot create directory'
            ];
            $allGood = false;
            continue;
        }
    }
    
    // Check if writable
    if (!is_writable($fullPath)) {
        $permissionResults[$dir] = [
            'status' => 'error',
            'message' => 'Directory not writable'
        ];
        $allGood = false;
    } else {
        // Test file creation
        $testFile = $fullPath . '/test_' . time() . '.txt';
        if (file_put_contents($testFile, 'test') !== false) {
            unlink($testFile);
            $permissionResults[$dir] = [
                'status' => 'success',
                'message' => 'Writable'
            ];
        } else {
            $permissionResults[$dir] = [
                'status' => 'error',
                'message' => 'Cannot write test file'
            ];
            $allGood = false;
        }
    }
}

// Create .htaccess files for security
$htaccessContent = "Options -Indexes\nDeny from all\n<Files ~ \"\\.(jpg|jpeg|png|gif|pdf|doc|docx)$\">\nAllow from all\n</Files>";

foreach (['uploads/products', 'uploads/documents', 'uploads/avatars'] as $dir) {
    $htaccessPath = "../../$dir/.htaccess";
    if (!file_exists($htaccessPath)) {
        file_put_contents($htaccessPath, $htaccessContent);
    }
}

echo json_encode([
    'success' => $allGood,
    'message' => $allGood ? 'All directories are properly configured' : 'Some directories have permission issues',
    'details' => $permissionResults
]);
?>
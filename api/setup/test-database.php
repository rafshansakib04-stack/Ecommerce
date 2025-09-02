<?php
/**
 * Database Test API
 * Tests database connection and schema
 */

header('Content-Type: application/json');

try {
    // Test basic connection
    require_once '../../config/database.php';
    
    $db = getDB();
    $connection = $db->getConnection();
    
    if (!$connection) {
        throw new Exception('Failed to establish database connection');
    }
    
    // Test if tables exist
    $requiredTables = [
        'users', 'products', 'categories', 'brands', 'orders', 'order_items',
        'cart_items', 'wishlist_items', 'payments', 'service_requests',
        'warranties', 'inventory_movements', 'notifications'
    ];
    
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        $stmt = $connection->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        
        if (!$stmt->fetch()) {
            $missingTables[] = $table;
        }
    }
    
    if (!empty($missingTables)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing tables: ' . implode(', ', $missingTables) . '. Please import database_schema.sql'
        ]);
        exit();
    }
    
    // Test data insertion
    $testQuery = "SELECT COUNT(*) FROM users WHERE role = 'super_admin'";
    $stmt = $connection->prepare($testQuery);
    $stmt->execute();
    $adminCount = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful!',
        'details' => [
            'tables_checked' => count($requiredTables),
            'admin_users' => $adminCount,
            'database_name' => DB_NAME
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
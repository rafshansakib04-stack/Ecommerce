<?php
/**
 * Water Purifier ERP System - Installation Script
 * This script will set up the database and initial configuration
 */

// Prevent direct access if already installed
if (file_exists('config/installed.lock')) {
    die('System is already installed. Delete config/installed.lock to reinstall.');
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'config/database.php';

// Installation status
$installationSteps = [
    'database' => false,
    'tables' => false,
    'settings' => false,
    'admin_user' => false,
    'sample_data' => false,
    'firebase' => false,
    'permissions' => false
];

$errors = [];
$success = [];

// Handle installation process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        // Step 1: Test database connection
        $db = Database::getInstance();
        $installationSteps['database'] = true;
        $success[] = 'Database connection successful';
        
        // Step 2: Create tables
        createTables($db);
        $installationSteps['tables'] = true;
        $success[] = 'Database tables created successfully';
        
        // Step 3: Insert system settings
        insertSystemSettings($db);
        $installationSteps['settings'] = true;
        $success[] = 'System settings configured';
        
        // Step 4: Create admin user
        createAdminUser($db, $_POST);
        $installationSteps['admin_user'] = true;
        $success[] = 'Admin user created successfully';
        
        // Step 5: Insert sample data
        insertSampleData($db);
        $installationSteps['sample_data'] = true;
        $success[] = 'Sample data inserted';
        
        // Step 6: Configure Firebase
        configureFirebase($db, $_POST);
        $installationSteps['firebase'] = true;
        $success[] = 'Firebase configuration completed';
        
        // Step 7: Set permissions
        setPermissions();
        $installationSteps['permissions'] = true;
        $success[] = 'File permissions set';
        
        // Create installation lock file
        file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
        
        $installationComplete = true;
        
    } catch (Exception $e) {
        $errors[] = 'Installation failed: ' . $e->getMessage();
    }
}

function createTables($db) {
    // Read and execute schema file
    $schema = file_get_contents('database/schema.sql');
    $statements = explode(';', $schema);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $db->execute($statement);
        }
    }
    
    // Insert system settings
    $settingsSchema = file_get_contents('database/system_settings.sql');
    $settingsStatements = explode(';', $settingsSchema);
    
    foreach ($settingsStatements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $db->execute($statement);
        }
    }

    // Create AI conversations table if present
    if (file_exists('database/ai_conversations.sql')) {
        $aiSchema = file_get_contents('database/ai_conversations.sql');
        $aiStatements = explode(';', $aiSchema);
        foreach ($aiStatements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $db->execute($statement);
            }
        }
    }

    // Create payments tables if present
    if (file_exists('database/payments.sql')) {
        $paySchema = file_get_contents('database/payments.sql');
        $payStatements = explode(';', $paySchema);
        foreach ($payStatements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $db->execute($statement);
            }
        }
    }

    // Create ledger and credit notes if present
    if (file_exists('database/ledger.sql')) {
        $ledgerSchema = file_get_contents('database/ledger.sql');
        $ledgerStatements = explode(';', $ledgerSchema);
        foreach ($ledgerStatements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $db->execute($statement);
            }
        }
    }

    // Create notifications table if present
    if (file_exists('database/notifications.sql')) {
        $notifSchema = file_get_contents('database/notifications.sql');
        $notifStatements = explode(';', $notifSchema);
        foreach ($notifStatements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $db->execute($statement);
            }
        }
    }
}

function insertSystemSettings($db) {
    // Update company settings with installation data
    $settings = [
        'company_name' => $_POST['company_name'] ?? 'Water Purifier ERP',
        'company_email' => $_POST['company_email'] ?? '',
        'company_phone' => $_POST['company_phone'] ?? '',
        'from_email' => $_POST['company_email'] ?? '',
        'notification_email' => $_POST['company_email'] ?? ''
    ];
    
    foreach ($settings as $key => $value) {
        $db->execute("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ", [$key, $value, $value]);
    }
}

function createAdminUser($db, $data) {
    $username = $data['admin_username'] ?? 'admin';
    $email = $data['admin_email'] ?? '';
    $password = $data['admin_password'] ?? 'admin123';
    $fullName = $data['admin_name'] ?? 'Administrator';
    
    // Create user account
    $userId = $db->insert('users', [
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'email' => $email,
        'phone' => $data['admin_phone'] ?? '',
        'role' => 'admin',
        'status' => 'active',
        'email_verified' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Create admin profile
    $db->insert('admins', [
        'user_id' => $userId,
        'full_name' => $fullName,
        'department' => 'Administration',
        'permissions' => 'all',
        'status' => 'active'
    ]);
}

function insertSampleData($db) {
    // Create sample categories
    $categories = [
        ['name' => 'Water Purifiers', 'description' => 'Water purification systems'],
        ['name' => 'Filters', 'description' => 'Water filter components'],
        ['name' => 'Accessories', 'description' => 'Water purifier accessories'],
        ['name' => 'Maintenance', 'description' => 'Maintenance services']
    ];
    
    foreach ($categories as $category) {
        $db->insert('categories', [
            'name' => $category['name'],
            'description' => $category['description'],
            'status' => 'active'
        ]);
    }
    
    // Create sample products
    $products = [
        [
            'name' => 'RO Water Purifier - Basic',
            'description' => 'Basic RO water purification system',
            'category_id' => 1,
            'sku' => 'RO-BASIC-001',
            'price' => 15000,
            'cost_price' => 12000,
            'stock_quantity' => 10,
            'min_stock_level' => 2,
            'unit' => 'piece'
        ],
        [
            'name' => 'UV Water Purifier',
            'description' => 'UV water purification system',
            'category_id' => 1,
            'sku' => 'UV-001',
            'price' => 8000,
            'cost_price' => 6000,
            'stock_quantity' => 15,
            'min_stock_level' => 3,
            'unit' => 'piece'
        ]
    ];
    
    foreach ($products as $product) {
        $db->insert('products', [
            'name' => $product['name'],
            'description' => $product['description'],
            'category_id' => $product['category_id'],
            'sku' => $product['sku'],
            'price' => $product['price'],
            'cost_price' => $product['cost_price'],
            'stock_quantity' => $product['stock_quantity'],
            'min_stock_level' => $product['min_stock_level'],
            'unit' => $product['unit'],
            'status' => 'active'
        ]);
    }
}

function configureFirebase($db, $data) {
    if (!empty($data['firebase_config'])) {
        $firebaseConfig = json_decode($data['firebase_config'], true);
        
        $firebaseSettings = [
            'firebase_api_key' => $firebaseConfig['apiKey'] ?? '',
            'firebase_auth_domain' => $firebaseConfig['authDomain'] ?? '',
            'firebase_database_url' => $firebaseConfig['databaseURL'] ?? '',
            'firebase_project_id' => $firebaseConfig['projectId'] ?? '',
            'firebase_storage_bucket' => $firebaseConfig['storageBucket'] ?? '',
            'firebase_messaging_sender_id' => $firebaseConfig['messagingSenderId'] ?? '',
            'firebase_app_id' => $firebaseConfig['appId'] ?? ''
        ];
        
        foreach ($firebaseSettings as $key => $value) {
            $db->execute("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ", [$key, $value, $value]);
        }
    }
}

function setPermissions() {
    // Set proper permissions for uploads directory
    if (!is_dir('uploads')) {
        mkdir('uploads', 0755, true);
    }
    
    // Set permissions for config directory
    chmod('config', 0755);
    
    // Create .htaccess for security
    $htaccessContent = '
# Security Headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

# Prevent access to sensitive files
<Files "*.sql">
    Order allow,deny
    Deny from all
</Files>

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>

<Files "installed.lock">
    Order allow,deny
    Deny from all
</Files>
';
    
    file_put_contents('.htaccess', $htaccessContent);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Water Purifier ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .installation-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .installation-header {
            background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step.active {
            background: #0d6efd;
            color: white;
        }
        .step.completed {
            background: #198754;
            color: white;
        }
        .form-section {
            padding: 30px;
        }
        .section-title {
            color: #0d6efd;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .alert-custom {
            border-left: 4px solid #0d6efd;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="installation-container">
        <div class="installation-header">
            <h1><i class="fas fa-tint me-2"></i>Water Purifier ERP</h1>
            <p class="mb-0">System Installation & Configuration</p>
        </div>
        
        <div class="form-section">
            <?php if (isset($installationComplete) && $installationComplete): ?>
            <!-- Installation Complete -->
            <div class="text-center">
                <div class="mb-4">
                    <i class="fas fa-check-circle fa-5x text-success"></i>
                </div>
                <h3 class="text-success">Installation Completed Successfully!</h3>
                <p class="text-muted">Your Water Purifier ERP system is now ready to use.</p>
                
                <div class="alert alert-custom">
                    <h6><i class="fas fa-info-circle me-2"></i>Important Information:</h6>
                    <ul class="mb-0">
                        <li><strong>Admin Username:</strong> <?php echo htmlspecialchars($_POST['admin_username'] ?? 'admin'); ?></li>
                        <li><strong>Admin Password:</strong> <?php echo htmlspecialchars($_POST['admin_password'] ?? 'admin123'); ?></li>
                        <li><strong>Login URL:</strong> <a href="index.php" class="text-decoration-none">index.php</a></li>
                    </ul>
                </div>
                
                <div class="mt-4">
                    <a href="index.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Login to System
                    </a>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Installation Form -->
            <form method="POST">
                <!-- Database Configuration -->
                <div class="section-title">
                    <i class="fas fa-database me-2"></i>Database Configuration
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Make sure your database is created and accessible. The system will create all necessary tables automatically.
                </div>
                
                <!-- Company Information -->
                <div class="section-title">
                    <i class="fas fa-building me-2"></i>Company Information
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="company_name" class="form-label">Company Name *</label>
                        <input type="text" class="form-control" id="company_name" name="company_name" value="Water Purifier ERP" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="company_email" class="form-label">Company Email *</label>
                        <input type="email" class="form-control" id="company_email" name="company_email" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="company_phone" class="form-label">Company Phone</label>
                        <input type="tel" class="form-control" id="company_phone" name="company_phone">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="company_website" class="form-label">Company Website</label>
                        <input type="url" class="form-control" id="company_website" name="company_website">
                    </div>
                </div>
                
                <!-- Admin Account -->
                <div class="section-title">
                    <i class="fas fa-user-shield me-2"></i>Administrator Account
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="admin_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="admin_name" name="admin_name" value="Administrator" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="admin_username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="admin_username" name="admin_username" value="admin" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="admin_email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="admin_email" name="admin_email" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="admin_phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="admin_phone" name="admin_phone">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="admin_password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="admin_password" name="admin_password" value="admin123" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" value="admin123" required>
                    </div>
                </div>
                
                <!-- Firebase Configuration (Optional) -->
                <div class="section-title">
                    <i class="fas fa-fire me-2"></i>Firebase Configuration (Optional)
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Firebase is required for real-time features. You can configure this later in system settings.
                </div>
                <div class="mb-3">
                    <label for="firebase_config" class="form-label">Firebase Config JSON</label>
                    <textarea class="form-control" id="firebase_config" name="firebase_config" rows="6" placeholder='{"apiKey": "...", "authDomain": "...", "databaseURL": "...", "projectId": "...", "storageBucket": "...", "messagingSenderId": "...", "appId": "..."}'></textarea>
                </div>
                
                <!-- Installation Progress -->
                <?php if (!empty($success) || !empty($errors)): ?>
                <div class="section-title">
                    <i class="fas fa-cogs me-2"></i>Installation Progress
                </div>
                
                <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <h6><i class="fas fa-check-circle me-2"></i>Success:</h6>
                    <ul class="mb-0">
                        <?php foreach ($success as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Errors:</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <!-- Install Button -->
                <div class="text-center mt-4">
                    <button type="submit" name="install" class="btn btn-primary btn-lg">
                        <i class="fas fa-download me-2"></i>Install System
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('admin_password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('admin_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>
<?php
/**
 * Water Purifier ERP System - Installation Script
 * Run this script to set up the system for the first time
 */

// Check if already installed
if (file_exists('config/installed.lock')) {
    die('System is already installed. Delete config/installed.lock to reinstall.');
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            $result = testDatabaseConnection($_POST);
            if ($result['success']) {
                $step = 3;
                $success = 'Database connection successful!';
            } else {
                $error = $result['message'];
            }
            break;
            
        case 3:
            $result = createDatabase($_POST);
            if ($result['success']) {
                $step = 4;
                $success = 'Database created successfully!';
            } else {
                $error = $result['message'];
            }
            break;
            
        case 4:
            $result = createAdminUser($_POST);
            if ($result['success']) {
                $step = 5;
                $success = 'Admin user created successfully!';
            } else {
                $error = $result['message'];
            }
            break;
            
        case 5:
            $result = finalizeInstallation($_POST);
            if ($result['success']) {
                $step = 6;
                $success = 'Installation completed successfully!';
            } else {
                $error = $result['message'];
            }
            break;
    }
}

function testDatabaseConnection($data) {
    try {
        $host = $data['db_host'];
        $username = $data['db_username'];
        $password = $data['db_password'];
        $database = $data['db_name'];
        
        $dsn = "mysql:host=$host;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
    }
}

function createDatabase($data) {
    try {
        $host = $data['db_host'];
        $username = $data['db_username'];
        $password = $data['db_password'];
        $database = $data['db_name'];
        
        $dsn = "mysql:host=$host;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$database`");
        
        // Read and execute schema
        $schema = file_get_contents('database/schema.sql');
        $statements = explode(';', $schema);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database creation failed: ' . $e->getMessage()];
    }
}

function createAdminUser($data) {
    try {
        $host = $data['db_host'];
        $username = $data['db_username'];
        $password = $data['db_password'];
        $database = $data['db_name'];
        
        $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // Create admin user
        $adminUsername = $data['admin_username'];
        $adminPassword = password_hash($data['admin_password'], PASSWORD_DEFAULT);
        $adminEmail = $data['admin_email'];
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, status) VALUES (?, ?, ?, 'admin', 'active')");
        $stmt->execute([$adminUsername, $adminPassword, $adminEmail]);
        
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Admin user creation failed: ' . $e->getMessage()];
    }
}

function finalizeInstallation($data) {
    try {
        // Update configuration files
        updateConfigFiles($data);
        
        // Create installed lock file
        file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
        
        // Set proper permissions
        chmod('uploads', 0755);
        chmod('config', 0755);
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Finalization failed: ' . $e->getMessage()];
    }
}

function updateConfigFiles($data) {
    // Update database.php
    $dbConfig = "<?php
// Database Configuration
define('DB_HOST', '{$data['db_host']}');
define('DB_NAME', '{$data['db_name']}');
define('DB_USER', '{$data['db_username']}');
define('DB_PASS', '{$data['db_password']}');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private \$connection;
    private static \$instance = null;
    
    private function __construct() {
        try {
            \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET;
            \$this->connection = new PDO(\$dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException \$e) {
            die(\"Database connection failed: \" . \$e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::\$instance === null) {
            self::\$instance = new self();
        }
        return self::\$instance;
    }
    
    public function getConnection() {
        return \$this->connection;
    }
    
    public function query(\$sql, \$params = []) {
        \$stmt = \$this->connection->prepare(\$sql);
        \$stmt->execute(\$params);
        return \$stmt;
    }
    
    public function fetchAll(\$sql, \$params = []) {
        return \$this->query(\$sql, \$params)->fetchAll();
    }
    
    public function fetchOne(\$sql, \$params = []) {
        return \$this->query(\$sql, \$params)->fetch();
    }
    
    public function insert(\$table, \$data) {
        \$columns = implode(',', array_keys(\$data));
        \$placeholders = ':' . implode(', :', array_keys(\$data));
        \$sql = \"INSERT INTO {\$table} ({\$columns}) VALUES ({\$placeholders})\";
        \$this->query(\$sql, \$data);
        return \$this->connection->lastInsertId();
    }
    
    public function update(\$table, \$data, \$where, \$whereParams = []) {
        \$set = [];
        foreach (\$data as \$key => \$value) {
            \$set[] = \"{\$key} = :{\$key}\";
        }
        \$sql = \"UPDATE {\$table} SET \" . implode(', ', \$set) . \" WHERE {\$where}\";
        \$params = array_merge(\$data, \$whereParams);
        return \$this->query(\$sql, \$params)->rowCount();
    }
    
    public function delete(\$table, \$where, \$params = []) {
        \$sql = \"DELETE FROM {\$table} WHERE {\$where}\";
        return \$this->query(\$sql, \$params)->rowCount();
    }
    
    public function beginTransaction() {
        return \$this->connection->beginTransaction();
    }
    
    public function commit() {
        return \$this->connection->commit();
    }
    
    public function rollback() {
        return \$this->connection->rollback();
    }
}
?>";
    
    file_put_contents('config/database.php', $dbConfig);
    
    // Update system settings
    $settings = [
        'company_name' => $data['company_name'] ?? 'Water Purifier ERP',
        'company_email' => $data['company_email'] ?? 'info@waterpurifiererp.com',
        'company_phone' => $data['company_phone'] ?? '+91-9876543210',
        'tax_rate' => $data['tax_rate'] ?? '18',
        'currency' => $data['currency'] ?? 'INR',
        'timezone' => $data['timezone'] ?? 'Asia/Kolkata'
    ];
    
    // These would be inserted into the database in a real implementation
    return true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Purifier ERP - Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .install-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
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
    </style>
</head>
<body>
    <div class="container-fluid min-vh-100 d-flex align-items-center">
        <div class="install-container w-100">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white text-center">
                    <h2 class="mb-0">
                        <i class="fas fa-tint me-2"></i>Water Purifier ERP Installation
                    </h2>
                </div>
                <div class="card-body">
                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
                        <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
                        <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">3</div>
                        <div class="step <?php echo $step >= 4 ? ($step > 4 ? 'completed' : 'active') : ''; ?>">4</div>
                        <div class="step <?php echo $step >= 5 ? ($step > 5 ? 'completed' : 'active') : ''; ?>">5</div>
                    </div>
                    
                    <!-- Alerts -->
                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Step Content -->
                    <?php if ($step == 1): ?>
                    <div class="text-center">
                        <i class="fas fa-rocket fa-4x text-primary mb-4"></i>
                        <h3>Welcome to Water Purifier ERP</h3>
                        <p class="text-muted">Let's set up your system step by step.</p>
                        <div class="mt-4">
                            <h5>System Requirements</h5>
                            <ul class="list-unstyled text-start">
                                <li><i class="fas fa-check text-success me-2"></i>PHP 8.4.1 or higher</li>
                                <li><i class="fas fa-check text-success me-2"></i>MySQL 8.0 or higher</li>
                                <li><i class="fas fa-check text-success me-2"></i>Web server (Apache/Nginx)</li>
                                <li><i class="fas fa-check text-success me-2"></i>Firebase project (optional)</li>
                            </ul>
                        </div>
                        <a href="?step=2" class="btn btn-primary btn-lg">
                            <i class="fas fa-arrow-right me-2"></i>Start Installation
                        </a>
                    </div>
                    
                    <?php elseif ($step == 2): ?>
                    <h3>Database Configuration</h3>
                    <p class="text-muted">Enter your database connection details.</p>
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="db_host" class="form-label">Database Host</label>
                                <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="db_name" class="form-label">Database Name</label>
                                <input type="text" class="form-control" id="db_name" name="db_name" value="water_purifier_erp" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="db_username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="db_username" name="db_username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="db_password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="db_password" name="db_password">
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-database me-2"></i>Test Connection
                            </button>
                        </div>
                    </form>
                    
                    <?php elseif ($step == 3): ?>
                    <h3>Create Database</h3>
                    <p class="text-muted">The system will create the database and tables.</p>
                    <form method="POST">
                        <input type="hidden" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host']); ?>">
                        <input type="hidden" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name']); ?>">
                        <input type="hidden" name="db_username" value="<?php echo htmlspecialchars($_POST['db_username']); ?>">
                        <input type="hidden" name="db_password" value="<?php echo htmlspecialchars($_POST['db_password']); ?>">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This will create the database and all required tables.
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-cogs me-2"></i>Create Database
                            </button>
                        </div>
                    </form>
                    
                    <?php elseif ($step == 4): ?>
                    <h3>Create Admin User</h3>
                    <p class="text-muted">Create the administrator account.</p>
                    <form method="POST">
                        <input type="hidden" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host']); ?>">
                        <input type="hidden" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name']); ?>">
                        <input type="hidden" name="db_username" value="<?php echo htmlspecialchars($_POST['db_username']); ?>">
                        <input type="hidden" name="db_password" value="<?php echo htmlspecialchars($_POST['db_password']); ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="admin_username" class="form-label">Admin Username</label>
                                <input type="text" class="form-control" id="admin_username" name="admin_username" value="admin" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="admin_email" class="form-label">Admin Email</label>
                                <input type="email" class="form-control" id="admin_email" name="admin_email" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="admin_password" class="form-label">Admin Password</label>
                                <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-shield me-2"></i>Create Admin
                            </button>
                        </div>
                    </form>
                    
                    <?php elseif ($step == 5): ?>
                    <h3>System Configuration</h3>
                    <p class="text-muted">Configure basic system settings.</p>
                    <form method="POST">
                        <input type="hidden" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host']); ?>">
                        <input type="hidden" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name']); ?>">
                        <input type="hidden" name="db_username" value="<?php echo htmlspecialchars($_POST['db_username']); ?>">
                        <input type="hidden" name="db_password" value="<?php echo htmlspecialchars($_POST['db_password']); ?>">
                        <input type="hidden" name="admin_username" value="<?php echo htmlspecialchars($_POST['admin_username']); ?>">
                        <input type="hidden" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email']); ?>">
                        <input type="hidden" name="admin_password" value="<?php echo htmlspecialchars($_POST['admin_password']); ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" value="Water Purifier ERP">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="company_email" class="form-label">Company Email</label>
                                <input type="email" class="form-control" id="company_email" name="company_email" value="info@waterpurifiererp.com">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="company_phone" class="form-label">Company Phone</label>
                                <input type="tel" class="form-control" id="company_phone" name="company_phone" value="+91-9876543210">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                                <input type="number" class="form-control" id="tax_rate" name="tax_rate" value="18" step="0.01">
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check me-2"></i>Complete Installation
                            </button>
                        </div>
                    </form>
                    
                    <?php elseif ($step == 6): ?>
                    <div class="text-center">
                        <i class="fas fa-check-circle fa-4x text-success mb-4"></i>
                        <h3>Installation Complete!</h3>
                        <p class="text-muted">Your Water Purifier ERP system is ready to use.</p>
                        <div class="alert alert-info">
                            <strong>Default Login Credentials:</strong><br>
                            Username: <?php echo htmlspecialchars($_POST['admin_username']); ?><br>
                            Password: [Your chosen password]
                        </div>
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to System
                            </a>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <strong>Important:</strong> Delete the install.php file for security reasons.
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('admin_password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
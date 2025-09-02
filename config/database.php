<?php
/**
 * Database Configuration for PureFit Business Management System
 * Handles both MySQL and Firebase connections
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'purefit_business');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Firebase configuration
define('FIREBASE_PROJECT_ID', 'purefit-8eff0');
define('FIREBASE_API_KEY', 'AIzaSyAoOEEoQsA_9SniUooBiAuWErJQgVnv71I');
define('FIREBASE_AUTH_DOMAIN', 'purefit-8eff0.firebaseapp.com');
define('FIREBASE_STORAGE_BUCKET', 'purefit-8eff0.firebasestorage.app');
define('FIREBASE_MESSAGING_SENDER_ID', '140765611062');
define('FIREBASE_APP_ID', '1:140765611062:web:201e0cc743322901b74497');
define('FIREBASE_MEASUREMENT_ID', 'G-73HLK8QC46');

// OAuth Configuration
define('GOOGLE_CLIENT_ID', '632626262725-21duevi4qhbnol2d28fj3i49lqt06p2k.apps.googleusercontent.com');
define('FACEBOOK_APP_ID', '1767235537207404');
define('FACEBOOK_APP_SECRET', 'e2e8a51b6376b081c9fcd63b101e5223');

// Application settings
define('APP_NAME', 'PureFit Bangladesh');
define('APP_URL', 'https://purefitbd.com');
define('APP_ENV', 'production'); // development, staging, production
define('APP_DEBUG', false);

// Security settings
define('JWT_SECRET', 'your-jwt-secret-key-here-change-in-production');
define('ENCRYPTION_KEY', 'your-32-character-encryption-key-here');
define('SESSION_LIFETIME', 86400); // 24 hours
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour

// File upload settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'txt']);

// Email settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'purefitbd@gmail.com');
define('SMTP_PASSWORD', ''); // Set your app password
define('SMTP_ENCRYPTION', 'tls');
define('FROM_EMAIL', 'noreply@purefitbd.com');
define('FROM_NAME', 'PureFit Bangladesh');

// SMS settings (for Bangladesh SMS gateways)
define('SMS_GATEWAY', 'ssl_wireless'); // ssl_wireless, robi, grameenphone
define('SMS_API_URL', 'https://smsplus.sslwireless.com/api/v3/send-sms');
define('SMS_API_TOKEN', ''); // Set your SMS API token
define('SMS_SENDER_ID', 'PureFit');

// Payment gateway settings
define('BKASH_APP_KEY', ''); // Set your bKash app key
define('BKASH_APP_SECRET', ''); // Set your bKash app secret
define('BKASH_USERNAME', ''); // Set your bKash username
define('BKASH_PASSWORD', ''); // Set your bKash password
define('BKASH_BASE_URL', 'https://tokenized.pay.bka.sh/v1.2.0-beta');

define('NAGAD_MERCHANT_ID', ''); // Set your Nagad merchant ID
define('NAGAD_MERCHANT_PRIVATE_KEY', ''); // Set your Nagad private key
define('NAGAD_PGP_PUBLIC_KEY', ''); // Set Nagad PGP public key

// Business settings
define('BUSINESS_NAME', 'PureFit Bangladesh Ltd.');
define('BUSINESS_ADDRESS', 'House 123, Road 456, Dhanmondi, Dhaka-1205, Bangladesh');
define('BUSINESS_PHONE', '+880-1700-123456');
define('BUSINESS_EMAIL', 'info@purefitbd.com');
define('BUSINESS_WHATSAPP', '+8801700123456');

// Default timezone for Bangladesh
date_default_timezone_set('Asia/Dhaka');

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    public function query($sql) {
        return $this->connection->query($sql);
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    public function commit() {
        return $this->connection->commit();
    }

    public function rollback() {
        return $this->connection->rollback();
    }
}

// Global database instance
function getDB() {
    return Database::getInstance();
}

// Test database connection
try {
    $db = getDB();
    if (APP_DEBUG) {
        error_log("Database connected successfully");
    }
} catch (Exception $e) {
    if (APP_DEBUG) {
        error_log("Database connection test failed: " . $e->getMessage());
    }
}
?>
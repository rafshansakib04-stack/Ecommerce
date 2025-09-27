<?php
// Environment Detection and Configuration
class Environment {
    private static $environment = null;
    private static $config = null;
    
    public static function detect() {
        if (self::$environment !== null) {
            return self::$environment;
        }
        
        // Check for local development indicators
        $localIndicators = [
            $_SERVER['HTTP_HOST'] === 'localhost',
            $_SERVER['HTTP_HOST'] === '127.0.0.1',
            strpos($_SERVER['HTTP_HOST'], '.local') !== false,
            strpos($_SERVER['HTTP_HOST'], '.test') !== false,
            strpos($_SERVER['HTTP_HOST'], '.dev') !== false,
            isset($_SERVER['HTTP_X_FORWARDED_HOST']) && strpos($_SERVER['HTTP_X_FORWARDED_HOST'], 'localhost') !== false,
            // Check for common local development ports
            in_array($_SERVER['SERVER_PORT'], ['3000', '8000', '8080', '9000', '5000']),
            // Check for XAMPP, WAMP, MAMP indicators
            strpos($_SERVER['DOCUMENT_ROOT'], 'xampp') !== false,
            strpos($_SERVER['DOCUMENT_ROOT'], 'wamp') !== false,
            strpos($_SERVER['DOCUMENT_ROOT'], 'mamp') !== false,
            strpos($_SERVER['DOCUMENT_ROOT'], 'htdocs') !== false,
            // Check for development environment variables
            getenv('APP_ENV') === 'local' || getenv('APP_ENV') === 'development',
            // Check for common development file indicators
            file_exists($_SERVER['DOCUMENT_ROOT'] . '/.env.local'),
            file_exists($_SERVER['DOCUMENT_ROOT'] . '/.env.development'),
            // Check for development server indicators
            strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'Development') !== false,
            strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'Built-in') !== false,
        ];
        
        // If any local indicator is true, it's local
        $isLocal = in_array(true, $localIndicators, true);
        
        self::$environment = $isLocal ? 'local' : 'production';
        
        // Log environment detection for debugging
        error_log("Environment detected: " . self::$environment);
        error_log("HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set'));
        error_log("SERVER_PORT: " . ($_SERVER['SERVER_PORT'] ?? 'not set'));
        error_log("DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'not set'));
        
        return self::$environment;
    }
    
    public static function getConfig() {
        if (self::$config !== null) {
            return self::$config;
        }
        
        $env = self::detect();
        
        // Load .env file if it exists
        self::loadEnvFile();
        
        if ($env === 'local') {
            self::$config = [
                'database' => [
                    'host' => getenv('DB_HOST') ?: 'localhost',
                    'name' => getenv('DB_NAME') ?: 'water_purifier_erp',
                    'user' => getenv('DB_USER') ?: 'root',
                    'pass' => getenv('DB_PASS') ?: '',
                    'charset' => 'utf8mb4'
                ],
                'firebase' => [
                    'project_id' => getenv('FIREBASE_PROJECT_ID') ?: 'water-purifier-erp-dev',
                    'api_key' => getenv('FIREBASE_API_KEY') ?: 'dev-firebase-api-key',
                    'auth_domain' => getenv('FIREBASE_AUTH_DOMAIN') ?: 'water-purifier-erp-dev.firebaseapp.com',
                    'database_url' => getenv('FIREBASE_DATABASE_URL') ?: 'https://water-purifier-erp-dev-default-rtdb.firebaseio.com/'
                ],
                'debug' => true,
                'error_reporting' => E_ALL,
                'display_errors' => true
            ];
        } else {
            self::$config = [
                'database' => [
                    'host' => getenv('DB_HOST') ?: 'localhost',
                    'name' => getenv('DB_NAME') ?: 'water_purifier_erp',
                    'user' => getenv('DB_USER') ?: 'root',
                    'pass' => getenv('DB_PASS') ?: '',
                    'charset' => 'utf8mb4'
                ],
                'firebase' => [
                    'project_id' => getenv('FIREBASE_PROJECT_ID') ?: 'water-purifier-erp',
                    'api_key' => getenv('FIREBASE_API_KEY') ?: 'your-firebase-api-key',
                    'auth_domain' => getenv('FIREBASE_AUTH_DOMAIN') ?: 'water-purifier-erp.firebaseapp.com',
                    'database_url' => getenv('FIREBASE_DATABASE_URL') ?: 'https://water-purifier-erp-default-rtdb.firebaseio.com/'
                ],
                'debug' => false,
                'error_reporting' => E_ERROR | E_WARNING | E_PARSE,
                'display_errors' => false
            ];
        }
        
        return self::$config;
    }
    
    private static function loadEnvFile() {
        $envFile = $_SERVER['DOCUMENT_ROOT'] . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    if (!getenv($key)) {
                        putenv("$key=$value");
                    }
                }
            }
        }
    }
    
    public static function isLocal() {
        return self::detect() === 'local';
    }
    
    public static function isProduction() {
        return self::detect() === 'production';
    }
    
    public static function getDatabaseConfig() {
        return self::getConfig()['database'];
    }
    
    public static function getFirebaseConfig() {
        return self::getConfig()['firebase'];
    }
    
    public static function setupErrorReporting() {
        $config = self::getConfig();
        error_reporting($config['error_reporting']);
        ini_set('display_errors', $config['display_errors'] ? '1' : '0');
    }
}
?>
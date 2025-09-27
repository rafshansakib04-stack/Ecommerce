<?php
// Database Configuration with Environment Detection
require_once __DIR__ . '/environment.php';
require_once __DIR__ . '/error-logger.php';

// Setup error reporting based on environment
Environment::setupErrorReporting();

// Initialize error logger
ErrorLogger::init();

// Get database configuration based on environment
$dbConfig = Environment::getDatabaseConfig();

define('DB_HOST', $dbConfig['host']);
define('DB_NAME', $dbConfig['name']);
define('DB_USER', $dbConfig['user']);
define('DB_PASS', $dbConfig['pass']);
define('DB_CHARSET', $dbConfig['charset']);

class Database {
    private $connection;
    private static $instance = null;
    
    private function __construct() {
        $startTime = microtime(true);
        
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            ErrorLogger::log('DATABASE', 'Attempting database connection', [
                'host' => DB_HOST,
                'database' => DB_NAME,
                'user' => DB_USER,
                'charset' => DB_CHARSET
            ]);
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10,
            ]);
            
            // Test the connection
            $this->connection->query("SELECT 1");
            
            $connectionTime = microtime(true) - $startTime;
            ErrorLogger::log('DATABASE', 'Database connection successful', [
                'connection_time' => $connectionTime,
                'host' => DB_HOST,
                'database' => DB_NAME
            ]);
            
        } catch (PDOException $e) {
            $connectionTime = microtime(true) - $startTime;
            
            // Log detailed error information
            ErrorLogger::log('DATABASE_ERROR', 'Database connection failed', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'connection_time' => $connectionTime,
                'host' => DB_HOST,
                'database' => DB_NAME,
                'user' => DB_USER,
                'charset' => DB_CHARSET,
                'pdo_error_info' => $e->errorInfo ?? null
            ]);
            
            // Show user-friendly error message
            if (Environment::isLocal()) {
                die("Database connection failed: " . $e->getMessage() . 
                    "<br><br>Please check your database configuration in config/database.php<br>" .
                    "Check error logs for more details: " . ErrorLogger::getLogFile());
            } else {
                die("Database connection failed. Please contact the administrator.");
            }
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
    
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        return $this->connection->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE {$where}";
        $params = array_merge($data, $whereParams);
        return $this->query($sql, $params)->rowCount();
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params)->rowCount();
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
?>
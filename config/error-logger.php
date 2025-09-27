<?php
/**
 * Comprehensive Error Logger
 * Writes to actual log files for debugging
 */

class ErrorLogger {
    private static $logDir = null;
    private static $errorLogFile = null;
    private static $fatalLogFile = null;
    private static $loginLogFile = null;
    private static $databaseLogFile = null;
    private static $apiLogFile = null;
    
    public static function init() {
        // Set log directory
        self::$logDir = __DIR__ . '/../logs/';
        
        // Create logs directory if it doesn't exist
        if (!file_exists(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
        
        // Set log file paths
        $date = date('Y-m-d');
        self::$errorLogFile = self::$logDir . "error-{$date}.log";
        self::$fatalLogFile = self::$logDir . "fatal-{$date}.log";
        self::$loginLogFile = self::$logDir . "login-{$date}.log";
        self::$databaseLogFile = self::$logDir . "database-{$date}.log";
        self::$apiLogFile = self::$logDir . "api-{$date}.log";
        
        // Set custom error handlers
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        
        // Log system startup
        self::writeToFile(self::$errorLogFile, "SYSTEM", "Error logger initialized", [
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'log_directory' => self::$logDir
        ]);
    }
    
    public static function handleError($severity, $message, $file, $line) {
        $errorTypes = [
            E_ERROR => 'FATAL',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        ];
        
        $type = $errorTypes[$severity] ?? 'UNKNOWN';
        
        $context = [
            'type' => $type,
            'severity' => $severity,
            'file' => $file,
            'line' => $line,
            'message' => $message,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ];
        
        // Write to appropriate log file
        if (in_array($type, ['FATAL', 'CORE_ERROR', 'COMPILE_ERROR', 'USER_ERROR'])) {
            self::writeToFile(self::$fatalLogFile, 'FATAL_ERROR', $message, $context);
        } else {
            self::writeToFile(self::$errorLogFile, 'PHP_ERROR', $message, $context);
        }
        
        // Also write to PHP error log
        error_log("[$type] $message in $file on line $line");
        
        // Don't execute PHP internal error handler for fatal errors
        return !in_array($type, ['FATAL', 'CORE_ERROR', 'COMPILE_ERROR']);
    }
    
    public static function handleException($exception) {
        $context = [
            'type' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'trace' => $exception->getTraceAsString()
        ];
        
        self::writeToFile(self::$fatalLogFile, 'EXCEPTION', $exception->getMessage(), $context);
        error_log("Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    }
    
    public static function handleShutdown() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $context = [
                'type' => 'SHUTDOWN_ERROR',
                'file' => $error['file'],
                'line' => $error['line'],
                'message' => $error['message']
            ];
            
            self::writeToFile(self::$fatalLogFile, 'SHUTDOWN_FATAL', $error['message'], $context);
        }
    }
    
    public static function log($category, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'CLI';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'category' => $category,
            'message' => $message,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'request_uri' => $requestUri,
            'context' => $context
        ];
        
        // Write to general error log
        self::writeToFile(self::$errorLogFile, $category, $message, $logEntry);
    }
    
    public static function logLogin($username, $success, $message, $context = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'username' => $username,
            'success' => $success,
            'message' => $message,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'session_id' => session_id(),
            'context' => $context
        ];
        
        self::writeToFile(self::$loginLogFile, 'LOGIN', $message, $logEntry);
    }
    
    public static function logDatabase($operation, $query, $success, $error = null, $context = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'operation' => $operation,
            'query' => $query,
            'success' => $success,
            'error' => $error,
            'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)),
            'context' => $context
        ];
        
        self::writeToFile(self::$databaseLogFile, 'DATABASE', $operation, $logEntry);
    }
    
    public static function logAPI($endpoint, $method, $success, $response, $context = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoint' => $endpoint,
            'method' => $method,
            'success' => $success,
            'response' => $response,
            'request_data' => $_POST ?? $_GET ?? [],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'context' => $context
        ];
        
        self::writeToFile(self::$apiLogFile, 'API', "$method $endpoint", $logEntry);
    }
    
    private static function writeToFile($file, $category, $message, $context) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'CLI';
        
        $logLine = sprintf(
            "[%s] [%s] [%s] [%s] %s | %s\n",
            $timestamp,
            $category,
            $ip,
            $requestUri,
            $message,
            json_encode($context)
        );
        
        file_put_contents($file, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    public static function getLogs($category = null, $limit = 100) {
        $logFile = self::$errorLogFile;
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $logs = [];
        
        foreach (array_reverse($lines) as $line) {
            if (empty($line)) continue;
            
            // Parse log line
            if (preg_match('/^\[([^\]]+)\] \[([^\]]+)\] \[([^\]]+)\] \[([^\]]+)\] (.+) \| (.+)$/', $line, $matches)) {
                $logEntry = [
                    'timestamp' => $matches[1],
                    'category' => $matches[2],
                    'ip' => $matches[3],
                    'request_uri' => $matches[4],
                    'message' => $matches[5],
                    'context' => json_decode($matches[6], true) ?: []
                ];
                
                if ($category && $logEntry['category'] !== $category) {
                    continue;
                }
                
                $logs[] = $logEntry;
                
                if (count($logs) >= $limit) {
                    break;
                }
            }
        }
        
        return $logs;
    }
    
    public static function getLogFiles() {
        return [
            'error' => self::$errorLogFile,
            'fatal' => self::$fatalLogFile,
            'login' => self::$loginLogFile,
            'database' => self::$databaseLogFile,
            'api' => self::$apiLogFile
        ];
    }
    
    public static function clearLogs() {
        $files = self::getLogFiles();
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
    
    public static function getLogFile($type = 'error') {
        $files = self::getLogFiles();
        return $files[$type] ?? null;
    }
    
    public static function getLogPath() {
        return self::$logDir;
    }
}
?>
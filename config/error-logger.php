<?php
/**
 * Error Logger Configuration
 * Centralized error logging for the Water Purifier ERP System
 */

class ErrorLogger {
    private static $logFile = null;
    private static $logPath = null;
    
    public static function init() {
        // Set log file path
        self::$logPath = __DIR__ . '/../logs/';
        
        // Create logs directory if it doesn't exist
        if (!file_exists(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
        
        // Set log file with date
        self::$logFile = self::$logPath . 'error-' . date('Y-m-d') . '.log';
        
        // Set custom error handler
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        
        // Log system startup
        self::log('SYSTEM', 'Error logger initialized', [
            'log_file' => self::$logFile,
            'log_path' => self::$logPath,
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
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
        
        self::log('PHP_ERROR', $message, [
            'type' => $type,
            'severity' => $severity,
            'file' => $file,
            'line' => $line,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ]);
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    public static function handleException($exception) {
        self::log('EXCEPTION', $exception->getMessage(), [
            'type' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'code' => $exception->getCode()
        ]);
        
        // Log to PHP error log as well
        error_log("Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
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
        
        $logLine = json_encode($logEntry) . "\n";
        
        // Write to file
        file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Also write to PHP error log for critical errors
        if (in_array($category, ['FATAL', 'EXCEPTION', 'DATABASE_ERROR', 'LOGIN_ERROR'])) {
            error_log("[$category] $message - " . json_encode($context));
        }
    }
    
    public static function logLogin($username, $success, $message, $context = []) {
        self::log('LOGIN', $message, array_merge([
            'username' => $username,
            'success' => $success,
            'session_id' => session_id(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ], $context));
    }
    
    public static function logDatabase($operation, $query, $success, $error = null, $context = []) {
        self::log('DATABASE', $operation, array_merge([
            'query' => $query,
            'success' => $success,
            'error' => $error,
            'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
        ], $context));
    }
    
    public static function logAPI($endpoint, $method, $success, $response, $context = []) {
        self::log('API', "$method $endpoint", array_merge([
            'endpoint' => $endpoint,
            'method' => $method,
            'success' => $success,
            'response' => $response,
            'request_data' => $_POST ?? $_GET ?? []
        ], $context));
    }
    
    public static function getLogs($category = null, $limit = 100) {
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $lines = file(self::$logFile, FILE_IGNORE_NEW_LINES);
        $logs = [];
        
        foreach (array_reverse($lines) as $line) {
            if (empty($line)) continue;
            
            $logEntry = json_decode($line, true);
            if (!$logEntry) continue;
            
            if ($category && $logEntry['category'] !== $category) {
                continue;
            }
            
            $logs[] = $logEntry;
            
            if (count($logs) >= $limit) {
                break;
            }
        }
        
        return $logs;
    }
    
    public static function clearLogs() {
        if (file_exists(self::$logFile)) {
            unlink(self::$logFile);
        }
    }
    
    public static function getLogFile() {
        return self::$logFile;
    }
    
    public static function getLogPath() {
        return self::$logPath;
    }
}
?>
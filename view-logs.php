<?php
/**
 * Log Viewer
 * View and analyze error logs
 */

require_once 'config/database.php';
require_once 'config/environment.php';
require_once 'includes/functions.php';

// Check if user is admin (basic security)
if (!Environment::isLocal()) {
    die("Log viewer is only available in local environment for security reasons.");
}

$logType = $_GET['type'] ?? 'error';
$limit = intval($_GET['limit'] ?? 50);

$logFiles = [
    'error' => 'General Errors',
    'fatal' => 'Fatal Errors',
    'login' => 'Login Attempts',
    'database' => 'Database Operations',
    'api' => 'API Calls'
];

$logFile = ErrorLogger::getLogFile($logType);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Log Viewer - Water Purifier ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .log-entry {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin-bottom: 10px;
            padding: 10px;
            border-left: 4px solid #007bff;
            background-color: #f8f9fa;
        }
        .log-entry.error {
            border-left-color: #dc3545;
            background-color: #f8d7da;
        }
        .log-entry.fatal {
            border-left-color: #6f42c1;
            background-color: #e2e3f1;
        }
        .log-entry.success {
            border-left-color: #28a745;
            background-color: #d4edda;
        }
        .log-entry.warning {
            border-left-color: #ffc107;
            background-color: #fff3cd;
        }
        .log-timestamp {
            font-weight: bold;
            color: #6c757d;
        }
        .log-category {
            font-weight: bold;
            color: #007bff;
        }
        .log-message {
            color: #212529;
        }
        .log-context {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-file-alt me-2"></i>Error Log Viewer
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Log Type Selector -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Log Type:</label>
                                <select class="form-select" onchange="changeLogType(this.value)">
                                    <?php foreach ($logFiles as $type => $name): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $logType === $type ? 'selected' : ''; ?>>
                                        <?php echo $name; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Limit:</label>
                                <select class="form-select" onchange="changeLimit(this.value)">
                                    <option value="25" <?php echo $limit === 25 ? 'selected' : ''; ?>>25 entries</option>
                                    <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50 entries</option>
                                    <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100 entries</option>
                                    <option value="200" <?php echo $limit === 200 ? 'selected' : ''; ?>>200 entries</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Actions:</label>
                                <div class="btn-group w-100">
                                    <button class="btn btn-primary" onclick="refreshLogs()">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                    <button class="btn btn-danger" onclick="clearLogs()">
                                        <i class="fas fa-trash"></i> Clear
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Log File Info -->
                        <div class="alert alert-info">
                            <strong>Log File:</strong> <?php echo $logFile ?: 'Not found'; ?><br>
                            <strong>Environment:</strong> <?php echo Environment::detect(); ?><br>
                            <strong>Last Updated:</strong> <?php echo $logFile && file_exists($logFile) ? date('Y-m-d H:i:s', filemtime($logFile)) : 'Never'; ?>
                        </div>

                        <!-- Log Entries -->
                        <div id="logEntries">
                            <?php
                            if ($logFile && file_exists($logFile)) {
                                $lines = file($logFile, FILE_IGNORE_NEW_LINES);
                                $entries = array_reverse(array_slice($lines, -$limit));
                                
                                if (empty($entries)) {
                                    echo '<div class="alert alert-warning">No log entries found.</div>';
                                } else {
                                    foreach ($entries as $line) {
                                        if (empty($line)) continue;
                                        
                                        // Parse log line
                                        if (preg_match('/^\[([^\]]+)\] \[([^\]]+)\] \[([^\]]+)\] \[([^\]]+)\] (.+) \| (.+)$/', $line, $matches)) {
                                            $timestamp = $matches[1];
                                            $category = $matches[2];
                                            $ip = $matches[3];
                                            $requestUri = $matches[4];
                                            $message = $matches[5];
                                            $context = json_decode($matches[6], true) ?: [];
                                            
                                            $entryClass = 'log-entry';
                                            if (strpos($category, 'ERROR') !== false || strpos($category, 'FATAL') !== false) {
                                                $entryClass .= ' error';
                                            } elseif (strpos($category, 'SUCCESS') !== false) {
                                                $entryClass .= ' success';
                                            } elseif (strpos($category, 'WARNING') !== false) {
                                                $entryClass .= ' warning';
                                            }
                                            
                                            echo '<div class="' . $entryClass . '">';
                                            echo '<div class="log-timestamp">[' . htmlspecialchars($timestamp) . ']</div>';
                                            echo '<div class="log-category">[' . htmlspecialchars($category) . ']</div>';
                                            echo '<div class="log-message">' . htmlspecialchars($message) . '</div>';
                                            echo '<div class="log-context">IP: ' . htmlspecialchars($ip) . ' | URI: ' . htmlspecialchars($requestUri) . '</div>';
                                            if (!empty($context)) {
                                                echo '<div class="log-context">Context: ' . htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT)) . '</div>';
                                            }
                                            echo '</div>';
                                        }
                                    }
                                }
                            } else {
                                echo '<div class="alert alert-danger">Log file not found: ' . htmlspecialchars($logFile) . '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function changeLogType(type) {
            window.location.href = '?type=' + type + '&limit=<?php echo $limit; ?>';
        }
        
        function changeLimit(limit) {
            window.location.href = '?type=<?php echo $logType; ?>&limit=' + limit;
        }
        
        function refreshLogs() {
            window.location.reload();
        }
        
        function clearLogs() {
            if (confirm('Are you sure you want to clear all logs? This action cannot be undone.')) {
                fetch('clear-logs.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({action: 'clear_logs'})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Logs cleared successfully!');
                        window.location.reload();
                    } else {
                        alert('Error clearing logs: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        }
    </script>
</body>
</html>
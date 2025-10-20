<?php
// Simple script to check PHP error logs
echo "<h2>PHP Error Logs</h2>";

// Try to find the error log file
$errorLogPath = ini_get('error_log');

if (empty($errorLogPath)) {
    echo "<p>Error log path not configured in php.ini</p>";
    
    // Try common locations
    $commonPaths = [
        'C:\Windows\Temp\php_errors.log',
        'C:\xampp\php\logs\php_error_log',
        'C:\wamp\logs\php_error.log',
        '/var/log/php_errors.log',
        '/var/log/apache2/error.log'
    ];
    
    foreach ($commonPaths as $path) {
        if (file_exists($path)) {
            $errorLogPath = $path;
            break;
        }
    }
}

if (!empty($errorLogPath) && file_exists($errorLogPath)) {
    echo "<p>Error log file: " . htmlspecialchars($errorLogPath) . "</p>";
    
    // Read last 100 lines of the log file
    $lines = file($errorLogPath);
    $lastLines = array_slice($lines, -100);
    
    echo "<pre>";
    foreach ($lastLines as $line) {
        if (strpos($line, 'admin_fields_handler') !== false || strpos($line, 'Excel') !== false) {
            echo htmlspecialchars($line);
        }
    }
    echo "</pre>";
} else {
    echo "<p>Could not find error log file. Please check your PHP configuration.</p>";
}

// Also check for errors in the current directory
$localErrorLog = 'error_log';
if (file_exists($localErrorLog)) {
    echo "<h3>Local Error Log:</h3>";
    $lines = file($localErrorLog);
    $lastLines = array_slice($lines, -50);
    
    echo "<pre>";
    foreach ($lastLines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
}
?>
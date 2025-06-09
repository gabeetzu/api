<?php
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $log = "[" . date("Y-m-d H:i:s") . "] PHP ERROR: $errstr in $errfile on line $errline\n";
    file_put_contents("/var/data/logs/errors.csv", $log, FILE_APPEND);
});

// Simple index file for Render deployment
header('Content-Type: application/json');

echo json_encode([
    'status' => 'success',
    'message' => 'GospodApp API Server is running!',
    'endpoints' => [
        'text' => '/process-text.php',
        'image' => '/process-image.php'
    ],
    'timestamp' => date('Y-m-d H:i:s')
]);
?>

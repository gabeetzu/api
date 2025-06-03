<?php
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

<?php
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $log = "[" . date("Y-m-d H:i:s") . "] PHP ERROR: $errstr in $errfile on line $errline\n";
    file_put_contents("/var/data/logs/errors.csv", $log, FILE_APPEND);
});

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');
if (!hash_equals($expectedKey, $apiKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['original']) || empty($data['correction'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit();
}

$entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'original' => strip_tags($data['original']),
    'correction' => strip_tags($data['correction']),
    'image' => $data['image'] ?? 'none'
];

if (!is_writable('/data')) {
    error_log('❌ /data not writable');
}
error_log("✅ Feedback received: " . json_encode($entry));

$csvLine = '"' . implode('","', array_map('addslashes', $entry)) . '"' . PHP_EOL;
file_put_contents('/data/corrections.csv', $csvLine, FILE_APPEND);

echo json_encode(['success' => true, 'message' => 'Feedback salvat. Mulțumim!']);

<?php
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $log = "[" . date("Y-m-d H:i:s") . "] PHP ERROR: $errstr in $errfile on line $errline\n";
    file_put_contents("/var/data/logs/errors.csv", $log, FILE_APPEND);
});

header('Content-Type: application/json; charset=utf-8');
$allowedOrigins = ['https://gospodapp.netlify.app', 'https://gospodapp.ro'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

if (!in_array($_SERVER['HTTP_ORIGIN'] ?? '', $allowedOrigins)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit();
}

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');

if ($expectedKey && !hash_equals($expectedKey, $apiKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$deviceHash = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['device_hash'] ?? '');
if (!$data || empty($data['original']) || empty($data['correction'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit();
}

$name = substr(strip_tags($data['user_name'] ?? ''), 0, 30);

$entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'device' => $data['device_hash'] ?? 'unknown',
    'name' => $name,
    'original' => strip_tags($data['original']),
    'correction' => strip_tags($data['correction']),
    'image' => $data['image'] ?? 'none',
    'device_hash' => $deviceHash
];

$logPath = "/data/corrections.csv";

// Create file if it doesn't exist (so we can check writability)
if (!file_exists($logPath)) {
    @touch($logPath);
}

// Final check
if (!is_writable($logPath)) {
    error_log("❌ corrections.csv is not writable!");
} else {
    error_log("✅ corrections.csv is writable");
}

error_log("✅ Feedback received: " . json_encode($entry));

$csvLine = '"' . implode('","', array_map('addslashes', $entry)) . '"' . PHP_EOL;
file_put_contents($logPath, $csvLine, FILE_APPEND);

echo json_encode(['success' => true, 'message' => 'Feedback salvat. Mulțumim!']);

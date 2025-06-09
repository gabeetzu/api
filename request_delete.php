<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');
if (!hash_equals($expectedKey, $apiKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$deviceHash = isset($input['device_hash']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $input['device_hash']) : '';
if (!$deviceHash) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'device_hash required']);
    exit();
}

$pdo = new PDO(
    "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4",
    getenv('DB_USER'),
    getenv('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$stmt = $pdo->prepare("UPDATE usage_tracking SET pending_deletion = 1, deletion_due_at = NOW() + INTERVAL 7 DAY WHERE device_hash = ?");
$stmt->execute([$deviceHash]);

echo json_encode(['success' => true, 'message' => 'Deletion scheduled']);

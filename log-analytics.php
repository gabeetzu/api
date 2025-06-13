<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$event = preg_replace('/[^a-zA-Z0-9_]/', '', $input['event'] ?? '');
$device = preg_replace('/[^a-zA-Z0-9_-]/', '', $input['device_hash'] ?? '');
$data = isset($input['data']) ? json_encode($input['data']) : '';
$timestamp = date('Y-m-d H:i:s');

$line = sprintf("%s,%s,%s,%s\n", $timestamp, $device, $event, str_replace(["\n","\r"], ' ', $data));

$dir = '/var/data/logs';
if (!file_exists($dir)) {
    mkdir($dir, 0775, true);
}
file_put_contents($dir . '/analytics.csv', $line, FILE_APPEND | LOCK_EX);

echo json_encode(['success' => true]);
?>

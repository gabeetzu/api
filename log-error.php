<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
mb_internal_encoding("UTF-8");

ini_set('display_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$message = sanitize($input['message'] ?? '');
if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'message required']);
    exit();
}

$device = sanitize($input['device'] ?? 'unknown');
$version = sanitize($input['version'] ?? 'unknown');
$timestamp = isset($input['timestamp']) && is_numeric($input['timestamp']) ? (int)$input['timestamp'] : time();

$line = sprintf("%s,%s,%s,%s\n", date('Y-m-d H:i:s', $timestamp), $device, $version, str_replace(["\n", "\r"], ' ', $message));

$dir = '/var/data/logs';
if (!file_exists($dir)) {
    mkdir($dir, 0775, true);
}
file_put_contents($dir . '/errors.csv', $line, FILE_APPEND | LOCK_EX);

echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

function sanitize($text) {
    $clean = trim(strip_tags($text));
    $clean = preg_replace('/[^\p{L}\p{N}\s.,!?()\-]/u', '', $clean);
    return mb_substr($clean, 0, 300);
}

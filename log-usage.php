<?php
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $log = "[" . date("Y-m-d H:i:s") . "] PHP ERROR: $errstr in $errfile on line $errline\n";
    file_put_contents("/var/data/logs/errors.csv", $log, FILE_APPEND);
});

header('Content-Type: application/json; charset=utf-8');
$allowed = ['https://gospodapp.netlify.app', 'https://gospodapp.ro'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$hash      = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['device_hash'] ?? '');
$message   = substr(trim(strip_tags($data['message'] ?? '')), 0, 500);
$image     = !empty($data['image']) ? true : false;
$platform  = substr(strip_tags($data['platform'] ?? ''), 0, 100);
$version   = substr(strip_tags($data['app_version'] ?? ''), 0, 20);
$ref       = preg_replace('/[^A-Z0-9]/', '', $data['ref_code'] ?? '');
$timestamp = substr($data['timestamp'] ?? '', 0, 30);

$logLine = json_encode([
    'device_hash' => $hash,
    'message'     => $message,
    'image'       => $image,
    'platform'    => $platform,
    'app_version' => $version,
    'ref_code'    => $ref ?: null,
    'timestamp'   => $timestamp ?: gmdate('c')
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

$dir = '/var/data/logs';
if (!file_exists($dir)) {
    mkdir($dir, 0775, true);
}
    file_put_contents($dir . '/usage.log', $logLine, FILE_APPEND | LOCK_EX);

echo json_encode(['success' => true]);
?>

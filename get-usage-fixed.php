<?php
/**
 * GospodApp - Get Usage Statistics Endpoint
 * Returns user usage data for frontend consumption
 */

// Set headers for JSON API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Include the database manager
require_once 'database-manager-fixed.php';

try {
    // Get device hash from query parameters
    $deviceHash = $_GET['hash'] ?? '';
    
    // Validate device hash
    if (empty($deviceHash) || !preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $deviceHash)) {
        throw new Exception('Invalid device hash');
    }
    
    // Get database instance
    $db = DatabaseManager::getInstance();
    
    // Get today's usage data
    $todayUsage = $db->getUserUsageData($deviceHash);
    
    // Get overall statistics
    $overallStats = $db->getUsageStats($deviceHash);
    
    // Check premium status
    $premiumStatus = $db->checkPremiumStatus($deviceHash);
    
    // Prepare response data
    $stats = [
        'device_hash' => $deviceHash,
        'today' => [
            'text_count' => $todayUsage['text_count'] ?? 0,
            'image_count' => $todayUsage['image_count'] ?? 0,
            'total_count' => ($todayUsage['text_count'] ?? 0) + ($todayUsage['image_count'] ?? 0),
            'last_request' => $todayUsage['last_request'] ?? null,
            'user_name' => $todayUsage['user_name'] ?? null
        ],
        'overall' => [
            'total_text' => $overallStats['total_text'] ?? 0,
            'total_images' => $overallStats['total_images'] ?? 0,
            'total_requests' => $overallStats['total_requests'] ?? 0,
            'active_days' => $overallStats['active_days'] ?? 0
        ],
        'premium' => [
            'is_premium' => $premiumStatus['is_premium'],
            'premium_until' => $premiumStatus['premium_until']
        ],
        'limits' => [
            'daily_limit' => 30,
            'remaining_today' => max(0, 30 - ($todayUsage['text_count'] ?? 0) - ($todayUsage['image_count'] ?? 0))
        ]
    ];
    
    // For backward compatibility, include flat structure
    $flatStats = [
        'total_count' => $stats['today']['total_count'],
        'text_count' => $stats['today']['text_count'],
        'image_count' => $stats['today']['image_count'],
        'premium' => $premiumStatus['is_premium'] ? 1 : 0,
        'premium_until' => $premiumStatus['premium_until'],
        'user_name' => $stats['today']['user_name']
    ];
    
    // Send successful response
    echo json_encode([
        'success' => true,
        'stats' => array_merge($flatStats, $stats)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Get usage error: " . $e->getMessage());
    
    // Send error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'stats' => [
            'total_count' => 0,
            'text_count' => 0,
            'image_count' => 0,
            'premium' => 0,
            'premium_until' => null,
            'user_name' => null
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>
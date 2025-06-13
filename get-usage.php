<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$rawString = $_SERVER['QUERY_STRING'] ?? '';
require_once 'security.php';

require_once 'DatabaseManager.php';

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed', 405);
    }
    
    // Get device hash from query parameters
    $deviceHash = $_GET['hash'] ?? '';

     if (!validateRequestSignature($rawString)) {
        $db = DatabaseManager::getInstance();
        $pdo = $db->getConnection();
        logSecurityEvent($pdo, $deviceHash ?: 'unknown', 'bypass_attempt');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid signature']);
        exit();
    }
    
    // Validate device hash
    if (empty($deviceHash) || !preg_match('/^[a-zA-Z0-9_]{8,64}$/', $deviceHash)) {
        throw new Exception('Invalid device hash', 400);
    }
    
    // Get database instance
    $db = DatabaseManager::getInstance();
    
    // Fetch usage statistics
    $stats = $db->getUsageStats($deviceHash);
    
    if ($stats === null) {
        throw new Exception('Failed to retrieve usage statistics', 500);
    }
    
    // Check for referral bonuses
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as referral_count 
        FROM referrals 
        WHERE inviter_hash = ?
    ");
    $stmt->execute([$deviceHash]);
    $referralData = $stmt->fetch();
    
    // Calculate premium status
    $isPremium = false;
    $premiumUntil = null;
    
    if ($stats['premium'] == 1 && $stats['premium_until']) {
        $premiumDate = new DateTime($stats['premium_until']);
        $now = new DateTime();
        
        if ($premiumDate > $now) {
            $isPremium = true;
            $premiumUntil = $premiumDate->format('Y-m-d H:i:s');
        }
    }
    
    $textLimit = $isPremium ? 10 : 3;
    $imageLimit = $isPremium ? 3 : 1;

    $response = [
        'success' => true,
        'stats' => [
            'total_count'    => (int)$stats['total_count'],
            'text_count'     => (int)$stats['text_count'],
            'image_count'    => (int)$stats['image_count'],
            'premium'        => $isPremium ? 1 : 0,
            'premium_until'  => $premiumUntil,
            'user_name'      => $stats['user_name'],
            'referral_count' => (int)$referralData['referral_count'],
            'text_limit'     => $textLimit,
            'image_limit'    => $imageLimit,
            'can_make_text'  => $stats['text_count'] < $textLimit,
            'can_make_image' => $stats['image_count'] < $imageLimit
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    
    $errorResponse = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
    
    // Log error for debugging
    error_log("get-usage.php error: " . $e->getMessage());
}
?>

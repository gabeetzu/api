<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Allow large files (50MB)
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('max_execution_time', '300');
ini_set('memory_limit', '512M');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate input
    if (!$data || !isset($data['image']) || !isset($data['device_hash'])) {
        throw new Exception('Date lipsă: Imaginea sau hash-ul dispozitivului nu au fost primite');
    }

    $imageBase64 = $data['image'];
    $deviceHash = $data['device_hash'];

    // Connect to database
    $pdo = connectToDatabase();
    
    // Check image usage limits
    checkUsageLimits($pdo, $deviceHash, 'image');

    // Step 1: Analyze with Google Vision
    $visionResults = analyzeWithGoogleVision($imageBase64);

    // Validate Vision API results
    if (empty($visionResults['objects']) && empty($visionResults['labels'])) {
        throw new Exception('Imaginea nu conține elemente recunoscute');
    }

    // Step 2: Get treatment from OpenAI
    $treatment = getTreatmentFromOpenAI($visionResults);

    // Save to chat history
    saveChatHistory($pdo, $deviceHash, "", true, 'image', $imageBase64); // User image
    saveChatHistory($pdo, $deviceHash, $treatment, false, 'text', null); // Bot response

    // Record usage
    recordUsage($pdo, $deviceHash, 'image');

    echo json_encode([
        'success' => true,
        'treatment' => $treatment,
        'vision_results' => $visionResults
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Database functions (same as process-text.php)
function connectToDatabase() {
    $host = getenv('DB_HOST');
    $dbname = getenv('DB_NAME');
    $username = getenv('DB_USER');
    $password = getenv('DB_PASS');
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

function checkUsageLimits($pdo, $deviceHash, $type) {
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT image_count, premium 
        FROM usage_tracking 
        WHERE device_hash = ? AND date = ?
    ");
    $stmt->execute([$deviceHash, $today]);
    $usage = $stmt->fetch();

    if (!$usage) {
        $stmt = $pdo->prepare("
            INSERT INTO usage_tracking (device_hash, date, image_count) 
            VALUES (?, ?, 0)
        ");
        $stmt->execute([$deviceHash, $today]);
        $usage = ['image_count' => 0, 'premium' => 0];
    }

    if ($type === 'image') {
        $limit = $usage['premium'] ? 5 : 1; // 5 for free, 50 for premium
        if ($usage['image_count'] >= $limit) {
            throw new Exception('Ați atins limita zilnică de ' . $limit . ' imagini. Upgrade la premium pentru mai multe.');
        }
    }
}

function recordUsage($pdo, $deviceHash, $type) {
    $today = date('Y-m-d');
    $field = $type === 'image' ? 'image_count' : 'text_count';
    
    $stmt = $pdo->prepare("
        UPDATE usage_tracking 
        SET $field = $field + 1 
        WHERE device_hash = ? AND date = ?
    ");
    $stmt->execute([$deviceHash, $today]);
}

function saveChatHistory($pdo, $deviceHash, $messageText, $isUserMessage, $messageType, $imageData) {
    $stmt = $pdo->prepare("
        INSERT INTO chat_history (device_hash, message_text, is_user_message, message_type, image_data) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $deviceHash,
        $messageText,
        $isUserMessage ? 1 : 0,
        $messageType,
        $imageData
    ]);
}

// Existing vision/openai functions remain the same
function analyzeWithGoogleVision($imageBase64) { /* ... */ }
function getTreatmentFromOpenAI($visionResults) { /* ... */ }
function cleanForTTS($text) { /* ... */ }

?>

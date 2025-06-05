<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

// --- Environment Variables ---
$apiSecretKey = getenv('API_SECRET_KEY');
$googleVisionKey = getenv('GOOGLE_VISION_KEY');
$openaiKey = getenv('OPENAI_API_KEY');
$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');
$redisHost = getenv('REDIS_HOST');
$redisPort = getenv('REDIS_PORT');
$redisPass = getenv('REDIS_PASSWORD');

// --- Security Check ---
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== $apiSecretKey) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

// --- Redis Rate Limiter ---
try {
    if ($redisHost && $redisPort) {
        $redis = new Redis();
        $redis->connect($redisHost, $redisPort);
        if ($redisPass) $redis->auth($redisPass);
        
        $clientIp = $_SERVER['REMOTE_ADDR'];
        $ipKey = "ip_limit:$clientIp";
        
        if ($redis->exists($ipKey) && $redis->get($ipKey) >= 20) {
            http_response_code(429);
            die(json_encode(['success' => false, 'error' => 'Prea multe solicitări']));
        }
        
        $redis->multi()
            ->incr($ipKey)
            ->expire($ipKey, 60)
            ->exec();
    }
} catch (Exception $e) {
    error_log("Redis error: " . $e->getMessage());
}

// --- Database Connection ---
try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    error_log("DB error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Service unavailable']));
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // --- Image Processing ---
    if (isset($data['image'])) {
        $imageBase64 = $data['image'];
        $userMessage = $data['message'] ?? '';
        $deviceHash = $data['device_hash'] ?? '';

        validateImage($imageBase64);
        validateDeviceHash($deviceHash);
        trackUsage($pdo, $deviceHash, 'image');
        
        $rawResponse = processImage($imageBase64, $userMessage);

    // --- Text Processing ---
    } elseif (isset($data['message'], $data['device_hash'])) {
        $message = trim($data['message']);
        $deviceHash = $data['device_hash'];

        validateTextInput($message);
        validateDeviceHash($deviceHash);
        trackUsage($pdo, $deviceHash, 'text');
        
        $rawResponse = processText($message);

    } else {
        throw new Exception('Cerere invalidă');
    }

    echo json_encode([
    'success' => true,
    'response' => formatForDisplay($rawResponse)
]);



} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
    'success' => true,
    'response_id' => $responseId ?? null,
    'response' => $displayText,
    'tts' => $ttsText
]);
}

// ====================
// PROCESSING FUNCTIONS
// ====================

function processImage($imageBase64, $userMessage) {
    $visionData = analyzeImageWithVisionAPI($imageBase64);
    $features = extractVisualFeatures($visionData);
    $prompt = buildImagePrompt($features, $userMessage);
    return getGPTResponse($prompt, true);
}

function processText($message) {
    $systemPrompt = <<<PROMPT
Ești un expert grădinar. Structurează răspunsurile exact astfel:

**Observații:**
• Maxim 3 puncte cheie

**Cauze posibile:**
1. [Principală] (70-90%)
2. [Secundară] (10-30%)

**Recomandări:**
• Pas 1: Acțiune concretă
• Pas 2: Produs specific

**Monitorizare:**
✓ Verificați [indicator]
✗ Evitați [acțiune]

Folosește doar structura de mai sus. Fără markdown.
PROMPT;

    return getGPTResponse($systemPrompt, $message);
}

// ====================
// CORE FUNCTIONALITY
// ====================

function analyzeImageWithVisionAPI($imageBase64) {
    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . getenv('GOOGLE_VISION_KEY');
    
    $requestData = [
        'requests' => [[
            'image' => ['content' => $imageBase64],
            'features' => [
                ['type' => 'LABEL_DETECTION', 'maxResults' => 20],
                ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 10],
                ['type' => 'WEB_DETECTION', 'maxResults' => 10],
                ['type' => 'IMAGE_PROPERTIES']
            ]
        ]]
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($requestData)
        ]
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    if ($response === FALSE) {
        throw new Exception('Eroare analiză imagine');
    }
    
    return json_decode($response, true);
}

function extractVisualFeatures($visionData) {
    $features = [];
    $keywords = ['leaf spot', 'blight', 'mildew', 'rust', 'rot', 'lesion', 'chlorosis'];

    foreach ($visionData['responses'][0]['labelAnnotations'] ?? [] as $label) {
        if ($label['score'] > 0.8 && preg_match('/(' . implode('|', $keywords) . ')/i', $label['description'])) {
            $features[] = $label['description'];
        }
    }

    foreach ($visionData['responses'][0]['webDetection']['webEntities'] ?? [] as $entity) {
        if (($entity['score'] ?? 0) > 0.7 && preg_match('/(' . implode('|', $keywords) . ')/i', $entity['description'] ?? '')) {
            $features[] = "Context web: " . substr($entity['description'], 0, 50);
        }
    }

    return array_unique($features);
}

function getGPTResponse($systemPrompt, $userPrompt) {
    $model = 'gpt-4o-mini';
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . getenv('OPENAI_API_KEY')
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => 0.4,
            'max_tokens' => 600
        ])
    ]);
    $response = curl_exec($ch);
    if (!$response) throw new Exception('Eroare AI');
    
    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'];
}

// ====================
// RESPONSE FORMATTING
// ====================

function formatForDisplay($text) {
    $text = preg_replace('/\*\*Observații:\*\*/', "🔍 Observații\n", $text);
    $text = preg_replace('/\*\*Cauze posibile:\*\*/', "🦠 Cauze posibile\n", $text);
    $text = preg_replace('/\*\*Recomandări:\*\*/', "💡 Recomandări\n", $text);
    $text = preg_replace('/\*\*Monitorizare:\*\*/', "👀 Monitorizare\n", $text);
    return str_replace('•', "• ", $text);
}


function cleanForTTS($text) {
    $text = strip_tags($text);
    $text = preg_replace('/\*\*.*?\*\*/', '', $text); // Remove any bold
    $text = preg_replace('/[\n\r]+/', '. ', $text); // New lines to periods
    $text = preg_replace('/•\s*/', '', $text); // Remove bullet points
    $text = preg_replace('/\d+\.\s*/', '', $text); // Remove numbering
    $text = preg_replace('/\s+/', ' ', $text); // Normalize spaces
    return trim($text);
}


// ====================
// VALIDATION & HELPERS
// ====================

function validateImage($base64) {
    if (strlen($base64) > 5 * 1024 * 1024 || !preg_match('/^[a-zA-Z0-9\/+]+={0,2}$/', $base64)) {
        throw new Exception('Imagine invalidă');
    }
}

function validateTextInput($message) {
    if (empty($message) || strlen($message) > 2000) {
        throw new Exception('Mesaj invalid');
    }
}

function validateDeviceHash($hash) {
    if (!preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $hash)) {
        throw new Exception('Dispozitiv invalid');
    }
}

function trackUsage($pdo, $deviceHash, $type) {
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("SELECT * FROM usage_tracking WHERE device_hash = ? AND date = ?");
    $stmt->execute([$deviceHash, $today]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usage) {
        $field = ($type === 'image') ? 'image_count' : 'text_count';
        $stmt = $pdo->prepare("UPDATE usage_tracking SET $field = $field + 1, last_request = ? WHERE id = ?");
        $stmt->execute([$now, $usage['id']]);
    } else {
        $fields = ($type === 'image') ? 'image_count' : 'text_count';
        $stmt = $pdo->prepare("INSERT INTO usage_tracking (device_hash, date, $fields, created_at, last_request) VALUES (?, ?, 1, ?, ?)");
        $stmt->execute([$deviceHash, $today, $now, $now]);
    }
}

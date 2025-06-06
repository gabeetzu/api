<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');
mb_internal_encoding("UTF-8");

// --- Handle preflight CORS ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- API Key Security ---
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');
if (!hash_equals($expectedKey, $apiKey)) {
    http_response_code(401);
    die(safeJsonEncode(['success' => false, 'error' => 'Acces neautorizat']));
}

// --- Database Connection ---
try {
    $pdo = new PDO(
        "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4",
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    error_log("DB error: " . $e->getMessage());
    http_response_code(500);
    die(safeJsonEncode(['success' => false, 'error' => 'Service unavailable']));
}

try {
    $input = getInputData();
    $imageBase64 = $input['image'] ?? '';
    $userMessage = sanitizeInput($input['message'] ?? '');
    $cnnDiagnosis = sanitizeInput($input['diagnosis'] ?? '');

    if (!empty($imageBase64)) {
        validateImage($imageBase64);
        $treatment = handleImageAnalysis($imageBase64, $userMessage, $cnnDiagnosis);
    } elseif (!empty($cnnDiagnosis)) {
        $treatment = handleCnnDiagnosis($cnnDiagnosis, $userMessage);
    } elseif (!empty($userMessage)) {
        $treatment = getGPTResponse($userMessage);
    } else {
        throw new Exception('Date lipsă: Trimiteți o imagine, un diagnostic sau un mesaj');
    }

    if ($treatment === null) {
        throw new Exception('Răspuns gol de la AI');
    }

    echo safeJsonEncode([
        'success' => true,
        'response_id' => bin2hex(random_bytes(6)),
        'response' => $treatment
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo safeJsonEncode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// --- Helper Functions ---
function safeJsonEncode($data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        exit(json_encode([
            'success' => false,
            'error' => 'Eroare internă: ' . json_last_error_msg()
        ]));
    }
    return $json;
}

function getInputData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function sanitizeInput($text) {
    $clean = trim(strip_tags($text));
    $clean = preg_replace('/[^\p{L}\p{N}\s.,;:!?()-]/u', '', $clean);
    return mb_substr($clean, 0, 300);
}

function validateImage(&$imageBase64) {
    if (strlen($imageBase64) > 5 * 1024 * 1024) {
        throw new Exception('Imagine prea mare (max 5MB)');
    }
    if (!preg_match('/^[a-zA-Z0-9\/+]+={0,2}$/', $imageBase64)) {
        throw new Exception('Format imagine invalid');
    }
}

// --- Processing Functions ---
function handleImageAnalysis($imageBase64, $userMessage, $cnnDiagnosis) {
    $visionData = analyzeImageWithVisionAPI($imageBase64);
    $features = extractVisualFeatures($visionData);
    $prompt = buildHybridPrompt(
        formatFeatures($features),
        $userMessage,
        $cnnDiagnosis
    );
    return getGPTResponse($prompt);
}

function handleCnnDiagnosis($diagnosis, $userMessage) {
    $prompt = buildCnnBasedPrompt($diagnosis, $userMessage);
    return getGPTResponse($prompt);
}

// --- GPT Response Handler ---
function getGPTResponse($prompt) {
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . getenv('OPENAI_API_KEY')
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Sunteți asistent agronom pentru aplicația GospodApp. Răspundeți în română.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.3,
            'max_tokens' => 600
        ])
    ]);

    $response = curl_exec($ch);
    if (!$response || curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        throw new Exception('Eroare serviciu AI');
    }

    $data = json_decode($response, true);
    if (empty($data['choices'][0]['message']['content'])) {
        throw new Exception('Răspuns invalid de la AI');
    }

    return formatResponse($data['choices'][0]['message']['content']);
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
    $text = preg_replace('/\*\*.*?\*\*/', '', $text);
    $text = preg_replace('/[\n\r]+/', '. ', $text);
    $text = preg_replace('/•\s*/', '', $text);
    $text = preg_replace('/\d+\.\s*/', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
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

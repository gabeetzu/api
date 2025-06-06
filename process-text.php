<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');
mb_internal_encoding("UTF-8");

ini_set('display_errors', 0);
error_reporting(0);

// --- CORS Preflight ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- API Key Validation ---
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');
if (!hash_equals($expectedKey, $apiKey)) {
    logEvent('Unauthorized', ['ip' => $_SERVER['REMOTE_ADDR']]);
    http_response_code(401);
    echo jsonResponse(false, 'Acces neautorizat');
    exit();
}

// --- Database ---
try {
    $pdo = new PDO(
        "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4",
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    logEvent('DBError', $e->getMessage());
    http_response_code(500);
    echo jsonResponse(false, 'Eroare conexiune la baza de date');
    exit();
}

// --- Main Logic ---
try {
    $input = getInputData();
    logEvent('TextInput', $input);

    $userMessage = sanitizeInput($input['message'] ?? '');
    $cnnDiagnosis = sanitizeInput($input['diagnosis'] ?? '');
    $deviceHash = sanitizeInput($input['device_hash'] ?? '');

    if (empty($userMessage) && empty($cnnDiagnosis)) {
        throw new Exception('Trimite un mesaj sau un diagnostic pentru a primi ajutor.');
    }

    if (!empty($deviceHash)) {
        validateDeviceHash($deviceHash);
        logEvent('Device', $deviceHash);
        trackUsage($pdo, $deviceHash, 'text');
    }

    $prompt = buildPrompt($userMessage, $cnnDiagnosis);
    $response = getGPTResponse($prompt);

    if (!$response || empty($response['text'])) {
        throw new Exception('Răspuns gol de la AI');
    }

    logEvent('TextResponse', $response);
    echo json_encode([
    'success' => true,
    'response' => [
        'text' => $response['text'],
        'raw' => $response['raw']
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);


} catch (Exception $e) {
    logEvent('TextError', $e->getMessage());
    http_response_code(400);
    echo jsonResponse(false, $e->getMessage());
}
function buildPrompt($userMessage, $diagnosis) {
    if (!empty($diagnosis)) {
        return "Diagnostic AI: $diagnosis\nÎntrebarea utilizatorului: $userMessage\n\nInstrucțiuni:\n- Explică simplu problema.\n- Oferă 2-3 pași ecologici.\n- Încheie pozitiv.";
    }
    return $userMessage;
}

function getGPTResponse($prompt) {
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT => 10,               // Total request timeout
        CURLOPT_CONNECTTIMEOUT => 5,         // Time to connect before giving up
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . getenv('OPENAI_API_KEY')
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' =>
                    'Ești un asistent agronom empatic pentru aplicația GospodApp. Răspunde simplu, clar și ecologic. Nu aborda subiecte în afara agriculturii.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.4,
            'max_tokens' => 500
        ])
    ]);

    $res = curl_exec($ch);
    if (!$res || curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        throw new Exception('Eroare serviciu OpenAI');
    }
    $data = json_decode($res, true);
    $text = $data['choices'][0]['message']['content'] ?? '';
    'response' => $response
}

function formatResponse($text) {
    return preg_replace([
        '/##\s+/', '/\*\*(.*?)\*\*/', '/<tratament>/i', '/<prevenire>/i'
    ], [
        '🔸 ', '$1', '💊 Tratament:', '🛡 Prevenire:'
    ], $text);
}

function jsonResponse($success, $payload) {
    return json_encode([
        'success' => $success,
        'response' => $payload
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function logEvent($label, $data) {
    $dir = __DIR__ . '/logs';
    if (!file_exists($dir)) mkdir($dir, 0775, true);
    $line = date('Y-m-d H:i:s') . " [$label] " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($dir . '/activity.log', $line, FILE_APPEND);
}

function getInputData() {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    $json = file_get_contents('php://input');
    return (stripos($ct, 'application/json') !== false) ? json_decode($json, true) ?? [] : $_POST;
}

function sanitizeInput($txt) {
    $clean = trim(strip_tags($txt));
    return mb_substr(preg_replace('/[^\p{L}\p{N}\s.,!?-]/u', '', $clean), 0, 300);
}

function validateDeviceHash($hash) {
    if (!preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $hash)) {
        throw new Exception('ID dispozitiv invalid');
    }
}

function trackUsage($pdo, $hash, $type) {
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT * FROM usage_tracking WHERE device_hash = ? AND date = ?");
    $stmt->execute([$hash, $today]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $col = $type === 'image' ? 'image_count' : 'text_count';
        $stmt = $pdo->prepare("UPDATE usage_tracking SET $col = $col + 1, last_request = ? WHERE id = ?");
        $stmt->execute([$now, $row['id']]);
    } else {
        $col = $type === 'image' ? 'image_count' : 'text_count';
        $stmt = $pdo->prepare("INSERT INTO usage_tracking (device_hash, date, $col, created_at, last_request) VALUES (?, ?, 1, ?, ?)");
        $stmt->execute([$hash, $today, $now, $now]);
    }
}

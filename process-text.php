<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// --- ENVIRONMENT VARIABLES (Render.com) ---
$apiSecretKey = getenv('API_SECRET_KEY');
$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');
$openaiKey = getenv('OPENAI_API_KEY');
$redisHost = getenv('REDIS_HOST');
$redisPort = getenv('REDIS_PORT');
$redisPass = getenv('REDIS_PASSWORD');

// --- API Key Check ---
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== $apiSecretKey) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

// --- Early IP Rate Limiter (Redis) ---
try {
    if ($redisHost && $redisPort) {
        $redis = new Redis();
        $redis->connect($redisHost, $redisPort);
        if ($redisPass) $redis->auth($redisPass);
        
        $clientIp = $_SERVER['REMOTE_ADDR'];
        $ipKey = "ip_limit:$clientIp";
        
        // Allow 20 requests/minute per IP
        if ($redis->exists($ipKey) && $redis->get($ipKey) >= 20) {
            http_response_code(429);
            die(json_encode(['success' => false, 'error' => 'Prea multe solicitări de la această rețea']));
        }
        
        $redis->multi()
            ->incr($ipKey)
            ->expire($ipKey, 60)
            ->exec();
    }
} catch (Exception $e) {
    error_log("Redis connection failed: " . $e->getMessage());
    // Continue processing if Redis is unavailable
}

// --- Connect to DB (using env vars) ---
try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    error_log("DB connect error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Service unavailable']));
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['message']) || !isset($data['device_hash'])) {
        throw new Exception('Date lipsă: Mesajul sau hash-ul dispozitivului nu au fost primite');
    }

    $message = trim($data['message']);
    $deviceHash = $data['device_hash'];

    // --- Device Hash Validation ---
    if (!preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $deviceHash)) {
        throw new Exception('Hash dispozitiv invalid');
    }

    // --- Validate Input ---
    validateTextInput($message);
    securityScanText($message);

    // --- RATE LIMITING (per device_hash) ---
    $maxDaily = 50;     // Max questions/day/device
    $maxMinute = 5;     // Max questions/minute/device

    // Get today's date
    $today = date('Y-m-d');

    // Try to fetch usage row
    $stmt = $pdo->prepare("SELECT * FROM usage_tracking WHERE device_hash = ? AND date = ?");
    $stmt->execute([$deviceHash, $today]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC);

    $now = date('Y-m-d H:i:s');

    if ($usage) {
        // Daily quota
        if ($usage['text_count'] >= $maxDaily) {
            http_response_code(429);
            die(json_encode(['success' => false, 'error' => 'Ați depășit limita zilnică de întrebări']));
        }
        // Burst limit
        $lastRequest = strtotime($usage['last_request']);
        if (time() - $lastRequest < 60) {
            // Same minute, increment minute_counter
            $minuteCounter = $usage['minute_counter'] + 1;
            if ($minuteCounter > $maxMinute) {
                http_response_code(429);
                die(json_encode(['success' => false, 'error' => 'Prea multe solicitări. Așteptați un minut.']));
            }
        } else {
            // New minute, reset minute_counter
            $minuteCounter = 1;
        }
        // Update usage row
        $stmt = $pdo->prepare("UPDATE usage_tracking SET text_count = text_count + 1, last_request = ?, minute_counter = ? WHERE id = ?");
        $stmt->execute([$now, $minuteCounter, $usage['id']]);
    } else {
        // First request today for this device
        $stmt = $pdo->prepare("INSERT INTO usage_tracking (device_hash, date, text_count, image_count, premium, extra_questions, created_at, last_request, minute_counter) VALUES (?, ?, 1, 0, 0, 0, ?, ?, 1)");
        $stmt->execute([$deviceHash, $today, $now, $now]);
    }

    // --- Get AI Response ---
    $response = getAIResponse($message, $openaiKey);

    echo json_encode([
        'success' => true,
        'response' => $response
    ]);

} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// --- Helper Functions ---

function validateTextInput($message) {
    if (empty($message)) {
        throw new Exception('Mesajul nu poate fi gol');
    }
    if (strlen($message) > 2000) {
        throw new Exception('Mesajul este prea lung');
    }
}

function securityScanText($message) {
    $patterns = [
        '/select.*from/i', '/insert.*into/i', '/update.*set/i', '/delete.*from/i',
        '/<script/i', '/javascript:/i', '/eval\(/i', '/exec\(/i', '/system\(/i'
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, strtolower($message))) {
            throw new Exception('Mesajul conține conținut suspect. Reformulați.');
        }
    }
}

function getAIResponse($message, $openaiKey) {
    $systemPrompt = "Ești un grădinar român cu peste 30 de ani de experiență practică în grădinărit. Răspunzi întotdeauna în limba română, clar, simplu și prietenos, ca pentru o persoană începătoare sau în vârstă. Nu folosești termeni tehnici inutili. Oferi soluții directe, bazate pe experiență reală, explicate în 150–300 de cuvinte. Nu menționezi AI, roboți sau modele de limbaj. Nu încurajezi căutări pe Google sau consultarea altor surse.";

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message]
        ],
        'max_tokens' => 500,
        'temperature' => 0.7
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Nu am putut genera răspuns');
    }

    $data = json_decode($response, true);
    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception('Răspuns invalid de la AI');
    }

    return cleanForTTS($data['choices'][0]['message']['content']);
}

function cleanForTTS($text) {
    $text = preg_replace('/\*+/', '', $text);
    $text = preg_replace('/^\d+\.\s*/m', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

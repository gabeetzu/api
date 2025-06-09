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

// --- Database Connection ---
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
    logEvent('Input', $input);

    $userMessage = sanitizeInput($input['message'] ?? '');
    $imageBase64 = $input['image'] ?? '';
        // Limit uploaded images to 3MB to keep requests lightweight
    if ($imageBase64 && strlen($imageBase64) > 3 * 1024 * 1024) {
        throw new Exception('Imaginea depășește 3MB.');
    }
    if ($imageBase64 && !preg_match('/^[A-Za-z0-9+\/=\s]+$/', $imageBase64)) {
        throw new Exception('Format imagine invalid.');
    }
    $cnnDiagnosis = sanitizeInput($input['diagnosis'] ?? '');
    $cnnConfidence = isset($input['confidence']) ? floatval($input['confidence']) : 1.0;
    $cnnConfidence = max(0, min(1, $cnnConfidence));
    $weather = sanitizeInput($input['weather'] ?? '');
    $deviceHash = sanitizeInput($input['device_hash'] ?? '');

    if (empty($userMessage) && empty($imageBase64) && empty($cnnDiagnosis)) {
        throw new Exception('Trimite un mesaj, o imagine sau un diagnostic pentru a primi ajutor.');
    }

    if (!empty($deviceHash)) {
        validateDeviceHash($deviceHash);
        logEvent('Device', $deviceHash);
    }

     $rateId = $deviceHash ?: ($_SERVER['REMOTE_ADDR'] ?? 'guest');
    if (!checkRateLimit($rateId)) {
        http_response_code(429);
        echo jsonResponse(false, 'Prea multe cereri, încearcă mai târziu.');
        exit();
    }
    
    // Track usage for text or image
    if (!empty($imageBase64)) {
        trackUsage($pdo, $deviceHash, 'image');
    } else {
        trackUsage($pdo, $deviceHash, 'text');
    }

    // Load conversation history (last 10 messages)
    $historyMessages = loadRecentMessages($pdo, $deviceHash, 10);

    // System instruction for GPT
    $systemMessage = [
        'role' => 'system',
        'content' => <<<PROMPT
Ești un vecin amabil și priceput la grădinărit. Răspunde pe scurt, călduros și încurajator.
Include sfaturi practice și menționează, dacă este specificat, condițiile meteo curente.
Oferă soluții ecologice și aprobate local. Dacă informațiile sunt puține sau imaginea nu este clară, cere cu delicatețe detalii suplimentare.
Nu menționa niciodată că nu poți analiza fotografia; roagă utilizatorul să descrie ce vede pentru un diagnostic mai bun.
La final prezintă un rezumat în 2‑3 puncte ce pot fi urmate ușor.
Răspunde doar la subiecte de agricultură.
PROMPT
    ];

    // Prepare user content for GPT input
    $featuresText = '';
    if (!empty($imageBase64)) {
        $features = analyzeImageFeatures($imageBase64);
        $featuresText = formatFeaturesText($features);
    }
    if (!empty($cnnDiagnosis) && $cnnConfidence >= 0.75) {
        // High confidence diagnosis
        $userContent = "Diagnostic AI: $cnnDiagnosis\nÎntrebarea utilizatorului: $userMessage";
        if ($featuresText) {
            $userContent .= "\n\nImagine: $featuresText";
        }
    } elseif (!empty($imageBase64)) {
        // Image provided without much text
        $warning = '';
        if ($cnnConfidence < 0.6) {
            $warning = "Imaginea pare neclară. Dacă poți, trimite alta sau descrie ce vezi.\n\n";
        }
        if (strlen($userMessage) === 0) {
            $userContent = "Analizează doar fotografia. Nu spune că utilizatorul a menționat culori sau alte informații. " . $warning;
        } else {
            $userContent = $userMessage . "\n\n" . $warning;
        }
        if ($featuresText) {
            $userContent .= "Caracteristici observate: $featuresText";
        }
        if (!empty($cnnDiagnosis)) {
            $userContent .= "\n\nSugestie de diagnostic: $cnnDiagnosis";
        }
    } else {
        $userContent = $userMessage;
    }

    if (!empty($weather)) {
        $userContent .= "\n\nCondiții meteo: $weather";
    }
    $currentUserMessage = ['role' => 'user', 'content' => $userContent];

    $messagesForGPT = array_merge([$systemMessage], $historyMessages, [$currentUserMessage]);

    // Call GPT with full conversation context
    $responseText = getGPTResponse($messagesForGPT);

    // Save user message + GPT response for context next time
    saveChatMessage($pdo, $deviceHash, $userContent, true);
    saveChatMessage($pdo, $deviceHash, $responseText, false);

    if (empty($responseText)) {
        throw new Exception('Răspuns gol de la AI');
    }

    $formattedText = formatResponse($responseText);

    logEvent('Response', ['length' => strlen($formattedText)]);

    echo json_encode([
        'success' => true,
        'response_id' => bin2hex(random_bytes(6)),
        'response' => [
            'text' => $formattedText,
            'raw' => $responseText
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    logEvent('Error', $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'response_id' => bin2hex(random_bytes(6)),
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// --- Helper functions ---

function analyzeImageFeatures($base64) {
    // Calls Google Vision API to extract visual labels and colors as features
    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . getenv('GOOGLE_VISION_KEY');
    $body = [
        'requests' => [[
            'image' => ['content' => $base64],
            'features' => [
                ['type' => 'LABEL_DETECTION', 'maxResults' => 10],
                ['type' => 'IMAGE_PROPERTIES']
            ]
        ]]
    ];
    $opts = ['http' => [
        'header'  => "Content-Type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($body)
    ]];
    $context = stream_context_create($opts);
    $res = @file_get_contents($url, false, $context);
    if (!$res) {
        logEvent('VisionFail', $http_response_header ?? []);
        return [];
    }
    $data = json_decode($res, true);
    $features = [];
    $keywords = ['leaf spot', 'blight', 'mildew', 'rust', 'rot', 'lesion', 'chlorosis'];
    foreach ($data['responses'][0]['labelAnnotations'] ?? [] as $label) {
        if ($label['score'] > 0.75 && preg_match('/' . implode('|', $keywords) . '/i', $label['description'])) {
            $features[] = ucfirst($label['description']);
        }
    }
    $colors = [];
    foreach ($data['responses'][0]['imagePropertiesAnnotation']['dominantColors']['colors'] ?? [] as $color) {
        if ($color['pixelFraction'] > 0.05) {
            $c = $color['color'];
            $colors[] = sprintf("#%02x%02x%02x", $c['red'], $c['green'], $c['blue']);
        }
    }
    if (!empty($colors)) {
        $features[] = "Culori predominante: " . implode(', ', $colors);
    }
    return $features;
}

function formatFeaturesText(array $features) {
    if (empty($features)) return "Nu s-au detectat caracteristici clare.";
    return implode(", ", array_slice($features, 0, 5));
}

function getGPTResponse(array $messages, $retries = 2) {
    $attempt = 0;
    do {
        $attempt++;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT => 12,
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . getenv('OPENAI_API_KEY')
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1200,
                'top_p' => 0.9
            ])
        ]);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($res && $httpCode === 200) {
            $data = json_decode($res, true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($data['choices'][0]['message']['content'])) {
                return formatResponse($data['choices'][0]['message']['content']);
            }
        }
        sleep(1);
    } while ($attempt <= $retries);
    throw new Exception('OpenAI API indisponibil.');
}

function formatResponse($text) {
    return preg_replace([
        '/##\s+/',
        '/\*\*(.*?)\*\*/',
        '/<tratament>/i',
        '/<prevenire>/i'
    ], [
        '🔸 ',
        '$1',
        '💊 Tratament:',
        '🛡 Prevenire:'
    ], $text);
}

function saveChatMessage($pdo, $deviceHash, $messageText, $isUserMessage) {
    $sql = "INSERT INTO chat_history (device_hash, message_text, is_user_message, created_at) VALUES (:device_hash, :message_text, :is_user_message, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':device_hash' => $deviceHash,
        ':message_text' => $messageText,
        ':is_user_message' => $isUserMessage ? 1 : 0
    ]);
}

function loadRecentMessages($pdo, $deviceHash, $limit = 10) {
    $sql = "SELECT message_text, is_user_message FROM chat_history WHERE device_hash = :device_hash ORDER BY created_at DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':device_hash', $deviceHash, PDO::PARAM_STR);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $messages = [];
    foreach (array_reverse($rows) as $row) { // oldest first
        $messages[] = [
            'role' => $row['is_user_message'] ? 'user' : 'assistant',
            'content' => $row['message_text']
        ];
    }
    return $messages;
}

function sanitizeInput($text) {
    $clean = trim(strip_tags($text));
    $clean = preg_replace('/[^\p{L}\p{N}\s.,!?()\-]/u', '', $clean);
    return mb_substr($clean, 0, 300);
}

function validateDeviceHash($hash) {
    if (!preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $hash)) {
        throw new Exception('ID dispozitiv invalid');
    }
}

function trackUsage($pdo, $deviceHash, $type) {
    $allowedTypes = ['image' => 'image_count', 'text' => 'text_count'];
    if (!isset($allowedTypes[$type])) {
        throw new InvalidArgumentException('Invalid tracking type');
    }
    $field = $allowedTypes[$type];

    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("SELECT * FROM usage_tracking WHERE device_hash = ? AND date = ?");
    $stmt->execute([$deviceHash, $today]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usage) {
        $stmt = $pdo->prepare("UPDATE usage_tracking SET $field = $field + 1, last_request = ? WHERE id = ?");
        $stmt->execute([$now, $usage['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO usage_tracking (device_hash, date, $field, created_at, last_request) VALUES (?, ?, 1, ?, ?)");
        $stmt->execute([$deviceHash, $today, $now, $now]);
    }
}

function jsonResponse($success, $payload) {
    return json_encode([
        'success' => $success,
        'error' => $success ? null : $payload,
        'response' => $success ? $payload : null
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function logEvent($label, $data) {
    $dir = __DIR__ . '/logs';
    if (!file_exists($dir)) mkdir($dir, 0775, true);
    $line = date('Y-m-d H:i:s') . " [$label] " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($dir . '/activity.log', $line, FILE_APPEND | LOCK_EX);
}

function checkRateLimit($id, $limit = 30, $window = 3600) {
    $dir = sys_get_temp_dir() . '/gospod_rl';
    if (!file_exists($dir)) mkdir($dir, 0775, true);
    $file = $dir . '/' . sha1($id) . '.json';
    $now = time();
    $data = ['count' => 1, 'start' => $now];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: $data;
        if ($now - $data['start'] > $window) {
            $data = ['count' => 1, 'start' => $now];
        } else {
            $data['count']++;
        }
    }
    file_put_contents($file, json_encode($data));
    return $data['count'] <= $limit;
}

function getInputData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // logEvent('JSONDecodeError', ['error' => json_last_error_msg(), 'raw' => $json]);
            return [];
        }

        return is_array($data) ? $data : [];
    }
    return $_POST;
}

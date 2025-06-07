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
    $cnnDiagnosis = sanitizeInput($input['diagnosis'] ?? '');
    $cnnConfidence = isset($input['confidence']) ? floatval($input['confidence']) : 1.0;
    $cnnConfidence = max(0, min(1, $cnnConfidence));
    $deviceHash = sanitizeInput($input['device_hash'] ?? '');

    if (empty($userMessage) && empty($imageBase64) && empty($cnnDiagnosis)) {
        throw new Exception('Trimite un mesaj, o imagine sau un diagnostic pentru a primi ajutor.');
    }

    if (!empty($deviceHash)) {
        validateDeviceHash($deviceHash);
        logEvent('Device', $deviceHash);
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
Ești vecinul prietenos care se pricepe la grădinărit și ajuți utilizatorii aplicației GospodApp.
Vorbește cald și politicos, pe un ton optimist și uman, ca într-o conversație între vecini.

Oferă sfaturi practice și valoroase pentru plante, culturi, boli sau dăunători și menționează, când e util, cum poate influența vremea situația curentă. Dacă informațiile sunt insuficiente, cere cu grijă mai multe detalii.
Dacă nu ai suficiente informații, cere politicos mai multe detalii despre simptomele plantei sau condițiile de creștere, pentru a putea face un diagnostic mai bun.
Recomandă soluții ecologice și sigure, evitând jargonul tehnic. Dacă întrebarea nu ține de agricultură, explică politicos că poți ajuta doar pe această temă.
Încheie cu un scurt rezumat în câteva puncte despre ce poate face utilizatorul mai departe. Limitează răspunsul la maximum 5 propoziții clare și utile.
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
        // Low confidence or no diagnosis but image is present
        $userContent = "$userMessage\n\nSimptome vizuale detectate: $featuresText";
        if (!empty($cnnDiagnosis) && $cnnConfidence < 0.75) {
            $userContent .= "\n\nSugestie de diagnostic: $cnnDiagnosis (nesigur). Cere utilizatorului mai multe detalii.";
        } elseif ($cnnConfidence < 0.75) {
            $userContent .= "\n\nNotă: Modelul AI a fost nesigur. Întreabă utilizatorul mai multe detalii despre imagine.";
        }
    } else {
        $userContent = $userMessage;
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

function getGPTResponse(array $messages) {
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

    if (!$res) {
        throw new Exception('Eroare la serviciul OpenAI: Nu s-a primit răspuns.');
    }
    if ($httpCode !== 200) {
        $errorData = json_decode($res, true);
        $errorMsg = $errorData['error']['message'] ?? 'Eroare necunoscută de la API.';
        throw new Exception("OpenAI API Error ($httpCode): $errorMsg");
    }
    $data = json_decode($res, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Răspuns JSON invalid de la OpenAI: ' . json_last_error_msg());
    }
    if (empty($data['choices'][0]['message']['content'])) {
        throw new Exception('Răspuns invalid de la AI: conținut lipsă.');
    }
    $raw = $data['choices'][0]['message']['content'];
    return formatResponse($raw);
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

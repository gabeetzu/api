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
    $isPremium = filter_var($input['is_premium'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (empty($userMessage) && empty($cnnDiagnosis)) {
        throw new Exception('Trimite un mesaj sau un diagnostic pentru a primi ajutor.');
    }

    if (!empty($deviceHash)) {
        validateDeviceHash($deviceHash);
        logEvent('Device', $deviceHash);
        
        $stmt = $pdo->prepare("SELECT pending_deletion, deletion_due_at FROM usage_tracking WHERE device_hash = ?");
        $stmt->execute([$deviceHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['pending_deletion']) {
            http_response_code(403);
            echo jsonResponse(false, "Contul t\u0103u este programat pentru \u0219tergere. Accesul este restric\u021bionat pentru 7 zile.");
            exit();
        }

        trackUsage($pdo, $deviceHash, 'text');
    }

    $rateId = $deviceHash ?: ($_SERVER['REMOTE_ADDR'] ?? 'guest');
    if (!checkRateLimit($rateId, 30, 3600, $isPremium)) {
        http_response_code(429);
        echo jsonResponse(false, 'Prea multe cereri, încearcă mai târziu.');
        exit();
    }

    // Load last messages from DB as array of messages with role/content
$historyMessages = loadRecentMessages($pdo, $deviceHash, 10);

// System instruction message
$systemMessage = [
    'role' => 'system',
    'content' => <<<PROMPT
Ești un asistent agronom prietenos și empatic pentru aplicația GospodApp. Vorbești clar și simplu în limba română, ca și cum ai explica unui prieten care are grijă de grădina lui.

Rolul tău este să oferi sfaturi practice, concrete și ușor de urmat pentru probleme legate de plante, culturi, boli și dăunători. Folosește un ton cald, optimist și încurajator.

Dacă nu ai suficiente informații, cere politicos mai multe detalii despre simptomele plantei sau condițiile de creștere, pentru a putea face un diagnostic mai bun.

Oferă recomandări ecologice, sigure și, dacă e cazul, produse aprobate în UE. Evită jargonul tehnic sau explicațiile prea complicate.

Dacă întrebarea nu este legată de agricultură sau grădinărit, explică politicos că poți ajuta doar cu subiecte agricole și sugerează să ceară ajutor în altă parte.

La final, rezumă în câteva puncte scurte ce poate face utilizatorul mai departe.

Răspunde în maxim 5 propoziții clare și utile.
PROMPT
];

// Current user message
$currentUserMessage = ['role' => 'user', 'content' => $userMessage];

// Combine all messages for GPT
$messagesForGPT = array_merge([$systemMessage], $historyMessages, [$currentUserMessage]);

// Call GPT with messages array instead of a single string prompt
$responseText = getGPTResponse($messagesForGPT);

// Save user message and assistant reply to DB for context next time
saveChatMessage($pdo, $deviceHash, $userMessage, true);
saveChatMessage($pdo, $deviceHash, $responseText, false);


    if (empty($responseText)) {
        throw new Exception('Răspuns gol de la AI');
    }

    $formattedText = formatResponse($responseText);
    
    logEvent('TextResponse', ['length' => strlen($formattedText)]);
    
    // Consistent response structure
    echo json_encode([
        'success' => true,
        'response_id' => bin2hex(random_bytes(6)),
        'response' => [
            'text' => $formattedText,
            'raw' => $responseText
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    logEvent('TextError', $e->getMessage());
    http_response_code(400);
    echo json_encode([
    'success' => false,
    'response_id' => bin2hex(random_bytes(6)),
    'error' => $e->getMessage()
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// --- Helper Functions ---
function buildPrompt($userMessage, $diagnosis) {
    if (!empty($diagnosis)) {
        return <<<PROMPT
Diagnostic AI: $diagnosis
Întrebarea utilizatorului: $userMessage

Instrucțiuni:
- Explică simplu problema în română
- Oferă 2-3 pași ecologici și practici
- Încheie cu un mesaj pozitiv și încurajator
- Răspunde doar la întrebări despre plante și grădinărit
PROMPT;
    }
    
    return <<<PROMPT
Întrebarea utilizatorului: $userMessage

Instrucțiuni:
- Răspunde în română, simplu și clar
- Oferă sfaturi practice pentru grădinărit
- Folosește emoji unde e potrivit
- Dacă întrebarea nu e despre plante, explică politicos că poți ajuta doar cu grădinăritul
PROMPT;
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
            // Log the malformed JSON for debugging if you want:
            // logEvent('JSONDecodeError', ['error' => json_last_error_msg(), 'raw' => $json]);
            return [];  // Or throw new Exception('Invalid JSON input');
        }

        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function loadRecentMessages($pdo, $deviceHash, $limit = 10) {
    $sql = "SELECT message_text, is_user_message FROM chat_history WHERE device_hash = :device_hash ORDER BY created_at DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':device_hash', $deviceHash, PDO::PARAM_STR);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $messages = [];
    foreach (array_reverse($rows) as $row) { // Reverse to oldest first
        $messages[] = [
            'role' => $row['is_user_message'] ? 'user' : 'assistant',
            'content' => $row['message_text']
        ];
    }
    return $messages;
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


function sanitizeInput($text) {
    $clean = trim(strip_tags($text));
    $clean = preg_replace('/[^\p{L}\p{N}\s.,!?()\-]/u', '', $clean); // Added hyphen to allowed chars
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

function checkRateLimit($id, $limit = 30, $window = 3600, $isPremium = false) {
    if ($isPremium) return true;
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

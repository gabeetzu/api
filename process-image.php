<?php
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    $log = "[" . date('Y-m-d H:i:s') . "] PHP ERROR: $errstr in $errfile on line $errline\n";
    file_put_contents('/var/data/logs/errors.csv', $log, FILE_APPEND);
});

header('Content-Type: application/json; charset=utf-8');
$allowedOrigins = [
    'https://netlify.app',
    'https://gospodapp.ro',
    'https://gabeetzu-project.onrender.com'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins) || strpos($origin, '.onrender.com') !== false) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');
mb_internal_encoding("UTF-8");

// Resource limits
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 60);
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');

ini_set('display_errors', 0);
error_reporting(0);

// --- CORS Preflight ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- API Key Validation ---
//$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
//$expectedKey = getenv('API_SECRET_KEY');

//if ($expectedKey && !hash_equals($expectedKey, $apiKey)) {
//    logEvent('Unauthorized', ['ip' => $_SERVER['REMOTE_ADDR']]);
//    http_response_code(401);
//    echo jsonResponse(false, 'Acces neautorizat');
//    exit();
//}

// --- Database Connection ---
try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST'),
        getenv('DB_NAME')
    );
    
    $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (Exception $e) {
    logEvent('DBError', $e->getMessage());
    http_response_code(500);
    sendResponse(false, null, 'Database connection failed');
}

// --- Main Logic ---
try {
    $input = getInputData();
    logEvent('Input', $input);
    debugLog('Request received', $input);

    $userMessage = sanitizeInput($input['message'] ?? '');
    $imageBase64 = $input['image'] ?? '';
        // Limit uploaded images to 3MB to keep requests lightweight
    if ($imageBase64 && strlen($imageBase64) > 3 * 1024 * 1024) {
        throw new Exception('Imaginea depășește 3MB.');
    }
    if ($imageBase64 && !preg_match('/^[A-Za-z0-9+\/=\s]+$/', $imageBase64)) {
        throw new Exception('Format imagine invalid.');
    }
   $plantLabel = '';
    $cnnConfidence = 0.0;
    $imageId = substr(sha1($imageBase64 ?: microtime()), 0, 8);
    
    if ($imageBase64) {
        $uploadDir = ensureUploadDirectory();
        $filename = 'img_' . $imageId . '.jpg';
        $filePath = $uploadDir . '/' . $filename;
        if (!preg_match('/^data:image\/\w+;base64,/', $imageBase64)) {
            throw new Exception('Invalid image format');
        }
        $data = preg_replace('#^data:image/\w+;base64,#i', '', $imageBase64);
         $imageData = base64_decode($data, true);
        if ($imageData === false) {
            throw new Exception('Failed to decode image data');
        }

        $imageInfo = getimagesizefromstring($imageData);
        if ($imageInfo === false) {
            throw new Exception('Invalid image file');
        }

        if (file_put_contents($filePath, $imageData) === false) {
            throw new Exception('Failed to save image file');
        }

        $cnn = executeCNNAnalysis($filePath);
        if (is_array($cnn) && isset($cnn['label'])) {
            $plantLabel = sanitizeInput($cnn['label']);
            $cnnConfidence = isset($cnn['confidence']) ? floatval($cnn['confidence']) : 0;
        } else {
            logEvent('YOLOFail', $cnn);
        }
        debugLog('CNN result', ['label' => $plantLabel, 'confidence' => $cnnConfidence]);
    }

    $cnnDiagnosis = $plantLabel;
    $confidence = $cnnConfidence;
    $label = $plantLabel ?: 'necunoscută';
    error_log("🌿 [YOLO label] $label for image $imageId", 3, "/var/log/gospodapp.log");
    $confidencePercent = round(max(0, min(1, $cnnConfidence)) * 100);
    $weather = sanitizeInput($input['weather'] ?? '');
    $deviceHash = sanitizeInput($input['device_hash'] ?? '');
    $refCode    = sanitizeInput($input['ref_code'] ?? '');
    try {
        $stmt = $pdo->prepare("INSERT INTO usage_log (device_hash, label, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$deviceHash, $plantLabel]);
    } catch (Exception $e) {
        // ignore logging failures
    }
    $referralReward = false;
    $userName = sanitizeInput($input['user_name'] ?? '');
    if ($userName && !preg_match('/^[\p{L}\p{M} \'-]{2,30}$/u', $userName)) {
        throw new Exception('Nume invalid');
    }

    if (empty($userMessage) && empty($imageBase64) && empty($cnnDiagnosis)) {
        throw new Exception('Trimite un mesaj, o imagine sau un diagnostic pentru a primi ajutor.');
    }
    
    $isPremium = filter_var($input['is_premium'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    if (!empty($deviceHash)) {
        validateDeviceHash($deviceHash);
        logEvent('Device', $deviceHash);
        
        $stmt = $pdo->prepare("SELECT pending_deletion, deletion_due_at FROM usage_tracking WHERE device_hash = ?");
        $stmt->execute([$deviceHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['pending_deletion']) {
            http_response_code(403);
            sendResponse(false, null, 'Contul tău este programat pentru ștergere. Accesul este restricționat pentru 7 zile.');
        }

        // --- Referral Handling ---
        if (!empty($refCode) && $refCode !== $deviceHash) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM referrals WHERE invited_hash = ?");
            $stmt->execute([$deviceHash]);
            $alreadyReferred = $stmt->fetchColumn() > 0;

            if (!$alreadyReferred) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO referrals (inviter_hash, invited_hash) VALUES (?, ?)");
                $stmt->execute([$refCode, $deviceHash]);

                $stmt = $pdo->prepare("\n      UPDATE usage_tracking\n         SET premium = 1,\n             premium_until = DATE_ADD(NOW(), INTERVAL 30 DAY)\n       WHERE device_hash IN (?, ?)\n    ");
                $stmt->execute([$refCode, $deviceHash]);

                $referralReward = true;
            }
        }        
    }

     $rateId = $deviceHash ?: ($_SERVER['REMOTE_ADDR'] ?? 'guest');
    if (!checkRateLimit($rateId, 30, 3600, $isPremium)) {
        http_response_code(429);
        sendResponse(false, null, 'Prea multe cereri, încearcă mai târziu.');
    }
    
    // Track usage for text or image
    if (!empty($imageBase64)) {
        trackUsage($pdo, $deviceHash, 'image');
    } else {
        trackUsage($pdo, $deviceHash, 'text');
    }

    if ($deviceHash && $userName) {
        $stmt = $pdo->prepare("\n        UPDATE usage_tracking\n           SET user_name = :name\n         WHERE device_hash = :hash\n           AND date = CURDATE()\n    ");
        $stmt->execute([':name' => $userName, 'hash' => $deviceHash]);
    }

    // Load conversation history (last 10 messages)
    $historyMessages = loadRecentMessages($pdo, $deviceHash, 10);

    $storedName = '';
    if ($deviceHash) {
        $stmt = $pdo->prepare("\n            SELECT user_name FROM usage_tracking\n             WHERE device_hash = :hash AND user_name IS NOT NULL\n             ORDER BY date DESC LIMIT 1\n        ");
        $stmt->execute([':hash' => $deviceHash]);
        $storedName = $stmt->fetchColumn() ?: '';
    }

    $greet = $storedName ? "Vorbește pe nume: $storedName.\n" : '';
    // System instruction for GPT
    $systemMessage = [
        'role' => 'system',
        'content' => $greet . <<<PROMPT
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
        $userContent = <<<TEXT
Imaginea a fost analizată automat de un model AI specializat în boli ale plantelor, antrenat pe imagini de frunze.

Modelul a identificat următoarea clasă: "$cnnDiagnosis" (încredere estimată: {$confidencePercent}%)

Aceste informații au fost generate automat. Utilizatorul poate adăuga și alte detalii manual.

$featuresText

Întrebarea sau observația utilizatorului (dacă a fost trimisă): $userMessage
TEXT;
              
    } elseif (!empty($imageBase64)) {
        // Image provided without much text
        $warning = '';
           if ($cnnConfidence < 0.6) {
            $warning = "Imaginea a fost analizată, dar rezultatul nu este sigur. Te rugăm să descrii simptomele sau să trimiți o altă fotografie pentru un diagnostic mai clar.\n\n";
        }

        if (strlen(trim($userMessage)) === 0) {
            $userContent = "Analizează doar fotografia. Nu presupune că utilizatorul a descris culori sau simptome. Oferă un răspuns vizual bazat pe imagine și cere detalii suplimentare dacă este cazul.\n\n" . $warning;
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
    $finalPrompt = "Imaginea pare să fie afectată de: $plantLabel (cu încredere $confidence). Oferă sfaturi legate de această boală, chiar dacă e incertă. Rămâi pozitiv și empatic.";
    $userContent = $finalPrompt . "\n\n" . $userContent;
    $currentUserMessage = ['role' => 'user', 'content' => $userContent];

    $messagesForGPT = formatMessagesForGPT($systemMessage, $historyMessages, $currentUserMessage);

    // Call GPT with full conversation context
    $responseText = getGPTResponse($messagesForGPT);
    debugLog('GPT response', ['response_length' => strlen($responseText)]);

    // Save user message + GPT response for context next time
    saveChatMessage($pdo, $deviceHash, $userContent, true);
    saveChatMessage($pdo, $deviceHash, $responseText, false);

    if (empty($responseText)) {
        throw new Exception('Răspuns gol de la AI');
    }

    $formattedText = formatResponse($responseText);

    logEvent('Response', ['length' => strlen($formattedText)]);

    sendSuccessResponse($formattedText, [
        'raw' => $responseText,
        'reward' => $referralReward ? 'referral_success' : null
    ]);

}
catch (Exception $e) {
    logError('Process Image Error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    sendErrorResponse('Internal server error occurred');
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
        'content' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
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
    $apiKey = getenv('OPENAI_API_KEY');
    if (empty($apiKey)) {
        throw new Exception('OpenAI API key not configured');
    }

    $attempt = 0;
    do {
        $attempt++;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1200,
                'top_p' => 0.9
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);
        
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('cURL error: ' . $curlError);
        }

        if ($httpCode === 200 && $res) {
            $data = json_decode($res, true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($data['choices'][0]['message']['content'])) {
                return formatResponse($data['choices'][0]['message']['content']);
            }
            } elseif ($httpCode !== 200) {
            throw new Exception('OpenAI API error: HTTP ' . $httpCode);
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

function formatMessagesForGPT($systemMessage, array $historyMessages, array $currentMessage) {
    $messages = [$systemMessage];
    foreach ($historyMessages as $msg) {
        $messages[] = [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
    }
    $messages[] = $currentMessage;
    return $messages;
}

function sanitizeInput($text) {
    $clean = trim(strip_tags($text));
    $clean = preg_replace('/[^\p{L}\p{N}\s.,!?()\-:\"\']/u', '', $clean);
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

    $stmt = $pdo->prepare("SELECT id FROM usage_tracking WHERE device_hash = ? AND date = ?");
    $stmt->execute([$deviceHash, $today]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        $sql = "UPDATE usage_tracking SET {$field} = {$field} + 1, last_request = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$now, $existingId]);
    } else {
        $sql = "INSERT INTO usage_tracking (device_hash, date, {$field}, created_at, last_request) VALUES (?, ?, 1, ?, ?)";
        $stmt = $pdo->prepare($sql);
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

function sendResponse($success, $data = null, $error = null) {
    $response = [
        'success' => $success,
        'response' => $success ? $data : null,
        'error' => $success ? null : $error,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function sendSuccessResponse($responseText, $additionalData = []) {
    $payload = array_merge([
        'text' => $responseText
    ], $additionalData);
    sendResponse(true, $payload, null);
}

function sendErrorResponse($errorMessage, $errorCode = 500) {
    http_response_code($errorCode);
    sendResponse(false, null, $errorMessage);
}

function ensureUploadDirectory() {
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Cannot create upload directory');
        }
    }
    if (!is_writable($uploadDir) && !chmod($uploadDir, 0755)) {
        throw new Exception('Upload directory not writable');
    }
    return $uploadDir;
}

function executeCNNAnalysis($imagePath) {
    $pythonScript = __DIR__ . '/cnn_yolo_infer.py';
    if (!file_exists($pythonScript)) {
        throw new Exception('CNN script not found at: ' . $pythonScript);
    }
    $command = sprintf(
        'python3 %s %s 2>&1',
        escapeshellarg($pythonScript),
        escapeshellarg($imagePath)
    );
    $output = shell_exec($command);
    if (empty($output)) {
        throw new Exception('CNN script produced no output');
    }
    $result = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('CNN script returned invalid JSON: ' . $output);
    }
    return $result;
}

function logError($message, $context = []) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];
    file_put_contents(
        $logDir . '/error.log',
        json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

function logEvent($label, $data) {
    $dir = __DIR__ . '/logs';
    if (!file_exists($dir)) mkdir($dir, 0775, true);
    $line = date('Y-m-d H:i:s') . " [$label] " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    file_put_contents($dir . '/activity.log', $line, FILE_APPEND | LOCK_EX);
}

function debugLog($message, $data = []) {
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0775, true);
    }

    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'data' => $data,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? ''
    ];

    file_put_contents(
        $logDir . '/debug.log',
        json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND | LOCK_EX
    );
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
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $data['count'] <= $limit;
}

function getInputData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'multipart/form-data') !== false) {
        $data = $_POST;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['image']['tmp_name'];
            $mime = mime_content_type($tmp) ?: 'image/jpeg';
            $imgData = file_get_contents($tmp);
            $data['image'] = 'data:' . $mime . ';base64,' . base64_encode($imgData);
        }
        return $data;
    }

        if (stripos($contentType, 'application/json') !== false) {
        $json = file_get_contents('php://input');
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
    
    return $_POST;
}

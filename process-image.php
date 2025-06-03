<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Verify API Key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== getenv('API_SECRET_KEY')) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

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

    // ANTI-BOT PROTECTION - Check rate limits first
    checkRateLimits($deviceHash);
    detectSuspiciousImageActivity($deviceHash, $imageBase64);

    // Connect to database
    $pdo = connectToDatabase();
    
    // Check image usage limits with enhanced messaging
    checkUsageLimits($pdo, $deviceHash, 'image');

    // Step 1: Analyze with Google Vision
    $visionResults = analyzeWithGoogleVision($imageBase64);

    // Validate Vision API results
    if (empty($visionResults['objects']) && empty($visionResults['labels'])) {
        throw new Exception('Nu am putut identifica plante în această imagine. Încercați o poză mai clară cu planta în prim-plan.');
    }

    // Step 2: Get enhanced treatment from OpenAI
    $treatment = getTreatmentFromOpenAI($visionResults);

    // Save to chat history
    saveChatHistory($pdo, $deviceHash, "", true, 'image', $imageBase64);
    saveChatHistory($pdo, $deviceHash, $treatment, false, 'text', null);

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

// ANTI-BOT PROTECTION FUNCTIONS
function checkRateLimits($deviceHash) {
    $rateLimitFile = '/tmp/rate_limit_' . $deviceHash . '.txt';
    $currentTime = time();
    
    // Clean old entries
    if (file_exists($rateLimitFile)) {
        $requests = json_decode(file_get_contents($rateLimitFile), true) ?: [];
        $requests = array_filter($requests, function($timestamp) use ($currentTime) {
            return ($currentTime - $timestamp) < 60; // Keep only last minute
        });
    } else {
        $requests = [];
    }
    
    // Check if limit exceeded (10 requests per minute)
    if (count($requests) >= 10) {
        throw new Exception('Prea multe cereri. Încercați din nou în 1 minut.');
    }
    
    // Add current request
    $requests[] = $currentTime;
    file_put_contents($rateLimitFile, json_encode($requests));
}

function detectSuspiciousImageActivity($deviceHash, $imageBase64) {
    $imageHash = md5($imageBase64);
    $suspiciousFile = '/tmp/img_suspicious_' . $deviceHash . '_' . $imageHash . '.txt';
    $currentTime = time();
    
    // Check for repeated identical images
    if (file_exists($suspiciousFile)) {
        $data = json_decode(file_get_contents($suspiciousFile), true);
        $count = $data['count'] ?? 0;
        $lastTime = $data['last_time'] ?? 0;
        
        // Reset count if more than 1 hour passed
        if (($currentTime - $lastTime) > 3600) {
            $count = 0;
        }
        
        $count++;
        
        if ($count > 2) {
            throw new Exception('Aceeași imagine trimisă prea des. Încercați o imagine diferită.');
        }
        
        file_put_contents($suspiciousFile, json_encode([
            'count' => $count,
            'last_time' => $currentTime
        ]));
    } else {
        file_put_contents($suspiciousFile, json_encode([
            'count' => 1,
            'last_time' => $currentTime
        ]));
    }
    
    // Check for rapid-fire image requests
    $rapidFile = '/tmp/rapid_img_' . $deviceHash . '.txt';
    if (file_exists($rapidFile)) {
        $rapidRequests = json_decode(file_get_contents($rapidFile), true) ?: [];
        $rapidRequests = array_filter($rapidRequests, function($timestamp) use ($currentTime) {
            return ($currentTime - $timestamp) < 30; // Last 30 seconds for images
        });
        
        if (count($rapidRequests) >= 3) {
            throw new Exception('Prea multe imagini trimise rapid. Așteptați puțin între analize.');
        }
        
        $rapidRequests[] = $currentTime;
        file_put_contents($rapidFile, json_encode($rapidRequests));
    } else {
        file_put_contents($rapidFile, json_encode([$currentTime]));
    }
}

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
            INSERT INTO usage_tracking (device_hash, date, image_count, premium) 
            VALUES (?, ?, 0, 0)
        ");
        $stmt->execute([$deviceHash, $today]);
        $usage = ['image_count' => 0, 'premium' => 0];
    }

    if ($type === 'image') {
        $limit = $usage['premium'] ? 5 : 1;
        $remaining = $limit - $usage['image_count'];
        
        if ($usage['image_count'] >= $limit) {
            if ($usage['premium']) {
                throw new Exception('Ați atins limita zilnică de 5 imagini premium. Reveniți mâine pentru analize noi!');
            } else {
                throw new Exception('Ați folosit analiza gratuită de astăzi! Upgradeați la Premium pentru 5 analize zilnice sau urmăriți o reclamă pentru o analiză extra.');
            }
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

function analyzeWithGoogleVision($imageBase64) {
    $googleVisionKey = getenv('GOOGLE_VISION_KEY');
    if (!$googleVisionKey) {
        throw new Exception('Serviciul de analiză imagini nu este disponibil momentan');
    }

    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . $googleVisionKey;

    $requestData = [
        'requests' => [[
            'image' => ['content' => $imageBase64],
            'features' => [
                ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 15],
                ['type' => 'LABEL_DETECTION', 'maxResults' => 15]
            ]
        ]]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));

    $response = curl_exec($ch);
    $result = json_decode($response, true);

    if (isset($result['error'])) {
        throw new Exception('Eroare la analiza imaginii: ' . $result['error']['message']);
    }

    $objects = [];
    $labels = [];

    if (isset($result['responses'][0]['localizedObjectAnnotations'])) {
        foreach ($result['responses'][0]['localizedObjectAnnotations'] as $obj) {
            $objects[] = $obj['name'];
        }
    }

    if (isset($result['responses'][0]['labelAnnotations'])) {
        foreach ($result['responses'][0]['labelAnnotations'] as $label) {
            $labels[] = $label['description'];
        }
    }

    return [
        'objects' => $objects,
        'labels' => $labels
    ];
}

function getTreatmentFromOpenAI($visionResults) {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        throw new Exception('Serviciul de analiză nu este disponibil momentan');
    }

    $objects = implode(', ', $visionResults['objects']);
    $labels = implode(', ', $visionResults['labels']);

    $systemPrompt = "Ești un expert în grădinărit din România cu 30 de ani experiență. 

REGULI IMPORTANTE pentru analiza imaginilor:
- Analizezi imaginea bazându-te pe obiectele și etichetele detectate
- Identifici tipul de plantă, problemele vizibile și starea generală
- Dai sfaturi practice și specifice pentru clima României
- Menționezi anotimpul potrivit pentru tratamente
- Folosești termeni simpli, fără formatare specială
- Răspunsurile să fie între 150-400 de cuvinte, detaliate și utile
- Dacă vezi semne de boală sau dăunători, explici cum să tratezi

Cunoștințele tale includ:
- Identificarea plantelor românești comune
- Boli și dăunători specifici României
- Tratamente naturale și chimice disponibile local
- Tehnici de îngrijire pentru clima continentală";

    $prompt = "Analizează această imagine de grădină. 

Obiecte detectate: $objects
Etichete identificate: $labels

Te rog să îmi oferi:
1. Ce tip de plantă/plante vezi
2. Starea lor de sănătate
3. Probleme vizibile (dacă există)
4. Sfaturi concrete de îngrijire
5. Când și cum să aplici tratamentele";

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
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 1200,
        'temperature' => 0.7
    ]));

    $response = curl_exec($ch);
    $data = json_decode($response, true);

    if (isset($data['error'])) {
        throw new Exception('Nu am putut analiza imaginea momentan. Încercați din nou.');
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception('Analiza imaginii a eșuat. Încercați cu o altă poză.');
    }

    $content = $data['choices'][0]['message']['content'];
    return cleanForTTS($content);
}

function cleanForTTS($text) {
    $text = preg_replace('/\*+/', '', $text);
    $text = preg_replace('/^\d+\.\s*/m', '', $text);
    $text = preg_replace('/^[\-\*\+]\s*/m', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/[#@$%^&(){}[\]|\\]/', '', $text);
    $text = preg_replace('/\s*([,.!?;:])\s*/', '$1 ', $text);
    $text = preg_replace('/\s*\(\d+%\)\s*/', ' ', $text);
    return trim($text);
}

?>

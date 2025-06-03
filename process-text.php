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

ini_set('max_execution_time', '60');
ini_set('memory_limit', '256M');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['message']) || !isset($data['device_hash'])) {
        throw new Exception('Date lipsă: Mesajul sau hash-ul dispozitivului nu au fost primite');
    }

    $message = trim($data['message']);
    $deviceHash = $data['device_hash'];

    if (empty($message)) {
        throw new Exception('Mesajul nu poate fi gol');
    }

    // ANTI-BOT PROTECTION - Check rate limits first
    checkRateLimits($deviceHash);
    detectSuspiciousActivity($deviceHash, $message);

    // Connect to database
    $pdo = connectToDatabase();
    
    // Check usage limits
    checkUsageLimits($pdo, $deviceHash, 'text');

    // Save user message to chat history
    saveChatHistory($pdo, $deviceHash, $message, true, 'text', null);

    // Get response from OpenAI
    $response = getTextResponseFromOpenAI($message);

    // Save bot response to chat history
    saveChatHistory($pdo, $deviceHash, $response, false, 'text', null);

    // Record usage
    recordUsage($pdo, $deviceHash, 'text');

    echo json_encode([
        'success' => true,
        'response' => $response
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// ANTI-BOT FUNCTIONS
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

function detectSuspiciousActivity($deviceHash, $message) {
    $messageHash = md5($message);
    $suspiciousFile = '/tmp/suspicious_' . $deviceHash . '_' . $messageHash . '.txt';
    $currentTime = time();
    
    // Check for repeated identical messages
    if (file_exists($suspiciousFile)) {
        $data = json_decode(file_get_contents($suspiciousFile), true);
        $count = $data['count'] ?? 0;
        $lastTime = $data['last_time'] ?? 0;
        
        // Reset count if more than 1 hour passed
        if (($currentTime - $lastTime) > 3600) {
            $count = 0;
        }
        
        $count++;
        
        if ($count > 3) {
            throw new Exception('Mesaj repetat prea des. Încercați o întrebare diferită.');
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
    
    // Check for rapid-fire requests
    $rapidFile = '/tmp/rapid_' . $deviceHash . '.txt';
    if (file_exists($rapidFile)) {
        $rapidRequests = json_decode(file_get_contents($rapidFile), true) ?: [];
        $rapidRequests = array_filter($rapidRequests, function($timestamp) use ($currentTime) {
            return ($currentTime - $timestamp) < 10; // Last 10 seconds
        });
        
        if (count($rapidRequests) >= 5) {
            throw new Exception('Cereri prea rapide. Așteptați câteva secunde.');
        }
        
        $rapidRequests[] = $currentTime;
        file_put_contents($rapidFile, json_encode($rapidRequests));
    } else {
        file_put_contents($rapidFile, json_encode([$currentTime]));
    }
}

// ... [Keep all your existing functions: connectToDatabase, checkUsageLimits, etc.] ...

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
        SELECT text_count, image_count, premium, extra_questions 
        FROM usage_tracking 
        WHERE device_hash = ? AND date = ?
    ");
    $stmt->execute([$deviceHash, $today]);
    $usage = $stmt->fetch();
    
    if (!$usage) {
        $stmt = $pdo->prepare("
            INSERT INTO usage_tracking (device_hash, date, text_count, image_count, premium, extra_questions) 
            VALUES (?, ?, 0, 0, 0, 0)
        ");
        $stmt->execute([$deviceHash, $today]);
        $usage = ['text_count' => 0, 'image_count' => 0, 'premium' => 0, 'extra_questions' => 0];
    }
    
    if ($type === 'text') {
        $limit = $usage['premium'] ? 100 : 10;
        $totalUsed = $usage['text_count'] - $usage['extra_questions'];
        $remaining = $limit - $totalUsed;
        
        if ($totalUsed >= $limit) {
            if ($usage['premium']) {
                throw new Exception('Ați atins limita zilnică de 100 întrebări premium. Impresionant! Reveniți mâine pentru mai multe.');
            } else {
                throw new Exception('Ați folosit toate cele 10 întrebări gratuite de astăzi! Upgradeați la Premium pentru întrebări nelimitate sau urmăriți o reclamă pentru 3 întrebări extra.');
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
    $stmt->execute([$deviceHash, $messageText, $isUserMessage ? 1 : 0, $messageType, $imageData]);
}

function getTextResponseFromOpenAI($message) {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        throw new Exception('Serviciul de răspunsuri nu este disponibil momentan');
    }

    $systemPrompt = "Ești un expert în grădinărit din România cu 30 de ani experiență, cunoscut pentru sfaturile practice și rezultatele excelente.

PERSONALITATEA TA:
- Vorbești natural și prietenos, ca un vecin cu experiență
- Ești entuziast și încurajator
- Dai sfaturi concrete și testate personal
- Explici de ce funcționează anumite metode

REGULI IMPORTANTE:
- Răspunzi DOAR la întrebări despre plante, grădină, agricultură și grădinărit
- Dacă întrebarea nu e despre grădinărit, spui politicos că te specializezi doar în grădinărit
- Folosești termeni simpli, accesibili oricărui grădinar român
- Menționezi anotimpul potrivit și specificul climei românești
- Eviți asteriscuri, numere în paranteză sau formatare specială
- Răspunsurile să fie între 120-350 de cuvinte, clare și practice
- Incluzi trucuri și secrete din experiența ta

EXPERTIZA TA:
- Plantele cultivate în clima României (continentală)
- Boli și dăunători comuni în România și tratamentele lor
- Produse și îngrășăminte disponibile în România
- Perioade optime pentru fiecare activitate de grădinărit
- Tehnici tradiționale românești și moderne
- Soiuri rezistente la clima noastră
- Probleme specifice solurilor românești";

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
        'max_tokens' => 900,
        'temperature' => 0.8,
        'top_p' => 0.9
    ]));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Nu pot răspunde momentan. Încercați din nou în câteva secunde.');
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        throw new Exception('Serviciul este temporar indisponibil. Încercați din nou.');
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception('Nu am putut genera un răspuns. Reformulați întrebarea.');
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

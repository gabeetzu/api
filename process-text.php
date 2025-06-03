<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// ENHANCED DEBUG: Log all environment variables and request details
error_log("=== ENHANCED DEBUG START ===");
error_log("API_SECRET_KEY exists: " . (getenv('API_SECRET_KEY') ? 'YES' : 'NO'));
error_log("OPENAI_API_KEY exists: " . (getenv('OPENAI_API_KEY') ? 'YES' : 'NO'));
error_log("DB_HOST: " . getenv('DB_HOST'));
error_log("DB_NAME: " . getenv('DB_NAME'));
error_log("DB_USER: " . getenv('DB_USER'));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
error_log("User agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'not set'));
error_log("X-API-Key header: " . ($_SERVER['HTTP_X_API_KEY'] ?? 'not set'));

$rawInput = file_get_contents('php://input');
error_log("Raw input length: " . strlen($rawInput));
error_log("Raw input preview: " . substr($rawInput, 0, 200) . "...");
error_log("=== ENHANCED DEBUG END ===");

// Verify API Key with enhanced logging
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');

error_log("API Key comparison - Received: " . substr($apiKey, 0, 10) . "... Expected: " . substr($expectedKey, 0, 10) . "...");

if ($apiKey !== $expectedKey) {
    error_log("API key mismatch - Authentication failed");
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

error_log("API key verified successfully");

ini_set('max_execution_time', '60');
ini_set('memory_limit', '256M');

try {
    error_log("Starting request processing...");
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['message']) || !isset($data['device_hash'])) {
        error_log("Invalid request data - missing required fields");
        throw new Exception('Date lipsă: Mesajul sau hash-ul dispozitivului nu au fost primite');
    }

    $message = trim($data['message']);
    $deviceHash = $data['device_hash'];

    error_log("Processing message from device: $deviceHash");
    error_log("Message preview: " . substr($message, 0, 50) . "...");

    if (empty($message)) {
        error_log("Empty message received from device: $deviceHash");
        throw new Exception('Mesajul nu poate fi gol');
    }

    // ANTI-BOT PROTECTION - Check rate limits first
    error_log("Checking rate limits for device: $deviceHash");
    checkRateLimits($deviceHash);
    
    error_log("Checking for suspicious activity...");
    detectSuspiciousActivity($deviceHash, $message);

    // Connect to database
    error_log("Attempting database connection...");
    $pdo = connectToDatabase();
    error_log("Database connection successful");
    
    // Check usage limits
    error_log("Checking usage limits...");
    checkUsageLimits($pdo, $deviceHash, 'text');

    // Save user message to chat history
    error_log("Saving user message to chat history...");
    saveChatHistory($pdo, $deviceHash, $message, true, 'text', null);

    // Get response from OpenAI
    error_log("Getting response from OpenAI...");
    $response = getTextResponseFromOpenAI($message);
    error_log("OpenAI response received successfully");

    // Save bot response to chat history
    error_log("Saving bot response to chat history...");
    saveChatHistory($pdo, $deviceHash, $response, false, 'text', null);

    // Record usage
    error_log("Recording usage...");
    recordUsage($pdo, $deviceHash, 'text');

    error_log("Request completed successfully");
    echo json_encode([
        'success' => true,
        'response' => $response
    ]);

} catch (Exception $e) {
    error_log("ERROR in process-text.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
// ENHANCED DATABASE CONNECTION with detailed error logging
function connectToDatabase() {
    try {
        $host = getenv('DB_HOST');
        $dbname = getenv('DB_NAME');
        $username = getenv('DB_USER');
        $password = getenv('DB_PASS');
        
        // DEBUG: Log database connection attempt (without password)
        error_log("Attempting DB connection to: $host");
        error_log("Database name: $dbname");
        error_log("Username: $username");
        error_log("Password exists: " . (getenv('DB_PASS') ? 'YES' : 'NO'));
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10 // 10 second timeout
        ]);
        
        error_log("Database connection successful");
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        error_log("PDO Error Code: " . $e->getCode());
        throw new Exception('Nu pot conecta la baza de date momentan. Încercați din nou.');
    }
}

// ENHANCED USAGE LIMITS CHECK with error logging
function checkUsageLimits($pdo, $deviceHash, $type) {
    try {
        $today = date('Y-m-d');
        error_log("Checking usage limits for device: $deviceHash on date: $today");
        
        $stmt = $pdo->prepare("
            SELECT text_count, image_count, premium, extra_questions 
            FROM usage_tracking 
            WHERE device_hash = ? AND date = ?
        ");
        $stmt->execute([$deviceHash, $today]);
        $usage = $stmt->fetch();
        
        if (!$usage) {
            error_log("No usage record found, creating new entry for device: $deviceHash");
            $stmt = $pdo->prepare("
                INSERT INTO usage_tracking (device_hash, date, text_count, image_count, premium, extra_questions) 
                VALUES (?, ?, 0, 0, 0, 0)
            ");
            $stmt->execute([$deviceHash, $today]);
            $usage = ['text_count' => 0, 'image_count' => 0, 'premium' => 0, 'extra_questions' => 0];
            error_log("New usage record created successfully");
        } else {
            error_log("Found existing usage record: " . json_encode($usage));
        }
        
        if ($type === 'text') {
            $limit = $usage['premium'] ? 100 : 10;
            $totalUsed = $usage['text_count'] - $usage['extra_questions'];
            $remaining = $limit - $totalUsed;
            
            error_log("Text usage check - Used: $totalUsed, Limit: $limit, Remaining: $remaining");
            
            if ($totalUsed >= $limit) {
                if ($usage['premium']) {
                    error_log("Premium user hit daily limit");
                    throw new Exception('Ați atins limita zilnică de 100 întrebări premium. Impresionant! Reveniți mâine pentru mai multe.');
                } else {
                    error_log("Free user hit daily limit");
                    throw new Exception('Ați folosit toate cele 10 întrebări gratuite de astăzi! Upgradeați la Premium pentru întrebări nelimitate sau urmăriți o reclamă pentru 3 întrebări extra.');
                }
            }
        }
        
    } catch (PDOException $e) {
        error_log("Database error in checkUsageLimits: " . $e->getMessage());
        throw new Exception('Eroare temporară la verificarea limitelor. Încercați din nou.');
    }
}

// ENHANCED RECORD USAGE with error logging
function recordUsage($pdo, $deviceHash, $type) {
    try {
        $today = date('Y-m-d');
        $field = $type === 'image' ? 'image_count' : 'text_count';
        
        error_log("Recording usage for device: $deviceHash, type: $type");
        
        $stmt = $pdo->prepare("
            UPDATE usage_tracking 
            SET $field = $field + 1 
            WHERE device_hash = ? AND date = ?
        ");
        $result = $stmt->execute([$deviceHash, $today]);
        
        if ($result) {
            error_log("Usage recorded successfully");
        } else {
            error_log("Failed to record usage");
        }
        
    } catch (PDOException $e) {
        error_log("Database error in recordUsage: " . $e->getMessage());
        throw new Exception('Eroare la înregistrarea utilizării.');
    }
}

// ENHANCED SAVE CHAT HISTORY with error logging
function saveChatHistory($pdo, $deviceHash, $messageText, $isUserMessage, $messageType, $imageData) {
    try {
        error_log("Saving chat history for device: $deviceHash, type: $messageType, user: " . ($isUserMessage ? 'YES' : 'NO'));
        
        $stmt = $pdo->prepare("
            INSERT INTO chat_history (device_hash, message_text, is_user_message, message_type, image_data) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([$deviceHash, $messageText, $isUserMessage ? 1 : 0, $messageType, $imageData]);
        
        if ($result) {
            error_log("Chat history saved successfully");
        } else {
            error_log("Failed to save chat history");
        }
        
    } catch (PDOException $e) {
        error_log("Database error in saveChatHistory: " . $e->getMessage());
        // Don't throw exception here - chat history is not critical
        error_log("Continuing despite chat history save failure");
    }
}
// ENHANCED OPENAI FUNCTION with detailed error logging
function getTextResponseFromOpenAI($message) {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        error_log("OpenAI API key not found in environment variables");
        throw new Exception('Serviciul de răspunsuri nu este disponibil momentan');
    }

    error_log("Making OpenAI request for message: " . substr($message, 0, 50) . "...");
    error_log("OpenAI API key exists: YES (length: " . strlen($openaiKey) . ")");

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

    $requestData = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message]
        ],
        'max_tokens' => 900,
        'temperature' => 0.8,
        'top_p' => 0.9
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    error_log("Sending request to OpenAI API...");
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Enhanced error logging
    error_log("OpenAI response code: $httpCode");
    if ($httpCode !== 200) {
        error_log("OpenAI error response: " . substr($response, 0, 500));
        $curlError = curl_error($ch);
        if ($curlError) {
            error_log("cURL error: " . $curlError);
        }
    } else {
        error_log("OpenAI response received successfully (length: " . strlen($response) . ")");
    }
    
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Nu pot răspunde momentan. Încercați din nou în câteva secunde.');
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        error_log("OpenAI API error: " . json_encode($data['error']));
        throw new Exception('Serviciul este temporar indisponibil. Încercați din nou.');
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        error_log("OpenAI response missing content: " . json_encode($data));
        throw new Exception('Nu am putut genera un răspuns. Reformulați întrebarea.');
    }

    $content = $data['choices'][0]['message']['content'];
    error_log("OpenAI response content length: " . strlen($content));
    
    return cleanForTTS($content);
}

// ENHANCED ANTI-BOT FUNCTIONS with better error logging
function checkRateLimits($deviceHash) {
    try {
        $rateLimitFile = '/tmp/rate_limit_' . $deviceHash . '.txt';
        $currentTime = time();
        
        error_log("Checking rate limits for device: $deviceHash");
        
        // Clean old entries
        if (file_exists($rateLimitFile)) {
            $requests = json_decode(file_get_contents($rateLimitFile), true) ?: [];
            $requests = array_filter($requests, function($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < 60; // Keep only last minute
            });
            error_log("Found " . count($requests) . " requests in last minute");
        } else {
            $requests = [];
            error_log("No previous rate limit file found");
        }
        
        // Check if limit exceeded (10 requests per minute)
        if (count($requests) >= 10) {
            error_log("Rate limit exceeded for device: $deviceHash");
            throw new Exception('Prea multe cereri. Încercați din nou în 1 minut.');
        }
        
        // Add current request
        $requests[] = $currentTime;
        file_put_contents($rateLimitFile, json_encode($requests));
        error_log("Rate limit check passed, requests this minute: " . count($requests));
        
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Prea multe cereri') !== false) {
            throw $e; // Re-throw rate limit exceptions
        }
        error_log("Error in rate limit check: " . $e->getMessage());
        // Continue execution if file operations fail
    }
}

function detectSuspiciousActivity($deviceHash, $message) {
    try {
        $messageHash = md5($message);
        $suspiciousFile = '/tmp/suspicious_' . $deviceHash . '_' . $messageHash . '.txt';
        $currentTime = time();
        
        error_log("Checking suspicious activity for device: $deviceHash");
        
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
                error_log("Suspicious activity detected - repeated message from device: $deviceHash");
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
                error_log("Rapid-fire requests detected from device: $deviceHash");
                throw new Exception('Cereri prea rapide. Așteptați câteva secunde.');
            }
            
            $rapidRequests[] = $currentTime;
            file_put_contents($rapidFile, json_encode($rapidRequests));
        } else {
            file_put_contents($rapidFile, json_encode([$currentTime]));
        }
        
        error_log("Suspicious activity check passed for device: $deviceHash");
        
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Mesaj repetat') !== false || 
            strpos($e->getMessage(), 'Cereri prea rapide') !== false) {
            throw $e; // Re-throw suspicious activity exceptions
        }
        error_log("Error in suspicious activity check: " . $e->getMessage());
        // Continue execution if file operations fail
    }
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

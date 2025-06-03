<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// ENHANCED DEBUG: Log all environment variables and request details
error_log("=== IMAGE PROCESSING DEBUG START ===");
error_log("API_SECRET_KEY exists: " . (getenv('API_SECRET_KEY') ? 'YES' : 'NO'));
error_log("GOOGLE_VISION_KEY exists: " . (getenv('GOOGLE_VISION_KEY') ? 'YES' : 'NO'));
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
error_log("Raw input preview: " . substr($rawInput, 0, 100) . "...");
error_log("=== IMAGE PROCESSING DEBUG END ===");

// Verify API Key with enhanced logging
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');

error_log("API Key comparison - Received: " . substr($apiKey, 0, 10) . "... Expected: " . substr($expectedKey, 0, 10) . "...");

if ($apiKey !== $expectedKey) {
    error_log("API key mismatch - Authentication failed for image request");
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

error_log("API key verified successfully for image request");

// Allow large files (50MB) with logging
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('max_execution_time', '300');
ini_set('memory_limit', '512M');

error_log("PHP settings configured for large image processing");

try {
    error_log("Starting image processing request...");
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate input with enhanced logging
    if (!$data || !isset($data['image']) || !isset($data['device_hash'])) {
        error_log("Invalid image request data - missing required fields");
        error_log("Data keys present: " . (is_array($data) ? implode(', ', array_keys($data)) : 'not an array'));
        throw new Exception('Date lipsă: Imaginea sau hash-ul dispozitivului nu au fost primite');
    }

    $imageBase64 = $data['image'];
    $deviceHash = $data['device_hash'];

    error_log("Processing image from device: $deviceHash");
    error_log("Image base64 length: " . strlen($imageBase64));
    error_log("Estimated image size: " . round((strlen($imageBase64) * 0.75) / 1024, 2) . " KB");

    // ANTI-BOT PROTECTION - Check rate limits first
    error_log("Checking rate limits for image request from device: $deviceHash");
    checkRateLimits($deviceHash);
    
    error_log("Checking for suspicious image activity...");
    detectSuspiciousImageActivity($deviceHash, $imageBase64);

    // Connect to database
    error_log("Attempting database connection for image processing...");
    $pdo = connectToDatabase();
    error_log("Database connection successful for image processing");
    
    // Check image usage limits with enhanced messaging
    error_log("Checking image usage limits...");
    checkUsageLimits($pdo, $deviceHash, 'image');

    // Step 1: Analyze with Google Vision
    error_log("Starting Google Vision analysis...");
    $visionResults = analyzeWithGoogleVision($imageBase64);
    error_log("Google Vision analysis completed");
    error_log("Vision results - Objects: " . count($visionResults['objects']) . ", Labels: " . count($visionResults['labels']));

    // Validate Vision API results
    if (empty($visionResults['objects']) && empty($visionResults['labels'])) {
        error_log("Google Vision found no objects or labels in image");
        throw new Exception('Nu am putut identifica plante în această imagine. Încercați o poză mai clară cu planta în prim-plan.');
    }

    // Step 2: Get enhanced treatment from OpenAI
    error_log("Getting treatment recommendations from OpenAI...");
    $treatment = getTreatmentFromOpenAI($visionResults);
    error_log("OpenAI treatment response received successfully");

    // Save to chat history
    error_log("Saving image and treatment to chat history...");
    saveChatHistory($pdo, $deviceHash, "", true, 'image', $imageBase64);
    saveChatHistory($pdo, $deviceHash, $treatment, false, 'text', null);

    // Record usage
    error_log("Recording image usage...");
    recordUsage($pdo, $deviceHash, 'image');

    error_log("Image processing completed successfully");
    echo json_encode([
        'success' => true,
        'treatment' => $treatment,
        'vision_results' => $visionResults
    ]);

} catch (Exception $e) {
    error_log("ERROR in process-image.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
// ENHANCED GOOGLE VISION FUNCTION with detailed error logging
function analyzeWithGoogleVision($imageBase64) {
    $googleVisionKey = getenv('GOOGLE_VISION_KEY');
    if (!$googleVisionKey) {
        error_log("Google Vision API key not found in environment variables");
        throw new Exception('Serviciul de analiză imagini nu este disponibil momentan');
    }

    error_log("Starting Google Vision analysis...");
    error_log("Google Vision API key exists: YES (length: " . strlen($googleVisionKey) . ")");
    error_log("Image base64 length for Vision API: " . strlen($imageBase64));

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

    error_log("Sending request to Google Vision API...");

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Enhanced error logging
    error_log("Google Vision response code: $httpCode");
    if ($httpCode !== 200) {
        error_log("Google Vision error response: " . substr($response, 0, 500));
        $curlError = curl_error($ch);
        if ($curlError) {
            error_log("cURL error for Google Vision: " . $curlError);
        }
    } else {
        error_log("Google Vision response received successfully (length: " . strlen($response) . ")");
    }
    
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Serviciul de analiză imagini este temporar indisponibil. Încercați din nou.');
    }

    $result = json_decode($response, true);

    if (isset($result['error'])) {
        error_log("Google Vision API error: " . json_encode($result['error']));
        throw new Exception('Eroare la analiza imaginii: ' . $result['error']['message']);
    }

    $objects = [];
    $labels = [];

    if (isset($result['responses'][0]['localizedObjectAnnotations'])) {
        foreach ($result['responses'][0]['localizedObjectAnnotations'] as $obj) {
            $objects[] = $obj['name'];
        }
        error_log("Google Vision found " . count($objects) . " objects: " . implode(', ', $objects));
    }

    if (isset($result['responses'][0]['labelAnnotations'])) {
        foreach ($result['responses'][0]['labelAnnotations'] as $label) {
            $labels[] = $label['description'];
        }
        error_log("Google Vision found " . count($labels) . " labels: " . implode(', ', $labels));
    }

    error_log("Google Vision analysis completed successfully");

    return [
        'objects' => $objects,
        'labels' => $labels
    ];
}

// ENHANCED OPENAI TREATMENT FUNCTION with detailed error logging
function getTreatmentFromOpenAI($visionResults) {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        error_log("OpenAI API key not found in environment variables for image analysis");
        throw new Exception('Serviciul de analiză nu este disponibil momentan');
    }

    $objects = implode(', ', $visionResults['objects']);
    $labels = implode(', ', $visionResults['labels']);

    error_log("Creating OpenAI prompt for image analysis...");
    error_log("Objects for analysis: $objects");
    error_log("Labels for analysis: $labels");

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

    error_log("Sending request to OpenAI for image treatment analysis...");

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Enhanced error logging
    error_log("OpenAI image analysis response code: $httpCode");
    if ($httpCode !== 200) {
        error_log("OpenAI image analysis error response: " . substr($response, 0, 500));
        $curlError = curl_error($ch);
        if ($curlError) {
            error_log("cURL error for OpenAI image analysis: " . $curlError);
        }
    } else {
        error_log("OpenAI image analysis response received successfully (length: " . strlen($response) . ")");
    }
    
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Nu am putut analiza imaginea momentan. Încercați din nou.');
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        error_log("OpenAI image analysis API error: " . json_encode($data['error']));
        throw new Exception('Nu am putut analiza imaginea momentan. Încercați din nou.');
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        error_log("OpenAI image analysis response missing content: " . json_encode($data));
        throw new Exception('Analiza imaginii a eșuat. Încercați cu o altă poză.');
    }

    $content = $data['choices'][0]['message']['content'];
    error_log("OpenAI image analysis content length: " . strlen($content));
    
    return cleanForTTS($content);
}

// ENHANCED DATABASE FUNCTIONS (same as text version but with image-specific logging)
function connectToDatabase() {
    try {
        $host = getenv('DB_HOST');
        $dbname = getenv('DB_NAME');
        $username = getenv('DB_USER');
        $password = getenv('DB_PASS');
        
        // DEBUG: Log database connection attempt (without password)
        error_log("Image processing - Attempting DB connection to: $host");
        error_log("Image processing - Database name: $dbname");
        error_log("Image processing - Username: $username");
        error_log("Image processing - Password exists: " . (getenv('DB_PASS') ? 'YES' : 'NO'));
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10 // 10 second timeout
        ]);
        
        error_log("Image processing - Database connection successful");
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Image processing - Database connection failed: " . $e->getMessage());
        error_log("Image processing - PDO Error Code: " . $e->getCode());
        throw new Exception('Nu pot conecta la baza de date momentan. Încercați din nou.');
    }
}

function checkUsageLimits($pdo, $deviceHash, $type) {
    try {
        $today = date('Y-m-d');
        error_log("Image processing - Checking usage limits for device: $deviceHash on date: $today");
        
        $stmt = $pdo->prepare("
            SELECT text_count, image_count, premium, extra_questions 
            FROM usage_tracking 
            WHERE device_hash = ? AND date = ?
        ");
        $stmt->execute([$deviceHash, $today]);
        $usage = $stmt->fetch();

        if (!$usage) {
            error_log("Image processing - No usage record found, creating new entry for device: $deviceHash");
            $stmt = $pdo->prepare("
                INSERT INTO usage_tracking (device_hash, date, text_count, image_count, premium, extra_questions) 
                VALUES (?, ?, 0, 0, 0, 0)
            ");
            $stmt->execute([$deviceHash, $today]);
            $usage = ['text_count' => 0, 'image_count' => 0, 'premium' => 0, 'extra_questions' => 0];
            error_log("Image processing - New usage record created successfully");
        } else {
            error_log("Image processing - Found existing usage record: " . json_encode($usage));
        }

        if ($type === 'image') {
            $limit = $usage['premium'] ? 5 : 1;
            $remaining = $limit - $usage['image_count'];
            
            error_log("Image usage check - Used: {$usage['image_count']}, Limit: $limit, Remaining: $remaining");
            
            if ($usage['image_count'] >= $limit) {
                if ($usage['premium']) {
                    error_log("Premium user hit daily image limit");
                    throw new Exception('Ați atins limita zilnică de 5 imagini premium. Reveniți mâine pentru analize noi!');
                } else {
                    error_log("Free user hit daily image limit");
                    throw new Exception('Ați folosit analiza gratuită de astăzi! Upgradeați la Premium pentru 5 analize zilnice sau urmăriți o reclamă pentru o analiză extra.');
                }
            }
        }
        
    } catch (PDOException $e) {
        error_log("Image processing - Database error in checkUsageLimits: " . $e->getMessage());
        throw new Exception('Eroare temporară la verificarea limitelor. Încercați din nou.');
    }
}

function recordUsage($pdo, $deviceHash, $type) {
    try {
        $today = date('Y-m-d');
        $field = $type === 'image' ? 'image_count' : 'text_count';
        
        error_log("Image processing - Recording usage for device: $deviceHash, type: $type");
        
        $stmt = $pdo->prepare("
            UPDATE usage_tracking 
            SET $field = $field + 1 
            WHERE device_hash = ? AND date = ?
        ");
        $result = $stmt->execute([$deviceHash, $today]);
        
        if ($result) {
            error_log("Image processing - Usage recorded successfully");
        } else {
            error_log("Image processing - Failed to record usage");
        }
        
    } catch (PDOException $e) {
        error_log("Image processing - Database error in recordUsage: " . $e->getMessage());
        throw new Exception('Eroare la înregistrarea utilizării.');
    }
}

function saveChatHistory($pdo, $deviceHash, $messageText, $isUserMessage, $messageType, $imageData) {
    try {
        error_log("Image processing - Saving chat history for device: $deviceHash, type: $messageType, user: " . ($isUserMessage ? 'YES' : 'NO'));
        
        $stmt = $pdo->prepare("
            INSERT INTO chat_history (device_hash, message_text, is_user_message, message_type, image_data) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $deviceHash,
            $messageText,
            $isUserMessage ? 1 : 0,
            $messageType,
            $imageData
        ]);
        
        if ($result) {
            error_log("Image processing - Chat history saved successfully");
        } else {
            error_log("Image processing - Failed to save chat history");
        }
        
    } catch (PDOException $e) {
        error_log("Image processing - Database error in saveChatHistory: " . $e->getMessage());
        // Don't throw exception here - chat history is not critical
        error_log("Image processing - Continuing despite chat history save failure");
    }
}

function cleanForTTS($text) {
    // Handle null input
    if ($text === null || $text === '') {
        return '';
    }
    
    // Convert to string if not already
    $text = (string) $text;
    
    // Clean text for TTS - FIXED regex patterns
    $text = preg_replace('/\*+/', '', $text);                    // Remove asterisks
    $text = preg_replace('/^\d+\.\s*/m', '', $text);            // Remove numbered lists
    $text = preg_replace('/^[\-\*\+]\s*/m', '', $text);         // Remove bullet points
    $text = preg_replace('/\s+/', ' ', $text);                  // Multiple spaces to single
    $text = preg_replace('/[#@$%^&(){}|\\\\]/', '', $text);     // FIXED: Escaped brackets properly
    $text = preg_replace('/\s*([,.!?;:])\s*/', '$1 ', $text);   // Fix punctuation spacing
    $text = preg_replace('/\s*\(\d+%\)\s*/', ' ', $text);       // Remove percentages
    
    return trim($text);
}

?>

<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enhanced Logging
error_log("=== IMAGE PROCESSING START ===");
error_log("API_SECRET_KEY exists: " . (getenv('API_SECRET_KEY') ? 'YES' : 'NO'));

// API Key Check
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');

if ($apiKey !== $expectedKey) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

// Performance Configuration
ini_set('max_execution_time', '60');
ini_set('memory_limit', '512M');  // Increased for image processing

try {
    // Database Connection
    $pdo = new PDO(
        "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4",
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['image']) || !isset($data['device_hash'])) {
        throw new Exception('Date lipsă: Imaginea sau hash-ul dispozitivului nu au fost primite');
    }

    $imageBase64 = $data['image'];
    $deviceHash = $data['device_hash'];

    // --- Rate Limiting ---
    $maxDailyImages = 5;    // Max 5 images/day/device
    $maxMinuteImages = 2;   // Max 2 images/minute/device
    $today = date('Y-m-d');

    // Check existing usage
    $stmt = $pdo->prepare("SELECT * FROM usage_tracking WHERE device_hash = ? AND date = ?");
    $stmt->execute([$deviceHash, $today]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC);

    $now = date('Y-m-d H:i:s');

    if ($usage) {
        // Daily limit
        if ($usage['image_count'] >= $maxDailyImages) {
            throw new Exception('Ați depășit limita zilnică de analize foto');
        }
        
        // Burst limit
        $lastImageTime = strtotime($usage['last_image_request']);
        if (time() - $lastImageTime < 60) {
            if ($usage['image_minute_counter'] >= $maxMinuteImages) {
                throw new Exception('Prea multe analize foto. Încercați din nou peste 1 minut.');
            }
            $newMinuteCounter = $usage['image_minute_counter'] + 1;
        } else {
            $newMinuteCounter = 1;
        }
    }

    // --- Image Validation ---
    validateImage($imageBase64);

    // --- Get AI Treatment ---
    $treatment = getAITreatment($imageBase64);

    // --- Update Usage Tracking ---
    if ($usage) {
        $stmt = $pdo->prepare("
            UPDATE usage_tracking 
            SET image_count = image_count + 1, 
                last_image_request = ?,
                image_minute_counter = ?
            WHERE id = ?
        ");
        $stmt->execute([$now, $newMinuteCounter, $usage['id']]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO usage_tracking 
            (device_hash, date, image_count, last_image_request, image_minute_counter) 
            VALUES (?, ?, 1, ?, 1)
        ");
        $stmt->execute([$deviceHash, $today, $now]);
    }

    // Response
    echo json_encode([
        'success' => true,
        'treatment' => $treatment
    ]);

} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// --------------------------------------------------
// ENHANCED VALIDATION FUNCTIONS
// --------------------------------------------------

function validateImage(&$imageBase64) {
    // Remove data URL prefix
    if (strpos($imageBase64, 'data:image') === 0) {
        $imageBase64 = substr($imageBase64, strpos($imageBase64, ',') + 1);
    }

    // Base64 validation
    $decodedImage = base64_decode($imageBase64, true);
    if ($decodedImage === false) {
        throw new Exception('Imagine coruptă');
    }

    // File size check (4MB max)
    $fileSize = (int)(strlen($imageBase64) * 3 / 4); // Approximate real size
    if ($fileSize > 4 * 1024 * 1024) {
        throw new Exception('Imagine prea mare (maxim 4MB)');
    }

    // Image dimensions check
    $imageInfo = @getimagesizefromstring($decodedImage);
    if ($imageInfo === false) {
        throw new Exception('Format invalid. Acceptăm JPEG sau PNG.');
    }
    
    // Max 4096x4096 pixels
    list($width, $height) = $imageInfo;
    if ($width > 4096 || $height > 4096) {
        throw new Exception('Dimensiuni prea mari (maxim 4096x4096 pixeli)');
    }

    // MIME type check
    $allowedTypes = ['image/jpeg', 'image/png'];
    if (!in_array($imageInfo['mime'], $allowedTypes)) {
        throw new Exception('Format neacceptat. Folosiți JPEG sau PNG.');
    }
}

// -----------------------------------------------
// SIMPLE FUNCTIONS (Google Vision + OpenAI)
// -----------------------------------------------

function getAITreatment($imageBase64) {
    $googleVisionKey = getenv('GOOGLE_VISION_KEY');
    $openaiKey = getenv('OPENAI_API_KEY');

    if (!$googleVisionKey || !$openaiKey) {
        throw new Exception('Serviciile AI nu sunt configurate corect');
    }

    // Call Google Vision
    $visionResults = analyzeImageWithVisionAPI($imageBase64, $googleVisionKey);

    // Prepare prompt for OpenAI
    $prompt = buildPromptFromVisionResults($visionResults);

    // Call OpenAI
    $aiResponse = getOpenAIResponse($prompt, $openaiKey);

    return cleanForTTS($aiResponse);
}

function analyzeImageWithVisionAPI($imageBase64, $googleVisionKey) {
    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . $googleVisionKey;

    $requestData = [
        'requests' => [[
            'image' => ['content' => $imageBase64],
            'features' => [['type' => 'LABEL_DETECTION', 'maxResults' => 5]]
        ]]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $errorInfo = json_decode($response, true)['error']['message'] ?? 'Unknown error';
        throw new Exception('Eroare Google Vision: ' . $errorInfo);
    }

    $result = json_decode($response, true);
    return $result;
}

function buildPromptFromVisionResults($visionResults) {
    $labels = [];
    if (isset($visionResults['responses'][0]['labelAnnotations'])) {
        foreach ($visionResults['responses'][0]['labelAnnotations'] as $label) {
            $labels[] = $label['description'];
        }
    }

    if (empty($labels)) {
        return "Analizează această imagine de plantă. Oferă un tratament concis, în română, pentru o plantă de grădină, în maxim 200 de cuvinte.";
    }

    $prompt = "Analizează această imagine cu următoarele etichete: " . implode(', ', $labels) . ". ";
    $prompt .= "Oferă un tratament concis, în română, pentru o plantă de grădină, în maxim 200 de cuvinte.";

    return $prompt;
}

function getOpenAIResponse($prompt, $openaiKey) {
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => "Ești un expert în grădinărit."],
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 400,
        'temperature' => 0.7
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $errorInfo = json_decode($response, true)['error']['message'] ?? 'Unknown error';
        throw new Exception('Eroare OpenAI: ' . $errorInfo);
    }

    $data = json_decode($response, true);
    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception('Răspuns invalid de la AI');
    }

    return $data['choices'][0]['message']['content'];
}

function cleanForTTS($text) {
    $text = preg_replace('/\*+/', '', $text);
    $text = preg_replace('/^\d+\.\s*/m', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

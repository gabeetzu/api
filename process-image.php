<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enhanced Logging
error_log("=== IMAGE PROCESSING START ===");
error_log("API_SECRET_KEY exists: " . (getenv('API_SECRET_KEY') ? 'YES' : 'NO'));
error_log("GOOGLE_VISION_KEY exists: " . (getenv('GOOGLE_VISION_KEY') ? 'YES' : 'NO'));

// API Key Check
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');

if ($apiKey !== $expectedKey) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

// Performance Configuration
ini_set('max_execution_time', '60');
ini_set('memory_limit', '512M');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['image'])) {
        throw new Exception('Date lipsă: Imaginea nu a fost primită');
    }

    $imageBase64 = $data['image'];
    $userMessage = isset($data['message']) ? $data['message'] : '';

    // Validate Image
    validateImage($imageBase64);

    // Get AI Treatment
    $treatment = getAITreatment($imageBase64, $userMessage);

    // Response
    echo json_encode([
        'success' => true,
        'response' => $treatment
    ]);

} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// ==============================================
// ENHANCED VALIDATION FUNCTIONS
// ==============================================

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
    $fileSize = (int)(strlen($imageBase64) * 3 / 4);
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

    // Reject very small images
    if ($width < 200 || $height < 200) {
        throw new Exception('Imaginea este prea mică (minim 200x200 pixeli)');
    }

    // Aspect ratio check
    $aspectRatio = $width / $height;
    if ($aspectRatio > 3 || $aspectRatio < 0.33) {
        throw new Exception('Proporții nepotrivite pentru plante');
    }

    // MIME type check
    $allowedTypes = ['image/jpeg', 'image/png'];
    if (!in_array($imageInfo['mime'], $allowedTypes)) {
        throw new Exception('Format neacceptat. Folosiți JPEG sau PNG.');
    }
}

// ==============================================
// AI PROCESSING WITH IMPROVED PLANT DETECTION
// ==============================================

function getAITreatment($imageBase64, $userMessage = '') {
    $googleVisionKey = getenv('GOOGLE_VISION_KEY');
    $openaiKey = getenv('OPENAI_API_KEY');

    if (!$googleVisionKey || !$openaiKey) {
        throw new Exception('Serviciile AI nu sunt configurate corect');
    }

    // Call Google Vision
    $visionResults = analyzeImageWithVisionAPI($imageBase64, $googleVisionKey);

    // Prepare prompt for OpenAI
    $prompt = buildPromptFromVisionResults($visionResults, $userMessage);

    // Call OpenAI
    $aiResponse = getOpenAIResponse($prompt, $openaiKey);

    return cleanForTTS($aiResponse);
}

function analyzeImageWithVisionAPI($imageBase64, $googleVisionKey) {
    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . $googleVisionKey;

    $requestData = [
        'requests' => [[
            'image' => ['content' => $imageBase64],
            'features' => [['type' => 'LABEL_DETECTION', 'maxResults' => 10]]
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
        $errorInfo = json_decode($response, true)['error']['message'] ?? 'Eroare necunoscută';
        throw new Exception('Eroare Google Vision: ' . $errorInfo);
    }

    return json_decode($response, true);
}

function buildPromptFromVisionResults($visionResults, $userMessage = '') {
    $labels = [];
    if (isset($visionResults['responses'][0]['labelAnnotations'])) {
        foreach ($visionResults['responses'][0]['labelAnnotations'] as $label) {
            $labels[] = strtolower($label['description']);
        }
    }

    // Plant and disease-related keywords
    $plantKeywords = [
        'plant', 'leaf', 'foliage', 'stem', 'root', 'flower', 'tree', 
        'shrub', 'vegetation', 'garden', 'petal', 'chlorosis', 'fungus',
        'spot', 'wilt', 'blight', 'mold', 'mildew', 'rot', 'insect', 'pest'
    ];
    $nonPlantKeywords = [
        'animal', 'cat', 'dog', 'person', 'human', 'car', 'building', 'screen', 
        'television', 'computer', 'minecraft', 'game', 'indoor', 'furniture', 'electronic', 'device', 'logo', 'text', 'paper'
    ];

    // Plant detection logic
    $hasPlant = false;
    foreach ($labels as $label) {
        foreach ($plantKeywords as $keyword) {
            if (strpos($label, $keyword) !== false) {
                $hasPlant = true;
                break 2;
            }
        }
    }

    $hasDominantNonPlant = false;
    foreach ($labels as $label) {
        foreach ($nonPlantKeywords as $keyword) {
            if (strpos($label, $keyword) !== false) {
                $hasDominantNonPlant = true;
                break 2;
            }
        }
    }

    // Only reject if there are NO plant labels and there ARE non-plant labels
    if (!$hasPlant && $hasDominantNonPlant) {
        throw new Exception('Imaginea conține elemente care nu sunt plante. Te rog să focalizezi pe planta cu problema.');
    }
    if (!$hasPlant) {
        throw new Exception('Imaginea nu pare să conțină o plantă. Te rog să fotografiezi clar o frunză sau o parte a unei plante.');
    }

    // Compose prompt for OpenAI
    $prompt = "Imaginea conține: " . implode(', ', $labels) . ". ";
    if (!empty($userMessage)) {
        $prompt .= "Utilizatorul a întrebat: \"$userMessage\". ";
    }
    $prompt .= "Dacă observi pete, decolorări, găuri sau alte simptome pe frunze, rădăcini sau tulpină, descrie posibilele cauze și oferă un tratament pentru grădinărit. Dacă nu vezi probleme, explică ce simptome ar trebui urmărite și cum arată o plantă sănătoasă.";

    return $prompt;
}

function getOpenAIResponse($prompt, $openaiKey) {
    $systemPrompt = "Ești un expert în boli ale plantelor. Analizează etichetele generate pentru imagine și: 
    1. Identifică posibile boli sau dăunători bazându-te pe elemente precum pete, decolorări, găuri, mucegai, insecte, etc.
    2. Dacă nu vezi probleme evidente, sugerează ce simptome ar trebui căutate și cum arată o plantă sănătoasă.
    3. Răspunde în română, clar și concis (150-250 cuvinte).";

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
        'max_tokens' => 400,
        'temperature' => 0.7
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Eroare la procesarea imaginii');
    }

    $data = json_decode($response, true);
    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception('Răspuns invalid de la AI');
    }

    $responseText = $data['choices'][0]['message']['content'];
    
    // Final safety check
    if (stripos($responseText, 'nu pot oferi sfaturi') !== false) {
        throw new Exception('Nu pot identifica probleme la plante în această poză.');
    }

    return $responseText;
}

function cleanForTTS($text) {
    $text = preg_replace('/\*+/', '', $text);
    $text = preg_replace('/^\d+\.\s*/m', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

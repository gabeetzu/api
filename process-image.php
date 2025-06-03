<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enhanced Logging (Essential Info Only)
error_log("=== IMAGE PROCESSING START ===");
error_log("API_SECRET_KEY exists: " . (getenv('API_SECRET_KEY') ? 'YES' : 'NO'));
error_log("OPENAI_API_KEY exists: " . (getenv('OPENAI_API_KEY') ? 'YES' : 'NO'));
error_log("GOOGLE_VISION_KEY exists: " . (getenv('GOOGLE_VISION_KEY') ? 'YES' : 'NO'));

// API Key Check
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');

if ($apiKey !== $expectedKey) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

// Performance Configuration
ini_set('max_execution_time', '60');     //Less work, 60s is more than enough
ini_set('memory_limit', '256M');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['image'])) {
        throw new Exception('Date lipsă: Imaginea nu a fost primită');
    }

    $imageBase64 = $data['image'];

    // Validate Image
    validateImage($imageBase64);

    // Get AI Treatment (Simplified)
    $treatment = getAITreatment($imageBase64);

    // Simple Response
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

// -----------------------------------------------
// SIMPLE FUNCTIONS (Google Vision + OpenAI)
// -----------------------------------------------

function validateImage($imageBase64) {
    // Remove data URL prefix if present
    if (strpos($imageBase64, 'data:image') === 0) {
        $imageBase64 = substr($imageBase64, strpos($imageBase64, ',') + 1);
    }

    $decodedImage = base64_decode($imageBase64, true);
    if ($decodedImage === false) {
        throw new Exception('Imagine coruptă');
    }

    // Basic format check
    $imageInfo = @getimagesizefromstring($decodedImage);
    if ($imageInfo === false) {
        throw new Exception('Format invalid. Folosiți JPEG sau PNG.');
    }
}

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
        throw new Exception('Eroare Google Vision: ' . $httpCode);
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
            ['role' => 'system', 'content' => "Ești un expert în grădinărit."},
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
        throw new Exception('Eroare OpenAI: ' . $httpCode);
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

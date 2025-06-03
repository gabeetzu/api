<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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

    // Step 1: Analyze with Google Vision
    $visionResults = analyzeWithGoogleVision($imageBase64);

    // Validate Vision API results
    if (empty($visionResults['objects']) && empty($visionResults['labels'])) {
        throw new Exception('Nu s-au detectat obiecte sau etichete în imagine');
    }

    // Step 2: Get treatment from OpenAI
    $treatment = getTreatmentFromOpenAI($visionResults);

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

function analyzeWithGoogleVision($imageBase64) {
    $googleVisionKey = getenv('GOOGLE_VISION_KEY');
    if (!$googleVisionKey) {
        throw new Exception('Cheia Google Vision nu este configurată corect');
    }

    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . $googleVisionKey;

    $requestData = [
        'requests' => [[
            'image' => ['content' => $imageBase64],
            'features' => [
                ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 10],
                ['type' => 'LABEL_DETECTION', 'maxResults' => 10]
            ]
        ]]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));

    $response = curl_exec($ch);
    $result = json_decode($response, true);

    // Handle Vision API errors
    if (isset($result['error'])) {
        throw new Exception('Eroare Google Vision: ' . $result['error']['message']);
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
        throw new Exception('Cheia OpenAI nu este configurată corect');
    }

    $objects = implode(', ', $visionResults['objects']);
    $labels = implode(', ', $visionResults['labels']);

    $prompt = "Analiza foto: Obiecte detectate: $objects. Etichete: $labels. Oferă sfaturi de grădinărit în română.";

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'max_tokens' => 500,
        'temperature' => 0.7
    ]));

    $response = curl_exec($ch);
    $data = json_decode($response, true);

    // Handle OpenAI API errors
    if (isset($data['error'])) {
        throw new Exception('Eroare OpenAI: ' . $data['error']['message']);
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception('Răspuns neașteptat de la OpenAI');
    }

    return $data['choices'][0]['message']['content'];
}

?>

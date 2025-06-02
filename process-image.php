<?php
require __DIR__ . '/config.php';

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
    
    if (!$data || !isset($data['image']) || !isset($data['device_hash'])) {
        throw new Exception('Date lipsă');
    }
    
    $imageBase64 = $data['image'];
    
    // Step 1: Analyze with Google Vision
    $visionResults = analyzeWithGoogleVision($imageBase64);
    
    // Step 2: Get treatment from OpenAI
    $treatment = getTreatmentFromOpenAI($visionResults);
    
    echo json_encode([
        'success' => true,
        'treatment' => $treatment,
        'vision_results' => $visionResults
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function analyzeWithGoogleVision($imageBase64) {
    $url = 'https://vision.googleapis.com/v1/images:annotate?key=AIzaSyA5yZrcvC7ajAVutCwz0gOvmAtYzm9QBMs';
    
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
    $objects = implode(', ', $visionResults['objects']);
    $labels = implode(', ', $visionResults['labels']);
    
    $prompt = "Analiza foto: Obiecte detectate: $objects. Etichete: $labels. Oferă sfaturi de grădinărit în română.";
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer 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'
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'max_tokens' => 500
    ]));
    
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    
    return $data['choices'][0]['message']['content'];
}
?>

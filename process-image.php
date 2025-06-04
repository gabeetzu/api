<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// --- Logging and API Key Check ---
error_log("=== IMAGE PROCESSING START ===");
error_log("API_SECRET_KEY exists: " . (getenv('API_SECRET_KEY') ? 'YES' : 'NO'));
error_log("GOOGLE_VISION_KEY exists: " . (getenv('GOOGLE_VISION_KEY') ? 'YES' : 'NO'));

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');
if ($apiKey !== $expectedKey) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

ini_set('max_execution_time', '60');
ini_set('memory_limit', '512M');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['image'])) {
        throw new Exception('Date lipsă: Imaginea nu a fost primită');
    }

    $imageBase64 = $data['image'];
    $userMessage = $data['message'] ?? '';

    validateImage($imageBase64);

    $treatment = getAITreatment($imageBase64, $userMessage);

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

// --- Image Validation ---
function validateImage(&$imageBase64) {
    if (strpos($imageBase64, 'data:image') === 0) {
        $imageBase64 = substr($imageBase64, strpos($imageBase64, ',') + 1);
    }
    $decodedImage = base64_decode($imageBase64, true);
    if ($decodedImage === false) {
        throw new Exception('Imagine coruptă');
    }
    $fileSize = (int)(strlen($imageBase64) * 3 / 4);
    if ($fileSize > 4 * 1024 * 1024) {
        throw new Exception('Imagine prea mare (maxim 4MB)');
    }
    $imageInfo = @getimagesizefromstring($decodedImage);
    if ($imageInfo === false) {
        throw new Exception('Format invalid. Acceptăm JPEG sau PNG.');
    }
    list($width, $height) = $imageInfo;
    if ($width > 4096 || $height > 4096) {
        throw new Exception('Dimensiuni prea mari (maxim 4096x4096 pixeli)');
    }
    if ($width < 200 || $height < 200) {
        throw new Exception('Imaginea este prea mică (minim 200x200 pixeli)');
    }
    $aspectRatio = $width / $height;
    if ($aspectRatio > 3 || $aspectRatio < 0.33) {
        throw new Exception('Proporții nepotrivite pentru plante');
    }
    $allowedTypes = ['image/jpeg', 'image/png'];
    if (!in_array($imageInfo['mime'], $allowedTypes)) {
        throw new Exception('Format neacceptat. Folosiți JPEG sau PNG.');
    }
}

// --- AI Processing ---
function getAITreatment($imageBase64, $userMessage) {
    $googleVisionKey = getenv('GOOGLE_VISION_KEY');
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$googleVisionKey || !$openaiKey) {
        throw new Exception('Serviciile AI nu sunt configurate corect');
    }

    $visionResults = analyzeImageWithVisionAPI($imageBase64, $googleVisionKey);
    $diseaseFeatures = extractDiseaseFeatures($visionResults);

    // If no strong disease features, still allow plant analysis (don't block, just inform)
    $prompt = buildClinicalPrompt($diseaseFeatures, $userMessage);

    return getOpenAIDiagnosis($prompt, $openaiKey);
}

function analyzeImageWithVisionAPI($imageBase64, $googleVisionKey) {
    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . $googleVisionKey;
    $requestData = [
        'requests' => [[
            'image' => ['content' => $imageBase64],
            'features' => [
                ['type' => 'LABEL_DETECTION', 'maxResults' => 20],
                ['type' => 'WEB_DETECTION', 'maxResults' => 10],
                ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 5]
            ]
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

function extractDiseaseFeatures($visionResults) {
    $features = [];
    $diseaseKeywords = [
        'spot', 'wilt', 'blight', 'mold', 'rot', 'lesion', 'fungus', 
        'discoloration', 'chlorosis', 'necrosis', 'powdery mildew', 'rust', 'hole', 'yellow', 'brown', 'black', 'pest', 'insect'
    ];

    // Label analysis
    if (isset($visionResults['responses'][0]['labelAnnotations'])) {
        foreach ($visionResults['responses'][0]['labelAnnotations'] as $label) {
            $text = strtolower($label['description']);
            foreach ($diseaseKeywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $features[] = $text;
                    break;
                }
            }
        }
    }
    // Web detection
    if (isset($visionResults['responses'][0]['webDetection']['webEntities'])) {
        foreach ($visionResults['responses'][0]['webDetection']['webEntities'] as $entity) {
            if (!empty($entity['description']) && $entity['score'] > 0.7) {
                $features[] = strtolower($entity['description']);
            }
        }
    }
    // Object localization
    if (isset($visionResults['responses'][0]['localizedObjectAnnotations'])) {
        foreach ($visionResults['responses'][0]['localizedObjectAnnotations'] as $obj) {
            if (!empty($obj['name']) && $obj['score'] > 0.8) {
                $features[] = strtolower($obj['name']) . ' (localized)';
            }
        }
    }
    return array_unique($features);
}

function buildClinicalPrompt($features, $userMessage) {
    $prompt = "Analizează această imagine de plantă.\n";
    if (!empty($features)) {
        $prompt .= "Simptome detectate automat: " . implode(', ', $features) . ".\n";
    } else {
        $prompt .= "Nu s-au detectat automat simptome specifice, dar analizează cu atenție orice semn de boală sau dăunători.\n";
    }
    if (!empty($userMessage)) {
        $prompt .= "Întrebare de la utilizator: \"$userMessage\".\n";
    }
    $prompt .= "1. Descrie pe scurt simptomele vizibile (pete, găuri, decolorări, mucegai, insecte, etc).\n";
    $prompt .= "2. Indică posibile cauze sau boli.\n";
    $prompt .= "3. Oferă un tratament concret, cu pași clari.\n";
    $prompt .= "4. Dacă nu vezi probleme, explică ce simptome ar trebui urmărite și cum arată o plantă sănătoasă.";
    return $prompt;
}

function getOpenAIDiagnosis($prompt, $openaiKey) {
    $systemPrompt = "Ești un expert în boli ale plantelor. Răspunde clar, practic și în română. Dacă nu vezi probleme, spune explicit că planta pare sănătoasă, dar descrie ce simptome ar trebui urmărite.";
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
        'temperature' => 0.5
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
    return cleanForTTS($data['choices'][0]['message']['content']);
}

function cleanForTTS($text) {
    $text = preg_replace('/\*+/', '', $text);
    $text = preg_replace('/^\d+\.\s*/m', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

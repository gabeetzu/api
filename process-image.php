<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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

// --- Config ---
ini_set('max_execution_time', '60');
ini_set('memory_limit', '512M');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['image'])) {
        throw new Exception('Date lipsă: Imaginea nu a fost primită');
    }

    $imageBase64 = $data['image'];
    $userMessage = strip_tags(trim($data['message'] ?? ''));
    if (strlen($userMessage) > 300) {
        $userMessage = substr($userMessage, 0, 300);
    }

    validateImage($imageBase64);
    $treatment = getAITreatment($imageBase64, $userMessage);

    $responseId = bin2hex(random_bytes(6));
    error_log("Response ID: $responseId");

    echo json_encode([
        'success' => true,
        'response_id' => $responseId,
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

    if (strlen($imageBase64) < 100) {
        throw new Exception('Imagine prea mică sau invalidă');
    }

    $decodedImage = base64_decode($imageBase64, true);
    if ($decodedImage === false) {
        throw new Exception('Imagine coruptă');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($decodedImage);
    if (!in_array($mimeType, ['image/jpeg', 'image/png'])) {
        throw new Exception('Format neacceptat. Folosiți JPEG sau PNG.');
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
}

function getAITreatment($imageBase64, $userMessage) {
    $googleVisionKey = getenv('GOOGLE_VISION_KEY');
    $openaiKey = getenv('OPENAI_API_KEY');

    // Enhanced Google Vision analysis
    $visionData = analyzeImageWithVisionAPI($imageBase64, $googleVisionKey);
    $features = extractVisualFeatures($visionData);

    // Build structured prompt
    $prompt = buildAgronomistPrompt($features, $userMessage);
    
    // Get GPT response
    return getStructuredResponse($prompt, $openaiKey);
}

function analyzeImageWithVisionAPI($imageBase64, $googleVisionKey) {
    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . $googleVisionKey;
    $requestData = [
        'requests' => [[
            'image' => ['content' => $imageBase64],
            'features' => [
                ['type' => 'LABEL_DETECTION', 'maxResults' => 15],
                ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 5],
                ['type' => 'WEB_DETECTION', 'maxResults' => 5],
                ['type' => 'IMAGE_PROPERTIES'] // For color analysis
            ]
        ]]
    ];
    // ... [Make API call as before] ...
    return json_decode($response, true);
}

function extractVisualFeatures($visionData) {
    $features = [];
    
    // 1. Labels with high confidence
    if (isset($visionData['responses'][0]['labelAnnotations'])) {
        foreach ($visionData['responses'][0]['labelAnnotations'] as $label) {
            if ($label['score'] > 0.85) {
                $features[] = $label['description'];
            }
        }
    }
    
    // 2. Localized objects (e.g., "leaf with spots")
    if (isset($visionData['responses'][0]['localizedObjectAnnotations'])) {
        foreach ($visionData['responses'][0]['localizedObjectAnnotations'] as $obj) {
            if ($obj['score'] > 0.8) {
                $features[] = $obj['name'] . " (localizat)";
            }
        }
    }
    
    // 3. Dominant colors
    if (isset($visionData['responses'][0]['imagePropertiesAnnotation']['dominantColors']['colors'])) {
        foreach ($visionData['responses'][0]['imagePropertiesAnnotation']['dominantColors']['colors'] as $color) {
            if ($color['pixelFraction'] > 0.1) {
                $features[] = "Culoare dominantă: RGB(" 
                    . $color['color']['red'] . ","
                    . $color['color']['green'] . ","
                    . $color['color']['blue'] . ")";
            }
        }
    }
    
    // 4. Web entities related to plant pathology
    if (isset($visionData['responses'][0]['webDetection']['webEntities'])) {
        foreach ($visionData['responses'][0]['webDetection']['webEntities'] as $entity) {
            if (stripos($entity['description'], 'plant disease') !== false && $entity['score'] > 0.7) {
                $features[] = "Context web: " . $entity['description'];
            }
        }
    }
    
    return array_unique($features);
}

function buildAgronomistPrompt($features, $userMessage) {
    return "Imaginează-ți că ești un expert agronom care analizează fotografia unei plante afectate. Detalii extrase:

1. 🖼️ Caracteristici vizuale: " . (!empty($features) ? implode(', ', $features) : "Nu s-au detectat caracteristici clare") . "
" . ($userMessage ? "2. ❓ Întrebare utilizator: \"$userMessage\"\n" : "") . "

Răspunde în română, ca pentru un grădinar amator, folosind structura:

- 🔎 Observații: Descrie pete, culori anormale, forme, texturi (max 3 propoziții)
- 🦠 Cauze probabile: 2-3 boli/dăunători posibili (ex: mană, mucegai prafos)
- 💊 Tratament: Pași concreti cu produse specifice (ex: „Pulverizați cu Myclobutanil 0.2%”)
- 👀 Recomandări: Ce să monitorizeze în următoarele zile

Dacă nu vezi suficiente detalii, spune clar: „Nu pot da un diagnostic precis. Recomand [...]”";
}

function getStructuredResponse($prompt, $openaiKey) {
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => "Ești un agronom cu 20 de ani experiență. Răspunsurile tale sunt concise, practice și bazate pe observații vizuale."
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.3,
        'max_tokens' => 500
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        throw new Exception('Eroare la procesarea AI (OpenAI)');
    }

    $data = json_decode($response, true);
    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception('Răspuns invalid de la AI');
    }

    $responseText = trim($data['choices'][0]['message']['content']);
    $responseText = preg_replace('/^\-/', '•', $responseText); // Replace - with •
    $responseText = str_replace(':)', '🙂', $responseText);     // Emoji consistency

    return $responseText;
}

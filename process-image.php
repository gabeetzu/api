<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');
mb_internal_encoding("UTF-8");

// --- Handle preflight CORS ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- API Key Security ---
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');
if ($apiKey !== $expectedKey) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

try {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        $imageBase64 = $data['image'] ?? '';
        $userMessage = sanitizeInput($data['message'] ?? '');
    } elseif (isset($_FILES['image'])) {
        $allowedTypes = ['image/jpeg', 'image/png'];
        if (!in_array($_FILES['image']['type'], $allowedTypes)) {
            throw new Exception('Imagine neacceptată. Folosiți JPG sau PNG.');
        }
        $imageData = file_get_contents($_FILES['image']['tmp_name']);
        $imageBase64 = base64_encode($imageData);
        $userMessage = sanitizeInput($_POST['message'] ?? '');
    } else {
        throw new Exception('Date lipsă: Imaginea nu a fost primită');
    }

    if (empty($imageBase64)) throw new Exception('Date lipsă: Imaginea nu a fost primită');
    validateImage($imageBase64);

    $treatment = getAITreatment($imageBase64, $userMessage);

    echo json_encode([
        'success' => true,
        'response_id' => bin2hex(random_bytes(6)),
        'response' => $treatment
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// --- Helpers ---

function sanitizeInput($text) {
    $clean = strip_tags(trim($text));
    return mb_substr($clean, 0, 300);
}

function validateImage(&$imageBase64) {
    if (strlen($imageBase64) > 5 * 1024 * 1024) throw new Exception('Imagine prea mare');
    if (!preg_match('/^[a-zA-Z0-9\/+\s=]+$/', $imageBase64)) throw new Exception('Imagine invalidă');
}

function analyzeImageWithVisionAPI($imageBase64) {
    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . getenv('GOOGLE_VISION_KEY');
    
    $requestData = [
        'requests' => [[
            'image' => ['content' => $imageBase64],
            'features' => [
                ['type' => 'LABEL_DETECTION', 'maxResults' => 20],
                ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 10],
                ['type' => 'WEB_DETECTION', 'maxResults' => 10],
                ['type' => 'IMAGE_PROPERTIES']
            ]
        ]]
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($requestData)
        ]
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    if ($response === FALSE) {
        throw new Exception('Eroare la analiza imaginii (Google Vision)');
    }
    
    return json_decode($response, true);
}

function getAITreatment($imageBase64, $userMessage) {
    $visionData = analyzeImageWithVisionAPI($imageBase64);
    $features = extractVisualFeatures($visionData);

    $hasSymptoms = count($features) > 0 
        && !preg_grep('/^Culori dominante:/', $features)
        && !preg_grep('/nu a fost clasificată/i', $features);

    $prompt = $hasSymptoms 
        ? buildExpertPrompt($features, $userMessage)
        : buildClarificationPrompt($features, $userMessage);

    return getGPTResponse($prompt);
}

function extractVisualFeatures($visionData) {
    $features = [];
    $diseaseKeywords = ['leaf spot', 'blight', 'mildew', 'rust', 'rot', 'lesion', 'chlorosis', 'black spot', 'fungus', 'necrosis'];
    $hasDamageIndicators = false;
    $dominantColors = [];

    foreach ($visionData['responses'][0]['labelAnnotations'] ?? [] as $label) {
        $desc = strtolower($label['description']);
        if ($label['score'] > 0.8 && hasDiseaseKeyword($desc, $diseaseKeywords)) {
            $features[] = ucfirst($desc);
            $hasDamageIndicators = true;
        }
    }

    foreach ($visionData['responses'][0]['webDetection']['webEntities'] ?? [] as $entity) {
        $desc = strtolower($entity['description'] ?? '');
        if (($entity['score'] ?? 0) > 0.7 && hasDiseaseKeyword($desc, $diseaseKeywords)) {
            $features[] = "Web context: " . ucfirst($desc);
            $hasDamageIndicators = true;
        }
    }

    foreach ($visionData['responses'][0]['imagePropertiesAnnotation']['dominantColors']['colors'] ?? [] as $color) {
        if ($color['pixelFraction'] > 0.05) {
            $rgb = $color['color'];
            $hex = sprintf("#%02x%02x%02x", $rgb['red'], $rgb['green'], $rgb['blue']);
            $dominantColors[] = $hex;
        }
    }

    if (!$hasDamageIndicators && !empty($dominantColors)) {
        $features[] = "Culori dominante: " . implode(', ', $dominantColors);
        $features[] = "⚠️ Observație generală: culori neobișnuite sau pete maronii";
    }

    if (empty($features)) {
        $features[] = "⚠️ Imaginea nu a fost clasificată automat ca boală, dar frunza arată anormal (culoare, textură, pete etc).";
    }

    return array_unique($features);
}

function hasDiseaseKeyword($text, $keywords) {
    return preg_match('/(' . implode('|', $keywords) . ')/i', $text);
}

function formatFeatures(array $features): string {
    return $features ? "• " . implode("\n• ", $features) : 'Nicio caracteristică detectată';
}

function buildExpertPrompt($features, $userMessage) {
    $formattedFeatures = formatFeatures($features);
    return <<<PROMPT
**Context:** Expert agronom român analizează planta. 
**Simptome observate:**
$formattedFeatures
**Întrebare utilizator:** "$userMessage"

Chiar dacă clasificarea automată nu a identificat o boală clară, imaginea poate conține semne vizuale de deteriorare. Analizează logic și oferă o opinie estimativă.

**Analiză:**
1. Descrie simptome cheie (max 3)
2. Compară cu boli comune în RO
3. Elimină opțiuni improbabile
4. Ordonează după probabilitate

**Răspuns în structura:**
<observații>
• [Simptom 1]
• [Simptom 2]
</observații>

<cauze>
1. [Boală] ([Probabilitate 1-100%]) - [Detalii]
2. [Boală] ([Probabilitate]) - [Detalii]
</cauze>

<tratament>
• [Acțiune 1] (ex: "Tăiați frunzele infectate")
• [Acțiune 2] (ex: "Pulverizați cu [produs]")
</tratament>

<monitorizare>
• [Ce să verifice în următoarele zile]
</monitorizare>

Dacă informații insuficiente:
<neclar>
• [Ce detalii lipsesc]
</neclar>
PROMPT;
}

function buildClarificationPrompt($features, $userMessage) {
    $formatted = formatFeatures($features);
    return <<<PROMPT
**Imagine analizată automat:**
$formatted

Din imagine nu pot identifica probleme clare. Poate calitatea nu e suficient de bună sau simptomele nu sunt vizibile clar. Dar te pot ajuta imediat dacă îmi spui:

• Ce tip de plantă e? (ex: roșie, ardei, viță de vie)  
• Ce simptome ai observat tu? (ex: pete, ofilire, frunze căzute)  
• Când au apărut simptomele?  
• Ai aplicat vreun tratament deja?

Te rog răspunde cu cât mai multe detalii și îți ofer imediat sfaturi clare și un tratament potrivit.
PROMPT;
}

function getGPTResponse($prompt) {
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . getenv('OPENAI_API_KEY')
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => 'Ești un expert agronom român, cu 30 de ani de experiență practică. Explici simplu, în română, ca pentru un om în vârstă, fără termeni tehnici.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.2,
            'max_tokens' => 600
        ])
    ]);

    $response = curl_exec($ch);
    if (!$response) throw new Exception('Eroare OpenAI');
    $data = json_decode($response, true);
    if (!isset($data['choices'][0]['message']['content'])) throw new Exception('Răspuns invalid de la OpenAI');
    return formatResponse($data['choices'][0]['message']['content']);
}

function formatResponse($text) {
    $text = str_replace(['<observații>', '</observații>'], "🔎 Observații\n", $text);
    $text = str_replace(['<cauze>', '</cauze>'], "\n🦠 Cauze probabile\n", $text);
    $text = str_replace(['<tratament>', '</tratament>'], "\n💊 Tratament\n", $text);
    $text = str_replace(['<monitorizare>', '</monitorizare>'], "\n👀 Recomandări\n", $text);
    $text = str_replace(['<neclar>', '</neclar>'], "\n❓ Necesită verificare\n", $text);
    return str_replace('**', '', $text);
}

function logSuccess() {
    error_log("Processing completed successfully");
    if (rand(1, 100) > 95) {
        error_log("Sample success event logged.");
    }
}

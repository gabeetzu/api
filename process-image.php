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

// --- Logging and Security ---
error_log("=== IMAGE PROCESSING START ===");
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
    // Accept both JSON and form-data
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $imageBase64 = $data['image'] ?? '';
        $userMessage = sanitizeInput($data['message'] ?? '');
    } elseif (isset($_FILES['image'])) {
        $imageData = file_get_contents($_FILES['image']['tmp_name']);
        $imageBase64 = base64_encode($imageData);
        $userMessage = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';
    } else {
        throw new Exception('Date lipsă: Imaginea nu a fost primită');
    }

    if (empty($imageBase64)) {
        throw new Exception('Date lipsă: Imaginea nu a fost primită');
    }

    validateImage($imageBase64);
    $treatment = getAITreatment($imageBase64, $userMessage);

    logSuccess();
    echo json_encode([
        'success' => true,
        'response_id' => bin2hex(random_bytes(6)),
        'response' => $treatment
    ]);

} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// --- Helper Functions ---
function sanitizeInput($text) {
    $clean = strip_tags(trim($text));
    return mb_substr($clean, 0, 300);
}

function validateImage(&$imageBase64) {
    // Basic validation: check if base64 string is valid and not too large
    if (strlen($imageBase64) > 5 * 1024 * 1024) { // 5MB limit
        throw new Exception('Imagine prea mare');
    }
    if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $imageBase64)) {
        throw new Exception('Imagine invalidă');
    }
}

function getAITreatment($imageBase64, $userMessage) {
    $visionData = analyzeImageWithVisionAPI($imageBase64);
    $features = extractVisualFeatures($visionData);
    $prompt = buildExpertPrompt($features, $userMessage);
    return getGPTResponse($prompt);
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

function extractVisualFeatures($visionData) {
    $features = [];
    $diseaseKeywords = ['leaf spot', 'blight', 'mildew', 'rust', 'rot', 'lesion', 'chlorosis'];

    // 1. Pathology-focused labels
    foreach ($visionData['responses'][0]['labelAnnotations'] ?? [] as $label) {
        if ($label['score'] > 0.85 && hasDiseaseKeyword($label['description'], $diseaseKeywords)) {
            $features[] = $label['description'];
        }
    }

    // 2. Precise object locations
    foreach ($visionData['responses'][0]['localizedObjectAnnotations'] ?? [] as $obj) {
        if ($obj['score'] > 0.8) {
            $position = $obj['boundingPoly']['normalizedVertices'][0] ?? null;
            $loc = $position ? sprintf("(%.0f%%,%.0f%%)", $position['x']*100, $position['y']*100) : "";
            $features[] = "{$obj['name']} $loc";
        }
    }

    // 3. Color analysis with HEX codes
    foreach ($visionData['responses'][0]['imagePropertiesAnnotation']['dominantColors']['colors'] ?? [] as $color) {
        if ($color['pixelFraction'] > 0.1) {
            $rgb = $color['color'];
            $hex = sprintf("#%02x%02x%02x", $rgb['red'], $rgb['green'], $rgb['blue']);
            $features[] = "Culoare: $hex";
        }
    }

    // 4. Web context filtering
    foreach ($visionData['responses'][0]['webDetection']['webEntities'] ?? [] as $entity) {
        if (($entity['score'] ?? 0) > 0.7 && hasDiseaseKeyword($entity['description'] ?? '', $diseaseKeywords)) {
            $features[] = "Context: " . substr($entity['description'], 0, 50);
        }
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
                ['role' => 'system', 'content' => 'Ești un expert agronom. Răspunsurile sunt concise, în română.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.2,
            'max_tokens' => 600
        ])
    ]);

    $response = curl_exec($ch);
    if (!$response) throw new Exception('Eroare OpenAI');

    $data = json_decode($response, true);
    return formatResponse($data['choices'][0]['message']['content']);
}

function formatResponse($text) {
    // Convert XML-like tags to Markdown
    $text = str_replace(['<observații>', '</observații>'], "🔎 **Observații**\n", $text);
    $text = str_replace(['<cauze>', '</cauze>'], "\n🦠 **Cauze probabile**\n", $text);
    $text = str_replace(['<tratament>', '</tratament>'], "\n💊 **Tratament**\n", $text);
    $text = str_replace(['<monitorizare>', '</monitorizare>'], "\n👀 **Recomandări**\n", $text);
    $text = str_replace(['<neclar>', '</neclar>'], "\n❓ **Necesită verificare**\n", $text);
    
    return preg_replace('/•/', '•', $text); // Ensure consistent bullets
}

function logSuccess() {
    error_log("Processing completed successfully");
    if (rand(1, 100) > 95) { // Sample 5% of requests
        error_log("Sample successful request details");
    }
}

?>

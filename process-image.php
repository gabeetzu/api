<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');
mb_internal_encoding("UTF-8");

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Security check
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');
if ($apiKey !== $expectedKey) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

ini_set('max_execution_time', '60');
ini_set('memory_limit', '512M');

try {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        $imageBase64 = $data['image'] ?? '';
        $userMessage = sanitizeInput($data['message'] ?? '');
    } elseif (isset($_FILES['image'])) {
        $allowed = ['image/jpeg', 'image/png'];
        if (!in_array($_FILES['image']['type'], $allowed)) {
            throw new Exception('Imagine neacceptată. Folosiți JPG sau PNG.');
        }
        $imageData = file_get_contents($_FILES['image']['tmp_name']);
        $imageBase64 = base64_encode($imageData);
        $userMessage = sanitizeInput($_POST['message'] ?? '');
    } else {
        throw new Exception('Date lipsă: Imaginea nu a fost primită');
    }

    if (empty($imageBase64)) {
        throw new Exception('Imagine lipsă sau coruptă');
    }

    validateImage($imageBase64);
    $treatment = getAITreatment($imageBase64, $userMessage);

    echo json_encode([
        'success' => true,
        'response_id' => bin2hex(random_bytes(6)),
        'response' => ['text' => $treatment]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// --- Helper Functions ---

function sanitizeInput($text) {
    return mb_substr(strip_tags(trim($text)), 0, 300);
}

function validateImage($base64) {
    if (strlen($base64) > 5 * 1024 * 1024) {
        throw new Exception('Imagine prea mare');
    }
    if (!preg_match('/^[a-zA-Z0-9\/+=\s]+$/', $base64)) {
        throw new Exception('Imagine invalidă');
    }
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
    $keywords = ['leaf spot', 'blight', 'mildew', 'rust', 'rot', 'lesion', 'chlorosis', 'black spot', 'fungus', 'necrosis'];
    $hasDamage = false;
    $colors = [];

    foreach ($visionData['responses'][0]['labelAnnotations'] ?? [] as $label) {
        $desc = strtolower($label['description']);
        if ($label['score'] > 0.8 && preg_match('/(' . implode('|', $keywords) . ')/i', $desc)) {
            $features[] = ucfirst($desc);
            $hasDamage = true;
        }
    }

    foreach ($visionData['responses'][0]['webDetection']['webEntities'] ?? [] as $entity) {
        $desc = strtolower($entity['description'] ?? '');
        if (($entity['score'] ?? 0) > 0.7 && preg_match('/(' . implode('|', $keywords) . ')/i', $desc)) {
            $features[] = "Web context: " . ucfirst($desc);
            $hasDamage = true;
        }
    }

    foreach ($visionData['responses'][0]['imagePropertiesAnnotation']['dominantColors']['colors'] ?? [] as $c) {
        if ($c['pixelFraction'] > 0.05) {
            $rgb = $c['color'];
            $colors[] = sprintf("#%02x%02x%02x", $rgb['red'], $rgb['green'], $rgb['blue']);
        }
    }

    if (!$hasDamage && !empty($colors)) {
        $features[] = "Culori dominante: " . implode(', ', $colors);
        if (count(preg_grep('/^#(6|7|8|9|a)[0-9a-f]{5}$/i', $colors)) > 1) {
            $features[] = "⚠️ Observație generală: culori neobișnuite sau pete maronii";
        }
    }

    if (empty($features)) {
        $features[] = "⚠️ Imaginea nu a fost clasificată automat ca boală, dar frunza arată anormal (culoare, textură, pete etc).";
    }

    return array_unique($features);
}

function buildExpertPrompt($features, $msg) {
    $f = "• " . implode("\n• ", $features);
    return <<<TEXT
**Context:** Expert agronom român analizează planta. 
**Simptome observate:**
$f
**Întrebare utilizator:** "$msg"

Chiar dacă clasificarea automată nu a identificat o boală clară, imaginea poate conține semne vizuale de deteriorare.

**Analiză:**
<observații>• ...</observații>
<cauze>1. ...</cauze>
<tratament>• ...</tratament>
<monitorizare>• ...</monitorizare>
TEXT;
}

function buildClarificationPrompt($features, $msg) {
    $f = "• " . implode("\n• ", $features);
    return <<<TEXT
**Imagine analizată automat:**
$f

Nu se observă simptome clare. Ca să pot ajuta:
• Ce plantă e?
• Ce simptome ai văzut?
• Când au apărut?
• Ai dat deja vreun tratament?

Scrie cât mai multe detalii și îți ofer imediat o recomandare.
TEXT;
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
                ['role' => 'system', 'content' => 'Ești un expert agronom român. Răspunde clar, simplu, ca pentru o persoană în vârstă.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.2,
            'max_tokens' => 600
        ])
    ]);

    $response = curl_exec($ch);
    if (!$response) throw new Exception('Eroare OpenAI');
    $data = json_decode($response, true);

    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception('Răspuns invalid de la OpenAI');
    }

    return formatResponse($data['choices'][0]['message']['content']);
}

function formatResponse($text) {
    return str_replace(
        ['<observații>', '</observații>', '<cauze>', '</cauze>', '<tratament>', '</tratament>', '<monitorizare>', '</monitorizare>', '**'],
        ["🔎 Observații\n", "", "\n🦠 Cauze probabile\n", "", "\n💊 Tratament\n", "", "\n👀 Recomandări\n", "", ""],
        $text
    );
}

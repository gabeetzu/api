<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');
mb_internal_encoding("UTF-8");

// --- Logging ---
function logEvent($label, $data) {
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) mkdir($logDir, 0775, true);
    $line = date('Y-m-d H:i:s') . " [$label] " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($logDir . '/activity.log', $line, FILE_APPEND);
}

// --- Handle preflight CORS ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- API Key Security ---
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');
if (!hash_equals($expectedKey, $apiKey)) {
    logEvent('Unauthorized', ['ip' => $_SERVER['REMOTE_ADDR']]);
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat'], JSON_UNESCAPED_UNICODE));
}

try {
    $input = getInputData();
    logEvent('Input', $input);

    $imageBase64 = $input['image'] ?? '';
    $userMessage = sanitizeInput($input['message'] ?? '');
    $cnnDiagnosis = sanitizeInput($input['diagnosis'] ?? '');

    if (!empty($imageBase64)) {
        validateImage($imageBase64);
        $treatment = handleImageAnalysis($imageBase64, $userMessage, $cnnDiagnosis);
    } elseif (!empty($cnnDiagnosis)) {
        $treatment = handleCnnDiagnosis($cnnDiagnosis, $userMessage);
    } elseif (!empty($userMessage)) {
        $treatment = getGPTResponse($userMessage);
    } else {
        throw new Exception('Date lipsă: Trimiteți o imagine, un diagnostic sau un mesaj');
    }

    if ($treatment === null) {
        throw new Exception('Răspuns gol de la AI');
    }

    echo safeJsonEncode([
        'success' => true,
        'response_id' => bin2hex(random_bytes(6)),
        'response' => is_string($treatment) ? ['text' => $treatment, 'raw' => $treatment] : $treatment
    ]);
    logEvent('Success', $treatment);

} catch (Exception $e) {
    logEvent('Error', $e->getMessage());
    http_response_code(400);
    echo safeJsonEncode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getInputData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function sanitizeInput($text) {
    $clean = trim(strip_tags($text));
    $clean = preg_replace('/[^\p{L}\p{N}\s.,;:!?()-]/u', '', $clean);
    return mb_substr($clean, 0, 300);
}

function validateImage(&$imageBase64) {
    if (strlen($imageBase64) > 5 * 1024 * 1024) {
        throw new Exception('Imagine prea mare (max 5MB)');
    }
    if (!preg_match('/^[a-zA-Z0-9\/+=]+$/', $imageBase64)) {
        throw new Exception('Format imagine invalid');
    }
}

function handleImageAnalysis($imageBase64, $userMessage, $cnnDiagnosis) {
    $visionData = analyzeImageWithVisionAPI($imageBase64);
    $features = extractVisualFeatures($visionData);

    if (empty($features) || (count($features) === 1 && str_contains($features[0], 'Nu s-au detectat'))) {
        $features = runYoloFallback($imageBase64);
    }

    $prompt = buildHybridPrompt(
        formatFeatures($features),
        $userMessage,
        $cnnDiagnosis
    );

    $response = getGPTResponse($prompt);
    saveTrainingExample($imageBase64, $cnnDiagnosis, $userMessage);

    return $response;
}

function runYoloFallback($base64) {
    $tmp = __DIR__ . '/temp_yolo.jpg';
    file_put_contents($tmp, base64_decode($base64));
    $cmd = escapeshellcmd("python3 yolo_infer.py " . escapeshellarg($tmp));
    $output = shell_exec($cmd);
    if (!$output) return ["Analiza alternativă a eșuat"];
    $data = json_decode($output, true);
    return isset($data['label']) ? [ucfirst($data['label']) . " (YOLO: " . round($data['confidence'] * 100) . "% încredere)"] : ["YOLO nu a detectat nimic clar"];
}

function handleCnnDiagnosis($diagnosis, $userMessage) {
    $prompt = buildCnnBasedPrompt($diagnosis, $userMessage);
    return getGPTResponse($prompt);
}

function analyzeImageWithVisionAPI($imageBase64) {
    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . getenv('GOOGLE_VISION_KEY');
    $requestData = [
        'requests' => [[
            'image' => ['content' => $imageBase64],
            'features' => [
                ['type' => 'LABEL_DETECTION', 'maxResults' => 15],
                ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 10],
                ['type' => 'IMAGE_PROPERTIES']
            ]
        ]]
    ];

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($requestData)
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    if ($response === false) {
        throw new Exception('Eroare la analiza imaginii');
    }
    return json_decode($response, true);
}

function extractVisualFeatures($visionData) {
    $features = [];
    $diseaseKeywords = ['leaf spot', 'blight', 'mildew', 'rust', 'rot', 'lesion', 'chlorosis'];
    foreach ($visionData['responses'][0]['labelAnnotations'] ?? [] as $label) {
        if ($label['score'] > 0.75 && hasDiseaseKeyword($label['description'], $diseaseKeywords)) {
            $features[] = ucfirst($label['description']);
        }
    }
    $colors = [];
    foreach ($visionData['responses'][0]['imagePropertiesAnnotation']['dominantColors']['colors'] ?? [] as $color) {
        if ($color['pixelFraction'] > 0.05) {
            $rgb = $color['color'];
            $colors[] = sprintf("#%02x%02x%02x", $rgb['red'], $rgb['green'], $rgb['blue']);
        }
    }
    if (!empty($colors)) {
        $features[] = "Culori predominante: " . implode(', ', $colors);
    }
    return empty($features) ? ["Nu s-au detectat caracteristici clare"] : $features;
}

function hasDiseaseKeyword($text, $keywords) {
    return preg_match('/\\b(' . implode('|', $keywords) . ')\\b/i', $text);
}

// --- Prompt Engineering ---
function buildHybridPrompt($features, $userMessage, $cnnDiagnosis) {
    return <<<PROMPT
Ești un asistent agronom prietenos pentru aplicația GospodApp. Răspunde în limba română clar și empatic.

Context:
- Diagnostic AI: {$cnnDiagnosis}
- Simptome vizuale: {$features}
- Întrebare utilizator: {$userMessage}

Instrucțiuni:
1. Începe cu o adresare prietenoasă („Salut! Am analizat imaginea ta...”)
2. Spune clar ce poate avea planta, fără termeni complicați.
3. Oferă 2-3 pași concreți (cu emoji dacă e cazul, ex: 💧☀️✂️).
4. Sugerează un produs (numai dacă e aprobat UE).
5. Încheie cu un sfat de prevenire + încurajare („Succes cu grădina ta!”)
6. Dacă nu ai destule informații, cere detalii în plus.

Reguli:
- Fără liste lungi sau termeni științifici.
- Max. 5 propoziții.
- Răspunde doar pe subiecte legate de grădinărit, plante sau agricultură.
PROMPT;
}

function buildCnnBasedPrompt($diagnosis, $userMessage) {
    return <<<PROMPT
Salut! Am analizat diagnosticul AI: {$diagnosis}
Întrebarea ta: {$userMessage}

Instrucțiuni:
1. Explică simplu diagnosticul.
2. Oferă 2-3 pași practici (emoji dacă se potrivește).
3. Adaugă un sfat de prevenire și un mesaj pozitiv.
PROMPT;
}

function formatFeatures(array $features) {
    return '• ' . implode("\n• ", array_slice($features, 0, 5));
}

// --- GPT Integration ---
function getGPTResponse($prompt) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT => 12,
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . getenv('OPENAI_API_KEY')
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' =>
                    'Ești un asistent agronom empatic pentru aplicația GospodApp. Răspunde mereu în română, simplu, clar și pozitiv. Nu răspunde la întrebări în afara agriculturii.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 600,
            'top_p' => 0.9
        ])
    ]);

    $response = curl_exec($ch);
    if (!$response || curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        throw new Exception('Eroare la serviciul OpenAI');
    }

    $data = json_decode($response, true);
    if (empty($data['choices'][0]['message']['content'])) {
        throw new Exception('Răspuns invalid de la AI');
    }

    $raw = $data['choices'][0]['message']['content'];
    return [
        'text' => formatResponse($raw),
        'raw' => $raw
    ];
}

function formatResponse($text) {
    return preg_replace([
        '/##\s+/',
        '/\*\*(.*?)\*\*/',
        '/<tratament>/i',
        '/<prevenire>/i'
    ], [
        '🔸 ',
        '$1',
        '💊 Tratament:',
        '🛡 Prevenire:'
    ], $text);
}

// --- Auto-Training Sample Save ---
function saveTrainingExample($base64, $label, $note) {
    if (empty($base64)) return;

    $dir = __DIR__ . '/data/uploads';
    if (!file_exists($dir)) {
        mkdir($dir, 0775, true);
    }

    $imageData = base64_decode($base64);
    if (!$imageData) return;

    $filename = 'plant_' . time() . '_' . rand(1000, 9999) . '.jpg';
    $filePath = $dir . '/' . $filename;
    file_put_contents($filePath, $imageData);

    $csvLine = '"' . addslashes($label) . '","' . addslashes($note) . '","' . addslashes($filename) . '"' . PHP_EOL;
    file_put_contents(__DIR__ . '/data/dataset.csv', $csvLine, FILE_APPEND);

    logEvent('TrainingSaved', ['file' => $filename, 'label' => $label, 'note' => $note]);
}

function safeJsonEncode($data) {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
}

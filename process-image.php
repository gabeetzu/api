<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');
mb_internal_encoding("UTF-8");

ini_set('display_errors', 0);
error_reporting(0);


function logEvent($label, $data) {
    $dir = __DIR__ . '/logs';
    if (!file_exists($dir)) mkdir($dir, 0775, true);
    $line = date('Y-m-d H:i:s') . " [$label] " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($dir . '/activity.log', $line, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');
if (!hash_equals($expectedKey, $apiKey)) {
    logEvent('Unauthorized', ['ip' => $_SERVER['REMOTE_ADDR']]);
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Acces neautorizat']);
    exit();
}

try {
    $input = getInputData();
    logEvent('ImageInput', $input);

    $imageBase64 = $input['image'] ?? '';
    $userMessage = sanitizeInput($input['message'] ?? '');
    $cnnDiagnosis = sanitizeInput($input['diagnosis'] ?? '');

    if (!empty($imageBase64)) {
        validateImage($imageBase64);
        $response = handleImageAnalysis($imageBase64, $userMessage, $cnnDiagnosis);
    } elseif (!empty($cnnDiagnosis)) {
        $response = handleCnnDiagnosis($cnnDiagnosis, $userMessage);
    } elseif (!empty($userMessage)) {
        $response = getGPTResponse($userMessage);
    } else {
        throw new Exception('Date lipsă: trimiteți o imagine, un diagnostic sau un mesaj.');
    }

    logEvent('ImageResponse', ['text' => $response]);
    $json = json_encode([
    'success' => true,
    'response_id' => bin2hex(random_bytes(6)),
    'response' => $response  // now a flat string
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        http_response_code(500);
        echo '{"success":false,"error":"Eroare la formatul JSON"}';
        exit();
    }

    echo $json;

} catch (Exception $e) {
    logEvent('Error', $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

function getInputData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $json = json_decode(file_get_contents('php://input'), true);
        return is_array($json) ? $json : [];
    }
    return $_POST;
}

function sanitizeInput($text) {
    $clean = trim(strip_tags($text));
    $clean = preg_replace('/[^\p{L}\p{N}\s.,;:!?()-]/u', '', $clean);
    return mb_substr($clean, 0, 300);
}

function validateImage(&$base64) {
    if (strpos($base64, 'base64,') !== false) {
        $parts = explode(',', $base64, 2);
        $base64 = $parts[1];
    }
    $base64 = preg_replace('/[\s\r\n]/', '', $base64);
    if (strlen($base64) > 5 * 1024 * 1024) throw new Exception('Imagine prea mare (max 5MB)');
    if (!preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $base64)) throw new Exception('Format imagine invalid');
}

function handleImageAnalysis($base64, $userMessage, $cnnDiagnosis) {
    $visionData = analyzeImageWithVisionAPI($base64);
    $features = extractVisualFeatures($visionData);

    // If Vision fails or is weak, fallback to TFLite diagnosis (already coming from Android client)
    if (empty($features)) {
        $features = ["Nu s-au detectat semne clare pe imagine"];
    }

    $prompt = buildHybridPrompt(
        formatFeatures($features),
        $userMessage,
        $cnnDiagnosis
    );

    $result = getGPTResponse($prompt);
    saveTrainingExample($base64, $cnnDiagnosis, $userMessage);
    return $result;
}

function handleCnnDiagnosis($diagnosis, $userMessage) {
    $prompt = buildCnnBasedPrompt($diagnosis, $userMessage);
    return getGPTResponse($prompt);
}

function analyzeImageWithVisionAPI($base64) {
    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . getenv('GOOGLE_VISION_KEY');
    $body = [
        'requests' => [[
            'image' => ['content' => $base64],
            'features' => [
                ['type' => 'LABEL_DETECTION', 'maxResults' => 10],
                ['type' => 'IMAGE_PROPERTIES']
            ]
        ]]
    ];
    $opts = ['http' => [
        'header'  => "Content-Type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($body)
    ]];
    $context = stream_context_create($opts);
    $res = @file_get_contents($url, false, $context);
    if (!$res) {
        logEvent('VisionFail', $http_response_header ?? []);
        throw new Exception('Eroare la analiza imaginii');
    }
    return json_decode($res, true);
}

function extractVisualFeatures($data) {
    $features = [];
    $keywords = ['leaf spot', 'blight', 'mildew', 'rust', 'rot', 'lesion', 'chlorosis'];
    foreach ($data['responses'][0]['labelAnnotations'] ?? [] as $label) {
        if ($label['score'] > 0.75 && preg_match('/' . implode('|', $keywords) . '/i', $label['description'])) {
            $features[] = ucfirst($label['description']);
        }
    }
    $colors = [];
    foreach ($data['responses'][0]['imagePropertiesAnnotation']['dominantColors']['colors'] ?? [] as $color) {
        if ($color['pixelFraction'] > 0.05) {
            $c = $color['color'];
            $colors[] = sprintf("#%02x%02x%02x", $c['red'], $c['green'], $c['blue']);
        }
    }
    if (!empty($colors)) {
        $features[] = "Culori predominante: " . implode(', ', $colors);
    }
    return $features ?: ["Nu s-au detectat caracteristici clare"];
}

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
            'max_tokens' => 1200,
            'top_p' => 0.9
        ])
    ]);
    $res = curl_exec($ch);
    if (!$res || curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        curl_close($ch);
        throw new Exception('Eroare la serviciul OpenAI');
    }
    curl_close($ch);
    $data = json_decode($res, true);
    if (empty($data['choices'][0]['message']['content'])) throw new Exception('Răspuns invalid de la AI');
    $raw = $data['choices'][0]['message']['content'];
    return formatResponse($raw);
}

function formatResponse($text) {
    return preg_replace([
        '/##\s+/', '/\*\*(.*?)\*\*/', '/<tratament>/i', '/<prevenire>/i'
    ], [
        '🔸 ', '$1', '💊 Tratament:', '🛡 Prevenire:'
    ], $text);
}

function saveTrainingExample($base64, $label, $note) {
    if (empty($base64)) return;
    $dir = __DIR__ . '/data/uploads';
    if (!file_exists($dir)) mkdir($dir, 0775, true);
    $imageData = base64_decode($base64);
    if (!$imageData) return;
    $filename = 'plant_' . time() . '_' . rand(1000, 9999) . '.jpg';
    file_put_contents($dir . '/' . $filename, $imageData);
    $line = '"' . addslashes($label) . '","' . addslashes($note) . '","' . addslashes($filename) . '"' . PHP_EOL;
    file_put_contents(__DIR__ . '/data/dataset.csv', $line, FILE_APPEND);
}

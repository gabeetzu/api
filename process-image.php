<?php
header('Content-Type: application/json; charset=utf-8');
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
if (!hash_equals($expectedKey, $apiKey)) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat'], JSON_UNESCAPED_UNICODE));
}

try {
    $input = getInputData();
    $imageBase64 = $input['image'] ?? '';
    $userMessage = sanitizeInput($input['message'] ?? '');
    $cnnDiagnosis = sanitizeInput($input['diagnosis'] ?? '');

    if (!empty($imageBase64)) {
        validateImage($imageBase64);
        $treatment = handleImageAnalysis($imageBase64, $userMessage, $cnnDiagnosis);
    } elseif (!empty($cnnDiagnosis)) {
        $treatment = handleCnnDiagnosis($cnnDiagnosis, $userMessage);
    } elseif (!empty($userMessage)) {
        $treatment = getGPTResponse($userMessage); // ✅ FIXED HERE
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

} catch (Exception $e) {
    http_response_code(400);
    echo safeJsonEncode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// --- Helper Functions ---
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
    if (!preg_match('/^[a-zA-Z0-9\/+]+={0,2}$/', $imageBase64)) {
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

    // ✅ Save training data
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
    if (!isset($data['label'])) return ["YOLO nu a detectat nimic clar"];

    return [ucfirst($data['label']) . " (YOLO: " . round($data['confidence'] * 100) . "% încredere)"];
}
    
    // ✅ Save training data
    saveTrainingExample($imageBase64, $cnnDiagnosis, $userMessage);

    return $response;
}

function handleCnnDiagnosis($diagnosis, $userMessage) {
    $prompt = buildCnnBasedPrompt($diagnosis, $userMessage);
    return getGPTResponse($prompt);
}

// --- Vision API Integration ---
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
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true
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
    return preg_match('/\b(' . implode('|', $keywords) . ')\b/i', $text);
}

// --- Prompt Engineering ---
function buildHybridPrompt($features, $userMessage, $cnnDiagnosis) {
    return <<<PROMPT
Ești un asistent agronom prietenos pentru aplicația GospodApp. Răspunde în limba română clar și empatic.

Context: 
- Diagnostic model AI: {$cnnDiagnosis ?: 'Nespecificat'}
- Simptome vizuale: {$features}
- Întrebare de la utilizator: {$userMessage}

Instrucțiuni:
1. Începe cu o adresare caldă ("Salut! Am analizat imaginea ta...")
2. Explică pe scurt ce ar putea avea planta, folosind cuvinte simple.
3. Oferă 2-3 pași concreți de acțiune (folosește emoji unde se potrivește, ex: 💧☀️✂️).
4. Recomandă un produs sau tratament (numai dacă e aprobat UE).
5. Dă un sfat de prevenire și încheie cu o încurajare ("Succes cu grădina ta!").
6. Dacă informațiile nu sunt suficiente, cere detalii suplimentare.

Reguli:
- Nu folosi termeni științifici sau liste lungi.
- Max. 5 propoziții.
- Fii pozitiv și scurt. Dacă întrebarea nu are legătură cu plante, grădinărit sau agricultură, explică politicos că poți răspunde doar la astfel de subiecte.
PROMPT;
}

function buildCnnBasedPrompt($diagnosis, $userMessage) {
    return <<<PROMPT
Salut! Am analizat diagnosticul AI: {$diagnosis}
Întrebarea ta: {$userMessage}

Instrucțiuni:
1. Explică diagnosticul pe scurt, cu cuvinte simple.
2. Dă 2-3 pași concreți de acțiune (emoji dacă se potrivește).
3. Sfat de prevenire și o încurajare ("Succes cu grădina ta!").
Dacă întrebarea nu are legătură cu plante, grădinărit sau agricultură, explică politicos că poți răspunde doar la astfel de subiecte.
PROMPT;
}

function formatFeatures(array $features) {
    return '• ' . implode("\n• ", array_slice($features, 0, 5));
}

// --- GPT-4o Integration ---
function getGPTResponse($prompt) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT => 10,
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . getenv('OPENAI_API_KEY')
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Ești un asistent agronom empatic pentru aplicația GospodApp. Răspunde mereu în română, pe înțelesul tuturor, folosind un ton prietenos și exemple practice. Nu răspunde la întrebări care nu țin de plante, grădinărit sau agricultură.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
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

// ✅ Save image + metadata
function saveTrainingExample($base64, $label, $note) {
    $dir = __DIR__ . '/data/uploads';
    if (!file_exists($dir)) {
        mkdir($dir, 0775, true);
    }

    $imageData = base64_decode($base64);
    $filename = 'plant_' . time() . '_' . rand(1000, 9999) . '.jpg';
    $filePath = $dir . '/' . $filename;
    file_put_contents($filePath, $imageData);

    $csvLine = '"' . addslashes($label) . '","' . addslashes($note) . '","' . addslashes($filename) . '"' . PHP_EOL;
    file_put_contents(__DIR__ . '/data/dataset.csv', $csvLine, FILE_APPEND);
}

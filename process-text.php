<?php
// Complete process-text.php with database integration and improved responses

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Verify API Key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== getenv('API_SECRET_KEY')) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

ini_set('max_execution_time', '60');
ini_set('memory_limit', '256M');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['message']) || !isset($data['device_hash'])) {
        throw new Exception('Date lipsă: Mesajul sau hash-ul dispozitivului nu au fost primite');
    }

    $message = trim($data['message']);
    $deviceHash = $data['device_hash'];

    if (empty($message)) {
        throw new Exception('Mesajul nu poate fi gol');
    }

    // Connect to database
    $pdo = connectToDatabase();
    
    // Check usage limits
    checkUsageLimits($pdo, $deviceHash, 'text');

    // Save user message to chat history
    saveChatHistory($pdo, $deviceHash, $message, true, 'text', null);

    // Get response from OpenAI
    $response = getTextResponseFromOpenAI($message);

    // Save bot response to chat history
    saveChatHistory($pdo, $deviceHash, $response, false, 'text', null);

    // Record usage
    recordUsage($pdo, $deviceHash, 'text');

    echo json_encode([
        'success' => true,
        'response' => $response
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function connectToDatabase() {
    $host = getenv('DB_HOST');
    $dbname = getenv('DB_NAME');
    $username = getenv('DB_USER');
    $password = getenv('DB_PASS');
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    return new PDO($dsn, $username, $password, $options);
}

function checkUsageLimits($pdo, $deviceHash, $type) {
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT text_count, image_count, premium, extra_questions 
        FROM usage_tracking 
        WHERE device_hash = ? AND date = ?
    ");
    $stmt->execute([$deviceHash, $today]);
    $usage = $stmt->fetch();
    
    if (!$usage) {
        $stmt = $pdo->prepare("
            INSERT INTO usage_tracking (device_hash, date, text_count, image_count, premium, extra_questions) 
            VALUES (?, ?, 0, 0, 0, 0)
        ");
        $stmt->execute([$deviceHash, $today]);
        $usage = ['text_count' => 0, 'image_count' => 0, 'premium' => 0, 'extra_questions' => 0];
    }
    
    if ($type === 'text') {
        $limit = $usage['premium'] ? 100 : 10;
        $totalUsed = $usage['text_count'] - $usage['extra_questions'];
        if ($totalUsed >= $limit) {
            throw new Exception('Ați atins limita zilnică de întrebări. Urmăriți o reclamă pentru întrebări extra sau upgradeați la premium.');
        }
    }
}

function recordUsage($pdo, $deviceHash, $type) {
    $today = date('Y-m-d');
    $field = $type === 'image' ? 'image_count' : 'text_count';
    
    $stmt = $pdo->prepare("
        UPDATE usage_tracking 
        SET $field = $field + 1 
        WHERE device_hash = ? AND date = ?
    ");
    $stmt->execute([$deviceHash, $today]);
}

function saveChatHistory($pdo, $deviceHash, $messageText, $isUserMessage, $messageType, $imageData) {
    $stmt = $pdo->prepare("
        INSERT INTO chat_history (device_hash, message_text, is_user_message, message_type, image_data) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$deviceHash, $messageText, $isUserMessage ? 1 : 0, $messageType, $imageData]);
}

function getTextResponseFromOpenAI($message) {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        throw new Exception('Cheia OpenAI nu este configurată corect');
    }

    // Enhanced system prompt for better Romanian gardening advice
    $systemPrompt = "Ești un expert în grădinărit din România cu 30 de ani experiență. 

REGULI IMPORTANTE:
- Răspunzi DOAR la întrebări despre plante, grădină, agricultură și grădinărit
- Dacă întrebarea nu e despre grădinărit, spui politicos că poți ajuta doar cu sfaturi de grădină
- Vorbești natural, ca un prieten cu experiență, fără formalități excesive
- Folosești termeni simpli, accesibili oricărui grădinar român
- Dai sfaturi practice, testate și aplicabile în România
- Menționezi anotimpul potrivit pentru activități
- Eviți asteriscuri, numere în paranteză sau formatare specială
- Răspunsurile să fie între 100-300 de cuvinte, clare și practice

Cunoștințele tale includ:
- Plantele cultivate în clima României
- Boli și dăunători comuni în România  
- Produse și îngrășăminte disponibile în România
- Anotimpurile și climatul specific României
- Tehnici tradiționale românești de grădinărit";

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
            ['role' => 'user', 'content' => $message]
        ],
        'max_tokens' => 800, // Increased from 500
        'temperature' => 0.7,
        'top_p' => 0.9
    ]));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Eroare OpenAI API: HTTP ' . $httpCode);
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        throw new Exception('Eroare OpenAI: ' . $data['error']['message']);
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception('Răspuns invalid de la OpenAI');
    }

    $content = $data['choices'][0]['message']['content'];
    
    // Clean content for TTS
    $cleanContent = cleanForTTS($content);
    
    return $cleanContent;
}

function cleanForTTS($text) {
    // Remove asterisks and markdown formatting
    $text = preg_replace('/\*+/', '', $text);
    
    // Remove numbered lists (1., 2., 3., etc.)
    $text = preg_replace('/^\d+\.\s*/m', '', $text);
    
    // Remove bullet points
    $text = preg_replace('/^[\-\*\+]\s*/m', '', $text);
    
    // Replace multiple spaces with single space
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Remove special characters that TTS reads awkwardly
    $text = preg_replace('/[#@$%^&(){}[\]|\\]/', '', $text);
    
    // Clean up spacing around punctuation
    $text = preg_replace('/\s*([,.!?;:])\s*/', '$1 ', $text);
    
    // Remove parenthetical percentages like (85%)
    $text = preg_replace('/\s*\(\d+%\)\s*/', ' ', $text);
    
    return trim($text);
}

?>

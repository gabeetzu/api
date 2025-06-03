<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate input
    if (!$data || !isset($data['message']) || !isset($data['device_hash'])) {
        throw new Exception('Date lipsă: Mesajul sau hash-ul dispozitivului nu au fost primite');
    }

    $message = $data['message'];
    $deviceHash = $data['device_hash'];

    // Step 1: Check usage limits
    $pdo = new PDO("mysql:host=localhost;dbname=yourdb", "user", "pass");
    $stmt = $pdo->prepare("SELECT count FROM usage WHERE device_hash = ?");
    $stmt->execute([$deviceHash]);
    $usage = $stmt->fetchColumn();
    
    if ($usage >= 5) { // Free tier limit
        throw new Exception('Ați atins limita zilnică de întrebări');
    }

    // Step 2: Get response from OpenAI
    $responseText = getTextResponseFromOpenAI($message);

    echo json_encode([
        'success' => true,
        'response' => $responseText
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getTextResponseFromOpenAI($message) {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        throw new Exception('Cheia OpenAI nu este configurată corect');
    }

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [['role' => 'user', 'content' => $message]],
        'max_tokens' => 500,
        'temperature' => 0.7
    ]));

    $response = curl_exec($ch);
    $data = json_decode($response, true);

    if (isset($data['error'])) {
        throw new Exception('Eroare OpenAI: ' . $data['error']['message']);
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception('Răspuns invalid de la OpenAI');
    }

    return $data['choices'][0]['message']['content'];
}

?>

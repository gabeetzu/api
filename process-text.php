<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enhanced Logging (Essential Info Only)
error_log("=== TEXT PROCESSING START ===");
error_log("API_SECRET_KEY exists: " . (getenv('API_SECRET_KEY') ? 'YES' : 'NO'));
error_log("OPENAI_API_KEY exists: " . (getenv('OPENAI_API_KEY') ? 'YES' : 'NO'));

// API Key Check
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');

if ($apiKey !== $expectedKey) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

// Performance Configuration
ini_set('max_execution_time', '45');
ini_set('memory_limit', '256M');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['message']) || !isset($data['device_hash'])) {
        throw new Exception('Date lipsă: Mesajul sau hash-ul dispozitivului nu au fost primite');
    }

    $message = trim($data['message']);
    $deviceHash = $data['device_hash'];

    // Validate Input
    validateTextInput($message);

    // Anti-Abuse Check
    securityScanText($message);

    // Get AI Response
    $response = getAIResponse($message);

    // Simple Response
    echo json_encode([
        'success' => true,
        'response' => $response
    ]);

} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// -----------------------------------------------
// SIMPLE FUNCTIONS (No DB Tracking)
// -----------------------------------------------

function validateTextInput($message) {
    if (empty($message)) {
        throw new Exception('Mesajul nu poate fi gol');
    }
    if (strlen($message) > 2000) {
        throw new Exception('Mesajul este prea lung');
    }
}

function securityScanText($message) {
    $patterns = [
        '/select.*from/i', '/insert.*into/i', '/update.*set/i', '/delete.*from/i',
        '/<script/i', '/javascript:/i', '/eval\(/i', '/exec\(/i', '/system\(/i'
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, strtolower($message))) {
            throw new Exception('Mesajul conține conținut suspect. Reformulați.');
        }
    }
}

function getAIResponse($message) {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        throw new Exception('Serviciul nu este disponibil');
    }

    $systemPrompt = "Ești un expert în grădinărit din România cu 30 de ani experiență. Răspunzi în română, simplu și practic, între 150-300 de cuvinte.";

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
        'max_tokens' => 500,
        'temperature' => 0.7
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Nu am putut genera răspuns');
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

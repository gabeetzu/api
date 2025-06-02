<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$openai_key = getenv('OPENAI_API_KEY');
$google_key = getenv('GOOGLE_VISION_KEY');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['image']) || !isset($data['device_hash'])) {
        throw new Exception('Missing required data');
    }
    // TEMPORARY: Allow unlimited image uploads
// $canProceed = checkAndUpdateUsage($pdo, $deviceHash);
$canProceed = true;

    echo json_encode([
        'success' => true,
        'treatment' => 'API WORKS! Planta arată sănătoasă!',
        'test' => 'Environment variables loaded successfully'
    ]);
    // Increase upload limits (place at the top)
ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

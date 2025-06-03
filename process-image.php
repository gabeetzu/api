<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// COMPREHENSIVE DEBUG: Log all environment variables and request details
error_log("=== ENHANCED IMAGE PROCESSING DEBUG START ===");
error_log("API_SECRET_KEY exists: " . (getenv('API_SECRET_KEY') ? 'YES' : 'NO'));
error_log("GOOGLE_VISION_KEY exists: " . (getenv('GOOGLE_VISION_KEY') ? 'YES' : 'NO'));
error_log("OPENAI_API_KEY exists: " . (getenv('OPENAI_API_KEY') ? 'YES' : 'NO'));
error_log("DB_HOST: " . getenv('DB_HOST'));
error_log("DB_NAME: " . getenv('DB_NAME'));
error_log("DB_USER: " . getenv('DB_USER'));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
error_log("User agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'not set'));
error_log("X-API-Key header: " . ($_SERVER['HTTP_X_API_KEY'] ?? 'not set'));

$rawInput = file_get_contents('php://input');
error_log("Raw input length: " . strlen($rawInput));
error_log("Raw input preview: " . substr($rawInput, 0, 100) . "...");
error_log("Memory usage before processing: " . memory_get_usage(true) / 1024 / 1024 . " MB");
error_log("=== ENHANCED IMAGE PROCESSING DEBUG END ===");

// Verify API Key with enhanced logging
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');

error_log("API Key comparison - Received: " . substr($apiKey, 0, 10) . "... Expected: " . substr($expectedKey, 0, 10) . "...");

if ($apiKey !== $expectedKey) {
    error_log("API key mismatch - Authentication failed for image request");
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

error_log("API key verified successfully for image request");

// ENHANCED: Configure for S24 Ultra and large images (200MP support)
ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');
ini_set('max_execution_time', '120'); // 10 minutes for large images
ini_set('memory_limit', '256M'); // 1GB for S24 Ultra processing
ini_set('max_input_time', '300');

error_log("PHP settings configured for S24 Ultra image processing");

try {
    error_log("Starting enhanced image processing request...");
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // ENHANCED: Validate input with comprehensive logging
    if (!$data || !isset($data['image']) || !isset($data['device_hash'])) {
        error_log("Invalid image request data - missing required fields");
        error_log("Data keys present: " . (is_array($data) ? implode(', ', array_keys($data)) : 'not an array'));
        throw new Exception('Date lipsă: Imaginea sau hash-ul dispozitivului nu au fost primite');
    }

    $imageBase64 = $data['image'];
    $deviceHash = $data['device_hash'];

    error_log("Processing image from device: $deviceHash");
    error_log("Image base64 length: " . strlen($imageBase64));
    
    // ENHANCED: Calculate actual image size and validate
    $estimatedSizeKB = round((strlen($imageBase64) * 0.75) / 1024, 2);
    $estimatedSizeMB = round($estimatedSizeKB / 1024, 2);
    error_log("Estimated image size: {$estimatedSizeKB} KB ({$estimatedSizeMB} MB)");

    // S24 ULTRA FIX: Validate image size before processing
    if ($estimatedSizeMB > 50) {
        error_log("Image too large: {$estimatedSizeMB} MB - rejecting to prevent crash");
        throw new Exception('Imaginea este prea mare (' . $estimatedSizeMB . ' MB). Te rog să folosești o imagine mai mică de 50 MB.');
    }

    // STEP 1: COMPREHENSIVE IMAGE VALIDATION
    error_log("Step 1: Validating image data...");
    validateImageData($imageBase64);
    
    // STEP 2: SECURITY SCANNING
    error_log("Step 2: Performing security scan...");
    securityScanImage($imageBase64);

    // STEP 3: ANTI-BOT PROTECTION
    error_log("Step 3: Checking rate limits for image request from device: $deviceHash");
    checkRateLimits($deviceHash);
    
    error_log("Step 4: Checking for suspicious image activity...");
    detectSuspiciousImageActivity($deviceHash, $imageBase64);

    // STEP 5: DATABASE CONNECTION
    error_log("Step 5: Attempting database connection for image processing...");
    $pdo = connectToDatabase();
    error_log("Database connection successful for image processing");
    
    // STEP 6: USAGE LIMITS CHECK
    error_log("Step 6: Checking image usage limits...");
    checkUsageLimits($pdo, $deviceHash, 'image');

    // STEP 7: S24 ULTRA IMAGE PREPROCESSING
    error_log("Step 7: Preprocessing image for optimal analysis...");
    $optimizedImageBase64 = preprocessImageForAnalysis($imageBase64);
    error_log("Image preprocessing completed");

    // STEP 8: GOOGLE VISION ANALYSIS
    error_log("Step 8: Starting Google Vision analysis...");
    $visionResults = analyzeWithGoogleVision($optimizedImageBase64);
    error_log("Google Vision analysis completed");
    error_log("Vision results - Objects: " . count($visionResults['objects']) . ", Labels: " . count($visionResults['labels']));

    // STEP 9: VALIDATE VISION RESULTS
    if (empty($visionResults['objects']) && empty($visionResults['labels'])) {
        error_log("Google Vision found no objects or labels in image");
        throw new Exception('Nu am putut identifica plante în această imagine. Încercați o poză mai clară cu planta în prim-plan.');
    }

    // STEP 10: ENHANCED AI TREATMENT ANALYSIS
    error_log("Step 10: Getting enhanced treatment recommendations from OpenAI...");
    $treatment = getTreatmentFromOpenAI($visionResults);
    error_log("OpenAI treatment response received successfully");

    // STEP 11: SAVE TO DATABASE
    error_log("Step 11: Saving image and treatment to chat history...");
    saveChatHistory($pdo, $deviceHash, "", true, 'image', $imageBase64);
    saveChatHistory($pdo, $deviceHash, $treatment, false, 'text', null);

    // STEP 12: RECORD USAGE
    error_log("Step 12: Recording image usage...");
    recordUsage($pdo, $deviceHash, 'image');

    // STEP 13: CLEANUP MEMORY
    unset($imageBase64, $optimizedImageBase64);
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }

    error_log("Enhanced image processing completed successfully");
    error_log("Memory usage after processing: " . memory_get_usage(true) / 1024 / 1024 . " MB");
    
    echo json_encode([
        'success' => true,
        'treatment' => $treatment,
        'vision_results' => $visionResults,
        'processing_info' => [
            'original_size_mb' => $estimatedSizeMB,
            'objects_found' => count($visionResults['objects']),
            'labels_found' => count($visionResults['labels'])
        ]
    ]);

} catch (Exception $e) {
    error_log("ERROR in enhanced process-image.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("Memory usage at error: " . memory_get_usage(true) / 1024 / 1024 . " MB");
    
    // Cleanup on error
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
// COMPREHENSIVE IMAGE VALIDATION FUNCTION
function validateImageData($imageBase64) {
    try {
        error_log("Starting comprehensive image validation...");
        
        // Check if base64 string is valid
        if (empty($imageBase64)) {
            throw new Exception('Imaginea este goală sau coruptă');
        }
        
        // Remove data URL prefix if present (data:image/jpeg;base64,)
        if (strpos($imageBase64, 'data:image') === 0) {
            $imageBase64 = substr($imageBase64, strpos($imageBase64, ',') + 1);
        }
        
        // Validate base64 encoding
        $decodedImage = base64_decode($imageBase64, true);
        if ($decodedImage === false) {
            error_log("Invalid base64 encoding detected");
            throw new Exception('Imaginea nu este codificată corect. Încercați din nou.');
        }
        
        // Check decoded image size
        $decodedSize = strlen($decodedImage);
        $decodedSizeMB = round($decodedSize / 1024 / 1024, 2);
        error_log("Decoded image size: {$decodedSizeMB} MB");
        
        // S24 ULTRA: Reject extremely large images before they crash the system
        if ($decodedSizeMB > 80) {
            error_log("Decoded image too large: {$decodedSizeMB} MB - preventing system crash");
            throw new Exception("Imaginea este prea mare ({$decodedSizeMB} MB). Samsung S24 Ultra face poze foarte mari. Te rog să comprimi imaginea sau să folosești o setare mai mică în cameră.");
        }
        
        // Validate image format using getimagesizefromstring
        $imageInfo = @getimagesizefromstring($decodedImage);
        if ($imageInfo === false) {
            error_log("Invalid image format detected");
            throw new Exception('Formatul imaginii nu este valid. Folosiți JPEG, PNG sau WebP.');
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        error_log("Image validation - Width: {$width}px, Height: {$height}px, Type: {$mimeType}");
        
        // S24 ULTRA: Check for extremely high resolution images
        $megapixels = round(($width * $height) / 1000000, 1);
        error_log("Image resolution: {$megapixels} MP");
        
        if ($megapixels > 150) {
            error_log("Ultra-high resolution detected: {$megapixels} MP - S24 Ultra compatibility mode");
            throw new Exception("Imaginea are o rezoluție foarte mare ({$megapixels} MP). Pentru Samsung S24 Ultra, te rog să folosești o setare mai mică în cameră sau să comprimi imaginea.");
        }
        
        // Validate supported formats
        $supportedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $supportedTypes)) {
            error_log("Unsupported image type: {$mimeType}");
            throw new Exception('Tipul imaginii nu este suportat. Folosiți JPEG, PNG sau WebP.');
        }
        
        // Check for minimum size (too small images are usually invalid)
        if ($width < 50 || $height < 50) {
            error_log("Image too small: {$width}x{$height}");
            throw new Exception('Imaginea este prea mică pentru analiză. Minimum 50x50 pixeli.');
        }
        
        error_log("Image validation passed successfully");
        return true;
        
    } catch (Exception $e) {
        error_log("Image validation failed: " . $e->getMessage());
        throw $e;
    }
}

// SECURITY SCANNING FUNCTION
function securityScanImage($imageBase64) {
    try {
        error_log("Starting security scan of image...");
        
        // Decode image for security analysis
        $decodedImage = base64_decode($imageBase64, true);
        
        // Check for suspicious patterns in binary data
        $suspiciousPatterns = [
            'script',
            'javascript',
            'eval(',
            'exec(',
            'system(',
            '<?php',
            '<%',
            'onload=',
            'onerror='
        ];
        
        $imageHex = bin2hex($decodedImage);
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($imageHex, bin2hex($pattern)) !== false) {
                error_log("Suspicious pattern detected in image: {$pattern}");
                throw new Exception('Imaginea conține conținut suspect. Pentru siguranță, încercați cu o altă imagine.');
            }
        }
        
        // Check for image bomb patterns (malicious images designed to consume resources)
        $imageInfo = @getimagesizefromstring($decodedImage);
        if ($imageInfo) {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $fileSize = strlen($decodedImage);
            
            // Calculate compression ratio
            $expectedSize = $width * $height * 3; // RGB
            $compressionRatio = $expectedSize / $fileSize;
            
            // Extremely high compression ratios can indicate image bombs
            if ($compressionRatio > 1000) {
                error_log("Suspicious compression ratio detected: {$compressionRatio}");
                throw new Exception('Imaginea are o structură suspectă. Încercați cu o altă imagine.');
            }
        }
        
        // Check for excessive EXIF data (can be used to hide malicious content)
        if (function_exists('exif_read_data')) {
            $tempFile = tempnam(sys_get_temp_dir(), 'img_security_');
            file_put_contents($tempFile, $decodedImage);
            
            $exifData = @exif_read_data($tempFile);
            unlink($tempFile);
            
            if ($exifData && count($exifData) > 50) {
                error_log("Excessive EXIF data detected: " . count($exifData) . " fields");
                // Don't throw exception, just log - EXIF data is usually harmless
            }
        }
        
        error_log("Security scan passed successfully");
        return true;
        
    } catch (Exception $e) {
        error_log("Security scan failed: " . $e->getMessage());
        throw $e;
    }
}

// S24 ULTRA PREPROCESSING FUNCTION
function preprocessImageForAnalysis($imageBase64) {
    try {
        error_log("Starting S24 Ultra image preprocessing...");
        
        // Decode the image
        $decodedImage = base64_decode($imageBase64, true);
        $imageInfo = getimagesizefromstring($decodedImage);
        
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        error_log("Original image: {$originalWidth}x{$originalHeight}, Type: {$mimeType}");
        
        // Create image resource based on type
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                $sourceImage = @imagecreatefromstring($decodedImage);
                break;
            case 'image/png':
                $sourceImage = @imagecreatefromstring($decodedImage);
                break;
            case 'image/webp':
                $sourceImage = @imagecreatefromstring($decodedImage);
                break;
            default:
                throw new Exception('Tip de imagine nesuportat pentru procesare');
        }
        
        if (!$sourceImage) {
            error_log("Failed to create image resource from decoded data");
            throw new Exception('Nu am putut procesa imaginea. Încercați cu o altă imagine.');
        }
        
        // S24 ULTRA: Aggressive resizing for ultra-high resolution images
        $maxDimension = 1200; // Increased from 800 for better quality
        $maxFileSize = 800 * 1024; // 800KB target
        
        // Calculate optimal dimensions
        $scale = min(
            $maxDimension / $originalWidth,
            $maxDimension / $originalHeight,
            1.0 // Don't upscale
        );
        
        $newWidth = round($originalWidth * $scale);
        $newHeight = round($originalHeight * $scale);
        
        error_log("Resizing to: {$newWidth}x{$newHeight} (scale: {$scale})");
        
        // Create new image with optimal dimensions
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG
        if ($mimeType === 'image/png') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefill($resizedImage, 0, 0, $transparent);
        }
        
        // High-quality resampling
        imagecopyresampled(
            $resizedImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );
        
        // Apply image enhancement for better plant recognition
        if (function_exists('imagefilter')) {
            // Enhance contrast for better plant detection
            imagefilter($resizedImage, IMG_FILTER_CONTRAST, -10);
            // Slightly increase brightness
            imagefilter($resizedImage, IMG_FILTER_BRIGHTNESS, 5);
            // Sharpen for better detail recognition
            imagefilter($resizedImage, IMG_FILTER_EDGEDETECT);
            imagefilter($resizedImage, IMG_FILTER_CONTRAST, -20);
        }
        
        // Convert back to base64 with optimal quality
        ob_start();
        
        // Try different quality levels to meet size target
        $quality = 85;
        do {
            ob_clean();
            imagejpeg($resizedImage, null, $quality);
            $outputSize = ob_get_length();
            $quality -= 5;
        } while ($outputSize > $maxFileSize && $quality > 30);
        
        $optimizedImageData = ob_get_contents();
        ob_end_clean();
        
        // Cleanup memory
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        
        $finalSize = strlen($optimizedImageData);
        $finalSizeKB = round($finalSize / 1024, 2);
        
        error_log("Image preprocessing completed: {$finalSizeKB} KB (quality: " . ($quality + 5) . ")");
        
        return base64_encode($optimizedImageData);
        
    } catch (Exception $e) {
        error_log("Image preprocessing failed: " . $e->getMessage());
        // If preprocessing fails, return original (but this might cause issues with large images)
        error_log("Falling back to original image");
        return $imageBase64;
    }
}
// ENHANCED GOOGLE VISION FUNCTION with S24 Ultra optimization
function analyzeWithGoogleVision($imageBase64) {
    $googleVisionKey = getenv('GOOGLE_VISION_KEY');
    if (!$googleVisionKey) {
        error_log("Google Vision API key not found in environment variables");
        throw new Exception('Serviciul de analiză imagini nu este disponibil momentan');
    }

    error_log("Starting enhanced Google Vision analysis...");
    error_log("Google Vision API key exists: YES (length: " . strlen($googleVisionKey) . ")");
    error_log("Image base64 length for Vision API: " . strlen($imageBase64));

    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . $googleVisionKey;

    // ENHANCED: Multiple detection types for comprehensive plant analysis
    $requestData = [
        'requests' => [[
            'image' => ['content' => $imageBase64],
            'features' => [
                ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 20],
                ['type' => 'LABEL_DETECTION', 'maxResults' => 20],
                ['type' => 'TEXT_DETECTION', 'maxResults' => 10], // For plant labels/signs
                ['type' => 'CROP_HINTS', 'maxResults' => 5], // For better framing
                ['type' => 'IMAGE_PROPERTIES'] // For color analysis
            ],
            'imageContext' => [
                'cropHintsParams' => [
                    'aspectRatios' => [1.0, 1.77, 0.56] // Common aspect ratios
                ],
                'languageHints' => ['ro', 'en'] // Romanian and English
            ]
        ]]
    ];

    error_log("Sending enhanced request to Google Vision API...");

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: GospodApp/1.0 (Romanian Gardening Assistant)'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout for large images
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    // Enhanced error logging
    error_log("Google Vision response code: $httpCode");
    if ($httpCode !== 200) {
        error_log("Google Vision error response: " . substr($response, 0, 1000));
        if ($curlError) {
            error_log("cURL error for Google Vision: " . $curlError);
        }
    } else {
        error_log("Google Vision response received successfully (length: " . strlen($response) . ")");
    }
    
    curl_close($ch);

    // Handle different error scenarios
    if ($curlError) {
        throw new Exception('Eroare de conexiune la serviciul de analiză imagini. Verificați conexiunea la internet.');
    }

    if ($httpCode === 429) {
        throw new Exception('Prea multe cereri către serviciul de analiză. Încercați din nou în câteva minute.');
    }

    if ($httpCode === 403) {
        throw new Exception('Serviciul de analiză imagini este temporar restricționat. Încercați din nou mai târziu.');
    }

    if ($httpCode !== 200) {
        throw new Exception('Serviciul de analiză imagini este temporar indisponibil. Încercați din nou.');
    }

    $result = json_decode($response, true);

    if (isset($result['error'])) {
        error_log("Google Vision API error: " . json_encode($result['error']));
        $errorMessage = $result['error']['message'] ?? 'Eroare necunoscută';
        
        if (strpos($errorMessage, 'quota') !== false) {
            throw new Exception('Serviciul de analiză a atins limita zilnică. Încercați mâine.');
        }
        
        throw new Exception('Eroare la analiza imaginii: ' . $errorMessage);
    }

    // ENHANCED: Extract comprehensive data
    $objects = [];
    $labels = [];
    $texts = [];
    $colors = [];
    $cropHints = [];

    $responseData = $result['responses'][0] ?? [];

    // Extract objects
    if (isset($responseData['localizedObjectAnnotations'])) {
        foreach ($responseData['localizedObjectAnnotations'] as $obj) {
            $objects[] = [
                'name' => $obj['name'],
                'score' => $obj['score'] ?? 0,
                'boundingBox' => $obj['boundingPoly'] ?? null
            ];
        }
        error_log("Google Vision found " . count($objects) . " objects: " . implode(', ', array_column($objects, 'name')));
    }

    // Extract labels
    if (isset($responseData['labelAnnotations'])) {
        foreach ($responseData['labelAnnotations'] as $label) {
            $labels[] = [
                'description' => $label['description'],
                'score' => $label['score'] ?? 0,
                'confidence' => $label['confidence'] ?? 0
            ];
        }
        error_log("Google Vision found " . count($labels) . " labels: " . implode(', ', array_column($labels, 'description')));
    }

    // Extract text (useful for plant labels, garden signs)
    if (isset($responseData['textAnnotations'])) {
        foreach ($responseData['textAnnotations'] as $text) {
            if (strlen($text['description']) > 2) { // Filter out single characters
                $texts[] = $text['description'];
            }
        }
        error_log("Google Vision found " . count($texts) . " text elements");
    }

    // Extract dominant colors
    if (isset($responseData['imagePropertiesAnnotation']['dominantColors']['colors'])) {
        foreach ($responseData['imagePropertiesAnnotation']['dominantColors']['colors'] as $colorInfo) {
            $color = $colorInfo['color'];
            $colors[] = [
                'red' => $color['red'] ?? 0,
                'green' => $color['green'] ?? 0,
                'blue' => $color['blue'] ?? 0,
                'score' => $colorInfo['score'] ?? 0
            ];
        }
        error_log("Google Vision found " . count($colors) . " dominant colors");
    }

    // Extract crop hints
    if (isset($responseData['cropHintsAnnotation']['cropHints'])) {
        foreach ($responseData['cropHintsAnnotation']['cropHints'] as $hint) {
            $cropHints[] = $hint['boundingPoly'] ?? null;
        }
        error_log("Google Vision found " . count($cropHints) . " crop hints");
    }

    error_log("Enhanced Google Vision analysis completed successfully");

    return [
        'objects' => $objects,
        'labels' => $labels,
        'texts' => $texts,
        'colors' => $colors,
        'cropHints' => $cropHints
    ];
}

// ENHANCED OPENAI TREATMENT FUNCTION with comprehensive plant analysis
function getTreatmentFromOpenAI($visionResults) {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        error_log("OpenAI API key not found in environment variables for image analysis");
        throw new Exception('Serviciul de analiză nu este disponibil momentan');
    }

    // Prepare comprehensive data for analysis
    $objects = array_column($visionResults['objects'], 'name');
    $labels = array_column($visionResults['labels'], 'description');
    $texts = $visionResults['texts'];
    $colors = $visionResults['colors'];

    error_log("Creating enhanced OpenAI prompt for comprehensive plant analysis...");
    error_log("Objects for analysis: " . implode(', ', $objects));
    error_log("Labels for analysis: " . implode(', ', $labels));
    error_log("Text elements found: " . count($texts));
    error_log("Color palette size: " . count($colors));

    // Analyze dominant colors for plant health
    $colorAnalysis = "";
    if (!empty($colors)) {
        $dominantColor = $colors[0];
        $greenLevel = $dominantColor['green'] ?? 0;
        $redLevel = $dominantColor['red'] ?? 0;
        $blueLevel = $dominantColor['blue'] ?? 0;
        
        $colorAnalysis = "Culorile dominante: Verde({$greenLevel}), Roșu({$redLevel}), Albastru({$blueLevel}). ";
        
        if ($greenLevel < 100 && $redLevel > 150) {
            $colorAnalysis .= "Posibile semne de stres sau boală (culori roșiatice). ";
        } elseif ($greenLevel > 150) {
            $colorAnalysis .= "Planta pare sănătoasă (verde intens). ";
        }
    }

    $systemPrompt = "Ești un expert în grădinărit din România cu 30 de ani experiență, specializat în diagnosticarea plantelor prin imagini.

EXPERTIZA TA AVANSATĂ:
- Identifici specii de plante românești și internaționale
- Diagnostichezi boli, dăunători și deficiențe nutriționale
- Analizezi sănătatea plantelor prin culoare, formă și aspect
- Cunoști tratamentele disponibile în România
- Înțelegi specificul climei continentale românești

REGULI PENTRU ANALIZA IMAGINILOR:
- Analizezi toate datele: obiecte, etichete, text și culori
- Identifici tipul exact de plantă dacă este posibil
- Evaluezi starea de sănătate și problemele vizibile
- Dai sfaturi concrete și practice pentru clima României
- Menționezi produse și tratamente disponibile local
- Explici când și cum să aplici tratamentele
- Folosești termeni simpli, fără formatare specială
- Răspunsurile să fie între 200-500 de cuvinte, detaliate și utile

STRUCTURA RĂSPUNSULUI:
1. Identificarea plantei
2. Evaluarea stării de sănătate
3. Probleme identificate (dacă există)
4. Tratamente și soluții concrete
5. Sfaturi de îngrijire pe termen lung";

    // Create comprehensive prompt with all available data
    $prompt = "Analizează această imagine de grădină folosind toate datele disponibile:

OBIECTE DETECTATE: " . implode(', ', $objects) . "
ETICHETE IDENTIFICATE: " . implode(', ', $labels) . "
TEXT GĂSIT ÎN IMAGINE: " . implode(', ', $texts) . "
ANALIZA CULORILOR: " . $colorAnalysis . "

Te rog să îmi oferi o analiză completă:
1. Ce tip de plantă/plante vezi și cum le identifici
2. Evaluarea stării de sănătate bazată pe culori și aspect
3. Probleme vizibile (boli, dăunători, deficiențe)
4. Tratamente concrete disponibile în România
5. Sfaturi de îngrijire pentru clima noastră
6. Când și cum să aplici tratamentele recomandate

Dacă ai identificat text în imagine (etichete, semne), folosește aceste informații pentru o analiză mai precisă.";

    error_log("Sending enhanced request to OpenAI for comprehensive plant analysis...");

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey,
        'User-Agent: GospodApp/1.0 (Romanian Gardening Assistant)'
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 1500, // Increased for comprehensive analysis
        'temperature' => 0.7,
        'top_p' => 0.9
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    // Enhanced error logging
    error_log("OpenAI enhanced analysis response code: $httpCode");
    if ($httpCode !== 200) {
        error_log("OpenAI enhanced analysis error response: " . substr($response, 0, 1000));
        if ($curlError) {
            error_log("cURL error for OpenAI enhanced analysis: " . $curlError);
        }
    } else {
        error_log("OpenAI enhanced analysis response received successfully (length: " . strlen($response) . ")");
    }
    
    curl_close($ch);

    // Handle different error scenarios
    if ($curlError) {
        throw new Exception('Eroare de conexiune la serviciul de analiză AI. Verificați conexiunea la internet.');
    }

    if ($httpCode === 429) {
        throw new Exception('Serviciul de analiză AI este temporar suprasolicitat. Încercați din nou în câteva minute.');
    }

    if ($httpCode === 401) {
        throw new Exception('Serviciul de analiză AI are probleme de autentificare. Încercați din nou mai târziu.');
    }

    if ($httpCode !== 200) {
        throw new Exception('Nu am putut analiza imaginea momentan. Încercați din nou.');
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        error_log("OpenAI enhanced analysis API error: " . json_encode($data['error']));
        $errorType = $data['error']['type'] ?? 'unknown';
        
        if ($errorType === 'insufficient_quota') {
            throw new Exception('Serviciul de analiză AI a atins limita zilnică. Încercați mâine.');
        }
        
        throw new Exception('Nu am putut analiza imaginea momentan. Încercați din nou.');
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        error_log("OpenAI enhanced analysis response missing content: " . json_encode($data));
        throw new Exception('Analiza imaginii a eșuat. Încercați cu o altă poză.');
    }

    $content = $data['choices'][0]['message']['content'];
    error_log("OpenAI enhanced analysis content length: " . strlen($content));
    
    return cleanForTTS($content);
}
// ENHANCED DATABASE CONNECTION with detailed error logging for image processing
function connectToDatabase() {
    try {
        $host = getenv('DB_HOST');
        $dbname = getenv('DB_NAME');
        $username = getenv('DB_USER');
        $password = getenv('DB_PASS');
        
        // DEBUG: Log database connection attempt (without password)
        error_log("Image processing - Attempting DB connection to: $host");
        error_log("Image processing - Database name: $dbname");
        error_log("Image processing - Username: $username");
        error_log("Image processing - Password exists: " . (getenv('DB_PASS') ? 'YES' : 'NO'));
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 15, // Increased timeout for image processing
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        
        error_log("Image processing - Database connection successful");
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Image processing - Database connection failed: " . $e->getMessage());
        error_log("Image processing - PDO Error Code: " . $e->getCode());
        throw new Exception('Nu pot conecta la baza de date momentan. Încercați din nou.');
    }
}

// ENHANCED USAGE LIMITS CHECK for image processing
function checkUsageLimits($pdo, $deviceHash, $type) {
    try {
        $today = date('Y-m-d');
        error_log("Image processing - Checking usage limits for device: $deviceHash on date: $today");
        
        $stmt = $pdo->prepare("
            SELECT text_count, image_count, premium, extra_questions 
            FROM usage_tracking 
            WHERE device_hash = ? AND date = ?
        ");
        $stmt->execute([$deviceHash, $today]);
        $usage = $stmt->fetch();

        if (!$usage) {
            error_log("Image processing - No usage record found, creating new entry for device: $deviceHash");
            $stmt = $pdo->prepare("
                INSERT INTO usage_tracking (device_hash, date, text_count, image_count, premium, extra_questions) 
                VALUES (?, ?, 0, 0, 0, 0)
            ");
            $stmt->execute([$deviceHash, $today]);
            $usage = ['text_count' => 0, 'image_count' => 0, 'premium' => 0, 'extra_questions' => 0];
            error_log("Image processing - New usage record created successfully");
        } else {
            error_log("Image processing - Found existing usage record: " . json_encode($usage));
        }

        if ($type === 'image') {
            $limit = $usage['premium'] ? 5 : 1;
            $remaining = $limit - $usage['image_count'];
            
            error_log("Image usage check - Used: {$usage['image_count']}, Limit: $limit, Remaining: $remaining");
            
            if ($usage['image_count'] >= $limit) {
                if ($usage['premium']) {
                    error_log("Premium user hit daily image limit");
                    throw new Exception('Ați atins limita zilnică de 5 imagini premium. Reveniți mâine pentru analize noi!');
                } else {
                    error_log("Free user hit daily image limit");
                    throw new Exception('Ați folosit analiza gratuită de astăzi! Upgradeați la Premium pentru 5 analize zilnice sau urmăriți o reclamă pentru o analiză extra.');
                }
            }
        }
        
    } catch (PDOException $e) {
        error_log("Image processing - Database error in checkUsageLimits: " . $e->getMessage());
        throw new Exception('Eroare temporară la verificarea limitelor. Încercați din nou.');
    }
}

// ENHANCED RECORD USAGE for image processing
function recordUsage($pdo, $deviceHash, $type) {
    try {
        $today = date('Y-m-d');
        $field = $type === 'image' ? 'image_count' : 'text_count';
        
        error_log("Image processing - Recording usage for device: $deviceHash, type: $type");
        
        $stmt = $pdo->prepare("
            UPDATE usage_tracking 
            SET $field = $field + 1 
            WHERE device_hash = ? AND date = ?
        ");
        $result = $stmt->execute([$deviceHash, $today]);
        
        if ($result) {
            error_log("Image processing - Usage recorded successfully");
        } else {
            error_log("Image processing - Failed to record usage");
        }
        
    } catch (PDOException $e) {
        error_log("Image processing - Database error in recordUsage: " . $e->getMessage());
        throw new Exception('Eroare la înregistrarea utilizării.');
    }
}

// ENHANCED SAVE CHAT HISTORY for image processing
function saveChatHistory($pdo, $deviceHash, $messageText, $isUserMessage, $messageType, $imageData) {
    try {
        error_log("Image processing - Saving chat history for device: $deviceHash, type: $messageType, user: " . ($isUserMessage ? 'YES' : 'NO'));
        
        // For large images, store only a compressed version or reference
        if ($messageType === 'image' && $imageData && strlen($imageData) > 1000000) {
            error_log("Image processing - Large image detected, storing compressed version");
            // Store only first 100KB of base64 for reference
            $imageData = substr($imageData, 0, 100000) . '...[TRUNCATED]';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO chat_history (device_hash, message_text, is_user_message, message_type, image_data) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $deviceHash,
            $messageText,
            $isUserMessage ? 1 : 0,
            $messageType,
            $imageData
        ]);
        
        if ($result) {
            error_log("Image processing - Chat history saved successfully");
        } else {
            error_log("Image processing - Failed to save chat history");
        }
        
    } catch (PDOException $e) {
        error_log("Image processing - Database error in saveChatHistory: " . $e->getMessage());
        // Don't throw exception here - chat history is not critical for image processing
        error_log("Image processing - Continuing despite chat history save failure");
    }
}

// ENHANCED ANTI-BOT PROTECTION FUNCTIONS for Image Processing
function checkRateLimits($deviceHash) {
    try {
        $rateLimitFile = '/tmp/rate_limit_image_' . $deviceHash . '.txt';
        $currentTime = time();
        
        error_log("Image processing - Checking rate limits for device: $deviceHash");
        
        // Clean old entries
        if (file_exists($rateLimitFile)) {
            $requests = json_decode(file_get_contents($rateLimitFile), true) ?: [];
            $requests = array_filter($requests, function($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < 300; // Keep only last 5 minutes for images
            });
            error_log("Image processing - Found " . count($requests) . " requests in last 5 minutes");
        } else {
            $requests = [];
            error_log("Image processing - No previous rate limit file found");
        }
        
        // Check if limit exceeded (3 image requests per 5 minutes)
        if (count($requests) >= 3) {
            error_log("Image processing - Rate limit exceeded for device: $deviceHash");
            throw new Exception('Prea multe analize de imagini. Încercați din nou în 5 minute.');
        }
        
        // Add current request
        $requests[] = $currentTime;
        file_put_contents($rateLimitFile, json_encode($requests));
        error_log("Image processing - Rate limit check passed, requests in last 5 min: " . count($requests));
        
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Prea multe analize') !== false) {
            throw $e; // Re-throw rate limit exceptions
        }
        error_log("Image processing - Error in rate limit check: " . $e->getMessage());
        // Continue execution if file operations fail
    }
}

function detectSuspiciousImageActivity($deviceHash, $imageBase64) {
    try {
        $imageHash = md5($imageBase64);
        $suspiciousFile = '/tmp/suspicious_image_' . $deviceHash . '_' . $imageHash . '.txt';
        $currentTime = time();
        
        error_log("Image processing - Checking suspicious activity for device: $deviceHash");
        
        // Check for repeated identical images
        if (file_exists($suspiciousFile)) {
            $data = json_decode(file_get_contents($suspiciousFile), true);
            $count = $data['count'] ?? 0;
            $lastTime = $data['last_time'] ?? 0;
            
            // Reset count if more than 1 hour passed
            if (($currentTime - $lastTime) > 3600) {
                $count = 0;
            }
            
            $count++;
            
            if ($count > 2) {
                error_log("Image processing - Suspicious activity detected - repeated image from device: $deviceHash");
                throw new Exception('Aceeași imagine trimisă prea des. Încercați cu o imagine diferită.');
            }
            
            file_put_contents($suspiciousFile, json_encode([
                'count' => $count,
                'last_time' => $currentTime
            ]));
        } else {
            file_put_contents($suspiciousFile, json_encode([
                'count' => 1,
                'last_time' => $currentTime
            ]));
        }
        
        error_log("Image processing - Suspicious activity check passed for device: $deviceHash");
        
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Aceeași imagine') !== false) {
            throw $e; // Re-throw suspicious activity exceptions
        }
        error_log("Image processing - Error in suspicious activity check: " . $e->getMessage());
        // Continue execution if file operations fail
    }
}

// ENHANCED TEXT CLEANING FUNCTION with null safety
function cleanForTTS($text) {
    // Handle null input
    if ($text === null || $text === '') {
        return '';
    }
    
    // Convert to string if not already
    $text = (string) $text;
    
    // Clean text for TTS - FIXED regex patterns
    $text = preg_replace('/\*+/', '', $text);                    // Remove asterisks
    $text = preg_replace('/^\d+\.\s*/m', '', $text);            // Remove numbered lists
    $text = preg_replace('/^[\-\*\+]\s*/m', '', $text);         // Remove bullet points
    $text = preg_replace('/\s+/', ' ', $text);                  // Multiple spaces to single
    $text = preg_replace('/[#@$%^&(){}|\\\\]/', '', $text);     // FIXED: Escaped brackets properly
    $text = preg_replace('/\s*([,.!?;:])\s*/', '$1 ', $text);   // Fix punctuation spacing
    $text = preg_replace('/\s*\(\d+%\)\s*/', ' ', $text);       // Remove percentages
    
    return trim($text);
}

// MEMORY CLEANUP FUNCTION for large image processing
function cleanupMemory() {
    // Force garbage collection
    if (function_exists('gc_collect_cycles')) {
        $collected = gc_collect_cycles();
        error_log("Image processing - Garbage collection freed $collected cycles");
    }
    
    // Clear any temporary files older than 1 hour
    $tempDir = sys_get_temp_dir();
    $files = glob($tempDir . '/rate_limit_image_*.txt');
    $files = array_merge($files, glob($tempDir . '/suspicious_image_*.txt'));
    
    $currentTime = time();
    $cleaned = 0;
    
    foreach ($files as $file) {
        if (file_exists($file) && ($currentTime - filemtime($file)) > 3600) {
            unlink($file);
            $cleaned++;
        }
    }
    
    if ($cleaned > 0) {
        error_log("Image processing - Cleaned up $cleaned temporary files");
    }
}

?>

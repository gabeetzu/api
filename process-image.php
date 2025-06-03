<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// COMPREHENSIVE DEBUG: Enhanced logging for enterprise image processing
error_log("=== ENTERPRISE IMAGE PROCESSING START ===");
error_log("Timestamp: " . date('Y-m-d H:i:s'));
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
error_log("Client IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

$rawInput = file_get_contents('php://input');
error_log("Raw input length: " . strlen($rawInput));
error_log("Raw input preview: " . substr($rawInput, 0, 100) . "...");
error_log("Memory usage before processing: " . memory_get_usage(true) / 1024 / 1024 . " MB");
error_log("Server load: " . (function_exists('sys_getloadavg') ? implode(', ', sys_getloadavg()) : 'N/A'));
error_log("=== ENTERPRISE IMAGE PROCESSING DEBUG END ===");

// Verify API Key with enhanced logging
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');

error_log("API Key verification - Received length: " . strlen($apiKey) . ", Expected length: " . strlen($expectedKey));

if ($apiKey !== $expectedKey) {
    error_log("API key mismatch - Authentication failed for enterprise image request");
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

error_log("API key verified successfully for enterprise image request");

// ENTERPRISE: Configure for S24 Ultra and large images (Starter plan optimized)
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('max_execution_time', '90');    // Optimized for Starter plan
ini_set('memory_limit', '512M');        // Sufficient for image processing
ini_set('max_input_time', '60');

error_log("PHP settings configured for enterprise S24 Ultra image processing");

try {
    error_log("Starting enterprise image processing pipeline...");
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // ENHANCED: Comprehensive input validation
    if (!$data || !isset($data['image']) || !isset($data['device_hash'])) {
        error_log("Invalid enterprise image request - missing required fields");
        error_log("Data keys present: " . (is_array($data) ? implode(', ', array_keys($data)) : 'not an array'));
        throw new Exception('Date lipsă: Imaginea sau hash-ul dispozitivului nu au fost primite');
    }

    $imageBase64 = $data['image'];
    $deviceHash = $data['device_hash'];

    error_log("Processing enterprise image from device: $deviceHash");
    error_log("Image base64 length: " . strlen($imageBase64));
    
    // Calculate actual image size
    $estimatedSizeKB = round((strlen($imageBase64) * 0.75) / 1024, 2);
    $estimatedSizeMB = round($estimatedSizeKB / 1024, 2);
    error_log("Estimated image size: {$estimatedSizeKB} KB ({$estimatedSizeMB} MB)");

    // ENTERPRISE PIPELINE - Step 1: Advanced Image Validation
    error_log("STEP 1: Enterprise image validation and S24 Ultra compatibility check...");
    validateImageDataEnterprise($imageBase64, $estimatedSizeMB);
    
    // ENTERPRISE PIPELINE - Step 2: Security Scanning
    error_log("STEP 2: Enterprise image security scanning...");
    performImageSecurityScanEnterprise($imageBase64);

    // ENTERPRISE PIPELINE - Step 3: Anti-Bot Protection
    error_log("STEP 3: Enterprise anti-bot protection for images...");
    checkImageRateLimitsEnterprise($deviceHash);
    detectSuspiciousImageActivityEnterprise($deviceHash, $imageBase64);

    // ENTERPRISE PIPELINE - Step 4: Database Connection
    error_log("STEP 4: Enterprise database connection...");
    $pdo = connectToDatabaseEnterprise();
    error_log("Enterprise database connection successful");
    
    // ENTERPRISE PIPELINE - Step 5: Usage Limits Check
    error_log("STEP 5: Enterprise image usage limits verification...");
    checkUsageLimitsEnterprise($pdo, $deviceHash, 'image');

    // ENTERPRISE PIPELINE - Step 6: Image Cache Check
    error_log("STEP 6: Enterprise image cache check...");
    $cachedTreatment = getCachedImageTreatmentEnterprise($imageBase64);
    if ($cachedTreatment) {
        error_log("Enterprise image cache hit - returning optimized cached treatment");
        
        // Record usage and save to history
        saveChatHistoryEnterprise($pdo, $deviceHash, "", true, 'image', $imageBase64);
        saveChatHistoryEnterprise($pdo, $deviceHash, $cachedTreatment, false, 'text', null);
        recordUsageEnterprise($pdo, $deviceHash, 'image');
        
        // SIMPLE response format for Android compatibility
        echo json_encode([
            'success' => true,
            'treatment' => $cachedTreatment
        ]);
        exit;
    }

    // ENTERPRISE PIPELINE - Step 7: User Context Retrieval
    error_log("STEP 7: Enterprise user context retrieval for personalized image analysis...");
    $userContext = getUserContextEnterprise($pdo, $deviceHash);
    error_log("User context for image analysis: experience=" . $userContext['experience_level'] . ", garden=" . $userContext['garden_type']);

    // ENTERPRISE PIPELINE - Step 8: S24 Ultra Image Preprocessing
    error_log("STEP 8: Enterprise S24 Ultra image preprocessing and optimization...");
    sendProgressUpdate('preprocessing', 'Optimizez imaginea...');
    $optimizedImageBase64 = preprocessImageForAnalysisEnterprise($imageBase64, $estimatedSizeMB);
    error_log("Enterprise image preprocessing completed");

    // ENTERPRISE PIPELINE - Step 9: Enhanced Google Vision Analysis
    error_log("STEP 9: Enterprise Google Vision analysis with advanced detection...");
    sendProgressUpdate('vision_analysis', 'Analizez imaginea cu AI...');
    $visionResults = analyzeWithGoogleVisionEnterprise($optimizedImageBase64);
    error_log("Enterprise Google Vision analysis completed");
    error_log("Vision results - Objects: " . count($visionResults['objects']) . ", Labels: " . count($visionResults['labels']));

    // ENTERPRISE PIPELINE - Step 10: Vision Results Validation
    if (empty($visionResults['objects']) && empty($visionResults['labels'])) {
        error_log("Enterprise Google Vision found no objects or labels in image");
        throw new Exception('Nu am putut identifica plante în această imagine. Încercați o poză mai clară cu planta în prim-plan.');
    }

    // ENTERPRISE PIPELINE - Step 11: Content Analysis for Images
    error_log("STEP 11: Enterprise image content analysis and classification...");
    sendProgressUpdate('content_analysis', 'Identific planta...');
    $imageAnalysis = analyzeImageContentEnterprise($visionResults, $userContext);
    error_log("Enterprise image analysis result: " . json_encode($imageAnalysis));

    // ENTERPRISE PIPELINE - Step 12: Enhanced AI Treatment Analysis
    error_log("STEP 12: Enterprise AI treatment analysis with context awareness...");
    sendProgressUpdate('ai_treatment', 'Creez recomandările...');
    $treatment = getEnhancedImageTreatmentEnterprise($visionResults, $imageAnalysis, $userContext);
    error_log("Enterprise AI treatment response generated successfully (length: " . strlen($treatment) . ")");

    // ENTERPRISE PIPELINE - Step 13: Treatment Caching
    error_log("STEP 13: Caching enterprise image treatment...");
    cacheImageTreatmentEnterprise($imageBase64, $treatment);

    // ENTERPRISE PIPELINE - Step 14: Data Persistence
    error_log("STEP 14: Enterprise image data persistence...");
    saveChatHistoryEnterprise($pdo, $deviceHash, "", true, 'image', $imageBase64);
    saveChatHistoryEnterprise($pdo, $deviceHash, $treatment, false, 'text', null);

    // ENTERPRISE PIPELINE - Step 15: Usage Recording
    error_log("STEP 15: Enterprise image usage recording...");
    recordUsageEnterprise($pdo, $deviceHash, 'image');

    // ENTERPRISE PIPELINE - Step 16: Analytics (Async)
    error_log("STEP 16: Enterprise image analytics tracking...");
    trackImageEngagementEnterprise($pdo, $deviceHash, $imageAnalysis, $treatment, $visionResults);

    // ENTERPRISE PIPELINE - Step 17: User Context Update
    error_log("STEP 17: Enterprise user context update with image insights...");
    updateUserContextWithImageEnterprise($pdo, $deviceHash, $imageAnalysis);

    // ENTERPRISE PIPELINE - Step 18: Memory Cleanup
    unset($imageBase64, $optimizedImageBase64);
    if (function_exists('gc_collect_cycles')) {
        $collected = gc_collect_cycles();
        error_log("Enterprise image memory cleanup: $collected cycles collected");
    }

    error_log("Enterprise image processing completed successfully");
    error_log("Final memory usage: " . memory_get_usage(true) / 1024 / 1024 . " MB");
    error_log("Total processing time: " . round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . "ms");
    
    // SIMPLE response format (Android compatible)
    echo json_encode([
        'success' => true,
        'treatment' => $treatment
    ]);

} catch (Exception $e) {
    error_log("ENTERPRISE ERROR in process-image.php: " . $e->getMessage());
    error_log("Error file: " . $e->getFile() . " Line: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("Memory usage at error: " . memory_get_usage(true) / 1024 / 1024 . " MB");
    
    // Enterprise error cleanup
    unset($imageBase64, $optimizedImageBase64);
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
    
    // Enhanced error categorization
    $errorCategory = categorizeImageErrorEnterprise($e->getMessage());
    error_log("Enterprise image error category: " . $errorCategory);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function sendProgressUpdate($phase, $message) {
    // Only send if not cached response
    static $updatesSent = 0;
    $updatesSent++;
    
    // Limit updates to prevent spam
    if ($updatesSent > 5) return;
    
    $progressData = [
        'success' => true,
        'status' => 'processing',
        'phase' => $phase,
        'message' => $message,
        'timestamp' => time(),
        'step' => $updatesSent
    ];
    
    // Send progress update (non-blocking)
    if (function_exists('fastcgi_finish_request')) {
        echo json_encode($progressData) . "\n";
        fastcgi_finish_request();
    }
    
    error_log("Progress update sent: $phase - $message");
}

function getPhaseMessage($phase) {
    $messages = [
        'validating' => 'Validez mesajul...',
        'analyzing' => 'Analizez conținutul...',
        'thinking' => 'Mă gândesc la răspuns...',
        'writing' => 'Scriu răspunsul...',
        'preprocessing' => 'Optimizez imaginea...',
        'vision_analysis' => 'Analizez imaginea cu AI...',
        'content_analysis' => 'Identific planta...',
        'ai_treatment' => 'Creez recomandările...',
        'caching' => 'Salvez pentru viitor...',
        'finalizing' => 'Finalizez răspunsul...'
    ];
    
    return $messages[$phase] ?? 'Procesez...';
}


// ENTERPRISE IMAGE VALIDATION FUNCTION with S24 Ultra support
function validateImageDataEnterprise($imageBase64, $estimatedSizeMB) {
    try {
        error_log("Starting enterprise image validation with S24 Ultra support...");
        
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
            error_log("Enterprise validation: Invalid base64 encoding detected");
            throw new Exception('Imaginea nu este codificată corect. Încercați din nou cu o imagine validă.');
        }
        
        // Check decoded image size
        $decodedSize = strlen($decodedImage);
        $decodedSizeMB = round($decodedSize / 1024 / 1024, 2);
        error_log("Enterprise validation: Decoded image size: {$decodedSizeMB} MB");
        
        // S24 ULTRA: Enterprise-grade size validation
        if ($decodedSizeMB > 40) {
            error_log("Enterprise validation: Image too large for processing: {$decodedSizeMB} MB");
            throw new Exception("Imaginea este prea mare ({$decodedSizeMB} MB). Samsung S24 Ultra face poze foarte mari. Te rog să comprimi imaginea sau să folosești o setare mai mică în cameră (maxim 40 MB).");
        }
        
        // Validate image format using getimagesizefromstring
        $imageInfo = @getimagesizefromstring($decodedImage);
        if ($imageInfo === false) {
            error_log("Enterprise validation: Invalid image format detected");
            throw new Exception('Formatul imaginii nu este valid. Folosiți JPEG, PNG sau WebP pentru analiză optimă.');
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        error_log("Enterprise validation - Width: {$width}px, Height: {$height}px, Type: {$mimeType}");
        
        // S24 ULTRA: Enterprise resolution handling
        $megapixels = round(($width * $height) / 1000000, 1);
        error_log("Enterprise validation: Image resolution: {$megapixels} MP");
        
        if ($megapixels > 100) {
            error_log("Enterprise validation: Ultra-high resolution detected: {$megapixels} MP");
            // Don't throw exception, just log for enterprise processing
            error_log("Enterprise mode: Will optimize ultra-high resolution image for processing");
        }
        
        // Validate supported formats
        $supportedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $supportedTypes)) {
            error_log("Enterprise validation: Unsupported image type: {$mimeType}");
            throw new Exception('Tipul imaginii nu este suportat. Folosiți JPEG, PNG sau WebP pentru cea mai bună analiză.');
        }
        
        // Check for minimum size (too small images are usually invalid)
        if ($width < 100 || $height < 100) {
            error_log("Enterprise validation: Image too small: {$width}x{$height}");
            throw new Exception('Imaginea este prea mică pentru analiză detaliată. Minimum 100x100 pixeli pentru identificarea plantelor.');
        }
        
        // Enterprise aspect ratio validation
        $aspectRatio = $width / $height;
        if ($aspectRatio > 10 || $aspectRatio < 0.1) {
            error_log("Enterprise validation: Unusual aspect ratio: $aspectRatio");
            throw new Exception('Imaginea are proporții neobișnuite. Folosiți o imagine cu proporții normale pentru analiză optimă.');
        }
        
        error_log("Enterprise image validation passed successfully");
        return true;
        
    } catch (Exception $e) {
        error_log("Enterprise image validation failed: " . $e->getMessage());
        throw $e;
    }
}

// ENTERPRISE IMAGE SECURITY SCANNING FUNCTION
function performImageSecurityScanEnterprise($imageBase64) {
    try {
        error_log("Starting enterprise image security scan...");
        
        // Decode image for security analysis
        $decodedImage = base64_decode($imageBase64, true);
        
        // Enterprise security: Check for embedded malicious content
        $suspiciousPatterns = [
            'script',
            'javascript',
            'eval(',
            'exec(',
            'system(',
            '<?php',
            '<%',
            'onload=',
            'onerror=',
            'document.cookie',
            'window.location'
        ];
        
        $imageHex = bin2hex($decodedImage);
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($imageHex, bin2hex($pattern)) !== false) {
                error_log("Enterprise security: Malicious pattern detected in image: {$pattern}");
                throw new Exception('Imaginea conține conținut suspect. Pentru siguranță, încercați cu o altă imagine de plantă.');
            }
        }
        
        // Enterprise security: Image bomb detection
        $imageInfo = @getimagesizefromstring($decodedImage);
        if ($imageInfo) {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $fileSize = strlen($decodedImage);
            
            // Calculate compression ratio
            $expectedSize = $width * $height * 3; // RGB
            $compressionRatio = $expectedSize / $fileSize;
            
            // Enterprise threshold for image bombs
            if ($compressionRatio > 2000) {
                error_log("Enterprise security: Suspicious compression ratio detected: {$compressionRatio}");
                throw new Exception('Imaginea are o structură suspectă. Încercați cu o fotografie normală de plantă.');
            }
        }
        
        // Enterprise security: EXIF data analysis
        if (function_exists('exif_read_data')) {
            $tempFile = tempnam(sys_get_temp_dir(), 'enterprise_img_security_');
            file_put_contents($tempFile, $decodedImage);
            
            $exifData = @exif_read_data($tempFile);
            unlink($tempFile);
            
            if ($exifData && count($exifData) > 100) {
                error_log("Enterprise security: Excessive EXIF data detected: " . count($exifData) . " fields");
                // Log but don't block - could be legitimate camera data
                error_log("Enterprise security: Large EXIF data noted but allowing processing");
            }
        }
        
        // Enterprise security: File signature validation
        $fileSignature = substr($imageHex, 0, 8);
        $validSignatures = [
            'ffd8ffe0', // JPEG
            'ffd8ffe1', // JPEG
            'ffd8ffe2', // JPEG
            '89504e47', // PNG
            '52494646', // WebP (RIFF)
        ];
        
        $signatureValid = false;
        foreach ($validSignatures as $signature) {
            if (stripos($fileSignature, $signature) === 0) {
                $signatureValid = true;
                break;
            }
        }
        
        if (!$signatureValid) {
            error_log("Enterprise security: Invalid file signature: $fileSignature");
            throw new Exception('Imaginea nu are o semnătură validă. Folosiți o imagine autentică de plantă.');
        }
        
        error_log("Enterprise image security scan passed successfully");
        return true;
        
    } catch (Exception $e) {
        error_log("Enterprise image security scan failed: " . $e->getMessage());
        throw $e;
    }
}

// ENTERPRISE S24 ULTRA IMAGE PREPROCESSING FUNCTION
function preprocessImageForAnalysisEnterprise($imageBase64, $estimatedSizeMB) {
    try {
        error_log("Starting enterprise S24 Ultra image preprocessing...");
        
        // Decode the image
        $decodedImage = base64_decode($imageBase64, true);
        $imageInfo = getimagesizefromstring($decodedImage);
        
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        error_log("Enterprise preprocessing: Original {$originalWidth}x{$originalHeight}, Type: {$mimeType}, Size: {$estimatedSizeMB}MB");
        
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
                throw new Exception('Tip de imagine nesuportat pentru procesare enterprise');
        }
        
        if (!$sourceImage) {
            error_log("Enterprise preprocessing: Failed to create image resource");
            throw new Exception('Nu am putut procesa imaginea pentru analiză. Încercați cu o altă imagine.');
        }
        
        // Enterprise S24 ULTRA: Intelligent resizing based on content
        $maxDimension = 1600; // Enterprise quality - higher than basic
        $maxFileSize = 1024 * 1024; // 1MB target for optimal processing
        
        // Calculate optimal dimensions
        $scale = min(
            $maxDimension / $originalWidth,
            $maxDimension / $originalHeight,
            1.0 // Don't upscale
        );
        
        $newWidth = round($originalWidth * $scale);
        $newHeight = round($originalHeight * $scale);
        
        error_log("Enterprise preprocessing: Resizing to {$newWidth}x{$newHeight} (scale: {$scale})");
        
        // Create new image with optimal dimensions
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Enterprise quality: Preserve transparency for PNG
        if ($mimeType === 'image/png') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefill($resizedImage, 0, 0, $transparent);
        }
        
        // Enterprise quality: High-quality resampling
        imagecopyresampled(
            $resizedImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );
        
        // Enterprise enhancement: Apply image filters for better plant recognition
        if (function_exists('imagefilter')) {
            // Enhance contrast for better plant detection
            imagefilter($resizedImage, IMG_FILTER_CONTRAST, -15);
            // Slightly increase brightness for better visibility
            imagefilter($resizedImage, IMG_FILTER_BRIGHTNESS, 10);
            // Reduce noise for cleaner analysis
            imagefilter($resizedImage, IMG_FILTER_SMOOTH, 2);
        }
        
        // Enterprise optimization: Convert to optimal format with quality control
        ob_start();
        
        // Try different quality levels to meet size target
        $quality = 90; // Start with high quality for enterprise
        do {
            ob_clean();
            imagejpeg($resizedImage, null, $quality);
            $outputSize = ob_get_length();
            $quality -= 5;
        } while ($outputSize > $maxFileSize && $quality > 60); // Maintain minimum quality
        
        $optimizedImageData = ob_get_contents();
        ob_end_clean();
        
        // Cleanup memory
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        
        $finalSize = strlen($optimizedImageData);
        $finalSizeKB = round($finalSize / 1024, 2);
        $compressionRatio = round((strlen($decodedImage) / $finalSize) * 100, 1);
        
        error_log("Enterprise preprocessing completed: {$finalSizeKB} KB (quality: " . ($quality + 5) . ", compression: {$compressionRatio}%)");
        
        return base64_encode($optimizedImageData);
        
    } catch (Exception $e) {
        error_log("Enterprise image preprocessing failed: " . $e->getMessage());
        // Enterprise fallback: Return original if preprocessing fails
        error_log("Enterprise fallback: Using original image");
        return $imageBase64;
    }
}

// ENTERPRISE IMAGE CONTENT ANALYSIS FUNCTION
function analyzeImageContentEnterprise($visionResults, $userContext) {
    try {
        error_log("Starting enterprise image content analysis...");
        
        $analysis = [
            'plant_type' => 'unknown',
            'confidence' => 0.0,
            'health_status' => 'unknown',
            'problems_detected' => [],
            'season_relevant' => false,
            'user_relevant' => false,
            'complexity_level' => 'basic',
            'romanian_context' => false
        ];
        
        // Analyze detected objects and labels
        $allDetections = array_merge(
            array_column($visionResults['objects'], 'name'),
            array_column($visionResults['labels'], 'description')
        );
        
        error_log("Enterprise analysis: Processing " . count($allDetections) . " detections");
        
        // Plant type detection with Romanian context
        $plantTypes = [
            'tomato' => ['tomate', 'rosii', 'tomato'],
            'cucumber' => ['castraveti', 'castravet', 'cucumber'],
            'pepper' => ['ardei', 'paprika', 'pepper'],
            'eggplant' => ['vinete', 'patlagele', 'eggplant'],
            'lettuce' => ['salata', 'laitue', 'lettuce'],
            'onion' => ['ceapa', 'onion'],
            'garlic' => ['usturoi', 'garlic'],
            'carrot' => ['morcov', 'carrot'],
            'radish' => ['ridichi', 'radish'],
            'spinach' => ['spanac', 'spinach'],
            'parsley' => ['patrunjel', 'parsley'],
            'dill' => ['marar', 'dill'],
            'basil' => ['busuioc', 'basil'],
            'rosemary' => ['rozmarinul', 'rosemary'],
            'roses' => ['trandafiri', 'roses'],
            'tulips' => ['lalele', 'tulips']
        ];
        
        foreach ($plantTypes as $type => $keywords) {
            foreach ($allDetections as $detection) {
                foreach ($keywords as $keyword) {
                    if (stripos($detection, $keyword) !== false) {
                        $analysis['plant_type'] = $type;
                        $analysis['confidence'] += 0.3;
                        $analysis['romanian_context'] = true;
                        break 3;
                    }
                }
            }
        }
        
        // Health status detection
        $healthIndicators = [
            'healthy' => ['green', 'fresh', 'vibrant', 'lush'],
            'stressed' => ['yellow', 'wilted', 'dry', 'brown'],
            'diseased' => ['spotted', 'moldy', 'fungus', 'pest'],
            'dying' => ['dead', 'withered', 'black', 'rotten']
        ];
        
        foreach ($healthIndicators as $status => $indicators) {
            foreach ($allDetections as $detection) {
                foreach ($indicators as $indicator) {
                    if (stripos($detection, $indicator) !== false) {
                        $analysis['health_status'] = $status;
                        $analysis['confidence'] += 0.2;
                        break 3;
                    }
                }
            }
        }
        
        // Problem detection
        $problemTypes = [
            'pest' => ['insect', 'bug', 'aphid', 'caterpillar'],
            'disease' => ['spot', 'mold', 'fungus', 'blight'],
            'nutrient' => ['yellow', 'pale', 'deficiency'],
            'water' => ['wilted', 'dry', 'overwatered']
        ];
        
        foreach ($problemTypes as $problem => $indicators) {
            foreach ($allDetections as $detection) {
                foreach ($indicators as $indicator) {
                    if (stripos($detection, $indicator) !== false) {
                        $analysis['problems_detected'][] = $problem;
                        $analysis['confidence'] += 0.1;
                    }
                }
            }
        }
        
        // User relevance check
        if (!empty($userContext['favorite_plants'])) {
            foreach ($userContext['favorite_plants'] as $favPlant) {
                if ($analysis['plant_type'] === $favPlant || 
                    in_array($favPlant, $allDetections)) {
                    $analysis['user_relevant'] = true;
                    $analysis['confidence'] += 0.2;
                    break;
                }
            }
        }
        
        // Complexity level based on user experience
        $analysis['complexity_level'] = match($userContext['experience_level']) {
            'avansat' => 'advanced',
            'intermediar' => 'intermediate',
            default => 'basic'
        };
        
        // Seasonal relevance
        $currentMonth = date('n');
        $analysis['season_relevant'] = true; // Images are always season-relevant
        
        // Final confidence adjustment
        $analysis['confidence'] = min(1.0, $analysis['confidence']);
        
        error_log("Enterprise image content analysis completed: " . json_encode($analysis));
        return $analysis;
        
    } catch (Exception $e) {
        error_log("Enterprise image content analysis failed: " . $e->getMessage());
        return [
            'plant_type' => 'unknown',
            'confidence' => 0.3,
            'health_status' => 'unknown',
            'problems_detected' => [],
            'season_relevant' => false,
            'user_relevant' => false,
            'complexity_level' => 'basic',
            'romanian_context' => false
        ];
    }
}

// ENTERPRISE ERROR CATEGORIZATION FOR IMAGES
function categorizeImageErrorEnterprise($errorMessage) {
    $lowerError = strtolower($errorMessage);
    
    if (strpos($lowerError, 's24 ultra') !== false || strpos($lowerError, 'prea mare') !== false) {
        return 'image_size_error';
    } elseif (strpos($lowerError, 'format') !== false || strpos($lowerError, 'invalid') !== false) {
        return 'image_format_error';
    } elseif (strpos($lowerError, 'suspect') !== false || strpos($lowerError, 'security') !== false) {
        return 'image_security_error';
    } elseif (strpos($lowerError, 'vision') !== false || strpos($lowerError, 'google') !== false) {
        return 'vision_api_error';
    } elseif (strpos($lowerError, 'openai') !== false || strpos($lowerError, 'ai') !== false) {
        return 'ai_processing_error';
    } elseif (strpos($lowerError, 'memory') !== false || strpos($lowerError, 'timeout') !== false) {
        return 'resource_error';
    } else {
        return 'general_image_error';
    }
}
// ENTERPRISE GOOGLE VISION FUNCTION with advanced detection
function analyzeWithGoogleVisionEnterprise($imageBase64) {
    $googleVisionKey = getenv('GOOGLE_VISION_KEY');
    if (!$googleVisionKey) {
        error_log("Google Vision API key not found for enterprise image processing");
        throw new Exception('Serviciul de analiză imagini nu este disponibil momentan');
    }

    error_log("Starting enterprise Google Vision analysis...");
    error_log("Google Vision API key exists: YES (length: " . strlen($googleVisionKey) . ")");
    error_log("Image base64 length for Vision API: " . strlen($imageBase64));

    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . $googleVisionKey;

    // ENTERPRISE: Comprehensive detection types for professional plant analysis
    $requestData = [
        'requests' => [[
            'image' => ['content' => $imageBase64],
            'features' => [
                ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 30],     // More objects
                ['type' => 'LABEL_DETECTION', 'maxResults' => 30],         // More labels
                ['type' => 'TEXT_DETECTION', 'maxResults' => 15],          // Plant labels/signs
                ['type' => 'CROP_HINTS', 'maxResults' => 10],              // Better framing
                ['type' => 'IMAGE_PROPERTIES'],                            // Color analysis
                ['type' => 'SAFE_SEARCH_DETECTION'],                       // Content safety
                ['type' => 'WEB_DETECTION', 'maxResults' => 10]            // Similar images
            ],
            'imageContext' => [
                'cropHintsParams' => [
                    'aspectRatios' => [1.0, 1.77, 0.56, 0.75, 1.33] // Multiple aspect ratios
                ],
                'languageHints' => ['ro', 'en', 'la'],                     // Romanian, English, Latin
                'latLongRect' => [                                          // Romanian geographic context
                    'minLatLng' => ['latitude' => 43.6, 'longitude' => 20.2],
                    'maxLatLng' => ['latitude' => 48.3, 'longitude' => 29.7]
                ]
            ]
        ]]
    ];

    error_log("Sending enterprise request to Google Vision API...");

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: GospodApp-Enterprise/2.0 (Romanian Gardening Assistant)'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 45); // Increased timeout for comprehensive analysis
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    // Enhanced error logging
    error_log("Google Vision enterprise response code: $httpCode");
    if ($httpCode !== 200) {
        error_log("Google Vision enterprise error response: " . substr($response, 0, 1000));
        if ($curlError) {
            error_log("cURL error for Google Vision enterprise: " . $curlError);
        }
    } else {
        error_log("Google Vision enterprise response received successfully (length: " . strlen($response) . ")");
    }
    
    curl_close($ch);

    // Enterprise error handling
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
        error_log("Google Vision enterprise API error: " . json_encode($result['error']));
        $errorMessage = $result['error']['message'] ?? 'Eroare necunoscută';
        
        if (strpos($errorMessage, 'quota') !== false) {
            throw new Exception('Serviciul de analiză a atins limita zilnică. Încercați mâine.');
        }
        
        throw new Exception('Eroare la analiza imaginii: ' . $errorMessage);
    }

    // ENTERPRISE: Extract comprehensive data with advanced processing
    $objects = [];
    $labels = [];
    $texts = [];
    $colors = [];
    $cropHints = [];
    $webDetection = [];
    $safeSearch = [];

    $responseData = $result['responses'][0] ?? [];

    // Extract objects with confidence scoring
    if (isset($responseData['localizedObjectAnnotations'])) {
        foreach ($responseData['localizedObjectAnnotations'] as $obj) {
            $objects[] = [
                'name' => $obj['name'],
                'score' => $obj['score'] ?? 0,
                'boundingBox' => $obj['boundingPoly'] ?? null,
                'mid' => $obj['mid'] ?? null
            ];
        }
        error_log("Google Vision enterprise found " . count($objects) . " objects: " . implode(', ', array_column($objects, 'name')));
    }

    // Extract labels with enhanced metadata
    if (isset($responseData['labelAnnotations'])) {
        foreach ($responseData['labelAnnotations'] as $label) {
            $labels[] = [
                'description' => $label['description'],
                'score' => $label['score'] ?? 0,
                'confidence' => $label['confidence'] ?? 0,
                'mid' => $label['mid'] ?? null,
                'topicality' => $label['topicality'] ?? 0
            ];
        }
        error_log("Google Vision enterprise found " . count($labels) . " labels: " . implode(', ', array_column($labels, 'description')));
    }

    // Extract text with language detection
    if (isset($responseData['textAnnotations'])) {
        foreach ($responseData['textAnnotations'] as $text) {
            if (strlen($text['description']) > 2) {
                $texts[] = [
                    'text' => $text['description'],
                    'boundingBox' => $text['boundingPoly'] ?? null,
                    'confidence' => $text['confidence'] ?? 0
                ];
            }
        }
        error_log("Google Vision enterprise found " . count($texts) . " text elements");
    }

    // Extract dominant colors with advanced analysis
    if (isset($responseData['imagePropertiesAnnotation']['dominantColors']['colors'])) {
        foreach ($responseData['imagePropertiesAnnotation']['dominantColors']['colors'] as $colorInfo) {
            $color = $colorInfo['color'];
            $colors[] = [
                'red' => $color['red'] ?? 0,
                'green' => $color['green'] ?? 0,
                'blue' => $color['blue'] ?? 0,
                'alpha' => $color['alpha'] ?? 1.0,
                'score' => $colorInfo['score'] ?? 0,
                'pixelFraction' => $colorInfo['pixelFraction'] ?? 0
            ];
        }
        error_log("Google Vision enterprise found " . count($colors) . " dominant colors");
    }

    // Extract crop hints for better composition
    if (isset($responseData['cropHintsAnnotation']['cropHints'])) {
        foreach ($responseData['cropHintsAnnotation']['cropHints'] as $hint) {
            $cropHints[] = [
                'boundingPoly' => $hint['boundingPoly'] ?? null,
                'confidence' => $hint['confidence'] ?? 0,
                'importanceFraction' => $hint['importanceFraction'] ?? 0
            ];
        }
        error_log("Google Vision enterprise found " . count($cropHints) . " crop hints");
    }

    // Extract web detection for similar plants
    if (isset($responseData['webDetection'])) {
        $webData = $responseData['webDetection'];
        $webDetection = [
            'webEntities' => $webData['webEntities'] ?? [],
            'fullMatchingImages' => $webData['fullMatchingImages'] ?? [],
            'partialMatchingImages' => $webData['partialMatchingImages'] ?? [],
            'pagesWithMatchingImages' => $webData['pagesWithMatchingImages'] ?? []
        ];
        error_log("Google Vision enterprise web detection completed");
    }

    // Extract safe search results
    if (isset($responseData['safeSearchAnnotation'])) {
        $safeSearch = $responseData['safeSearchAnnotation'];
        error_log("Google Vision enterprise safe search completed");
    }

    error_log("Enhanced Google Vision enterprise analysis completed successfully");

    return [
        'objects' => $objects,
        'labels' => $labels,
        'texts' => $texts,
        'colors' => $colors,
        'cropHints' => $cropHints,
        'webDetection' => $webDetection,
        'safeSearch' => $safeSearch
    ];
}

// ENTERPRISE AI TREATMENT FUNCTION with comprehensive plant analysis
function getEnhancedImageTreatmentEnterprise($visionResults, $imageAnalysis, $userContext) {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        error_log("OpenAI API key not found for enterprise image analysis");
        throw new Exception('Serviciul de analiză AI nu este disponibil momentan');
    }

    // Prepare comprehensive data for analysis
    $objects = array_column($visionResults['objects'], 'name');
    $labels = array_column($visionResults['labels'], 'description');
    $texts = array_column($visionResults['texts'], 'text');
    $colors = $visionResults['colors'];

    error_log("Creating enterprise OpenAI prompt for comprehensive plant analysis...");
    error_log("Objects for analysis: " . implode(', ', $objects));
    error_log("Labels for analysis: " . implode(', ', $labels));
    error_log("Text elements found: " . count($texts));
    error_log("Color palette size: " . count($colors));
    error_log("Plant type detected: " . $imageAnalysis['plant_type']);
    error_log("Health status: " . $imageAnalysis['health_status']);

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
        } elseif ($greenLevel < 80) {
            $colorAnalysis .= "Posibile probleme de nutriție (verde slab). ";
        }
    }

    // Get current season context
    $currentMonth = date('n');
    $currentSeason = getSeasonEnterprise($currentMonth);
    $seasonalContext = getSeasonalContextEnterprise($currentSeason, $currentMonth);

    $systemPrompt = "Ești un expert în grădinărit din România cu 30 de ani experiență, specializat în diagnosticarea plantelor prin imagini de înaltă calitate.

CONTEXTUL UTILIZATORULUI:
- Nivel experiență: {$userContext['experience_level']}
- Tip grădină: {$userContext['garden_type']}
- Regiunea: {$userContext['region']}
- Plante preferate: " . implode(', ', $userContext['favorite_plants']) . "

CONTEXTUL SEZONULUI ACTUAL:
- Sezonul: $currentSeason (luna " . date('F') . ")
- Activități de sezon: {$seasonalContext['activities']}
- Plante specifice: {$seasonalContext['plants']}
- Probleme comune: {$seasonalContext['common_issues']}

ANALIZA IMAGINII DETECTATE:
- Tip plantă identificat: {$imageAnalysis['plant_type']}
- Starea de sănătate: {$imageAnalysis['health_status']}
- Probleme detectate: " . implode(', ', $imageAnalysis['problems_detected']) . "
- Încrederea analizei: " . round($imageAnalysis['confidence'] * 100) . "%
- Context românesc: " . ($imageAnalysis['romanian_context'] ? 'DA' : 'NU') . "

EXPERTIZA TA AVANSATĂ PENTRU IMAGINI:
- Analizezi toate datele: obiecte, etichete, text și culori
- Identifici specii exacte de plante românești și internaționale
- Diagnostichezi boli, dăunători și deficiențe prin aspectul vizual
- Evaluezi sănătatea plantelor prin culoare, formă și aspect general
- Cunoști tratamentele și produsele disponibile în România
- Înțelegi specificul climei continentale românești

REGULI PENTRU ANALIZA IMAGINILOR:
- Adaptezi răspunsul la nivelul utilizatorului și contextul sezonier
- Incluzi recomandări concrete pentru clima României
- Menționezi produse și tratamente disponibile local
- Explici când și cum să aplici tratamentele
- Folosești termeni simpli pentru începători, detalii tehnice pentru avansați
- Răspunsurile să fie între 200-400 de cuvinte, detaliate și utile
- Eviți asteriscuri, numere în paranteză sau formatare specială

STRUCTURA RĂSPUNSULUI:
1. Identificarea precisă a plantei
2. Evaluarea stării de sănătate bazată pe aspectul vizual
3. Probleme identificate (dacă există) și cauzele lor
4. Tratamente și soluții concrete disponibile în România
5. Sfaturi de îngrijire pe termen lung și prevenire
6. Recomandări sezoniere specifice";

    // Create comprehensive prompt with all available data
    $prompt = "Analizează această imagine de grădină folosind toate datele disponibile din analiza avansată:

OBIECTE DETECTATE: " . implode(', ', $objects) . "
ETICHETE IDENTIFICATE: " . implode(', ', $labels) . "
TEXT GĂSIT ÎN IMAGINE: " . implode(', ', $texts) . "
ANALIZA CULORILOR: " . $colorAnalysis . "

CONTEXT SUPLIMENTAR:
- Planta identificată automat: {$imageAnalysis['plant_type']}
- Starea detectată: {$imageAnalysis['health_status']}
- Relevanță pentru utilizator: " . ($imageAnalysis['user_relevant'] ? 'DA (plantă cunoscută)' : 'NU') . "

Te rog să îmi oferi o analiză completă și personalizată:
1. Identificarea exactă a plantei și cum ai ajuns la această concluzie
2. Evaluarea stării de sănătate bazată pe culori, formă și aspectul general
3. Probleme vizibile (boli, dăunători, deficiențe) și cum le-ai identificat
4. Tratamente concrete și produse disponibile în România
5. Sfaturi de îngrijire adaptate pentru clima noastră și sezonul actual
6. Când și cum să aplici tratamentele recomandate
7. Măsuri de prevenire pentru viitor

Dacă ai identificat text în imagine (etichete, semne), folosește aceste informații pentru o analiză și mai precisă.";

    error_log("Sending enterprise request to OpenAI for comprehensive plant analysis...");

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey,
        'User-Agent: GospodApp-Enterprise/2.0 (Romanian Gardening Assistant)'
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 800, // Increased for comprehensive analysis
        'temperature' => $imageAnalysis['health_status'] === 'diseased' ? 0.3 : 0.7, // Lower temp for medical issues
        'top_p' => 0.9
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    error_log("OpenAI enterprise image analysis response code: $httpCode");
    if ($httpCode !== 200) {
        error_log("OpenAI enterprise image analysis error: " . substr($response, 0, 1000));
        if ($curlError) {
            error_log("cURL error for OpenAI enterprise image analysis: " . $curlError);
        }
    } else {
        error_log("OpenAI enterprise image analysis response received (length: " . strlen($response) . ")");
    }
    
    curl_close($ch);

    // Enhanced error handling
    if ($curlError) {
        throw new Exception('Eroare de conexiune la serviciul de analiză AI. Verificați conexiunea la internet și încercați din nou.');
    }

    if ($httpCode === 429) {
        throw new Exception('Serviciul de analiză AI este temporar suprasolicitat. Încercați din nou în câteva minute.');
    }

    if ($httpCode !== 200) {
        throw new Exception('Nu am putut analiza imaginea momentan. Încercați din nou.');
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        error_log("OpenAI enterprise image analysis API error: " . json_encode($data['error']));
        throw new Exception('Nu am putut analiza imaginea momentan. Încercați din nou.');
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        error_log("OpenAI enterprise image analysis response missing content");
        throw new Exception('Analiza imaginii a eșuat. Încercați cu o altă poză.');
    }

    $content = $data['choices'][0]['message']['content'];
    error_log("OpenAI enterprise image analysis content length: " . strlen($content));
    
    return cleanForTTSEnterprise($content);
}

// ENTERPRISE IMAGE CACHING FUNCTIONS
function getCachedImageTreatmentEnterprise($imageBase64) {
    try {
        $imageHash = md5($imageBase64);
        $cacheKey = 'enterprise_img_' . $imageHash;
        $cacheFile = '/tmp/' . $cacheKey . '.json';
        
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            $cacheAge = time() - $cacheData['timestamp'];
            
            // Enterprise image cache expires after 6 hours
            if ($cacheAge < 21600) {
                error_log("Enterprise image cache hit for: " . substr($imageHash, 0, 15));
                return $cacheData['treatment'];
            } else {
                unlink($cacheFile);
                error_log("Enterprise image cache expired and removed: " . substr($imageHash, 0, 15));
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Enterprise image cache retrieval error: " . $e->getMessage());
        return null;
    }
}

function cacheImageTreatmentEnterprise($imageBase64, $treatment) {
    try {
        $imageHash = md5($imageBase64);
        $cacheKey = 'enterprise_img_' . $imageHash;
        $cacheFile = '/tmp/' . $cacheKey . '.json';
        
        $cacheData = [
            'treatment' => $treatment,
            'timestamp' => time(),
            'version' => 'enterprise_image_v2',
            'hash' => $imageHash
        ];
        
        file_put_contents($cacheFile, json_encode($cacheData));
        error_log("Enterprise image treatment cached: " . substr($imageHash, 0, 15));
        
    } catch (Exception $e) {
        error_log("Enterprise image cache storage error: " . $e->getMessage());
    }
}
// ENTERPRISE IMAGE ANTI-BOT PROTECTION
function checkImageRateLimitsEnterprise($deviceHash) {
    try {
        $rateLimitFile = '/tmp/rate_limit_enterprise_image_' . $deviceHash . '.txt';
        $currentTime = time();
        
        error_log("Enterprise image rate limit check for device: $deviceHash");
        
        if (file_exists($rateLimitFile)) {
            $requests = json_decode(file_get_contents($rateLimitFile), true) ?: [];
            $requests = array_filter($requests, function($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < 300; // Last 5 minutes for images
            });
            error_log("Enterprise image found " . count($requests) . " requests in last 5 minutes");
        } else {
            $requests = [];
            error_log("Enterprise image no previous rate limit file found");
        }
        
        // Enterprise allows more image requests (5 per 5 minutes vs 3 for basic)
        if (count($requests) >= 5) {
            error_log("Enterprise image rate limit exceeded for device: $deviceHash");
            throw new Exception('Prea multe analize de imagini. Încercați din nou în 5 minute pentru a preveni supraîncărcarea sistemului.');
        }
        
        $requests[] = $currentTime;
        file_put_contents($rateLimitFile, json_encode($requests));
        error_log("Enterprise image rate limit check passed, requests in last 5 min: " . count($requests));
        
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Prea multe analize') !== false) {
            throw $e;
        }
        error_log("Enterprise image error in rate limit check: " . $e->getMessage());
    }
}

function detectSuspiciousImageActivityEnterprise($deviceHash, $imageBase64) {
    try {
        $imageHash = md5($imageBase64);
        $suspiciousFile = '/tmp/suspicious_enterprise_image_' . $deviceHash . '_' . $imageHash . '.txt';
        $currentTime = time();
        
        error_log("Enterprise image checking suspicious activity for device: $deviceHash");
        
        if (file_exists($suspiciousFile)) {
            $data = json_decode(file_get_contents($suspiciousFile), true);
            $count = $data['count'] ?? 0;
            $lastTime = $data['last_time'] ?? 0;
            
            // Reset count if more than 2 hours passed (enterprise allows more flexibility)
            if (($currentTime - $lastTime) > 7200) {
                $count = 0;
            }
            
            $count++;
            
            // Enterprise allows more repeated images (5 vs 3 for basic)
            if ($count > 5) {
                error_log("Enterprise image suspicious activity detected - repeated image from device: $deviceHash");
                throw new Exception('Aceeași imagine trimisă prea des. Încercați cu o imagine diferită sau așteptați 2 ore.');
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
        
        error_log("Enterprise image suspicious activity check passed for device: $deviceHash");
        
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Aceeași imagine') !== false) {
            throw $e;
        }
        error_log("Enterprise image error in suspicious activity check: " . $e->getMessage());
    }
}

// ENTERPRISE IMAGE ANALYTICS TRACKING
function trackImageEngagementEnterprise($pdo, $deviceHash, $imageAnalysis, $treatment, $visionResults) {
    try {
        error_log("Enterprise tracking image engagement for device: $deviceHash");
        
        $stmt = $pdo->prepare("
            INSERT INTO user_analytics 
            (device_hash, message_length, response_length, content_type, urgency_level, 
             plant_mentioned, problem_type, confidence_score, processing_time_ms, cached_response, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $processingTime = round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2);
        
        $stmt->execute([
            $deviceHash,
            0, // Image length (not applicable)
            strlen($treatment),
            'image_analysis',
            $imageAnalysis['health_status'] === 'diseased' ? 'high' : 'normal',
            $imageAnalysis['plant_type'] !== 'unknown' ? 1 : 0,
            !empty($imageAnalysis['problems_detected']) ? implode(',', $imageAnalysis['problems_detected']) : null,
            $imageAnalysis['confidence'],
            $processingTime,
            0 // Not cached (new analysis)
        ]);
        
        error_log("Enterprise image engagement tracked successfully");
        
        // Update popular plant topics
        updatePopularPlantsEnterprise($pdo, $imageAnalysis, $visionResults);
        
    } catch (PDOException $e) {
        error_log("Enterprise DB error in image engagement tracking: " . $e->getMessage());
    }
}

function updatePopularPlantsEnterprise($pdo, $imageAnalysis, $visionResults) {
    try {
        // Track detected plant types
        if ($imageAnalysis['plant_type'] !== 'unknown') {
            $stmt = $pdo->prepare("
                INSERT INTO popular_topics (topic, category, count, last_mentioned) 
                VALUES (?, 'plant', 1, NOW()) 
                ON DUPLICATE KEY UPDATE 
                count = count + 1, 
                last_mentioned = NOW()
            ");
            $stmt->execute([$imageAnalysis['plant_type']]);
        }
        
        // Track detected problems
        foreach ($imageAnalysis['problems_detected'] as $problem) {
            $stmt = $pdo->prepare("
                INSERT INTO popular_topics (topic, category, count, last_mentioned) 
                VALUES (?, 'problem', 1, NOW()) 
                ON DUPLICATE KEY UPDATE 
                count = count + 1, 
                last_mentioned = NOW()
            ");
            $stmt->execute(['image_' . $problem]);
        }
        
        // Track health status
        if ($imageAnalysis['health_status'] !== 'unknown') {
            $stmt = $pdo->prepare("
                INSERT INTO popular_topics (topic, category, count, last_mentioned) 
                VALUES (?, 'health', 1, NOW()) 
                ON DUPLICATE KEY UPDATE 
                count = count + 1, 
                last_mentioned = NOW()
            ");
            $stmt->execute(['health_' . $imageAnalysis['health_status']]);
        }
        
    } catch (PDOException $e) {
        error_log("Enterprise error updating popular plants: " . $e->getMessage());
    }
}

function updateUserContextWithImageEnterprise($pdo, $deviceHash, $imageAnalysis) {
    try {
        if ($imageAnalysis['plant_type'] !== 'unknown') {
            $stmt = $pdo->prepare("SELECT favorite_plants FROM user_profiles WHERE device_hash = ?");
            $stmt->execute([$deviceHash]);
            $currentPlants = json_decode($stmt->fetchColumn() ?? '[]', true);
            
            // Add detected plant to favorites
            if (!in_array($imageAnalysis['plant_type'], $currentPlants)) {
                $currentPlants[] = $imageAnalysis['plant_type'];
                
                $stmt = $pdo->prepare("
                    UPDATE user_profiles 
                    SET favorite_plants = ?, last_activity = NOW(), total_questions = total_questions + 1
                    WHERE device_hash = ?
                ");
                $stmt->execute([json_encode($currentPlants), $deviceHash]);
                
                error_log("Enterprise user context updated with plant: " . $imageAnalysis['plant_type']);
            }
        }
        
        // Update experience level based on image complexity
        if ($imageAnalysis['confidence'] > 0.8) {
            $stmt = $pdo->prepare("
                UPDATE user_profiles 
                SET experience_level = CASE 
                    WHEN experience_level = 'începător' AND total_questions > 10 THEN 'intermediar'
                    WHEN experience_level = 'intermediar' AND total_questions > 25 THEN 'avansat'
                    ELSE experience_level
                END
                WHERE device_hash = ?
            ");
            $stmt->execute([$deviceHash]);
        }
        
    } catch (Exception $e) {
        error_log("Enterprise error updating user context with image: " . $e->getMessage());
    }
}

// ENTERPRISE SEASONAL FUNCTIONS (shared with text system)
function getSeasonEnterprise($month) {
    if ($month >= 3 && $month <= 5) return 'primăvara';
    if ($month >= 6 && $month <= 8) return 'vara';
    if ($month >= 9 && $month <= 11) return 'toamna';
    return 'iarna';
}

function getSeasonalContextEnterprise($season, $month) {
    $seasonalData = [
        'primăvara' => [
            'activities' => 'semănatul legumelor, plantatul puieților, pregătirea solului, tăierea pomilor',
            'plants' => 'salată, ridichi, mazăre, morcov, ceapă, usturoi de primăvară',
            'common_issues' => 'îngheț târziu, sol prea umed, dăunători care se trezesc, boli fungice'
        ],
        'vara' => [
            'activities' => 'udatul regulat, recoltatul continuu, tratarea dăunătorilor, legarea plantelor',
            'plants' => 'tomate, castraveți, ardei, vinete, floarea-soarelui, bostan',
            'common_issues' => 'secetă, căldură excesivă, boli fungice, afide, păianjenul roșu'
        ],
        'toamna' => [
            'activities' => 'recoltatul de toamnă, pregătirea pentru iarnă, plantatul bulbilor, compostarea',
            'plants' => 'varză, spanac, ridichi de toamnă, usturoi de iarnă, ceapă de iarnă',
            'common_issues' => 'umiditate excesivă, putregai, pregătirea pentru ger, depozitarea recoltei'
        ],
        'iarna' => [
            'activities' => 'protecția plantelor, planificarea grădinii, întreținerea uneltelor, răsaduri în casă',
            'plants' => 'plante de interior, microverdeturi, răsaduri în seră, planificarea pentru primăvară',
            'common_issues' => 'ger, lipsa luminii, aer uscat în interior, planificare pentru anul următor'
        ]
    ];
    
    return $seasonalData[$season] ?? $seasonalData['primăvara'];
}

// ENTERPRISE DATABASE FUNCTIONS (shared with text system)
function connectToDatabaseEnterprise() {
    try {
        $host = getenv('DB_HOST');
        $dbname = getenv('DB_NAME');
        $username = getenv('DB_USER');
        $password = getenv('DB_PASS');
        
        error_log("Enterprise DB connection for images to: $host");
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 15,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_PERSISTENT => false
        ]);
        
        $pdo->query("SELECT 1");
        error_log("Enterprise DB connection for images successful");
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Enterprise DB connection for images failed: " . $e->getMessage());
        throw new Exception('Nu pot conecta la baza de date momentan. Încercați din nou în câteva secunde.');
    }
}

function checkUsageLimitsEnterprise($pdo, $deviceHash, $type) {
    try {
        $today = date('Y-m-d');
        error_log("Enterprise usage limits check for images: $deviceHash on $today");
        
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

        if ($type === 'image') {
            $limit = $usage['premium'] ? 10 : 2; // Enterprise: 10 premium, 2 free (vs 5/1 basic)
            $remaining = $limit - $usage['image_count'];
            
            error_log("Enterprise image usage: used {$usage['image_count']}, limit $limit, remaining $remaining");
            
            if ($usage['image_count'] >= $limit) {
                if ($usage['premium']) {
                    throw new Exception('Ați atins limita zilnică de 10 analize premium de imagini. Reveniți mâine pentru analize noi!');
                } else {
                    throw new Exception('Ați folosit cele 2 analize gratuite de imagini de astăzi! Upgradeați la Premium pentru 10 analize zilnice sau urmăriți o reclamă pentru analize extra.');
                }
            }
        }
        
    } catch (PDOException $e) {
        error_log("Enterprise DB error in image usage limits: " . $e->getMessage());
        throw new Exception('Eroare temporară la verificarea limitelor. Încercați din nou în câteva secunde.');
    }
}

function recordUsageEnterprise($pdo, $deviceHash, $type) {
    try {
        $today = date('Y-m-d');
        $field = $type === 'image' ? 'image_count' : 'text_count';
        
        error_log("Enterprise recording image usage for device: $deviceHash");
        
        $stmt = $pdo->prepare("
            UPDATE usage_tracking 
            SET $field = $field + 1, last_activity = NOW() 
            WHERE device_hash = ? AND date = ?
        ");
        $result = $stmt->execute([$deviceHash, $today]);
        
        if ($result) {
            error_log("Enterprise image usage recorded successfully");
            updateDailyStatisticsEnterprise($pdo, $type);
        }
        
    } catch (PDOException $e) {
        error_log("Enterprise DB error in record image usage: " . $e->getMessage());
        throw new Exception('Eroare la înregistrarea utilizării.');
    }
}

function saveChatHistoryEnterprise($pdo, $deviceHash, $messageText, $isUserMessage, $messageType, $imageData) {
    try {
        error_log("Enterprise saving image chat history for device: $deviceHash");
        
        // Handle large image data
        if ($messageType === 'image' && $imageData && strlen($imageData) > 500000) {
            error_log("Enterprise large image detected, storing compressed reference");
            $imageData = substr($imageData, 0, 100000) . '...[ENTERPRISE_TRUNCATED]';
        }
        
        if (strlen($messageText) > 10000) {
            $messageText = substr($messageText, 0, 10000) . '...[ENTERPRISE_TRUNCATED]';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO chat_history (device_hash, message_text, is_user_message, message_type, image_data, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $result = $stmt->execute([
            $deviceHash,
            $messageText,
            $isUserMessage ? 1 : 0,
            $messageType,
            $imageData
        ]);
        
        if ($result) {
            error_log("Enterprise image chat history saved successfully");
        }
        
    } catch (PDOException $e) {
        error_log("Enterprise DB error in save image chat history: " . $e->getMessage());
    }
}

function getUserContextEnterprise($pdo, $deviceHash) {
    try {
        error_log("Enterprise retrieving user context for images: $deviceHash");
        
        $stmt = $pdo->prepare("
            SELECT experience_level, garden_type, region, favorite_plants, 
                   last_activity, total_questions, premium
            FROM user_profiles 
            WHERE device_hash = ?
        ");
        $stmt->execute([$deviceHash]);
        $profile = $stmt->fetch();
        
        if (!$profile) {
            $defaultProfile = [
                'experience_level' => 'începător',
                'garden_type' => 'general',
                'region' => 'România',
                'favorite_plants' => [],
                'total_questions' => 0,
                'premium' => false
            ];
            
            createUserProfileEnterprise($pdo, $deviceHash, $defaultProfile);
            return $defaultProfile;
        }
        
        return [
            'experience_level' => $profile['experience_level'] ?? 'începător',
            'garden_type' => $profile['garden_type'] ?? 'general',
            'region' => $profile['region'] ?? 'România',
            'favorite_plants' => json_decode($profile['favorite_plants'] ?? '[]', true),
            'total_questions' => $profile['total_questions'] ?? 0,
            'premium' => (bool)($profile['premium'] ?? false)
        ];
        
    } catch (Exception $e) {
        error_log("Enterprise user context error for images: " . $e->getMessage());
        return [
            'experience_level' => 'începător',
            'garden_type' => 'general',
            'region' => 'România',
            'favorite_plants' => [],
            'total_questions' => 0,
            'premium' => false
        ];
    }
}

function createUserProfileEnterprise($pdo, $deviceHash, $profile) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_profiles 
            (device_hash, experience_level, garden_type, region, favorite_plants, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $deviceHash,
            $profile['experience_level'],
            $profile['garden_type'],
            $profile['region'],
            json_encode($profile['favorite_plants'])
        ]);
        
        error_log("Enterprise user profile created for images: $deviceHash");
        
    } catch (Exception $e) {
        error_log("Enterprise user profile creation error for images: " . $e->getMessage());
    }
}

function updateDailyStatisticsEnterprise($pdo, $type) {
    try {
        $today = date('Y-m-d');
        
        $stmt = $pdo->prepare("
            INSERT INTO daily_statistics (date, text_requests, image_requests) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            text_requests = text_requests + ?, 
            image_requests = image_requests + ?
        ");
        
        $textIncrement = ($type === 'text') ? 1 : 0;
        $imageIncrement = ($type === 'image') ? 1 : 0;
        
        $stmt->execute([$today, $textIncrement, $imageIncrement, $textIncrement, $imageIncrement]);
        
    } catch (PDOException $e) {
        error_log("Enterprise error updating daily statistics for images: " . $e->getMessage());
    }
}

// ENTERPRISE TEXT CLEANING FUNCTION (shared)
function cleanForTTSEnterprise($text) {
    if ($text === null || $text === '') {
        return '';
    }
    
    $text = (string) $text;
    
    // Enhanced cleaning for enterprise TTS
    $text = preg_replace('/\*+/', '', $text);
    $text = preg_replace('/^\d+\.\s*/m', '', $text);
    $text = preg_replace('/^[\-\*\+]\s*/m', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/[#@$%^&(){}|\\\\]/', '', $text);
    $text = preg_replace('/\s*([,.!?;:])\s*/', '$1 ', $text);
    $text = preg_replace('/\s*\(\d+%\)\s*/', ' ', $text);
    $text = preg_replace('/\s*\[.*?\]\s*/', ' ', $text);
    $text = preg_replace('/🚨|⚡|✅|❌|🌱|📸|🏢/', '', $text);
    
    return trim($text);
}

// ENTERPRISE MEMORY CLEANUP FOR IMAGES
function cleanupImageMemoryEnterprise() {
    try {
        if (function_exists('gc_collect_cycles')) {
            $collected = gc_collect_cycles();
            error_log("Enterprise image garbage collection freed $collected cycles");
        }
        
        $tempDir = sys_get_temp_dir();
        $patterns = [
            '/rate_limit_enterprise_image_*.txt',
            '/suspicious_enterprise_image_*.txt',
            '/enterprise_img_*.json'
        ];
        
        $currentTime = time();
        $cleaned = 0;
        
        foreach ($patterns as $pattern) {
            $files = glob($tempDir . $pattern);
            foreach ($files as $file) {
                if (file_exists($file) && ($currentTime - filemtime($file)) > 7200) { // 2 hours
                    unlink($file);
                    $cleaned++;
                }
            }
        }
        
        if ($cleaned > 0) {
            error_log("Enterprise image cleaned up $cleaned temporary files");
        }
        
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024;
        error_log("Enterprise image final memory usage: {$memoryUsage} MB, Peak: {$peakMemory} MB");
        
    } catch (Exception $e) {
        error_log("Enterprise image cleanup error: " . $e->getMessage());
    }
}

// Register cleanup function for images
register_shutdown_function('cleanupImageMemoryEnterprise');

?>

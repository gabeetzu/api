<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// COMPREHENSIVE DEBUG: Enhanced logging for enterprise text processing
error_log("=== ENTERPRISE TEXT PROCESSING START ===");
error_log("Timestamp: " . date('Y-m-d H:i:s'));
error_log("API_SECRET_KEY exists: " . (getenv('API_SECRET_KEY') ? 'YES' : 'NO'));
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
error_log("Raw input preview: " . substr($rawInput, 0, 200) . "...");
error_log("Memory usage before processing: " . memory_get_usage(true) / 1024 / 1024 . " MB");
error_log("=== ENTERPRISE TEXT PROCESSING DEBUG END ===");

// Verify API Key with enhanced logging
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');

error_log("API Key verification - Received length: " . strlen($apiKey) . ", Expected length: " . strlen($expectedKey));

if ($apiKey !== $expectedKey) {
    error_log("API key mismatch - Authentication failed for enterprise text request");
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

error_log("API key verified successfully for enterprise text request");

// OPTIMIZED: Configure for fast enterprise text processing (Starter plan optimized)
ini_set('max_execution_time', '45');     // Optimized for Starter plan
ini_set('memory_limit', '256M');         // Sufficient for text processing
ini_set('max_input_time', '30');

error_log("PHP settings configured for enterprise text processing on Starter plan");

try {
    error_log("Starting enterprise text processing pipeline...");
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // ENHANCED: Comprehensive input validation
    if (!$data || !isset($data['message']) || !isset($data['device_hash'])) {
        error_log("Invalid enterprise text request - missing required fields");
        error_log("Data keys present: " . (is_array($data) ? implode(', ', array_keys($data)) : 'not an array'));
        throw new Exception('Date lipsă: Mesajul sau hash-ul dispozitivului nu au fost primite');
    }

    $message = $data['message'];
    $deviceHash = $data['device_hash'];

    error_log("Processing enterprise text message from device: $deviceHash");
    error_log("Message length: " . strlen($message));
    error_log("Message preview: " . substr($message, 0, 100) . "...");

    // ENTERPRISE PIPELINE - Step 1: Advanced Text Validation
    error_log("STEP 1: Advanced text validation...");
    $validatedMessage = validateTextInputEnterprise($message);
    sendProgressUpdate('validating', 'Validez mesajul...');
    
    // ENTERPRISE PIPELINE - Step 2: Security Scanning
    error_log("STEP 2: Enterprise security scanning...");
    performSecurityScanEnterprise($validatedMessage);

    // ENTERPRISE PIPELINE - Step 3: Content Analysis
    error_log("STEP 3: Content analysis and classification...");
    sendProgressUpdate('analyzing', 'Analizez conținutul...');
    $contentAnalysis = analyzeContentEnterprise($validatedMessage);
    error_log("Content analysis result: " . json_encode($contentAnalysis));

    // ENTERPRISE PIPELINE - Step 4: Anti-Bot Protection
    error_log("STEP 4: Anti-bot protection checks...");
    checkRateLimitsEnterprise($deviceHash);
    detectSuspiciousActivityEnterprise($deviceHash, $validatedMessage);

    // ENTERPRISE PIPELINE - Step 5: Database Connection
    error_log("STEP 5: Enterprise database connection...");
    $pdo = connectToDatabaseEnterprise();
    error_log("Enterprise database connection successful");
    
    // ENTERPRISE PIPELINE - Step 6: Usage Limits Check
    error_log("STEP 6: Enterprise usage limits verification...");
    checkUsageLimitsEnterprise($pdo, $deviceHash, 'text');

    // ENTERPRISE PIPELINE - Step 7: Response Cache Check
    error_log("STEP 7: Enterprise response cache check...");
    $cachedResponse = getCachedResponseEnterprise($validatedMessage);
    if ($cachedResponse) {
        error_log("Enterprise cache hit - returning optimized cached response");
        
        // Record usage and save to history
        saveChatHistoryEnterprise($pdo, $deviceHash, $validatedMessage, true, 'text', null);
        saveChatHistoryEnterprise($pdo, $deviceHash, $cachedResponse, false, 'text', null);
        recordUsageEnterprise($pdo, $deviceHash, 'text');
        
        // SIMPLE response format for Android compatibility
        echo json_encode([
            'success' => true,
            'response' => $cachedResponse
        ]);
        exit;
    }

    // ENTERPRISE PIPELINE - Step 8: User Context Retrieval
    error_log("STEP 8: Enterprise user context retrieval...");
    $userContext = getUserContextEnterprise($pdo, $deviceHash);
    error_log("User context retrieved: experience=" . $userContext['experience_level'] . ", garden=" . $userContext['garden_type']);

    // ENTERPRISE PIPELINE - Step 9: Enhanced AI Processing
    error_log("STEP 9: Enterprise AI processing with context awareness...");
    sendProgressUpdate('thinking', 'Mă gândesc la răspuns...');
    $response = getEnhancedAIResponseEnterprise($validatedMessage, $contentAnalysis, $userContext);
    error_log("Enterprise AI response generated successfully (length: " . strlen($response) . ")");

    // ENTERPRISE PIPELINE - Step 10: Response Caching
    error_log("STEP 10: Caching enterprise response...");
    sendProgressUpdate('writing', 'Scriu răspunsul...');
    cacheResponseEnterprise($validatedMessage, $response);

    // ENTERPRISE PIPELINE - Step 11: Data Persistence
    error_log("STEP 11: Enterprise data persistence...");
    saveChatHistoryEnterprise($pdo, $deviceHash, $validatedMessage, true, 'text', null);
    saveChatHistoryEnterprise($pdo, $deviceHash, $response, false, 'text', null);

    // ENTERPRISE PIPELINE - Step 12: Usage Recording
    error_log("STEP 12: Enterprise usage recording...");
    recordUsageEnterprise($pdo, $deviceHash, 'text');

    // ENTERPRISE PIPELINE - Step 13: Analytics (Async)
    error_log("STEP 13: Enterprise analytics tracking...");
    trackUserEngagementEnterprise($pdo, $deviceHash, $validatedMessage, $response, $contentAnalysis);

    // ENTERPRISE PIPELINE - Step 14: User Context Update
    error_log("STEP 14: Enterprise user context update...");
    updateUserContextEnterprise($pdo, $deviceHash, $validatedMessage, $contentAnalysis);

    // ENTERPRISE PIPELINE - Step 15: Memory Cleanup
    if (function_exists('gc_collect_cycles')) {
        $collected = gc_collect_cycles();
        error_log("Enterprise memory cleanup: $collected cycles collected");
    }

    error_log("Enterprise text processing completed successfully");
    error_log("Final memory usage: " . memory_get_usage(true) / 1024 / 1024 . " MB");
    error_log("Total processing time: " . round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . "ms");
    
    // SIMPLE response format (Android compatible)
    echo json_encode([
        'success' => true,
        'response' => $response
    ]);

} catch (Exception $e) {
    error_log("ENTERPRISE ERROR in process-text.php: " . $e->getMessage());
    error_log("Error file: " . $e->getFile() . " Line: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("Memory usage at error: " . memory_get_usage(true) / 1024 / 1024 . " MB");
    
    // Enterprise error cleanup
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
    
    // Enhanced error categorization
    $errorCategory = categorizeErrorEnterprise($e->getMessage());
    error_log("Enterprise error category: " . $errorCategory);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
// ENTERPRISE TEXT VALIDATION FUNCTION
function validateTextInputEnterprise($message) {
    try {
        error_log("Starting enterprise text validation...");
        
        // Null and empty checks
        if ($message === null || $message === '') {
            throw new Exception('Mesajul nu poate fi gol. Scrieți o întrebare despre grădinărit.');
        }
        
        // Convert to string and trim
        $message = trim((string) $message);
        
        // Length validation
        if (strlen($message) < 3) {
            error_log("Enterprise validation: Message too short (" . strlen($message) . " chars)");
            throw new Exception('Mesajul este prea scurt. Scrieți cel puțin 3 caractere pentru o întrebare completă.');
        }
        
        if (strlen($message) > 2000) {
            error_log("Enterprise validation: Message too long (" . strlen($message) . " chars)");
            throw new Exception('Mesajul este prea lung. Maxim 2000 de caractere permise pentru o procesare optimă.');
        }
        
        // UTF-8 encoding validation
        if (!mb_check_encoding($message, 'UTF-8')) {
            error_log("Enterprise validation: Invalid UTF-8 encoding detected");
            throw new Exception('Mesajul conține caractere invalide. Folosiți doar text normal în română sau engleză.');
        }
        
        // Spam detection - excessive repetition
        $words = preg_split('/\s+/', $message);
        $wordCount = array_count_values($words);
        $maxRepeats = max($wordCount);
        $totalWords = count($words);
        
        if ($totalWords > 5 && $maxRepeats > ($totalWords * 0.6)) {
            error_log("Enterprise validation: Excessive word repetition detected (max: $maxRepeats of $totalWords)");
            throw new Exception('Mesajul conține prea multe cuvinte repetate. Reformulați întrebarea pentru o analiză mai bună.');
        }
        
        // Character validation for Romanian gardening context
        if (!preg_match('/^[a-zA-Z0-9\săâîșțĂÂÎȘȚ\s\.,!?\-\(\)\'"]+$/u', $message)) {
            error_log("Enterprise validation: Invalid characters detected");
            throw new Exception('Mesajul conține caractere nevalide. Folosiți doar litere, cifre și punctuație normală.');
        }
        
        error_log("Enterprise text validation passed successfully");
        return $message;
        
    } catch (Exception $e) {
        error_log("Enterprise text validation failed: " . $e->getMessage());
        throw $e;
    }
}

// ENTERPRISE SECURITY SCANNING FUNCTION
function performSecurityScanEnterprise($message) {
    try {
        error_log("Starting enterprise security scan...");
        
        $lowerMessage = strtolower($message);
        
        // SQL Injection Detection
        $sqlPatterns = [
            '/select\s+.*\s+from/i',
            '/insert\s+into/i',
            '/update\s+.*\s+set/i',
            '/delete\s+from/i',
            '/drop\s+table/i',
            '/union\s+select/i',
            '/exec\s+sp_/i',
            '/xp_cmdshell/i',
            '/\'.*or.*\'/i',
            '/\".*or.*\"/i',
            '/;\s*--/i',
            '/\/\*.*\*\//i'
        ];
        
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $lowerMessage)) {
                error_log("Enterprise security: SQL injection pattern detected");
                throw new Exception('Mesajul conține conținut suspect de tip SQL. Pentru siguranță, reformulați întrebarea despre grădinărit.');
            }
        }
        
        // XSS Attack Detection
        $xssPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/onclick\s*=/i',
            '/onmouseover\s*=/i',
            '/eval\s*\(/i',
            '/document\.cookie/i',
            '/window\.location/i',
            '/alert\s*\(/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        ];
        
        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $lowerMessage)) {
                error_log("Enterprise security: XSS pattern detected");
                throw new Exception('Mesajul conține cod suspect de tip JavaScript. Scrieți doar întrebări normale despre grădinărit.');
            }
        }
        
        // Command Injection Detection
        $commandPatterns = [
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i',
            '/file_get_contents\s*\(/i',
            '/curl_exec\s*\(/i',
            '/`[^`]*`/i',
            '/\$\([^)]*\)/i',
            '/&&/i',
            '/\|\|/i',
            '/;\s*rm\s/i',
            '/;\s*cat\s/i',
            '/;\s*ls\s/i'
        ];
        
        foreach ($commandPatterns as $pattern) {
            if (preg_match($pattern, $lowerMessage)) {
                error_log("Enterprise security: Command injection pattern detected");
                throw new Exception('Mesajul conține comenzi de sistem nevalide. Scrieți doar întrebări despre plante și grădinărit.');
            }
        }
        
        // URL and Link Detection
        $urlCount = 0;
        $urlPatterns = [
            '/https?:\/\//i',
            '/ftp:\/\//i',
            '/www\./i',
            '/\.(com|org|net|ro|eu)/i'
        ];
        
        foreach ($urlPatterns as $pattern) {
            if (preg_match($pattern, $lowerMessage)) {
                $urlCount++;
            }
        }
        
        if ($urlCount > 2) {
            error_log("Enterprise security: Multiple URLs detected ($urlCount)");
            throw new Exception('Mesajul conține prea multe link-uri. Scrieți doar întrebări despre grădinărit fără link-uri externe.');
        }
        
        // Profanity Detection (Romanian context)
        $profanityPatterns = [
            '/\b(prost|idiot|fraier|muist|cacat|pula|futut)\b/i',
            '/\b(fuck|shit|damn|bitch|asshole)\b/i'
        ];
        
        foreach ($profanityPatterns as $pattern) {
            if (preg_match($pattern, $lowerMessage)) {
                error_log("Enterprise security: Profanity detected");
                throw new Exception('Mesajul conține limbaj nepotrivit. Vă rugăm să fiți respectuos în întrebările despre grădinărit.');
            }
        }
        
        error_log("Enterprise security scan passed successfully");
        return true;
        
    } catch (Exception $e) {
        error_log("Enterprise security scan failed: " . $e->getMessage());
        throw $e;
    }
}

// ENTERPRISE CONTENT ANALYSIS FUNCTION
function analyzeContentEnterprise($message) {
    try {
        error_log("Starting enterprise content analysis...");
        
        $lowerMessage = strtolower($message);
        $analysis = [
            'type' => 'general',
            'confidence' => 0.5,
            'topics' => [],
            'urgency' => 'normal',
            'season_relevant' => false,
            'plant_mentioned' => false,
            'problem_type' => null,
            'romanian_context' => false
        ];
        
        // Romanian Plant Detection
        $romanianPlants = [
            'tomate' => 'tomato', 'rosii' => 'tomato',
            'castraveti' => 'cucumber', 'castravet' => 'cucumber',
            'ardei' => 'pepper', 'paprika' => 'pepper',
            'vinete' => 'eggplant', 'patlagele' => 'eggplant',
            'salata' => 'lettuce', 'laitue' => 'lettuce',
            'ceapa' => 'onion', 'zwiebel' => 'onion',
            'usturoi' => 'garlic', 'ail' => 'garlic',
            'morcov' => 'carrot', 'carotte' => 'carrot',
            'ridichi' => 'radish', 'radis' => 'radish',
            'spanac' => 'spinach', 'épinard' => 'spinach',
            'patrunjel' => 'parsley', 'persil' => 'parsley',
            'marar' => 'dill', 'aneth' => 'dill',
            'busuioc' => 'basil', 'basilic' => 'basil',
            'rozmarinul' => 'rosemary', 'romarin' => 'rosemary',
            'trandafiri' => 'roses', 'rosa' => 'roses',
            'garoafe' => 'carnations', 'garofita' => 'carnations',
            'lalele' => 'tulips', 'tulipa' => 'tulips'
        ];
        
        foreach ($romanianPlants as $romanian => $english) {
            if (strpos($lowerMessage, $romanian) !== false) {
                $analysis['plant_mentioned'] = true;
                $analysis['romanian_context'] = true;
                $analysis['topics'][] = $romanian;
                $analysis['confidence'] += 0.3;
                break;
            }
        }
        
        // Problem Type Classification
        $problemTypes = [
            'disease' => ['boala', 'bolnav', 'disease', 'sick', 'infectie', 'putrezire', 'mucegai', 'ciuperca'],
            'pest' => ['insecte', 'viermi', 'pests', 'bugs', 'afide', 'gandaci', 'limacsi', 'melci'],
            'watering' => ['apa', 'udat', 'water', 'watering', 'uscat', 'inundat', 'irigatii'],
            'nutrition' => ['ingrasamant', 'fertilizer', 'nutrients', 'galben', 'yellow', 'nutritie'],
            'growth' => ['crestere', 'growth', 'dezvoltare', 'mare', 'mic', 'lent'],
            'flowering' => ['flori', 'flowers', 'inflorire', 'boboci', 'buds', 'inmultire'],
            'harvesting' => ['recolta', 'harvest', 'cules', 'copt', 'ripe', 'matur']
        ];
        
        foreach ($problemTypes as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($lowerMessage, $keyword) !== false) {
                    $analysis['problem_type'] = $type;
                    $analysis['type'] = 'problem_solving';
                    $analysis['confidence'] += 0.4;
                    break 2;
                }
            }
        }
        
        // Urgency Detection
        $urgentKeywords = ['urgent', 'repede', 'rapid', 'emergency', 'help', 'ajutor', 'muribund', 'dying', 'mort'];
        foreach ($urgentKeywords as $urgent) {
            if (strpos($lowerMessage, $urgent) !== false) {
                $analysis['urgency'] = 'high';
                $analysis['confidence'] += 0.2;
                break;
            }
        }
        
        // Seasonal Relevance
        $currentMonth = date('n');
        $seasonalKeywords = [
            'primavara' => [3, 4, 5], 'spring' => [3, 4, 5], 'semanare' => [3, 4, 5],
            'vara' => [6, 7, 8], 'summer' => [6, 7, 8], 'canicula' => [6, 7, 8],
            'toamna' => [9, 10, 11], 'autumn' => [9, 10, 11], 'recolta' => [9, 10, 11],
            'iarna' => [12, 1, 2], 'winter' => [12, 1, 2], 'protectie' => [12, 1, 2]
        ];
        
        foreach ($seasonalKeywords as $keyword => $months) {
            if (strpos($lowerMessage, $keyword) !== false && in_array($currentMonth, $months)) {
                $analysis['season_relevant'] = true;
                $analysis['confidence'] += 0.2;
                break;
            }
        }
        
        // Question Type Detection
        $questionWords = ['ce', 'cum', 'cand', 'unde', 'de ce', 'what', 'how', 'when', 'where', 'why'];
        foreach ($questionWords as $qword) {
            if (strpos($lowerMessage, $qword) !== false) {
                $analysis['type'] = 'question';
                $analysis['confidence'] += 0.2;
                break;
            }
        }
        
        // Final confidence adjustment
        $analysis['confidence'] = min(1.0, $analysis['confidence']);
        
        error_log("Enterprise content analysis completed: " . json_encode($analysis));
        return $analysis;
        
    } catch (Exception $e) {
        error_log("Enterprise content analysis failed: " . $e->getMessage());
        // Return default analysis on failure
        return [
            'type' => 'general',
            'confidence' => 0.3,
            'topics' => [],
            'urgency' => 'normal',
            'season_relevant' => false,
            'plant_mentioned' => false,
            'problem_type' => null,
            'romanian_context' => false
        ];
    }
}

// ENTERPRISE ERROR CATEGORIZATION FUNCTION
function categorizeErrorEnterprise($errorMessage) {
    $lowerError = strtolower($errorMessage);
    
    if (strpos($lowerError, 'conexiune') !== false || strpos($lowerError, 'connection') !== false) {
        return 'connection_error';
    } elseif (strpos($lowerError, 'limita') !== false || strpos($lowerError, 'limit') !== false) {
        return 'usage_limit';
    } elseif (strpos($lowerError, 'suspect') !== false || strpos($lowerError, 'security') !== false) {
        return 'security_violation';
    } elseif (strpos($lowerError, 'baza de date') !== false || strpos($lowerError, 'database') !== false) {
        return 'database_error';
    } elseif (strpos($lowerError, 'api') !== false || strpos($lowerError, 'openai') !== false) {
        return 'api_error';
    } elseif (strpos($lowerError, 'timeout') !== false || strpos($lowerError, 'timp') !== false) {
        return 'timeout_error';
    } else {
        return 'general_error';
    }
}
// ENTERPRISE AI RESPONSE FUNCTION with context awareness
function getEnhancedAIResponseEnterprise($message, $contentAnalysis, $userContext) {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        error_log("OpenAI API key not found for enterprise text processing");
        throw new Exception('Serviciul de răspunsuri AI nu este disponibil momentan');
    }

    error_log("Creating enterprise AI prompt with full context awareness...");
    error_log("User experience: " . $userContext['experience_level']);
    error_log("Content type: " . $contentAnalysis['type']);
    error_log("Problem type: " . ($contentAnalysis['problem_type'] ?? 'none'));
    error_log("Urgency level: " . $contentAnalysis['urgency']);

    // Get current season context
    $currentMonth = date('n');
    $currentSeason = getSeasonEnterprise($currentMonth);
    $seasonalContext = getSeasonalContextEnterprise($currentSeason, $currentMonth);

    // Build comprehensive system prompt
    $systemPrompt = "Ești un expert în grădinărit din România cu 30 de ani experiență, specializat în sfaturi practice pentru clima continentală românească.

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

ANALIZA ÎNTREBĂRII:
- Tipul: {$contentAnalysis['type']}
- Urgența: {$contentAnalysis['urgency']}
- Problemă: " . ($contentAnalysis['problem_type'] ?? 'generală') . "
- Context românesc: " . ($contentAnalysis['romanian_context'] ? 'DA' : 'NU') . "

PERSONALITATEA TA ADAPTATĂ:
- Pentru începători: Explici pas cu pas, termeni simpli, exemple concrete
- Pentru intermediari: Dai alternative și opțiuni multiple
- Pentru avansați: Incluzi detalii tehnice și metode profesionale
- Pentru urgențe: Prioritizezi soluțiile rapide și eficiente

REGULI IMPORTANTE:
- Adaptezi răspunsul la nivelul și contextul utilizatorului
- Incluzi recomandări sezoniere relevante pentru România
- Menționezi produse și tratamente disponibile în România
- Dai sfaturi pentru clima continentală românească
- Folosești experiența anterioară a utilizatorului când e relevantă
- Răspunsurile să fie între 150-350 de cuvinte, clare și actionabile
- Eviți asteriscuri, numere în paranteză sau formatare specială
- Incluzi trucuri practice din experiența ta de 30 de ani

STRUCTURA RĂSPUNSULUI:
1. Răspuns direct la întrebare
2. Sfaturi practice pentru situația actuală
3. Recomandări sezoniere (dacă e relevant)
4. Sfaturi de prevenire pentru viitor";

    // Build enhanced user prompt
    $enhancedPrompt = buildEnhancedPromptEnterprise($message, $contentAnalysis, $userContext, $seasonalContext);

    error_log("Sending enterprise AI request to OpenAI...");

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey,
        'User-Agent: GospodApp-Enterprise/2.0 (Romanian Gardening Assistant)'
    ]);

    $requestData = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $enhancedPrompt]
        ],
        'max_tokens' => 600,  // Optimized for Starter plan
        'temperature' => $contentAnalysis['urgency'] === 'high' ? 0.3 : 0.7,
        'top_p' => 0.9,
        'frequency_penalty' => 0.1,
        'presence_penalty' => 0.1
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    error_log("OpenAI enterprise response code: $httpCode");
    if ($httpCode !== 200) {
        error_log("OpenAI enterprise error: " . substr($response, 0, 500));
        if ($curlError) {
            error_log("cURL error for OpenAI enterprise: " . $curlError);
        }
    } else {
        error_log("OpenAI enterprise response received (length: " . strlen($response) . ")");
    }
    
    curl_close($ch);

    // Enhanced error handling
    if ($curlError) {
        throw new Exception('Eroare de conexiune la serviciul AI. Verificați conexiunea și încercați din nou.');
    }

    if ($httpCode === 429) {
        throw new Exception('Serviciul AI este temporar suprasolicitat. Încercați din nou în câteva minute.');
    }

    if ($httpCode !== 200) {
        throw new Exception('Nu am putut genera un răspuns momentan. Încercați din nou.');
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        error_log("OpenAI enterprise API error: " . json_encode($data['error']));
        throw new Exception('Nu am putut genera un răspuns. Reformulați întrebarea și încercați din nou.');
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        error_log("OpenAI enterprise response missing content");
        throw new Exception('Nu am putut genera un răspuns complet. Încercați cu o întrebare mai specifică.');
    }

    $content = $data['choices'][0]['message']['content'];
    error_log("OpenAI enterprise content generated successfully (length: " . strlen($content) . ")");
    
    return cleanForTTSEnterprise($content);
}

// ENTERPRISE SEASONAL INTELLIGENCE FUNCTIONS
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

function buildEnhancedPromptEnterprise($message, $contentAnalysis, $userContext, $seasonalContext) {
    $prompt = "Întrebarea utilizatorului: \"$message\"\n\n";
    
    if ($contentAnalysis['urgency'] === 'high') {
        $prompt .= "🚨 ATENȚIE: Aceasta pare să fie o situație urgentă pentru plante. Prioritizează soluțiile rapide și eficiente!\n\n";
    }
    
    if ($contentAnalysis['plant_mentioned']) {
        $prompt .= "Plante menționate: " . implode(', ', $contentAnalysis['topics']) . "\n";
    }
    
    if ($contentAnalysis['problem_type']) {
        $prompt .= "Tipul problemei identificate: {$contentAnalysis['problem_type']}\n";
    }
    
    if ($contentAnalysis['season_relevant']) {
        $prompt .= "Context sezonier relevant: {$seasonalContext['activities']}\n";
    }
    
    if ($contentAnalysis['romanian_context']) {
        $prompt .= "Context românesc: DA - folosește termeni și produse disponibile în România\n";
    }
    
    $prompt .= "\nAdaptează răspunsul pentru:\n";
    $prompt .= "- Nivel experiență: {$userContext['experience_level']}\n";
    $prompt .= "- Tip grădină: {$userContext['garden_type']}\n";
    $prompt .= "- Sezonul actual și activitățile specifice\n";
    $prompt .= "- Produsele și tehnicile disponibile în România\n";
    $prompt .= "- Clima continentală românească\n";
    
    return $prompt;
}

// ENTERPRISE RESPONSE CACHING FUNCTIONS
function getCachedResponseEnterprise($message) {
    try {
        $cacheKey = 'enterprise_' . md5(strtolower(trim($message)));
        $cacheFile = '/tmp/' . $cacheKey . '.json';
        
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            $cacheAge = time() - $cacheData['timestamp'];
            
            // Cache expires after 12 hours (enterprise cache)
            if ($cacheAge < 43200) {
                error_log("Enterprise cache hit for: " . substr($cacheKey, 0, 15));
                return $cacheData['response'];
            } else {
                unlink($cacheFile);
                error_log("Enterprise cache expired and removed: " . substr($cacheKey, 0, 15));
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Enterprise cache retrieval error: " . $e->getMessage());
        return null;
    }
}

function cacheResponseEnterprise($message, $response) {
    try {
        $cacheKey = 'enterprise_' . md5(strtolower(trim($message)));
        $cacheFile = '/tmp/' . $cacheKey . '.json';
        
        $cacheData = [
            'message' => $message,
            'response' => $response,
            'timestamp' => time(),
            'version' => 'enterprise_v2'
        ];
        
        file_put_contents($cacheFile, json_encode($cacheData));
        error_log("Enterprise response cached: " . substr($cacheKey, 0, 15));
        
    } catch (Exception $e) {
        error_log("Enterprise cache storage error: " . $e->getMessage());
    }
}

// ENTERPRISE USER CONTEXT MANAGEMENT
function getUserContextEnterprise($pdo, $deviceHash) {
    try {
        error_log("Retrieving enterprise user context for: $deviceHash");
        
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
        
        $context = [
            'experience_level' => $profile['experience_level'] ?? 'începător',
            'garden_type' => $profile['garden_type'] ?? 'general',
            'region' => $profile['region'] ?? 'România',
            'favorite_plants' => json_decode($profile['favorite_plants'] ?? '[]', true),
            'total_questions' => $profile['total_questions'] ?? 0,
            'premium' => (bool)($profile['premium'] ?? false)
        ];
        
        error_log("Enterprise user context retrieved successfully");
        return $context;
        
    } catch (Exception $e) {
        error_log("Enterprise user context error: " . $e->getMessage());
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
        
        error_log("Enterprise user profile created for: $deviceHash");
        
    } catch (Exception $e) {
        error_log("Enterprise user profile creation error: " . $e->getMessage());
    }
}

// ENTERPRISE TEXT CLEANING FUNCTION
function cleanForTTSEnterprise($text) {
    if ($text === null || $text === '') {
        return '';
    }
    
    $text = (string) $text;
    
    // Enhanced cleaning for enterprise TTS
    $text = preg_replace('/\*+/', '', $text);                    // Remove asterisks
    $text = preg_replace('/^\d+\.\s*/m', '', $text);            // Remove numbered lists
    $text = preg_replace('/^[\-\*\+]\s*/m', '', $text);         // Remove bullet points
    $text = preg_replace('/\s+/', ' ', $text);                  // Multiple spaces to single
    $text = preg_replace('/[#@$%^&(){}|\\\\]/', '', $text);     // Remove special chars
    $text = preg_replace('/\s*([,.!?;:])\s*/', '$1 ', $text);   // Fix punctuation spacing
    $text = preg_replace('/\s*\(\d+%\)\s*/', ' ', $text);       // Remove percentages
    $text = preg_replace('/\s*\[.*?\]\s*/', ' ', $text);        // Remove square brackets
    $text = preg_replace('/🚨|⚡|✅|❌|🌱/', '', $text);          // Remove emojis
    
    return trim($text);
}
// ENTERPRISE DATABASE CONNECTION FUNCTION
function connectToDatabaseEnterprise() {
    try {
        $host = getenv('DB_HOST');
        $dbname = getenv('DB_NAME');
        $username = getenv('DB_USER');
        $password = getenv('DB_PASS');
        
        error_log("Enterprise DB connection attempt to: $host");
        error_log("Enterprise DB name: $dbname");
        error_log("Enterprise DB username: $username");
        error_log("Enterprise DB password exists: " . (getenv('DB_PASS') ? 'YES' : 'NO'));
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 15,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_PERSISTENT => false
        ]);
        
        // Test connection
        $pdo->query("SELECT 1");
        
        error_log("Enterprise DB connection successful");
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Enterprise DB connection failed: " . $e->getMessage());
        error_log("Enterprise PDO Error Code: " . $e->getCode());
        throw new Exception('Nu pot conecta la baza de date momentan. Încercați din nou în câteva secunde.');
    }
}

// ENTERPRISE USAGE LIMITS CHECK
function checkUsageLimitsEnterprise($pdo, $deviceHash, $type) {
    try {
        $today = date('Y-m-d');
        error_log("Enterprise usage limits check for device: $deviceHash on $today");
        
        $stmt = $pdo->prepare("
            SELECT text_count, image_count, premium, extra_questions 
            FROM usage_tracking 
            WHERE device_hash = ? AND date = ?
        ");
        $stmt->execute([$deviceHash, $today]);
        $usage = $stmt->fetch();

        if (!$usage) {
            error_log("Enterprise no usage record found, creating new for device: $deviceHash");
            $stmt = $pdo->prepare("
                INSERT INTO usage_tracking (device_hash, date, text_count, image_count, premium, extra_questions) 
                VALUES (?, ?, 0, 0, 0, 0)
            ");
            $stmt->execute([$deviceHash, $today]);
            $usage = ['text_count' => 0, 'image_count' => 0, 'premium' => 0, 'extra_questions' => 0];
            error_log("Enterprise new usage record created successfully");
        } else {
            error_log("Enterprise usage record found: " . json_encode($usage));
        }

        if ($type === 'text') {
            $limit = $usage['premium'] ? 100 : 10;
            $totalUsed = $usage['text_count'] - $usage['extra_questions'];
            $remaining = $limit - $totalUsed;
            
            error_log("Enterprise text usage: used $totalUsed, limit $limit, remaining $remaining, extra {$usage['extra_questions']}");
            
            if ($totalUsed >= $limit) {
                if ($usage['premium']) {
                    error_log("Enterprise premium user hit text limit");
                    throw new Exception('Ați atins limita zilnică de 100 întrebări premium. Impresionant! Reveniți mâine pentru mai multe sfaturi de grădinărit.');
                } else {
                    error_log("Enterprise free user hit text limit");
                    throw new Exception('Ați folosit toate cele 10 întrebări gratuite de astăzi! 🌱 Upgradeați la Premium pentru întrebări nelimitate sau urmăriți o reclamă pentru 3 întrebări extra.');
                }
            }
        }
        
    } catch (PDOException $e) {
        error_log("Enterprise DB error in usage limits: " . $e->getMessage());
        throw new Exception('Eroare temporară la verificarea limitelor. Încercați din nou în câteva secunde.');
    }
}

// ENTERPRISE RECORD USAGE
function recordUsageEnterprise($pdo, $deviceHash, $type) {
    try {
        $today = date('Y-m-d');
        $field = $type === 'image' ? 'image_count' : 'text_count';
        
        error_log("Enterprise recording usage for device: $deviceHash, type: $type");
        
        $stmt = $pdo->prepare("
            UPDATE usage_tracking 
            SET $field = $field + 1, last_activity = NOW() 
            WHERE device_hash = ? AND date = ?
        ");
        $result = $stmt->execute([$deviceHash, $today]);
        
        if ($result) {
            error_log("Enterprise usage recorded successfully");
            updateDailyStatisticsEnterprise($pdo, $type);
        } else {
            error_log("Enterprise failed to record usage");
        }
        
    } catch (PDOException $e) {
        error_log("Enterprise DB error in record usage: " . $e->getMessage());
        throw new Exception('Eroare la înregistrarea utilizării.');
    }
}

// ENTERPRISE SAVE CHAT HISTORY
function saveChatHistoryEnterprise($pdo, $deviceHash, $messageText, $isUserMessage, $messageType, $imageData) {
    try {
        error_log("Enterprise saving chat history for device: $deviceHash, type: $messageType, user: " . ($isUserMessage ? 'YES' : 'NO'));
        
        // Handle large content
        if (strlen($messageText) > 5000) {
            $messageText = substr($messageText, 0, 5000) . '...[TRUNCATED]';
            error_log("Enterprise message truncated due to length");
        }
        
        if ($messageType === 'image' && $imageData && strlen($imageData) > 1000000) {
            error_log("Enterprise large image detected, storing compressed version");
            $imageData = substr($imageData, 0, 100000) . '...[TRUNCATED]';
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
            error_log("Enterprise chat history saved successfully");
            cleanOldChatHistoryEnterprise($pdo, $deviceHash);
        } else {
            error_log("Enterprise failed to save chat history");
        }
        
    } catch (PDOException $e) {
        error_log("Enterprise DB error in save chat history: " . $e->getMessage());
        // Don't throw exception - chat history is not critical
        error_log("Enterprise continuing despite chat history save failure");
    }
}

// ENTERPRISE ANALYTICS TRACKING
function trackUserEngagementEnterprise($pdo, $deviceHash, $message, $response, $contentAnalysis) {
    try {
        error_log("Enterprise tracking user engagement for device: $deviceHash");
        
        $stmt = $pdo->prepare("
            INSERT INTO user_analytics 
            (device_hash, message_length, response_length, content_type, urgency_level, 
             plant_mentioned, problem_type, confidence_score, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $deviceHash,
            strlen($message),
            strlen($response),
            $contentAnalysis['type'],
            $contentAnalysis['urgency'],
            $contentAnalysis['plant_mentioned'] ? 1 : 0,
            $contentAnalysis['problem_type'],
            $contentAnalysis['confidence']
        ]);
        
        error_log("Enterprise user engagement tracked successfully");
        updatePopularTopicsEnterprise($pdo, $contentAnalysis);
        
    } catch (PDOException $e) {
        error_log("Enterprise DB error in user engagement tracking: " . $e->getMessage());
        // Don't throw exception - analytics failure shouldn't break the flow
    }
}

// ENTERPRISE USER CONTEXT UPDATE
function updateUserContextEnterprise($pdo, $deviceHash, $message, $contentAnalysis) {
    try {
        if ($contentAnalysis['plant_mentioned'] && !empty($contentAnalysis['topics'])) {
            $stmt = $pdo->prepare("SELECT favorite_plants FROM user_profiles WHERE device_hash = ?");
            $stmt->execute([$deviceHash]);
            $currentPlants = json_decode($stmt->fetchColumn() ?? '[]', true);
            
            $newPlants = array_unique(array_merge($currentPlants, $contentAnalysis['topics']));
            
            $stmt = $pdo->prepare("
                UPDATE user_profiles 
                SET favorite_plants = ?, last_activity = NOW(), total_questions = total_questions + 1
                WHERE device_hash = ?
            ");
            $stmt->execute([json_encode($newPlants), $deviceHash]);
            
            error_log("Enterprise user context updated with plants: " . implode(', ', $contentAnalysis['topics']));
        }
        
    } catch (Exception $e) {
        error_log("Enterprise error updating user context: " . $e->getMessage());
    }
}

// ENTERPRISE ANTI-BOT PROTECTION
function checkRateLimitsEnterprise($deviceHash) {
    try {
        $rateLimitFile = '/tmp/rate_limit_enterprise_' . $deviceHash . '.txt';
        $currentTime = time();
        
        error_log("Enterprise checking rate limits for device: $deviceHash");
        
        if (file_exists($rateLimitFile)) {
            $requests = json_decode(file_get_contents($rateLimitFile), true) ?: [];
            $requests = array_filter($requests, function($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < 60; // Last minute
            });
            error_log("Enterprise found " . count($requests) . " requests in last minute");
        } else {
            $requests = [];
            error_log("Enterprise no previous rate limit file found");
        }
        
        // Enterprise allows more requests (15 per minute vs 10 for basic)
        if (count($requests) >= 15) {
            error_log("Enterprise rate limit exceeded for device: $deviceHash");
            throw new Exception('Prea multe întrebări într-un timp scurt. Încercați din nou în 1 minut pentru a preveni spam-ul.');
        }
        
        $requests[] = $currentTime;
        file_put_contents($rateLimitFile, json_encode($requests));
        error_log("Enterprise rate limit check passed, requests this minute: " . count($requests));
        
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Prea multe întrebări') !== false) {
            throw $e;
        }
        error_log("Enterprise error in rate limit check: " . $e->getMessage());
    }
}

function detectSuspiciousActivityEnterprise($deviceHash, $message) {
    try {
        $messageHash = md5(strtolower(trim($message)));
        $suspiciousFile = '/tmp/suspicious_enterprise_' . $deviceHash . '_' . $messageHash . '.txt';
        $currentTime = time();
        
        error_log("Enterprise checking suspicious activity for device: $deviceHash");
        
        if (file_exists($suspiciousFile)) {
            $data = json_decode(file_get_contents($suspiciousFile), true);
            $count = $data['count'] ?? 0;
            $lastTime = $data['last_time'] ?? 0;
            
            if (($currentTime - $lastTime) > 3600) {
                $count = 0;
            }
            
            $count++;
            
            if ($count > 5) { // Enterprise allows more repeats
                error_log("Enterprise suspicious activity detected - repeated message from device: $deviceHash");
                throw new Exception('Ați trimis același mesaj prea des. Încercați o întrebare diferită sau reformulați.');
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
        
        error_log("Enterprise suspicious activity check passed for device: $deviceHash");
        
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'același mesaj') !== false) {
            throw $e;
        }
        error_log("Enterprise error in suspicious activity check: " . $e->getMessage());
    }
}

// ENTERPRISE HELPER FUNCTIONS
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
        error_log("Enterprise error updating daily statistics: " . $e->getMessage());
    }
}

function updatePopularTopicsEnterprise($pdo, $contentAnalysis) {
    try {
        if (!empty($contentAnalysis['topics'])) {
            foreach ($contentAnalysis['topics'] as $topic) {
                $stmt = $pdo->prepare("
                    INSERT INTO popular_topics (topic, count, last_mentioned) 
                    VALUES (?, 1, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                    count = count + 1, 
                    last_mentioned = NOW()
                ");
                $stmt->execute([$topic]);
            }
        }
        
        if ($contentAnalysis['problem_type']) {
            $stmt = $pdo->prepare("
                INSERT INTO popular_topics (topic, count, last_mentioned) 
                VALUES (?, 1, NOW()) 
                ON DUPLICATE KEY UPDATE 
                count = count + 1, 
                last_mentioned = NOW()
            ");
            $stmt->execute(['problem_' . $contentAnalysis['problem_type']]);
        }
        
    } catch (PDOException $e) {
        error_log("Enterprise error updating popular topics: " . $e->getMessage());
    }
}

function cleanOldChatHistoryEnterprise($pdo, $deviceHash) {
    try {
        // Keep only the last 150 messages per user (enterprise gets more history)
        $stmt = $pdo->prepare("
            DELETE FROM chat_history 
            WHERE device_hash = ? 
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM chat_history 
                    WHERE device_hash = ? 
                    ORDER BY created_at DESC 
                    LIMIT 150
                ) AS recent_messages
            )
        ");
        $stmt->execute([$deviceHash, $deviceHash]);
        
    } catch (PDOException $e) {
        error_log("Enterprise error cleaning old chat history: " . $e->getMessage());
    }
}

// ENTERPRISE MEMORY CLEANUP
function cleanupMemoryEnterprise() {
    try {
        if (function_exists('gc_collect_cycles')) {
            $collected = gc_collect_cycles();
            error_log("Enterprise garbage collection freed $collected cycles");
        }
        
        $tempDir = sys_get_temp_dir();
        $patterns = [
            '/rate_limit_enterprise_*.txt',
            '/suspicious_enterprise_*.txt',
            '/enterprise_*.json'
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
            error_log("Enterprise cleaned up $cleaned temporary files");
        }
        
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024;
        error_log("Enterprise final memory usage: {$memoryUsage} MB, Peak: {$peakMemory} MB");
        
    } catch (Exception $e) {
        error_log("Enterprise cleanup error: " . $e->getMessage());
    }
}

// Register cleanup function
register_shutdown_function('cleanupMemoryEnterprise');

?>

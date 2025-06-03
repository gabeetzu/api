<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// COMPREHENSIVE DEBUG: Enhanced logging for text processing
error_log("=== ENHANCED TEXT PROCESSING DEBUG START ===");
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
error_log("Request timestamp: " . date('Y-m-d H:i:s'));

$rawInput = file_get_contents('php://input');
error_log("Raw input length: " . strlen($rawInput));
error_log("Raw input preview: " . substr($rawInput, 0, 200) . "...");
error_log("Memory usage before processing: " . memory_get_usage(true) / 1024 / 1024 . " MB");
error_log("Server load: " . (function_exists('sys_getloadavg') ? implode(', ', sys_getloadavg()) : 'N/A'));
error_log("=== ENHANCED TEXT PROCESSING DEBUG END ===");

// Verify API Key with enhanced logging
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$expectedKey = getenv('API_SECRET_KEY');

error_log("API Key comparison - Received: " . substr($apiKey, 0, 10) . "... Expected: " . substr($expectedKey, 0, 10) . "...");

if ($apiKey !== $expectedKey) {
    error_log("API key mismatch - Authentication failed for text request");
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Acces neautorizat']));
}

error_log("API key verified successfully for text request");

// ENHANCED: Configure for optimal text processing
ini_set('max_execution_time', '120'); // 2 minutes for complex queries
ini_set('memory_limit', '256M'); // Sufficient for text processing
ini_set('max_input_time', '60');

error_log("PHP settings configured for enhanced text processing");

try {
    error_log("Starting enhanced text processing request...");
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // ENHANCED: Comprehensive input validation
    if (!$data || !isset($data['message']) || !isset($data['device_hash'])) {
        error_log("Invalid text request data - missing required fields");
        error_log("Data keys present: " . (is_array($data) ? implode(', ', array_keys($data)) : 'not an array'));
        throw new Exception('Date lipsă: Mesajul sau hash-ul dispozitivului nu au fost primite');
    }

    $message = $data['message'];
    $deviceHash = $data['device_hash'];

    error_log("Processing text message from device: $deviceHash");
    error_log("Message length: " . strlen($message));
    error_log("Message preview: " . substr($message, 0, 100) . "...");

    // STEP 1: COMPREHENSIVE TEXT VALIDATION
    error_log("Step 1: Validating text input...");
    $validatedMessage = validateTextInput($message);
    
    // STEP 2: SECURITY SCANNING
    error_log("Step 2: Performing security scan on text...");
    securityScanText($validatedMessage);

    // STEP 3: CONTENT ANALYSIS & CLASSIFICATION
    error_log("Step 3: Analyzing content type and intent...");
    $contentAnalysis = analyzeContentIntent($validatedMessage);
    error_log("Content analysis: " . json_encode($contentAnalysis));

    // STEP 4: ANTI-BOT PROTECTION
    error_log("Step 4: Checking rate limits for text request from device: $deviceHash");
    checkRateLimits($deviceHash);
    
    error_log("Step 5: Checking for suspicious text activity...");
    detectSuspiciousActivity($deviceHash, $validatedMessage);

    // STEP 5: DATABASE CONNECTION
    error_log("Step 6: Attempting database connection for text processing...");
    $pdo = connectToDatabase();
    error_log("Database connection successful for text processing");
    
    // STEP 6: USAGE LIMITS CHECK
    error_log("Step 7: Checking text usage limits...");
    checkUsageLimits($pdo, $deviceHash, 'text');

    // STEP 7: CHECK RESPONSE CACHE
    error_log("Step 8: Checking response cache...");
    $cachedResponse = getCachedResponse($validatedMessage);
    if ($cachedResponse) {
        error_log("Cache hit - returning cached response");
        
        // Still save to chat history and record usage
        saveChatHistory($pdo, $deviceHash, $validatedMessage, true, 'text', null);
        saveChatHistory($pdo, $deviceHash, $cachedResponse, false, 'text', null);
        recordUsage($pdo, $deviceHash, 'text');
        
        echo json_encode([
            'success' => true,
            'response' => $cachedResponse,
            'cached' => true,
            'processing_info' => [
                'content_type' => $contentAnalysis['type'],
                'confidence' => $contentAnalysis['confidence'],
                'cache_hit' => true
            ]
        ]);
        exit;
    }

    // STEP 8: GET USER CONTEXT
    error_log("Step 9: Retrieving user context and history...");
    $userContext = getUserContext($pdo, $deviceHash);
    error_log("User context retrieved: " . json_encode($userContext));

    // STEP 9: ENHANCED AI PROCESSING
    error_log("Step 10: Getting enhanced response from OpenAI...");
    $response = getEnhancedTextResponseFromOpenAI($validatedMessage, $contentAnalysis, $userContext);
    error_log("OpenAI enhanced response received successfully");

    // STEP 10: CACHE RESPONSE
    error_log("Step 11: Caching response for future use...");
    cacheResponse($validatedMessage, $response);

    // STEP 11: SAVE TO DATABASE
    error_log("Step 12: Saving message and response to chat history...");
    saveChatHistory($pdo, $deviceHash, $validatedMessage, true, 'text', null);
    saveChatHistory($pdo, $deviceHash, $response, false, 'text', null);

    // STEP 12: RECORD USAGE & ANALYTICS
    error_log("Step 13: Recording usage and analytics...");
    recordUsage($pdo, $deviceHash, 'text');
    trackUserEngagement($pdo, $deviceHash, $validatedMessage, $response, $contentAnalysis);

    // STEP 13: UPDATE USER CONTEXT
    error_log("Step 14: Updating user context...");
    updateUserContext($pdo, $deviceHash, $validatedMessage, $contentAnalysis);

    // STEP 14: CLEANUP MEMORY
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }

    error_log("Enhanced text processing completed successfully");
    error_log("Memory usage after processing: " . memory_get_usage(true) / 1024 / 1024 . " MB");
    
    echo json_encode([
        'success' => true,
        'response' => $response,
        'cached' => false,
        'processing_info' => [
            'content_type' => $contentAnalysis['type'],
            'confidence' => $contentAnalysis['confidence'],
            'user_level' => $userContext['experience_level'],
            'response_length' => strlen($response),
            'processing_time_ms' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2)
        ]
    ]);

} catch (Exception $e) {
    error_log("ERROR in enhanced process-text.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("Memory usage at error: " . memory_get_usage(true) / 1024 / 1024 . " MB");
    
    // Cleanup on error
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
    
    // Enhanced error categorization
    $errorCategory = categorizeError($e->getMessage());
    error_log("Error category: " . $errorCategory);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_category' => $errorCategory
    ]);
}
// COMPREHENSIVE TEXT INPUT VALIDATION FUNCTION
function validateTextInput($message) {
    try {
        error_log("Starting comprehensive text validation...");
        
        // Check if message is empty or null
        if (empty($message) || $message === null) {
            throw new Exception('Mesajul nu poate fi gol. Scrieți o întrebare despre grădinărit.');
        }
        
        // Convert to string and trim
        $message = trim((string) $message);
        
        // Check minimum length
        if (strlen($message) < 3) {
            error_log("Message too short: " . strlen($message) . " characters");
            throw new Exception('Mesajul este prea scurt. Scrieți cel puțin 3 caractere.');
        }
        
        // Check maximum length (prevent abuse)
        if (strlen($message) > 2000) {
            error_log("Message too long: " . strlen($message) . " characters");
            throw new Exception('Mesajul este prea lung. Maxim 2000 de caractere permise.');
        }
        
        // Validate encoding (ensure UTF-8)
        if (!mb_check_encoding($message, 'UTF-8')) {
            error_log("Invalid UTF-8 encoding detected");
            throw new Exception('Mesajul conține caractere invalide. Folosiți doar text normal.');
        }
        
        // Check for excessive repetition (spam detection)
        $words = explode(' ', $message);
        $wordCount = array_count_values($words);
        $maxRepeats = max($wordCount);
        $totalWords = count($words);
        
        if ($totalWords > 5 && $maxRepeats > ($totalWords * 0.5)) {
            error_log("Excessive word repetition detected: max repeats = $maxRepeats out of $totalWords words");
            throw new Exception('Mesajul conține prea multe cuvinte repetate. Reformulați întrebarea.');
        }
        
        // Check for excessive punctuation (spam indicator)
        $punctuationCount = preg_match_all('/[!?.,;:]/', $message);
        if ($punctuationCount > (strlen($message) * 0.3)) {
            error_log("Excessive punctuation detected: $punctuationCount marks");
            throw new Exception('Mesajul conține prea multe semne de punctuație.');
        }
        
        // Check for excessive uppercase (shouting)
        $uppercaseCount = preg_match_all('/[A-ZĂÂÎȘȚ]/', $message);
        $letterCount = preg_match_all('/[a-zA-ZăâîșțĂÂÎȘȚ]/', $message);
        if ($letterCount > 0 && ($uppercaseCount / $letterCount) > 0.7) {
            error_log("Excessive uppercase detected: $uppercaseCount/$letterCount");
            // Don't throw exception, just convert to proper case
            $message = mb_convert_case($message, MB_CASE_TITLE, 'UTF-8');
            error_log("Converted to proper case");
        }
        
        // Validate Romanian/English characters (gardening context)
        if (!preg_match('/^[a-zA-Z0-9\săâîșțĂÂÎȘȚ\s\.,!?\-\(\)]+$/u', $message)) {
            error_log("Invalid characters detected in message");
            throw new Exception('Mesajul conține caractere nevalide. Folosiți doar litere, cifre și punctuație normală.');
        }
        
        error_log("Text validation passed successfully");
        return $message;
        
    } catch (Exception $e) {
        error_log("Text validation failed: " . $e->getMessage());
        throw $e;
    }
}

// ADVANCED SECURITY SCANNING FUNCTION
function securityScanText($message) {
    try {
        error_log("Starting security scan of text message...");
        
        // Convert to lowercase for pattern matching
        $lowerMessage = strtolower($message);
        
        // Check for SQL injection patterns
        $sqlPatterns = [
            'select.*from',
            'insert.*into',
            'update.*set',
            'delete.*from',
            'drop.*table',
            'union.*select',
            'exec.*sp_',
            'xp_cmdshell',
            '\'.*or.*\'',
            '\".*or.*\"',
            ';.*--',
            '/*.*\*/'
        ];
        
        foreach ($sqlPatterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $lowerMessage)) {
                error_log("SQL injection pattern detected: $pattern");
                throw new Exception('Mesajul conține conținut suspect. Pentru siguranță, reformulați întrebarea.');
            }
        }
        
        // Check for XSS patterns
        $xssPatterns = [
            '<script',
            'javascript:',
            'onload=',
            'onerror=',
            'onclick=',
            'onmouseover=',
            'eval\(',
            'document\.cookie',
            'window\.location',
            'alert\(',
            '<iframe',
            '<object',
            '<embed'
        ];
        
        foreach ($xssPatterns as $pattern) {
            if (stripos($lowerMessage, $pattern) !== false) {
                error_log("XSS pattern detected: $pattern");
                throw new Exception('Mesajul conține cod suspect. Scrieți doar întrebări normale despre grădinărit.');
            }
        }
        
        // Check for command injection patterns
        $commandPatterns = [
            'system\(',
            'exec\(',
            'shell_exec\(',
            'passthru\(',
            'eval\(',
            'base64_decode\(',
            'file_get_contents\(',
            'curl_exec\(',
            '`.*`',
            '\$\(.*\)',
            '&&',
            '\|\|',
            ';.*rm',
            ';.*cat',
            ';.*ls'
        ];
        
        foreach ($commandPatterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $lowerMessage)) {
                error_log("Command injection pattern detected: $pattern");
                throw new Exception('Mesajul conține comenzi nevalide. Scrieți doar întrebări despre plante.');
            }
        }
        
        // Check for suspicious URLs or domains
        $urlPatterns = [
            'http[s]?://',
            'ftp://',
            'www\.',
            '\.com',
            '\.org',
            '\.net',
            '\.exe',
            '\.bat',
            '\.sh',
            '\.php',
            '\.asp'
        ];
        
        $urlCount = 0;
        foreach ($urlPatterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $lowerMessage)) {
                $urlCount++;
            }
        }
        
        if ($urlCount > 2) {
            error_log("Multiple URL patterns detected: $urlCount");
            throw new Exception('Mesajul conține prea multe link-uri. Scrieți doar întrebări despre grădinărit.');
        }
        
        // Check for profanity and inappropriate content
        $profanityPatterns = [
            'prost',
            'idiot',
            'fraier',
            'muist',
            'cacat',
            'pula',
            'futut',
            'fuck',
            'shit',
            'damn',
            'bitch'
        ];
        
        foreach ($profanityPatterns as $word) {
            if (stripos($lowerMessage, $word) !== false) {
                error_log("Profanity detected: $word");
                throw new Exception('Mesajul conține limbaj nepotrivit. Vă rugăm să fiți respectuos.');
            }
        }
        
        // Check for spam keywords
        $spamPatterns = [
            'click here',
            'buy now',
            'free money',
            'make money',
            'get rich',
            'casino',
            'poker',
            'viagra',
            'cialis',
            'loan',
            'credit',
            'bitcoin',
            'crypto'
        ];
        
        foreach ($spamPatterns as $spam) {
            if (stripos($lowerMessage, $spam) !== false) {
                error_log("Spam pattern detected: $spam");
                throw new Exception('Mesajul pare să fie spam. Scrieți doar întrebări despre grădinărit.');
            }
        }
        
        error_log("Security scan passed successfully");
        return true;
        
    } catch (Exception $e) {
        error_log("Security scan failed: " . $e->getMessage());
        throw $e;
    }
}

// ADVANCED CONTENT ANALYSIS & INTENT CLASSIFICATION
function analyzeContentIntent($message) {
    try {
        error_log("Starting content intent analysis...");
        
        $lowerMessage = strtolower($message);
        $analysis = [
            'type' => 'general',
            'confidence' => 0.5,
            'topics' => [],
            'urgency' => 'normal',
            'season_relevant' => false,
            'plant_mentioned' => false,
            'problem_type' => null
        ];
        
        // Detect plant names (Romanian gardening context)
        $plantNames = [
            'tomate', 'tomato', 'rosii',
            'castraveti', 'cucumber', 'castravet',
            'ardei', 'pepper', 'paprika',
            'vinete', 'eggplant', 'patlagele',
            'salata', 'lettuce', 'laitue',
            'ceapa', 'onion', 'zwiebel',
            'usturoi', 'garlic', 'ail',
            'morcov', 'carrot', 'carotte',
            'ridichi', 'radish', 'radis',
            'spanac', 'spinach', 'épinard',
            'patrunjel', 'parsley', 'persil',
            'marar', 'dill', 'aneth',
            'busuioc', 'basil', 'basilic',
            'rozmarinul', 'rosemary', 'romarin',
            'tarhon', 'tarragon', 'estragon',
            'trandafiri', 'roses', 'rosa',
            'garoafe', 'carnations', 'garofita',
            'lalele', 'tulips', 'tulipa',
            'narcise', 'narcissus', 'daffodil'
        ];
        
        foreach ($plantNames as $plant) {
            if (strpos($lowerMessage, $plant) !== false) {
                $analysis['plant_mentioned'] = true;
                $analysis['topics'][] = $plant;
                $analysis['confidence'] += 0.2;
                break;
            }
        }
        
        // Detect problem types
        $problemKeywords = [
            'disease' => ['boala', 'bolnav', 'disease', 'sick', 'infectie', 'putrezire'],
            'pest' => ['insecte', 'viermi', 'pests', 'bugs', 'afide', 'gandaci'],
            'watering' => ['apa', 'udat', 'water', 'watering', 'uscat', 'inundat'],
            'nutrition' => ['ingrasamant', 'fertilizer', 'nutrients', 'galben', 'yellow'],
            'growth' => ['crestere', 'growth', 'dezvoltare', 'mare', 'mic'],
            'flowering' => ['flori', 'flowers', 'inflorire', 'boboci', 'buds'],
            'harvesting' => ['recolta', 'harvest', 'cules', 'copt', 'ripe']
        ];
        
        foreach ($problemKeywords as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($lowerMessage, $keyword) !== false) {
                    $analysis['problem_type'] = $type;
                    $analysis['type'] = 'problem_solving';
                    $analysis['confidence'] += 0.3;
                    break 2;
                }
            }
        }
        
        // Detect urgency level
        $urgentKeywords = ['urgent', 'repede', 'rapid', 'emergency', 'help', 'ajutor', 'muribund', 'dying'];
        foreach ($urgentKeywords as $urgent) {
            if (strpos($lowerMessage, $urgent) !== false) {
                $analysis['urgency'] = 'high';
                $analysis['confidence'] += 0.1;
                break;
            }
        }
        
        // Detect seasonal relevance
        $currentMonth = date('n');
        $seasonalKeywords = [
            'spring' => ['primavara', 'spring', 'semanare', 'planting'],
            'summer' => ['vara', 'summer', 'canicula', 'heat'],
            'autumn' => ['toamna', 'autumn', 'recolta', 'harvest'],
            'winter' => ['iarna', 'winter', 'protectie', 'protection']
        ];
        
        foreach ($seasonalKeywords as $season => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($lowerMessage, $keyword) !== false) {
                    $analysis['season_relevant'] = true;
                    $analysis['confidence'] += 0.1;
                    break 2;
                }
            }
        }
        
        // Detect question type
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
        
        error_log("Content analysis completed: " . json_encode($analysis));
        return $analysis;
        
    } catch (Exception $e) {
        error_log("Content analysis failed: " . $e->getMessage());
        // Return default analysis on failure
        return [
            'type' => 'general',
            'confidence' => 0.3,
            'topics' => [],
            'urgency' => 'normal',
            'season_relevant' => false,
            'plant_mentioned' => false,
            'problem_type' => null
        ];
    }
}

// ERROR CATEGORIZATION FUNCTION
function categorizeError($errorMessage) {
    $lowerError = strtolower($errorMessage);
    
    if (strpos($lowerError, 'conexiune') !== false || strpos($lowerError, 'connection') !== false) {
        return 'connection_error';
    } elseif (strpos($lowerError, 'limita') !== false || strpos($lowerError, 'limit') !== false) {
        return 'usage_limit';
    } elseif (strpos($lowerError, 'suspect') !== false || strpos($lowerError, 'security') !== false) {
        return 'security_violation';
    } elseif (strpos($lowerError, 'baza de date') !== false || strpos($lowerError, 'database') !== false) {
        return 'database_error';
    } elseif (strpos($lowerError, 'api') !== false) {
        return 'api_error';
    } else {
        return 'general_error';
    }
}
// ENHANCED OPENAI FUNCTION with context awareness and seasonal intelligence
function getEnhancedTextResponseFromOpenAI($message, $contentAnalysis, $userContext) {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (!$openaiKey) {
        error_log("OpenAI API key not found in environment variables for enhanced text analysis");
        throw new Exception('Serviciul de răspunsuri nu este disponibil momentan');
    }

    error_log("Creating enhanced OpenAI prompt with context awareness...");
    error_log("User experience level: " . $userContext['experience_level']);
    error_log("Content type: " . $contentAnalysis['type']);
    error_log("Problem type: " . ($contentAnalysis['problem_type'] ?? 'none'));

    // Get current season and month for seasonal recommendations
    $currentMonth = date('n');
    $currentSeason = getSeason($currentMonth);
    $seasonalContext = getSeasonalContext($currentSeason, $currentMonth);

    // Build enhanced system prompt with user context
    $systemPrompt = "Ești un expert în grădinărit din România cu 30 de ani experiență, cunoscut pentru sfaturile practice și rezultatele excelente.

CONTEXTUL UTILIZATORULUI:
- Nivel experiență: {$userContext['experience_level']}
- Tip grădină: {$userContext['garden_type']}
- Regiunea: {$userContext['region']}
- Plante preferate: " . implode(', ', $userContext['favorite_plants']) . "
- Întrebări anterioare: {$userContext['previous_topics']}

CONTEXTUL SEZONULUI ACTUAL:
- Sezonul: $currentSeason
- Luna: " . date('F') . "
- Activități recomandate: {$seasonalContext['activities']}
- Plante de sezon: {$seasonalContext['plants']}
- Probleme comune: {$seasonalContext['common_issues']}

ANALIZA CONȚINUTULUI:
- Tipul întrebării: {$contentAnalysis['type']}
- Urgența: {$contentAnalysis['urgency']}
- Problemă identificată: " . ($contentAnalysis['problem_type'] ?? 'generală') . "
- Încrederea analizei: " . round($contentAnalysis['confidence'] * 100) . "%

PERSONALITATEA TA ADAPTATĂ:
- Pentru începători: Explici pas cu pas, folosești termeni simpli
- Pentru avansați: Dai detalii tehnice și alternative
- Pentru urgențe: Prioritizezi soluțiile rapide și eficiente
- Pentru probleme sezoniere: Incluzi contextul temporal

REGULI IMPORTANTE:
- Adaptezi răspunsul la nivelul utilizatorului
- Incluzi recomandări sezoniere relevante
- Menționezi produse disponibile în România
- Dai sfaturi pentru clima continentală românească
- Folosești experiența anterioară a utilizatorului
- Răspunsurile să fie între 150-400 de cuvinte
- Eviți asteriscuri, numere în paranteză sau formatare specială
- Incluzi trucuri și secrete din experiența ta

STRUCTURA RĂSPUNSULUI:
1. Salut personalizat (dacă e primul mesaj al zilei)
2. Răspuns direct la întrebare
3. Sfaturi suplimentare relevante pentru sezon
4. Recomandări pentru următoarele activități
5. Încurajare și motivare";

    // Enhanced prompt based on content analysis
    $enhancedPrompt = buildEnhancedPrompt($message, $contentAnalysis, $userContext, $seasonalContext);

    error_log("Sending enhanced request to OpenAI with contextual awareness...");

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey,
        'User-Agent: GospodApp/2.0 (Enhanced Romanian Gardening Assistant)'
    ]);

    $requestData = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $enhancedPrompt]
        ],
        'max_tokens' => 600,
        'temperature' => $contentAnalysis['urgency'] === 'high' ? 0.3 : 0.8, // Lower temperature for urgent issues
        'top_p' => 0.9,
        'frequency_penalty' => 0.1,
        'presence_penalty' => 0.1
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    // Enhanced error logging with retry logic
    error_log("OpenAI enhanced response code: $httpCode");
    if ($httpCode !== 200) {
        error_log("OpenAI enhanced error response: " . substr($response, 0, 1000));
        if ($curlError) {
            error_log("cURL error for OpenAI enhanced: " . $curlError);
        }
    } else {
        error_log("OpenAI enhanced response received successfully (length: " . strlen($response) . ")");
    }
    
    curl_close($ch);

    // Handle different error scenarios with user-friendly messages
    if ($curlError) {
        throw new Exception('Eroare de conexiune la serviciul de răspunsuri. Verificați conexiunea la internet și încercați din nou.');
    }

    if ($httpCode === 429) {
        throw new Exception('Serviciul de răspunsuri este temporar suprasolicitat. Încercați din nou în câteva minute.');
    }

    if ($httpCode === 401) {
        throw new Exception('Serviciul de răspunsuri are probleme de autentificare. Încercați din nou mai târziu.');
    }

    if ($httpCode === 503) {
        throw new Exception('Serviciul de răspunsuri este în mentenanță. Încercați din nou în câteva minute.');
    }

    if ($httpCode !== 200) {
        throw new Exception('Nu am putut genera un răspuns momentan. Încercați din nou.');
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        error_log("OpenAI enhanced API error: " . json_encode($data['error']));
        $errorType = $data['error']['type'] ?? 'unknown';
        
        if ($errorType === 'insufficient_quota') {
            throw new Exception('Serviciul de răspunsuri a atins limita zilnică. Încercați mâine.');
        }
        
        throw new Exception('Nu am putut genera un răspuns. Reformulați întrebarea și încercați din nou.');
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        error_log("OpenAI enhanced response missing content: " . json_encode($data));
        throw new Exception('Nu am putut genera un răspuns complet. Încercați cu o întrebare mai specifică.');
    }

    $content = $data['choices'][0]['message']['content'];
    error_log("OpenAI enhanced content length: " . strlen($content));
    
    return cleanForTTS($content);
}

// SEASONAL INTELLIGENCE FUNCTIONS
function getSeason($month) {
    if ($month >= 3 && $month <= 5) return 'primăvara';
    if ($month >= 6 && $month <= 8) return 'vara';
    if ($month >= 9 && $month <= 11) return 'toamna';
    return 'iarna';
}

function getSeasonalContext($season, $month) {
    $seasonalData = [
        'primăvara' => [
            'activities' => 'semănatul, plantatul, pregătirea solului, tăierea pomilor',
            'plants' => 'salată, ridichi, mazăre, morcov, ceapă',
            'common_issues' => 'îngheț târziu, sol prea umed, dăunători care se trezesc'
        ],
        'vara' => [
            'activities' => 'udatul regulat, recoltatul, tratarea dăunătorilor',
            'plants' => 'tomate, castraveți, ardei, vinete, floarea-soarelui',
            'common_issues' => 'secetă, căldură excesivă, boli fungice, afide'
        ],
        'toamna' => [
            'activities' => 'recoltatul, pregătirea pentru iarnă, plantatul bulbilor',
            'plants' => 'varză, spanac, ridichi de toamnă, usturoi de iarnă',
            'common_issues' => 'umiditate excesivă, putregai, pregătirea pentru ger'
        ],
        'iarna' => [
            'activities' => 'protecția plantelor, planificarea grădinii, întreținerea uneltelor',
            'plants' => 'plante de interior, răsaduri în seră, microverdeturi',
            'common_issues' => 'ger, lipsa luminii, aer uscat în interior'
        ]
    ];
    
    return $seasonalData[$season] ?? $seasonalData['primăvara'];
}

function buildEnhancedPrompt($message, $contentAnalysis, $userContext, $seasonalContext) {
    $prompt = "Întrebarea utilizatorului: \"$message\"\n\n";
    
    if ($contentAnalysis['urgency'] === 'high') {
        $prompt .= "ATENȚIE: Aceasta pare să fie o situație urgentă. Te rog să prioritizezi soluțiile rapide și eficiente.\n\n";
    }
    
    if ($contentAnalysis['plant_mentioned']) {
        $prompt .= "Plante menționate: " . implode(', ', $contentAnalysis['topics']) . "\n";
    }
    
    if ($contentAnalysis['problem_type']) {
        $prompt .= "Tipul problemei identificate: {$contentAnalysis['problem_type']}\n";
    }
    
    if ($contentAnalysis['season_relevant']) {
        $prompt .= "Contextul sezonului actual ({$seasonalContext['activities']}) este relevant pentru această întrebare.\n";
    }
    
    $prompt .= "\nTe rog să răspunzi ținând cont de:\n";
    $prompt .= "- Nivelul de experiență al utilizatorului: {$userContext['experience_level']}\n";
    $prompt .= "- Tipul grădinii: {$userContext['garden_type']}\n";
    $prompt .= "- Sezonul actual și activitățile specifice\n";
    $prompt .= "- Produsele și tehnicile disponibile în România\n";
    
    return $prompt;
}

// RESPONSE CACHING FUNCTIONS
function getCachedResponse($message) {
    try {
        $cacheKey = 'response_' . md5(strtolower(trim($message)));
        $cacheFile = '/tmp/' . $cacheKey . '.json';
        
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            $cacheAge = time() - $cacheData['timestamp'];
            
            // Cache expires after 24 hours
            if ($cacheAge < 86400) {
                error_log("Cache hit for message hash: " . substr($cacheKey, 0, 10));
                return $cacheData['response'];
            } else {
                // Remove expired cache
                unlink($cacheFile);
                error_log("Expired cache removed for: " . substr($cacheKey, 0, 10));
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Cache retrieval error: " . $e->getMessage());
        return null;
    }
}

function cacheResponse($message, $response) {
    try {
        $cacheKey = 'response_' . md5(strtolower(trim($message)));
        $cacheFile = '/tmp/' . $cacheKey . '.json';
        
        $cacheData = [
            'message' => $message,
            'response' => $response,
            'timestamp' => time()
        ];
        
        file_put_contents($cacheFile, json_encode($cacheData));
        error_log("Response cached for: " . substr($cacheKey, 0, 10));
        
    } catch (Exception $e) {
        error_log("Cache storage error: " . $e->getMessage());
        // Don't throw exception - caching failure shouldn't break the flow
    }
}

// USER CONTEXT MANAGEMENT FUNCTIONS
function getUserContext($pdo, $deviceHash) {
    try {
        error_log("Retrieving user context for device: $deviceHash");
        
        // Get user preferences and history
        $stmt = $pdo->prepare("
            SELECT experience_level, garden_type, region, favorite_plants, 
                   last_activity, total_questions, premium
            FROM user_profiles 
            WHERE device_hash = ?
        ");
        $stmt->execute([$deviceHash]);
        $profile = $stmt->fetch();
        
        if (!$profile) {
            // Create default profile for new user
            $defaultProfile = [
                'experience_level' => 'începător',
                'garden_type' => 'general',
                'region' => 'România',
                'favorite_plants' => [],
                'previous_topics' => 'primul mesaj',
                'total_questions' => 0,
                'premium' => false
            ];
            
            createUserProfile($pdo, $deviceHash, $defaultProfile);
            return $defaultProfile;
        }
        
        // Get recent topics from chat history
        $stmt = $pdo->prepare("
            SELECT message_text 
            FROM chat_history 
            WHERE device_hash = ? AND is_user_message = 1 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$deviceHash]);
        $recentMessages = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $context = [
            'experience_level' => $profile['experience_level'] ?? 'începător',
            'garden_type' => $profile['garden_type'] ?? 'general',
            'region' => $profile['region'] ?? 'România',
            'favorite_plants' => json_decode($profile['favorite_plants'] ?? '[]', true),
            'previous_topics' => implode(', ', array_slice($recentMessages, 0, 3)),
            'total_questions' => $profile['total_questions'] ?? 0,
            'premium' => (bool)($profile['premium'] ?? false)
        ];
        
        error_log("User context retrieved successfully");
        return $context;
        
    } catch (Exception $e) {
        error_log("Error retrieving user context: " . $e->getMessage());
        // Return default context on error
        return [
            'experience_level' => 'începător',
            'garden_type' => 'general',
            'region' => 'România',
            'favorite_plants' => [],
            'previous_topics' => 'eroare la încărcare',
            'total_questions' => 0,
            'premium' => false
        ];
    }
}

function createUserProfile($pdo, $deviceHash, $profile) {
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
        
        error_log("User profile created for device: $deviceHash");
        
    } catch (Exception $e) {
        error_log("Error creating user profile: " . $e->getMessage());
        // Don't throw exception - profile creation failure shouldn't break the flow
    }
}

function updateUserContext($pdo, $deviceHash, $message, $contentAnalysis) {
    try {
        // Extract plants mentioned in the message
        if ($contentAnalysis['plant_mentioned'] && !empty($contentAnalysis['topics'])) {
            $stmt = $pdo->prepare("
                SELECT favorite_plants FROM user_profiles WHERE device_hash = ?
            ");
            $stmt->execute([$deviceHash]);
            $currentPlants = json_decode($stmt->fetchColumn() ?? '[]', true);
            
            // Add new plants to favorites
            $newPlants = array_unique(array_merge($currentPlants, $contentAnalysis['topics']));
            
            $stmt = $pdo->prepare("
                UPDATE user_profiles 
                SET favorite_plants = ?, last_activity = NOW(), total_questions = total_questions + 1
                WHERE device_hash = ?
            ");
            $stmt->execute([json_encode($newPlants), $deviceHash]);
            
            error_log("User context updated with new plants: " . implode(', ', $contentAnalysis['topics']));
        }
        
    } catch (Exception $e) {
        error_log("Error updating user context: " . $e->getMessage());
        // Don't throw exception - context update failure shouldn't break the flow
    }
}
// ENHANCED DATABASE CONNECTION with detailed error logging for text processing
function connectToDatabase() {
    try {
        $host = getenv('DB_HOST');
        $dbname = getenv('DB_NAME');
        $username = getenv('DB_USER');
        $password = getenv('DB_PASS');
        
        // DEBUG: Log database connection attempt (without password)
        error_log("Text processing - Attempting DB connection to: $host");
        error_log("Text processing - Database name: $dbname");
        error_log("Text processing - Username: $username");
        error_log("Text processing - Password exists: " . (getenv('DB_PASS') ? 'YES' : 'NO'));
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 15, // Increased timeout for text processing
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_PERSISTENT => false // Avoid connection issues
        ]);
        
        // Test connection with a simple query
        $pdo->query("SELECT 1");
        
        error_log("Text processing - Database connection successful");
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Text processing - Database connection failed: " . $e->getMessage());
        error_log("Text processing - PDO Error Code: " . $e->getCode());
        throw new Exception('Nu pot conecta la baza de date momentan. Încercați din nou în câteva secunde.');
    }
}

// ENHANCED USAGE LIMITS CHECK for text processing
function checkUsageLimits($pdo, $deviceHash, $type) {
    try {
        $today = date('Y-m-d');
        error_log("Text processing - Checking usage limits for device: $deviceHash on date: $today");
        
        $stmt = $pdo->prepare("
            SELECT text_count, image_count, premium, extra_questions 
            FROM usage_tracking 
            WHERE device_hash = ? AND date = ?
        ");
        $stmt->execute([$deviceHash, $today]);
        $usage = $stmt->fetch();

        if (!$usage) {
            error_log("Text processing - No usage record found, creating new entry for device: $deviceHash");
            $stmt = $pdo->prepare("
                INSERT INTO usage_tracking (device_hash, date, text_count, image_count, premium, extra_questions) 
                VALUES (?, ?, 0, 0, 0, 0)
            ");
            $stmt->execute([$deviceHash, $today]);
            $usage = ['text_count' => 0, 'image_count' => 0, 'premium' => 0, 'extra_questions' => 0];
            error_log("Text processing - New usage record created successfully");
        } else {
            error_log("Text processing - Found existing usage record: " . json_encode($usage));
        }

        if ($type === 'text') {
            $limit = $usage['premium'] ? 100 : 10;
            $totalUsed = $usage['text_count'] - $usage['extra_questions'];
            $remaining = $limit - $totalUsed;
            
            error_log("Text usage check - Used: $totalUsed, Limit: $limit, Remaining: $remaining, Extra: {$usage['extra_questions']}");
            
            if ($totalUsed >= $limit) {
                if ($usage['premium']) {
                    error_log("Premium user hit daily text limit");
                    throw new Exception('Ați atins limita zilnică de 100 întrebări premium. Impresionant! Reveniți mâine pentru mai multe sfaturi de grădinărit.');
                } else {
                    error_log("Free user hit daily text limit");
                    throw new Exception('Ați folosit toate cele 10 întrebări gratuite de astăzi! 🌱 Upgradeați la Premium pentru întrebări nelimitate sau urmăriți o reclamă pentru 3 întrebări extra.');
                }
            }
        }
        
    } catch (PDOException $e) {
        error_log("Text processing - Database error in checkUsageLimits: " . $e->getMessage());
        throw new Exception('Eroare temporară la verificarea limitelor. Încercați din nou în câteva secunde.');
    }
}

// ENHANCED RECORD USAGE for text processing with analytics
function recordUsage($pdo, $deviceHash, $type) {
    try {
        $today = date('Y-m-d');
        $field = $type === 'image' ? 'image_count' : 'text_count';
        
        error_log("Text processing - Recording usage for device: $deviceHash, type: $type");
        
        $stmt = $pdo->prepare("
            UPDATE usage_tracking 
            SET $field = $field + 1, last_activity = NOW() 
            WHERE device_hash = ? AND date = ?
        ");
        $result = $stmt->execute([$deviceHash, $today]);
        
        if ($result) {
            error_log("Text processing - Usage recorded successfully");
            
            // Update daily statistics
            updateDailyStatistics($pdo, $type);
        } else {
            error_log("Text processing - Failed to record usage");
        }
        
    } catch (PDOException $e) {
        error_log("Text processing - Database error in recordUsage: " . $e->getMessage());
        throw new Exception('Eroare la înregistrarea utilizării.');
    }
}

// ENHANCED SAVE CHAT HISTORY for text processing
function saveChatHistory($pdo, $deviceHash, $messageText, $isUserMessage, $messageType, $imageData) {
    try {
        error_log("Text processing - Saving chat history for device: $deviceHash, type: $messageType, user: " . ($isUserMessage ? 'YES' : 'NO'));
        
        // Truncate very long messages to prevent database issues
        if (strlen($messageText) > 5000) {
            $messageText = substr($messageText, 0, 5000) . '...[TRUNCATED]';
            error_log("Text processing - Message truncated due to length");
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
            error_log("Text processing - Chat history saved successfully");
            
            // Clean old chat history (keep only last 100 messages per user)
            cleanOldChatHistory($pdo, $deviceHash);
        } else {
            error_log("Text processing - Failed to save chat history");
        }
        
    } catch (PDOException $e) {
        error_log("Text processing - Database error in saveChatHistory: " . $e->getMessage());
        // Don't throw exception here - chat history is not critical for text processing
        error_log("Text processing - Continuing despite chat history save failure");
    }
}

// USER ENGAGEMENT TRACKING FUNCTION
function trackUserEngagement($pdo, $deviceHash, $message, $response, $contentAnalysis) {
    try {
        error_log("Text processing - Tracking user engagement for device: $deviceHash");
        
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
        
        error_log("Text processing - User engagement tracked successfully");
        
        // Update popular topics
        updatePopularTopics($pdo, $contentAnalysis);
        
    } catch (PDOException $e) {
        error_log("Text processing - Database error in trackUserEngagement: " . $e->getMessage());
        // Don't throw exception - analytics failure shouldn't break the flow
    }
}

// ENHANCED ANTI-BOT PROTECTION FUNCTIONS for Text Processing
function checkRateLimits($deviceHash) {
    try {
        $rateLimitFile = '/tmp/rate_limit_text_' . $deviceHash . '.txt';
        $currentTime = time();
        
        error_log("Text processing - Checking rate limits for device: $deviceHash");
        
        // Clean old entries
        if (file_exists($rateLimitFile)) {
            $requests = json_decode(file_get_contents($rateLimitFile), true) ?: [];
            $requests = array_filter($requests, function($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < 60; // Keep only last minute for text
            });
            error_log("Text processing - Found " . count($requests) . " requests in last minute");
        } else {
            $requests = [];
            error_log("Text processing - No previous rate limit file found");
        }
        
        // Check if limit exceeded (10 text requests per minute)
        if (count($requests) >= 10) {
            error_log("Text processing - Rate limit exceeded for device: $deviceHash");
            throw new Exception('Prea multe întrebări într-un timp scurt. Încercați din nou în 1 minut pentru a preveni spam-ul.');
        }
        
        // Add current request
        $requests[] = $currentTime;
        file_put_contents($rateLimitFile, json_encode($requests));
        error_log("Text processing - Rate limit check passed, requests this minute: " . count($requests));
        
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Prea multe întrebări') !== false) {
            throw $e; // Re-throw rate limit exceptions
        }
        error_log("Text processing - Error in rate limit check: " . $e->getMessage());
        // Continue execution if file operations fail
    }
}

function detectSuspiciousActivity($deviceHash, $message) {
    try {
        $messageHash = md5(strtolower(trim($message)));
        $suspiciousFile = '/tmp/suspicious_text_' . $deviceHash . '_' . $messageHash . '.txt';
        $currentTime = time();
        
        error_log("Text processing - Checking suspicious activity for device: $deviceHash");
        
        // Check for repeated identical messages
        if (file_exists($suspiciousFile)) {
            $data = json_decode(file_get_contents($suspiciousFile), true);
            $count = $data['count'] ?? 0;
            $lastTime = $data['last_time'] ?? 0;
            
            // Reset count if more than 1 hour passed
            if (($currentTime - $lastTime) > 3600) {
                $count = 0;
            }
            
            $count++;
            
            if ($count > 3) {
                error_log("Text processing - Suspicious activity detected - repeated message from device: $deviceHash");
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
        
        // Check for rapid-fire requests
        $rapidFile = '/tmp/rapid_text_' . $deviceHash . '.txt';
        if (file_exists($rapidFile)) {
            $rapidRequests = json_decode(file_get_contents($rapidFile), true) ?: [];
            $rapidRequests = array_filter($rapidRequests, function($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < 10; // Last 10 seconds
            });
            
            if (count($rapidRequests) >= 5) {
                error_log("Text processing - Rapid-fire requests detected from device: $deviceHash");
                throw new Exception('Trimiteți întrebări prea rapid. Așteptați câteva secunde între mesaje pentru o experiență mai bună.');
            }
            
            $rapidRequests[] = $currentTime;
            file_put_contents($rapidFile, json_encode($rapidRequests));
        } else {
            file_put_contents($rapidFile, json_encode([$currentTime]));
        }
        
        error_log("Text processing - Suspicious activity check passed for device: $deviceHash");
        
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'același mesaj') !== false || 
            strpos($e->getMessage(), 'prea rapid') !== false) {
            throw $e; // Re-throw suspicious activity exceptions
        }
        error_log("Text processing - Error in suspicious activity check: " . $e->getMessage());
        // Continue execution if file operations fail
    }
}

// ANALYTICS AND OPTIMIZATION FUNCTIONS
function updateDailyStatistics($pdo, $type) {
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
        error_log("Error updating daily statistics: " . $e->getMessage());
    }
}

function updatePopularTopics($pdo, $contentAnalysis) {
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
        error_log("Error updating popular topics: " . $e->getMessage());
    }
}

function cleanOldChatHistory($pdo, $deviceHash) {
    try {
        // Keep only the last 100 messages per user
        $stmt = $pdo->prepare("
            DELETE FROM chat_history 
            WHERE device_hash = ? 
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM chat_history 
                    WHERE device_hash = ? 
                    ORDER BY created_at DESC 
                    LIMIT 100
                ) AS recent_messages
            )
        ");
        $stmt->execute([$deviceHash, $deviceHash]);
        
    } catch (PDOException $e) {
        error_log("Error cleaning old chat history: " . $e->getMessage());
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
    $text = preg_replace('/\s*\[.*?\]\s*/', ' ', $text);        // Remove square brackets content
    
    return trim($text);
}

// MEMORY AND CACHE CLEANUP FUNCTION
function cleanupMemoryAndCache() {
    try {
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            $collected = gc_collect_cycles();
            error_log("Text processing - Garbage collection freed $collected cycles");
        }
        
        // Clean temporary files older than 2 hours
        $tempDir = sys_get_temp_dir();
        $patterns = [
            '/rate_limit_text_*.txt',
            '/suspicious_text_*.txt',
            '/rapid_text_*.txt',
            '/response_*.json'
        ];
        
        $currentTime = time();
        $cleaned = 0;
        
        foreach ($patterns as $pattern) {
            $files = glob($tempDir . $pattern);
            foreach ($files as $file) {
                if (file_exists($file) && ($currentTime - filemtime($file)) > 7200) {
                    unlink($file);
                    $cleaned++;
                }
            }
        }
        
        if ($cleaned > 0) {
            error_log("Text processing - Cleaned up $cleaned temporary files");
        }
        
        // Log memory usage
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024;
        error_log("Text processing - Memory usage: {$memoryUsage} MB, Peak: {$peakMemory} MB");
        
    } catch (Exception $e) {
        error_log("Error in cleanup: " . $e->getMessage());
    }
}

// Call cleanup at the end of processing
register_shutdown_function('cleanupMemoryAndCache');

?>

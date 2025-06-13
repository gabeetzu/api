<?php
function validateRequestSignature($rawBody) {
    $secret = getenv('SIGNING_SECRET');
    if (!$secret) return true;
    $sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $ts  = $_SERVER['HTTP_X_TIMESTAMP'] ?? '';
    if (!$sig || !$ts) return false;
    if (abs(time() - (int)$ts) > 300) return false;
    $expected = hash_hmac('sha256', $ts . '.' . $rawBody, $secret);
    return hash_equals($expected, $sig);
}

function logSecurityEvent($pdo, $deviceHash, $type) {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO security_events (device_hash, event_type, ip_address, user_agent) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $deviceHash,
            $type,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        // ignore
    }
}

function checkRateLimit($id, $limit = 30, $window = 3600, $isPremium = false) {
    if ($isPremium) return true;
    if (class_exists('Redis')) {
        try {
            $r = new Redis();
            $r->connect('127.0.0.1', 6379, 1);
            $key = 'rl:' . sha1($id);
            $count = $r->incr($key);
            if ($count == 1) {
                $r->expire($key, $window);
            }
            return $count <= $limit;
        } catch (Exception $e) {
            // fallback
        }
    }
    $dir = sys_get_temp_dir() . '/gospod_rl';
    if (!file_exists($dir)) mkdir($dir, 0775, true);
    $file = $dir . '/' . sha1($id) . '.json';
    $now = time();
    $data = ['count' => 1, 'start' => $now];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: $data;
        if ($now - $data['start'] > $window) {
            $data = ['count' => 1, 'start' => $now];
        } else {
            $data['count']++;
        }
    }
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $data['count'] <= $limit;
}
?>

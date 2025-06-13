<?php
class DatabaseManager {
    private static $instance = null;
    private $pdo = null;
    private $host;
    private $dbname;
    private $username;
    private $password;
    
    private function __construct() {
        // Get environment variables with fallbacks
        $this->host = $_ENV['DATABASE_HOST'] ?? getenv('DATABASE_HOST') ?? 'localhost';
        $this->dbname = $_ENV['DATABASE_NAME'] ?? getenv('DATABASE_NAME') ?? 'u769920801_secretele';
        $this->username = $_ENV['DATABASE_USER'] ?? getenv('DATABASE_USER') ?? '';
        $this->password = $_ENV['DATABASE_PASSWORD'] ?? getenv('DATABASE_PASSWORD') ?? '';
        
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public function getConnection() {
        // Check if connection is still alive
        if ($this->pdo === null) {
            $this->connect();
        }
        
        try {
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            error_log("Database connection lost, reconnecting: " . $e->getMessage());
            $this->connect();
        }
        
        return $this->pdo;
    }
    
    public function logUsage($deviceHash, $type, $plantLabel = null, $imageData = null) {
        try {
            $pdo = $this->getConnection();
            
            // Update or insert usage tracking
            $stmt = $pdo->prepare("
                INSERT INTO usage_tracking (device_hash, date, text_count, image_count, last_request) 
                VALUES (?, CURDATE(), ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                text_count = text_count + ?, 
                image_count = image_count + ?, 
                last_request = NOW()
            ");
            
            $textIncrement = ($type === 'text') ? 1 : 0;
            $imageIncrement = ($type === 'image') ? 1 : 0;
            
            $stmt->execute([
                $deviceHash, 
                $textIncrement, 
                $imageIncrement, 
                $textIncrement, 
                $imageIncrement
            ]);
            
            // Log detailed usage
            if ($plantLabel || $imageData) {
                $stmt = $pdo->prepare("
                    INSERT INTO usage_log (device_hash, plant_label, image_data, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$deviceHash, $plantLabel, $imageData]);
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Failed to log usage: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUsageStats($deviceHash) {
        try {
            $pdo = $this->getConnection();
            
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(text_count, 0) + COALESCE(image_count, 0) as total_count,
                    COALESCE(text_count, 0) as text_count,
                    COALESCE(image_count, 0) as image_count,
                    premium,
                    deletion_due_at as premium_until,
                    user_name
                FROM usage_tracking 
                WHERE device_hash = ? AND date = CURDATE()
            ");
            
            $stmt->execute([$deviceHash]);
            $result = $stmt->fetch();
            
            if (!$result) {
                return [
                    'total_count' => 0,
                    'text_count' => 0,
                    'image_count' => 0,
                    'premium' => 0,
                    'premium_until' => null,
                    'user_name' => null
                ];
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Failed to get usage stats: " . $e->getMessage());
            return null;
        }
    }
}
?>

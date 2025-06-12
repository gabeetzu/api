<?php
/**
 * Modern Database Manager for GospodApp
 * Uses singleton pattern with proper error handling and environment variables
 * Compatible with Render.com deployment
 */

class DatabaseManager {
    private static $instance = null;
    private $pdo = null;
    private $host;
    private $dbname;
    private $username;
    private $password;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->initializeConnection();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize database connection with environment variables
     */
    private function initializeConnection() {
        // Get database credentials from environment variables (Render.com style)
        $this->host = $_ENV['DATABASE_HOST'] ?? 'localhost';
        $this->dbname = $_ENV['DATABASE_NAME'] ?? 'u769920801_secretele';
        $this->username = $_ENV['DATABASE_USER'] ?? '';
        $this->password = $_ENV['DATABASE_PASSWORD'] ?? '';
        
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
            // Test the connection
            $this->pdo->query('SELECT 1');
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            
            // In production, don't expose database errors to users
            if (getenv('ENVIRONMENT') === 'production') {
                throw new Exception("Database connection failed. Please try again later.");
            } else {
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection() {
        // Check if connection is still alive
        if ($this->pdo === null) {
            $this->initializeConnection();
        }
        
        try {
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            // Connection lost, reconnect
            $this->initializeConnection();
        }
        
        return $this->pdo;
    }
    
    /**
     * Execute a prepared statement with parameters
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query execution failed: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Database query failed");
        }
    }
    
    /**
     * Fetch single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insert record and return last insert ID
     */
    public function insert($sql, $params = []) {
        $this->execute($sql, $params);
        return $this->getConnection()->lastInsertId();
    }
    
    /**
     * Update or delete records and return affected rows
     */
    public function updateOrDelete($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->getConnection()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->getConnection()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->getConnection()->rollBack();
    }
    
    /**
     * Check if device hash exists and get usage data
     */
    public function getUserUsageData($deviceHash) {
        $sql = "SELECT * FROM usage_tracking WHERE device_hash = ? AND date = CURDATE()";
        return $this->fetchOne($sql, [$deviceHash]);
    }
    
    /**
     * Create or update daily usage tracking
     */
    public function updateUsageTracking($deviceHash, $data) {
        $existing = $this->getUserUsageData($deviceHash);
        
        if ($existing) {
            // Update existing record
            $sql = "UPDATE usage_tracking SET 
                    text_count = text_count + ?,
                    image_count = image_count + ?,
                    last_request = CURRENT_TIMESTAMP
                    WHERE device_hash = ? AND date = CURDATE()";
            
            $this->execute($sql, [
                $data['text_increment'] ?? 0,
                $data['image_increment'] ?? 0,
                $deviceHash
            ]);
        } else {
            // Create new record
            $sql = "INSERT INTO usage_tracking 
                    (device_hash, date, text_count, image_count, user_name, created_at, last_request) 
                    VALUES (?, CURDATE(), ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
            
            $this->execute($sql, [
                $deviceHash,
                $data['text_increment'] ?? 0,
                $data['image_increment'] ?? 0,
                $data['user_name'] ?? null
            ]);
        }
    }
    
    /**
     * Log chat message
     */
    public function logChatMessage($deviceHash, $messageText, $messageType, $imageData = null, $isUserMessage = true) {
        $sql = "INSERT INTO chat_history 
                (device_hash, message_text, message_type, image_data, is_user_message, created_at) 
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
        
        return $this->execute($sql, [
            $deviceHash,
            $messageText,
            $messageType,
            $imageData,
            $isUserMessage ? 1 : 0
        ]);
    }
    
    /**
     * Get recent chat history for context
     */
    public function getChatHistory($deviceHash, $limit = 10) {
        $sql = "SELECT message_text, is_user_message, created_at 
                FROM chat_history 
                WHERE device_hash = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        return $this->fetchAll($sql, [$deviceHash, $limit]);
    }
    
    /**
     * Check if user has premium access
     */
    public function checkPremiumStatus($deviceHash) {
        $sql = "SELECT premium, deletion_due_at 
                FROM usage_tracking 
                WHERE device_hash = ? 
                AND (deletion_due_at IS NULL OR deletion_due_at > NOW())
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $result = $this->fetchOne($sql, [$deviceHash]);
        
        return [
            'is_premium' => $result && $result['premium'] == 1,
            'premium_until' => $result['deletion_due_at'] ?? null
        ];
    }
    
    /**
     * Grant premium access (for referrals)
     */
    public function grantPremiumAccess($deviceHash, $days = 30) {
        $sql = "UPDATE usage_tracking 
                SET premium = 1, 
                    deletion_due_at = DATE_ADD(NOW(), INTERVAL ? DAY)
                WHERE device_hash = ?";
        
        return $this->execute($sql, [$days, $deviceHash]);
    }
    
    /**
     * Handle referral system
     */
    public function processReferral($inviterHash, $invitedHash) {
        try {
            $this->beginTransaction();
            
            // Check if referral already exists
            $existing = $this->fetchOne(
                "SELECT * FROM referrals WHERE inviter_hash = ? AND invited_hash = ?",
                [$inviterHash, $invitedHash]
            );
            
            if (!$existing) {
                // Create referral record
                $this->execute(
                    "INSERT INTO referrals (inviter_hash, invited_hash, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)",
                    [$inviterHash, $invitedHash]
                );
                
                // Grant premium to both users
                $this->grantPremiumAccess($inviterHash, 30);
                $this->grantPremiumAccess($invitedHash, 30);
                
                $this->commit();
                return true;
            }
            
            $this->rollback();
            return false;
            
        } catch (Exception $e) {
            $this->rollback();
            error_log("Referral processing failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get usage statistics for analytics
     */
    public function getUsageStats($deviceHash) {
        $sql = "SELECT 
                    COALESCE(SUM(text_count), 0) as total_text,
                    COALESCE(SUM(image_count), 0) as total_images,
                    COALESCE(SUM(text_count + image_count), 0) as total_requests,
                    COUNT(DISTINCT date) as active_days,
                    MAX(premium) as is_premium,
                    MAX(deletion_due_at) as premium_until
                FROM usage_tracking 
                WHERE device_hash = ?";
        
        return $this->fetchOne($sql, [$deviceHash]);
    }
    
    /**
     * Clean up old data (for GDPR compliance)
     */
    public function cleanupOldData() {
        try {
            $this->beginTransaction();
            
            // Delete chat history older than 30 days
            $this->execute(
                "DELETE FROM chat_history WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            
            // Delete usage tracking older than 90 days (keep for analytics)
            $this->execute(
                "DELETE FROM usage_tracking WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
            );
            
            // Delete old referrals
            $this->execute(
                "DELETE FROM referrals WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)"
            );
            
            $this->commit();
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            error_log("Data cleanup failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization of the instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Convenience function to get database instance
 */
function getDB() {
    return DatabaseManager::getInstance();
}

/**
 * Health check function for monitoring
 */
function checkDatabaseHealth() {
    try {
        $db = DatabaseManager::getInstance();
        $result = $db->fetchOne("SELECT 1 as health_check");
        
        return [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'connection' => $result['health_check'] == 1 ? 'ok' : 'failed'
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'unhealthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => $e->getMessage()
        ];
    }
}
?>
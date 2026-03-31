<?php
require_once __DIR__ . "/../config/db.php";

class AuditLog {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Log an action to the audit log
     */
    public function logAction($action, $table_name, $record_id, $old_values = null, $new_values = null, $user_id = null, $description = null) {
        try {
            $sql = "INSERT INTO audit_logs 
                    (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, url, description, created_at) 
                    VALUES (:user_id, :action, :table_name, :record_id, :old_values, :new_values, :ip_address, :user_agent, :url, :description, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            
            // Get request information
            $ip_address = $this->getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $url = $this->getCurrentUrl();
            
            // Filter sensitive data
            $old_values = $old_values ? $this->filterSensitiveData($old_values) : null;
            $new_values = $new_values ? $this->filterSensitiveData($new_values) : null;
            
            return $stmt->execute([
                ':user_id' => $user_id,
                ':action' => $action,
                ':table_name' => $table_name,
                ':record_id' => $record_id,
                ':old_values' => $old_values ? json_encode($old_values) : null,
                ':new_values' => $new_values ? json_encode($new_values) : null,
                ':ip_address' => $ip_address,
                ':user_agent' => $user_agent,
                ':url' => $url,
                ':description' => $description
            ]);
            
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all audit logs with filters
     */
    public function getAuditLogs($filters = []) {
        $sql = "SELECT al.*, u.name as user_name 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                WHERE 1=1";
        
        $params = [];
        
        // Apply filters
        if (!empty($filters['action'])) {
            $sql .= " AND al.action = :action";
            $params[':action'] = $filters['action'];
        }
        
        if (!empty($filters['table_name'])) {
            $sql .= " AND al.table_name = :table_name";
            $params[':table_name'] = $filters['table_name'];
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(al.created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(al.created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (al.description LIKE :search OR u.name LIKE :search OR al.action LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $sql .= " ORDER BY al.created_at DESC";
        
        // Add pagination
        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        $sql .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get audit log by ID
     */
    public function getAuditLogById($log_id) {
        $sql = "SELECT al.*, u.name as user_name 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                WHERE al.log_id = :log_id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':log_id' => $log_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get audit statistics
     */
    public function getAuditStats($days = 30) {
        $sql = "SELECT 
                COUNT(*) as total_actions,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT table_name) as tables_affected,
                COUNT(DISTINCT action) as unique_actions
                FROM audit_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':days' => $days]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent activities
     */
    public function getRecentActivities($limit = 10) {
        $sql = "SELECT al.*, u.name as user_name 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                ORDER BY al.created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get actions by user
     */
    public function getUserActivities($user_id, $limit = 20) {
        $sql = "SELECT al.*, u.name as user_name 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                WHERE al.user_id = :user_id 
                ORDER BY al.created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':limit' => $limit
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
    }
    
    /**
     * Get current URL
     */
    private function getCurrentUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return $protocol . "://" . $host . $uri;
    }
    
    /**
     * Filter sensitive data from logs
     */
    private function filterSensitiveData($data) {
        if (!is_array($data)) {
            return $data;
        }
        
        $sensitiveFields = [
            'password', 'password_confirmation', 'remember_token', 
            'api_token', 'credit_card_number', 'cvv', 'pin', 'token'
        ];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***HIDDEN***';
            }
        }
        
        return $data;
    }
    
    /**
     * Clean old audit logs (for maintenance)
     */
    public function cleanOldLogs($days = 365) {
        $sql = "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':days' => $days]);
    }
    
    /**
     * Get popular actions
     */
    public function getPopularActions($limit = 10) {
        $sql = "SELECT action, COUNT(*) as count 
                FROM audit_logs 
                GROUP BY action 
                ORDER BY count DESC 
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get actions by table
     */
    public function getActionsByTable($table_name, $limit = 50) {
        $sql = "SELECT al.*, u.name as user_name 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                WHERE al.table_name = :table_name 
                ORDER BY al.created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':table_name' => $table_name,
            ':limit' => $limit
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<?php
/**
 * SettingsAudit Model
 * Manages audit trail for system settings changes
 */

require_once 'BaseModel.php';

class SettingsAudit extends BaseModel {
    protected $table = 'settings_audit';
    protected $fillable = [
        'setting_id', 'user_id', 'old_value', 'new_value',
        'action', 'ip_address', 'user_agent', 'session_id', 
        'request_method', 'request_uri', 'integrity_hash'
    ];
    
    // Action constants
    const ACTION_CREATE = 'CREATE';
    const ACTION_UPDATE = 'UPDATE';
    const ACTION_DELETE = 'DELETE';
    const ACTION_RESET = 'RESET';
    
    /**
     * Create audit entry for setting change with comprehensive metadata and integrity checks
     */
    public function createAuditEntry($settingId, $userId, $oldValue, $newValue, $action, $ipAddress = null, $userAgent = null) {
        // Get comprehensive metadata
        $metadata = $this->gatherAuditMetadata($ipAddress, $userAgent);
        
        $data = [
            'setting_id' => $settingId,
            'user_id' => $userId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'action' => $action,
            'ip_address' => $metadata['ip_address'],
            'user_agent' => $metadata['user_agent'],
            'session_id' => $metadata['session_id'],
            'request_method' => $metadata['request_method'],
            'request_uri' => $metadata['request_uri']
        ];
        
        // Generate integrity hash for immutable audit trail
        $data['integrity_hash'] = $this->generateIntegrityHash($data);
        
        return $this->create($data);
    }
    
    /**
     * Gather comprehensive audit metadata
     */
    private function gatherAuditMetadata($ipAddress = null, $userAgent = null) {
        return [
            'ip_address' => $ipAddress ?? $this->getClientIpAddress(),
            'user_agent' => $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'),
            'session_id' => session_id() ?: 'no-session',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'cli-command'
        ];
    }
    
    /**
     * Generate integrity hash for audit entry to ensure immutability
     */
    private function generateIntegrityHash($data) {
        // Create a deterministic string from audit data
        $hashData = [
            'setting_id' => $data['setting_id'],
            'user_id' => $data['user_id'],
            'old_value' => $data['old_value'],
            'new_value' => $data['new_value'],
            'action' => $data['action'],
            'ip_address' => $data['ip_address'],
            'session_id' => $data['session_id'],
            'timestamp' => date('Y-m-d H:i:s') // Current timestamp
        ];
        
        // Sort keys to ensure consistent hash
        ksort($hashData);
        
        // Create hash string
        $hashString = json_encode($hashData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Generate SHA-256 hash with a secret salt
        $secret = $this->getAuditSecret();
        return hash('sha256', $hashString . $secret);
    }
    
    /**
     * Get audit secret for integrity hashing
     */
    private function getAuditSecret() {
        // In production, this should be stored in environment variables or secure config
        // For now, use a combination of database config and a fixed salt
        $dbConfig = DatabaseConfig::getInstance();
        
        // Use reflection to access private properties safely
        $reflection = new ReflectionClass($dbConfig);
        $hostProperty = $reflection->getProperty('host');
        $hostProperty->setAccessible(true);
        $dbHost = $hostProperty->getValue($dbConfig) ?? 'localhost';
        
        $dbnameProperty = $reflection->getProperty('dbname');
        $dbnameProperty->setAccessible(true);
        $dbName = $dbnameProperty->getValue($dbConfig) ?? 'clarity_db';
        
        return hash('sha256', $dbHost . $dbName . 'audit_integrity_salt_2026');
    }
    
    /**
     * Verify integrity of an audit entry
     */
    public function verifyIntegrity($auditEntry) {
        if (!isset($auditEntry['integrity_hash'])) {
            return ['valid' => false, 'reason' => 'No integrity hash found'];
        }
        
        // Recreate the hash data
        $hashData = [
            'setting_id' => $auditEntry['setting_id'],
            'user_id' => $auditEntry['user_id'],
            'old_value' => $auditEntry['old_value'],
            'new_value' => $auditEntry['new_value'],
            'action' => $auditEntry['action'],
            'ip_address' => $auditEntry['ip_address'],
            'session_id' => $auditEntry['session_id'],
            'timestamp' => $auditEntry['timestamp']
        ];
        
        ksort($hashData);
        $hashString = json_encode($hashData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $secret = $this->getAuditSecret();
        $expectedHash = hash('sha256', $hashString . $secret);
        
        $isValid = hash_equals($expectedHash, $auditEntry['integrity_hash']);
        
        return [
            'valid' => $isValid,
            'reason' => $isValid ? 'Integrity verified' : 'Integrity hash mismatch - entry may have been tampered with'
        ];
    }
    
    /**
     * Batch verify integrity of multiple audit entries
     */
    public function batchVerifyIntegrity($auditEntries) {
        $results = [
            'total' => count($auditEntries),
            'valid' => 0,
            'invalid' => 0,
            'details' => []
        ];
        
        foreach ($auditEntries as $entry) {
            $verification = $this->verifyIntegrity($entry);
            $results['details'][$entry['id']] = $verification;
            
            if ($verification['valid']) {
                $results['valid']++;
            } else {
                $results['invalid']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Get audit trail for a specific setting
     */
    public function getAuditTrailForSetting($settingId, $limit = 50, $offset = 0) {
        $sql = "SELECT sa.*, u.username, u.first_name, u.last_name, ss.setting_key
                FROM `{$this->table}` sa
                LEFT JOIN users u ON sa.user_id = u.id
                LEFT JOIN system_settings ss ON sa.setting_id = ss.id
                WHERE sa.setting_id = ?
                ORDER BY sa.timestamp DESC
                LIMIT ? OFFSET ?";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$settingId, $limit, $offset], 'iii');
    }
    
    /**
     * Get audit trail with filtering options
     */
    public function getAuditTrailWithFilters($filters = [], $limit = 50, $offset = 0) {
        $sql = "SELECT sa.*, u.username, u.first_name, u.last_name, ss.setting_key, ss.category
                FROM `{$this->table}` sa
                LEFT JOIN users u ON sa.user_id = u.id
                LEFT JOIN system_settings ss ON sa.setting_id = ss.id";
        
        $whereConditions = [];
        $params = [];
        $types = '';
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "sa.timestamp >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "sa.timestamp <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= 's';
        }
        
        // User filter
        if (!empty($filters['user_id'])) {
            $whereConditions[] = "sa.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }
        
        // Category filter
        if (!empty($filters['category'])) {
            $whereConditions[] = "ss.category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }
        
        // Action filter
        if (!empty($filters['action'])) {
            $whereConditions[] = "sa.action = ?";
            $params[] = $filters['action'];
            $types .= 's';
        }
        
        // Setting key filter
        if (!empty($filters['setting_key'])) {
            $whereConditions[] = "ss.setting_key = ?";
            $params[] = $filters['setting_key'];
            $types .= 's';
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $sql .= " ORDER BY sa.timestamp DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Get audit trail count for pagination
     */
    public function getAuditTrailCount($filters = []) {
        $sql = "SELECT COUNT(*) as total
                FROM `{$this->table}` sa
                LEFT JOIN system_settings ss ON sa.setting_id = ss.id";
        
        $whereConditions = [];
        $params = [];
        $types = '';
        
        // Apply same filters as getAuditTrailWithFilters
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "sa.timestamp >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "sa.timestamp <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= 's';
        }
        
        if (!empty($filters['user_id'])) {
            $whereConditions[] = "sa.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['category'])) {
            $whereConditions[] = "ss.category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }
        
        if (!empty($filters['action'])) {
            $whereConditions[] = "sa.action = ?";
            $params[] = $filters['action'];
            $types .= 's';
        }
        
        if (!empty($filters['setting_key'])) {
            $whereConditions[] = "ss.setting_key = ?";
            $params[] = $filters['setting_key'];
            $types .= 's';
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $result = DatabaseConfig::getInstance()->getResults($sql, $params, $types);
        return $result[0]['total'] ?? 0;
    }
    
    /**
     * Export audit trail data
     */
    public function exportAuditTrail($filters = [], $format = 'csv') {
        $data = $this->getAuditTrailWithFilters($filters, 10000, 0); // Large limit for export
        
        if ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT);
        }
        
        // Default to CSV
        if (empty($data)) {
            return '';
        }
        
        $csv = '';
        
        // Headers
        $headers = [
            'Timestamp', 'Setting Key', 'Category', 'Action', 
            'Old Value', 'New Value', 'User', 'IP Address'
        ];
        $csv .= implode(',', array_map([$this, 'escapeCsvValue'], $headers)) . "\n";
        
        // Data rows
        foreach ($data as $row) {
            $csvRow = [
                $row['timestamp'],
                $row['setting_key'],
                $row['category'],
                $row['action'],
                $row['old_value'],
                $row['new_value'],
                ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '') . ' (' . ($row['username'] ?? '') . ')',
                $row['ip_address']
            ];
            $csv .= implode(',', array_map([$this, 'escapeCsvValue'], $csvRow)) . "\n";
        }
        
        return $csv;
    }
    
    /**
     * Get audit statistics
     */
    public function getAuditStatistics($dateFrom = null, $dateTo = null) {
        $sql = "SELECT 
                    COUNT(*) as total_changes,
                    COUNT(DISTINCT sa.setting_id) as settings_modified,
                    COUNT(DISTINCT sa.user_id) as users_involved,
                    sa.action,
                    COUNT(*) as action_count
                FROM `{$this->table}` sa";
        
        $params = [];
        $types = '';
        $whereConditions = [];
        
        if ($dateFrom) {
            $whereConditions[] = "sa.timestamp >= ?";
            $params[] = $dateFrom;
            $types .= 's';
        }
        
        if ($dateTo) {
            $whereConditions[] = "sa.timestamp <= ?";
            $params[] = $dateTo . ' 23:59:59';
            $types .= 's';
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $sql .= " GROUP BY sa.action";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIpAddress() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Escape CSV values
     */
    private function escapeCsvValue($value) {
        if ($value === null) {
            return '';
        }
        
        $value = (string) $value;
        
        // If value contains comma, quote, or newline, wrap in quotes and escape quotes
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }
        
        return $value;
    }
}
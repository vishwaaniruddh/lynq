<?php
/**
 * IP Master Model
 * Represents a unique IP address combination for router configuration
 * 
 * Requirements: 1.1, 1.2, 1.3
 * - Stores unique combinations of Network IP, Router IP, Site IP, and Subnet Mask
 * - Tracks status (available, locked, configured)
 * - Validates IP format
 */

require_once __DIR__ . '/BaseModel.php';

class IPMaster extends BaseModel {
    protected $table = 'ip_master';
    protected $fillable = [
        'network_ip', 'router_ip', 'site_ip', 'subnet_mask',
        'status', 'created_by'
    ];
    
    // Status constants
    const STATUS_AVAILABLE = 'available';
    const STATUS_LOCKED = 'locked';
    const STATUS_CONFIGURED = 'configured';
    
    /**
     * Get all valid statuses
     */
    public static function getStatuses() {
        return [
            self::STATUS_AVAILABLE,
            self::STATUS_LOCKED,
            self::STATUS_CONFIGURED
        ];
    }
    
    /**
     * Check if a status is valid
     */
    public static function isValidStatus($status) {
        return in_array($status, self::getStatuses());
    }
    
    /**
     * Validate IPv4 format
     * Requirement 1.1: Validate IP format (four octets 0-255 separated by dots)
     * 
     * @param string $ip IP address to validate
     * @return bool True if valid IPv4 format
     */
    public static function validateIPFormat($ip) {
        if (empty($ip) || !is_string($ip)) {
            return false;
        }
        
        // Use filter_var for basic validation
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        
        // Additional validation: ensure each octet is 0-255
        $octets = explode('.', $ip);
        if (count($octets) !== 4) {
            return false;
        }
        
        foreach ($octets as $octet) {
            if (!is_numeric($octet) || $octet < 0 || $octet > 255) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate all IP fields in data array
     * 
     * @param array $data Data containing IP fields
     * @return array Array of validation errors (empty if valid)
     */
    public static function validateAllIPs($data) {
        $errors = [];
        $ipFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask'];
        
        foreach ($ipFields as $field) {
            if (isset($data[$field])) {
                if (!self::validateIPFormat($data[$field])) {
                    $errors[$field] = "Invalid IP format for {$field}. Expected format: xxx.xxx.xxx.xxx (0-255 for each octet)";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Find available IP_Master records
     * Requirement 3.1: Get IPs that are not locked and not configured
     */
    public function findAvailable() {
        return $this->findAll(['status' => self::STATUS_AVAILABLE], 'id ASC');
    }
    
    /**
     * Find locked IP_Master records
     */
    public function findLocked() {
        return $this->findAll(['status' => self::STATUS_LOCKED], 'id ASC');
    }
    
    /**
     * Find configured IP_Master records
     */
    public function findConfigured() {
        return $this->findAll(['status' => self::STATUS_CONFIGURED], 'id ASC');
    }
    
    /**
     * Find by status
     */
    public function findByStatus($status) {
        if (!self::isValidStatus($status)) {
            return [];
        }
        return $this->findAll(['status' => $status], 'id ASC');
    }
    
    /**
     * Check if IP combination already exists
     * Requirement 1.2: Prevent duplicate IP combinations
     * 
     * @param array $data IP data to check
     * @param int|null $excludeId ID to exclude from check (for updates)
     * @return bool True if duplicate exists
     */
    public function checkDuplicate($data, $excludeId = null) {
        $sql = "SELECT id FROM `{$this->table}` 
                WHERE network_ip = ? AND router_ip = ? AND site_ip = ? AND subnet_mask = ?";
        $params = [
            $data['network_ip'],
            $data['router_ip'],
            $data['site_ip'],
            $data['subnet_mask']
        ];
        $types = 'ssss';
        
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = DatabaseConfig::getInstance()->getResults($sql, $params, $types);
        return !empty($result);
    }
    
    /**
     * Get the next available IP_Master for configuration
     * Requirement 3.1: Automatically present next available IP
     * 
     * @return array|null Next available IP_Master or null if none available
     */
    public function getNextAvailable() {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE status = ? 
                ORDER BY id ASC 
                LIMIT 1";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [self::STATUS_AVAILABLE], 's');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Update IP_Master status
     * 
     * @param int $id IP_Master ID
     * @param string $status New status
     * @return array|null Updated record or null on failure
     */
    public function updateStatus($id, $status) {
        if (!self::isValidStatus($status)) {
            return null;
        }
        return $this->update($id, ['status' => $status]);
    }
    
    /**
     * Check if IP_Master can be edited
     * Requirement 1.4: Prevent editing configured IPs
     * 
     * @param int $id IP_Master ID
     * @return bool True if can be edited
     */
    public function canEdit($id) {
        $record = $this->find($id);
        if (!$record) {
            return false;
        }
        return $record['status'] !== self::STATUS_CONFIGURED;
    }
    
    /**
     * Check if IP_Master can be deleted
     * Requirement 1.5: Only allow deletion if not configured or locked
     * 
     * @param int $id IP_Master ID
     * @return bool True if can be deleted
     */
    public function canDelete($id) {
        $record = $this->find($id);
        if (!$record) {
            return false;
        }
        return $record['status'] === self::STATUS_AVAILABLE;
    }
    
    /**
     * Get count by status
     * Requirement 7.2: Dashboard IP statistics
     * 
     * @return array Counts by status
     */
    public function getCountByStatus() {
        $sql = "SELECT status, COUNT(*) as count FROM `{$this->table}` GROUP BY status";
        $results = DatabaseConfig::getInstance()->getResults($sql, [], '');
        
        $counts = [
            self::STATUS_AVAILABLE => 0,
            self::STATUS_LOCKED => 0,
            self::STATUS_CONFIGURED => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $counts[$row['status']] = (int)$row['count'];
            $counts['total'] += (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Search IP_Master records with filters
     * 
     * @param array $filters Search filters
     * @return array Matching records
     */
    public function search($filters = []) {
        $sql = "SELECT * FROM `{$this->table}` WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['network_ip'])) {
            $sql .= " AND network_ip LIKE ?";
            $params[] = '%' . $filters['network_ip'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['router_ip'])) {
            $sql .= " AND router_ip LIKE ?";
            $params[] = '%' . $filters['router_ip'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['site_ip'])) {
            $sql .= " AND site_ip LIKE ?";
            $params[] = '%' . $filters['site_ip'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (network_ip LIKE ? OR router_ip LIKE ? OR site_ip LIKE ? OR subnet_mask LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $types .= 'ssss';
        }
        
        $sql .= " ORDER BY id ASC";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
}

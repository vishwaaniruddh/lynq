<?php
/**
 * Configuration Audit Log Model
 * Tracks all IP configuration activities for audit purposes
 * 
 * Requirements: 9.1, 9.2, 9.3, 9.4
 * - Logs all configuration actions (locks, bindings, unbindings)
 * - Supports filtering by action type, timestamp, router, and user
 * - Stores additional context in JSON format
 */

require_once __DIR__ . '/BaseModel.php';

class ConfigurationAuditLog extends BaseModel {
    protected $table = 'configuration_audit_log';
    protected $fillable = [
        'action_type', 'user_id', 'router_serial_number',
        'ip_master_id', 'details'
    ];
    
    // Action type constants
    const ACTION_LOCK_ACQUIRED = 'lock_acquired';
    const ACTION_LOCK_RELEASED = 'lock_released';
    const ACTION_LOCK_EXPIRED = 'lock_expired';
    const ACTION_CONFIGURED = 'configured';
    const ACTION_UNBOUND = 'unbound';
    const ACTION_IP_CREATED = 'ip_created';
    const ACTION_IP_UPDATED = 'ip_updated';
    const ACTION_IP_DELETED = 'ip_deleted';
    const ACTION_BULK_UPLOAD = 'bulk_upload';
    
    /**
     * Get all valid action types
     */
    public static function getActionTypes() {
        return [
            self::ACTION_LOCK_ACQUIRED,
            self::ACTION_LOCK_RELEASED,
            self::ACTION_LOCK_EXPIRED,
            self::ACTION_CONFIGURED,
            self::ACTION_UNBOUND,
            self::ACTION_IP_CREATED,
            self::ACTION_IP_UPDATED,
            self::ACTION_IP_DELETED,
            self::ACTION_BULK_UPLOAD
        ];
    }
    
    /**
     * Check if an action type is valid
     */
    public static function isValidActionType($actionType) {
        return in_array($actionType, self::getActionTypes());
    }
    
    /**
     * Get human-readable action type label
     * 
     * @param string $actionType Action type constant
     * @return string Human-readable label
     */
    public static function getActionLabel($actionType) {
        $labels = [
            self::ACTION_LOCK_ACQUIRED => 'Lock Acquired',
            self::ACTION_LOCK_RELEASED => 'Lock Released',
            self::ACTION_LOCK_EXPIRED => 'Lock Expired',
            self::ACTION_CONFIGURED => 'Configuration Completed',
            self::ACTION_UNBOUND => 'IP Unbound',
            self::ACTION_IP_CREATED => 'IP Created',
            self::ACTION_IP_UPDATED => 'IP Updated',
            self::ACTION_IP_DELETED => 'IP Deleted',
            self::ACTION_BULK_UPLOAD => 'Bulk Upload'
        ];
        
        return $labels[$actionType] ?? $actionType;
    }
    
    /**
     * Log an action
     * Requirement 9.1: Log all configuration actions
     * 
     * @param string $actionType Type of action
     * @param int $userId User performing the action
     * @param string|null $routerSerial Router serial number (if applicable)
     * @param int|null $ipMasterId IP_Master ID (if applicable)
     * @param array|null $details Additional context
     * @return array|null Created log entry or null
     */
    public function logAction($actionType, $userId, $routerSerial = null, $ipMasterId = null, $details = null) {
        if (!self::isValidActionType($actionType)) {
            return null;
        }
        
        $data = [
            'action_type' => $actionType,
            'user_id' => $userId
        ];
        
        if ($routerSerial !== null) {
            $data['router_serial_number'] = $routerSerial;
        }
        
        if ($ipMasterId !== null) {
            $data['ip_master_id'] = $ipMasterId;
        }
        
        if ($details !== null) {
            $data['details'] = is_array($details) ? json_encode($details) : $details;
        }
        
        return $this->create($data);
    }
    
    /**
     * Log lock acquired action
     */
    public function logLockAcquired($userId, $routerSerial, $ipMasterId, $details = null) {
        return $this->logAction(self::ACTION_LOCK_ACQUIRED, $userId, $routerSerial, $ipMasterId, $details);
    }
    
    /**
     * Log lock released action
     */
    public function logLockReleased($userId, $routerSerial, $ipMasterId, $details = null) {
        return $this->logAction(self::ACTION_LOCK_RELEASED, $userId, $routerSerial, $ipMasterId, $details);
    }
    
    /**
     * Log lock expired action
     * Requirement 9.3: Record timeout events
     */
    public function logLockExpired($userId, $routerSerial, $ipMasterId, $details = null) {
        return $this->logAction(self::ACTION_LOCK_EXPIRED, $userId, $routerSerial, $ipMasterId, $details);
    }
    
    /**
     * Log configuration completed action
     */
    public function logConfigured($userId, $routerSerial, $ipMasterId, $details = null) {
        return $this->logAction(self::ACTION_CONFIGURED, $userId, $routerSerial, $ipMasterId, $details);
    }
    
    /**
     * Log unbind action
     * Requirement 6.3: Record unbind action with timestamp, user, and reason
     */
    public function logUnbound($userId, $routerSerial, $ipMasterId, $reason, $details = null) {
        $fullDetails = array_merge(
            ['unbind_reason' => $reason],
            $details ?? []
        );
        return $this->logAction(self::ACTION_UNBOUND, $userId, $routerSerial, $ipMasterId, $fullDetails);
    }
    
    /**
     * Log IP created action
     */
    public function logIPCreated($userId, $ipMasterId, $details = null) {
        return $this->logAction(self::ACTION_IP_CREATED, $userId, null, $ipMasterId, $details);
    }
    
    /**
     * Log IP updated action
     */
    public function logIPUpdated($userId, $ipMasterId, $details = null) {
        return $this->logAction(self::ACTION_IP_UPDATED, $userId, null, $ipMasterId, $details);
    }
    
    /**
     * Log IP deleted action
     */
    public function logIPDeleted($userId, $ipMasterId, $details = null) {
        return $this->logAction(self::ACTION_IP_DELETED, $userId, null, $ipMasterId, $details);
    }
    
    /**
     * Log bulk upload action
     */
    public function logBulkUpload($userId, $details = null) {
        return $this->logAction(self::ACTION_BULK_UPLOAD, $userId, null, null, $details);
    }
    
    /**
     * Get audit log entries with details
     * Requirement 9.2: View complete history
     * 
     * @param array $filters Search filters
     * @param int|null $limit Number of records to return
     * @return array Audit log entries
     */
    public function getHistory($filters = [], $limit = null) {
        $sql = "SELECT a.*, 
                       u.username,
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask
                FROM `{$this->table}` a
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN ip_master m ON a.ip_master_id = m.id
                WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($filters['action_type'])) {
            $sql .= " AND a.action_type = ?";
            $params[] = $filters['action_type'];
            $types .= 's';
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND a.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['router_serial_number'])) {
            $sql .= " AND a.router_serial_number LIKE ?";
            $params[] = '%' . $filters['router_serial_number'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['ip_master_id'])) {
            $sql .= " AND a.ip_master_id = ?";
            $params[] = $filters['ip_master_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND a.created_at >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND a.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= 's';
        }
        
        $sql .= " ORDER BY a.created_at DESC";
        
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
            $types .= 'i';
        }
        
        $results = DatabaseConfig::getInstance()->getResults($sql, $params, $types);
        
        // Decode JSON details
        foreach ($results as &$row) {
            if (!empty($row['details'])) {
                $decoded = json_decode($row['details'], true);
                $row['details_decoded'] = $decoded !== null ? $decoded : $row['details'];
            }
            $row['action_label'] = self::getActionLabel($row['action_type']);
        }
        
        return $results;
    }
    
    /**
     * Get recent activities
     * Requirement 7.4: Dashboard recent configuration activities
     * 
     * @param int $limit Number of records to return
     * @return array Recent activities
     */
    public function getRecentActivities($limit = 10) {
        return $this->getHistory([], $limit);
    }
    
    /**
     * Get history for a specific router
     * 
     * @param string $routerSerial Router serial number
     * @return array Audit log entries for the router
     */
    public function getRouterHistory($routerSerial) {
        return $this->getHistory(['router_serial_number' => $routerSerial]);
    }
    
    /**
     * Get history for a specific IP_Master
     * 
     * @param int $ipMasterId IP_Master ID
     * @return array Audit log entries for the IP
     */
    public function getIPHistory($ipMasterId) {
        return $this->getHistory(['ip_master_id' => $ipMasterId]);
    }
    
    /**
     * Get history for a specific user
     * 
     * @param int $userId User ID
     * @return array Audit log entries for the user
     */
    public function getUserHistory($userId) {
        return $this->getHistory(['user_id' => $userId]);
    }
    
    /**
     * Count actions by type
     * 
     * @return array Counts by action type
     */
    public function getCountByActionType() {
        $sql = "SELECT action_type, COUNT(*) as count 
                FROM `{$this->table}` 
                GROUP BY action_type";
        
        $results = DatabaseConfig::getInstance()->getResults($sql, [], '');
        
        $counts = [];
        foreach (self::getActionTypes() as $type) {
            $counts[$type] = 0;
        }
        
        foreach ($results as $row) {
            $counts[$row['action_type']] = (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Get activity summary for date range
     * 
     * @param string $dateFrom Start date
     * @param string $dateTo End date
     * @return array Activity summary
     */
    public function getActivitySummary($dateFrom, $dateTo) {
        $sql = "SELECT action_type, COUNT(*) as count, DATE(created_at) as date
                FROM `{$this->table}`
                WHERE created_at >= ? AND created_at <= ?
                GROUP BY action_type, DATE(created_at)
                ORDER BY date ASC, action_type";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$dateFrom, $dateTo . ' 23:59:59'], 'ss');
    }
}

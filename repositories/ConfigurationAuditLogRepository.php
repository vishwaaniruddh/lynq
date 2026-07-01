<?php
/**
 * Configuration Audit Log Repository
 * Provides data access operations for configuration audit log records
 * 
 * Requirements: 9.1, 9.2
 * - 9.1: Log all configuration actions with user, action type, timestamp, router serial, and IP_Master ID
 * - 9.2: View complete history with filtering
 */

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/ConfigurationAuditLog.php';

class ConfigurationAuditLogRepository extends BaseRepository {
    protected $table = 'configuration_audit_log';
    protected $primaryKey = 'id';
    
    // Audit log is global configuration data, not company-specific
    protected $applyCompanyFilter = false;
    protected $companyIdColumn = null;
    
    /**
     * Log a configuration action
     * Requirement 9.1: Log all configuration actions
     * 
     * @param string $actionType Type of action (use ConfigurationAuditLog constants)
     * @param int $userId User performing the action
     * @param string|null $routerSerialNumber Router serial number (if applicable)
     * @param int|null $ipMasterId IP_Master ID (if applicable)
     * @param array|null $details Additional context as associative array
     * @return int The ID of the created log entry
     * @throws Exception If logging fails
     */
    public function logAction(
        string $actionType,
        int $userId,
        ?string $routerSerialNumber = null,
        ?int $ipMasterId = null,
        ?array $details = null
    ): int {
        // Validate action type
        if (!ConfigurationAuditLog::isValidActionType($actionType)) {
            throw new Exception("Invalid action type: $actionType");
        }
        
        $fields = ['action_type', 'user_id'];
        $placeholders = ['?', '?'];
        $values = [$actionType, $userId];
        $types = 'si';
        
        if ($routerSerialNumber !== null) {
            $fields[] = 'router_serial_number';
            $placeholders[] = '?';
            $values[] = $routerSerialNumber;
            $types .= 's';
        }
        
        if ($ipMasterId !== null) {
            $fields[] = 'ip_master_id';
            $placeholders[] = '?';
            $values[] = $ipMasterId;
            $types .= 'i';
        }
        
        if ($details !== null && !empty($details)) {
            $fields[] = 'details';
            $placeholders[] = '?';
            $values[] = json_encode($details);
            $types .= 's';
        }
        
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create audit log entry");
        }
        
        return $insertId;
    }

    
    /**
     * Log lock acquired action
     * 
     * @param int $userId User who acquired the lock
     * @param string $routerSerialNumber Router serial number
     * @param int $ipMasterId IP_Master ID
     * @param array|null $details Additional details
     * @return int Log entry ID
     */
    public function logLockAcquired(int $userId, string $routerSerialNumber, int $ipMasterId, ?array $details = null): int {
        return $this->logAction(
            ConfigurationAuditLog::ACTION_LOCK_ACQUIRED,
            $userId,
            $routerSerialNumber,
            $ipMasterId,
            $details
        );
    }
    
    /**
     * Log lock released action
     * 
     * @param int $userId User who released the lock
     * @param string $routerSerialNumber Router serial number
     * @param int $ipMasterId IP_Master ID
     * @param array|null $details Additional details
     * @return int Log entry ID
     */
    public function logLockReleased(int $userId, string $routerSerialNumber, int $ipMasterId, ?array $details = null): int {
        return $this->logAction(
            ConfigurationAuditLog::ACTION_LOCK_RELEASED,
            $userId,
            $routerSerialNumber,
            $ipMasterId,
            $details
        );
    }
    
    /**
     * Log lock expired action
     * Requirement 9.3: Record timeout events
     * 
     * @param int $userId User whose lock expired (or system user ID)
     * @param string $routerSerialNumber Router serial number
     * @param int $ipMasterId IP_Master ID
     * @param array|null $details Additional details
     * @return int Log entry ID
     */
    public function logLockExpired(int $userId, string $routerSerialNumber, int $ipMasterId, ?array $details = null): int {
        return $this->logAction(
            ConfigurationAuditLog::ACTION_LOCK_EXPIRED,
            $userId,
            $routerSerialNumber,
            $ipMasterId,
            $details
        );
    }
    
    /**
     * Log configuration completed action
     * 
     * @param int $userId User who completed configuration
     * @param string $routerSerialNumber Router serial number
     * @param int $ipMasterId IP_Master ID
     * @param array|null $details Additional details
     * @return int Log entry ID
     */
    public function logConfigured(int $userId, string $routerSerialNumber, int $ipMasterId, ?array $details = null): int {
        return $this->logAction(
            ConfigurationAuditLog::ACTION_CONFIGURED,
            $userId,
            $routerSerialNumber,
            $ipMasterId,
            $details
        );
    }
    
    /**
     * Log unbind action
     * Requirement 6.3: Record unbind action with timestamp, user, and reason
     * 
     * @param int $userId User who performed unbind
     * @param string $routerSerialNumber Router serial number
     * @param int $ipMasterId IP_Master ID
     * @param string $reason Reason for unbinding
     * @param array|null $additionalDetails Additional details
     * @return int Log entry ID
     */
    public function logUnbound(int $userId, string $routerSerialNumber, int $ipMasterId, string $reason, ?array $additionalDetails = null): int {
        $details = ['unbind_reason' => $reason];
        if ($additionalDetails !== null) {
            $details = array_merge($details, $additionalDetails);
        }
        
        return $this->logAction(
            ConfigurationAuditLog::ACTION_UNBOUND,
            $userId,
            $routerSerialNumber,
            $ipMasterId,
            $details
        );
    }
    
    /**
     * Log IP created action
     * 
     * @param int $userId User who created the IP
     * @param int $ipMasterId IP_Master ID
     * @param array|null $details Additional details (IP addresses, etc.)
     * @return int Log entry ID
     */
    public function logIPCreated(int $userId, int $ipMasterId, ?array $details = null): int {
        return $this->logAction(
            ConfigurationAuditLog::ACTION_IP_CREATED,
            $userId,
            null,
            $ipMasterId,
            $details
        );
    }
    
    /**
     * Log IP updated action
     * 
     * @param int $userId User who updated the IP
     * @param int $ipMasterId IP_Master ID
     * @param array|null $details Additional details (changes made, etc.)
     * @return int Log entry ID
     */
    public function logIPUpdated(int $userId, int $ipMasterId, ?array $details = null): int {
        return $this->logAction(
            ConfigurationAuditLog::ACTION_IP_UPDATED,
            $userId,
            null,
            $ipMasterId,
            $details
        );
    }
    
    /**
     * Log IP deleted action
     * 
     * @param int $userId User who deleted the IP
     * @param int $ipMasterId IP_Master ID
     * @param array|null $details Additional details (IP addresses that were deleted, etc.)
     * @return int Log entry ID
     */
    public function logIPDeleted(int $userId, int $ipMasterId, ?array $details = null): int {
        return $this->logAction(
            ConfigurationAuditLog::ACTION_IP_DELETED,
            $userId,
            null,
            $ipMasterId,
            $details
        );
    }
    
    /**
     * Log bulk upload action
     * 
     * @param int $userId User who performed bulk upload
     * @param array|null $details Additional details (count, success/failure stats, etc.)
     * @return int Log entry ID
     */
    public function logBulkUpload(int $userId, ?array $details = null): int {
        return $this->logAction(
            ConfigurationAuditLog::ACTION_BULK_UPLOAD,
            $userId,
            null,
            null,
            $details
        );
    }

    
    /**
     * Get audit log history with filtering
     * Requirement 9.2: View complete history with filtering
     * 
     * @param array $filters Optional filters: action_type, user_id, router_serial_number, ip_master_id, date_from, date_to, search
     * @return array Array with 'data', 'total', 'page', 'limit', 'totalPages'
     */
    public function getHistory(array $filters = []): array {
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 20;
        $offset = ($page - 1) * $limit;
        $orderBy = $filters['orderBy'] ?? 'created_at';
        $orderDir = strtoupper($filters['orderDir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        
        $whereClause = [];
        $params = [];
        $types = '';
        
        // Action type filter
        if (isset($filters['action_type']) && $filters['action_type'] !== '') {
            $whereClause[] = "a.`action_type` = ?";
            $params[] = $filters['action_type'];
            $types .= 's';
        }
        
        // User ID filter
        if (isset($filters['user_id']) && $filters['user_id'] !== '') {
            $whereClause[] = "a.`user_id` = ?";
            $params[] = (int)$filters['user_id'];
            $types .= 'i';
        }
        
        // Router serial number filter
        if (!empty($filters['router_serial_number'])) {
            $whereClause[] = "a.`router_serial_number` LIKE ?";
            $params[] = '%' . $filters['router_serial_number'] . '%';
            $types .= 's';
        }
        
        // IP_Master ID filter
        if (isset($filters['ip_master_id']) && $filters['ip_master_id'] !== '') {
            $whereClause[] = "a.`ip_master_id` = ?";
            $params[] = (int)$filters['ip_master_id'];
            $types .= 'i';
        }
        
        // Date from filter
        if (!empty($filters['date_from'])) {
            $whereClause[] = "a.`created_at` >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
            $types .= 's';
        }
        
        // Date to filter
        if (!empty($filters['date_to'])) {
            $whereClause[] = "a.`created_at` <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= 's';
        }
        
        // General search filter (searches in router serial and details)
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereClause[] = "(a.`router_serial_number` LIKE ? OR a.`details` LIKE ? OR u.`username` LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        // Build WHERE clause
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        // Whitelist allowed order columns
        $allowedOrderColumns = ['id', 'action_type', 'user_id', 'router_serial_number', 'ip_master_id', 'created_at'];
        if (!in_array($orderBy, $allowedOrderColumns)) {
            $orderBy = 'created_at';
        }
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total FROM `{$this->table}` a 
                     LEFT JOIN `users` u ON a.`user_id` = u.`id`" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data with joins
        $dataSQL = "SELECT a.*, 
                           u.`username`,
                           m.`network_ip`, m.`router_ip`, m.`site_ip`, m.`subnet_mask`
                    FROM `{$this->table}` a
                    LEFT JOIN `users` u ON a.`user_id` = u.`id`
                    LEFT JOIN `ip_master` m ON a.`ip_master_id` = m.`id`" . 
                    $whereSQL . 
                    " ORDER BY a.`$orderBy` $orderDir LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $data = $this->db->getResults($dataSQL, $dataParams, $dataTypes);
        
        // Process results - decode JSON details and add action labels
        foreach ($data as &$row) {
            if (!empty($row['details'])) {
                $decoded = json_decode($row['details'], true);
                $row['details_decoded'] = $decoded !== null ? $decoded : [];
            } else {
                $row['details_decoded'] = [];
            }
            $row['action_label'] = ConfigurationAuditLog::getActionLabel($row['action_type']);
        }
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Find audit log entry by ID
     * 
     * @param int $id Audit log entry ID
     * @return array|null Audit log entry or null if not found
     */
    public function findById(int $id): ?array {
        $sql = "SELECT a.*, 
                       u.`username`,
                       m.`network_ip`, m.`router_ip`, m.`site_ip`, m.`subnet_mask`
                FROM `{$this->table}` a
                LEFT JOIN `users` u ON a.`user_id` = u.`id`
                LEFT JOIN `ip_master` m ON a.`ip_master_id` = m.`id`
                WHERE a.`id` = ?";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        
        if (empty($result)) {
            return null;
        }
        
        $row = $result[0];
        if (!empty($row['details'])) {
            $decoded = json_decode($row['details'], true);
            $row['details_decoded'] = $decoded !== null ? $decoded : [];
        } else {
            $row['details_decoded'] = [];
        }
        $row['action_label'] = ConfigurationAuditLog::getActionLabel($row['action_type']);
        
        return $row;
    }
    
    /**
     * Get recent activities for dashboard
     * Requirement 7.4: Dashboard recent configuration activities
     * 
     * @param int $limit Number of records to return
     * @return array Recent audit log entries
     */
    public function getRecentActivities(int $limit = 10): array {
        $result = $this->getHistory(['limit' => $limit, 'page' => 1]);
        return $result['data'];
    }
    
    /**
     * Get history for a specific router
     * 
     * @param string $routerSerialNumber Router serial number
     * @param int $limit Optional limit
     * @return array Audit log entries for the router
     */
    public function getRouterHistory(string $routerSerialNumber, int $limit = 100): array {
        $result = $this->getHistory([
            'router_serial_number' => $routerSerialNumber,
            'limit' => $limit,
            'page' => 1
        ]);
        return $result['data'];
    }
    
    /**
     * Get history for a specific IP_Master
     * 
     * @param int $ipMasterId IP_Master ID
     * @param int $limit Optional limit
     * @return array Audit log entries for the IP
     */
    public function getIPHistory(int $ipMasterId, int $limit = 100): array {
        $result = $this->getHistory([
            'ip_master_id' => $ipMasterId,
            'limit' => $limit,
            'page' => 1
        ]);
        return $result['data'];
    }
    
    /**
     * Get history for a specific user
     * 
     * @param int $userId User ID
     * @param int $limit Optional limit
     * @return array Audit log entries for the user
     */
    public function getUserHistory(int $userId, int $limit = 100): array {
        $result = $this->getHistory([
            'user_id' => $userId,
            'limit' => $limit,
            'page' => 1
        ]);
        return $result['data'];
    }
    
    /**
     * Get count by action type
     * 
     * @return array Counts by action type
     */
    public function getCountByActionType(): array {
        $sql = "SELECT `action_type`, COUNT(*) as count FROM `{$this->table}` GROUP BY `action_type`";
        $results = $this->db->getResults($sql, [], '');
        
        $counts = [];
        foreach (ConfigurationAuditLog::getActionTypes() as $type) {
            $counts[$type] = 0;
        }
        
        foreach ($results as $row) {
            $counts[$row['action_type']] = (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Get count by action type with filters applied
     * 
     * @param array $filters Optional filters: date_from, date_to, search, router_serial_number
     * @return array Counts by action type with total
     */
    public function getCountByActionTypeFiltered(array $filters = []): array {
        $whereClause = [];
        $params = [];
        $types = '';
        
        // Date from filter
        if (!empty($filters['date_from'])) {
            $whereClause[] = "`created_at` >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
            $types .= 's';
        }
        
        // Date to filter
        if (!empty($filters['date_to'])) {
            $whereClause[] = "`created_at` <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= 's';
        }
        
        // Router serial number filter
        if (!empty($filters['router_serial_number'])) {
            $whereClause[] = "`router_serial_number` LIKE ?";
            $params[] = '%' . $filters['router_serial_number'] . '%';
            $types .= 's';
        }
        
        // General search filter
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereClause[] = "(`router_serial_number` LIKE ? OR `details` LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        // Build WHERE clause
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        $sql = "SELECT `action_type`, COUNT(*) as count FROM `{$this->table}`" . $whereSQL . " GROUP BY `action_type`";
        $results = $this->db->getResults($sql, $params, $types);
        
        $counts = [];
        $total = 0;
        foreach (ConfigurationAuditLog::getActionTypes() as $type) {
            $counts[$type] = 0;
        }
        
        foreach ($results as $row) {
            $counts[$row['action_type']] = (int)$row['count'];
            $total += (int)$row['count'];
        }
        
        $counts['total'] = $total;
        
        return $counts;
    }
    
    /**
     * Get activity summary for date range
     * 
     * @param string $dateFrom Start date (Y-m-d format)
     * @param string $dateTo End date (Y-m-d format)
     * @return array Activity summary grouped by date and action type
     */
    public function getActivitySummary(string $dateFrom, string $dateTo): array {
        $sql = "SELECT `action_type`, COUNT(*) as count, DATE(`created_at`) as date
                FROM `{$this->table}`
                WHERE `created_at` >= ? AND `created_at` <= ?
                GROUP BY `action_type`, DATE(`created_at`)
                ORDER BY date ASC, `action_type`";
        
        return $this->db->getResults($sql, [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'], 'ss');
    }
    
    /**
     * Get all audit log entries for export (no pagination)
     * 
     * @param array $filters Optional filters
     * @return array Array of audit log entries
     */
    public function findAllForExport(array $filters = []): array {
        // Use getHistory with a very high limit
        $filters['limit'] = 10000;
        $filters['page'] = 1;
        $result = $this->getHistory($filters);
        return $result['data'];
    }
}

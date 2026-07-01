<?php
/**
 * Router IP Binding Model
 * Represents a permanent binding between a router and an IP_Master
 * 
 * Requirements: 5.1, 5.2, 5.3, 5.4, 6.2, 6.3
 * - Permanent bindings between routers and IP_Master records
 * - Tracks configuration details (timestamp, user, notes)
 * - Supports unbinding with reason tracking
 */

require_once __DIR__ . '/BaseModel.php';

class RouterIPBinding extends BaseModel {
    protected $table = 'router_ip_bindings';
    protected $fillable = [
        'router_serial_number', 'ip_master_id', 'configured_by',
        'configured_at', 'notes', 'status',
        'unbound_by', 'unbound_at', 'unbind_reason'
    ];
    
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_UNBOUND = 'unbound';
    
    /**
     * Get all valid statuses
     */
    public static function getStatuses() {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_UNBOUND
        ];
    }
    
    /**
     * Check if a status is valid
     */
    public static function isValidStatus($status) {
        return in_array($status, self::getStatuses());
    }
    
    /**
     * Find active bindings
     */
    public function findActive() {
        return $this->findAll(['status' => self::STATUS_ACTIVE], 'configured_at DESC');
    }
    
    /**
     * Find binding by router serial number
     * Requirement 5.3: Query router to get bound IP_Master details
     * 
     * @param string $routerSerial Router serial number
     * @return array|null Active binding or null
     */
    public function findByRouter($routerSerial) {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask, m.status as ip_status,
                       u.username as configured_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u ON b.configured_by = u.id
                WHERE b.router_serial_number = ? AND b.status = ?
                LIMIT 1";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$routerSerial, self::STATUS_ACTIVE], 'ss');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find binding by IP_Master ID
     * Requirement 5.4: Query IP_Master to get bound router serial number
     * 
     * @param int $ipMasterId IP_Master ID
     * @return array|null Active binding or null
     */
    public function findByIPMaster($ipMasterId) {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask,
                       u.username as configured_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u ON b.configured_by = u.id
                WHERE b.ip_master_id = ? AND b.status = ?
                LIMIT 1";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$ipMasterId, self::STATUS_ACTIVE], 'is');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Check if router is already configured
     * 
     * @param string $routerSerial Router serial number
     * @return bool True if router has active binding
     */
    public function isRouterConfigured($routerSerial) {
        $binding = $this->findByRouter($routerSerial);
        return $binding !== null;
    }
    
    /**
     * Check if IP_Master is already bound
     * 
     * @param int $ipMasterId IP_Master ID
     * @return bool True if IP has active binding
     */
    public function isIPBound($ipMasterId) {
        $binding = $this->findByIPMaster($ipMasterId);
        return $binding !== null;
    }
    
    /**
     * Get all active bindings with details
     * 
     * @return array Active bindings with IP and user details
     */
    public function getActiveBindingsWithDetails() {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask,
                       u.username as configured_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u ON b.configured_by = u.id
                WHERE b.status = ?
                ORDER BY b.configured_at DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, [self::STATUS_ACTIVE], 's');
    }
    
    /**
     * Get binding history for a router
     * 
     * @param string $routerSerial Router serial number
     * @return array All bindings (active and unbound) for the router
     */
    public function getRouterHistory($routerSerial) {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask,
                       u1.username as configured_by_username,
                       u2.username as unbound_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u1 ON b.configured_by = u1.id
                LEFT JOIN users u2 ON b.unbound_by = u2.id
                WHERE b.router_serial_number = ?
                ORDER BY b.configured_at DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$routerSerial], 's');
    }
    
    /**
     * Get binding history for an IP_Master
     * 
     * @param int $ipMasterId IP_Master ID
     * @return array All bindings (active and unbound) for the IP
     */
    public function getIPHistory($ipMasterId) {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask,
                       u1.username as configured_by_username,
                       u2.username as unbound_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u1 ON b.configured_by = u1.id
                LEFT JOIN users u2 ON b.unbound_by = u2.id
                WHERE b.ip_master_id = ?
                ORDER BY b.configured_at DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$ipMasterId], 'i');
    }
    
    /**
     * Unbind IP from router
     * Requirement 6.2: Remove binding and set IP_Master status back to Available
     * 
     * @param int $id Binding ID
     * @param int $userId User performing unbind
     * @param string $reason Reason for unbinding
     * @return array|null Updated binding or null
     */
    public function unbind($id, $userId, $reason) {
        return $this->update($id, [
            'status' => self::STATUS_UNBOUND,
            'unbound_by' => $userId,
            'unbound_at' => date('Y-m-d H:i:s'),
            'unbind_reason' => $reason
        ]);
    }
    
    /**
     * Count active bindings
     * Requirement 7.1: Dashboard router statistics
     * 
     * @return int Number of active bindings
     */
    public function countActive() {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE status = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [self::STATUS_ACTIVE], 's');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Get configured routers count
     * 
     * @return int Number of configured routers
     */
    public function getConfiguredRoutersCount() {
        $sql = "SELECT COUNT(DISTINCT router_serial_number) as count 
                FROM `{$this->table}` 
                WHERE status = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [self::STATUS_ACTIVE], 's');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Search bindings with filters
     * 
     * @param array $filters Search filters
     * @return array Matching bindings
     */
    public function search($filters = []) {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask,
                       u1.username as configured_by_username,
                       u2.username as unbound_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u1 ON b.configured_by = u1.id
                LEFT JOIN users u2 ON b.unbound_by = u2.id
                WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($filters['status'])) {
            $sql .= " AND b.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['router_serial_number'])) {
            $sql .= " AND b.router_serial_number LIKE ?";
            $params[] = '%' . $filters['router_serial_number'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['configured_by'])) {
            $sql .= " AND b.configured_by = ?";
            $params[] = $filters['configured_by'];
            $types .= 'i';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND b.configured_at >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND b.configured_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (b.router_serial_number LIKE ? OR m.network_ip LIKE ? OR m.router_ip LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            $types .= 'sss';
        }
        
        $sql .= " ORDER BY b.configured_at DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Get recent configurations
     * Requirement 7.4: Dashboard recent configuration activities
     * 
     * @param int $limit Number of records to return
     * @return array Recent configurations
     */
    public function getRecentConfigurations($limit = 10) {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask,
                       u.username as configured_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u ON b.configured_by = u.id
                WHERE b.status = ?
                ORDER BY b.configured_at DESC
                LIMIT ?";
        
        return DatabaseConfig::getInstance()->getResults($sql, [self::STATUS_ACTIVE, $limit], 'si');
    }
}

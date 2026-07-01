<?php
/**
 * InventoryAuditLog Repository
 * Provides data access for inventory audit logs with filtering capabilities
 * 
 * Requirements: 12.1
 * - Log user, action type, timestamp, source location, and destination location for all inventory actions
 */

require_once __DIR__ . '/BaseRepository.php';

class InventoryAuditLogRepository extends BaseRepository {
    protected $table = 'inventory_audit_log';
    protected $companyIdColumn = null;
    protected $applyCompanyFilter = false;
    
    // Action type constants
    const ACTION_STOCK_ENTRY = 'stock_entry';
    const ACTION_DISPATCH = 'dispatch';
    const ACTION_TRANSFER = 'transfer';
    const ACTION_STATUS_CHANGE = 'status_change';
    const ACTION_REPAIR = 'repair';
    const ACTION_RETURN = 'return';
    const ACTION_SCRAP = 'scrap';
    const ACTION_LOST = 'lost';
    const ACTION_ACKNOWLEDGE = 'acknowledge';
    
    // Entity type constants
    const ENTITY_ASSET = 'asset';
    const ENTITY_STOCK = 'stock';
    const ENTITY_DISPATCH = 'dispatch';
    const ENTITY_TRANSFER = 'transfer';
    const ENTITY_REPAIR = 'repair';
    
    // Location type constants
    const LOCATION_WAREHOUSE = 'warehouse';
    const LOCATION_COMPANY = 'company';
    const LOCATION_USER = 'user';
    
    /**
     * Get all valid action types
     */
    public static function getActionTypes() {
        return [
            self::ACTION_STOCK_ENTRY,
            self::ACTION_DISPATCH,
            self::ACTION_TRANSFER,
            self::ACTION_STATUS_CHANGE,
            self::ACTION_REPAIR,
            self::ACTION_RETURN,
            self::ACTION_SCRAP,
            self::ACTION_LOST,
            self::ACTION_ACKNOWLEDGE
        ];
    }
    
    /**
     * Get all valid entity types
     */
    public static function getEntityTypes() {
        return [
            self::ENTITY_ASSET,
            self::ENTITY_STOCK,
            self::ENTITY_DISPATCH,
            self::ENTITY_TRANSFER,
            self::ENTITY_REPAIR
        ];
    }
    
    /**
     * Log an action
     */
    public function logAction($actionType, $entityType, $entityId, $userId, $data = []) {
        $logData = [
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId
        ];
        
        if (isset($data['from_location_type'])) {
            $logData['from_location_type'] = $data['from_location_type'];
        }
        if (isset($data['from_location_id'])) {
            $logData['from_location_id'] = $data['from_location_id'];
        }
        if (isset($data['to_location_type'])) {
            $logData['to_location_type'] = $data['to_location_type'];
        }
        if (isset($data['to_location_id'])) {
            $logData['to_location_id'] = $data['to_location_id'];
        }
        if (isset($data['old_values'])) {
            $logData['old_values'] = is_array($data['old_values']) ? json_encode($data['old_values']) : $data['old_values'];
        }
        if (isset($data['new_values'])) {
            $logData['new_values'] = is_array($data['new_values']) ? json_encode($data['new_values']) : $data['new_values'];
        }
        if (isset($data['ip_address'])) {
            $logData['ip_address'] = $data['ip_address'];
        }
        if (isset($data['user_agent'])) {
            $logData['user_agent'] = $data['user_agent'];
        }
        if (isset($data['notes'])) {
            $logData['notes'] = $data['notes'];
        }
        
        return $this->create($logData);
    }
    
    /**
     * Find logs by entity
     */
    public function findByEntity($entityType, $entityId) {
        $sql = "SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM `{$this->table}` l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.`entity_type` = ? AND l.`entity_id` = ?
                ORDER BY l.`created_at` DESC";
        return $this->db->getResults($sql, [$entityType, $entityId], 'si');
    }
    
    /**
     * Find logs by user
     */
    public function findByUser($userId) {
        $sql = "SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM `{$this->table}` l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.user_id = ?
                ORDER BY l.created_at DESC";
        return $this->db->getResults($sql, [$userId], 'i');
    }
    
    /**
     * Find logs by action type
     */
    public function findByActionType($actionType) {
        $sql = "SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM `{$this->table}` l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.action_type = ?
                ORDER BY l.created_at DESC";
        return $this->db->getResults($sql, [$actionType], 's');
    }
    
    /**
     * Get log with full details
     */
    public function findWithDetails($id) {
        $sql = "SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM `{$this->table}` l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.id = ?";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all logs with full details
     */
    public function findAllWithDetails($conditions = [], $orderBy = 'l.created_at DESC') {
        $sql = "SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM `{$this->table}` l
                LEFT JOIN users u ON l.user_id = u.id";
        $params = [];
        $types = '';
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "l.`$field` = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $sql .= " ORDER BY $orderBy";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get movement history for asset
     */
    public function getAssetHistory($assetId) {
        return $this->findByEntity(self::ENTITY_ASSET, $assetId);
    }
    
    /**
     * Search logs with filters
     */
    public function search($filters = []) {
        $sql = "SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM `{$this->table}` l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($filters['action_type'])) {
            $sql .= " AND l.action_type = ?";
            $params[] = $filters['action_type'];
            $types .= 's';
        }
        
        if (!empty($filters['entity_type'])) {
            $sql .= " AND l.entity_type = ?";
            $params[] = $filters['entity_type'];
            $types .= 's';
        }
        
        if (!empty($filters['entity_id'])) {
            $sql .= " AND l.entity_id = ?";
            $params[] = $filters['entity_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND l.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND l.created_at >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND l.created_at <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        $sql .= " ORDER BY l.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . intval($filters['limit']);
        }
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get recent activity
     */
    public function getRecentActivity($limit = 50) {
        return $this->search(['limit' => $limit]);
    }
    
    /**
     * Generate audit report
     */
    public function generateReport($filters = []) {
        $sql = "SELECT 
                    DATE(l.created_at) as date,
                    l.action_type,
                    COUNT(*) as count
                FROM `{$this->table}` l
                WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND l.created_at >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND l.created_at <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        $sql .= " GROUP BY DATE(l.created_at), l.action_type
                  ORDER BY date DESC, action_type";
        
        return $this->db->getResults($sql, $params, $types);
    }
}

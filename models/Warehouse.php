<?php
/**
 * Warehouse Model
 * Represents a physical storage location belonging to a company
 * 
 * Requirements: 1.1, 1.2, 1.3
 * - Store warehouse with name, location, company assignment, and active status
 * - Display only warehouses belonging to companies the user has access to
 * - Prevent new dispatches from inactive warehouses while preserving existing stock records
 */

require_once __DIR__ . '/BaseModel.php';

class Warehouse extends BaseModel {
    protected $table = 'warehouses';
    protected $fillable = [
        'name', 'location', 'company_id', 'status', 
        'created_by', 'updated_by'
    ];
    
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    
    /**
     * Get all valid statuses
     */
    public static function getStatuses() {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE
        ];
    }
    
    /**
     * Check if a status is valid
     */
    public static function isValidStatus($status) {
        return in_array($status, self::getStatuses());
    }
    
    /**
     * Find warehouses by company
     */
    public function findByCompany($companyId) {
        return $this->findAll(['company_id' => $companyId], 'name');
    }
    
    /**
     * Find active warehouses
     */
    public function findActive() {
        return $this->findAll(['status' => self::STATUS_ACTIVE], 'name');
    }
    
    /**
     * Find active warehouses by company
     */
    public function findActiveByCompany($companyId) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `company_id` = ? AND `status` = ? 
                ORDER BY `name`";
        return DatabaseConfig::getInstance()->getResults($sql, [$companyId, self::STATUS_ACTIVE], 'is');
    }
    
    /**
     * Check if warehouse is active
     */
    public function isActive($id) {
        $warehouse = $this->find($id);
        return $warehouse && $warehouse['status'] === self::STATUS_ACTIVE;
    }
    
    /**
     * Check if warehouse name is unique within company
     */
    public function isNameUniqueInCompany($name, $companyId, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `name` = ? AND `company_id` = ?";
        $params = [$name, $companyId];
        $types = 'si';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = DatabaseConfig::getInstance()->getResults($sql, $params, $types);
        return $result[0]['count'] == 0;
    }
    
    /**
     * Get warehouse with company details
     */
    public function findWithCompany($id) {
        $sql = "SELECT w.*, c.name as company_name, c.type as company_type
                FROM `{$this->table}` w
                LEFT JOIN companies c ON w.company_id = c.id
                WHERE w.id = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all warehouses with company details
     */
    public function findAllWithCompany($conditions = [], $orderBy = 'w.name') {
        $sql = "SELECT w.*, c.name as company_name, c.type as company_type
                FROM `{$this->table}` w
                LEFT JOIN companies c ON w.company_id = c.id";
        $params = [];
        $types = '';
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "w.`$field` = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $sql .= " ORDER BY $orderBy";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Deactivate warehouse (soft disable)
     * Requirement 1.3: Prevent new dispatches while preserving existing stock records
     */
    public function deactivate($id, $updatedBy = null) {
        $data = ['status' => self::STATUS_INACTIVE];
        if ($updatedBy !== null) {
            $data['updated_by'] = $updatedBy;
        }
        return $this->update($id, $data);
    }
    
    /**
     * Activate warehouse
     */
    public function activate($id, $updatedBy = null) {
        $data = ['status' => self::STATUS_ACTIVE];
        if ($updatedBy !== null) {
            $data['updated_by'] = $updatedBy;
        }
        return $this->update($id, $data);
    }
    
    /**
     * Get stock count for warehouse
     */
    public function getStockCount($id) {
        $sql = "SELECT COALESCE(SUM(quantity), 0) as total_quantity
                FROM stock WHERE warehouse_id = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return $result[0]['total_quantity'] ?? 0;
    }
    
    /**
     * Get asset count for warehouse
     */
    public function getAssetCount($id) {
        $sql = "SELECT COUNT(*) as count FROM assets 
                WHERE warehouse_id = ? AND status NOT IN ('scrapped', 'lost')";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return $result[0]['count'] ?? 0;
    }
}

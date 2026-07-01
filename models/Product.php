<?php
/**
 * Product Model
 * Represents a product/material definition in the inventory system
 * 
 * Requirements: 2.1, 2.2, 2.3, 2.4
 * - Product name, category, unit of measure, inventory type (INTERNAL/SITE), serializable flag, repairable flag
 * - Serializable products require serial number entry for each unit
 * - Non-serializable products tracked by quantity only
 * - Filter by category, inventory type, and serializable status
 */

require_once __DIR__ . '/BaseModel.php';

class Product extends BaseModel {
    protected $table = 'products';
    protected $fillable = [
        'name', 'category_id', 'unit_of_measure', 
        'inventory_type', 'is_serializable', 'is_repairable',
        'low_stock_threshold', 'status', 'description',
        'created_by', 'updated_by'
    ];
    
    // Inventory type constants
    const TYPE_INTERNAL = 'INTERNAL';
    const TYPE_SITE = 'SITE';
    
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    
    /**
     * Get all valid inventory types
     */
    public static function getInventoryTypes() {
        return [
            self::TYPE_INTERNAL,
            self::TYPE_SITE
        ];
    }
    
    /**
     * Check if an inventory type is valid
     */
    public static function isValidInventoryType($type) {
        return in_array($type, self::getInventoryTypes());
    }
    
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
     * Find active products
     */
    public function findActive() {
        return $this->findAll(['status' => self::STATUS_ACTIVE], 'name');
    }
    
    /**
     * Find products by category
     */
    public function findByCategory($categoryId) {
        return $this->findAll(['category_id' => $categoryId, 'status' => self::STATUS_ACTIVE], 'name');
    }
    
    /**
     * Find products by inventory type
     */
    public function findByInventoryType($type) {
        return $this->findAll(['inventory_type' => $type, 'status' => self::STATUS_ACTIVE], 'name');
    }
    
    /**
     * Find serializable products
     */
    public function findSerializable() {
        return $this->findAll(['is_serializable' => 1, 'status' => self::STATUS_ACTIVE], 'name');
    }
    
    /**
     * Find non-serializable products
     */
    public function findNonSerializable() {
        return $this->findAll(['is_serializable' => 0, 'status' => self::STATUS_ACTIVE], 'name');
    }
    
    /**
     * Find repairable products
     */
    public function findRepairable() {
        return $this->findAll(['is_repairable' => 1, 'status' => self::STATUS_ACTIVE], 'name');
    }
    
    /**
     * Check if product is serializable
     */
    public function isSerializable($id) {
        $product = $this->find($id);
        return $product && $product['is_serializable'] == 1;
    }
    
    /**
     * Check if product is repairable
     */
    public function isRepairable($id) {
        $product = $this->find($id);
        return $product && $product['is_repairable'] == 1;
    }
    
    /**
     * Get product with category details
     */
    public function findWithCategory($id) {
        $sql = "SELECT p.*, c.name as category_name
                FROM `{$this->table}` p
                LEFT JOIN product_categories c ON p.category_id = c.id
                WHERE p.id = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all products with category details
     */
    public function findAllWithCategory($conditions = [], $orderBy = 'p.name') {
        $sql = "SELECT p.*, c.name as category_name
                FROM `{$this->table}` p
                LEFT JOIN product_categories c ON p.category_id = c.id";
        $params = [];
        $types = '';
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "p.`$field` = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $sql .= " ORDER BY $orderBy";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Search products with filters
     * Requirement 2.4: Filter by category, inventory type, and serializable status
     */
    public function search($filters = []) {
        $sql = "SELECT p.*, c.name as category_name
                FROM `{$this->table}` p
                LEFT JOIN product_categories c ON p.category_id = c.id
                WHERE p.status = ?";
        $params = [self::STATUS_ACTIVE];
        $types = 's';
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['inventory_type'])) {
            $sql .= " AND p.inventory_type = ?";
            $params[] = $filters['inventory_type'];
            $types .= 's';
        }
        
        if (isset($filters['is_serializable'])) {
            $sql .= " AND p.is_serializable = ?";
            $params[] = $filters['is_serializable'] ? 1 : 0;
            $types .= 'i';
        }
        
        if (isset($filters['is_repairable'])) {
            $sql .= " AND p.is_repairable = ?";
            $params[] = $filters['is_repairable'] ? 1 : 0;
            $types .= 'i';
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        $sql .= " ORDER BY p.name";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Get total stock quantity for product across all warehouses
     */
    public function getTotalStock($id) {
        $product = $this->find($id);
        if (!$product) {
            return 0;
        }
        
        if ($product['is_serializable']) {
            // Count assets for serializable products
            $sql = "SELECT COUNT(*) as count FROM assets 
                    WHERE product_id = ? AND status NOT IN ('scrapped', 'lost')";
        } else {
            // Sum stock quantities for non-serializable products
            $sql = "SELECT COALESCE(SUM(quantity), 0) as count FROM stock WHERE product_id = ?";
        }
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Get stock quantity for product in specific warehouse
     */
    public function getStockInWarehouse($id, $warehouseId) {
        $product = $this->find($id);
        if (!$product) {
            return 0;
        }
        
        if ($product['is_serializable']) {
            // Count assets for serializable products
            $sql = "SELECT COUNT(*) as count FROM assets 
                    WHERE product_id = ? AND warehouse_id = ? AND status = 'in_stock'";
        } else {
            // Get stock quantity for non-serializable products
            $sql = "SELECT COALESCE(quantity, 0) as count FROM stock 
                    WHERE product_id = ? AND warehouse_id = ?";
        }
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id, $warehouseId], 'ii');
        return $result[0]['count'] ?? 0;
    }
}

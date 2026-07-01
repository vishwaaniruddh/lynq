<?php
/**
 * ProductCategory Model
 * Represents a category for organizing products
 * 
 * Requirements: 2.1, 2.4
 * - Product category for organizing inventory items
 * - Support hierarchical categories with parent_id
 */

require_once __DIR__ . '/BaseModel.php';

class ProductCategory extends BaseModel {
    protected $table = 'product_categories';
    protected $fillable = [
        'name', 'parent_id', 'status',
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
     * Find active categories
     */
    public function findActive() {
        return $this->findAll(['status' => self::STATUS_ACTIVE], 'name');
    }
    
    /**
     * Find root categories (no parent)
     */
    public function findRootCategories() {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `parent_id` IS NULL AND `status` = ? 
                ORDER BY `name`";
        return DatabaseConfig::getInstance()->getResults($sql, [self::STATUS_ACTIVE], 's');
    }
    
    /**
     * Find child categories
     */
    public function findChildren($parentId) {
        return $this->findAll(['parent_id' => $parentId, 'status' => self::STATUS_ACTIVE], 'name');
    }
    
    /**
     * Get category with parent details
     */
    public function findWithParent($id) {
        $sql = "SELECT c.*, p.name as parent_name
                FROM `{$this->table}` c
                LEFT JOIN `{$this->table}` p ON c.parent_id = p.id
                WHERE c.id = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all categories with parent details
     */
    public function findAllWithParent($conditions = [], $orderBy = 'c.name') {
        $sql = "SELECT c.*, p.name as parent_name
                FROM `{$this->table}` c
                LEFT JOIN `{$this->table}` p ON c.parent_id = p.id";
        $params = [];
        $types = '';
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "c.`$field` = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $sql .= " ORDER BY $orderBy";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Get category tree (hierarchical structure)
     */
    public function getCategoryTree() {
        $categories = $this->findAll(['status' => self::STATUS_ACTIVE], 'name');
        return $this->buildTree($categories);
    }
    
    /**
     * Build hierarchical tree from flat array
     */
    private function buildTree($categories, $parentId = null) {
        $tree = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] == $parentId) {
                $children = $this->buildTree($categories, $category['id']);
                if ($children) {
                    $category['children'] = $children;
                }
                $tree[] = $category;
            }
        }
        return $tree;
    }
    
    /**
     * Get product count for category
     */
    public function getProductCount($id) {
        $sql = "SELECT COUNT(*) as count FROM products WHERE category_id = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Check if category has children
     */
    public function hasChildren($id) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE parent_id = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return ($result[0]['count'] ?? 0) > 0;
    }
}

<?php
/**
 * MaterialMasterItem Model
 * Represents a product item within a Material Master template
 * 
 * Requirements: 1.3
 * - Product association with quantity
 * - Links Material Master to Products
 */

require_once __DIR__ . '/BaseModel.php';

class MaterialMasterItem extends BaseModel {
    protected $table = 'material_master_items';
    protected $fillable = [
        'material_master_id', 'product_id', 'quantity', 'created_at'
    ];
    
    /**
     * Find items by Material Master ID
     * 
     * @param int $masterId Material Master ID
     * @return array Items
     */
    public function findByMasterId(int $masterId): array {
        return $this->findAll(['material_master_id' => $masterId]);
    }
    
    /**
     * Find items with product details
     * Requirement 1.3
     * 
     * @param int $masterId Material Master ID
     * @return array Items with product details
     */
    public function findByMasterIdWithProduct(int $masterId): array {
        $sql = "SELECT mmi.*, 
                       p.name as product_name, 
                       p.unit_of_measure,
                       p.is_serializable,
                       p.is_repairable,
                       p.inventory_type,
                       pc.name as category_name
                FROM `{$this->table}` mmi
                LEFT JOIN products p ON mmi.product_id = p.id
                LEFT JOIN product_categories pc ON p.category_id = pc.id
                WHERE mmi.material_master_id = ?
                ORDER BY p.name";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$masterId], 'i');
    }
    
    /**
     * Delete all items for a Material Master
     * 
     * @param int $masterId Material Master ID
     * @return bool Success
     */
    public function deleteByMasterId(int $masterId): bool {
        $sql = "DELETE FROM `{$this->table}` WHERE `material_master_id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$masterId], 'i');
        $stmt->close();
        return true;
    }
    
    /**
     * Create multiple items for a Material Master
     * 
     * @param int $masterId Material Master ID
     * @param array $items Array of items with product_id and quantity
     * @return bool Success
     */
    public function createBulk(int $masterId, array $items): bool {
        if (empty($items)) {
            return true;
        }
        
        $sql = "INSERT INTO `{$this->table}` (`material_master_id`, `product_id`, `quantity`, `created_at`) VALUES ";
        $placeholders = [];
        $params = [];
        $types = '';
        $now = date('Y-m-d H:i:s');
        
        foreach ($items as $item) {
            $placeholders[] = "(?, ?, ?, ?)";
            $params[] = $masterId;
            $params[] = $item['product_id'];
            $params[] = $item['quantity'];
            $params[] = $now;
            $types .= 'iiis';
        }
        
        $sql .= implode(', ', $placeholders);
        
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, $params, $types);
        $stmt->close();
        
        return true;
    }
    
    /**
     * Check if product exists in Material Master
     * 
     * @param int $masterId Material Master ID
     * @param int $productId Product ID
     * @return bool True if exists
     */
    public function productExistsInMaster(int $masterId, int $productId): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `material_master_id` = ? AND `product_id` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$masterId, $productId], 'ii');
        return $result[0]['count'] > 0;
    }
    
    /**
     * Get total quantity of all items in a Material Master
     * 
     * @param int $masterId Material Master ID
     * @return int Total quantity
     */
    public function getTotalQuantity(int $masterId): int {
        $sql = "SELECT COALESCE(SUM(quantity), 0) as total FROM `{$this->table}` WHERE `material_master_id` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$masterId], 'i');
        return (int)($result[0]['total'] ?? 0);
    }
    
    /**
     * Validate item data
     * 
     * @param array $data Item data to validate
     * @return array Validation result with 'isValid' and 'errors'
     */
    public function validate(array $data): array {
        $errors = [];
        
        // Validate material_master_id
        if (!isset($data['material_master_id']) || !is_numeric($data['material_master_id'])) {
            $errors[] = [
                'field' => 'material_master_id',
                'message' => 'Material Master ID is required',
                'code' => 'REQUIRED_FIELD_MISSING'
            ];
        }
        
        // Validate product_id
        if (!isset($data['product_id']) || !is_numeric($data['product_id'])) {
            $errors[] = [
                'field' => 'product_id',
                'message' => 'Product ID is required',
                'code' => 'REQUIRED_FIELD_MISSING'
            ];
        }
        
        // Validate quantity
        if (!isset($data['quantity']) || !is_numeric($data['quantity']) || $data['quantity'] < 1) {
            $errors[] = [
                'field' => 'quantity',
                'message' => 'Quantity must be a positive integer',
                'code' => 'INVALID_QUANTITY'
            ];
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate multiple items
     * 
     * @param array $items Array of items to validate
     * @return array Validation result with 'isValid' and 'errors'
     */
    public function validateBulk(array $items): array {
        $errors = [];
        
        if (empty($items)) {
            $errors[] = [
                'field' => 'items',
                'message' => 'At least one product is required',
                'code' => 'ITEMS_REQUIRED'
            ];
            return ['isValid' => false, 'errors' => $errors];
        }
        
        $productIds = [];
        foreach ($items as $index => $item) {
            $itemValidation = $this->validate($item);
            if (!$itemValidation['isValid']) {
                foreach ($itemValidation['errors'] as $error) {
                    $error['index'] = $index;
                    $errors[] = $error;
                }
            }
            
            // Check for duplicate products
            if (isset($item['product_id'])) {
                if (in_array($item['product_id'], $productIds)) {
                    $errors[] = [
                        'field' => 'product_id',
                        'message' => 'Duplicate product in items list',
                        'code' => 'DUPLICATE_PRODUCT',
                        'index' => $index
                    ];
                }
                $productIds[] = $item['product_id'];
            }
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
}

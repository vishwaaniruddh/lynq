<?php
/**
 * MaterialRequestItem Model
 * Represents a product item within a Material Request
 * 
 * Requirements: 4.3
 * - Product association with requested, dispatched, and received quantities
 * - Links Material Request to Products
 */

require_once __DIR__ . '/BaseModel.php';

class MaterialRequestItem extends BaseModel {
    protected $table = 'material_request_items';
    protected $fillable = [
        'material_request_id', 'product_id', 'quantity_requested',
        'quantity_dispatched', 'quantity_received', 'created_at'
    ];
    
    /**
     * Find items by Material Request ID
     * 
     * @param int $requestId Material Request ID
     * @return array Items
     */
    public function findByRequestId(int $requestId): array {
        return $this->findAll(['material_request_id' => $requestId]);
    }
    
    /**
     * Find items with product details
     * Requirement 4.3
     * 
     * @param int $requestId Material Request ID
     * @return array Items with product details
     */
    public function findByRequestIdWithProduct(int $requestId): array {
        $sql = "SELECT mri.*, 
                       p.name as product_name, 
                       p.unit_of_measure,
                       p.is_serializable,
                       p.is_repairable,
                       p.inventory_type,
                       pc.name as category_name
                FROM `{$this->table}` mri
                LEFT JOIN products p ON mri.product_id = p.id
                LEFT JOIN product_categories pc ON p.category_id = pc.id
                WHERE mri.material_request_id = ?
                ORDER BY p.name";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$requestId], 'i');
    }
    
    /**
     * Delete all items for a Material Request
     * 
     * @param int $requestId Material Request ID
     * @return bool Success
     */
    public function deleteByRequestId(int $requestId): bool {
        $sql = "DELETE FROM `{$this->table}` WHERE `material_request_id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$requestId], 'i');
        $stmt->close();
        return true;
    }
    
    /**
     * Create multiple items for a Material Request
     * 
     * @param int $requestId Material Request ID
     * @param array $items Array of items with product_id and quantity_requested
     * @return bool Success
     */
    public function createBulk(int $requestId, array $items): bool {
        if (empty($items)) {
            return true;
        }
        
        $sql = "INSERT INTO `{$this->table}` (`material_request_id`, `product_id`, `quantity_requested`, `quantity_dispatched`, `quantity_received`, `created_at`) VALUES ";
        $placeholders = [];
        $params = [];
        $types = '';
        $now = date('Y-m-d H:i:s');
        
        foreach ($items as $item) {
            $placeholders[] = "(?, ?, ?, ?, ?, ?)";
            $params[] = $requestId;
            $params[] = $item['product_id'];
            $params[] = $item['quantity_requested'] ?? $item['quantity'];
            $params[] = 0; // quantity_dispatched
            $params[] = 0; // quantity_received
            $params[] = $now;
            $types .= 'iiiiss';
        }
        
        $sql .= implode(', ', $placeholders);
        
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, $params, $types);
        $stmt->close();
        
        return true;
    }
    
    /**
     * Create items from Material Master items
     * 
     * @param int $requestId Material Request ID
     * @param int $masterId Material Master ID
     * @return bool Success
     */
    public function createFromMaster(int $requestId, int $masterId): bool {
        $sql = "INSERT INTO `{$this->table}` (`material_request_id`, `product_id`, `quantity_requested`, `quantity_dispatched`, `quantity_received`, `created_at`)
                SELECT ?, product_id, quantity, 0, 0, NOW()
                FROM material_master_items
                WHERE material_master_id = ?";
        
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$requestId, $masterId], 'ii');
        $stmt->close();
        
        return true;
    }
    
    /**
     * Update dispatched quantity for an item
     * 
     * @param int $id Item ID
     * @param int $quantity Dispatched quantity
     * @return bool Success
     */
    public function updateDispatchedQuantity(int $id, int $quantity): bool {
        $sql = "UPDATE `{$this->table}` SET `quantity_dispatched` = ? WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$quantity, $id], 'ii');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Update received quantity for an item
     * 
     * @param int $id Item ID
     * @param int $quantity Received quantity
     * @return bool Success
     */
    public function updateReceivedQuantity(int $id, int $quantity): bool {
        $sql = "UPDATE `{$this->table}` SET `quantity_received` = ? WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$quantity, $id], 'ii');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Update all items to dispatched (set dispatched = requested)
     * 
     * @param int $requestId Material Request ID
     * @return bool Success
     */
    public function markAllDispatched(int $requestId): bool {
        $sql = "UPDATE `{$this->table}` SET `quantity_dispatched` = `quantity_requested` WHERE `material_request_id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$requestId], 'i');
        $stmt->close();
        
        return true;
    }
    
    /**
     * Update all items to received (set received = dispatched)
     * 
     * @param int $requestId Material Request ID
     * @return bool Success
     */
    public function markAllReceived(int $requestId): bool {
        $sql = "UPDATE `{$this->table}` SET `quantity_received` = `quantity_dispatched` WHERE `material_request_id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$requestId], 'i');
        $stmt->close();
        
        return true;
    }
    
    /**
     * Get total requested quantity for a Material Request
     * 
     * @param int $requestId Material Request ID
     * @return int Total quantity
     */
    public function getTotalRequestedQuantity(int $requestId): int {
        $sql = "SELECT COALESCE(SUM(quantity_requested), 0) as total FROM `{$this->table}` WHERE `material_request_id` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$requestId], 'i');
        return (int)($result[0]['total'] ?? 0);
    }
    
    /**
     * Get total dispatched quantity for a Material Request
     * 
     * @param int $requestId Material Request ID
     * @return int Total quantity
     */
    public function getTotalDispatchedQuantity(int $requestId): int {
        $sql = "SELECT COALESCE(SUM(quantity_dispatched), 0) as total FROM `{$this->table}` WHERE `material_request_id` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$requestId], 'i');
        return (int)($result[0]['total'] ?? 0);
    }
    
    /**
     * Get total received quantity for a Material Request
     * 
     * @param int $requestId Material Request ID
     * @return int Total quantity
     */
    public function getTotalReceivedQuantity(int $requestId): int {
        $sql = "SELECT COALESCE(SUM(quantity_received), 0) as total FROM `{$this->table}` WHERE `material_request_id` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$requestId], 'i');
        return (int)($result[0]['total'] ?? 0);
    }
    
    /**
     * Get item count for a Material Request
     * 
     * @param int $requestId Material Request ID
     * @return int Item count
     */
    public function getItemCount(int $requestId): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `material_request_id` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$requestId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Check if product exists in Material Request
     * 
     * @param int $requestId Material Request ID
     * @param int $productId Product ID
     * @return bool True if exists
     */
    public function productExistsInRequest(int $requestId, int $productId): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `material_request_id` = ? AND `product_id` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$requestId, $productId], 'ii');
        return $result[0]['count'] > 0;
    }
    
    /**
     * Validate item data
     * 
     * @param array $data Item data to validate
     * @return array Validation result with 'isValid' and 'errors'
     */
    public function validate(array $data): array {
        $errors = [];
        
        // Validate material_request_id
        if (!isset($data['material_request_id']) || !is_numeric($data['material_request_id'])) {
            $errors[] = [
                'field' => 'material_request_id',
                'message' => 'Material Request ID is required',
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
        
        // Validate quantity_requested
        $quantity = $data['quantity_requested'] ?? $data['quantity'] ?? null;
        if (!isset($quantity) || !is_numeric($quantity) || $quantity < 1) {
            $errors[] = [
                'field' => 'quantity_requested',
                'message' => 'Quantity must be a positive integer',
                'code' => 'INVALID_QUANTITY'
            ];
        }
        
        // Validate quantity_dispatched if provided
        if (isset($data['quantity_dispatched']) && 
            (!is_numeric($data['quantity_dispatched']) || $data['quantity_dispatched'] < 0)) {
            $errors[] = [
                'field' => 'quantity_dispatched',
                'message' => 'Dispatched quantity must be a non-negative integer',
                'code' => 'INVALID_QUANTITY'
            ];
        }
        
        // Validate quantity_received if provided
        if (isset($data['quantity_received']) && 
            (!is_numeric($data['quantity_received']) || $data['quantity_received'] < 0)) {
            $errors[] = [
                'field' => 'quantity_received',
                'message' => 'Received quantity must be a non-negative integer',
                'code' => 'INVALID_QUANTITY'
            ];
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
}

<?php
/**
 * Stock Model
 * Represents quantity-based stock for non-serializable items
 * 
 * Requirements: 3.2
 * - Track non-serializable products by quantity only
 * - Track quantity per product per warehouse
 */

require_once __DIR__ . '/BaseModel.php';

class Stock extends BaseModel {
    protected $table = 'stock';
    protected $fillable = [
        'product_id', 'warehouse_id', 'quantity',
        'reserved_quantity', 'updated_by'
    ];
    
    /**
     * Find stock by product and warehouse
     */
    public function findByProductAndWarehouse($productId, $warehouseId) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `product_id` = ? AND `warehouse_id` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$productId, $warehouseId], 'ii');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find all stock for a product
     */
    public function findByProduct($productId) {
        return $this->findAll(['product_id' => $productId]);
    }
    
    /**
     * Find all stock in a warehouse
     */
    public function findByWarehouse($warehouseId) {
        return $this->findAll(['warehouse_id' => $warehouseId]);
    }
    
    /**
     * Get stock with product and warehouse details
     */
    public function findWithDetails($id) {
        $sql = "SELECT s.*, p.name as product_name, p.unit_of_measure,
                       w.name as warehouse_name, c.name as company_name
                FROM `{$this->table}` s
                LEFT JOIN products p ON s.product_id = p.id
                LEFT JOIN warehouses w ON s.warehouse_id = w.id
                LEFT JOIN companies c ON w.company_id = c.id
                WHERE s.id = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all stock with product and warehouse details
     */
    public function findAllWithDetails($conditions = [], $orderBy = 'p.name, w.name') {
        $sql = "SELECT s.*, p.name as product_name, p.unit_of_measure,
                       w.name as warehouse_name, c.name as company_name
                FROM `{$this->table}` s
                LEFT JOIN products p ON s.product_id = p.id
                LEFT JOIN warehouses w ON s.warehouse_id = w.id
                LEFT JOIN companies c ON w.company_id = c.id";
        $params = [];
        $types = '';
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "s.`$field` = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $sql .= " ORDER BY $orderBy";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Get available quantity (total - reserved)
     */
    public function getAvailableQuantity($productId, $warehouseId) {
        $stock = $this->findByProductAndWarehouse($productId, $warehouseId);
        if (!$stock) {
            return 0;
        }
        return max(0, $stock['quantity'] - $stock['reserved_quantity']);
    }
    
    /**
     * Get total quantity for product across all warehouses
     */
    public function getTotalQuantity($productId) {
        $sql = "SELECT COALESCE(SUM(quantity), 0) as total FROM `{$this->table}` WHERE product_id = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$productId], 'i');
        return $result[0]['total'] ?? 0;
    }
    
    /**
     * Get total available quantity for product across all warehouses
     */
    public function getTotalAvailableQuantity($productId) {
        $sql = "SELECT COALESCE(SUM(quantity - reserved_quantity), 0) as total 
                FROM `{$this->table}` WHERE product_id = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$productId], 'i');
        return max(0, $result[0]['total'] ?? 0);
    }
    
    /**
     * Add stock quantity
     */
    public function addQuantity($productId, $warehouseId, $quantity, $updatedBy = null) {
        $stock = $this->findByProductAndWarehouse($productId, $warehouseId);
        
        if ($stock) {
            // Update existing stock
            $newQuantity = $stock['quantity'] + $quantity;
            $data = ['quantity' => $newQuantity];
            if ($updatedBy !== null) {
                $data['updated_by'] = $updatedBy;
            }
            return $this->update($stock['id'], $data);
        } else {
            // Create new stock record
            $data = [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
                'reserved_quantity' => 0
            ];
            if ($updatedBy !== null) {
                $data['updated_by'] = $updatedBy;
            }
            return $this->create($data);
        }
    }
    
    /**
     * Subtract stock quantity
     */
    public function subtractQuantity($productId, $warehouseId, $quantity, $updatedBy = null) {
        $stock = $this->findByProductAndWarehouse($productId, $warehouseId);
        
        if (!$stock) {
            throw new Exception("No stock found for product in warehouse");
        }
        
        $newQuantity = $stock['quantity'] - $quantity;
        if ($newQuantity < 0) {
            throw new Exception("Insufficient stock quantity");
        }
        
        $data = ['quantity' => $newQuantity];
        if ($updatedBy !== null) {
            $data['updated_by'] = $updatedBy;
        }
        
        return $this->update($stock['id'], $data);
    }
    
    /**
     * Reserve stock quantity
     */
    public function reserveQuantity($productId, $warehouseId, $quantity, $updatedBy = null) {
        $stock = $this->findByProductAndWarehouse($productId, $warehouseId);
        
        if (!$stock) {
            throw new Exception("No stock found for product in warehouse");
        }
        
        $available = $stock['quantity'] - $stock['reserved_quantity'];
        if ($quantity > $available) {
            throw new Exception("Insufficient available stock for reservation");
        }
        
        $newReserved = $stock['reserved_quantity'] + $quantity;
        $data = ['reserved_quantity' => $newReserved];
        if ($updatedBy !== null) {
            $data['updated_by'] = $updatedBy;
        }
        
        return $this->update($stock['id'], $data);
    }
    
    /**
     * Release reserved stock quantity
     */
    public function releaseReservation($productId, $warehouseId, $quantity, $updatedBy = null) {
        $stock = $this->findByProductAndWarehouse($productId, $warehouseId);
        
        if (!$stock) {
            throw new Exception("No stock found for product in warehouse");
        }
        
        $newReserved = max(0, $stock['reserved_quantity'] - $quantity);
        $data = ['reserved_quantity' => $newReserved];
        if ($updatedBy !== null) {
            $data['updated_by'] = $updatedBy;
        }
        
        return $this->update($stock['id'], $data);
    }
    
    /**
     * Check if sufficient stock is available
     */
    public function hasAvailableStock($productId, $warehouseId, $quantity) {
        return $this->getAvailableQuantity($productId, $warehouseId) >= $quantity;
    }
}

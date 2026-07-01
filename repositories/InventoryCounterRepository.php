<?php
/**
 * Inventory Counter Repository
 * Provides data access for real-time inventory counters at each entity level
 * 
 * Requirements: 7.1, 7.2, 7.3
 * - Display current stock minus pending outgoing dispatches for ADV inventory
 * - Display accepted items minus dispatched items for contractor inventory
 * - Display accepted items minus dispatched items for engineer inventory
 */

require_once __DIR__ . '/BaseRepository.php';

class InventoryCounterRepository extends BaseRepository {
    protected $table = 'inventory_counters';
    protected $companyIdColumn = null; // Counters are entity-based, not company-based
    protected $applyCompanyFilter = false;
    
    // Entity type constants
    const ENTITY_WAREHOUSE = 'warehouse';
    const ENTITY_COMPANY = 'company';
    const ENTITY_USER = 'user';
    
    /**
     * Get all valid entity types
     */
    public static function getEntityTypes() {
        return [
            self::ENTITY_WAREHOUSE,
            self::ENTITY_COMPANY,
            self::ENTITY_USER
        ];
    }
    
    /**
     * Check if entity type is valid
     */
    public static function isValidEntityType($entityType) {
        return in_array($entityType, self::getEntityTypes());
    }
    
    /**
     * Get counter for specific entity and product
     * Returns the counter record or null if not found
     * 
     * @param string $entityType Entity type (warehouse, company, user)
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @return array|null Counter record or null
     */
    public function getCounter($entityType, $entityId, $productId) {
        if (!self::isValidEntityType($entityType)) {
            throw new Exception("Invalid entity type: $entityType");
        }
        
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `entity_type` = ? AND `entity_id` = ? AND `product_id` = ?";
        
        $result = $this->db->getResults($sql, [$entityType, $entityId, $productId], 'sii');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all counters for an entity
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return array List of counter records with product details
     */
    public function getCountersByEntity($entityType, $entityId) {
        if (!self::isValidEntityType($entityType)) {
            throw new Exception("Invalid entity type: $entityType");
        }
        
        $sql = "SELECT ic.*, p.name as product_name, pc.name as category_name, p.is_serializable
                FROM `{$this->table}` ic
                LEFT JOIN products p ON ic.product_id = p.id
                LEFT JOIN product_categories pc ON p.category_id = pc.id
                WHERE ic.entity_type = ? AND ic.entity_id = ?
                ORDER BY p.name";
        
        return $this->db->getResults($sql, [$entityType, $entityId], 'si');
    }
    
    /**
     * Get serial numbers for a product held by an entity
     * 
     * @param string $entityType Entity type (warehouse, company, user)
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @return array List of serial numbers
     */
    public function getSerialNumbersForEntity($entityType, $entityId, $productId) {
        $sql = "SELECT a.id, a.serial_number, a.status, a.working_condition
                FROM assets a
                WHERE a.product_id = ?
                AND a.current_holder_type = ?
                AND a.current_holder_id = ?
                AND a.status NOT IN ('scrapped', 'lost')
                ORDER BY a.serial_number";
        
        return $this->db->getResults($sql, [$productId, $entityType, $entityId], 'isi');
    }
    
    /**
     * Get available quantity (quantity minus pending_out)
     * Requirements: 7.1, 7.2, 7.3
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @return int Available quantity
     */
    public function getAvailableQuantity($entityType, $entityId, $productId) {
        $counter = $this->getCounter($entityType, $entityId, $productId);
        
        if (!$counter) {
            return 0;
        }
        
        // Available = quantity - pending_out
        return max(0, $counter['quantity'] - $counter['pending_out']);
    }
    
    /**
     * Increment counter quantity
     * Creates counter if it doesn't exist
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @param int $quantity Amount to increment
     * @return array Updated counter record
     */
    public function incrementCounter($entityType, $entityId, $productId, $quantity) {
        if (!self::isValidEntityType($entityType)) {
            throw new Exception("Invalid entity type: $entityType");
        }
        
        if ($quantity < 0) {
            throw new Exception("Increment quantity must be non-negative");
        }
        
        $counter = $this->getCounter($entityType, $entityId, $productId);
        
        if ($counter) {
            // Update existing counter
            $sql = "UPDATE `{$this->table}` 
                    SET `quantity` = `quantity` + ?, `last_updated_at` = NOW()
                    WHERE `id` = ?";
            $stmt = $this->db->executeQuery($sql, [$quantity, $counter['id']], 'ii');
            $stmt->close();
            
            return $this->find($counter['id']);
        } else {
            // Create new counter
            return $this->create([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'pending_out' => 0,
                'pending_in' => 0
            ]);
        }
    }
    
    /**
     * Decrement counter quantity
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @param int $quantity Amount to decrement
     * @return array Updated counter record
     * @throws Exception if insufficient quantity
     */
    public function decrementCounter($entityType, $entityId, $productId, $quantity) {
        if (!self::isValidEntityType($entityType)) {
            throw new Exception("Invalid entity type: $entityType");
        }
        
        if ($quantity < 0) {
            throw new Exception("Decrement quantity must be non-negative");
        }
        
        $counter = $this->getCounter($entityType, $entityId, $productId);
        
        if (!$counter) {
            throw new Exception("No inventory counter found for this entity/product combination");
        }
        
        if ($counter['quantity'] < $quantity) {
            throw new Exception("Insufficient quantity. Available: {$counter['quantity']}, Requested: $quantity");
        }
        
        $sql = "UPDATE `{$this->table}` 
                SET `quantity` = `quantity` - ?, `last_updated_at` = NOW()
                WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$quantity, $counter['id']], 'ii');
        $stmt->close();
        
        return $this->find($counter['id']);
    }
    
    /**
     * Increment pending_out (when dispatch is created)
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @param int $quantity Amount to add to pending_out
     * @return array Updated counter record
     */
    public function incrementPendingOut($entityType, $entityId, $productId, $quantity) {
        $counter = $this->getCounter($entityType, $entityId, $productId);
        
        if (!$counter) {
            throw new Exception("No inventory counter found for this entity/product combination");
        }
        
        $sql = "UPDATE `{$this->table}` 
                SET `pending_out` = `pending_out` + ?, `last_updated_at` = NOW()
                WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$quantity, $counter['id']], 'ii');
        $stmt->close();
        
        return $this->find($counter['id']);
    }
    
    /**
     * Decrement pending_out (when dispatch is accepted/rejected)
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @param int $quantity Amount to remove from pending_out
     * @return array Updated counter record
     */
    public function decrementPendingOut($entityType, $entityId, $productId, $quantity) {
        $counter = $this->getCounter($entityType, $entityId, $productId);
        
        if (!$counter) {
            throw new Exception("No inventory counter found for this entity/product combination");
        }
        
        $sql = "UPDATE `{$this->table}` 
                SET `pending_out` = GREATEST(0, `pending_out` - ?), `last_updated_at` = NOW()
                WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$quantity, $counter['id']], 'ii');
        $stmt->close();
        
        return $this->find($counter['id']);
    }
    
    /**
     * Increment pending_in (when dispatch is created for recipient)
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @param int $quantity Amount to add to pending_in
     * @return array Updated counter record
     */
    public function incrementPendingIn($entityType, $entityId, $productId, $quantity) {
        $counter = $this->getCounter($entityType, $entityId, $productId);
        
        if ($counter) {
            $sql = "UPDATE `{$this->table}` 
                    SET `pending_in` = `pending_in` + ?, `last_updated_at` = NOW()
                    WHERE `id` = ?";
            $stmt = $this->db->executeQuery($sql, [$quantity, $counter['id']], 'ii');
            $stmt->close();
            
            return $this->find($counter['id']);
        } else {
            // Create new counter with pending_in
            return $this->create([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'product_id' => $productId,
                'quantity' => 0,
                'pending_out' => 0,
                'pending_in' => $quantity
            ]);
        }
    }
    
    /**
     * Decrement pending_in (when dispatch is accepted/rejected)
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @param int $quantity Amount to remove from pending_in
     * @return array Updated counter record
     */
    public function decrementPendingIn($entityType, $entityId, $productId, $quantity) {
        $counter = $this->getCounter($entityType, $entityId, $productId);
        
        if (!$counter) {
            throw new Exception("No inventory counter found for this entity/product combination");
        }
        
        $sql = "UPDATE `{$this->table}` 
                SET `pending_in` = GREATEST(0, `pending_in` - ?), `last_updated_at` = NOW()
                WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$quantity, $counter['id']], 'ii');
        $stmt->close();
        
        return $this->find($counter['id']);
    }
    
    /**
     * Get or create counter
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @return array Counter record
     */
    public function getOrCreateCounter($entityType, $entityId, $productId) {
        $counter = $this->getCounter($entityType, $entityId, $productId);
        
        if (!$counter) {
            $counter = $this->create([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'product_id' => $productId,
                'quantity' => 0,
                'pending_out' => 0,
                'pending_in' => 0
            ]);
        }
        
        return $counter;
    }
    
    /**
     * Set counter quantity directly (for recalculation)
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @param int $quantity New quantity
     * @return array Updated counter record
     */
    public function setQuantity($entityType, $entityId, $productId, $quantity) {
        $counter = $this->getOrCreateCounter($entityType, $entityId, $productId);
        
        $sql = "UPDATE `{$this->table}` 
                SET `quantity` = ?, `last_updated_at` = NOW()
                WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$quantity, $counter['id']], 'ii');
        $stmt->close();
        
        return $this->find($counter['id']);
    }
    
    /**
     * Get counters by product across all entities
     * 
     * @param int $productId Product ID
     * @return array List of counter records
     */
    public function getCountersByProduct($productId) {
        $sql = "SELECT ic.*, 
                       CASE 
                           WHEN ic.entity_type = 'warehouse' THEN w.name
                           WHEN ic.entity_type = 'company' THEN c.name
                           WHEN ic.entity_type = 'user' THEN CONCAT(u.first_name, ' ', u.last_name)
                       END as entity_name
                FROM `{$this->table}` ic
                LEFT JOIN warehouses w ON ic.entity_type = 'warehouse' AND ic.entity_id = w.id
                LEFT JOIN companies c ON ic.entity_type = 'company' AND ic.entity_id = c.id
                LEFT JOIN users u ON ic.entity_type = 'user' AND ic.entity_id = u.id
                WHERE ic.product_id = ?
                ORDER BY ic.entity_type, entity_name";
        
        return $this->db->getResults($sql, [$productId], 'i');
    }
    
    /**
     * Get total quantity across all entities for a product
     * 
     * @param int $productId Product ID
     * @return int Total quantity
     */
    public function getTotalQuantityByProduct($productId) {
        $sql = "SELECT COALESCE(SUM(quantity), 0) as total 
                FROM `{$this->table}` 
                WHERE product_id = ?";
        
        $result = $this->db->getResults($sql, [$productId], 'i');
        return (int)($result[0]['total'] ?? 0);
    }
    
    /**
     * Delete counter (for cleanup)
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @return bool Success
     */
    public function deleteCounter($entityType, $entityId, $productId) {
        $sql = "DELETE FROM `{$this->table}` 
                WHERE `entity_type` = ? AND `entity_id` = ? AND `product_id` = ?";
        
        $stmt = $this->db->executeQuery($sql, [$entityType, $entityId, $productId], 'sii');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Delete all counters for an entity
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return int Number of deleted records
     */
    public function deleteCountersByEntity($entityType, $entityId) {
        $sql = "DELETE FROM `{$this->table}` 
                WHERE `entity_type` = ? AND `entity_id` = ?";
        
        $stmt = $this->db->executeQuery($sql, [$entityType, $entityId], 'si');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
    }
}

<?php
/**
 * Inventory Counter Service
 * Handles real-time inventory counter operations for entities (warehouses, companies, users)
 * 
 * Requirements: 7.1, 7.2, 7.3
 * - Display current stock minus pending outgoing dispatches for ADV inventory
 * - Display accepted items minus dispatched items for contractor inventory
 * - Display accepted items minus dispatched items for engineer inventory
 * 
 * Requirements: 3.1, 3.2, 7.5
 * - Increment recipient's inventory counter when materials are accepted
 * 
 * Requirements: 1.4, 5.1, 7.4
 * - Deduct items from sender's inventory counter immediately on dispatch
 * - Validate sufficient inventory before dispatch
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/InventoryCounterRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/DispatchRepository.php';
require_once __DIR__ . '/../repositories/PendingReceiveRepository.php';
require_once __DIR__ . '/../repositories/DispatchChainRepository.php';
require_once __DIR__ . '/../repositories/InventoryAuditLogRepository.php';

class InventoryCounterService {
    private $db;
    private $conn;
    private $inventoryCounterRepository;
    private $productRepository;
    private $dispatchRepository;
    private $pendingReceiveRepository;
    private $dispatchChainRepository;
    private $auditLogRepository;
    
    /**
     * Constructor - inject dependencies
     * Requirements: 7.1, 7.2, 7.3
     */
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->conn = $this->db->getConnection();
        $this->inventoryCounterRepository = new InventoryCounterRepository();
        $this->productRepository = new ProductRepository();
        $this->dispatchRepository = new DispatchRepository();
        $this->dispatchRepository->disableCompanyFilter();
        $this->pendingReceiveRepository = new PendingReceiveRepository();
        $this->dispatchChainRepository = new DispatchChainRepository();
        $this->auditLogRepository = new InventoryAuditLogRepository();
    }

    
    /**
     * Get current quantity for entity/product combination
     * Returns 0 if counter doesn't exist
     * 
     * Requirements: 7.1, 7.2, 7.3
     * 
     * @param string $entityType Entity type (warehouse, company, user)
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @return int Current quantity
     */
    public function getCounter(string $entityType, int $entityId, int $productId): int {
        // Validate entity type
        if (!InventoryCounterRepository::isValidEntityType($entityType)) {
            return 0;
        }
        
        $counter = $this->inventoryCounterRepository->getCounter($entityType, $entityId, $productId);
        
        // Return 0 if counter doesn't exist
        if (!$counter) {
            return 0;
        }
        
        return (int)$counter['quantity'];
    }

    
    /**
     * Get all product counters for an entity
     * Includes product details in response
     * 
     * Requirements: 8.1, 8.2, 8.3
     * - Display total stock, pending dispatches for ADV dashboard
     * - Display received inventory, pending receives for contractor dashboard
     * - Display assigned inventory, pending receives for engineer dashboard
     * 
     * @param string $entityType Entity type (warehouse, company, user)
     * @param int $entityId Entity ID
     * @return array List of counters with product details
     */
    public function getAllCounters(string $entityType, int $entityId): array {
        // Validate entity type
        if (!InventoryCounterRepository::isValidEntityType($entityType)) {
            return [
                'success' => false,
                'message' => "Invalid entity type: $entityType",
                'code' => 'INVALID_ENTITY_TYPE',
                'data' => []
            ];
        }
        
        try {
            $counters = $this->inventoryCounterRepository->getCountersByEntity($entityType, $entityId);
            
            // Enrich with available quantity calculation and serial numbers for serializable products
            foreach ($counters as &$counter) {
                $counter['available_quantity'] = max(0, $counter['quantity'] - $counter['pending_out']);
                
                // Add serial numbers for serializable products
                if (!empty($counter['is_serializable']) && $counter['is_serializable']) {
                    $counter['serial_numbers'] = $this->inventoryCounterRepository->getSerialNumbersForEntity(
                        $entityType, 
                        $entityId, 
                        $counter['product_id']
                    );
                } else {
                    $counter['serial_numbers'] = [];
                }
            }
            
            return [
                'success' => true,
                'message' => 'Counters retrieved successfully',
                'data' => $counters
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve counters: ' . $e->getMessage(),
                'code' => 'GET_COUNTERS_ERROR',
                'data' => []
            ];
        }
    }

    
    /**
     * Increment counter quantity
     * Creates counter if it doesn't exist
     * Logs audit entry for counter change
     * 
     * Requirements: 3.1, 3.2, 7.5
     * - Increment contractor's inventory counter when materials are accepted
     * - Increment engineer's inventory counter when materials are accepted
     * - Immediately update recipient's inventory counter when receive is accepted
     * 
     * @param string $entityType Entity type (warehouse, company, user)
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @param int $quantity Amount to increment
     * @param int|null $userId User performing the action (for audit)
     * @param string|null $reason Reason for increment (for audit)
     * @return array Result with success status and updated counter
     */
    public function incrementCounter(string $entityType, int $entityId, int $productId, int $quantity, ?int $userId = null, ?string $reason = null): array {
        // Validate entity type
        if (!InventoryCounterRepository::isValidEntityType($entityType)) {
            return [
                'success' => false,
                'message' => "Invalid entity type: $entityType",
                'code' => 'INVALID_ENTITY_TYPE'
            ];
        }
        
        // Validate quantity
        if ($quantity <= 0) {
            return [
                'success' => false,
                'message' => 'Increment quantity must be greater than zero',
                'code' => 'INVALID_QUANTITY'
            ];
        }
        
        // Validate product exists
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 'PRODUCT_NOT_FOUND'
            ];
        }
        
        try {
            // Get current counter value for audit
            $oldCounter = $this->inventoryCounterRepository->getCounter($entityType, $entityId, $productId);
            $oldQuantity = $oldCounter ? $oldCounter['quantity'] : 0;
            
            // Increment counter (creates if doesn't exist)
            $updatedCounter = $this->inventoryCounterRepository->incrementCounter($entityType, $entityId, $productId, $quantity);
            
            // Log audit entry
            $this->logAuditEntry(
                'counter_increment',
                'inventory_counter',
                $updatedCounter['id'],
                $userId,
                null, null,
                $entityType, $entityId,
                [
                    'product_id' => $productId,
                    'product_name' => $product['name'],
                    'old_quantity' => $oldQuantity,
                    'increment' => $quantity,
                    'new_quantity' => $updatedCounter['quantity'],
                    'reason' => $reason ?? 'manual_increment'
                ]
            );
            
            return [
                'success' => true,
                'message' => "Counter incremented by $quantity",
                'data' => [
                    'counter' => $updatedCounter,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $updatedCounter['quantity']
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to increment counter: ' . $e->getMessage(),
                'code' => 'INCREMENT_ERROR'
            ];
        }
    }

    
    /**
     * Decrement counter quantity
     * Validates sufficient quantity before decrement
     * Returns error if insufficient inventory
     * 
     * Requirements: 1.4, 5.1, 7.4
     * - Deduct items from sender's inventory counter immediately when dispatch is created
     * - Validate contractor has sufficient inventory before dispatch
     * - Immediately update sender's available inventory counter when dispatch is created
     * 
     * @param string $entityType Entity type (warehouse, company, user)
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @param int $quantity Amount to decrement
     * @param int|null $userId User performing the action (for audit)
     * @param string|null $reason Reason for decrement (for audit)
     * @return array Result with success status and updated counter
     */
    public function decrementCounter(string $entityType, int $entityId, int $productId, int $quantity, ?int $userId = null, ?string $reason = null): array {
        // Validate entity type
        if (!InventoryCounterRepository::isValidEntityType($entityType)) {
            return [
                'success' => false,
                'message' => "Invalid entity type: $entityType",
                'code' => 'INVALID_ENTITY_TYPE'
            ];
        }
        
        // Validate quantity
        if ($quantity <= 0) {
            return [
                'success' => false,
                'message' => 'Decrement quantity must be greater than zero',
                'code' => 'INVALID_QUANTITY'
            ];
        }
        
        // Validate product exists
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 'PRODUCT_NOT_FOUND'
            ];
        }
        
        // Get current counter
        $counter = $this->inventoryCounterRepository->getCounter($entityType, $entityId, $productId);
        
        // Check if counter exists
        if (!$counter) {
            return [
                'success' => false,
                'message' => 'No inventory found for this entity/product combination',
                'code' => 'NO_INVENTORY',
                'data' => [
                    'available' => 0,
                    'requested' => $quantity
                ]
            ];
        }
        
        // Validate sufficient quantity
        if ($counter['quantity'] < $quantity) {
            return [
                'success' => false,
                'message' => "Insufficient inventory. Available: {$counter['quantity']}, Requested: $quantity",
                'code' => 'INSUFFICIENT_INVENTORY',
                'data' => [
                    'available' => $counter['quantity'],
                    'requested' => $quantity
                ]
            ];
        }
        
        try {
            $oldQuantity = $counter['quantity'];
            
            // Decrement counter
            $updatedCounter = $this->inventoryCounterRepository->decrementCounter($entityType, $entityId, $productId, $quantity);
            
            // Log audit entry
            $this->logAuditEntry(
                'counter_decrement',
                'inventory_counter',
                $updatedCounter['id'],
                $userId,
                $entityType, $entityId,
                null, null,
                [
                    'product_id' => $productId,
                    'product_name' => $product['name'],
                    'old_quantity' => $oldQuantity,
                    'decrement' => $quantity,
                    'new_quantity' => $updatedCounter['quantity'],
                    'reason' => $reason ?? 'manual_decrement'
                ]
            );
            
            return [
                'success' => true,
                'message' => "Counter decremented by $quantity",
                'data' => [
                    'counter' => $updatedCounter,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $updatedCounter['quantity']
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to decrement counter: ' . $e->getMessage(),
                'code' => 'DECREMENT_ERROR'
            ];
        }
    }

    
    /**
     * Get available quantity (quantity minus pending_out)
     * This represents the actual quantity available for dispatch
     * 
     * Requirements: 5.1, 5.4, 6.1, 6.4
     * - Validate contractor has sufficient inventory before dispatch
     * - Only allow dispatch of items currently in contractor's inventory
     * - Validate engineer has sufficient inventory before dispatch
     * - Only allow dispatch of items currently in engineer's inventory
     * 
     * @param string $entityType Entity type (warehouse, company, user)
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @return int Available quantity (quantity - pending_out)
     */
    public function getAvailableQuantity(string $entityType, int $entityId, int $productId): int {
        // Validate entity type
        if (!InventoryCounterRepository::isValidEntityType($entityType)) {
            return 0;
        }
        
        return $this->inventoryCounterRepository->getAvailableQuantity($entityType, $entityId, $productId);
    }

    
    /**
     * Recalculate counter from dispatch/receive history
     * Used for data integrity verification
     * 
     * Requirements: 7.1, 7.2, 7.3
     * - Display current stock minus pending outgoing dispatches for ADV inventory
     * - Display accepted items minus dispatched items for contractor inventory
     * - Display accepted items minus dispatched items for engineer inventory
     * 
     * @param string $entityType Entity type (warehouse, company, user)
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @param int|null $userId User performing the recalculation (for audit)
     * @return array Result with success status and recalculated counter
     */
    public function recalculateCounter(string $entityType, int $entityId, int $productId, ?int $userId = null): array {
        // Validate entity type
        if (!InventoryCounterRepository::isValidEntityType($entityType)) {
            return [
                'success' => false,
                'message' => "Invalid entity type: $entityType",
                'code' => 'INVALID_ENTITY_TYPE'
            ];
        }
        
        // Validate product exists
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 'PRODUCT_NOT_FOUND'
            ];
        }
        
        try {
            // Get current counter for comparison
            $currentCounter = $this->inventoryCounterRepository->getCounter($entityType, $entityId, $productId);
            $currentQuantity = $currentCounter ? $currentCounter['quantity'] : 0;
            $currentPendingOut = $currentCounter ? $currentCounter['pending_out'] : 0;
            $currentPendingIn = $currentCounter ? $currentCounter['pending_in'] : 0;
            
            // Calculate quantity from dispatch chain history
            // Items received (accepted dispatches TO this entity)
            $receivedQuantity = $this->calculateReceivedQuantity($entityType, $entityId, $productId);
            
            // Items dispatched (accepted dispatches FROM this entity)
            $dispatchedQuantity = $this->calculateDispatchedQuantity($entityType, $entityId, $productId);
            
            // Calculate pending_out (dispatches FROM this entity that are still pending)
            $pendingOut = $this->calculatePendingOut($entityType, $entityId, $productId);
            
            // Calculate pending_in (dispatches TO this entity that are still pending)
            $pendingIn = $this->calculatePendingIn($entityType, $entityId, $productId);
            
            // Calculate new quantity: received - dispatched
            $calculatedQuantity = $receivedQuantity - $dispatchedQuantity;
            
            // Ensure non-negative
            $calculatedQuantity = max(0, $calculatedQuantity);
            
            // Update counter with recalculated values
            $counter = $this->inventoryCounterRepository->getOrCreateCounter($entityType, $entityId, $productId);
            
            // Update the counter with recalculated values
            $sql = "UPDATE inventory_counters 
                    SET quantity = ?, pending_out = ?, pending_in = ?, last_updated_at = NOW() 
                    WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('iiii', $calculatedQuantity, $pendingOut, $pendingIn, $counter['id']);
            $stmt->execute();
            $stmt->close();
            
            // Get updated counter
            $updatedCounter = $this->inventoryCounterRepository->find($counter['id']);
            
            // Log audit entry
            $this->logAuditEntry(
                'counter_recalculated',
                'inventory_counter',
                $counter['id'],
                $userId,
                null, null,
                $entityType, $entityId,
                [
                    'product_id' => $productId,
                    'product_name' => $product['name'],
                    'old_quantity' => $currentQuantity,
                    'old_pending_out' => $currentPendingOut,
                    'old_pending_in' => $currentPendingIn,
                    'new_quantity' => $calculatedQuantity,
                    'new_pending_out' => $pendingOut,
                    'new_pending_in' => $pendingIn,
                    'received_total' => $receivedQuantity,
                    'dispatched_total' => $dispatchedQuantity,
                    'discrepancy' => $currentQuantity - $calculatedQuantity
                ]
            );
            
            return [
                'success' => true,
                'message' => 'Counter recalculated successfully',
                'data' => [
                    'counter' => $updatedCounter,
                    'old_quantity' => $currentQuantity,
                    'new_quantity' => $calculatedQuantity,
                    'received_total' => $receivedQuantity,
                    'dispatched_total' => $dispatchedQuantity,
                    'pending_out' => $pendingOut,
                    'pending_in' => $pendingIn,
                    'discrepancy' => $currentQuantity - $calculatedQuantity
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to recalculate counter: ' . $e->getMessage(),
                'code' => 'RECALCULATE_ERROR'
            ];
        }
    }
    
    /**
     * Calculate total received quantity from dispatch chain
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @return int Total received quantity
     */
    private function calculateReceivedQuantity(string $entityType, int $entityId, int $productId): int {
        $sql = "SELECT COALESCE(SUM(dc.quantity), 0) as total
                FROM dispatch_chain dc
                WHERE dc.to_entity_type = ? 
                AND dc.to_entity_id = ? 
                AND dc.product_id = ?
                AND dc.status = ?";
        
        $result = $this->db->getResults($sql, [
            $entityType, 
            $entityId, 
            $productId, 
            DispatchChainRepository::STATUS_ACCEPTED
        ], 'siis');
        
        return (int)($result[0]['total'] ?? 0);
    }
    
    /**
     * Calculate total dispatched quantity from dispatch chain
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @return int Total dispatched quantity
     */
    private function calculateDispatchedQuantity(string $entityType, int $entityId, int $productId): int {
        $sql = "SELECT COALESCE(SUM(dc.quantity), 0) as total
                FROM dispatch_chain dc
                WHERE dc.from_entity_type = ? 
                AND dc.from_entity_id = ? 
                AND dc.product_id = ?
                AND dc.status IN (?, ?)";
        
        $result = $this->db->getResults($sql, [
            $entityType, 
            $entityId, 
            $productId, 
            DispatchChainRepository::STATUS_DISPATCHED,
            DispatchChainRepository::STATUS_ACCEPTED
        ], 'siiss');
        
        return (int)($result[0]['total'] ?? 0);
    }
    
    /**
     * Calculate pending_out from pending dispatches
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @return int Pending out quantity
     */
    private function calculatePendingOut(string $entityType, int $entityId, int $productId): int {
        $sql = "SELECT COALESCE(SUM(dc.quantity), 0) as total
                FROM dispatch_chain dc
                WHERE dc.from_entity_type = ? 
                AND dc.from_entity_id = ? 
                AND dc.product_id = ?
                AND dc.status = ?";
        
        $result = $this->db->getResults($sql, [
            $entityType, 
            $entityId, 
            $productId, 
            DispatchChainRepository::STATUS_DISPATCHED
        ], 'siis');
        
        return (int)($result[0]['total'] ?? 0);
    }
    
    /**
     * Calculate pending_in from pending receives
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @return int Pending in quantity
     */
    private function calculatePendingIn(string $entityType, int $entityId, int $productId): int {
        $sql = "SELECT COALESCE(SUM(dc.quantity), 0) as total
                FROM dispatch_chain dc
                WHERE dc.to_entity_type = ? 
                AND dc.to_entity_id = ? 
                AND dc.product_id = ?
                AND dc.status = ?";
        
        $result = $this->db->getResults($sql, [
            $entityType, 
            $entityId, 
            $productId, 
            DispatchChainRepository::STATUS_DISPATCHED
        ], 'siis');
        
        return (int)($result[0]['total'] ?? 0);
    }
    
    /**
     * Log audit entry for counter operations
     * 
     * @param string $actionType Action type
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int|null $userId User ID
     * @param string|null $fromLocationType From location type
     * @param int|null $fromLocationId From location ID
     * @param string|null $toLocationType To location type
     * @param int|null $toLocationId To location ID
     * @param array|null $details Additional details
     */
    private function logAuditEntry(
        string $actionType, 
        string $entityType, 
        int $entityId, 
        ?int $userId,
        ?string $fromLocationType, 
        ?int $fromLocationId, 
        ?string $toLocationType, 
        ?int $toLocationId, 
        ?array $details = null
    ): void {
        try {
            $this->auditLogRepository->create([
                'action_type' => $actionType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'user_id' => $userId ?? 0,
                'from_location_type' => $fromLocationType,
                'from_location_id' => $fromLocationId,
                'to_location_type' => $toLocationType,
                'to_location_id' => $toLocationId,
                'new_values' => $details ? json_encode($details) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'
            ]);
        } catch (Exception $e) {
            error_log("Failed to log audit entry: " . $e->getMessage());
        }
    }
    
    /**
     * Validate if entity has sufficient inventory for dispatch
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param array $items Array of items with product_id and quantity
     * @return array Validation result
     */
    public function validateSufficientInventory(string $entityType, int $entityId, array $items): array {
        $insufficientItems = [];
        
        foreach ($items as $item) {
            $productId = $item['product_id'];
            $requestedQuantity = $item['quantity'] ?? 1;
            
            $availableQuantity = $this->getAvailableQuantity($entityType, $entityId, $productId);
            
            if ($availableQuantity < $requestedQuantity) {
                $product = $this->productRepository->find($productId);
                $insufficientItems[] = [
                    'product_id' => $productId,
                    'product_name' => $product ? $product['name'] : 'Unknown',
                    'available' => $availableQuantity,
                    'requested' => $requestedQuantity,
                    'shortage' => $requestedQuantity - $availableQuantity
                ];
            }
        }
        
        if (!empty($insufficientItems)) {
            return [
                'success' => false,
                'message' => 'Insufficient inventory for one or more items',
                'code' => 'INSUFFICIENT_INVENTORY',
                'data' => [
                    'insufficient_items' => $insufficientItems
                ]
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Sufficient inventory available',
            'data' => []
        ];
    }
    
    /**
     * Update pending_out when dispatch is created
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @param int $quantity Quantity to add to pending_out
     * @return array Result
     */
    public function addPendingOut(string $entityType, int $entityId, int $productId, int $quantity): array {
        try {
            $counter = $this->inventoryCounterRepository->incrementPendingOut($entityType, $entityId, $productId, $quantity);
            return [
                'success' => true,
                'message' => 'Pending out updated',
                'data' => ['counter' => $counter]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update pending out: ' . $e->getMessage(),
                'code' => 'UPDATE_PENDING_OUT_ERROR'
            ];
        }
    }
    
    /**
     * Update pending_out when dispatch is accepted/rejected
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @param int $quantity Quantity to remove from pending_out
     * @return array Result
     */
    public function removePendingOut(string $entityType, int $entityId, int $productId, int $quantity): array {
        try {
            $counter = $this->inventoryCounterRepository->decrementPendingOut($entityType, $entityId, $productId, $quantity);
            return [
                'success' => true,
                'message' => 'Pending out updated',
                'data' => ['counter' => $counter]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update pending out: ' . $e->getMessage(),
                'code' => 'UPDATE_PENDING_OUT_ERROR'
            ];
        }
    }
    
    /**
     * Update pending_in when dispatch is created
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @param int $quantity Quantity to add to pending_in
     * @return array Result
     */
    public function addPendingIn(string $entityType, int $entityId, int $productId, int $quantity): array {
        try {
            $counter = $this->inventoryCounterRepository->incrementPendingIn($entityType, $entityId, $productId, $quantity);
            return [
                'success' => true,
                'message' => 'Pending in updated',
                'data' => ['counter' => $counter]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update pending in: ' . $e->getMessage(),
                'code' => 'UPDATE_PENDING_IN_ERROR'
            ];
        }
    }
    
    /**
     * Update pending_in when dispatch is accepted/rejected
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int $productId Product ID
     * @param int $quantity Quantity to remove from pending_in
     * @return array Result
     */
    public function removePendingIn(string $entityType, int $entityId, int $productId, int $quantity): array {
        try {
            $counter = $this->inventoryCounterRepository->decrementPendingIn($entityType, $entityId, $productId, $quantity);
            return [
                'success' => true,
                'message' => 'Pending in updated',
                'data' => ['counter' => $counter]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update pending in: ' . $e->getMessage(),
                'code' => 'UPDATE_PENDING_IN_ERROR'
            ];
        }
    }
}

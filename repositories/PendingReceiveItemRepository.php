<?php
/**
 * Pending Receive Item Repository
 * Provides data access for individual items in pending receives (for partial acceptance tracking)
 * 
 * Requirements: 10.1, 10.2
 * - Allow specifying quantities actually received for non-serializable items when accepting
 * - Update recipient's inventory only for accepted quantities during partial acceptance
 */

require_once __DIR__ . '/BaseRepository.php';

class PendingReceiveItemRepository extends BaseRepository {
    protected $table = 'pending_receive_items';
    protected $companyIdColumn = null;
    protected $applyCompanyFilter = false;
    
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PARTIAL = 'partial';
    
    /**
     * Get all valid statuses
     */
    public static function getStatuses() {
        return [
            self::STATUS_PENDING,
            self::STATUS_ACCEPTED,
            self::STATUS_REJECTED,
            self::STATUS_PARTIAL
        ];
    }
    
    /**
     * Check if status is valid
     */
    public static function isValidStatus($status) {
        return in_array($status, self::getStatuses());
    }
    
    /**
     * Find items by pending receive ID
     * Requirement: 10.1
     * 
     * @param int $pendingReceiveId Pending receive ID
     * @return array List of pending receive items with product details
     */
    public function findByPendingReceive($pendingReceiveId) {
        $sql = "SELECT pri.*, 
                       di.product_id, di.asset_id, di.quantity as dispatch_quantity,
                       p.name as product_name, p.is_serializable,
                       pc.name as category_name,
                       a.serial_number
                FROM `{$this->table}` pri
                LEFT JOIN dispatch_items di ON pri.dispatch_item_id = di.id
                LEFT JOIN products p ON di.product_id = p.id
                LEFT JOIN product_categories pc ON p.category_id = pc.id
                LEFT JOIN assets a ON di.asset_id = a.id
                WHERE pri.pending_receive_id = ?
                ORDER BY p.name";
        
        return $this->db->getResults($sql, [$pendingReceiveId], 'i');
    }
    
    /**
     * Find item by pending receive and dispatch item
     * 
     * @param int $pendingReceiveId Pending receive ID
     * @param int $dispatchItemId Dispatch item ID
     * @return array|null Item record or null
     */
    public function findByPendingReceiveAndDispatchItem($pendingReceiveId, $dispatchItemId) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `pending_receive_id` = ? AND `dispatch_item_id` = ?";
        
        $result = $this->db->getResults($sql, [$pendingReceiveId, $dispatchItemId], 'ii');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Update received quantity for an item
     * Requirement: 10.2
     * 
     * @param int $id Item ID
     * @param int $receivedQuantity Quantity actually received
     * @param string|null $notes Optional notes
     * @return array Updated item record
     */
    public function updateReceivedQuantity($id, $receivedQuantity, $notes = null) {
        $item = $this->find($id);
        
        if (!$item) {
            throw new Exception("Pending receive item not found");
        }
        
        if ($receivedQuantity < 0) {
            throw new Exception("Received quantity cannot be negative");
        }
        
        // Determine status based on received vs expected
        $status = self::STATUS_PENDING;
        if ($receivedQuantity == 0) {
            $status = self::STATUS_REJECTED;
        } elseif ($receivedQuantity >= $item['expected_quantity']) {
            $status = self::STATUS_ACCEPTED;
        } else {
            $status = self::STATUS_PARTIAL;
        }
        
        $updateData = [
            'received_quantity' => $receivedQuantity,
            'status' => $status
        ];
        
        if ($notes !== null) {
            $updateData['notes'] = $notes;
        }
        
        return $this->update($id, $updateData);
    }
    
    /**
     * Accept item fully (received quantity = expected quantity)
     * 
     * @param int $id Item ID
     * @param string|null $notes Optional notes
     * @return array Updated item record
     */
    public function acceptFully($id, $notes = null) {
        $item = $this->find($id);
        
        if (!$item) {
            throw new Exception("Pending receive item not found");
        }
        
        return $this->updateReceivedQuantity($id, $item['expected_quantity'], $notes);
    }
    
    /**
     * Reject item (received quantity = 0)
     * 
     * @param int $id Item ID
     * @param string|null $notes Optional notes explaining rejection
     * @return array Updated item record
     */
    public function rejectItem($id, $notes = null) {
        return $this->updateReceivedQuantity($id, 0, $notes);
    }
    
    /**
     * Bulk update received quantities
     * 
     * @param array $items Array of ['id' => int, 'received_quantity' => int, 'notes' => string|null]
     * @return array Updated items
     */
    public function bulkUpdateReceivedQuantities(array $items) {
        $updatedItems = [];
        
        foreach ($items as $itemData) {
            if (!isset($itemData['id']) || !isset($itemData['received_quantity'])) {
                throw new Exception("Each item must have 'id' and 'received_quantity'");
            }
            
            $updatedItems[] = $this->updateReceivedQuantity(
                $itemData['id'],
                $itemData['received_quantity'],
                $itemData['notes'] ?? null
            );
        }
        
        return $updatedItems;
    }
    
    /**
     * Accept all items for a pending receive
     * 
     * @param int $pendingReceiveId Pending receive ID
     * @return array Updated items
     */
    public function acceptAllForPendingReceive($pendingReceiveId) {
        $items = $this->findByPendingReceive($pendingReceiveId);
        $updatedItems = [];
        
        foreach ($items as $item) {
            $updatedItems[] = $this->acceptFully($item['id']);
        }
        
        return $updatedItems;
    }
    
    /**
     * Reject all items for a pending receive
     * 
     * @param int $pendingReceiveId Pending receive ID
     * @param string|null $notes Optional rejection notes
     * @return array Updated items
     */
    public function rejectAllForPendingReceive($pendingReceiveId, $notes = null) {
        $items = $this->findByPendingReceive($pendingReceiveId);
        $updatedItems = [];
        
        foreach ($items as $item) {
            $updatedItems[] = $this->rejectItem($item['id'], $notes);
        }
        
        return $updatedItems;
    }
    
    /**
     * Get summary of items for a pending receive
     * 
     * @param int $pendingReceiveId Pending receive ID
     * @return array Summary with counts and totals
     */
    public function getSummary($pendingReceiveId) {
        $sql = "SELECT 
                    COUNT(*) as total_items,
                    SUM(expected_quantity) as total_expected,
                    SUM(received_quantity) as total_received,
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                    SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
                FROM `{$this->table}`
                WHERE pending_receive_id = ?";
        
        $result = $this->db->getResults($sql, [$pendingReceiveId], 'i');
        return $result[0] ?? [
            'total_items' => 0,
            'total_expected' => 0,
            'total_received' => 0,
            'accepted_count' => 0,
            'rejected_count' => 0,
            'partial_count' => 0,
            'pending_count' => 0
        ];
    }
    
    /**
     * Check if all items are processed (not pending)
     * 
     * @param int $pendingReceiveId Pending receive ID
     * @return bool True if all items are processed
     */
    public function allItemsProcessed($pendingReceiveId) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE pending_receive_id = ? AND status = ?";
        
        $result = $this->db->getResults($sql, [$pendingReceiveId, self::STATUS_PENDING], 'is');
        return ($result[0]['count'] ?? 0) == 0;
    }
    
    /**
     * Get items with discrepancies (received != expected)
     * 
     * @param int $pendingReceiveId Pending receive ID
     * @return array Items with discrepancies
     */
    public function findWithDiscrepancies($pendingReceiveId) {
        $sql = "SELECT pri.*, 
                       di.product_id, di.asset_id,
                       p.name as product_name,
                       (pri.expected_quantity - pri.received_quantity) as discrepancy_quantity
                FROM `{$this->table}` pri
                LEFT JOIN dispatch_items di ON pri.dispatch_item_id = di.id
                LEFT JOIN products p ON di.product_id = p.id
                WHERE pri.pending_receive_id = ? 
                AND pri.received_quantity != pri.expected_quantity
                AND pri.status != ?
                ORDER BY p.name";
        
        return $this->db->getResults($sql, [$pendingReceiveId, self::STATUS_PENDING], 'is');
    }
    
    /**
     * Create items for a pending receive from dispatch items
     * 
     * @param int $pendingReceiveId Pending receive ID
     * @param array $dispatchItems Array of dispatch items
     * @return array Created pending receive items
     */
    public function createFromDispatchItems($pendingReceiveId, array $dispatchItems) {
        $createdItems = [];
        
        foreach ($dispatchItems as $dispatchItem) {
            $createdItems[] = $this->create([
                'pending_receive_id' => $pendingReceiveId,
                'dispatch_item_id' => $dispatchItem['id'],
                'expected_quantity' => $dispatchItem['quantity'],
                'received_quantity' => 0,
                'status' => self::STATUS_PENDING
            ]);
        }
        
        return $createdItems;
    }
    
    /**
     * Delete all items for a pending receive
     * 
     * @param int $pendingReceiveId Pending receive ID
     * @return int Number of deleted items
     */
    public function deleteByPendingReceive($pendingReceiveId) {
        $sql = "DELETE FROM `{$this->table}` WHERE pending_receive_id = ?";
        
        $stmt = $this->db->executeQuery($sql, [$pendingReceiveId], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
    }
}

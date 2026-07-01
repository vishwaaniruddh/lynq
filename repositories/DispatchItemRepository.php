<?php
/**
 * DispatchItem Repository
 * Provides data access for dispatch line items
 * 
 * Requirements: 5.1, 5.5
 * - Link dispatches to products/assets with quantities
 * - Require selection of specific serial numbers for serializable items
 */

require_once __DIR__ . '/BaseRepository.php';

class DispatchItemRepository extends BaseRepository {
    protected $table = 'dispatch_items';
    protected $companyIdColumn = null;
    protected $applyCompanyFilter = false;
    
    /**
     * Find items by dispatch
     */
    public function findByDispatch($dispatchId) {
        return $this->findAll(['dispatch_id' => $dispatchId]);
    }
    
    /**
     * Find items by product
     */
    public function findByProduct($productId) {
        return $this->findAll(['product_id' => $productId]);
    }
    
    /**
     * Find items by asset
     */
    public function findByAsset($assetId) {
        return $this->findAll(['asset_id' => $assetId]);
    }
    
    /**
     * Get item with full details
     */
    public function findWithDetails($id) {
        $sql = "SELECT di.*, 
                       p.name as product_name, p.is_serializable,
                       a.serial_number, a.status as asset_status,
                       d.dispatch_number, d.status as dispatch_status
                FROM `{$this->table}` di
                LEFT JOIN products p ON di.product_id = p.id
                LEFT JOIN assets a ON di.asset_id = a.id
                LEFT JOIN dispatches d ON di.dispatch_id = d.id
                WHERE di.id = ?";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all items for dispatch with full details
     */
    public function findAllByDispatchWithDetails($dispatchId) {
        $sql = "SELECT di.*, 
                       p.name as product_name, p.is_serializable, p.unit_of_measure,
                       a.serial_number, a.status as asset_status, a.working_condition
                FROM `{$this->table}` di
                LEFT JOIN products p ON di.product_id = p.id
                LEFT JOIN assets a ON di.asset_id = a.id
                WHERE di.dispatch_id = ?
                ORDER BY p.name, a.serial_number";
        
        return $this->db->getResults($sql, [$dispatchId], 'i');
    }
    
    /**
     * Get total quantity for product in dispatch
     */
    public function getTotalQuantityForProduct($dispatchId, $productId) {
        $sql = "SELECT COALESCE(SUM(quantity), 0) as total 
                FROM `{$this->table}` 
                WHERE dispatch_id = ? AND product_id = ?";
        $result = $this->db->getResults($sql, [$dispatchId, $productId], 'ii');
        return $result[0]['total'] ?? 0;
    }
    
    /**
     * Count items in dispatch
     */
    public function countByDispatch($dispatchId) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE dispatch_id = ?";
        $result = $this->db->getResults($sql, [$dispatchId], 'i');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Delete all items for dispatch
     */
    public function deleteByDispatch($dispatchId) {
        $sql = "DELETE FROM `{$this->table}` WHERE dispatch_id = ?";
        $stmt = $this->db->executeQuery($sql, [$dispatchId], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }
    
    /**
     * Create multiple items for dispatch
     */
    public function createBatch($dispatchId, $items) {
        $createdItems = [];
        foreach ($items as $item) {
            $item['dispatch_id'] = $dispatchId;
            $createdItems[] = $this->create($item);
        }
        return $createdItems;
    }
}

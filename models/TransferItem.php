<?php
/**
 * TransferItem Model
 * Represents a line item in an inter-warehouse transfer
 * 
 * Requirements: 5.4
 * - Link transfers to products/assets with quantities
 */

require_once __DIR__ . '/BaseModel.php';

class TransferItem extends BaseModel {
    protected $table = 'transfer_items';
    protected $fillable = [
        'transfer_id', 'product_id', 'asset_id', 'quantity'
    ];
    
    /**
     * Find items by transfer
     */
    public function findByTransfer($transferId) {
        return $this->findAll(['transfer_id' => $transferId]);
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
        $sql = "SELECT ti.*, 
                       p.name as product_name, p.is_serializable,
                       a.serial_number, a.status as asset_status,
                       t.transfer_number, t.status as transfer_status
                FROM `{$this->table}` ti
                LEFT JOIN products p ON ti.product_id = p.id
                LEFT JOIN assets a ON ti.asset_id = a.id
                LEFT JOIN transfers t ON ti.transfer_id = t.id
                WHERE ti.id = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all items for transfer with full details
     */
    public function findAllByTransferWithDetails($transferId) {
        $sql = "SELECT ti.*, 
                       p.name as product_name, p.is_serializable, p.unit_of_measure,
                       a.serial_number, a.status as asset_status, a.working_condition
                FROM `{$this->table}` ti
                LEFT JOIN products p ON ti.product_id = p.id
                LEFT JOIN assets a ON ti.asset_id = a.id
                WHERE ti.transfer_id = ?
                ORDER BY p.name, a.serial_number";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$transferId], 'i');
    }
    
    /**
     * Get total quantity for product in transfer
     */
    public function getTotalQuantityForProduct($transferId, $productId) {
        $sql = "SELECT COALESCE(SUM(quantity), 0) as total 
                FROM `{$this->table}` 
                WHERE transfer_id = ? AND product_id = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$transferId, $productId], 'ii');
        return $result[0]['total'] ?? 0;
    }
    
    /**
     * Count items in transfer
     */
    public function countByTransfer($transferId) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE transfer_id = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$transferId], 'i');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Delete all items for transfer
     */
    public function deleteByTransfer($transferId) {
        $sql = "DELETE FROM `{$this->table}` WHERE transfer_id = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$transferId], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }
}

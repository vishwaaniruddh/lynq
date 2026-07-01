<?php
/**
 * Repair Service
 * Manages repair workflows for repairable assets
 * 
 * Requirements: 7.1, 7.2, 7.3, 7.4
 * - 7.1: Change status to "Under Repair" for repairable items marked as not working
 * - 7.2: Record repair vendor, estimated cost, send date, and expected return date
 * - 7.3: Update status to "In Stock" and record actual repair cost and completion date
 * - 7.4: Change status to "Scrapped" for non-repairable items marked as not working
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/RepairRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/InventoryAuditLogRepository.php';
require_once __DIR__ . '/AssetStatusService.php';

class RepairService {
    private $db;
    private $conn;
    private $repairRepository;
    private $assetRepository;
    private $productRepository;
    private $warehouseRepository;
    private $auditLogRepository;
    private $assetStatusService;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->conn = $this->db->getConnection();
        $this->repairRepository = new RepairRepository();
        $this->assetRepository = new AssetRepository();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->auditLogRepository = new InventoryAuditLogRepository();
        $this->assetStatusService = new AssetStatusService();
    }
    
    /**
     * Initiate repair for a repairable item
     * Requirement 7.1: Change status to "Under Repair" for repairable items
     * Requirement 7.2: Record repair vendor, estimated cost, send date, expected return date
     * 
     * @param int $assetId Asset ID
     * @param array $repairData Repair details (repair_vendor, estimated_cost, expected_return_date, etc.)
     * @param int|null $userId User performing the action
     * @return array Result with success status
     */
    public function initiateRepair(int $assetId, array $repairData, ?int $userId = null): array {
        // Get asset
        $asset = $this->assetRepository->find($assetId);
        if (!$asset) {
            return [
                'success' => false,
                'message' => 'Asset not found',
                'code' => 'ASSET_NOT_FOUND'
            ];
        }
        
        // Get product to check if repairable
        $product = $this->productRepository->find($asset['product_id']);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 'PRODUCT_NOT_FOUND'
            ];
        }
        
        // Check if product is repairable
        // Requirement 7.1: Only repairable items can go to Under Repair
        if (!$product['is_repairable']) {
            return [
                'success' => false,
                'message' => 'This product is not repairable. Use scrapAsset() instead.',
                'code' => 'NOT_REPAIRABLE'
            ];
        }
        
        // Check if asset is locked (lost or scrapped)
        if ($this->assetStatusService->isAssetLocked($asset)) {
            return [
                'success' => false,
                'message' => "Asset is locked in status '{$asset['status']}' and cannot be repaired",
                'code' => 'ASSET_LOCKED'
            ];
        }
        
        // Check if asset already has an active repair
        if ($this->repairRepository->hasActiveRepair($assetId)) {
            return [
                'success' => false,
                'message' => 'Asset already has an active repair in progress',
                'code' => 'REPAIR_IN_PROGRESS'
            ];
        }
        
        // Validate required repair data
        if (empty($repairData['repair_vendor'])) {
            return [
                'success' => false,
                'message' => 'Repair vendor is required',
                'code' => 'MISSING_VENDOR'
            ];
        }
        
        try {
            $this->conn->begin_transaction();
            
            // Create repair record
            // Requirement 7.2: Record repair vendor, estimated cost, send date, expected return date
            $repair = $this->repairRepository->create([
                'asset_id' => $assetId,
                'repair_vendor' => $repairData['repair_vendor'],
                'estimated_cost' => $repairData['estimated_cost'] ?? null,
                'send_date' => $repairData['send_date'] ?? date('Y-m-d'),
                'expected_return_date' => $repairData['expected_return_date'] ?? null,
                'status' => RepairRepository::STATUS_PENDING,
                'diagnosis' => $repairData['diagnosis'] ?? null,
                'repair_notes' => $repairData['notes'] ?? null,
                'created_by' => $userId
            ]);
            
            // Update asset status to Under Repair
            // Requirement 7.1: Change status to "Under Repair"
            $this->assetRepository->update($assetId, [
                'status' => AssetRepository::STATUS_UNDER_REPAIR,
                'working_condition' => AssetRepository::CONDITION_NOT_WORKING,
                'updated_by' => $userId
            ]);
            
            // Log audit entry
            $this->logAuditEntry(
                'repair_initiated',
                'repair',
                $repair['id'],
                $userId,
                null,
                null,
                null,
                null,
                [
                    'asset_id' => $assetId,
                    'serial_number' => $asset['serial_number'],
                    'repair_vendor' => $repairData['repair_vendor'],
                    'estimated_cost' => $repairData['estimated_cost'] ?? null
                ]
            );
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Repair initiated successfully',
                'data' => [
                    'repair' => $repair,
                    'asset_status' => AssetRepository::STATUS_UNDER_REPAIR
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => 'Failed to initiate repair: ' . $e->getMessage(),
                'code' => 'INITIATE_REPAIR_ERROR'
            ];
        }
    }
    
    /**
     * Complete repair and return item to stock
     * Requirement 7.3: Update status to "In Stock" and record actual repair cost and completion date
     * 
     * @param int $repairId Repair ID
     * @param array $completionData Completion details (actual_cost, resolution, etc.)
     * @param int|null $userId User performing the action
     * @return array Result with success status
     */
    public function completeRepair(int $repairId, array $completionData, ?int $userId = null): array {
        // Get repair
        $repair = $this->repairRepository->find($repairId);
        if (!$repair) {
            return [
                'success' => false,
                'message' => 'Repair not found',
                'code' => 'REPAIR_NOT_FOUND'
            ];
        }
        
        // Check repair status
        if ($repair['status'] === RepairRepository::STATUS_COMPLETED) {
            return [
                'success' => false,
                'message' => 'Repair is already completed',
                'code' => 'ALREADY_COMPLETED'
            ];
        }
        
        if ($repair['status'] === RepairRepository::STATUS_CANCELLED) {
            return [
                'success' => false,
                'message' => 'Cannot complete a cancelled repair',
                'code' => 'REPAIR_CANCELLED'
            ];
        }
        
        // Get asset
        $asset = $this->assetRepository->find($repair['asset_id']);
        if (!$asset) {
            return [
                'success' => false,
                'message' => 'Associated asset not found',
                'code' => 'ASSET_NOT_FOUND'
            ];
        }
        
        try {
            $this->conn->begin_transaction();
            
            // Update repair record
            // Requirement 7.3: Record actual repair cost and completion date
            $this->repairRepository->update($repairId, [
                'status' => RepairRepository::STATUS_COMPLETED,
                'actual_cost' => $completionData['actual_cost'] ?? null,
                'actual_return_date' => date('Y-m-d'),
                'resolution' => $completionData['resolution'] ?? null,
                'updated_by' => $userId
            ]);
            
            // Determine return warehouse
            $returnWarehouseId = $completionData['return_warehouse_id'] 
                ?? $asset['source_warehouse_id'] 
                ?? $asset['warehouse_id'];
            
            // Update asset status to In Stock
            // Requirement 7.3: Update status to "In Stock"
            $this->assetRepository->update($repair['asset_id'], [
                'status' => AssetRepository::STATUS_IN_STOCK,
                'working_condition' => AssetRepository::CONDITION_WORKING,
                'warehouse_id' => $returnWarehouseId,
                'current_holder_type' => AssetRepository::HOLDER_WAREHOUSE,
                'current_holder_id' => $returnWarehouseId,
                'updated_by' => $userId
            ]);
            
            // Log audit entry
            $this->logAuditEntry(
                'repair_completed',
                'repair',
                $repairId,
                $userId,
                null,
                null,
                'warehouse',
                $returnWarehouseId,
                [
                    'asset_id' => $repair['asset_id'],
                    'serial_number' => $asset['serial_number'],
                    'actual_cost' => $completionData['actual_cost'] ?? null,
                    'resolution' => $completionData['resolution'] ?? null
                ]
            );
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Repair completed successfully. Asset returned to stock.',
                'data' => [
                    'repair_id' => $repairId,
                    'asset_id' => $repair['asset_id'],
                    'asset_status' => AssetRepository::STATUS_IN_STOCK,
                    'return_warehouse_id' => $returnWarehouseId
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => 'Failed to complete repair: ' . $e->getMessage(),
                'code' => 'COMPLETE_REPAIR_ERROR'
            ];
        }
    }

    
    /**
     * Handle non-working item based on repairability
     * Requirement 7.1: Repairable items go to Under Repair
     * Requirement 7.4: Non-repairable items go to Scrapped
     * 
     * @param int $assetId Asset ID
     * @param int|null $userId User performing the action
     * @param array $options Additional options (repair_vendor for repairable, reason for scrap)
     * @return array Result with success status
     */
    public function handleNotWorkingItem(int $assetId, ?int $userId = null, array $options = []): array {
        // Get asset
        $asset = $this->assetRepository->find($assetId);
        if (!$asset) {
            return [
                'success' => false,
                'message' => 'Asset not found',
                'code' => 'ASSET_NOT_FOUND'
            ];
        }
        
        // Get product to check if repairable
        $product = $this->productRepository->find($asset['product_id']);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 'PRODUCT_NOT_FOUND'
            ];
        }
        
        // Check if asset is locked
        if ($this->assetStatusService->isAssetLocked($asset)) {
            return [
                'success' => false,
                'message' => "Asset is locked in status '{$asset['status']}' and cannot be modified",
                'code' => 'ASSET_LOCKED'
            ];
        }
        
        // Update working condition to not working
        $this->assetRepository->updateWorkingCondition($assetId, AssetRepository::CONDITION_NOT_WORKING, $userId);
        
        if ($product['is_repairable']) {
            // Requirement 7.1: Repairable items go to Under Repair
            if (!empty($options['repair_vendor'])) {
                return $this->initiateRepair($assetId, $options, $userId);
            } else {
                // Just update status to Under Repair without creating repair record
                // (repair record can be created later with vendor details)
                $this->assetRepository->updateStatus($assetId, AssetRepository::STATUS_UNDER_REPAIR, $userId);
                
                $this->logAuditEntry(
                    'asset_marked_not_working',
                    'asset',
                    $assetId,
                    $userId,
                    null,
                    null,
                    null,
                    null,
                    [
                        'serial_number' => $asset['serial_number'],
                        'is_repairable' => true,
                        'new_status' => AssetRepository::STATUS_UNDER_REPAIR
                    ]
                );
                
                return [
                    'success' => true,
                    'message' => 'Asset marked as not working and set to Under Repair status',
                    'data' => [
                        'asset_id' => $assetId,
                        'status' => AssetRepository::STATUS_UNDER_REPAIR,
                        'is_repairable' => true
                    ]
                ];
            }
        } else {
            // Requirement 7.4: Non-repairable items go to Scrapped
            return $this->scrapAsset($assetId, $userId, $options['reason'] ?? 'Non-repairable item marked as not working');
        }
    }
    
    /**
     * Scrap an asset (for non-repairable items or items beyond repair)
     * Requirement 7.4: Change status to "Scrapped" and lock from future dispatch
     * 
     * @param int $assetId Asset ID
     * @param int|null $userId User performing the action
     * @param string|null $reason Reason for scrapping
     * @return array Result with success status
     */
    public function scrapAsset(int $assetId, ?int $userId = null, ?string $reason = null): array {
        // Get asset
        $asset = $this->assetRepository->find($assetId);
        if (!$asset) {
            return [
                'success' => false,
                'message' => 'Asset not found',
                'code' => 'ASSET_NOT_FOUND'
            ];
        }
        
        // Check if already scrapped
        if ($asset['status'] === AssetRepository::STATUS_SCRAPPED) {
            return [
                'success' => false,
                'message' => 'Asset is already scrapped',
                'code' => 'ALREADY_SCRAPPED'
            ];
        }
        
        // Check if lost (cannot scrap lost items)
        if ($asset['status'] === AssetRepository::STATUS_LOST) {
            return [
                'success' => false,
                'message' => 'Cannot scrap a lost asset',
                'code' => 'ASSET_LOST'
            ];
        }
        
        try {
            // Cancel any active repairs
            $activeRepair = $this->repairRepository->getActiveRepairForAsset($assetId);
            if ($activeRepair) {
                $this->repairRepository->updateStatus($activeRepair['id'], RepairRepository::STATUS_CANCELLED, $userId);
            }
            
            // Update asset status to Scrapped
            // Requirement 7.4: Change status to "Scrapped" and lock from future dispatch
            $this->assetRepository->update($assetId, [
                'status' => AssetRepository::STATUS_SCRAPPED,
                'working_condition' => AssetRepository::CONDITION_NOT_WORKING,
                'notes' => $reason,
                'updated_by' => $userId
            ]);
            
            // Log audit entry
            $this->logAuditEntry(
                'asset_scrapped',
                'asset',
                $assetId,
                $userId,
                null,
                null,
                null,
                null,
                [
                    'serial_number' => $asset['serial_number'],
                    'old_status' => $asset['status'],
                    'reason' => $reason
                ]
            );
            
            return [
                'success' => true,
                'message' => 'Asset scrapped successfully',
                'data' => [
                    'asset_id' => $assetId,
                    'status' => AssetRepository::STATUS_SCRAPPED
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to scrap asset: ' . $e->getMessage(),
                'code' => 'SCRAP_ERROR'
            ];
        }
    }
    
    /**
     * Cancel a repair
     * 
     * @param int $repairId Repair ID
     * @param int|null $userId User performing the action
     * @param string|null $reason Cancellation reason
     * @return array Result with success status
     */
    public function cancelRepair(int $repairId, ?int $userId = null, ?string $reason = null): array {
        // Get repair
        $repair = $this->repairRepository->find($repairId);
        if (!$repair) {
            return [
                'success' => false,
                'message' => 'Repair not found',
                'code' => 'REPAIR_NOT_FOUND'
            ];
        }
        
        // Check repair status
        if ($repair['status'] === RepairRepository::STATUS_COMPLETED) {
            return [
                'success' => false,
                'message' => 'Cannot cancel a completed repair',
                'code' => 'REPAIR_COMPLETED'
            ];
        }
        
        if ($repair['status'] === RepairRepository::STATUS_CANCELLED) {
            return [
                'success' => false,
                'message' => 'Repair is already cancelled',
                'code' => 'ALREADY_CANCELLED'
            ];
        }
        
        // Get asset
        $asset = $this->assetRepository->find($repair['asset_id']);
        
        try {
            $this->conn->begin_transaction();
            
            // Update repair status
            $this->repairRepository->update($repairId, [
                'status' => RepairRepository::STATUS_CANCELLED,
                'repair_notes' => ($repair['repair_notes'] ?? '') . "\nCancelled: " . ($reason ?? 'No reason provided'),
                'updated_by' => $userId
            ]);
            
            // Return asset to previous state (In Stock with not working condition)
            if ($asset && $asset['status'] === AssetRepository::STATUS_UNDER_REPAIR) {
                $returnWarehouseId = $asset['source_warehouse_id'] ?? $asset['warehouse_id'];
                
                $this->assetRepository->update($repair['asset_id'], [
                    'status' => AssetRepository::STATUS_IN_STOCK,
                    'warehouse_id' => $returnWarehouseId,
                    'current_holder_type' => AssetRepository::HOLDER_WAREHOUSE,
                    'current_holder_id' => $returnWarehouseId,
                    'updated_by' => $userId
                ]);
            }
            
            // Log audit entry
            $this->logAuditEntry(
                'repair_cancelled',
                'repair',
                $repairId,
                $userId,
                null,
                null,
                null,
                null,
                [
                    'asset_id' => $repair['asset_id'],
                    'reason' => $reason
                ]
            );
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Repair cancelled successfully',
                'data' => [
                    'repair_id' => $repairId,
                    'asset_id' => $repair['asset_id']
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => 'Failed to cancel repair: ' . $e->getMessage(),
                'code' => 'CANCEL_REPAIR_ERROR'
            ];
        }
    }
    
    /**
     * Update repair status to in progress
     * 
     * @param int $repairId Repair ID
     * @param int|null $userId User performing the action
     * @return array Result with success status
     */
    public function startRepair(int $repairId, ?int $userId = null): array {
        $repair = $this->repairRepository->find($repairId);
        if (!$repair) {
            return [
                'success' => false,
                'message' => 'Repair not found',
                'code' => 'REPAIR_NOT_FOUND'
            ];
        }
        
        if ($repair['status'] !== RepairRepository::STATUS_PENDING) {
            return [
                'success' => false,
                'message' => "Cannot start repair in status '{$repair['status']}'",
                'code' => 'INVALID_STATUS'
            ];
        }
        
        try {
            $this->repairRepository->updateStatus($repairId, RepairRepository::STATUS_IN_PROGRESS, $userId);
            
            $this->logAuditEntry(
                'repair_started',
                'repair',
                $repairId,
                $userId,
                null,
                null,
                null,
                null,
                ['asset_id' => $repair['asset_id']]
            );
            
            return [
                'success' => true,
                'message' => 'Repair started',
                'data' => ['repair_id' => $repairId, 'status' => RepairRepository::STATUS_IN_PROGRESS]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to start repair: ' . $e->getMessage(),
                'code' => 'START_REPAIR_ERROR'
            ];
        }
    }
    
    /**
     * Get repair details
     */
    public function getRepair(int $repairId): ?array {
        return $this->repairRepository->findWithDetails($repairId);
    }
    
    /**
     * Get repairs for an asset
     */
    public function getAssetRepairs(int $assetId): array {
        return $this->repairRepository->findByAsset($assetId);
    }
    
    /**
     * Get active repairs
     */
    public function getActiveRepairs(): array {
        return $this->repairRepository->findActive();
    }
    
    /**
     * Get overdue repairs
     */
    public function getOverdueRepairs(): array {
        return $this->repairRepository->findOverdue();
    }
    
    /**
     * Get repair history with filters
     */
    public function getRepairHistory(array $filters = []): array {
        return $this->repairRepository->getHistory($filters);
    }
    
    /**
     * Get total repair cost for an asset
     */
    public function getTotalRepairCost(int $assetId): float {
        return (float) $this->repairRepository->getTotalRepairCost($assetId);
    }
    
    /**
     * Check if product is repairable
     */
    public function isProductRepairable(int $productId): bool {
        $product = $this->productRepository->find($productId);
        return $product && $product['is_repairable'];
    }
    
    /**
     * Log audit entry
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
}

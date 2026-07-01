<?php
/**
 * Dispatch Service
 * Handles dispatch operations for inventory items
 * 
 * Requirements: 5.1, 5.3, 5.5
 * - 5.1: Require selection of source warehouse, destination (company/user/warehouse), items, and quantities
 * - 5.3: Update item status from "In Stock" to "Dispatched" and record from/to details
 * - 5.5: Require selection of specific serial numbers for serializable items
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/DispatchRepository.php';
require_once __DIR__ . '/../repositories/DispatchItemRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/InventoryAuditLogRepository.php';
require_once __DIR__ . '/../repositories/PendingReceiveRepository.php';
require_once __DIR__ . '/../repositories/PendingReceiveItemRepository.php';
require_once __DIR__ . '/../repositories/DispatchChainRepository.php';
require_once __DIR__ . '/../repositories/InventoryNotificationRepository.php';
require_once __DIR__ . '/../repositories/InventoryCounterRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/StockService.php';
require_once __DIR__ . '/InventoryCounterService.php';

class DispatchService {
    private $db;
    private $conn;
    private $dispatchRepository;
    private $dispatchItemRepository;
    private $warehouseRepository;
    private $productRepository;
    private $assetRepository;
    private $stockRepository;
    private $auditLogRepository;
    private $stockService;
    private $shippingColumnsExist = null;
    
    // New repositories for multi-directional dispatch
    private $pendingReceiveRepository;
    private $pendingReceiveItemRepository;
    private $dispatchChainRepository;
    private $notificationRepository;
    private $inventoryCounterService;
    private $userRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->conn = $this->db->getConnection();
        $this->dispatchRepository = new DispatchRepository();
        $this->dispatchRepository->disableCompanyFilter();
        $this->dispatchItemRepository = new DispatchItemRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->productRepository = new ProductRepository();
        $this->assetRepository = new AssetRepository();
        $this->stockRepository = new StockRepository();
        $this->auditLogRepository = new InventoryAuditLogRepository();
        $this->stockService = new StockService();
        
        // Initialize new repositories for multi-directional dispatch
        $this->pendingReceiveRepository = new PendingReceiveRepository();
        $this->pendingReceiveItemRepository = new PendingReceiveItemRepository();
        $this->dispatchChainRepository = new DispatchChainRepository();
        $this->notificationRepository = new InventoryNotificationRepository();
        $this->inventoryCounterService = new InventoryCounterService();
        $this->userRepository = new UserRepository();
        $this->userRepository->disableCompanyFilter();
    }
    
    /**
     * Check if shipping columns exist in dispatches table
     * Caches result to avoid repeated queries
     */
    private function checkShippingColumnsExist(): bool {
        if ($this->shippingColumnsExist !== null) {
            return $this->shippingColumnsExist;
        }
        
        try {
            $sql = "SHOW COLUMNS FROM dispatches LIKE 'courier_id'";
            $result = $this->db->getResults($sql, [], '');
            $this->shippingColumnsExist = !empty($result);
        } catch (Exception $e) {
            $this->shippingColumnsExist = false;
        }
        
        return $this->shippingColumnsExist;
    }
    
    /**
     * Create a new dispatch
     * Requirement 5.1: Require selection of source warehouse, destination, items, and quantities
     */
    public function createDispatch(array $dispatchData, array $items, ?int $userId = null): array {
        $validation = $this->validateDispatchData($dispatchData);
        if (!$validation['success']) {
            return $validation;
        }
        
        $fromWarehouse = $this->warehouseRepository->find($dispatchData['from_warehouse_id']);
        if (!$fromWarehouse) {
            return ['success' => false, 'message' => 'Source warehouse not found', 'code' => 'WAREHOUSE_NOT_FOUND'];
        }
        
        if ($fromWarehouse['status'] !== WarehouseRepository::STATUS_ACTIVE) {
            return ['success' => false, 'message' => 'Cannot dispatch from inactive warehouse', 'code' => 'WAREHOUSE_INACTIVE'];
        }
        
        $destinationValidation = $this->validateDestination($dispatchData);
        if (!$destinationValidation['success']) {
            return $destinationValidation;
        }
        
        if (empty($items)) {
            return ['success' => false, 'message' => 'At least one item is required for dispatch', 'code' => 'NO_ITEMS'];
        }
        
        $itemValidation = $this->validateDispatchItems($items, $dispatchData['from_warehouse_id']);
        if (!$itemValidation['success']) {
            return $itemValidation;
        }
        
        try {
            $this->conn->begin_transaction();
            
            $dispatchNumber = DispatchRepository::generateDispatchNumber();
            
            // Base dispatch data (columns that always exist)
            $dispatchCreateData = [
                'dispatch_number' => $dispatchNumber,
                'from_company_id' => $fromWarehouse['company_id'],
                'from_warehouse_id' => $dispatchData['from_warehouse_id'],
                'to_company_id' => $dispatchData['to_company_id'] ?? null,
                'to_user_id' => $dispatchData['to_user_id'] ?? null,
                'to_warehouse_id' => $dispatchData['to_warehouse_id'] ?? null,
                'dispatch_date' => $dispatchData['dispatch_date'] ?? date('Y-m-d'),
                'status' => DispatchRepository::STATUS_PENDING,
                'acknowledgment_status' => DispatchRepository::ACK_PENDING,
                'notes' => $dispatchData['notes'] ?? null,
                'created_by' => $userId
            ];
            
            // Check if new shipping columns exist before adding them
            $hasShippingColumns = $this->checkShippingColumnsExist();
            
            if ($hasShippingColumns) {
                // Add optional shipping fields if they have values
                if (!empty($dispatchData['courier_id'])) {
                    $dispatchCreateData['courier_id'] = $dispatchData['courier_id'];
                }
                if (!empty($dispatchData['pod_number'])) {
                    $dispatchCreateData['pod_number'] = $dispatchData['pod_number'];
                }
                if (!empty($dispatchData['contact_person_name'])) {
                    $dispatchCreateData['contact_person_name'] = $dispatchData['contact_person_name'];
                }
                if (!empty($dispatchData['contact_person_phone'])) {
                    $dispatchCreateData['contact_person_phone'] = $dispatchData['contact_person_phone'];
                }
                if (!empty($dispatchData['lr_copy_path'])) {
                    $dispatchCreateData['lr_copy_path'] = $dispatchData['lr_copy_path'];
                }
                if (!empty($dispatchData['pod_receipt_path'])) {
                    $dispatchCreateData['pod_receipt_path'] = $dispatchData['pod_receipt_path'];
                }
            }
            
            // Add site_id and material_request_id if provided
            if (!empty($dispatchData['site_id'])) {
                $dispatchCreateData['site_id'] = $dispatchData['site_id'];
            }
            if (!empty($dispatchData['material_request_id'])) {
                $dispatchCreateData['material_request_id'] = $dispatchData['material_request_id'];
            }
            
            $dispatch = $this->dispatchRepository->create($dispatchCreateData);
            
            $createdItems = [];
            foreach ($items as $item) {
                $createdItem = $this->createDispatchItem($dispatch['id'], $item);
                if (!$createdItem['success']) {
                    $this->conn->rollback();
                    return $createdItem;
                }
                $createdItems[] = $createdItem['data'];
                
                // Update asset status to dispatched immediately for serializable items
                if (!empty($createdItem['data']['asset_id'])) {
                    $this->assetRepository->update($createdItem['data']['asset_id'], [
                        'status' => AssetRepository::STATUS_DISPATCHED,
                        'warehouse_id' => null,
                        'current_holder_type' => $this->getDestinationType($dispatchData),
                        'current_holder_id' => $this->getDestinationId($dispatchData),
                        'updated_by' => $userId
                    ]);
                    
                    $this->logAuditEntry('asset_dispatched', 'asset', $createdItem['data']['asset_id'], $userId,
                        'warehouse', $dispatchData['from_warehouse_id'],
                        $this->getDestinationType($dispatchData), $this->getDestinationId($dispatchData),
                        ['dispatch_id' => $dispatch['id']]);
                } else {
                    // Deduct stock for non-serializable items
                    $product = $this->productRepository->find($item['product_id']);
                    if (!$product['is_serializable']) {
                        $deductResult = $this->stockService->deductStock(
                            $item['product_id'], 
                            $dispatchData['from_warehouse_id'], 
                            $createdItem['data']['quantity'], 
                            $userId
                        );
                        if (!$deductResult['success']) {
                            $this->conn->rollback();
                            return $deductResult;
                        }
                    }
                }
            }
            
            // Create pending receive for recipient (Requirements: 1.2, 1.3, 2.1, 2.2)
            $recipientType = $this->getDestinationType($dispatchData);
            $recipientId = $this->getDestinationId($dispatchData);
            
            if ($recipientType !== 'unknown' && $recipientId !== null) {
                $pendingReceiveResult = $this->createPendingReceive($dispatch['id'], $recipientType, $recipientId);
                if (!$pendingReceiveResult['success']) {
                    $this->conn->rollback();
                    return $pendingReceiveResult;
                }
                
                // Deduct from sender's inventory counter immediately (Requirement: 1.4)
                foreach ($createdItems as $createdItem) {
                    $decrementResult = $this->inventoryCounterService->decrementCounter(
                        'warehouse',
                        $dispatchData['from_warehouse_id'],
                        $createdItem['product_id'],
                        $createdItem['quantity'],
                        $userId,
                        'dispatch_created'
                    );
                    
                    // Note: We don't fail if counter doesn't exist (backward compatibility)
                    // The counter will be created when items are received
                }
                
                // Add dispatch chain entries (Requirement: 9.1, 9.2)
                foreach ($createdItems as $createdItem) {
                    $this->dispatchChainRepository->addToChain([
                        'asset_id' => $createdItem['asset_id'] ?? null,
                        'product_id' => $createdItem['product_id'],
                        'dispatch_id' => $dispatch['id'],
                        'from_entity_type' => 'warehouse',
                        'from_entity_id' => $dispatchData['from_warehouse_id'],
                        'to_entity_type' => $recipientType,
                        'to_entity_id' => $recipientId,
                        'quantity' => $createdItem['quantity'],
                        'dispatch_date' => $dispatch['dispatch_date'],
                        'status' => DispatchChainRepository::STATUS_DISPATCHED
                    ]);
                }
                
                // Send notification to recipient (Requirement: 11.1)
                $this->sendPendingReceiveNotification(
                    $dispatch,
                    $pendingReceiveResult['data']['pending_receive'],
                    'warehouse',
                    $dispatchData['from_warehouse_id'],
                    $recipientType,
                    $recipientId
                );
            }
            
            $this->logAuditEntry('dispatch_created', 'dispatch', $dispatch['id'], $userId,
                'warehouse', $dispatchData['from_warehouse_id'],
                $this->getDestinationType($dispatchData), $this->getDestinationId($dispatchData),
                ['dispatch_number' => $dispatchNumber, 'item_count' => count($items)]);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Dispatch $dispatchNumber created successfully",
                'data' => ['dispatch' => $dispatch, 'items' => $createdItems]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Failed to create dispatch: ' . $e->getMessage(), 'code' => 'CREATE_DISPATCH_ERROR'];
        }
    }
    
    /**
     * Process dispatch - update status and handle stock/asset updates
     * Requirement 5.3: Update item status from "In Stock" to "Dispatched"
     */
    public function processDispatch(int $dispatchId, string $newStatus, ?int $userId = null): array {
        if (!DispatchRepository::isValidStatus($newStatus)) {
            return ['success' => false, 'message' => "Invalid status: $newStatus", 'code' => 'INVALID_STATUS'];
        }
        
        $dispatch = $this->dispatchRepository->find($dispatchId);
        if (!$dispatch) {
            return ['success' => false, 'message' => 'Dispatch not found', 'code' => 'DISPATCH_NOT_FOUND'];
        }
        
        $transitionValidation = $this->validateStatusTransition($dispatch['status'], $newStatus);
        if (!$transitionValidation['success']) {
            return $transitionValidation;
        }
        
        try {
            $this->conn->begin_transaction();
            
            if ($newStatus === DispatchRepository::STATUS_IN_TRANSIT && 
                $dispatch['status'] === DispatchRepository::STATUS_PENDING) {
                $stockResult = $this->processStockDeduction($dispatchId, $dispatch, $userId);
                if (!$stockResult['success']) {
                    $this->conn->rollback();
                    return $stockResult;
                }
            }
            
            if ($newStatus === DispatchRepository::STATUS_CANCELLED && 
                $dispatch['status'] === DispatchRepository::STATUS_IN_TRANSIT) {
                $restoreResult = $this->restoreStock($dispatchId, $dispatch, $userId);
                if (!$restoreResult['success']) {
                    $this->conn->rollback();
                    return $restoreResult;
                }
            }
            
            $this->dispatchRepository->updateStatus($dispatchId, $newStatus);
            
            $this->logAuditEntry('dispatch_status_changed', 'dispatch', $dispatchId, $userId,
                null, null, null, null,
                ['old_status' => $dispatch['status'], 'new_status' => $newStatus]);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Dispatch status updated to $newStatus",
                'data' => ['dispatch_id' => $dispatchId, 'status' => $newStatus]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Failed to process dispatch: ' . $e->getMessage(), 'code' => 'PROCESS_DISPATCH_ERROR'];
        }
    }
    
    /**
     * Acknowledge receipt of dispatch
     * Requirement 14.1, 14.2: Track acknowledgment status and timestamps
     * @param int $dispatchId Dispatch ID
     * @param int $userId User acknowledging
     * @param array $additionalData Optional data including notes, condition, proof_files
     */
    public function acknowledgeReceipt(int $dispatchId, int $userId, array $additionalData = []): array {
        $dispatch = $this->dispatchRepository->find($dispatchId);
        if (!$dispatch) {
            return ['success' => false, 'message' => 'Dispatch not found', 'code' => 'DISPATCH_NOT_FOUND'];
        }
        
        // Allow acknowledgment for delivered or in_transit status
        if (!in_array($dispatch['status'], [DispatchRepository::STATUS_DELIVERED, DispatchRepository::STATUS_IN_TRANSIT])) {
            return ['success' => false, 'message' => 'Dispatch must be delivered or in transit before acknowledgment', 'code' => 'NOT_DELIVERED'];
        }
        
        if (!empty($dispatch['acknowledged_at'])) {
            return ['success' => false, 'message' => 'Dispatch already acknowledged', 'code' => 'ALREADY_ACKNOWLEDGED'];
        }
        
        try {
            // Prepare acknowledgment data
            $ackData = [
                'acknowledged_by' => $userId,
                'acknowledged_at' => date('Y-m-d H:i:s'),
                'acknowledgment_notes' => $additionalData['notes'] ?? null,
                'acknowledgment_condition' => $additionalData['condition'] ?? 'good',
                'acknowledgment_proof' => !empty($additionalData['proof_files']) ? json_encode($additionalData['proof_files']) : null
            ];
            
            $this->dispatchRepository->acknowledge($dispatchId, $userId, $ackData);
            
            // If dispatch was in_transit, also mark as delivered
            if ($dispatch['status'] === DispatchRepository::STATUS_IN_TRANSIT) {
                $this->dispatchRepository->updateStatus($dispatchId, DispatchRepository::STATUS_DELIVERED);
            }
            
            $this->logAuditEntry('dispatch_acknowledged', 'dispatch', $dispatchId, $userId,
                null, null, null, null,
                [
                    'acknowledged_at' => $ackData['acknowledged_at'],
                    'condition' => $ackData['acknowledgment_condition'],
                    'has_proof' => !empty($additionalData['proof_files']),
                    'proof_count' => count($additionalData['proof_files'] ?? [])
                ]);
            
            return [
                'success' => true,
                'message' => 'Dispatch acknowledged successfully',
                'data' => [
                    'dispatch_id' => $dispatchId, 
                    'acknowledgment_status' => DispatchRepository::ACK_ACKNOWLEDGED,
                    'acknowledged_at' => $ackData['acknowledged_at']
                ]
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to acknowledge dispatch: ' . $e->getMessage(), 'code' => 'ACKNOWLEDGE_ERROR'];
        }
    }
    
    public function getDispatch(int $dispatchId): ?array {
        return $this->dispatchRepository->findWithDetails($dispatchId);
    }
    
    public function getDispatchItems(int $dispatchId): array {
        return $this->dispatchItemRepository->findAllByDispatchWithDetails($dispatchId);
    }
    
    public function getDispatchHistory(array $filters = []): array {
        return $this->dispatchRepository->getHistory($filters);
    }
    
    public function cancelDispatch(int $dispatchId, ?int $userId = null): array {
        $dispatch = $this->dispatchRepository->find($dispatchId);
        if (!$dispatch) {
            return ['success' => false, 'message' => 'Dispatch not found', 'code' => 'DISPATCH_NOT_FOUND'];
        }
        
        if (!$this->dispatchRepository->canCancel($dispatchId)) {
            return ['success' => false, 'message' => 'Dispatch cannot be cancelled in current status', 'code' => 'CANNOT_CANCEL'];
        }
        
        return $this->processDispatch($dispatchId, DispatchRepository::STATUS_CANCELLED, $userId);
    }
    
    private function validateDispatchData(array $data): array {
        if (empty($data['from_warehouse_id'])) {
            return ['success' => false, 'message' => 'Missing required field: from_warehouse_id', 'code' => 'MISSING_FIELD'];
        }
        return ['success' => true];
    }
    
    private function validateDestination(array $data): array {
        $hasDestination = !empty($data['to_company_id']) || !empty($data['to_user_id']) || !empty($data['to_warehouse_id']);
        
        if (!$hasDestination) {
            return ['success' => false, 'message' => 'At least one destination (company, user, or warehouse) is required', 'code' => 'NO_DESTINATION'];
        }
        
        if (!empty($data['to_warehouse_id'])) {
            $toWarehouse = $this->warehouseRepository->find($data['to_warehouse_id']);
            if (!$toWarehouse) {
                return ['success' => false, 'message' => 'Destination warehouse not found', 'code' => 'DESTINATION_WAREHOUSE_NOT_FOUND'];
            }
        }
        
        return ['success' => true];
    }
    
    private function validateDispatchItems(array $items, int $warehouseId): array {
        $productQuantities = [];
        
        foreach ($items as $index => $item) {
            if (empty($item['product_id'])) {
                return ['success' => false, 'message' => "Item $index: product_id is required", 'code' => 'MISSING_PRODUCT_ID'];
            }
            
            $product = $this->productRepository->find($item['product_id']);
            if (!$product) {
                return ['success' => false, 'message' => "Item $index: Product not found", 'code' => 'PRODUCT_NOT_FOUND'];
            }
            
            if ($product['is_serializable']) {
                if (empty($item['asset_id']) && empty($item['serial_number'])) {
                    return ['success' => false, 'message' => "Item $index: Serializable items require asset_id or serial_number", 'code' => 'SERIAL_NUMBER_REQUIRED'];
                }
                
                $asset = !empty($item['asset_id']) 
                    ? $this->assetRepository->find($item['asset_id'])
                    : $this->assetRepository->findBySerialNumber($item['serial_number']);
                
                if (!$asset) {
                    return ['success' => false, 'message' => "Item $index: Asset not found", 'code' => 'ASSET_NOT_FOUND'];
                }
                
                if ($asset['warehouse_id'] != $warehouseId) {
                    return ['success' => false, 'message' => "Item $index: Asset is not in the source warehouse", 'code' => 'ASSET_WRONG_WAREHOUSE'];
                }
                
                if ($asset['status'] !== AssetRepository::STATUS_IN_STOCK) {
                    return ['success' => false, 'message' => "Item $index: Asset is not available (status: {$asset['status']})", 'code' => 'ASSET_NOT_AVAILABLE'];
                }
                
                if ($this->assetRepository->isLocked($asset['id'])) {
                    return ['success' => false, 'message' => "Item $index: Asset is locked and cannot be dispatched", 'code' => 'ASSET_LOCKED'];
                }
            } else {
                $quantity = $item['quantity'] ?? 1;
                if ($quantity <= 0) {
                    return ['success' => false, 'message' => "Item $index: Quantity must be greater than zero", 'code' => 'INVALID_QUANTITY'];
                }
                
                $productId = $item['product_id'];
                $productQuantities[$productId] = ($productQuantities[$productId] ?? 0) + $quantity;
            }
        }
        
        foreach ($productQuantities as $productId => $totalQuantity) {
            $validation = $this->stockService->validateStockAvailability($productId, $warehouseId, $totalQuantity);
            if (!$validation['success']) {
                $product = $this->productRepository->find($productId);
                return [
                    'success' => false,
                    'message' => "Insufficient stock for product '{$product['name']}': " . $validation['message'],
                    'code' => 'INSUFFICIENT_STOCK',
                    'data' => ['product_id' => $productId, 'requested' => $totalQuantity, 'available' => $validation['available']]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    private function createDispatchItem(int $dispatchId, array $item): array {
        $product = $this->productRepository->find($item['product_id']);
        
        $itemData = [
            'dispatch_id' => $dispatchId,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'] ?? 1,
            'asset_id' => null
        ];
        
        if ($product['is_serializable']) {
            if (!empty($item['asset_id'])) {
                $itemData['asset_id'] = $item['asset_id'];
            } elseif (!empty($item['serial_number'])) {
                $asset = $this->assetRepository->findBySerialNumber($item['serial_number']);
                $itemData['asset_id'] = $asset['id'];
            }
            $itemData['quantity'] = 1;
        }
        
        try {
            $createdItem = $this->dispatchItemRepository->create($itemData);
            return ['success' => true, 'data' => $createdItem];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to create dispatch item: ' . $e->getMessage(), 'code' => 'CREATE_ITEM_ERROR'];
        }
    }
    
    private function validateStatusTransition(string $currentStatus, string $newStatus): array {
        $validTransitions = [
            DispatchRepository::STATUS_PENDING => [DispatchRepository::STATUS_IN_TRANSIT, DispatchRepository::STATUS_CANCELLED],
            DispatchRepository::STATUS_IN_TRANSIT => [DispatchRepository::STATUS_DELIVERED, DispatchRepository::STATUS_CANCELLED],
            DispatchRepository::STATUS_DELIVERED => [],
            DispatchRepository::STATUS_CANCELLED => []
        ];
        
        if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus])) {
            return ['success' => false, 'message' => "Invalid status transition from '$currentStatus' to '$newStatus'", 'code' => 'INVALID_TRANSITION'];
        }
        
        return ['success' => true];
    }
    
    private function processStockDeduction(int $dispatchId, array $dispatch, ?int $userId): array {
        // Stock is now deducted at dispatch creation time, so this function
        // only needs to handle any additional processing for status changes.
        // Assets are already marked as dispatched and stock is already deducted.
        // This function is kept for backward compatibility and any edge cases.
        
        $items = $this->dispatchItemRepository->findByDispatch($dispatchId);
        
        foreach ($items as $item) {
            $product = $this->productRepository->find($item['product_id']);
            
            if ($product['is_serializable']) {
                if (!empty($item['asset_id'])) {
                    // Check if asset is already dispatched
                    $asset = $this->assetRepository->find($item['asset_id']);
                    if ($asset && $asset['status'] !== AssetRepository::STATUS_DISPATCHED) {
                        $this->assetRepository->update($item['asset_id'], [
                            'status' => AssetRepository::STATUS_DISPATCHED,
                            'warehouse_id' => null,
                            'current_holder_type' => $this->getDestinationType($dispatch),
                            'current_holder_id' => $this->getDestinationId($dispatch),
                            'updated_by' => $userId
                        ]);
                        
                        $this->logAuditEntry('asset_dispatched', 'asset', $item['asset_id'], $userId,
                            'warehouse', $dispatch['from_warehouse_id'],
                            $this->getDestinationType($dispatch), $this->getDestinationId($dispatch),
                            ['dispatch_id' => $dispatchId]);
                    }
                }
            } else {
                // Stock deduction is now done at creation time
                // Only deduct if not already done (for backward compatibility with old dispatches)
            }
        }
        
        return ['success' => true];
    }
    
    private function restoreStock(int $dispatchId, array $dispatch, ?int $userId): array {
        $items = $this->dispatchItemRepository->findByDispatch($dispatchId);
        
        foreach ($items as $item) {
            $product = $this->productRepository->find($item['product_id']);
            
            if ($product['is_serializable']) {
                if (!empty($item['asset_id'])) {
                    $this->assetRepository->update($item['asset_id'], [
                        'status' => AssetRepository::STATUS_IN_STOCK,
                        'warehouse_id' => $dispatch['from_warehouse_id'],
                        'current_holder_type' => AssetRepository::HOLDER_WAREHOUSE,
                        'current_holder_id' => $dispatch['from_warehouse_id'],
                        'updated_by' => $userId
                    ]);
                    
                    $this->logAuditEntry('asset_restored', 'asset', $item['asset_id'], $userId,
                        null, null, 'warehouse', $dispatch['from_warehouse_id'],
                        ['dispatch_id' => $dispatchId, 'reason' => 'dispatch_cancelled']);
                }
            } else {
                $addResult = $this->stockService->addStock($item['product_id'], $dispatch['from_warehouse_id'], $item['quantity'], $userId);
                if (!$addResult['success']) {
                    return $addResult;
                }
            }
        }
        
        return ['success' => true];
    }
    
    private function getDestinationType(array $data): string {
        if (!empty($data['to_warehouse_id'])) return 'warehouse';
        if (!empty($data['to_user_id'])) return 'user';
        if (!empty($data['to_company_id'])) return 'company';
        return 'unknown';
    }
    
    private function getDestinationId(array $data): ?int {
        if (!empty($data['to_warehouse_id'])) return $data['to_warehouse_id'];
        if (!empty($data['to_user_id'])) return $data['to_user_id'];
        if (!empty($data['to_company_id'])) return $data['to_company_id'];
        return null;
    }
    
    private function logAuditEntry(string $actionType, string $entityType, int $entityId, ?int $userId,
        ?string $fromLocationType, ?int $fromLocationId, ?string $toLocationType, ?int $toLocationId, ?array $details = null): void {
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
     * Create pending receive record when dispatch is created
     * Creates pending receive items for each dispatch item
     * 
     * Requirements: 1.2, 1.3, 2.1, 2.2
     * - Create pending receive record for contractor when ADV dispatches to contractor
     * - Create pending receive record for engineer when ADV dispatches to engineer
     * - Display dispatch in contractor's pending receives list
     * - Display dispatch in engineer's pending receives list
     * 
     * @param int $dispatchId Dispatch ID
     * @param string $recipientType Recipient type (warehouse, company, user)
     * @param int $recipientId Recipient ID
     * @return array Result with pending receive data
     */
    private function createPendingReceive(int $dispatchId, string $recipientType, int $recipientId): array {
        try {
            // Create pending receive record
            $pendingReceive = $this->pendingReceiveRepository->create([
                'dispatch_id' => $dispatchId,
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'status' => PendingReceiveRepository::STATUS_PENDING
            ]);
            
            // Get dispatch items
            $dispatchItems = $this->dispatchItemRepository->findByDispatch($dispatchId);
            
            // Create pending receive items for each dispatch item
            $pendingReceiveItems = $this->pendingReceiveItemRepository->createFromDispatchItems(
                $pendingReceive['id'],
                $dispatchItems
            );
            
            return [
                'success' => true,
                'data' => [
                    'pending_receive' => $pendingReceive,
                    'items' => $pendingReceiveItems
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create pending receive: ' . $e->getMessage(),
                'code' => 'CREATE_PENDING_RECEIVE_ERROR'
            ];
        }
    }
    
    /**
     * Create multi-directional dispatch with pending receive workflow
     * Deducts from sender's inventory counter immediately
     * Creates pending receive for recipient
     * Adds dispatch chain entry
     * Sends notification to recipient
     * 
     * Requirements: 1.4, 1.5, 11.1
     * - Deduct items from sender's inventory counter immediately when dispatch is created
     * - Record dispatch with source, destination, items, quantities, and timestamp
     * - Notify recipient of pending materials when dispatch is created
     * 
     * @param string $senderType Sender type (warehouse, company, user)
     * @param int $senderId Sender ID
     * @param string $recipientType Recipient type (warehouse, company, user)
     * @param int $recipientId Recipient ID
     * @param array $items Items to dispatch
     * @param int|null $userId User creating the dispatch
     * @param array $additionalData Optional additional dispatch data (notes, courier, etc.)
     * @return array Result with dispatch data
     */
    public function createMultiDirectionalDispatch(
        string $senderType,
        int $senderId,
        string $recipientType,
        int $recipientId,
        array $items,
        ?int $userId = null,
        array $additionalData = []
    ): array {
        // Validate sender inventory
        $inventoryValidation = $this->validateSenderInventory($senderType, $senderId, $items);
        if (!$inventoryValidation['success']) {
            return $inventoryValidation;
        }
        
        // Validate items
        if (empty($items)) {
            return ['success' => false, 'message' => 'At least one item is required for dispatch', 'code' => 'NO_ITEMS'];
        }
        
        try {
            $this->conn->begin_transaction();
            
            $dispatchNumber = DispatchRepository::generateDispatchNumber();
            
            // Build dispatch data based on sender/recipient types
            $dispatchCreateData = [
                'dispatch_number' => $dispatchNumber,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'dispatch_date' => $additionalData['dispatch_date'] ?? date('Y-m-d'),
                'status' => DispatchRepository::STATUS_IN_TRANSIT,
                'acknowledgment_status' => DispatchRepository::ACK_PENDING,
                'receive_status' => 'pending',
                'notes' => $additionalData['notes'] ?? null,
                'created_by' => $userId
            ];
            
            // Set from fields based on sender type
            if ($senderType === 'warehouse') {
                $warehouse = $this->warehouseRepository->find($senderId);
                $dispatchCreateData['from_warehouse_id'] = $senderId;
                $dispatchCreateData['from_company_id'] = $warehouse ? $warehouse['company_id'] : null;
            } elseif ($senderType === 'company') {
                $dispatchCreateData['from_company_id'] = $senderId;
            }
            
            // Set to fields based on recipient type
            if ($recipientType === 'warehouse') {
                $dispatchCreateData['to_warehouse_id'] = $recipientId;
            } elseif ($recipientType === 'company') {
                $dispatchCreateData['to_company_id'] = $recipientId;
            } elseif ($recipientType === 'user') {
                $dispatchCreateData['to_user_id'] = $recipientId;
            }
            
            // Add optional shipping fields if they exist
            $hasShippingColumns = $this->checkShippingColumnsExist();
            if ($hasShippingColumns) {
                if (!empty($additionalData['courier_id'])) {
                    $dispatchCreateData['courier_id'] = $additionalData['courier_id'];
                }
                if (!empty($additionalData['pod_number'])) {
                    $dispatchCreateData['pod_number'] = $additionalData['pod_number'];
                }
            }
            
            // Add site_id if provided
            if (!empty($additionalData['site_id'])) {
                $dispatchCreateData['site_id'] = $additionalData['site_id'];
            }
            
            // Create dispatch record
            $dispatch = $this->dispatchRepository->create($dispatchCreateData);
            
            // Create dispatch items
            $createdItems = [];
            foreach ($items as $item) {
                $createdItem = $this->createDispatchItem($dispatch['id'], $item);
                if (!$createdItem['success']) {
                    $this->conn->rollback();
                    return $createdItem;
                }
                $createdItems[] = $createdItem['data'];
            }
            
            // Deduct from sender's inventory counter immediately
            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'] ?? 1;
                
                $decrementResult = $this->inventoryCounterService->decrementCounter(
                    $senderType,
                    $senderId,
                    $productId,
                    $quantity,
                    $userId,
                    'dispatch_created'
                );
                
                if (!$decrementResult['success']) {
                    $this->conn->rollback();
                    return $decrementResult;
                }
            }
            
            // Create pending receive for recipient
            $pendingReceiveResult = $this->createPendingReceive($dispatch['id'], $recipientType, $recipientId);
            if (!$pendingReceiveResult['success']) {
                $this->conn->rollback();
                return $pendingReceiveResult;
            }
            
            // Add dispatch chain entries
            foreach ($createdItems as $item) {
                $this->dispatchChainRepository->addToChain([
                    'asset_id' => $item['asset_id'] ?? null,
                    'product_id' => $item['product_id'],
                    'dispatch_id' => $dispatch['id'],
                    'from_entity_type' => $senderType,
                    'from_entity_id' => $senderId,
                    'to_entity_type' => $recipientType,
                    'to_entity_id' => $recipientId,
                    'quantity' => $item['quantity'],
                    'dispatch_date' => $dispatch['dispatch_date'],
                    'status' => DispatchChainRepository::STATUS_DISPATCHED
                ]);
            }
            
            // Send notification to recipient
            $this->sendPendingReceiveNotification(
                $dispatch,
                $pendingReceiveResult['data']['pending_receive'],
                $senderType,
                $senderId,
                $recipientType,
                $recipientId
            );
            
            // Log audit entry
            $this->logAuditEntry('multi_dispatch_created', 'dispatch', $dispatch['id'], $userId,
                $senderType, $senderId, $recipientType, $recipientId,
                ['dispatch_number' => $dispatchNumber, 'item_count' => count($items)]);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Dispatch $dispatchNumber created successfully",
                'data' => [
                    'dispatch' => $dispatch,
                    'items' => $createdItems,
                    'pending_receive' => $pendingReceiveResult['data']['pending_receive']
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Failed to create dispatch: ' . $e->getMessage(), 'code' => 'CREATE_DISPATCH_ERROR'];
        }
    }
    
    /**
     * Dispatch from contractor to engineers or ADV
     * Validates contractor has sufficient inventory
     * Allows dispatch to engineers under contractor or to ADV
     * Deducts from contractor's inventory counter
     * 
     * Requirements: 5.1, 5.2, 5.3, 5.4
     * - Validate contractor has sufficient inventory before dispatch
     * - Deduct from contractor's inventory and create pending receive for engineer
     * - Deduct from contractor's inventory and create pending receive for ADV
     * - Only allow dispatch of items currently in contractor's inventory
     * 
     * @param int $contractorCompanyId Contractor company ID
     * @param array $dispatchData Dispatch data including destination
     * @param array $items Items to dispatch
     * @param int|null $userId User creating the dispatch
     * @return array Result with dispatch data
     */
    public function dispatchFromContractor(int $contractorCompanyId, array $dispatchData, array $items, ?int $userId = null): array {
        // Validate destination
        if (empty($dispatchData['to_user_id']) && empty($dispatchData['to_company_id']) && empty($dispatchData['to_warehouse_id'])) {
            return ['success' => false, 'message' => 'Destination is required', 'code' => 'NO_DESTINATION'];
        }
        
        // Determine recipient type and ID
        $recipientType = 'user';
        $recipientId = null;
        
        if (!empty($dispatchData['to_user_id'])) {
            $recipientType = 'user';
            $recipientId = $dispatchData['to_user_id'];
            
            // Validate engineer is under this contractor
            $user = $this->userRepository->findWithRelations($recipientId);
            if (!$user) {
                return ['success' => false, 'message' => 'Destination user not found', 'code' => 'USER_NOT_FOUND'];
            }
            
            // Check if user is in contractor's company (engineer under contractor)
            if ($user['company_id'] != $contractorCompanyId) {
                return ['success' => false, 'message' => 'Can only dispatch to engineers in your company', 'code' => 'INVALID_DESTINATION'];
            }
        } elseif (!empty($dispatchData['to_warehouse_id'])) {
            // Dispatching back to ADV warehouse
            $recipientType = 'warehouse';
            $recipientId = $dispatchData['to_warehouse_id'];
            
            $warehouse = $this->warehouseRepository->find($recipientId);
            if (!$warehouse) {
                return ['success' => false, 'message' => 'Destination warehouse not found', 'code' => 'WAREHOUSE_NOT_FOUND'];
            }
        } elseif (!empty($dispatchData['to_company_id'])) {
            $recipientType = 'company';
            $recipientId = $dispatchData['to_company_id'];
        }
        
        // Create multi-directional dispatch from contractor (company)
        return $this->createMultiDirectionalDispatch(
            'company',
            $contractorCompanyId,
            $recipientType,
            $recipientId,
            $items,
            $userId,
            $dispatchData
        );
    }
    
    /**
     * Dispatch from engineer to contractor or ADV
     * Validates engineer has sufficient inventory
     * Allows dispatch to assigned contractor or to ADV
     * Deducts from engineer's inventory counter
     * 
     * Requirements: 6.1, 6.2, 6.3, 6.4
     * - Validate engineer has sufficient inventory before dispatch
     * - Deduct from engineer's inventory and create pending receive for contractor
     * - Deduct from engineer's inventory and create pending receive for ADV
     * - Only allow dispatch of items currently in engineer's inventory
     * 
     * @param int $engineerUserId Engineer user ID
     * @param array $dispatchData Dispatch data including destination
     * @param array $items Items to dispatch
     * @param int|null $userId User creating the dispatch (usually same as engineer)
     * @return array Result with dispatch data
     */
    public function dispatchFromEngineer(int $engineerUserId, array $dispatchData, array $items, ?int $userId = null): array {
        // Validate destination
        if (empty($dispatchData['to_company_id']) && empty($dispatchData['to_warehouse_id'])) {
            return ['success' => false, 'message' => 'Destination is required', 'code' => 'NO_DESTINATION'];
        }
        
        // Get engineer's company (contractor)
        $engineer = $this->userRepository->findWithRelations($engineerUserId);
        if (!$engineer) {
            return ['success' => false, 'message' => 'Engineer not found', 'code' => 'ENGINEER_NOT_FOUND'];
        }
        
        // Determine recipient type and ID
        $recipientType = 'company';
        $recipientId = null;
        
        if (!empty($dispatchData['to_company_id'])) {
            $recipientType = 'company';
            $recipientId = $dispatchData['to_company_id'];
            
            // Validate destination is engineer's contractor company
            if ($recipientId != $engineer['company_id']) {
                // Check if it's an ADV company (return to ADV)
                $company = $this->getCompanyById($recipientId);
                if (!$company || $company['type'] !== 'ADV') {
                    return ['success' => false, 'message' => 'Can only dispatch to your contractor or ADV', 'code' => 'INVALID_DESTINATION'];
                }
            }
        } elseif (!empty($dispatchData['to_warehouse_id'])) {
            // Dispatching back to ADV warehouse
            $recipientType = 'warehouse';
            $recipientId = $dispatchData['to_warehouse_id'];
            
            $warehouse = $this->warehouseRepository->find($recipientId);
            if (!$warehouse) {
                return ['success' => false, 'message' => 'Destination warehouse not found', 'code' => 'WAREHOUSE_NOT_FOUND'];
            }
        }
        
        // Create multi-directional dispatch from engineer (user)
        return $this->createMultiDirectionalDispatch(
            'user',
            $engineerUserId,
            $recipientType,
            $recipientId,
            $items,
            $userId ?? $engineerUserId,
            $dispatchData
        );
    }
    
    /**
     * Get valid destinations for a sender
     * For ADV: return all contractors and engineers
     * For Contractor: return assigned engineers and ADV
     * For Engineer: return assigned contractor and ADV
     * 
     * Requirements: 5.5, 6.5
     * - Show only engineers assigned to contractor and ADV as destinations for contractor
     * - Show assigned contractor and ADV as destinations for engineer
     * 
     * @param string $senderType Sender type (warehouse, company, user)
     * @param int $senderId Sender ID
     * @return array Result with valid destinations
     */
    public function getValidDestinations(string $senderType, int $senderId): array {
        $destinations = [
            'warehouses' => [],
            'companies' => [],
            'users' => []
        ];
        
        try {
            if ($senderType === 'warehouse') {
                // ADV warehouse can dispatch to any contractor or engineer
                $warehouse = $this->warehouseRepository->find($senderId);
                if (!$warehouse) {
                    return ['success' => false, 'message' => 'Warehouse not found', 'code' => 'WAREHOUSE_NOT_FOUND'];
                }
                
                // Get all contractor companies
                $sql = "SELECT id, name, type FROM companies WHERE type = 'CONTRACTOR' AND status = 'ACTIVE' ORDER BY name";
                $destinations['companies'] = $this->db->getResults($sql, [], '');
                
                // Get all users (engineers)
                $sql = "SELECT u.id, u.first_name, u.last_name, u.email, c.name as company_name 
                        FROM users u 
                        LEFT JOIN companies c ON u.company_id = c.id 
                        WHERE u.status = 1 
                        ORDER BY u.first_name, u.last_name";
                $destinations['users'] = $this->db->getResults($sql, [], '');
                
                // Get other warehouses (for inter-warehouse transfers)
                $sql = "SELECT id, name FROM warehouses WHERE id != ? AND status = 'active' ORDER BY name";
                $destinations['warehouses'] = $this->db->getResults($sql, [$senderId], 'i');
                
            } elseif ($senderType === 'company') {
                // Contractor can dispatch to their engineers or back to ADV
                $company = $this->getCompanyById($senderId);
                if (!$company) {
                    return ['success' => false, 'message' => 'Company not found', 'code' => 'COMPANY_NOT_FOUND'];
                }
                
                // Get engineers in this contractor company
                $sql = "SELECT u.id, u.first_name, u.last_name, u.email 
                        FROM users u 
                        WHERE u.company_id = ? AND u.status = 1 
                        ORDER BY u.first_name, u.last_name";
                $destinations['users'] = $this->db->getResults($sql, [$senderId], 'i');
                
                // Get ADV warehouses (for returns)
                $sql = "SELECT w.id, w.name FROM warehouses w 
                        INNER JOIN companies c ON w.company_id = c.id 
                        WHERE c.type = 'ADV' AND w.status = 'active' 
                        ORDER BY w.name";
                $destinations['warehouses'] = $this->db->getResults($sql, [], '');
                
            } elseif ($senderType === 'user') {
                // Engineer can dispatch to their contractor or back to ADV
                $user = $this->userRepository->findWithRelations($senderId);
                if (!$user) {
                    return ['success' => false, 'message' => 'User not found', 'code' => 'USER_NOT_FOUND'];
                }
                
                // Get engineer's contractor company
                if ($user['company_id']) {
                    $sql = "SELECT id, name, type FROM companies WHERE id = ?";
                    $company = $this->db->getResults($sql, [$user['company_id']], 'i');
                    if (!empty($company)) {
                        $destinations['companies'] = $company;
                    }
                }
                
                // Get ADV warehouses (for returns)
                $sql = "SELECT w.id, w.name FROM warehouses w 
                        INNER JOIN companies c ON w.company_id = c.id 
                        WHERE c.type = 'ADV' AND w.status = 'active' 
                        ORDER BY w.name";
                $destinations['warehouses'] = $this->db->getResults($sql, [], '');
            }
            
            return [
                'success' => true,
                'message' => 'Valid destinations retrieved',
                'data' => $destinations
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get valid destinations: ' . $e->getMessage(),
                'code' => 'GET_DESTINATIONS_ERROR'
            ];
        }
    }
    
    /**
     * Validate sender has sufficient inventory for all items
     * Checks sender's inventory counter for all items
     * Returns detailed error for insufficient items
     * 
     * Requirements: 5.1, 5.4, 6.1, 6.4
     * - Validate contractor has sufficient inventory before dispatch
     * - Only allow dispatch of items currently in contractor's inventory
     * - Validate engineer has sufficient inventory before dispatch
     * - Only allow dispatch of items currently in engineer's inventory
     * 
     * @param string $senderType Sender type (warehouse, company, user)
     * @param int $senderId Sender ID
     * @param array $items Items to validate
     * @return array Validation result
     */
    public function validateSenderInventory(string $senderType, int $senderId, array $items): array {
        $insufficientItems = [];
        
        foreach ($items as $index => $item) {
            if (empty($item['product_id'])) {
                return ['success' => false, 'message' => "Item $index: product_id is required", 'code' => 'MISSING_PRODUCT_ID'];
            }
            
            $productId = $item['product_id'];
            $requestedQuantity = $item['quantity'] ?? 1;
            
            // Get available quantity from inventory counter
            $availableQuantity = $this->inventoryCounterService->getAvailableQuantity($senderType, $senderId, $productId);
            
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
     * Send pending receive notification to recipient
     * 
     * @param array $dispatch Dispatch data
     * @param array $pendingReceive Pending receive data
     * @param string $senderType Sender type
     * @param int $senderId Sender ID
     * @param string $recipientType Recipient type
     * @param int $recipientId Recipient ID
     */
    private function sendPendingReceiveNotification(
        array $dispatch,
        array $pendingReceive,
        string $senderType,
        int $senderId,
        string $recipientType,
        int $recipientId
    ): void {
        try {
            // Get sender name
            $senderName = $this->getEntityName($senderType, $senderId);
            
            // Get recipient user ID for notification
            $recipientUserId = null;
            
            if ($recipientType === 'user') {
                $recipientUserId = $recipientId;
            } elseif ($recipientType === 'company') {
                // Get first admin user of the company for notification
                $sql = "SELECT u.id FROM users u 
                        INNER JOIN roles r ON u.role_id = r.id 
                        WHERE u.company_id = ? AND u.status = 1 
                        ORDER BY r.level DESC LIMIT 1";
                $result = $this->db->getResults($sql, [$recipientId], 'i');
                if (!empty($result)) {
                    $recipientUserId = $result[0]['id'];
                }
            } elseif ($recipientType === 'warehouse') {
                // Get warehouse manager or first user of warehouse company
                $warehouse = $this->warehouseRepository->find($recipientId);
                if ($warehouse && $warehouse['company_id']) {
                    $sql = "SELECT u.id FROM users u 
                            INNER JOIN roles r ON u.role_id = r.id 
                            WHERE u.company_id = ? AND u.status = 1 
                            ORDER BY r.level DESC LIMIT 1";
                    $result = $this->db->getResults($sql, [$warehouse['company_id']], 'i');
                    if (!empty($result)) {
                        $recipientUserId = $result[0]['id'];
                    }
                }
            }
            
            if ($recipientUserId) {
                $this->notificationRepository->createPendingReceiveNotification(
                    $recipientUserId,
                    $dispatch['id'],
                    $pendingReceive['id'],
                    $senderName
                );
            }
        } catch (Exception $e) {
            error_log("Failed to send pending receive notification: " . $e->getMessage());
        }
    }
    
    /**
     * Get entity name based on type and ID
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return string Entity name
     */
    private function getEntityName(string $entityType, int $entityId): string {
        try {
            switch ($entityType) {
                case 'user':
                    $user = $this->userRepository->find($entityId);
                    return $user ? trim($user['first_name'] . ' ' . $user['last_name']) : 'Unknown User';
                    
                case 'company':
                    $company = $this->getCompanyById($entityId);
                    return $company ? $company['name'] : 'Unknown Company';
                    
                case 'warehouse':
                    $warehouse = $this->warehouseRepository->find($entityId);
                    return $warehouse ? $warehouse['name'] : 'Unknown Warehouse';
                    
                default:
                    return 'Unknown';
            }
        } catch (Exception $e) {
            return 'Unknown';
        }
    }
    
    /**
     * Get company by ID
     * 
     * @param int $companyId Company ID
     * @return array|null Company data or null
     */
    private function getCompanyById(int $companyId): ?array {
        $sql = "SELECT * FROM companies WHERE id = ?";
        $result = $this->db->getResults($sql, [$companyId], 'i');
        return !empty($result) ? $result[0] : null;
    }
}

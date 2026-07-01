<?php
/**
 * Transfer Service
 * Handles inter-warehouse transfer operations
 * 
 * Requirements: 5.4
 * - Inter-warehouse transfer: decrement source warehouse stock and increment destination warehouse stock
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/TransferRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/InventoryAuditLogRepository.php';
require_once __DIR__ . '/StockService.php';

class TransferService {
    private $db;
    private $conn;
    private $transferRepository;
    private $warehouseRepository;
    private $productRepository;
    private $assetRepository;
    private $stockRepository;
    private $auditLogRepository;
    private $stockService;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->conn = $this->db->getConnection();
        $this->transferRepository = new TransferRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->productRepository = new ProductRepository();
        $this->assetRepository = new AssetRepository();
        $this->stockRepository = new StockRepository();
        $this->auditLogRepository = new InventoryAuditLogRepository();
        $this->stockService = new StockService();
    }
    
    /**
     * Create a new inter-warehouse transfer
     * Requirement 5.4: Inter-warehouse transfer with source/destination validation
     */
    public function createTransfer(array $transferData, array $items, ?int $userId = null): array {
        // Validate required fields
        $validation = $this->validateTransferData($transferData);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Validate source warehouse exists and is active
        $fromWarehouse = $this->warehouseRepository->find($transferData['from_warehouse_id']);
        if (!$fromWarehouse) {
            return ['success' => false, 'message' => 'Source warehouse not found', 'code' => 'SOURCE_WAREHOUSE_NOT_FOUND'];
        }
        
        if ($fromWarehouse['status'] !== WarehouseRepository::STATUS_ACTIVE) {
            return ['success' => false, 'message' => 'Cannot transfer from inactive warehouse', 'code' => 'SOURCE_WAREHOUSE_INACTIVE'];
        }
        
        // Validate destination warehouse exists and is active
        $toWarehouse = $this->warehouseRepository->find($transferData['to_warehouse_id']);
        if (!$toWarehouse) {
            return ['success' => false, 'message' => 'Destination warehouse not found', 'code' => 'DESTINATION_WAREHOUSE_NOT_FOUND'];
        }
        
        if ($toWarehouse['status'] !== WarehouseRepository::STATUS_ACTIVE) {
            return ['success' => false, 'message' => 'Cannot transfer to inactive warehouse', 'code' => 'DESTINATION_WAREHOUSE_INACTIVE'];
        }
        
        // Validate source and destination are different
        if ($transferData['from_warehouse_id'] == $transferData['to_warehouse_id']) {
            return ['success' => false, 'message' => 'Source and destination warehouses must be different', 'code' => 'SAME_WAREHOUSE'];
        }
        
        // Validate items
        if (empty($items)) {
            return ['success' => false, 'message' => 'At least one item is required for transfer', 'code' => 'NO_ITEMS'];
        }
        
        // Validate each item and check stock availability
        $itemValidation = $this->validateTransferItems($items, $transferData['from_warehouse_id']);
        if (!$itemValidation['success']) {
            return $itemValidation;
        }
        
        try {
            $this->conn->begin_transaction();
            
            $transferNumber = TransferRepository::generateTransferNumber();
            
            $transfer = $this->transferRepository->create([
                'transfer_number' => $transferNumber,
                'from_warehouse_id' => $transferData['from_warehouse_id'],
                'to_warehouse_id' => $transferData['to_warehouse_id'],
                'transfer_date' => $transferData['transfer_date'] ?? date('Y-m-d'),
                'status' => TransferRepository::STATUS_PENDING,
                'notes' => $transferData['notes'] ?? null,
                'created_by' => $userId
            ]);
            
            $createdItems = [];
            foreach ($items as $item) {
                $createdItem = $this->createTransferItem($transfer['id'], $item);
                if (!$createdItem['success']) {
                    $this->conn->rollback();
                    return $createdItem;
                }
                $createdItems[] = $createdItem['data'];
            }
            
            $this->logAuditEntry('transfer_created', 'transfer', $transfer['id'], $userId,
                'warehouse', $transferData['from_warehouse_id'], 'warehouse', $transferData['to_warehouse_id'],
                ['transfer_number' => $transferNumber, 'item_count' => count($items)]);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Transfer $transferNumber created successfully",
                'data' => ['transfer' => $transfer, 'items' => $createdItems]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Failed to create transfer: ' . $e->getMessage(), 'code' => 'CREATE_TRANSFER_ERROR'];
        }
    }
    
    /**
     * Process transfer - execute the actual stock movement
     * Requirement 5.4: Decrement source warehouse stock and increment destination warehouse stock
     */
    public function processTransfer(int $transferId, ?int $userId = null): array {
        $transfer = $this->transferRepository->find($transferId);
        if (!$transfer) {
            return ['success' => false, 'message' => 'Transfer not found', 'code' => 'TRANSFER_NOT_FOUND'];
        }
        
        if ($transfer['status'] !== TransferRepository::STATUS_PENDING) {
            return ['success' => false, 'message' => 'Transfer can only be processed from pending status', 'code' => 'INVALID_STATUS'];
        }
        
        try {
            $this->conn->begin_transaction();
            
            $items = $this->transferRepository->getItems($transferId);
            
            foreach ($items as $item) {
                $product = $this->productRepository->find($item['product_id']);
                
                $transferResult = $product['is_serializable']
                    ? $this->transferAsset($item['asset_id'], $transfer['from_warehouse_id'], $transfer['to_warehouse_id'], $userId)
                    : $this->transferStockQuantity($item['product_id'], $transfer['from_warehouse_id'], $transfer['to_warehouse_id'], $item['quantity'], $userId);
                
                if (!$transferResult['success']) {
                    $this->conn->rollback();
                    return $transferResult;
                }
            }
            
            $this->transferRepository->updateStatus($transferId, TransferRepository::STATUS_COMPLETED, $userId);
            
            $this->logAuditEntry('transfer_completed', 'transfer', $transferId, $userId,
                'warehouse', $transfer['from_warehouse_id'], 'warehouse', $transfer['to_warehouse_id'],
                ['item_count' => count($items)]);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Transfer completed successfully',
                'data' => ['transfer_id' => $transferId, 'status' => TransferRepository::STATUS_COMPLETED]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Failed to process transfer: ' . $e->getMessage(), 'code' => 'PROCESS_TRANSFER_ERROR'];
        }
    }
    
    private function transferStockQuantity(int $productId, int $fromWarehouseId, int $toWarehouseId, int $quantity, ?int $userId): array {
        $validation = $this->stockService->validateStockAvailability($productId, $fromWarehouseId, $quantity);
        if (!$validation['success']) {
            return $validation;
        }
        
        try {
            $this->stockRepository->transfer($productId, $fromWarehouseId, $toWarehouseId, $quantity, $userId);
            
            $this->logAuditEntry('stock_transferred', 'stock', 0, $userId,
                'warehouse', $fromWarehouseId, 'warehouse', $toWarehouseId,
                ['product_id' => $productId, 'quantity' => $quantity]);
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to transfer stock: ' . $e->getMessage(), 'code' => 'STOCK_TRANSFER_ERROR'];
        }
    }
    
    private function transferAsset(int $assetId, int $fromWarehouseId, int $toWarehouseId, ?int $userId): array {
        $asset = $this->assetRepository->find($assetId);
        
        if (!$asset) {
            return ['success' => false, 'message' => 'Asset not found', 'code' => 'ASSET_NOT_FOUND'];
        }
        
        if ($asset['warehouse_id'] != $fromWarehouseId) {
            return ['success' => false, 'message' => 'Asset is not in the source warehouse', 'code' => 'ASSET_WRONG_WAREHOUSE'];
        }
        
        if ($asset['status'] !== AssetRepository::STATUS_IN_STOCK) {
            return ['success' => false, 'message' => 'Asset is not available for transfer', 'code' => 'ASSET_NOT_AVAILABLE'];
        }
        
        try {
            $this->assetRepository->update($assetId, [
                'warehouse_id' => $toWarehouseId,
                'current_holder_type' => AssetRepository::HOLDER_WAREHOUSE,
                'current_holder_id' => $toWarehouseId,
                'updated_by' => $userId
            ]);
            
            $this->logAuditEntry('asset_transferred', 'asset', $assetId, $userId,
                'warehouse', $fromWarehouseId, 'warehouse', $toWarehouseId,
                ['serial_number' => $asset['serial_number']]);
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to transfer asset: ' . $e->getMessage(), 'code' => 'ASSET_TRANSFER_ERROR'];
        }
    }
    
    public function cancelTransfer(int $transferId, ?int $userId = null): array {
        $transfer = $this->transferRepository->find($transferId);
        if (!$transfer) {
            return ['success' => false, 'message' => 'Transfer not found', 'code' => 'TRANSFER_NOT_FOUND'];
        }
        
        if (!$this->transferRepository->canCancel($transferId)) {
            return ['success' => false, 'message' => 'Transfer cannot be cancelled in current status', 'code' => 'CANNOT_CANCEL'];
        }
        
        try {
            $this->transferRepository->updateStatus($transferId, TransferRepository::STATUS_CANCELLED, $userId);
            
            $this->logAuditEntry('transfer_cancelled', 'transfer', $transferId, $userId, null, null, null, null,
                ['previous_status' => $transfer['status']]);
            
            return [
                'success' => true,
                'message' => 'Transfer cancelled successfully',
                'data' => ['transfer_id' => $transferId, 'status' => TransferRepository::STATUS_CANCELLED]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to cancel transfer: ' . $e->getMessage(), 'code' => 'CANCEL_TRANSFER_ERROR'];
        }
    }
    
    public function getTransfer(int $transferId): ?array {
        return $this->transferRepository->findWithDetails($transferId);
    }
    
    public function getTransferItems(int $transferId): array {
        return $this->transferRepository->getItems($transferId);
    }
    
    public function getTransferHistory(array $filters = []): array {
        return $this->transferRepository->getHistory($filters);
    }
    
    public function getStockLevels(int $productId, int $fromWarehouseId, int $toWarehouseId): array {
        return [
            'from_warehouse' => $this->stockService->getAvailableStock($productId, $fromWarehouseId),
            'to_warehouse' => $this->stockService->getAvailableStock($productId, $toWarehouseId),
            'total' => $this->stockService->getTotalStock($productId)
        ];
    }
    
    private function validateTransferData(array $data): array {
        $requiredFields = ['from_warehouse_id', 'to_warehouse_id'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Missing required field: $field", 'code' => 'MISSING_FIELD'];
            }
        }
        return ['success' => true];
    }
    
    private function validateTransferItems(array $items, int $warehouseId): array {
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
    
    private function createTransferItem(int $transferId, array $item): array {
        $product = $this->productRepository->find($item['product_id']);
        
        $itemData = [
            'transfer_id' => $transferId,
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
            $sql = "INSERT INTO transfer_items (transfer_id, product_id, asset_id, quantity) VALUES (?, ?, ?, ?)";
            $stmt = $this->db->executeQuery($sql, [
                $itemData['transfer_id'],
                $itemData['product_id'],
                $itemData['asset_id'],
                $itemData['quantity']
            ], 'iiii');
            
            $itemData['id'] = $this->conn->insert_id;
            $stmt->close();
            
            return ['success' => true, 'data' => $itemData];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to create transfer item: ' . $e->getMessage(), 'code' => 'CREATE_ITEM_ERROR'];
        }
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
}

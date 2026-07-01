<?php
/**
 * Stock Service
 * Manages stock operations for both serializable and non-serializable items
 * 
 * Requirements: 3.1, 3.2, 5.2
 * - 3.1: Create individual asset records with unique serial numbers and set status to "In Stock"
 * - 3.2: Increment quantity for non-serializable products in specified warehouse
 * - 5.2: Validate that sufficient stock exists in the selected warehouse
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/InventoryAuditLogRepository.php';

class StockService {
    private $db;
    private $stockRepository;
    private $assetRepository;
    private $productRepository;
    private $warehouseRepository;
    private $auditLogRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->stockRepository = new StockRepository();
        $this->assetRepository = new AssetRepository();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->auditLogRepository = new InventoryAuditLogRepository();
    }
    
    /**
     * Add stock for non-serializable items
     * Requirement 3.2: Increment quantity for non-serializable products
     * 
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID
     * @param int $quantity Quantity to add
     * @param int|null $userId User performing the action
     * @param string|null $notes Optional notes for the stock entry
     * @return array Result with success status and data/errors
     */
    public function addStock(int $productId, int $warehouseId, int $quantity, ?int $userId = null, ?string $notes = null): array {
        // Validate product exists
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 'PRODUCT_NOT_FOUND'
            ];
        }
        
        // Validate product is non-serializable
        if ($product['is_serializable']) {
            return [
                'success' => false,
                'message' => 'Cannot add stock for serializable products. Use addAsset() instead.',
                'code' => 'SERIALIZABLE_PRODUCT'
            ];
        }
        
        // Validate warehouse exists and is active
        $warehouse = $this->warehouseRepository->find($warehouseId);
        if (!$warehouse) {
            return [
                'success' => false,
                'message' => 'Warehouse not found',
                'code' => 'WAREHOUSE_NOT_FOUND'
            ];
        }
        
        // Validate quantity is positive
        if ($quantity <= 0) {
            return [
                'success' => false,
                'message' => 'Quantity must be greater than zero',
                'code' => 'INVALID_QUANTITY'
            ];
        }
        
        try {
            // Add stock quantity
            $result = $this->stockRepository->addQuantity($productId, $warehouseId, $quantity, $userId);
            
            // Log audit entry with notes if provided
            $auditDetails = ['quantity' => $quantity, 'product_id' => $productId];
            if ($notes) {
                $auditDetails['notes'] = $notes;
            }
            
            $this->logAuditEntry(
                'stock_added',
                'stock',
                $result['id'] ?? 0,
                $userId,
                null,
                null,
                'warehouse',
                $warehouseId,
                $auditDetails
            );
            
            return [
                'success' => true,
                'message' => "Added $quantity units to stock",
                'data' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to add stock: ' . $e->getMessage(),
                'code' => 'ADD_STOCK_ERROR'
            ];
        }
    }

    
    /**
     * Add asset for serializable items
     * Requirement 3.1: Create individual asset records with unique serial numbers
     * 
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID
     * @param string $serialNumber Unique serial number
     * @param int|null $userId User performing the action
     * @param array $additionalData Optional additional asset data
     * @return array Result with success status and data/errors
     */
    public function addAsset(int $productId, int $warehouseId, string $serialNumber, ?int $userId = null, array $additionalData = []): array {
        // Validate product exists
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 'PRODUCT_NOT_FOUND'
            ];
        }
        
        // Validate product is serializable
        if (!$product['is_serializable']) {
            return [
                'success' => false,
                'message' => 'Cannot add asset for non-serializable products. Use addStock() instead.',
                'code' => 'NON_SERIALIZABLE_PRODUCT'
            ];
        }
        
        // Validate warehouse exists
        $warehouse = $this->warehouseRepository->find($warehouseId);
        if (!$warehouse) {
            return [
                'success' => false,
                'message' => 'Warehouse not found',
                'code' => 'WAREHOUSE_NOT_FOUND'
            ];
        }
        
        // Validate serial number is not empty
        $serialNumber = trim($serialNumber);
        if (empty($serialNumber)) {
            return [
                'success' => false,
                'message' => 'Serial number is required',
                'code' => 'SERIAL_NUMBER_REQUIRED'
            ];
        }
        
        // Check for duplicate serial number
        if ($this->assetRepository->serialNumberExists($serialNumber)) {
            return [
                'success' => false,
                'message' => "Serial number '$serialNumber' already exists",
                'code' => 'DUPLICATE_SERIAL_NUMBER'
            ];
        }
        
        try {
            // Create asset record
            $assetData = array_merge([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'serial_number' => $serialNumber,
                'status' => AssetRepository::STATUS_IN_STOCK,
                'working_condition' => AssetRepository::CONDITION_WORKING,
                'current_holder_type' => AssetRepository::HOLDER_WAREHOUSE,
                'current_holder_id' => $warehouseId,
                'source_warehouse_id' => $warehouseId,
                'created_by' => $userId,
                'updated_by' => $userId
            ], $additionalData);
            
            $asset = $this->assetRepository->create($assetData);
            
            // Log audit entry
            $this->logAuditEntry(
                'asset_created',
                'asset',
                $asset['id'],
                $userId,
                null,
                null,
                'warehouse',
                $warehouseId,
                ['serial_number' => $serialNumber, 'product_id' => $productId]
            );
            
            return [
                'success' => true,
                'message' => "Asset created with serial number '$serialNumber'",
                'data' => $asset
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create asset: ' . $e->getMessage(),
                'code' => 'CREATE_ASSET_ERROR'
            ];
        }
    }
    
    /**
     * Add multiple assets for serializable items (bulk)
     * 
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID
     * @param array $serialNumbers Array of serial numbers
     * @param int|null $userId User performing the action
     * @return array Result with success/error counts
     */
    public function addAssets(int $productId, int $warehouseId, array $serialNumbers, ?int $userId = null): array {
        $results = [
            'success' => true,
            'total' => count($serialNumbers),
            'successCount' => 0,
            'errorCount' => 0,
            'errors' => [],
            'createdIds' => []
        ];
        
        foreach ($serialNumbers as $serialNumber) {
            $result = $this->addAsset($productId, $warehouseId, $serialNumber, $userId);
            
            if ($result['success']) {
                $results['successCount']++;
                $results['createdIds'][] = $result['data']['id'];
            } else {
                $results['errorCount']++;
                $results['errors'][] = [
                    'serial_number' => $serialNumber,
                    'message' => $result['message']
                ];
            }
        }
        
        $results['success'] = $results['errorCount'] === 0;
        $results['message'] = "Created {$results['successCount']} of {$results['total']} assets";
        
        return $results;
    }

    
    /**
     * Get available stock for a product in a warehouse
     * Requirement 5.2: Validate that sufficient stock exists
     * 
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID
     * @return int Available quantity
     */
    public function getAvailableStock(int $productId, int $warehouseId): int {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return 0;
        }
        
        if ($product['is_serializable']) {
            // Count assets in stock at warehouse
            $assets = $this->assetRepository->findInStockAtWarehouse($warehouseId);
            $count = 0;
            foreach ($assets as $asset) {
                if ($asset['product_id'] == $productId) {
                    $count++;
                }
            }
            return $count;
        } else {
            // Get available quantity from stock table
            return $this->stockRepository->getAvailableQuantity($productId, $warehouseId);
        }
    }
    
    /**
     * Validate stock availability for dispatch
     * Requirement 5.2: Validate that sufficient stock exists in the selected warehouse
     * 
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID
     * @param int $quantity Required quantity
     * @return array Validation result with success status
     */
    public function validateStockAvailability(int $productId, int $warehouseId, int $quantity): array {
        // Validate product exists
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 'PRODUCT_NOT_FOUND',
                'available' => 0,
                'requested' => $quantity
            ];
        }
        
        // Validate warehouse exists
        $warehouse = $this->warehouseRepository->find($warehouseId);
        if (!$warehouse) {
            return [
                'success' => false,
                'message' => 'Warehouse not found',
                'code' => 'WAREHOUSE_NOT_FOUND',
                'available' => 0,
                'requested' => $quantity
            ];
        }
        
        // Check warehouse is active
        if ($warehouse['status'] !== WarehouseRepository::STATUS_ACTIVE) {
            return [
                'success' => false,
                'message' => 'Cannot dispatch from inactive warehouse',
                'code' => 'WAREHOUSE_INACTIVE',
                'available' => 0,
                'requested' => $quantity
            ];
        }
        
        // Get available stock
        $available = $this->getAvailableStock($productId, $warehouseId);
        
        if ($available < $quantity) {
            return [
                'success' => false,
                'message' => "Insufficient stock. Available: $available, Requested: $quantity",
                'code' => 'INSUFFICIENT_STOCK',
                'available' => $available,
                'requested' => $quantity
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Stock available',
            'available' => $available,
            'requested' => $quantity
        ];
    }
    
    /**
     * Get total stock for a product across all warehouses
     * 
     * @param int $productId Product ID
     * @return int Total quantity
     */
    public function getTotalStock(int $productId): int {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return 0;
        }
        
        if ($product['is_serializable']) {
            // Count all assets not scrapped or lost
            return $this->assetRepository->countByProductAndStatus($productId, AssetRepository::STATUS_IN_STOCK);
        } else {
            return $this->stockRepository->getTotalQuantity($productId);
        }
    }
    
    /**
     * Get stock levels for a product across all warehouses
     * 
     * @param int $productId Product ID
     * @return array Stock levels by warehouse
     */
    public function getStockByWarehouse(int $productId): array {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return [];
        }
        
        if ($product['is_serializable']) {
            // Get asset counts by warehouse
            $sql = "SELECT w.id as warehouse_id, w.name as warehouse_name, 
                           COUNT(a.id) as quantity, 0 as reserved_quantity
                    FROM warehouses w
                    LEFT JOIN assets a ON w.id = a.warehouse_id 
                        AND a.product_id = ? AND a.status = 'in_stock'
                    GROUP BY w.id, w.name
                    HAVING quantity > 0
                    ORDER BY w.name";
            return $this->db->getResults($sql, [$productId], 'i');
        } else {
            return $this->stockRepository->findAllWithDetails(['product_id' => $productId]);
        }
    }

    
    /**
     * Reserve stock for a pending dispatch
     * Requirement 5.2: Handle stock reservation for dispatch operations
     * 
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID
     * @param int $quantity Quantity to reserve
     * @param int|null $userId User performing the action
     * @return array Result with reservation details
     */
    public function reserveStock(int $productId, int $warehouseId, int $quantity, ?int $userId = null): array {
        // Validate stock availability first
        $validation = $this->validateStockAvailability($productId, $warehouseId, $quantity);
        if (!$validation['success']) {
            return $validation;
        }
        
        $product = $this->productRepository->find($productId);
        
        try {
            if ($product['is_serializable']) {
                // For serializable items, we don't use reservation - items are selected directly
                return [
                    'success' => true,
                    'message' => 'Serializable items do not require reservation',
                    'reservation_id' => null,
                    'quantity' => $quantity
                ];
            } else {
                // Reserve stock quantity
                $this->stockRepository->reserveQuantity($productId, $warehouseId, $quantity, $userId);
                
                // Log audit entry
                $this->logAuditEntry(
                    'stock_reserved',
                    'stock',
                    0,
                    $userId,
                    'warehouse',
                    $warehouseId,
                    null,
                    null,
                    ['quantity' => $quantity, 'product_id' => $productId]
                );
                
                return [
                    'success' => true,
                    'message' => "Reserved $quantity units",
                    'reservation_id' => "{$productId}_{$warehouseId}",
                    'quantity' => $quantity
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to reserve stock: ' . $e->getMessage(),
                'code' => 'RESERVE_ERROR'
            ];
        }
    }
    
    /**
     * Release a stock reservation
     * 
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID
     * @param int $quantity Quantity to release
     * @param int|null $userId User performing the action
     * @return array Result with success status
     */
    public function releaseReservation(int $productId, int $warehouseId, int $quantity, ?int $userId = null): array {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 'PRODUCT_NOT_FOUND'
            ];
        }
        
        if ($product['is_serializable']) {
            // Serializable items don't use reservation
            return [
                'success' => true,
                'message' => 'Serializable items do not require reservation release'
            ];
        }
        
        try {
            $this->stockRepository->releaseReservation($productId, $warehouseId, $quantity, $userId);
            
            // Log audit entry
            $this->logAuditEntry(
                'stock_reservation_released',
                'stock',
                0,
                $userId,
                'warehouse',
                $warehouseId,
                null,
                null,
                ['quantity' => $quantity, 'product_id' => $productId]
            );
            
            return [
                'success' => true,
                'message' => "Released reservation of $quantity units"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to release reservation: ' . $e->getMessage(),
                'code' => 'RELEASE_ERROR'
            ];
        }
    }
    
    /**
     * Deduct stock after dispatch is completed
     * 
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID
     * @param int $quantity Quantity to deduct
     * @param int|null $userId User performing the action
     * @param bool $wasReserved Whether the stock was previously reserved
     * @return array Result with success status
     */
    public function deductStock(int $productId, int $warehouseId, int $quantity, ?int $userId = null, bool $wasReserved = false): array {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Product not found',
                'code' => 'PRODUCT_NOT_FOUND'
            ];
        }
        
        if ($product['is_serializable']) {
            // For serializable items, deduction is handled by updating asset status
            return [
                'success' => true,
                'message' => 'Serializable items are deducted by updating asset status'
            ];
        }
        
        try {
            // If stock was reserved, release the reservation first
            if ($wasReserved) {
                $this->stockRepository->releaseReservation($productId, $warehouseId, $quantity, $userId);
            }
            
            // Subtract from stock
            $this->stockRepository->subtractQuantity($productId, $warehouseId, $quantity, $userId);
            
            // Log audit entry
            $this->logAuditEntry(
                'stock_deducted',
                'stock',
                0,
                $userId,
                'warehouse',
                $warehouseId,
                null,
                null,
                ['quantity' => $quantity, 'product_id' => $productId]
            );
            
            return [
                'success' => true,
                'message' => "Deducted $quantity units from stock"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to deduct stock: ' . $e->getMessage(),
                'code' => 'DEDUCT_ERROR'
            ];
        }
    }

    
    /**
     * Get available assets for dispatch (serializable items)
     * 
     * @param int $productId Product ID
     * @param int $warehouseId Warehouse ID
     * @param int $limit Maximum number of assets to return
     * @return array Available assets
     */
    public function getAvailableAssets(int $productId, int $warehouseId, int $limit = 100): array {
        $sql = "SELECT a.*, p.name as product_name 
                FROM assets a
                LEFT JOIN products p ON a.product_id = p.id
                WHERE a.product_id = ? 
                AND a.warehouse_id = ? 
                AND a.status = ?
                ORDER BY a.serial_number
                LIMIT ?";
        
        return $this->db->getResults($sql, [
            $productId, 
            $warehouseId, 
            AssetRepository::STATUS_IN_STOCK,
            $limit
        ], 'iisi');
    }
    
    /**
     * Get low stock products
     * 
     * @return array Products with stock below threshold
     */
    public function getLowStockProducts(): array {
        return $this->stockRepository->findLowStock();
    }
    
    /**
     * Transfer stock between warehouses
     * 
     * @param int $productId Product ID
     * @param int $fromWarehouseId Source warehouse ID
     * @param int $toWarehouseId Destination warehouse ID
     * @param int $quantity Quantity to transfer
     * @param int|null $userId User performing the action
     * @return array Result with success status
     */
    public function transferStock(int $productId, int $fromWarehouseId, int $toWarehouseId, int $quantity, ?int $userId = null): array {
        // Validate stock availability
        $validation = $this->validateStockAvailability($productId, $fromWarehouseId, $quantity);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Validate destination warehouse
        $toWarehouse = $this->warehouseRepository->find($toWarehouseId);
        if (!$toWarehouse) {
            return [
                'success' => false,
                'message' => 'Destination warehouse not found',
                'code' => 'WAREHOUSE_NOT_FOUND'
            ];
        }
        
        $product = $this->productRepository->find($productId);
        
        try {
            if ($product['is_serializable']) {
                return [
                    'success' => false,
                    'message' => 'Use transferAssets() for serializable items',
                    'code' => 'SERIALIZABLE_PRODUCT'
                ];
            }
            
            // Perform transfer
            $this->stockRepository->transfer($productId, $fromWarehouseId, $toWarehouseId, $quantity, $userId);
            
            // Log audit entry
            $this->logAuditEntry(
                'stock_transferred',
                'stock',
                0,
                $userId,
                'warehouse',
                $fromWarehouseId,
                'warehouse',
                $toWarehouseId,
                ['quantity' => $quantity, 'product_id' => $productId]
            );
            
            return [
                'success' => true,
                'message' => "Transferred $quantity units"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to transfer stock: ' . $e->getMessage(),
                'code' => 'TRANSFER_ERROR'
            ];
        }
    }
    
    /**
     * Log audit entry for inventory actions
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
}

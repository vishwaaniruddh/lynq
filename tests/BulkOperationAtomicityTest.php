<?php
/**
 * Property Test: Bulk Operation Atomicity
 * 
 * **Feature: adv-crm-inventory-module, Property 17: Bulk Operation Atomicity**
 * **Validates: Requirements 4.4**
 * 
 * Property: For any bulk dispatch operation that fails mid-transaction, 
 * no partial changes SHALL persist in the database.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/BulkInventoryService.php';
require_once __DIR__ . '/../services/StockService.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/DispatchRepository.php';
require_once __DIR__ . '/../repositories/DispatchItemRepository.php';

class BulkOperationAtomicityTest extends PropertyTestBase {
    
    private $bulkInventoryService;
    private $stockService;
    private $productRepository;
    private $warehouseRepository;
    private $stockRepository;
    private $assetRepository;
    private $dispatchRepository;
    private $dispatchItemRepository;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->bulkInventoryService = new BulkInventoryService();
        $this->stockService = new StockService();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->stockRepository = new StockRepository();
        $this->assetRepository = new AssetRepository();
        $this->dispatchRepository = new DispatchRepository();
        $this->dispatchRepository->disableCompanyFilter();
        $this->dispatchItemRepository = new DispatchItemRepository();
    }
    
    public function runTests() {
        echo "=== Bulk Operation Atomicity Property Test ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 17: Bulk Operation Atomicity**\n";
        echo "**Validates: Requirements 4.4**\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        $allPassed = true;
        
        // Run property test for bulk dispatch atomicity
        $allPassed &= $this->runPropertyTest(
            'Property 17: Bulk dispatch with invalid item rolls back all changes',
            function() {
                return $this->testBulkDispatchAtomicity();
            },
            100
        );
        
        // Run property test for bulk dispatch with mixed valid/invalid items
        $allPassed &= $this->runPropertyTest(
            'Property 17: Bulk dispatch with mixed items - atomic failure preserves original state',
            function() {
                return $this->testBulkDispatchMixedItemsAtomicity();
            },
            100
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Test bulk dispatch atomicity - when one item fails, no dispatch should be created
     * 
     * Property: For any bulk dispatch operation that fails mid-transaction,
     * no partial changes SHALL persist in the database.
     */
    private function testBulkDispatchAtomicity() {
        // Setup: Create test data
        $product = $this->createTestProduct(false); // Non-serializable
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        $destWarehouse = $this->createTestWarehouse();
        if (!$destWarehouse) {
            return ['success' => false, 'message' => 'Failed to create destination warehouse'];
        }
        
        // Add some stock
        $stockQuantity = $this->generateRandomInt(10, 50);
        $addResult = $this->stockService->addStock($product['id'], $warehouse['id'], $stockQuantity);
        if (!$addResult['success']) {
            return ['success' => false, 'message' => 'Failed to add stock: ' . $addResult['message']];
        }
        
        // Record initial state
        $initialDispatchCount = $this->getDispatchCount();
        $initialDispatchItemCount = $this->getDispatchItemCount();
        $initialStock = $this->stockService->getAvailableStock($product['id'], $warehouse['id']);
        
        // Create dispatch data with one valid item and one invalid item (non-existent product)
        $dispatchData = [
            'from_warehouse_id' => $warehouse['id'],
            'to_warehouse_id' => $destWarehouse['id'],
            'dispatch_date' => date('Y-m-d')
        ];
        
        $validQuantity = $this->generateRandomInt(1, min(5, $stockQuantity));
        $items = [
            [
                'product_id' => $product['id'],
                'quantity' => $validQuantity,
                '_row_number' => 2
            ],
            [
                'product_id' => 999999, // Non-existent product - will cause failure
                'quantity' => 1,
                '_row_number' => 3
            ]
        ];
        
        // Execute bulk dispatch (should fail due to invalid product)
        $result = $this->bulkInventoryService->processBulkDispatch($dispatchData, $items);
        
        // Verify atomicity: no changes should persist
        $finalDispatchCount = $this->getDispatchCount();
        $finalDispatchItemCount = $this->getDispatchItemCount();
        $finalStock = $this->stockService->getAvailableStock($product['id'], $warehouse['id']);
        
        // Check that no dispatch was created
        if ($finalDispatchCount !== $initialDispatchCount) {
            return [
                'success' => false,
                'message' => "Atomicity violated: Dispatch count changed from $initialDispatchCount to $finalDispatchCount",
                'data' => [
                    'initial_dispatch_count' => $initialDispatchCount,
                    'final_dispatch_count' => $finalDispatchCount
                ]
            ];
        }
        
        // Check that no dispatch items were created
        if ($finalDispatchItemCount !== $initialDispatchItemCount) {
            return [
                'success' => false,
                'message' => "Atomicity violated: Dispatch item count changed from $initialDispatchItemCount to $finalDispatchItemCount",
                'data' => [
                    'initial_item_count' => $initialDispatchItemCount,
                    'final_item_count' => $finalDispatchItemCount
                ]
            ];
        }
        
        // Check that stock was not modified
        if ($finalStock !== $initialStock) {
            return [
                'success' => false,
                'message' => "Atomicity violated: Stock changed from $initialStock to $finalStock",
                'data' => [
                    'initial_stock' => $initialStock,
                    'final_stock' => $finalStock
                ]
            ];
        }
        
        // Verify the result indicates failure and rollback
        if ($result->success) {
            return [
                'success' => false,
                'message' => 'Expected bulk dispatch to fail but it succeeded',
                'data' => $result->toArray()
            ];
        }
        
        return ['success' => true];
    }

    
    /**
     * Test bulk dispatch atomicity with mixed valid/invalid serializable items
     * 
     * Property: For any bulk dispatch with some valid and some invalid items,
     * if the operation is atomic, no changes should persist on failure.
     */
    private function testBulkDispatchMixedItemsAtomicity() {
        // Setup: Create test data
        $product = $this->createTestProduct(true); // Serializable
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        $destWarehouse = $this->createTestWarehouse();
        if (!$destWarehouse) {
            return ['success' => false, 'message' => 'Failed to create destination warehouse'];
        }
        
        // Add some assets
        $assetCount = $this->generateRandomInt(3, 10);
        $createdAssets = [];
        for ($i = 0; $i < $assetCount; $i++) {
            $serialNumber = 'ATOM-' . $this->generateRandomString(12) . '-' . $i;
            $addResult = $this->stockService->addAsset($product['id'], $warehouse['id'], $serialNumber);
            if (!$addResult['success']) {
                return ['success' => false, 'message' => 'Failed to add asset: ' . $addResult['message']];
            }
            $createdAssets[] = $addResult['data'];
            $this->createdRecords['assets'][] = $addResult['data']['id'];
        }
        
        // Record initial state
        $initialDispatchCount = $this->getDispatchCount();
        $initialDispatchItemCount = $this->getDispatchItemCount();
        $initialAssetStatuses = [];
        foreach ($createdAssets as $asset) {
            $currentAsset = $this->assetRepository->find($asset['id']);
            $initialAssetStatuses[$asset['id']] = $currentAsset['status'];
        }
        
        // Create dispatch data with valid assets and one invalid serial number
        $dispatchData = [
            'from_warehouse_id' => $warehouse['id'],
            'to_warehouse_id' => $destWarehouse['id'],
            'dispatch_date' => date('Y-m-d')
        ];
        
        // Use first asset (valid) and a non-existent serial number (invalid)
        $items = [
            [
                'product_id' => $product['id'],
                'serial_number' => $createdAssets[0]['serial_number'],
                '_row_number' => 2
            ],
            [
                'product_id' => $product['id'],
                'serial_number' => 'NON-EXISTENT-SERIAL-' . $this->generateRandomString(10),
                '_row_number' => 3
            ]
        ];
        
        // Execute bulk dispatch (should fail due to invalid serial number)
        $result = $this->bulkInventoryService->processBulkDispatch($dispatchData, $items);
        
        // Verify atomicity: no changes should persist
        $finalDispatchCount = $this->getDispatchCount();
        $finalDispatchItemCount = $this->getDispatchItemCount();
        
        // Check that no dispatch was created
        if ($finalDispatchCount !== $initialDispatchCount) {
            return [
                'success' => false,
                'message' => "Atomicity violated: Dispatch count changed from $initialDispatchCount to $finalDispatchCount",
                'data' => [
                    'initial_dispatch_count' => $initialDispatchCount,
                    'final_dispatch_count' => $finalDispatchCount
                ]
            ];
        }
        
        // Check that no dispatch items were created
        if ($finalDispatchItemCount !== $initialDispatchItemCount) {
            return [
                'success' => false,
                'message' => "Atomicity violated: Dispatch item count changed from $initialDispatchItemCount to $finalDispatchItemCount",
                'data' => [
                    'initial_item_count' => $initialDispatchItemCount,
                    'final_item_count' => $finalDispatchItemCount
                ]
            ];
        }
        
        // Check that asset statuses were not modified
        foreach ($createdAssets as $asset) {
            $currentAsset = $this->assetRepository->find($asset['id']);
            if ($currentAsset['status'] !== $initialAssetStatuses[$asset['id']]) {
                return [
                    'success' => false,
                    'message' => "Atomicity violated: Asset {$asset['id']} status changed from {$initialAssetStatuses[$asset['id']]} to {$currentAsset['status']}",
                    'data' => [
                        'asset_id' => $asset['id'],
                        'initial_status' => $initialAssetStatuses[$asset['id']],
                        'final_status' => $currentAsset['status']
                    ]
                ];
            }
        }
        
        // Verify the result indicates failure
        if ($result->success) {
            return [
                'success' => false,
                'message' => 'Expected bulk dispatch to fail but it succeeded',
                'data' => $result->toArray()
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Check if required tables exist
     */
    private function tablesExist() {
        $requiredTables = ['products', 'warehouses', 'stock', 'assets', 'dispatches', 'dispatch_items', 'companies'];
        
        foreach ($requiredTables as $table) {
            try {
                $result = $this->db->query("SHOW TABLES LIKE '$table'");
                if (!$result || $result->num_rows === 0) {
                    return false;
                }
            } catch (Exception $e) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get current dispatch count
     */
    private function getDispatchCount(): int {
        $sql = "SELECT COUNT(*) as count FROM dispatches";
        $result = $this->getResults($sql);
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Get current dispatch item count
     */
    private function getDispatchItemCount(): int {
        $sql = "SELECT COUNT(*) as count FROM dispatch_items";
        $result = $this->getResults($sql);
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Create a test product
     */
    private function createTestProduct(bool $isSerializable) {
        try {
            $productData = [
                'name' => 'Test Product Atomicity ' . $this->generateRandomString(8),
                'unit_of_measure' => 'unit',
                'inventory_type' => 'INTERNAL',
                'is_serializable' => $isSerializable ? 1 : 0,
                'is_repairable' => 0,
                'low_stock_threshold' => 10,
                'status' => 'active'
            ];
            
            $product = $this->productRepository->create($productData);
            $this->createdRecords['products'][] = $product['id'];
            return $product;
            
        } catch (Exception $e) {
            error_log("Failed to create test product: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create a test warehouse
     */
    private function createTestWarehouse() {
        try {
            $companyId = $this->getTestCompanyId();
            
            $warehouseData = [
                'name' => 'Test Warehouse Atomicity ' . $this->generateRandomString(8),
                'location' => 'Test Location',
                'company_id' => $companyId,
                'status' => 'active'
            ];
            
            $warehouse = $this->warehouseRepository->create($warehouseData);
            $this->createdRecords['warehouses'][] = $warehouse['id'];
            return $warehouse;
            
        } catch (Exception $e) {
            error_log("Failed to create test warehouse: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get a test company ID
     */
    private function getTestCompanyId() {
        $sql = "SELECT id FROM companies WHERE status = 'ACTIVE' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (empty($result)) {
            $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test Company Atomicity ' . $this->generateRandomString(8), 'ADV', 'ACTIVE'],
                'sss'
            );
            $companyId = $this->db->insert_id;
            $this->createdRecords['companies'][] = $companyId;
            return $companyId;
        }
        
        return $result[0]['id'];
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Delete test dispatch items first (foreign key constraint)
            if (!empty($this->createdRecords['dispatches'])) {
                $dispatchIds = implode(',', array_map('intval', $this->createdRecords['dispatches']));
                $this->db->query("DELETE FROM `dispatch_items` WHERE dispatch_id IN ($dispatchIds)");
            }
            
            // Delete test dispatches
            if (!empty($this->createdRecords['dispatches'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['dispatches']));
                $this->db->query("DELETE FROM `dispatches` WHERE id IN ($ids)");
            }
            
            // Delete test assets
            if (!empty($this->createdRecords['assets'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['assets']));
                $this->db->query("DELETE FROM `assets` WHERE id IN ($ids)");
            }
            
            // Delete test stock
            if (!empty($this->createdRecords['products'])) {
                $productIds = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM `stock` WHERE product_id IN ($productIds)");
            }
            
            // Delete test products
            if (!empty($this->createdRecords['products'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM `products` WHERE id IN ($ids)");
            }
            
            // Delete test warehouses
            if (!empty($this->createdRecords['warehouses'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['warehouses']));
                $this->db->query("DELETE FROM `warehouses` WHERE id IN ($ids)");
            }
            
            // Delete test companies
            if (!empty($this->createdRecords['companies'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['companies']));
                $this->db->query("DELETE FROM `companies` WHERE id IN ($ids)");
            }
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

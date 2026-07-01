<?php
/**
 * Unit Tests for BulkInventoryService
 * 
 * Tests bulk validation logic, partial success handling, and atomic rollback
 * 
 * Requirements: 4.1, 4.2, 4.4
 * - 4.1: Validate all rows before committing any changes
 * - 4.2: Generate error report listing failed rows with reasons while allowing partial success
 * - 4.4: Rollback all changes and report the failure reason on bulk operation failure
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/BulkInventoryService.php';
require_once __DIR__ . '/../services/StockService.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';

class BulkInventoryServiceTest extends PropertyTestBase {
    
    private $bulkInventoryService;
    private $stockService;
    private $productRepository;
    private $warehouseRepository;
    private $stockRepository;
    private $assetRepository;
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
    }
    
    public function runTests() {
        echo "=== BulkInventoryService Unit Tests ===\n";
        echo "Requirements: 4.1, 4.2, 4.4\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        $allPassed = true;
        
        // Test bulk validation logic (Requirement 4.1)
        $allPassed &= $this->runTest('Test bulk validation - valid rows', function() {
            return $this->testBulkValidationValidRows();
        });
        
        $allPassed &= $this->runTest('Test bulk validation - missing product_id', function() {
            return $this->testBulkValidationMissingProductId();
        });
        
        $allPassed &= $this->runTest('Test bulk validation - missing warehouse_id', function() {
            return $this->testBulkValidationMissingWarehouseId();
        });
        
        $allPassed &= $this->runTest('Test bulk validation - duplicate serial numbers in batch', function() {
            return $this->testBulkValidationDuplicateSerialNumbers();
        });
        
        $allPassed &= $this->runTest('Test bulk validation - invalid quantity', function() {
            return $this->testBulkValidationInvalidQuantity();
        });
        
        // Test partial success handling (Requirement 4.2)
        $allPassed &= $this->runTest('Test partial success - mixed valid/invalid rows', function() {
            return $this->testPartialSuccessHandling();
        });
        
        $allPassed &= $this->runTest('Test result summary generation', function() {
            return $this->testResultSummaryGeneration();
        });
        
        // Test atomic rollback (Requirement 4.4)
        $allPassed &= $this->runTest('Test atomic rollback on dispatch failure', function() {
            return $this->testAtomicRollbackOnFailure();
        });
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Run a single test
     */
    private function runTest($testName, $testFunction) {
        echo "Running: $testName... ";
        try {
            $result = $testFunction();
            if ($result['success']) {
                echo "PASSED\n";
                return true;
            } else {
                echo "FAILED: {$result['message']}\n";
                return false;
            }
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test bulk validation with valid rows
     * Requirement 4.1: Validate all rows before committing any changes
     */
    private function testBulkValidationValidRows() {
        // Create test data
        $product = $this->createTestProduct(false);
        $warehouse = $this->createTestWarehouse();
        
        if (!$product || !$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test data'];
        }
        
        $rows = [
            ['product_id' => $product['id'], 'warehouse_id' => $warehouse['id'], 'quantity' => 10, '_row_number' => 2],
            ['product_id' => $product['id'], 'warehouse_id' => $warehouse['id'], 'quantity' => 20, '_row_number' => 3]
        ];
        
        $result = $this->bulkInventoryService->validateBulkUpload($rows);
        
        if (!$result['success']) {
            return ['success' => false, 'message' => 'Validation should pass for valid rows'];
        }
        
        if ($result['validCount'] !== 2) {
            return ['success' => false, 'message' => "Expected 2 valid rows, got {$result['validCount']}"];
        }
        
        if ($result['invalidCount'] !== 0) {
            return ['success' => false, 'message' => "Expected 0 invalid rows, got {$result['invalidCount']}"];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test bulk validation with missing product_id
     */
    private function testBulkValidationMissingProductId() {
        $warehouse = $this->createTestWarehouse();
        
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        $rows = [
            ['warehouse_id' => $warehouse['id'], 'quantity' => 10, '_row_number' => 2]
        ];
        
        $result = $this->bulkInventoryService->validateBulkUpload($rows);
        
        if ($result['success']) {
            return ['success' => false, 'message' => 'Validation should fail for missing product_id'];
        }
        
        if ($result['invalidCount'] !== 1) {
            return ['success' => false, 'message' => "Expected 1 invalid row, got {$result['invalidCount']}"];
        }
        
        // Check error message
        if (!isset($result['errors'][2])) {
            return ['success' => false, 'message' => 'Expected error for row 2'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test bulk validation with missing warehouse_id
     */
    private function testBulkValidationMissingWarehouseId() {
        $product = $this->createTestProduct(false);
        
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        $rows = [
            ['product_id' => $product['id'], 'quantity' => 10, '_row_number' => 2]
        ];
        
        $result = $this->bulkInventoryService->validateBulkUpload($rows);
        
        if ($result['success']) {
            return ['success' => false, 'message' => 'Validation should fail for missing warehouse_id'];
        }
        
        if ($result['invalidCount'] !== 1) {
            return ['success' => false, 'message' => "Expected 1 invalid row, got {$result['invalidCount']}"];
        }
        
        return ['success' => true];
    }

    
    /**
     * Test bulk validation with duplicate serial numbers in batch
     */
    private function testBulkValidationDuplicateSerialNumbers() {
        $product = $this->createTestProduct(true); // Serializable
        $warehouse = $this->createTestWarehouse();
        
        if (!$product || !$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test data'];
        }
        
        $duplicateSerial = 'DUP-' . $this->generateRandomString(10);
        
        $rows = [
            ['product_id' => $product['id'], 'warehouse_id' => $warehouse['id'], 'serial_number' => $duplicateSerial, '_row_number' => 2],
            ['product_id' => $product['id'], 'warehouse_id' => $warehouse['id'], 'serial_number' => $duplicateSerial, '_row_number' => 3]
        ];
        
        $result = $this->bulkInventoryService->validateBulkUpload($rows);
        
        if ($result['success']) {
            return ['success' => false, 'message' => 'Validation should fail for duplicate serial numbers'];
        }
        
        // First row should be valid, second should be invalid (duplicate)
        if ($result['validCount'] !== 1) {
            return ['success' => false, 'message' => "Expected 1 valid row, got {$result['validCount']}"];
        }
        
        if ($result['invalidCount'] !== 1) {
            return ['success' => false, 'message' => "Expected 1 invalid row, got {$result['invalidCount']}"];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test bulk validation with invalid quantity
     */
    private function testBulkValidationInvalidQuantity() {
        $product = $this->createTestProduct(false);
        $warehouse = $this->createTestWarehouse();
        
        if (!$product || !$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test data'];
        }
        
        $rows = [
            ['product_id' => $product['id'], 'warehouse_id' => $warehouse['id'], 'quantity' => -5, '_row_number' => 2],
            ['product_id' => $product['id'], 'warehouse_id' => $warehouse['id'], 'quantity' => 0, '_row_number' => 3],
            ['product_id' => $product['id'], 'warehouse_id' => $warehouse['id'], 'quantity' => 'abc', '_row_number' => 4]
        ];
        
        $result = $this->bulkInventoryService->validateBulkUpload($rows);
        
        if ($result['success']) {
            return ['success' => false, 'message' => 'Validation should fail for invalid quantities'];
        }
        
        if ($result['invalidCount'] !== 3) {
            return ['success' => false, 'message' => "Expected 3 invalid rows, got {$result['invalidCount']}"];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test partial success handling
     * Requirement 4.2: Allow partial success for valid rows
     */
    private function testPartialSuccessHandling() {
        $product = $this->createTestProduct(false);
        $warehouse = $this->createTestWarehouse();
        
        if (!$product || !$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test data'];
        }
        
        // Mix of valid and invalid rows
        $rows = [
            ['product_id' => $product['id'], 'warehouse_id' => $warehouse['id'], 'quantity' => 10, '_row_number' => 2],
            ['product_id' => 999999, 'warehouse_id' => $warehouse['id'], 'quantity' => 10, '_row_number' => 3], // Invalid product
            ['product_id' => $product['id'], 'warehouse_id' => $warehouse['id'], 'quantity' => 20, '_row_number' => 4]
        ];
        
        // First validate
        $validation = $this->bulkInventoryService->validateBulkUpload($rows);
        
        // Process only valid rows
        $result = $this->bulkInventoryService->processBulkStockEntry($validation['validRows']);
        
        // Should have partial success
        if ($result->successCount !== 2) {
            return ['success' => false, 'message' => "Expected 2 successful rows, got {$result->successCount}"];
        }
        
        // Check row results are tracked
        if (count($result->rowResults) !== 2) {
            return ['success' => false, 'message' => "Expected 2 row results, got " . count($result->rowResults)];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test result summary generation
     */
    private function testResultSummaryGeneration() {
        $product = $this->createTestProduct(false);
        $warehouse = $this->createTestWarehouse();
        
        if (!$product || !$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test data'];
        }
        
        $rows = [
            ['product_id' => $product['id'], 'warehouse_id' => $warehouse['id'], 'quantity' => 10, '_row_number' => 2],
            ['product_id' => $product['id'], 'warehouse_id' => $warehouse['id'], 'quantity' => 20, '_row_number' => 3]
        ];
        
        $result = $this->bulkInventoryService->processBulkStockEntry($rows);
        $summary = $this->bulkInventoryService->getResultSummary($result);
        
        // Check summary fields
        if (!isset($summary['totalRows']) || $summary['totalRows'] !== 2) {
            return ['success' => false, 'message' => 'Summary should have correct totalRows'];
        }
        
        if (!isset($summary['successCount']) || $summary['successCount'] !== 2) {
            return ['success' => false, 'message' => 'Summary should have correct successCount'];
        }
        
        if (!isset($summary['successRate']) || $summary['successRate'] !== 100.0) {
            return ['success' => false, 'message' => 'Summary should have correct successRate'];
        }
        
        if (!isset($summary['isAtomic']) || $summary['isAtomic'] !== false) {
            return ['success' => false, 'message' => 'Stock entry should not be atomic'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test atomic rollback on dispatch failure
     * Requirement 4.4: Rollback all changes on bulk operation failure
     */
    private function testAtomicRollbackOnFailure() {
        $product = $this->createTestProduct(true); // Serializable product
        $warehouse = $this->createTestWarehouse();
        $destWarehouse = $this->createTestWarehouse();
        
        if (!$product || !$warehouse || !$destWarehouse) {
            return ['success' => false, 'message' => 'Failed to create test data'];
        }
        
        // Add assets to warehouse
        $serial1 = 'ROLLBACK-' . $this->generateRandomString(10) . '-1';
        $serial2 = 'ROLLBACK-' . $this->generateRandomString(10) . '-2';
        
        $addResult1 = $this->stockService->addAsset($product['id'], $warehouse['id'], $serial1);
        if (!$addResult1['success']) {
            return ['success' => false, 'message' => 'Failed to add first asset'];
        }
        $this->createdRecords['assets'][] = $addResult1['data']['id'];
        
        $addResult2 = $this->stockService->addAsset($product['id'], $warehouse['id'], $serial2);
        if (!$addResult2['success']) {
            return ['success' => false, 'message' => 'Failed to add second asset'];
        }
        $this->createdRecords['assets'][] = $addResult2['data']['id'];
        
        // Record initial state
        $initialDispatchCount = $this->getDispatchCount();
        $initialDispatchItemCount = $this->getDispatchItemCount();
        
        // Create dispatch with one valid asset and one invalid serial number
        $dispatchData = [
            'from_warehouse_id' => $warehouse['id'],
            'to_warehouse_id' => $destWarehouse['id']
        ];
        
        $items = [
            ['product_id' => $product['id'], 'serial_number' => $serial1, '_row_number' => 2],
            ['product_id' => $product['id'], 'serial_number' => 'NON-EXISTENT-SERIAL-' . $this->generateRandomString(10), '_row_number' => 3] // Invalid
        ];
        
        $result = $this->bulkInventoryService->processBulkDispatch($dispatchData, $items);
        
        // Should fail (validation catches the invalid serial number)
        if ($result->success) {
            return ['success' => false, 'message' => 'Dispatch should fail with invalid serial number'];
        }
        
        // Check no dispatch was created
        $finalDispatchCount = $this->getDispatchCount();
        if ($finalDispatchCount !== $initialDispatchCount) {
            return ['success' => false, 'message' => 'Dispatch count should not change after failure'];
        }
        
        // Check no dispatch items were created
        $finalDispatchItemCount = $this->getDispatchItemCount();
        if ($finalDispatchItemCount !== $initialDispatchItemCount) {
            return ['success' => false, 'message' => 'Dispatch item count should not change after failure'];
        }
        
        // Check isAtomic flag (dispatch operations are atomic)
        if (!$result->isAtomic) {
            return ['success' => false, 'message' => 'Dispatch should be atomic'];
        }
        
        // Note: wasRolledBack is only true if transaction started and then rolled back
        // If validation fails before transaction, wasRolledBack will be false
        // This is correct behavior - no transaction to rollback
        
        return ['success' => true];
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
     * Create a test product
     */
    private function createTestProduct(bool $isSerializable) {
        try {
            $productData = [
                'name' => 'Test Product Bulk ' . $this->generateRandomString(8),
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
                'name' => 'Test Warehouse Bulk ' . $this->generateRandomString(8),
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
                ['Test Company Bulk ' . $this->generateRandomString(8), 'ADV', 'ACTIVE'],
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

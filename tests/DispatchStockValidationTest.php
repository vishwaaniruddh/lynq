<?php
/**
 * Property Test: Dispatch Stock Validation
 * 
 * **Feature: adv-crm-inventory-module, Property 4: Dispatch Stock Validation**
 * **Validates: Requirements 5.2**
 * 
 * Property: For any dispatch operation, the system SHALL reject the dispatch 
 * if the requested quantity exceeds available stock in the source warehouse.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/StockService.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';

class DispatchStockValidationTest extends PropertyTestBase {
    
    private $stockService;
    private $productRepository;
    private $warehouseRepository;
    private $stockRepository;
    private $assetRepository;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->stockService = new StockService();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->stockRepository = new StockRepository();
        $this->assetRepository = new AssetRepository();
    }
    
    public function runTests() {
        echo "=== Dispatch Stock Validation Property Test ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 4: Dispatch Stock Validation**\n";
        echo "**Validates: Requirements 5.2**\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        $allPassed = true;
        
        // Run property test for non-serializable items
        $allPassed &= $this->runPropertyTest(
            'Property 4: Dispatch rejects when requested quantity exceeds available stock (non-serializable)',
            function() {
                return $this->testDispatchStockValidationNonSerializable();
            },
            100
        );
        
        // Run property test for serializable items
        $allPassed &= $this->runPropertyTest(
            'Property 4: Dispatch rejects when requested quantity exceeds available assets (serializable)',
            function() {
                return $this->testDispatchStockValidationSerializable();
            },
            100
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Test dispatch stock validation for non-serializable items
     * 
     * Property: For any non-serializable product with stock quantity Q in warehouse W,
     * validateStockAvailability(product, W, R) should return:
     * - success=true if R <= Q
     * - success=false if R > Q
     */
    private function testDispatchStockValidationNonSerializable() {
        // Generate random test data
        $stockQuantity = $this->generateRandomInt(1, 100);
        $requestedQuantity = $this->generateRandomInt(1, 150);
        
        // Create test product (non-serializable)
        $product = $this->createTestProduct(false);
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        // Create test warehouse
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        // Add stock to warehouse
        $addResult = $this->stockService->addStock($product['id'], $warehouse['id'], $stockQuantity);
        if (!$addResult['success']) {
            return ['success' => false, 'message' => 'Failed to add stock: ' . $addResult['message']];
        }
        
        // Validate stock availability
        $validation = $this->stockService->validateStockAvailability(
            $product['id'], 
            $warehouse['id'], 
            $requestedQuantity
        );
        
        // Check property: success should be true iff requested <= available
        $expectedSuccess = ($requestedQuantity <= $stockQuantity);
        
        if ($validation['success'] !== $expectedSuccess) {
            return [
                'success' => false,
                'message' => "Property violated: stock=$stockQuantity, requested=$requestedQuantity, " .
                            "expected success=$expectedSuccess, got success=" . ($validation['success'] ? 'true' : 'false'),
                'data' => [
                    'stock_quantity' => $stockQuantity,
                    'requested_quantity' => $requestedQuantity,
                    'expected_success' => $expectedSuccess,
                    'actual_success' => $validation['success']
                ]
            ];
        }
        
        // Additional check: available quantity should be reported correctly
        if ($validation['available'] != $stockQuantity) {
            return [
                'success' => false,
                'message' => "Available quantity mismatch: expected=$stockQuantity, got={$validation['available']}",
                'data' => [
                    'expected_available' => $stockQuantity,
                    'actual_available' => $validation['available']
                ]
            ];
        }
        
        return ['success' => true];
    }

    
    /**
     * Test dispatch stock validation for serializable items
     * 
     * Property: For any serializable product with N assets in stock at warehouse W,
     * validateStockAvailability(product, W, R) should return:
     * - success=true if R <= N
     * - success=false if R > N
     */
    private function testDispatchStockValidationSerializable() {
        // Generate random test data
        $assetCount = $this->generateRandomInt(1, 20);
        $requestedQuantity = $this->generateRandomInt(1, 30);
        
        // Create test product (serializable)
        $product = $this->createTestProduct(true);
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        // Create test warehouse
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        // Add assets to warehouse
        for ($i = 0; $i < $assetCount; $i++) {
            $serialNumber = 'SN-' . $this->generateRandomString(12) . '-' . $i;
            $addResult = $this->stockService->addAsset($product['id'], $warehouse['id'], $serialNumber);
            if (!$addResult['success']) {
                return ['success' => false, 'message' => 'Failed to add asset: ' . $addResult['message']];
            }
            $this->createdRecords['assets'][] = $addResult['data']['id'];
        }
        
        // Validate stock availability
        $validation = $this->stockService->validateStockAvailability(
            $product['id'], 
            $warehouse['id'], 
            $requestedQuantity
        );
        
        // Check property: success should be true iff requested <= available assets
        $expectedSuccess = ($requestedQuantity <= $assetCount);
        
        if ($validation['success'] !== $expectedSuccess) {
            return [
                'success' => false,
                'message' => "Property violated: assets=$assetCount, requested=$requestedQuantity, " .
                            "expected success=$expectedSuccess, got success=" . ($validation['success'] ? 'true' : 'false'),
                'data' => [
                    'asset_count' => $assetCount,
                    'requested_quantity' => $requestedQuantity,
                    'expected_success' => $expectedSuccess,
                    'actual_success' => $validation['success']
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Check if required tables exist
     */
    private function tablesExist() {
        $requiredTables = ['products', 'warehouses', 'stock', 'assets', 'companies'];
        
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
     * Create a test product
     */
    private function createTestProduct(bool $isSerializable) {
        try {
            $productData = [
                'name' => 'Test Product ' . $this->generateRandomString(8),
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
            // Get or create a test company
            $companyId = $this->getTestCompanyId();
            
            $warehouseData = [
                'name' => 'Test Warehouse ' . $this->generateRandomString(8),
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
            // Create a test company if none exists
            $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test Company ' . $this->generateRandomString(8), 'ADV', 'ACTIVE'],
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

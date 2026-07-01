<?php
/**
 * Property Test: Dispatched Asset Exclusion from Available Inventory
 * **Feature: dispatch-workflow-fixes, Property 6: Dispatched Asset Exclusion from Available Inventory**
 * **Validates: Requirements 2.3**
 * 
 * Property: For any asset with status "dispatched", the asset SHALL NOT appear in queries 
 * for available/in_stock inventory at the sender's location.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';

class DispatchedAssetExclusionPropertyTest extends PropertyTestBase {
    private $assetRepository;
    private $productRepository;
    private $warehouseRepository;
    private $companyRepository;
    
    private $createdAssetIds = [];
    private $createdProductIds = [];
    private $createdWarehouseIds = [];
    private $createdCompanyIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->assetRepository = new AssetRepository();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->companyRepository = new CompanyRepository();
        $this->companyRepository->disableCompanyFilter();
    }
    
    /**
     * Run all property tests
     */
    public function runTests() {
        echo "\n=== Dispatched Asset Exclusion Property Tests ===\n";
        echo "**Feature: dispatch-workflow-fixes, Property 6: Dispatched Asset Exclusion from Available Inventory**\n";
        echo "**Validates: Requirements 2.3**\n\n";
        
        $results = [];
        
        // Property 6a: Dispatched assets excluded from in_stock queries
        $results['dispatched_excluded_from_in_stock'] = $this->runPropertyTest(
            'Property 6a: Dispatched assets are excluded from in_stock queries',
            function() {
                return $this->testDispatchedExcludedFromInStock();
            },
            50
        );
        
        // Property 6b: Dispatched assets excluded from findInStockAtWarehouse
        $results['dispatched_excluded_from_warehouse_stock'] = $this->runPropertyTest(
            'Property 6b: Dispatched assets are excluded from warehouse in_stock queries',
            function() {
                return $this->testDispatchedExcludedFromWarehouseStock();
            },
            50
        );
        
        // Property 6c: Dispatched assets still visible when filtering by dispatched status
        $results['dispatched_visible_when_filtered'] = $this->runPropertyTest(
            'Property 6c: Dispatched assets are visible when filtering by dispatched status',
            function() {
                return $this->testDispatchedVisibleWhenFiltered();
            },
            50
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($results));
        $total = count($results);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }
    
    /**
     * Property 6a: Dispatched assets are excluded from in_stock queries
     * Requirement 2.3: Dispatched assets SHALL NOT show as "available" or "in stock"
     */
    private function testDispatchedExcludedFromInStock(): array {
        // Create test company and warehouse
        $company = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($company['id']);
        
        // Create product
        $product = $this->createTestProduct(true);
        
        // Create an in_stock asset
        $inStockAsset = $this->createTestAsset($product['id'], $warehouse['id'], 'in_stock');
        
        // Create a dispatched asset (simulating dispatch from this warehouse)
        $dispatchedAsset = $this->createTestDispatchedAsset($product['id'], $warehouse['id']);
        
        // Query for in_stock assets using the search method
        $filters = [
            'warehouse_id' => $warehouse['id'],
            'status' => 'in_stock'
        ];
        $results = $this->assetRepository->search($filters);
        $resultIds = array_column($results, 'id');
        
        // In_stock asset should be in results
        $inStockFound = in_array($inStockAsset['id'], $resultIds);
        
        // Dispatched asset should NOT be in results
        $dispatchedFound = in_array($dispatchedAsset['id'], $resultIds);
        
        if (!$inStockFound) {
            return [
                'success' => false,
                'message' => 'In_stock asset not found in in_stock query',
                'data' => [
                    'in_stock_asset_id' => $inStockAsset['id'],
                    'result_ids' => $resultIds
                ]
            ];
        }
        
        if ($dispatchedFound) {
            return [
                'success' => false,
                'message' => 'Dispatched asset incorrectly appears in in_stock query',
                'data' => [
                    'dispatched_asset_id' => $dispatchedAsset['id'],
                    'dispatched_asset_status' => $dispatchedAsset['status'],
                    'result_ids' => $resultIds
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 6b: Dispatched assets are excluded from findInStockAtWarehouse
     * Requirement 2.3: Dispatched assets SHALL NOT show as "available" or "in stock"
     */
    private function testDispatchedExcludedFromWarehouseStock(): array {
        // Create test company and warehouse
        $company = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($company['id']);
        
        // Create product
        $product = $this->createTestProduct(true);
        
        // Create multiple in_stock assets
        $inStockAsset1 = $this->createTestAsset($product['id'], $warehouse['id'], 'in_stock');
        $inStockAsset2 = $this->createTestAsset($product['id'], $warehouse['id'], 'in_stock');
        
        // Create a dispatched asset
        $dispatchedAsset = $this->createTestDispatchedAsset($product['id'], $warehouse['id']);
        
        // Use findInStockAtWarehouse method
        $results = $this->assetRepository->findInStockAtWarehouse($warehouse['id']);
        $resultIds = array_column($results, 'id');
        
        // Both in_stock assets should be found
        $inStock1Found = in_array($inStockAsset1['id'], $resultIds);
        $inStock2Found = in_array($inStockAsset2['id'], $resultIds);
        
        // Dispatched asset should NOT be found
        $dispatchedFound = in_array($dispatchedAsset['id'], $resultIds);
        
        if (!$inStock1Found || !$inStock2Found) {
            return [
                'success' => false,
                'message' => 'In_stock assets not found in findInStockAtWarehouse',
                'data' => [
                    'in_stock_asset1_found' => $inStock1Found,
                    'in_stock_asset2_found' => $inStock2Found,
                    'result_ids' => $resultIds
                ]
            ];
        }
        
        if ($dispatchedFound) {
            return [
                'success' => false,
                'message' => 'Dispatched asset incorrectly appears in findInStockAtWarehouse',
                'data' => [
                    'dispatched_asset_id' => $dispatchedAsset['id'],
                    'result_ids' => $resultIds
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 6c: Dispatched assets are visible when filtering by dispatched status
     * This ensures dispatched assets are still trackable, just not shown as available
     */
    private function testDispatchedVisibleWhenFiltered(): array {
        // Create test company and warehouse
        $company = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($company['id']);
        
        // Create product
        $product = $this->createTestProduct(true);
        
        // Create a dispatched asset
        $dispatchedAsset = $this->createTestDispatchedAsset($product['id'], $warehouse['id']);
        
        // Query for dispatched assets
        $filters = [
            'status' => 'dispatched'
        ];
        $results = $this->assetRepository->search($filters);
        $resultIds = array_column($results, 'id');
        
        // Dispatched asset should be found when filtering by dispatched status
        $dispatchedFound = in_array($dispatchedAsset['id'], $resultIds);
        
        if (!$dispatchedFound) {
            return [
                'success' => false,
                'message' => 'Dispatched asset not found when filtering by dispatched status',
                'data' => [
                    'dispatched_asset_id' => $dispatchedAsset['id'],
                    'result_ids' => $resultIds
                ]
            ];
        }
        
        // Also verify the asset has dispatch details via findWithDispatchDetails
        $assetWithDetails = $this->assetRepository->findWithDispatchDetails($dispatchedAsset['id']);
        
        if (!$assetWithDetails) {
            return [
                'success' => false,
                'message' => 'findWithDispatchDetails returned null for dispatched asset',
                'data' => [
                    'dispatched_asset_id' => $dispatchedAsset['id']
                ]
            ];
        }
        
        if ($assetWithDetails['status'] !== 'dispatched') {
            return [
                'success' => false,
                'message' => 'Asset status mismatch in findWithDispatchDetails',
                'data' => [
                    'expected_status' => 'dispatched',
                    'actual_status' => $assetWithDetails['status']
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Create test company
     */
    private function createTestCompany(string $type = 'ADV'): array {
        $data = [
            'name' => 'Test Company ' . $this->generateRandomString(8),
            'type' => $type,
            'status' => 'ACTIVE'
        ];
        
        $company = $this->companyRepository->create($data);
        $this->createdCompanyIds[] = $company['id'];
        return $company;
    }
    
    /**
     * Create test warehouse
     */
    private function createTestWarehouse(int $companyId): array {
        $data = [
            'name' => 'Test Warehouse ' . $this->generateRandomString(8),
            'location' => 'Test Location',
            'company_id' => $companyId,
            'status' => 'active'
        ];
        
        $warehouse = $this->warehouseRepository->create($data);
        $this->createdWarehouseIds[] = $warehouse['id'];
        return $warehouse;
    }
    
    /**
     * Create test product
     */
    private function createTestProduct(bool $serializable = true): array {
        $data = [
            'name' => 'Test Product ' . $this->generateRandomString(8),
            'unit_of_measure' => 'unit',
            'inventory_type' => 'INTERNAL',
            'is_serializable' => $serializable ? 1 : 0,
            'is_repairable' => 1,
            'status' => 'active'
        ];
        
        $product = $this->productRepository->create($data);
        $this->createdProductIds[] = $product['id'];
        return $product;
    }
    
    /**
     * Create test asset with specified status
     */
    private function createTestAsset(int $productId, int $warehouseId, string $status = 'in_stock'): array {
        $data = [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'serial_number' => 'SN-' . $this->generateRandomString(12),
            'status' => $status,
            'working_condition' => AssetRepository::CONDITION_WORKING,
            'current_holder_type' => AssetRepository::HOLDER_WAREHOUSE,
            'current_holder_id' => $warehouseId,
            'source_warehouse_id' => $warehouseId
        ];
        
        $asset = $this->assetRepository->create($data);
        $this->createdAssetIds[] = $asset['id'];
        return $asset;
    }
    
    /**
     * Create test dispatched asset (simulating dispatch from warehouse)
     * The asset has status 'dispatched' and source_warehouse_id set to the original warehouse
     */
    private function createTestDispatchedAsset(int $productId, int $sourceWarehouseId): array {
        // Create a contractor company to dispatch to
        $contractor = $this->createTestCompany('CONTRACTOR');
        
        $data = [
            'product_id' => $productId,
            'warehouse_id' => null, // No longer in a warehouse
            'serial_number' => 'SN-DISP-' . $this->generateRandomString(10),
            'status' => AssetRepository::STATUS_DISPATCHED,
            'working_condition' => AssetRepository::CONDITION_WORKING,
            'current_holder_type' => AssetRepository::HOLDER_COMPANY,
            'current_holder_id' => $contractor['id'],
            'source_warehouse_id' => $sourceWarehouseId
        ];
        
        $asset = $this->assetRepository->create($data);
        $this->createdAssetIds[] = $asset['id'];
        return $asset;
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        // Delete assets first (foreign key constraints)
        foreach ($this->createdAssetIds as $id) {
            try {
                $this->assetRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete products
        foreach ($this->createdProductIds as $id) {
            try {
                $this->productRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete warehouses
        foreach ($this->createdWarehouseIds as $id) {
            try {
                $this->warehouseRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete companies
        foreach ($this->createdCompanyIds as $id) {
            try {
                $this->companyRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        $this->createdAssetIds = [];
        $this->createdProductIds = [];
        $this->createdWarehouseIds = [];
        $this->createdCompanyIds = [];
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new DispatchedAssetExclusionPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

<?php
/**
 * Property Test: Non-Repairable Item Workflow
 * **Feature: adv-crm-inventory-module, Property 8: Non-Repairable Item Workflow**
 * **Validates: Requirements 7.4**
 * 
 * Property: For any non-repairable item marked as not working, the status SHALL transition
 * to "Scrapped" and the item SHALL be locked from future dispatch.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/RepairService.php';
require_once __DIR__ . '/../services/AssetStatusService.php';
require_once __DIR__ . '/../services/DispatchService.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';

class NonRepairableItemWorkflowTest extends PropertyTestBase {
    private $repairService;
    private $assetStatusService;
    private $dispatchService;
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
        $this->repairService = new RepairService();
        $this->assetStatusService = new AssetStatusService();
        $this->dispatchService = new DispatchService();
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
        echo "\n=== Non-Repairable Item Workflow Property Tests ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 8: Non-Repairable Item Workflow**\n";
        echo "**Validates: Requirements 7.4**\n\n";
        
        $results = [];
        
        // Property 8a: Non-repairable items go to Scrapped
        $results['non_repairable_to_scrapped'] = $this->runPropertyTest(
            'Property 8a: Non-repairable items marked not working go to Scrapped',
            function() {
                return $this->testNonRepairableItemGoesToScrapped();
            },
            30
        );
        
        // Property 8b: Non-repairable items never go to Under Repair
        $results['non_repairable_not_under_repair'] = $this->runPropertyTest(
            'Property 8b: Non-repairable items do not go to Under Repair',
            function() {
                return $this->testNonRepairableItemNotUnderRepair();
            },
            30
        );
        
        // Property 8c: Scrapped items are locked from dispatch
        $results['scrapped_locked_from_dispatch'] = $this->runPropertyTest(
            'Property 8c: Scrapped items are locked from future dispatch',
            function() {
                return $this->testScrappedItemLockedFromDispatch();
            },
            20
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
     * Property 8a: Non-repairable items marked not working go to Scrapped
     */
    private function testNonRepairableItemGoesToScrapped(): array {
        // Create test data with non-repairable product
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(false); // is_repairable = false
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Verify product is NOT repairable
        if ($this->repairService->isProductRepairable($product['id'])) {
            return [
                'success' => false,
                'message' => 'Test product should NOT be repairable',
                'data' => ['product_id' => $product['id']]
            ];
        }
        
        // Mark item as not working using handleNotWorkingItem
        $result = $this->repairService->handleNotWorkingItem($asset['id']);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to handle not working item: ' . $result['message'],
                'data' => ['asset_id' => $asset['id']]
            ];
        }
        
        // Verify asset status is Scrapped
        $updatedAsset = $this->assetRepository->find($asset['id']);
        
        if ($updatedAsset['status'] !== AssetRepository::STATUS_SCRAPPED) {
            return [
                'success' => false,
                'message' => "Non-repairable item should be 'scrapped', got '{$updatedAsset['status']}'",
                'data' => [
                    'asset_id' => $asset['id'],
                    'expected_status' => AssetRepository::STATUS_SCRAPPED,
                    'actual_status' => $updatedAsset['status']
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 8b: Non-repairable items do not go to Under Repair
     */
    private function testNonRepairableItemNotUnderRepair(): array {
        // Create test data with non-repairable product
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(false); // is_repairable = false
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Mark item as not working
        $result = $this->repairService->handleNotWorkingItem($asset['id']);
        
        // Verify asset status is NOT Under Repair
        $updatedAsset = $this->assetRepository->find($asset['id']);
        
        if ($updatedAsset['status'] === AssetRepository::STATUS_UNDER_REPAIR) {
            return [
                'success' => false,
                'message' => 'Non-repairable item should NOT be under_repair',
                'data' => [
                    'asset_id' => $asset['id'],
                    'status' => $updatedAsset['status'],
                    'is_repairable' => false
                ]
            ];
        }
        
        // Also try to initiate repair directly - should fail
        $repairResult = $this->repairService->initiateRepair($asset['id'], [
            'repair_vendor' => 'Test Vendor'
        ]);
        
        // Should be rejected because product is not repairable (or asset is already scrapped)
        if ($repairResult['success']) {
            return [
                'success' => false,
                'message' => 'Repair should not be allowed for non-repairable item',
                'data' => ['asset_id' => $asset['id']]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 8c: Scrapped items are locked from future dispatch
     */
    private function testScrappedItemLockedFromDispatch(): array {
        // Create test data with non-repairable product
        $company = $this->createTestCompany();
        $warehouse1 = $this->createTestWarehouse($company['id']);
        $warehouse2 = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(false); // is_repairable = false
        $asset = $this->createTestAsset($product['id'], $warehouse1['id']);
        
        // Mark item as not working (will be scrapped)
        $scrapResult = $this->repairService->handleNotWorkingItem($asset['id']);
        
        if (!$scrapResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to scrap item: ' . $scrapResult['message'],
                'data' => ['asset_id' => $asset['id']]
            ];
        }
        
        // Verify asset is scrapped
        $scrappedAsset = $this->assetRepository->find($asset['id']);
        if ($scrappedAsset['status'] !== AssetRepository::STATUS_SCRAPPED) {
            return [
                'success' => false,
                'message' => 'Asset should be scrapped',
                'data' => ['status' => $scrappedAsset['status']]
            ];
        }
        
        // Verify canDispatch returns false
        $canDispatch = $this->assetStatusService->canDispatch($asset['id']);
        if ($canDispatch['success']) {
            return [
                'success' => false,
                'message' => 'canDispatch should return false for scrapped item',
                'data' => ['result' => $canDispatch]
            ];
        }
        
        // Try to create dispatch with scrapped asset - should fail
        $dispatchData = [
            'from_warehouse_id' => $warehouse1['id'],
            'to_warehouse_id' => $warehouse2['id'],
            'dispatch_date' => date('Y-m-d')
        ];
        
        $items = [
            [
                'product_id' => $product['id'],
                'asset_id' => $asset['id'],
                'quantity' => 1
            ]
        ];
        
        $dispatchResult = $this->dispatchService->createDispatch($dispatchData, $items);
        
        // Should be rejected
        if ($dispatchResult['success']) {
            return [
                'success' => false,
                'message' => 'Scrapped item should not be accepted for dispatch',
                'data' => [
                    'asset_id' => $asset['id'],
                    'dispatch_id' => $dispatchResult['data']['dispatch']['id'] ?? null
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Create test company
     */
    private function createTestCompany(): array {
        $data = [
            'name' => 'Test Company ' . $this->generateRandomString(8),
            'type' => 'ADV',
            'status' => 'active'
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
    private function createTestProduct(bool $repairable): array {
        $data = [
            'name' => 'Test Product ' . $this->generateRandomString(8),
            'unit_of_measure' => 'unit',
            'inventory_type' => 'INTERNAL',
            'is_serializable' => 1,
            'is_repairable' => $repairable ? 1 : 0,
            'status' => 'active'
        ];
        
        $product = $this->productRepository->create($data);
        $this->createdProductIds[] = $product['id'];
        return $product;
    }
    
    /**
     * Create test asset
     */
    private function createTestAsset(int $productId, int $warehouseId): array {
        $data = [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'serial_number' => 'SN-' . $this->generateRandomString(12),
            'status' => AssetRepository::STATUS_IN_STOCK,
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
     * Clean up test data
     */
    protected function cleanupTestData() {
        foreach ($this->createdAssetIds as $id) {
            try { $this->assetRepository->delete($id); } catch (Exception $e) {}
        }
        foreach ($this->createdProductIds as $id) {
            try { $this->productRepository->delete($id); } catch (Exception $e) {}
        }
        foreach ($this->createdWarehouseIds as $id) {
            try { $this->warehouseRepository->delete($id); } catch (Exception $e) {}
        }
        foreach ($this->createdCompanyIds as $id) {
            try { $this->companyRepository->delete($id); } catch (Exception $e) {}
        }
        
        $this->createdAssetIds = [];
        $this->createdProductIds = [];
        $this->createdWarehouseIds = [];
        $this->createdCompanyIds = [];
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new NonRepairableItemWorkflowTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

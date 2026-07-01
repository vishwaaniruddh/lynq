<?php
/**
 * Property Test: Repairable Item Workflow
 * **Feature: adv-crm-inventory-module, Property 7: Repairable Item Workflow**
 * **Validates: Requirements 7.1**
 * 
 * Property: For any repairable item marked as not working, the status SHALL transition
 * to "Under Repair" (not "Scrapped").
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/RepairService.php';
require_once __DIR__ . '/../services/AssetStatusService.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';

class RepairableItemWorkflowTest extends PropertyTestBase {
    private $repairService;
    private $assetStatusService;
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
        echo "\n=== Repairable Item Workflow Property Tests ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 7: Repairable Item Workflow**\n";
        echo "**Validates: Requirements 7.1**\n\n";
        
        $results = [];
        
        // Property 7: Repairable items go to Under Repair
        $results['repairable_to_under_repair'] = $this->runPropertyTest(
            'Property 7a: Repairable items marked not working go to Under Repair',
            function() {
                return $this->testRepairableItemGoesToUnderRepair();
            },
            30
        );
        
        // Property 7b: Repairable items never go directly to Scrapped when marked not working
        $results['repairable_not_scrapped'] = $this->runPropertyTest(
            'Property 7b: Repairable items do not go to Scrapped when marked not working',
            function() {
                return $this->testRepairableItemNotScrapped();
            },
            30
        );
        
        // Property 7c: Repair workflow completes correctly
        $results['repair_workflow_complete'] = $this->runPropertyTest(
            'Property 7c: Repair workflow returns item to In Stock',
            function() {
                return $this->testRepairWorkflowComplete();
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
     * Property 7a: Repairable items marked not working go to Under Repair
     */
    private function testRepairableItemGoesToUnderRepair(): array {
        // Create test data with repairable product
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true); // is_repairable = true
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Verify product is repairable
        if (!$this->repairService->isProductRepairable($product['id'])) {
            return [
                'success' => false,
                'message' => 'Test product should be repairable',
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
        
        // Verify asset status is Under Repair
        $updatedAsset = $this->assetRepository->find($asset['id']);
        
        if ($updatedAsset['status'] !== AssetRepository::STATUS_UNDER_REPAIR) {
            return [
                'success' => false,
                'message' => "Repairable item should be 'under_repair', got '{$updatedAsset['status']}'",
                'data' => [
                    'asset_id' => $asset['id'],
                    'expected_status' => AssetRepository::STATUS_UNDER_REPAIR,
                    'actual_status' => $updatedAsset['status']
                ]
            ];
        }
        
        // Verify working condition is not_working
        if ($updatedAsset['working_condition'] !== AssetRepository::CONDITION_NOT_WORKING) {
            return [
                'success' => false,
                'message' => "Working condition should be 'not_working'",
                'data' => [
                    'asset_id' => $asset['id'],
                    'working_condition' => $updatedAsset['working_condition']
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 7b: Repairable items do not go to Scrapped when marked not working
     */
    private function testRepairableItemNotScrapped(): array {
        // Create test data with repairable product
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true); // is_repairable = true
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Mark item as not working
        $result = $this->repairService->handleNotWorkingItem($asset['id']);
        
        // Verify asset status is NOT Scrapped
        $updatedAsset = $this->assetRepository->find($asset['id']);
        
        if ($updatedAsset['status'] === AssetRepository::STATUS_SCRAPPED) {
            return [
                'success' => false,
                'message' => 'Repairable item should NOT be scrapped when marked not working',
                'data' => [
                    'asset_id' => $asset['id'],
                    'status' => $updatedAsset['status'],
                    'is_repairable' => true
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 7c: Repair workflow returns item to In Stock
     */
    private function testRepairWorkflowComplete(): array {
        // Create test data with repairable product
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true); // is_repairable = true
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Initiate repair with vendor details
        $repairData = [
            'repair_vendor' => 'Test Vendor ' . $this->generateRandomString(5),
            'estimated_cost' => rand(100, 1000),
            'expected_return_date' => date('Y-m-d', strtotime('+7 days'))
        ];
        
        $initiateResult = $this->repairService->initiateRepair($asset['id'], $repairData);
        
        if (!$initiateResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to initiate repair: ' . $initiateResult['message'],
                'data' => ['asset_id' => $asset['id']]
            ];
        }
        
        $repairId = $initiateResult['data']['repair']['id'];
        
        // Verify asset is Under Repair
        $assetAfterInitiate = $this->assetRepository->find($asset['id']);
        if ($assetAfterInitiate['status'] !== AssetRepository::STATUS_UNDER_REPAIR) {
            return [
                'success' => false,
                'message' => "Asset should be 'under_repair' after initiate",
                'data' => ['status' => $assetAfterInitiate['status']]
            ];
        }
        
        // Complete repair
        $completionData = [
            'actual_cost' => rand(50, 500),
            'resolution' => 'Repaired successfully'
        ];
        
        $completeResult = $this->repairService->completeRepair($repairId, $completionData);
        
        if (!$completeResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to complete repair: ' . $completeResult['message'],
                'data' => ['repair_id' => $repairId]
            ];
        }
        
        // Verify asset is back In Stock
        $assetAfterComplete = $this->assetRepository->find($asset['id']);
        
        if ($assetAfterComplete['status'] !== AssetRepository::STATUS_IN_STOCK) {
            return [
                'success' => false,
                'message' => "Asset should be 'in_stock' after repair completion",
                'data' => [
                    'expected_status' => AssetRepository::STATUS_IN_STOCK,
                    'actual_status' => $assetAfterComplete['status']
                ]
            ];
        }
        
        // Verify working condition is working
        if ($assetAfterComplete['working_condition'] !== AssetRepository::CONDITION_WORKING) {
            return [
                'success' => false,
                'message' => "Working condition should be 'working' after repair",
                'data' => ['working_condition' => $assetAfterComplete['working_condition']]
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
    $test = new RepairableItemWorkflowTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

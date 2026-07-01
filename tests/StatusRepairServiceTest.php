<?php
/**
 * Unit Tests for Status and Repair Services
 * Tests valid status transitions, repair workflow steps, and scrap workflow
 * 
 * Requirements: 6.1, 7.1, 7.4
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/AssetStatusService.php';
require_once __DIR__ . '/../services/RepairService.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../repositories/RepairRepository.php';

class StatusRepairServiceTest {
    private $assetStatusService;
    private $repairService;
    private $assetRepository;
    private $productRepository;
    private $warehouseRepository;
    private $companyRepository;
    private $repairRepository;
    private $createdAssetIds = [];
    private $createdProductIds = [];
    private $createdWarehouseIds = [];
    private $createdCompanyIds = [];
    private $createdRepairIds = [];
    private $testResults = [];
    
    public function __construct() {
        $this->assetStatusService = new AssetStatusService();
        $this->repairService = new RepairService();
        $this->assetRepository = new AssetRepository();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->companyRepository = new CompanyRepository();
        $this->companyRepository->disableCompanyFilter();
        $this->repairRepository = new RepairRepository();
    }
    
    /**
     * Run all unit tests
     */
    public function runTests() {
        echo "\n=== Status and Repair Service Unit Tests ===\n";
        echo "Requirements: 6.1, 7.1, 7.4\n\n";
        
        // Status Transition Tests (Requirement 6.1)
        $this->runTest('testValidStatusTransitions', 'Valid status transitions are accepted');
        $this->runTest('testInvalidStatusTransitions', 'Invalid status transitions are rejected');
        $this->runTest('testLockedStatusCannotChange', 'Locked statuses (lost/scrapped) cannot change');
        
        // Repair Workflow Tests (Requirement 7.1)
        $this->runTest('testInitiateRepairForRepairableItem', 'Initiate repair for repairable item');
        $this->runTest('testInitiateRepairForNonRepairableItem', 'Initiate repair rejected for non-repairable item');
        $this->runTest('testCompleteRepairReturnsToStock', 'Complete repair returns item to stock');
        $this->runTest('testCancelRepair', 'Cancel repair workflow');
        
        // Scrap Workflow Tests (Requirement 7.4)
        $this->runTest('testScrapAsset', 'Scrap asset workflow');
        $this->runTest('testScrapLockedAsset', 'Cannot scrap already locked asset');
        
        // Working Condition Tests
        $this->runTest('testUpdateWorkingCondition', 'Update working condition');
        
        // Cleanup
        $this->cleanupTestData();
        
        // Summary
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($this->testResults));
        $total = count($this->testResults);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }
    
    /**
     * Run a single test
     */
    private function runTest($method, $description) {
        try {
            $result = $this->$method();
            $this->testResults[$method] = $result;
            echo ($result ? "✓" : "✗") . " $description\n";
            if (!$result) {
                echo "  Failed in: $method\n";
            }
        } catch (Exception $e) {
            $this->testResults[$method] = false;
            echo "✗ $description\n";
            echo "  Exception: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Test valid status transitions
     */
    private function testValidStatusTransitions(): bool {
        // Test in_stock -> dispatched (valid)
        $result1 = $this->assetStatusService->validateStatusTransition(
            AssetRepository::STATUS_IN_STOCK,
            AssetRepository::STATUS_DISPATCHED
        );
        if (!$result1['success']) return false;
        
        // Test dispatched -> returned (valid)
        $result2 = $this->assetStatusService->validateStatusTransition(
            AssetRepository::STATUS_DISPATCHED,
            AssetRepository::STATUS_RETURNED
        );
        if (!$result2['success']) return false;
        
        // Test in_use -> under_repair (valid)
        $result3 = $this->assetStatusService->validateStatusTransition(
            AssetRepository::STATUS_IN_USE,
            AssetRepository::STATUS_UNDER_REPAIR
        );
        if (!$result3['success']) return false;
        
        // Test under_repair -> in_stock (valid)
        $result4 = $this->assetStatusService->validateStatusTransition(
            AssetRepository::STATUS_UNDER_REPAIR,
            AssetRepository::STATUS_IN_STOCK
        );
        if (!$result4['success']) return false;
        
        return true;
    }
    
    /**
     * Test invalid status transitions
     */
    private function testInvalidStatusTransitions(): bool {
        // Test scrapped -> in_stock (invalid - scrapped is terminal)
        $result1 = $this->assetStatusService->validateStatusTransition(
            AssetRepository::STATUS_SCRAPPED,
            AssetRepository::STATUS_IN_STOCK
        );
        if ($result1['success']) return false;
        
        // Test lost -> dispatched (invalid - lost is terminal)
        $result2 = $this->assetStatusService->validateStatusTransition(
            AssetRepository::STATUS_LOST,
            AssetRepository::STATUS_DISPATCHED
        );
        if ($result2['success']) return false;
        
        // Test in_use -> in_stock (invalid - must go through returned)
        $result3 = $this->assetStatusService->validateStatusTransition(
            AssetRepository::STATUS_IN_USE,
            AssetRepository::STATUS_IN_STOCK
        );
        if ($result3['success']) return false;
        
        return true;
    }
    
    /**
     * Test locked statuses cannot change
     */
    private function testLockedStatusCannotChange(): bool {
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true);
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Mark as lost
        $this->assetStatusService->markAsLost($asset['id']);
        
        // Try to change status
        $result = $this->assetStatusService->updateStatus($asset['id'], AssetRepository::STATUS_IN_STOCK);
        
        // Should be rejected
        return !$result['success'] && $result['code'] === 'ASSET_LOCKED';
    }
    
    /**
     * Test initiate repair for repairable item
     */
    private function testInitiateRepairForRepairableItem(): bool {
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true); // repairable
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        $repairData = [
            'repair_vendor' => 'Test Vendor',
            'estimated_cost' => 100.00,
            'expected_return_date' => date('Y-m-d', strtotime('+7 days'))
        ];
        
        $result = $this->repairService->initiateRepair($asset['id'], $repairData);
        
        if (!$result['success']) return false;
        
        // Verify asset status changed to under_repair
        $updatedAsset = $this->assetRepository->find($asset['id']);
        if ($updatedAsset['status'] !== AssetRepository::STATUS_UNDER_REPAIR) return false;
        
        // Verify repair record created
        if (!isset($result['data']['repair']['id'])) return false;
        $this->createdRepairIds[] = $result['data']['repair']['id'];
        
        return true;
    }
    
    /**
     * Test initiate repair rejected for non-repairable item
     */
    private function testInitiateRepairForNonRepairableItem(): bool {
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(false); // NOT repairable
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        $repairData = [
            'repair_vendor' => 'Test Vendor',
            'estimated_cost' => 100.00
        ];
        
        $result = $this->repairService->initiateRepair($asset['id'], $repairData);
        
        // Should be rejected
        return !$result['success'] && $result['code'] === 'NOT_REPAIRABLE';
    }
    
    /**
     * Test complete repair returns item to stock
     */
    private function testCompleteRepairReturnsToStock(): bool {
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true);
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Initiate repair
        $repairData = [
            'repair_vendor' => 'Test Vendor',
            'estimated_cost' => 100.00
        ];
        
        $initiateResult = $this->repairService->initiateRepair($asset['id'], $repairData);
        if (!$initiateResult['success']) return false;
        
        $repairId = $initiateResult['data']['repair']['id'];
        $this->createdRepairIds[] = $repairId;
        
        // Complete repair
        $completionData = [
            'actual_cost' => 80.00,
            'resolution' => 'Fixed successfully'
        ];
        
        $completeResult = $this->repairService->completeRepair($repairId, $completionData);
        if (!$completeResult['success']) return false;
        
        // Verify asset status is in_stock
        $updatedAsset = $this->assetRepository->find($asset['id']);
        if ($updatedAsset['status'] !== AssetRepository::STATUS_IN_STOCK) return false;
        
        // Verify working condition is working
        if ($updatedAsset['working_condition'] !== AssetRepository::CONDITION_WORKING) return false;
        
        return true;
    }
    
    /**
     * Test cancel repair
     */
    private function testCancelRepair(): bool {
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true);
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Initiate repair
        $repairData = [
            'repair_vendor' => 'Test Vendor',
            'estimated_cost' => 100.00
        ];
        
        $initiateResult = $this->repairService->initiateRepair($asset['id'], $repairData);
        if (!$initiateResult['success']) return false;
        
        $repairId = $initiateResult['data']['repair']['id'];
        $this->createdRepairIds[] = $repairId;
        
        // Cancel repair
        $cancelResult = $this->repairService->cancelRepair($repairId, null, 'Test cancellation');
        if (!$cancelResult['success']) return false;
        
        // Verify repair status is cancelled
        $repair = $this->repairRepository->find($repairId);
        if ($repair['status'] !== RepairRepository::STATUS_CANCELLED) return false;
        
        return true;
    }
    
    /**
     * Test scrap asset
     */
    private function testScrapAsset(): bool {
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(false); // non-repairable
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        $result = $this->repairService->scrapAsset($asset['id'], null, 'Test scrap reason');
        
        if (!$result['success']) return false;
        
        // Verify asset status is scrapped
        $updatedAsset = $this->assetRepository->find($asset['id']);
        if ($updatedAsset['status'] !== AssetRepository::STATUS_SCRAPPED) return false;
        
        return true;
    }
    
    /**
     * Test cannot scrap already locked asset
     */
    private function testScrapLockedAsset(): bool {
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true);
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Mark as lost first
        $this->assetStatusService->markAsLost($asset['id']);
        
        // Try to scrap
        $result = $this->repairService->scrapAsset($asset['id']);
        
        // Should be rejected
        return !$result['success'] && $result['code'] === 'ASSET_LOST';
    }
    
    /**
     * Test update working condition
     */
    private function testUpdateWorkingCondition(): bool {
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true);
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Update to not_working
        $result = $this->assetStatusService->updateWorkingCondition(
            $asset['id'],
            AssetRepository::CONDITION_NOT_WORKING
        );
        
        if (!$result['success']) return false;
        
        // Verify condition changed
        $updatedAsset = $this->assetRepository->find($asset['id']);
        if ($updatedAsset['working_condition'] !== AssetRepository::CONDITION_NOT_WORKING) return false;
        
        // Update back to working
        $result2 = $this->assetStatusService->updateWorkingCondition(
            $asset['id'],
            AssetRepository::CONDITION_WORKING
        );
        
        if (!$result2['success']) return false;
        
        return true;
    }
    
    /**
     * Helper: Generate random string
     */
    private function generateRandomString($length = 8): string {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, $length);
    }
    
    /**
     * Create test company
     */
    private function createTestCompany(): array {
        $data = [
            'name' => 'Test Company ' . $this->generateRandomString(),
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
            'name' => 'Test Warehouse ' . $this->generateRandomString(),
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
            'name' => 'Test Product ' . $this->generateRandomString(),
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
    private function cleanupTestData() {
        foreach ($this->createdRepairIds as $id) {
            try { $this->repairRepository->delete($id); } catch (Exception $e) {}
        }
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
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new StatusRepairServiceTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

<?php
/**
 * Integration Tests for Status and Repair APIs
 * Tests status update flows and repair workflow
 * 
 * Requirements: 6.1, 7.2
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/RepairRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../services/AssetStatusService.php';
require_once __DIR__ . '/../services/RepairService.php';
require_once __DIR__ . '/../services/StockService.php';

class StatusRepairApiTest extends PropertyTestBase {
    
    private $warehouseRepository;
    private $productRepository;
    private $assetRepository;
    private $repairRepository;
    private $companyRepository;
    private $assetStatusService;
    private $repairService;
    private $stockService;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->productRepository = new ProductRepository();
        $this->assetRepository = new AssetRepository();
        $this->repairRepository = new RepairRepository();
        $this->companyRepository = new CompanyRepository();
        $this->companyRepository->disableCompanyFilter();
        $this->assetStatusService = new AssetStatusService();
        $this->repairService = new RepairService();
        $this->stockService = new StockService();
    }
    
    public function runTests() {
        echo "=== Status and Repair API Integration Tests ===\n\n";
        
        $allPassed = true;
        
        // Status update flow tests (Requirement 6.1)
        $allPassed &= $this->testStatusUpdateToDispatched();
        $allPassed &= $this->testStatusUpdateToInUse();
        $allPassed &= $this->testStatusUpdateToReturned();
        $allPassed &= $this->testStatusUpdateToUnderRepair();
        $allPassed &= $this->testInvalidStatusTransitionRejected();
        $allPassed &= $this->testLostStatusLock();
        $allPassed &= $this->testScrappedStatusLock();
        
        // Repair workflow tests (Requirement 7.2)
        $allPassed &= $this->testCreateRepairRequest();
        $allPassed &= $this->testCompleteRepair();
        $allPassed &= $this->testRepairForNonRepairableRejected();
        $allPassed &= $this->testRepairCostTracking();
        $allPassed &= $this->testRepairHistoryRetrieval();
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Test status update to dispatched
     * Requirements: 6.1
     */
    public function testStatusUpdateToDispatched() {
        echo "Testing status update to dispatched... ";
        
        try {
            if (!$this->tableExists('assets')) {
                echo "SKIPPED (assets table not found)\n";
                return true;
            }
            
            $asset = $this->createTestAsset(true);
            if (!$asset) {
                echo "SKIPPED (could not create test asset)\n";
                return true;
            }
            
            // Update status to dispatched
            $result = $this->assetStatusService->updateStatus(
                $asset['id'],
                AssetRepository::STATUS_DISPATCHED
            );
            
            $this->assert($result['success'], "Status update should succeed: " . ($result['message'] ?? ''));
            
            // Verify status changed
            $updatedAsset = $this->assetRepository->find($asset['id']);
            $this->assert(
                $updatedAsset['status'] === AssetRepository::STATUS_DISPATCHED,
                "Status should be 'dispatched'"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test status update to in_use
     * Requirements: 6.1
     */
    public function testStatusUpdateToInUse() {
        echo "Testing status update to in_use... ";
        
        try {
            if (!$this->tableExists('assets')) {
                echo "SKIPPED (assets table not found)\n";
                return true;
            }
            
            $asset = $this->createTestAsset(true);
            if (!$asset) {
                echo "SKIPPED (could not create test asset)\n";
                return true;
            }
            
            // First dispatch the asset
            $this->assetStatusService->updateStatus($asset['id'], AssetRepository::STATUS_DISPATCHED);
            
            // Then assign it
            $this->assetStatusService->updateStatus($asset['id'], AssetRepository::STATUS_ASSIGNED);
            
            // Now update to in_use
            $result = $this->assetStatusService->updateStatus(
                $asset['id'],
                AssetRepository::STATUS_IN_USE
            );
            
            $this->assert($result['success'], "Status update should succeed: " . ($result['message'] ?? ''));
            
            // Verify status changed
            $updatedAsset = $this->assetRepository->find($asset['id']);
            $this->assert(
                $updatedAsset['status'] === AssetRepository::STATUS_IN_USE,
                "Status should be 'in_use'"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test status update to returned
     * Requirements: 6.1
     */
    public function testStatusUpdateToReturned() {
        echo "Testing status update to returned... ";
        
        try {
            if (!$this->tableExists('assets')) {
                echo "SKIPPED (assets table not found)\n";
                return true;
            }
            
            $asset = $this->createTestAsset(true);
            if (!$asset) {
                echo "SKIPPED (could not create test asset)\n";
                return true;
            }
            
            // First dispatch and assign the asset
            $this->assetStatusService->updateStatus($asset['id'], AssetRepository::STATUS_DISPATCHED);
            $this->assetStatusService->updateStatus($asset['id'], AssetRepository::STATUS_ASSIGNED);
            $this->assetStatusService->updateStatus($asset['id'], AssetRepository::STATUS_IN_USE);
            
            // Now return it
            $result = $this->assetStatusService->updateStatus(
                $asset['id'],
                AssetRepository::STATUS_RETURNED
            );
            
            $this->assert($result['success'], "Status update should succeed: " . ($result['message'] ?? ''));
            
            // Verify status changed
            $updatedAsset = $this->assetRepository->find($asset['id']);
            $this->assert(
                $updatedAsset['status'] === AssetRepository::STATUS_RETURNED,
                "Status should be 'returned'"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test status update to under_repair
     * Requirements: 6.1
     */
    public function testStatusUpdateToUnderRepair() {
        echo "Testing status update to under_repair... ";
        
        try {
            if (!$this->tableExists('assets')) {
                echo "SKIPPED (assets table not found)\n";
                return true;
            }
            
            $asset = $this->createTestAsset(true);
            if (!$asset) {
                echo "SKIPPED (could not create test asset)\n";
                return true;
            }
            
            // Update status to under_repair
            $result = $this->assetStatusService->updateStatus(
                $asset['id'],
                AssetRepository::STATUS_UNDER_REPAIR
            );
            
            $this->assert($result['success'], "Status update should succeed: " . ($result['message'] ?? ''));
            
            // Verify status changed
            $updatedAsset = $this->assetRepository->find($asset['id']);
            $this->assert(
                $updatedAsset['status'] === AssetRepository::STATUS_UNDER_REPAIR,
                "Status should be 'under_repair'"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test invalid status transition is rejected
     * Requirements: 6.1
     */
    public function testInvalidStatusTransitionRejected() {
        echo "Testing invalid status transition rejection... ";
        
        try {
            if (!$this->tableExists('assets')) {
                echo "SKIPPED (assets table not found)\n";
                return true;
            }
            
            $asset = $this->createTestAsset(true);
            if (!$asset) {
                echo "SKIPPED (could not create test asset)\n";
                return true;
            }
            
            // First put asset in "in_use" status (valid path: in_stock -> dispatched -> assigned -> in_use)
            $this->assetStatusService->updateStatus($asset['id'], AssetRepository::STATUS_DISPATCHED);
            $this->assetStatusService->updateStatus($asset['id'], AssetRepository::STATUS_ASSIGNED);
            $this->assetStatusService->updateStatus($asset['id'], AssetRepository::STATUS_IN_USE);
            
            // Try invalid transition: in_use -> in_stock (should go through returned first)
            $result = $this->assetStatusService->updateStatus(
                $asset['id'],
                AssetRepository::STATUS_IN_STOCK
            );
            
            // This should fail - in_use can only go to: returned, under_repair, lost
            $this->assert(!$result['success'], "Invalid status transition should be rejected");
            $this->assert(
                $result['code'] === 'INVALID_TRANSITION',
                "Error code should be INVALID_TRANSITION, got: " . ($result['code'] ?? 'none')
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test lost status locks the asset
     * Requirements: 6.1
     */
    public function testLostStatusLock() {
        echo "Testing lost status lock... ";
        
        try {
            if (!$this->tableExists('assets')) {
                echo "SKIPPED (assets table not found)\n";
                return true;
            }
            
            $asset = $this->createTestAsset(true);
            if (!$asset) {
                echo "SKIPPED (could not create test asset)\n";
                return true;
            }
            
            // Mark as lost
            $lostResult = $this->assetStatusService->markAsLost($asset['id']);
            $this->assert($lostResult['success'], "Marking as lost should succeed");
            
            // Try to change status
            $result = $this->assetStatusService->updateStatus(
                $asset['id'],
                AssetRepository::STATUS_IN_STOCK
            );
            
            // Should be rejected
            $this->assert(!$result['success'], "Status change should be rejected for lost asset");
            $this->assert(
                $result['code'] === 'ASSET_LOCKED',
                "Error code should be ASSET_LOCKED"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test scrapped status locks the asset
     * Requirements: 6.1
     */
    public function testScrappedStatusLock() {
        echo "Testing scrapped status lock... ";
        
        try {
            if (!$this->tableExists('assets')) {
                echo "SKIPPED (assets table not found)\n";
                return true;
            }
            
            // Create non-repairable asset
            $asset = $this->createTestAsset(false);
            if (!$asset) {
                echo "SKIPPED (could not create test asset)\n";
                return true;
            }
            
            // Scrap the asset
            $scrapResult = $this->repairService->scrapAsset($asset['id']);
            $this->assert($scrapResult['success'], "Scrapping should succeed");
            
            // Try to change status
            $result = $this->assetStatusService->updateStatus(
                $asset['id'],
                AssetRepository::STATUS_IN_STOCK
            );
            
            // Should be rejected
            $this->assert(!$result['success'], "Status change should be rejected for scrapped asset");
            $this->assert(
                $result['code'] === 'ASSET_LOCKED',
                "Error code should be ASSET_LOCKED"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }

    
    /**
     * Test create repair request
     * Requirements: 7.2
     */
    public function testCreateRepairRequest() {
        echo "Testing create repair request... ";
        
        try {
            if (!$this->tableExists('repairs') || !$this->tableExists('assets')) {
                echo "SKIPPED (required tables not found)\n";
                return true;
            }
            
            // Create repairable asset
            $asset = $this->createTestAsset(true);
            if (!$asset) {
                echo "SKIPPED (could not create test asset)\n";
                return true;
            }
            
            $userId = $this->getTestUserId();
            
            $repairData = [
                'repair_vendor' => 'Test Repair Vendor ' . $this->generateRandomString(6),
                'estimated_cost' => 150.00,
                'send_date' => date('Y-m-d'),
                'expected_return_date' => date('Y-m-d', strtotime('+14 days')),
                'notes' => 'Test repair request'
            ];
            
            $result = $this->repairService->initiateRepair($asset['id'], $repairData, $userId);
            
            $this->assert($result['success'], "Repair creation should succeed: " . ($result['message'] ?? ''));
            $this->assert(isset($result['data']['repair']['id']), "Repair should have an ID");
            
            $this->createdRecords['repairs'][] = $result['data']['repair']['id'];
            
            // Verify asset status changed to under_repair
            $updatedAsset = $this->assetRepository->find($asset['id']);
            $this->assert(
                $updatedAsset['status'] === AssetRepository::STATUS_UNDER_REPAIR,
                "Asset status should be 'under_repair'"
            );
            
            // Verify repair record details
            $repair = $this->repairRepository->find($result['data']['repair']['id']);
            $this->assert($repair['repair_vendor'] === $repairData['repair_vendor'], "Repair vendor should match");
            $this->assert((float)$repair['estimated_cost'] === $repairData['estimated_cost'], "Estimated cost should match");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test complete repair
     * Requirements: 7.2
     */
    public function testCompleteRepair() {
        echo "Testing complete repair... ";
        
        try {
            if (!$this->tableExists('repairs') || !$this->tableExists('assets')) {
                echo "SKIPPED (required tables not found)\n";
                return true;
            }
            
            // Create repairable asset
            $asset = $this->createTestAsset(true);
            if (!$asset) {
                echo "SKIPPED (could not create test asset)\n";
                return true;
            }
            
            $userId = $this->getTestUserId();
            
            // Create repair request
            $repairData = [
                'repair_vendor' => 'Test Vendor',
                'estimated_cost' => 100.00
            ];
            
            $createResult = $this->repairService->initiateRepair($asset['id'], $repairData, $userId);
            $this->assert($createResult['success'], "Repair creation should succeed");
            
            $repairId = $createResult['data']['repair']['id'];
            $this->createdRecords['repairs'][] = $repairId;
            
            // Complete the repair
            $completionData = [
                'actual_cost' => 85.00,
                'resolution' => 'Replaced faulty component'
            ];
            
            $completeResult = $this->repairService->completeRepair($repairId, $completionData, $userId);
            
            $this->assert($completeResult['success'], "Repair completion should succeed: " . ($completeResult['message'] ?? ''));
            
            // Verify asset status returned to in_stock
            $updatedAsset = $this->assetRepository->find($asset['id']);
            $this->assert(
                $updatedAsset['status'] === AssetRepository::STATUS_IN_STOCK,
                "Asset status should be 'in_stock' after repair"
            );
            
            // Verify working condition is working
            $this->assert(
                $updatedAsset['working_condition'] === AssetRepository::CONDITION_WORKING,
                "Working condition should be 'working' after repair"
            );
            
            // Verify repair record updated
            $repair = $this->repairRepository->find($repairId);
            $this->assert($repair['status'] === RepairRepository::STATUS_COMPLETED, "Repair status should be 'completed'");
            $this->assert((float)$repair['actual_cost'] === $completionData['actual_cost'], "Actual cost should be recorded");
            $this->assert(!empty($repair['actual_return_date']), "Return date should be recorded");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test repair for non-repairable item is rejected
     * Requirements: 7.2
     */
    public function testRepairForNonRepairableRejected() {
        echo "Testing repair for non-repairable item rejection... ";
        
        try {
            if (!$this->tableExists('repairs') || !$this->tableExists('assets')) {
                echo "SKIPPED (required tables not found)\n";
                return true;
            }
            
            // Create non-repairable asset
            $asset = $this->createTestAsset(false);
            if (!$asset) {
                echo "SKIPPED (could not create test asset)\n";
                return true;
            }
            
            $repairData = [
                'repair_vendor' => 'Test Vendor',
                'estimated_cost' => 100.00
            ];
            
            $result = $this->repairService->initiateRepair($asset['id'], $repairData);
            
            // Should be rejected
            $this->assert(!$result['success'], "Repair should be rejected for non-repairable item");
            $this->assert(
                $result['code'] === 'NOT_REPAIRABLE',
                "Error code should be NOT_REPAIRABLE"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test repair cost tracking
     * Requirements: 7.2
     */
    public function testRepairCostTracking() {
        echo "Testing repair cost tracking... ";
        
        try {
            if (!$this->tableExists('repairs') || !$this->tableExists('assets')) {
                echo "SKIPPED (required tables not found)\n";
                return true;
            }
            
            // Create repairable asset
            $asset = $this->createTestAsset(true);
            if (!$asset) {
                echo "SKIPPED (could not create test asset)\n";
                return true;
            }
            
            $userId = $this->getTestUserId();
            
            // Create first repair
            $repair1Data = ['repair_vendor' => 'Vendor 1', 'estimated_cost' => 100.00];
            $result1 = $this->repairService->initiateRepair($asset['id'], $repair1Data, $userId);
            $this->assert($result1['success'], "First repair should be created");
            $this->createdRecords['repairs'][] = $result1['data']['repair']['id'];
            
            // Complete first repair
            $this->repairService->completeRepair($result1['data']['repair']['id'], ['actual_cost' => 80.00], $userId);
            
            // Create second repair
            $repair2Data = ['repair_vendor' => 'Vendor 2', 'estimated_cost' => 200.00];
            $result2 = $this->repairService->initiateRepair($asset['id'], $repair2Data, $userId);
            $this->assert($result2['success'], "Second repair should be created");
            $this->createdRecords['repairs'][] = $result2['data']['repair']['id'];
            
            // Complete second repair
            $this->repairService->completeRepair($result2['data']['repair']['id'], ['actual_cost' => 150.00], $userId);
            
            // Get total repair cost
            $totalCost = $this->repairService->getTotalRepairCost($asset['id']);
            
            $this->assert($totalCost === 230.00, "Total repair cost should be 230.00 (80 + 150), got: $totalCost");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test repair history retrieval
     * Requirements: 7.2
     */
    public function testRepairHistoryRetrieval() {
        echo "Testing repair history retrieval... ";
        
        try {
            if (!$this->tableExists('repairs') || !$this->tableExists('assets')) {
                echo "SKIPPED (required tables not found)\n";
                return true;
            }
            
            // Create repairable asset
            $asset = $this->createTestAsset(true);
            if (!$asset) {
                echo "SKIPPED (could not create test asset)\n";
                return true;
            }
            
            $userId = $this->getTestUserId();
            
            // Create and complete multiple repairs
            for ($i = 1; $i <= 3; $i++) {
                $repairData = [
                    'repair_vendor' => "Vendor $i",
                    'estimated_cost' => $i * 50.00
                ];
                
                $result = $this->repairService->initiateRepair($asset['id'], $repairData, $userId);
                if ($result['success']) {
                    $this->createdRecords['repairs'][] = $result['data']['repair']['id'];
                    $this->repairService->completeRepair(
                        $result['data']['repair']['id'],
                        ['actual_cost' => $i * 40.00],
                        $userId
                    );
                }
            }
            
            // Get repair history
            $history = $this->repairService->getAssetRepairs($asset['id']);
            
            $this->assert(count($history) >= 3, "Should have at least 3 repair records");
            
            // Verify history contains expected data
            foreach ($history as $repair) {
                $this->assert(isset($repair['repair_vendor']), "Repair should have vendor");
                $this->assert(isset($repair['status']), "Repair should have status");
            }
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Get a valid test user ID from the database
     */
    private function getTestUserId() {
        try {
            if (!$this->tableExists('users')) {
                return null;
            }
            
            $sql = "SELECT id FROM users WHERE status = 1 LIMIT 1";
            $result = $this->getResults($sql);
            
            return !empty($result) ? $result[0]['id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Create test asset
     */
    private function createTestAsset($repairable = true) {
        try {
            // Create company first
            $company = $this->createTestCompany();
            if (!$company) {
                return null;
            }
            
            // Create warehouse
            $warehouse = $this->createTestWarehouse($company['id']);
            if (!$warehouse) {
                return null;
            }
            
            // Create product
            $product = $this->createTestProduct($repairable);
            if (!$product) {
                return null;
            }
            
            // Create asset
            $serialNumber = 'SN-TEST-' . $this->generateRandomString(12);
            $assetData = [
                'product_id' => $product['id'],
                'warehouse_id' => $warehouse['id'],
                'serial_number' => $serialNumber,
                'status' => AssetRepository::STATUS_IN_STOCK,
                'working_condition' => AssetRepository::CONDITION_WORKING,
                'current_holder_type' => AssetRepository::HOLDER_WAREHOUSE,
                'current_holder_id' => $warehouse['id'],
                'source_warehouse_id' => $warehouse['id']
            ];
            
            $asset = $this->assetRepository->create($assetData);
            $this->createdRecords['assets'][] = $asset['id'];
            
            return $asset;
            
        } catch (Exception $e) {
            error_log("Failed to create test asset: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create test company
     */
    private function createTestCompany() {
        try {
            if (!$this->tableExists('companies')) {
                return null;
            }
            
            $companyData = [
                'name' => 'Test Company ' . $this->generateRandomString(8),
                'type' => 'ADV',
                'status' => 'active'
            ];
            
            $company = $this->companyRepository->create($companyData);
            $this->createdRecords['companies'][] = $company['id'];
            
            return $company;
            
        } catch (Exception $e) {
            error_log("Failed to create test company: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create test warehouse
     */
    private function createTestWarehouse($companyId) {
        try {
            if (!$this->tableExists('warehouses')) {
                return null;
            }
            
            $warehouseData = [
                'name' => 'Test Warehouse ' . $this->generateRandomString(8),
                'location' => 'Test Location',
                'company_id' => $companyId,
                'status' => WarehouseRepository::STATUS_ACTIVE
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
     * Create test product
     */
    private function createTestProduct($repairable = true) {
        try {
            if (!$this->tableExists('products')) {
                return null;
            }
            
            $productData = [
                'name' => 'Test Product ' . $this->generateRandomString(8),
                'unit_of_measure' => 'unit',
                'inventory_type' => ProductRepository::TYPE_INTERNAL,
                'is_serializable' => 1,
                'is_repairable' => $repairable ? 1 : 0,
                'status' => ProductRepository::STATUS_ACTIVE
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
     * Check if table exists
     */
    private function tableExists($tableName) {
        try {
            $result = $this->db->query("SHOW TABLES LIKE '$tableName'");
            return $result && $result->num_rows > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Clean up test data
     */
    public function cleanupTestData() {
        try {
            // Delete in reverse order of dependencies
            
            // Delete repairs
            if (isset($this->createdRecords['repairs']) && !empty($this->createdRecords['repairs'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['repairs']));
                $this->db->query("DELETE FROM `repairs` WHERE id IN ($ids)");
            }
            
            // Delete assets
            if (isset($this->createdRecords['assets']) && !empty($this->createdRecords['assets'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['assets']));
                $this->db->query("DELETE FROM `assets` WHERE id IN ($ids)");
            }
            
            // Delete products
            if (isset($this->createdRecords['products']) && !empty($this->createdRecords['products'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM `products` WHERE id IN ($ids)");
            }
            
            // Delete warehouses
            if (isset($this->createdRecords['warehouses']) && !empty($this->createdRecords['warehouses'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['warehouses']));
                $this->db->query("DELETE FROM `warehouses` WHERE id IN ($ids)");
            }
            
            // Delete companies
            if (isset($this->createdRecords['companies']) && !empty($this->createdRecords['companies'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['companies']));
                $this->db->query("DELETE FROM `companies` WHERE id IN ($ids)");
            }
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}


// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $test = new StatusRepairApiTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}

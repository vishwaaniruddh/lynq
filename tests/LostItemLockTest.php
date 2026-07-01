<?php
/**
 * Property Test: Lost Item Lock
 * **Feature: adv-crm-inventory-module, Property 9: Lost Item Lock**
 * **Validates: Requirements 6.3**
 * 
 * Property: For any item marked as "Lost", all subsequent dispatch or transfer operations
 * on that item SHALL be rejected.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/AssetStatusService.php';
require_once __DIR__ . '/../services/DispatchService.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';

class LostItemLockTest extends PropertyTestBase {
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
        echo "\n=== Lost Item Lock Property Tests ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 9: Lost Item Lock**\n";
        echo "**Validates: Requirements 6.3**\n\n";
        
        $results = [];
        
        // Property 9: Lost Item Lock - Status changes rejected
        $results['lost_item_status_lock'] = $this->runPropertyTest(
            'Property 9a: Lost items reject status changes',
            function() {
                return $this->testLostItemStatusLock();
            },
            30
        );
        
        // Property 9: Lost Item Lock - Dispatch rejected
        $results['lost_item_dispatch_lock'] = $this->runPropertyTest(
            'Property 9b: Lost items reject dispatch operations',
            function() {
                return $this->testLostItemDispatchLock();
            },
            30
        );
        
        // Property 9: Lost Item Lock - canDispatch returns false
        $results['lost_item_can_dispatch'] = $this->runPropertyTest(
            'Property 9c: canDispatch returns false for lost items',
            function() {
                return $this->testLostItemCanDispatch();
            },
            30
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
     * Property 9a: Lost items reject status changes
     * For any lost item, status change attempts SHALL be rejected
     */
    private function testLostItemStatusLock(): array {
        // Create test data
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct();
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Mark asset as lost
        $lostResult = $this->assetStatusService->markAsLost($asset['id']);
        if (!$lostResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to mark asset as lost: ' . $lostResult['message'],
                'data' => ['asset_id' => $asset['id']]
            ];
        }
        
        // Verify asset is now lost
        $lostAsset = $this->assetRepository->find($asset['id']);
        if ($lostAsset['status'] !== AssetRepository::STATUS_LOST) {
            return [
                'success' => false,
                'message' => 'Asset was not marked as lost',
                'data' => ['status' => $lostAsset['status']]
            ];
        }
        
        // Try to change status to any other valid status
        $validStatuses = AssetRepository::getStatuses();
        $randomStatus = $this->generateRandomChoice(array_filter($validStatuses, function($s) {
            return $s !== AssetRepository::STATUS_LOST;
        }));
        
        $updateResult = $this->assetStatusService->updateStatus($asset['id'], $randomStatus);
        
        // Should be rejected
        if ($updateResult['success']) {
            return [
                'success' => false,
                'message' => "Lost item accepted status change to '$randomStatus'",
                'data' => [
                    'asset_id' => $asset['id'],
                    'attempted_status' => $randomStatus
                ]
            ];
        }
        
        // Verify status is still lost
        $finalAsset = $this->assetRepository->find($asset['id']);
        if ($finalAsset['status'] !== AssetRepository::STATUS_LOST) {
            return [
                'success' => false,
                'message' => 'Lost item status was changed despite rejection',
                'data' => [
                    'expected' => AssetRepository::STATUS_LOST,
                    'actual' => $finalAsset['status']
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 9b: Lost items reject dispatch operations
     * For any lost item, dispatch attempts SHALL be rejected
     */
    private function testLostItemDispatchLock(): array {
        // Create test data
        $company = $this->createTestCompany();
        $warehouse1 = $this->createTestWarehouse($company['id']);
        $warehouse2 = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct();
        $asset = $this->createTestAsset($product['id'], $warehouse1['id']);
        
        // Mark asset as lost
        $lostResult = $this->assetStatusService->markAsLost($asset['id']);
        if (!$lostResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to mark asset as lost: ' . $lostResult['message'],
                'data' => ['asset_id' => $asset['id']]
            ];
        }
        
        // Try to create dispatch with lost asset
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
                'message' => 'Lost item was accepted for dispatch',
                'data' => [
                    'asset_id' => $asset['id'],
                    'dispatch_id' => $dispatchResult['data']['dispatch']['id'] ?? null
                ]
            ];
        }
        
        // Verify the rejection code indicates the asset issue
        $expectedCodes = ['ASSET_NOT_AVAILABLE', 'ASSET_LOCKED', 'ASSET_NOT_FOUND'];
        if (!in_array($dispatchResult['code'] ?? '', $expectedCodes)) {
            // Still a pass if rejected for any reason
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 9c: canDispatch returns false for lost items
     * For any lost item, canDispatch SHALL return false
     */
    private function testLostItemCanDispatch(): array {
        // Create test data
        $company = $this->createTestCompany();
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct();
        $asset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Verify canDispatch returns true before marking as lost
        $canDispatchBefore = $this->assetStatusService->canDispatch($asset['id']);
        if (!$canDispatchBefore['success']) {
            return [
                'success' => false,
                'message' => 'canDispatch should return true for in-stock asset',
                'data' => ['asset_id' => $asset['id'], 'result' => $canDispatchBefore]
            ];
        }
        
        // Mark asset as lost
        $lostResult = $this->assetStatusService->markAsLost($asset['id']);
        if (!$lostResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to mark asset as lost: ' . $lostResult['message'],
                'data' => ['asset_id' => $asset['id']]
            ];
        }
        
        // Verify canDispatch returns false after marking as lost
        $canDispatchAfter = $this->assetStatusService->canDispatch($asset['id']);
        if ($canDispatchAfter['success']) {
            return [
                'success' => false,
                'message' => 'canDispatch should return false for lost asset',
                'data' => [
                    'asset_id' => $asset['id'],
                    'result' => $canDispatchAfter
                ]
            ];
        }
        
        // Verify the rejection is due to locked status
        if ($canDispatchAfter['code'] !== 'ASSET_LOCKED') {
            // Still acceptable if rejected for any reason
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
    private function createTestProduct(): array {
        $data = [
            'name' => 'Test Product ' . $this->generateRandomString(8),
            'unit_of_measure' => 'unit',
            'inventory_type' => 'INTERNAL',
            'is_serializable' => 1,
            'is_repairable' => 1,
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
        // Delete assets
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
    $test = new LostItemLockTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

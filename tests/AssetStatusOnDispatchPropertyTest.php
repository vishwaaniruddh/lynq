<?php
/**
 * Property Test: Asset Status Transition on Dispatch
 * **Feature: dispatch-workflow-fixes, Property 1: Asset Status Transition on Dispatch**
 * **Validates: Requirements 2.1, 4.1**
 * 
 * Property: For any asset dispatched from ADV to a contractor, the asset status SHALL be 
 * "dispatched" and current_holder_type SHALL be "company" with current_holder_id set to 
 * the contractor's company ID.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/DispatchService.php';
require_once __DIR__ . '/../repositories/DispatchRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/PendingReceiveRepository.php';

class AssetStatusOnDispatchPropertyTest extends PropertyTestBase {
    private $dispatchService;
    private $dispatchRepository;
    private $assetRepository;
    private $productRepository;
    private $warehouseRepository;
    private $companyRepository;
    private $stockRepository;
    private $userRepository;
    private $pendingReceiveRepository;
    
    private $createdDispatchIds = [];
    private $createdAssetIds = [];
    private $createdProductIds = [];
    private $createdWarehouseIds = [];
    private $createdCompanyIds = [];
    private $createdStockIds = [];
    private $createdUserIds = [];
    private $testUserId = null;
    
    public function __construct() {
        parent::__construct();
        $this->dispatchService = new DispatchService();
        $this->dispatchRepository = new DispatchRepository();
        $this->dispatchRepository->disableCompanyFilter();
        $this->assetRepository = new AssetRepository();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->companyRepository = new CompanyRepository();
        $this->companyRepository->disableCompanyFilter();
        $this->stockRepository = new StockRepository();
        $this->userRepository = new UserRepository();
        $this->userRepository->disableCompanyFilter();
        $this->pendingReceiveRepository = new PendingReceiveRepository();
    }
    
    /**
     * Run all property tests
     */
    public function runTests() {
        echo "\n=== Asset Status on Dispatch Property Tests ===\n";
        echo "**Feature: dispatch-workflow-fixes, Property 1: Asset Status Transition on Dispatch**\n";
        echo "**Validates: Requirements 2.1, 4.1**\n\n";
        
        // Create a test user for dispatch operations
        $this->testUserId = $this->getOrCreateTestUser();
        
        $results = [];
        
        // Property 1a: Asset status changes to 'dispatched' on dispatch
        $results['asset_status_dispatched'] = $this->runPropertyTest(
            'Property 1a: Asset status changes to "dispatched" on dispatch',
            function() {
                return $this->testAssetStatusChangesToDispatched();
            },
            20
        );
        
        // Property 1b: Asset current_holder_type set to 'company' on dispatch to contractor
        $results['asset_holder_type_company'] = $this->runPropertyTest(
            'Property 1b: Asset current_holder_type set to "company" on dispatch to contractor',
            function() {
                return $this->testAssetHolderTypeSetToCompany();
            },
            20
        );
        
        // Property 1c: Asset current_holder_id set to contractor's company_id on dispatch
        $results['asset_holder_id_contractor'] = $this->runPropertyTest(
            'Property 1c: Asset current_holder_id set to contractor\'s company_id on dispatch',
            function() {
                return $this->testAssetHolderIdSetToContractor();
            },
            20
        );
        
        // Property 1d: Asset warehouse_id cleared on dispatch
        $results['asset_warehouse_cleared'] = $this->runPropertyTest(
            'Property 1d: Asset warehouse_id cleared on dispatch',
            function() {
                return $this->testAssetWarehouseCleared();
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
     * Property 1a: Asset status changes to 'dispatched' on dispatch
     * Requirement 2.1: When an asset is dispatched from ADV to a contractor, the asset status SHALL be "dispatched"
     */
    private function testAssetStatusChangesToDispatched(): array {
        // Create test ADV company and warehouse
        $advCompany = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($advCompany['id']);
        
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        
        // Create serializable product
        $product = $this->createTestProduct(true);
        
        // Create in_stock asset
        $asset = $this->createTestAsset($product['id'], $warehouse['id'], 'in_stock');
        
        // Verify initial status is in_stock
        if ($asset['status'] !== AssetRepository::STATUS_IN_STOCK) {
            return [
                'success' => false,
                'message' => 'Initial asset status is not "in_stock"',
                'data' => ['initial_status' => $asset['status']]
            ];
        }
        
        // Create dispatch to contractor
        $dispatchData = [
            'from_warehouse_id' => $warehouse['id'],
            'to_company_id' => $contractor['id'],
            'dispatch_date' => date('Y-m-d'),
            'notes' => 'Test dispatch for asset status property test'
        ];
        
        $items = [
            [
                'product_id' => $product['id'],
                'asset_id' => $asset['id'],
                'quantity' => 1
            ]
        ];
        
        $dispatchResult = $this->dispatchService->createDispatch($dispatchData, $items, $this->testUserId);
        
        if (!$dispatchResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to create dispatch: ' . ($dispatchResult['message'] ?? 'Unknown error'),
                'data' => $dispatchResult
            ];
        }
        
        $this->createdDispatchIds[] = $dispatchResult['data']['dispatch']['id'];
        
        // Verify asset status is now 'dispatched'
        $updatedAsset = $this->assetRepository->find($asset['id']);
        
        if ($updatedAsset['status'] !== AssetRepository::STATUS_DISPATCHED) {
            return [
                'success' => false,
                'message' => 'Asset status not changed to "dispatched" after dispatch',
                'data' => [
                    'expected_status' => AssetRepository::STATUS_DISPATCHED,
                    'actual_status' => $updatedAsset['status']
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 1b: Asset current_holder_type set to 'company' on dispatch to contractor
     * Requirement 4.1: current_holder_type SHALL be "company" for contractor recipients
     */
    private function testAssetHolderTypeSetToCompany(): array {
        // Create test ADV company and warehouse
        $advCompany = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($advCompany['id']);
        
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        
        // Create serializable product
        $product = $this->createTestProduct(true);
        
        // Create in_stock asset
        $asset = $this->createTestAsset($product['id'], $warehouse['id'], 'in_stock');
        
        // Verify initial holder type is warehouse
        if ($asset['current_holder_type'] !== AssetRepository::HOLDER_WAREHOUSE) {
            return [
                'success' => false,
                'message' => 'Initial asset holder type is not "warehouse"',
                'data' => ['initial_holder_type' => $asset['current_holder_type']]
            ];
        }
        
        // Create dispatch to contractor
        $dispatchData = [
            'from_warehouse_id' => $warehouse['id'],
            'to_company_id' => $contractor['id'],
            'dispatch_date' => date('Y-m-d'),
            'notes' => 'Test dispatch for holder type property test'
        ];
        
        $items = [
            [
                'product_id' => $product['id'],
                'asset_id' => $asset['id'],
                'quantity' => 1
            ]
        ];
        
        $dispatchResult = $this->dispatchService->createDispatch($dispatchData, $items, $this->testUserId);
        
        if (!$dispatchResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to create dispatch: ' . ($dispatchResult['message'] ?? 'Unknown error'),
                'data' => $dispatchResult
            ];
        }
        
        $this->createdDispatchIds[] = $dispatchResult['data']['dispatch']['id'];
        
        // Verify asset current_holder_type is 'company'
        $updatedAsset = $this->assetRepository->find($asset['id']);
        
        if ($updatedAsset['current_holder_type'] !== AssetRepository::HOLDER_COMPANY) {
            return [
                'success' => false,
                'message' => 'Asset current_holder_type not set to "company" after dispatch to contractor',
                'data' => [
                    'expected_holder_type' => AssetRepository::HOLDER_COMPANY,
                    'actual_holder_type' => $updatedAsset['current_holder_type']
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 1c: Asset current_holder_id set to contractor's company_id on dispatch
     * Requirement 4.1: current_holder_id SHALL be set to contractor's company_id
     */
    private function testAssetHolderIdSetToContractor(): array {
        // Create test ADV company and warehouse
        $advCompany = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($advCompany['id']);
        
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        
        // Create serializable product
        $product = $this->createTestProduct(true);
        
        // Create in_stock asset
        $asset = $this->createTestAsset($product['id'], $warehouse['id'], 'in_stock');
        
        // Verify initial holder id is warehouse id
        if ((int)$asset['current_holder_id'] !== (int)$warehouse['id']) {
            return [
                'success' => false,
                'message' => 'Initial asset holder id is not warehouse id',
                'data' => [
                    'expected_holder_id' => $warehouse['id'],
                    'actual_holder_id' => $asset['current_holder_id']
                ]
            ];
        }
        
        // Create dispatch to contractor
        $dispatchData = [
            'from_warehouse_id' => $warehouse['id'],
            'to_company_id' => $contractor['id'],
            'dispatch_date' => date('Y-m-d'),
            'notes' => 'Test dispatch for holder id property test'
        ];
        
        $items = [
            [
                'product_id' => $product['id'],
                'asset_id' => $asset['id'],
                'quantity' => 1
            ]
        ];
        
        $dispatchResult = $this->dispatchService->createDispatch($dispatchData, $items, $this->testUserId);
        
        if (!$dispatchResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to create dispatch: ' . ($dispatchResult['message'] ?? 'Unknown error'),
                'data' => $dispatchResult
            ];
        }
        
        $this->createdDispatchIds[] = $dispatchResult['data']['dispatch']['id'];
        
        // Verify asset current_holder_id is contractor's company_id
        $updatedAsset = $this->assetRepository->find($asset['id']);
        
        if ((int)$updatedAsset['current_holder_id'] !== (int)$contractor['id']) {
            return [
                'success' => false,
                'message' => 'Asset current_holder_id not set to contractor\'s company_id after dispatch',
                'data' => [
                    'expected_holder_id' => $contractor['id'],
                    'actual_holder_id' => $updatedAsset['current_holder_id']
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 1d: Asset warehouse_id cleared on dispatch
     * Requirement 2.1: When dispatched, asset should no longer be associated with source warehouse
     */
    private function testAssetWarehouseCleared(): array {
        // Create test ADV company and warehouse
        $advCompany = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($advCompany['id']);
        
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        
        // Create serializable product
        $product = $this->createTestProduct(true);
        
        // Create in_stock asset
        $asset = $this->createTestAsset($product['id'], $warehouse['id'], 'in_stock');
        
        // Verify initial warehouse_id is set
        if (empty($asset['warehouse_id'])) {
            return [
                'success' => false,
                'message' => 'Initial asset warehouse_id is not set',
                'data' => ['initial_warehouse_id' => $asset['warehouse_id']]
            ];
        }
        
        // Create dispatch to contractor
        $dispatchData = [
            'from_warehouse_id' => $warehouse['id'],
            'to_company_id' => $contractor['id'],
            'dispatch_date' => date('Y-m-d'),
            'notes' => 'Test dispatch for warehouse cleared property test'
        ];
        
        $items = [
            [
                'product_id' => $product['id'],
                'asset_id' => $asset['id'],
                'quantity' => 1
            ]
        ];
        
        $dispatchResult = $this->dispatchService->createDispatch($dispatchData, $items, $this->testUserId);
        
        if (!$dispatchResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to create dispatch: ' . ($dispatchResult['message'] ?? 'Unknown error'),
                'data' => $dispatchResult
            ];
        }
        
        $this->createdDispatchIds[] = $dispatchResult['data']['dispatch']['id'];
        
        // Verify asset warehouse_id is cleared (null)
        $updatedAsset = $this->assetRepository->find($asset['id']);
        
        if (!empty($updatedAsset['warehouse_id'])) {
            return [
                'success' => false,
                'message' => 'Asset warehouse_id not cleared after dispatch',
                'data' => [
                    'expected_warehouse_id' => null,
                    'actual_warehouse_id' => $updatedAsset['warehouse_id']
                ]
            ];
        }
        
        return ['success' => true];
    }


    
    // ==================== Helper Methods ====================
    
    /**
     * Get or create a test user for dispatch operations
     */
    private function getOrCreateTestUser(): int {
        // Try to find an existing user first
        $sql = "SELECT id FROM users WHERE status = 1 LIMIT 1";
        $result = $this->getResults($sql);
        
        if (!empty($result)) {
            return (int)$result[0]['id'];
        }
        
        // Create a test user if none exists
        $advCompany = $this->createTestCompany('ADV');
        $user = $this->userRepository->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test_' . $this->generateRandomString(8) . '@test.com',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'company_id' => $advCompany['id'],
            'status' => 1
        ]);
        
        $this->createdUserIds[] = $user['id'];
        return (int)$user['id'];
    }
    
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
     * Clean up test data
     */
    protected function cleanupTestData() {
        // Delete pending receives first
        foreach ($this->createdDispatchIds as $dispatchId) {
            try {
                $pendingReceive = $this->pendingReceiveRepository->findByDispatch($dispatchId);
                if ($pendingReceive) {
                    // Delete pending receive items first
                    $sql = "DELETE FROM pending_receive_items WHERE pending_receive_id = ?";
                    $this->executeQuery($sql, [$pendingReceive['id']], 'i');
                    // Delete pending receive
                    $this->pendingReceiveRepository->delete($pendingReceive['id']);
                }
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete dispatch items and dispatches
        foreach ($this->createdDispatchIds as $id) {
            try {
                // Delete dispatch items first
                $sql = "DELETE FROM dispatch_items WHERE dispatch_id = ?";
                $this->executeQuery($sql, [$id], 'i');
                // Delete dispatch chain entries
                $sql = "DELETE FROM dispatch_chain WHERE dispatch_id = ?";
                $this->executeQuery($sql, [$id], 'i');
                // Delete dispatch
                $this->dispatchRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete inventory counters for test companies
        foreach ($this->createdCompanyIds as $companyId) {
            try {
                $sql = "DELETE FROM inventory_counters WHERE entity_type = 'company' AND entity_id = ?";
                $this->executeQuery($sql, [$companyId], 'i');
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete inventory counters for test warehouses
        foreach ($this->createdWarehouseIds as $warehouseId) {
            try {
                $sql = "DELETE FROM inventory_counters WHERE entity_type = 'warehouse' AND entity_id = ?";
                $this->executeQuery($sql, [$warehouseId], 'i');
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete assets
        foreach ($this->createdAssetIds as $id) {
            try {
                $this->assetRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete stock
        foreach ($this->createdStockIds as $id) {
            try {
                $this->stockRepository->delete($id);
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
        
        // Delete users (before companies due to foreign key)
        foreach ($this->createdUserIds as $id) {
            try {
                $this->userRepository->delete($id);
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
        
        $this->createdDispatchIds = [];
        $this->createdAssetIds = [];
        $this->createdStockIds = [];
        $this->createdProductIds = [];
        $this->createdWarehouseIds = [];
        $this->createdUserIds = [];
        $this->createdCompanyIds = [];
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new AssetStatusOnDispatchPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

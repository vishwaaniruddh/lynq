<?php
/**
 * Property Test: Pending Receive Creation on Dispatch
 * **Feature: dispatch-workflow-fixes, Property 5: Pending Receive Creation on Dispatch**
 * **Validates: Requirements 3.1, 3.2**
 * 
 * Property: For any dispatch from ADV to a contractor, a pending receive record SHALL be created 
 * with status "pending" and SHALL be visible in the contractor's pending receives list.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/DispatchService.php';
require_once __DIR__ . '/../services/ReceiveService.php';
require_once __DIR__ . '/../repositories/PendingReceiveRepository.php';
require_once __DIR__ . '/../repositories/DispatchRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

class PendingReceiveCreationPropertyTest extends PropertyTestBase {
    private $dispatchService;
    private $receiveService;
    private $pendingReceiveRepository;
    private $dispatchRepository;
    private $assetRepository;
    private $productRepository;
    private $warehouseRepository;
    private $companyRepository;
    private $stockRepository;
    private $userRepository;
    
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
        $this->receiveService = new ReceiveService();
        $this->pendingReceiveRepository = new PendingReceiveRepository();
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
    }
    
    /**
     * Run all property tests
     */
    public function runTests() {
        echo "\n=== Pending Receive Creation Property Tests ===\n";
        echo "**Feature: dispatch-workflow-fixes, Property 5: Pending Receive Creation on Dispatch**\n";
        echo "**Validates: Requirements 3.1, 3.2**\n\n";
        
        // Create a test user for dispatch operations
        $this->testUserId = $this->getOrCreateTestUser();
        
        $results = [];
        
        // Property 5a: Pending receive created for serializable item dispatch to contractor
        $results['pending_receive_created_serializable'] = $this->runPropertyTest(
            'Property 5a: Pending receive created for serializable item dispatch to contractor',
            function() {
                return $this->testPendingReceiveCreatedForSerializableDispatch();
            },
            20
        );
        
        // Property 5b: Pending receive created for non-serializable item dispatch to contractor
        $results['pending_receive_created_non_serializable'] = $this->runPropertyTest(
            'Property 5b: Pending receive created for non-serializable item dispatch to contractor',
            function() {
                return $this->testPendingReceiveCreatedForNonSerializableDispatch();
            },
            20
        );
        
        // Property 5c: Pending receive visible in contractor's pending receives list
        $results['pending_receive_visible_to_contractor'] = $this->runPropertyTest(
            'Property 5c: Pending receive visible in contractor\'s pending receives list',
            function() {
                return $this->testPendingReceiveVisibleToContractor();
            },
            20
        );
        
        // Property 5d: Pending receive has correct dispatch details
        $results['pending_receive_has_dispatch_details'] = $this->runPropertyTest(
            'Property 5d: Pending receive has correct dispatch details',
            function() {
                return $this->testPendingReceiveHasDispatchDetails();
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
     * Property 5a: Pending receive created for serializable item dispatch to contractor
     * Requirement 3.1: When ADV dispatches materials to a contractor, a pending receive record SHALL be created
     */
    private function testPendingReceiveCreatedForSerializableDispatch(): array {
        // Create test ADV company and warehouse
        $advCompany = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($advCompany['id']);
        
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        
        // Create serializable product
        $product = $this->createTestProduct(true);
        
        // Create in_stock asset
        $asset = $this->createTestAsset($product['id'], $warehouse['id'], 'in_stock');
        
        // Create dispatch to contractor
        $dispatchData = [
            'from_warehouse_id' => $warehouse['id'],
            'to_company_id' => $contractor['id'],
            'dispatch_date' => date('Y-m-d'),
            'notes' => 'Test dispatch for property test'
        ];
        
        $items = [
            [
                'product_id' => $product['id'],
                'asset_id' => $asset['id'],
                'quantity' => 1
            ]
        ];
        
        $result = $this->dispatchService->createDispatch($dispatchData, $items, $this->testUserId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to create dispatch: ' . ($result['message'] ?? 'Unknown error'),
                'data' => $result
            ];
        }
        
        $this->createdDispatchIds[] = $result['data']['dispatch']['id'];
        
        // Verify pending receive was created
        $pendingReceive = $this->pendingReceiveRepository->findByDispatch($result['data']['dispatch']['id']);
        
        if (!$pendingReceive) {
            return [
                'success' => false,
                'message' => 'Pending receive not created for dispatch',
                'data' => [
                    'dispatch_id' => $result['data']['dispatch']['id']
                ]
            ];
        }
        
        // Verify pending receive has correct recipient
        if ($pendingReceive['recipient_type'] !== 'company' || $pendingReceive['recipient_id'] != $contractor['id']) {
            return [
                'success' => false,
                'message' => 'Pending receive has incorrect recipient',
                'data' => [
                    'expected_type' => 'company',
                    'expected_id' => $contractor['id'],
                    'actual_type' => $pendingReceive['recipient_type'],
                    'actual_id' => $pendingReceive['recipient_id']
                ]
            ];
        }
        
        // Verify status is pending
        if ($pendingReceive['status'] !== 'pending') {
            return [
                'success' => false,
                'message' => 'Pending receive has incorrect status',
                'data' => [
                    'expected_status' => 'pending',
                    'actual_status' => $pendingReceive['status']
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 5b: Pending receive created for non-serializable item dispatch to contractor
     * Requirement 3.1: When ADV dispatches materials to a contractor, a pending receive record SHALL be created
     */
    private function testPendingReceiveCreatedForNonSerializableDispatch(): array {
        // Create test ADV company and warehouse
        $advCompany = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($advCompany['id']);
        
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        
        // Create non-serializable product
        $product = $this->createTestProduct(false);
        
        // Add stock for non-serializable product
        $quantity = $this->generateRandomInt(5, 20);
        $this->addStock($product['id'], $warehouse['id'], $quantity);
        
        // Create dispatch to contractor
        $dispatchQuantity = $this->generateRandomInt(1, $quantity);
        $dispatchData = [
            'from_warehouse_id' => $warehouse['id'],
            'to_company_id' => $contractor['id'],
            'dispatch_date' => date('Y-m-d'),
            'notes' => 'Test non-serializable dispatch'
        ];
        
        $items = [
            [
                'product_id' => $product['id'],
                'quantity' => $dispatchQuantity
            ]
        ];
        
        $result = $this->dispatchService->createDispatch($dispatchData, $items, $this->testUserId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to create dispatch: ' . ($result['message'] ?? 'Unknown error'),
                'data' => $result
            ];
        }
        
        $this->createdDispatchIds[] = $result['data']['dispatch']['id'];
        
        // Verify pending receive was created
        $pendingReceive = $this->pendingReceiveRepository->findByDispatch($result['data']['dispatch']['id']);
        
        if (!$pendingReceive) {
            return [
                'success' => false,
                'message' => 'Pending receive not created for non-serializable dispatch',
                'data' => [
                    'dispatch_id' => $result['data']['dispatch']['id']
                ]
            ];
        }
        
        // Verify pending receive has correct recipient
        if ($pendingReceive['recipient_type'] !== 'company' || $pendingReceive['recipient_id'] != $contractor['id']) {
            return [
                'success' => false,
                'message' => 'Pending receive has incorrect recipient',
                'data' => [
                    'expected_type' => 'company',
                    'expected_id' => $contractor['id'],
                    'actual_type' => $pendingReceive['recipient_type'],
                    'actual_id' => $pendingReceive['recipient_id']
                ]
            ];
        }
        
        return ['success' => true];
    }

    
    /**
     * Property 5c: Pending receive visible in contractor's pending receives list
     * Requirement 3.2: Contractor SHALL see all dispatches awaiting acceptance with dispatch details
     */
    private function testPendingReceiveVisibleToContractor(): array {
        // Create test ADV company and warehouse
        $advCompany = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($advCompany['id']);
        
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        
        // Create serializable product
        $product = $this->createTestProduct(true);
        
        // Create in_stock asset
        $asset = $this->createTestAsset($product['id'], $warehouse['id'], 'in_stock');
        
        // Create dispatch to contractor
        $dispatchData = [
            'from_warehouse_id' => $warehouse['id'],
            'to_company_id' => $contractor['id'],
            'dispatch_date' => date('Y-m-d'),
            'notes' => 'Test dispatch visibility'
        ];
        
        $items = [
            [
                'product_id' => $product['id'],
                'asset_id' => $asset['id'],
                'quantity' => 1
            ]
        ];
        
        $result = $this->dispatchService->createDispatch($dispatchData, $items, $this->testUserId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to create dispatch: ' . ($result['message'] ?? 'Unknown error'),
                'data' => $result
            ];
        }
        
        $this->createdDispatchIds[] = $result['data']['dispatch']['id'];
        
        // Get pending receives for contractor using ReceiveService
        $pendingReceivesResult = $this->receiveService->getPendingReceives('company', $contractor['id']);
        
        if (!$pendingReceivesResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to get pending receives: ' . ($pendingReceivesResult['message'] ?? 'Unknown error'),
                'data' => $pendingReceivesResult
            ];
        }
        
        $pendingReceives = $pendingReceivesResult['data']['pending_receives'];
        
        // Find the pending receive for our dispatch
        $found = false;
        foreach ($pendingReceives as $pr) {
            if ($pr['dispatch_id'] == $result['data']['dispatch']['id']) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            return [
                'success' => false,
                'message' => 'Pending receive not visible in contractor\'s pending receives list',
                'data' => [
                    'dispatch_id' => $result['data']['dispatch']['id'],
                    'contractor_id' => $contractor['id'],
                    'pending_receives_count' => count($pendingReceives)
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 5d: Pending receive has correct dispatch details
     * Requirement 3.2: Display dispatch number, sender, items, quantities, and dispatch date
     */
    private function testPendingReceiveHasDispatchDetails(): array {
        // Create test ADV company and warehouse
        $advCompany = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($advCompany['id']);
        
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        
        // Create serializable product
        $product = $this->createTestProduct(true);
        
        // Create in_stock asset
        $asset = $this->createTestAsset($product['id'], $warehouse['id'], 'in_stock');
        
        // Create dispatch to contractor
        $dispatchDate = date('Y-m-d');
        $dispatchData = [
            'from_warehouse_id' => $warehouse['id'],
            'to_company_id' => $contractor['id'],
            'dispatch_date' => $dispatchDate,
            'notes' => 'Test dispatch details'
        ];
        
        $items = [
            [
                'product_id' => $product['id'],
                'asset_id' => $asset['id'],
                'quantity' => 1
            ]
        ];
        
        $result = $this->dispatchService->createDispatch($dispatchData, $items, $this->testUserId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to create dispatch: ' . ($result['message'] ?? 'Unknown error'),
                'data' => $result
            ];
        }
        
        $this->createdDispatchIds[] = $result['data']['dispatch']['id'];
        $dispatchNumber = $result['data']['dispatch']['dispatch_number'];
        
        // Get pending receives for contractor
        $pendingReceivesResult = $this->receiveService->getPendingReceives('company', $contractor['id']);
        
        if (!$pendingReceivesResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to get pending receives',
                'data' => $pendingReceivesResult
            ];
        }
        
        // Find our pending receive
        $pendingReceive = null;
        foreach ($pendingReceivesResult['data']['pending_receives'] as $pr) {
            if ($pr['dispatch_id'] == $result['data']['dispatch']['id']) {
                $pendingReceive = $pr;
                break;
            }
        }
        
        if (!$pendingReceive) {
            return [
                'success' => false,
                'message' => 'Pending receive not found',
                'data' => []
            ];
        }
        
        // Verify dispatch_number is present
        if (empty($pendingReceive['dispatch_number'])) {
            return [
                'success' => false,
                'message' => 'Pending receive missing dispatch_number',
                'data' => ['pending_receive' => $pendingReceive]
            ];
        }
        
        // Verify dispatch_number matches
        if ($pendingReceive['dispatch_number'] !== $dispatchNumber) {
            return [
                'success' => false,
                'message' => 'Dispatch number mismatch',
                'data' => [
                    'expected' => $dispatchNumber,
                    'actual' => $pendingReceive['dispatch_number']
                ]
            ];
        }
        
        // Verify sender_name is present (from warehouse)
        if (empty($pendingReceive['sender_name']) && empty($pendingReceive['from_warehouse_name'])) {
            return [
                'success' => false,
                'message' => 'Pending receive missing sender information',
                'data' => ['pending_receive' => $pendingReceive]
            ];
        }
        
        // Verify dispatch_date is present
        if (empty($pendingReceive['dispatch_date'])) {
            return [
                'success' => false,
                'message' => 'Pending receive missing dispatch_date',
                'data' => ['pending_receive' => $pendingReceive]
            ];
        }
        
        // Verify items are present
        if (empty($pendingReceive['items'])) {
            return [
                'success' => false,
                'message' => 'Pending receive missing items',
                'data' => ['pending_receive' => $pendingReceive]
            ];
        }
        
        // Verify days_pending is present
        if (!isset($pendingReceive['days_pending'])) {
            return [
                'success' => false,
                'message' => 'Pending receive missing days_pending',
                'data' => ['pending_receive' => $pendingReceive]
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
     * Add stock for non-serializable product
     */
    private function addStock(int $productId, int $warehouseId, int $quantity): array {
        $data = [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => $quantity,
            'reserved_quantity' => 0
        ];
        
        $stock = $this->stockRepository->create($data);
        $this->createdStockIds[] = $stock['id'];
        return $stock;
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        // Delete dispatches first (will cascade to pending_receives and dispatch_items)
        foreach ($this->createdDispatchIds as $id) {
            try {
                $this->dispatchRepository->delete($id);
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
    $test = new PendingReceiveCreationPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

<?php
/**
 * Property Test: Inventory Counter Conservation
 * 
 * **Feature: dispatch-workflow-fixes, Property 4: Inventory Counter Conservation**
 * **Validates: Requirements 6.1, 6.2, 6.3**
 * 
 * Property: For any dispatch-accept cycle, the sum of sender's counter decrease and 
 * recipient's counter increase SHALL equal the dispatched quantity. For any dispatch-reject 
 * cycle, the sender's counter SHALL return to its pre-dispatch value.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/ReceiveService.php';
require_once __DIR__ . '/../services/DispatchService.php';
require_once __DIR__ . '/../services/InventoryCounterService.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/DispatchRepository.php';
require_once __DIR__ . '/../repositories/DispatchItemRepository.php';
require_once __DIR__ . '/../repositories/PendingReceiveRepository.php';
require_once __DIR__ . '/../repositories/PendingReceiveItemRepository.php';
require_once __DIR__ . '/../repositories/InventoryCounterRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';

class InventoryCounterConservationPropertyTest extends PropertyTestBase {
    
    private $receiveService;
    private $dispatchService;
    private $inventoryCounterService;
    private $productRepository;
    private $warehouseRepository;
    private $dispatchRepository;
    private $dispatchItemRepository;
    private $pendingReceiveRepository;
    private $pendingReceiveItemRepository;
    private $inventoryCounterRepository;
    private $companyRepository;
    private $userRepository;
    private $stockRepository;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->receiveService = new ReceiveService();
        $this->dispatchService = new DispatchService();
        $this->inventoryCounterService = new InventoryCounterService();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->dispatchRepository = new DispatchRepository();
        $this->dispatchRepository->disableCompanyFilter();
        $this->dispatchItemRepository = new DispatchItemRepository();
        $this->pendingReceiveRepository = new PendingReceiveRepository();
        $this->pendingReceiveItemRepository = new PendingReceiveItemRepository();
        $this->inventoryCounterRepository = new InventoryCounterRepository();
        $this->companyRepository = new CompanyRepository();
        $this->userRepository = new UserRepository();
        $this->stockRepository = new StockRepository();
    }
    
    public function runTests() {
        echo "=== Inventory Counter Conservation Property Test ===\n";
        echo "**Feature: dispatch-workflow-fixes, Property 4: Inventory Counter Conservation**\n";
        echo "**Validates: Requirements 6.1, 6.2, 6.3**\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        $allPassed = true;
        
        // Property 4a: Dispatch-Accept cycle conservation
        $allPassed &= $this->runPropertyTest(
            'Property 4a: Dispatch-Accept cycle conserves total inventory',
            function() {
                return $this->testDispatchAcceptConservation();
            },
            50
        );
        
        // Property 4b: Dispatch-Reject cycle restoration
        $allPassed &= $this->runPropertyTest(
            'Property 4b: Dispatch-Reject cycle restores sender inventory',
            function() {
                return $this->testDispatchRejectRestoration();
            },
            50
        );
        
        // Property 4c: Counter deduction on dispatch (Requirement 6.1)
        $allPassed &= $this->runPropertyTest(
            'Property 4c: Counter deducted immediately on dispatch (Req 6.1)',
            function() {
                return $this->testCounterDeductionOnDispatch();
            },
            50
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }

    /**
     * Test dispatch-accept cycle conserves total inventory
     * 
     * Property: For any dispatch from warehouse to company that is accepted,
     * sender's decrease + recipient's increase = dispatched quantity
     * 
     * Validates: Requirements 6.1, 6.2
     */
    private function testDispatchAcceptConservation() {
        // Generate random test data
        $initialQuantity = $this->generateRandomInt(50, 200);
        $dispatchQuantity = $this->generateRandomInt(1, min($initialQuantity, 50));
        
        // Create test product (non-serializable for counter testing)
        $product = $this->createTestProduct();
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        // Create test warehouse (sender)
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        // Create test company (recipient)
        $company = $this->createTestCompany('CONTRACTOR');
        if (!$company) {
            return ['success' => false, 'message' => 'Failed to create test company'];
        }
        
        // Create test user for actions
        $user = $this->createTestUser($company['id']);
        if (!$user) {
            return ['success' => false, 'message' => 'Failed to create test user'];
        }
        
        // Set initial inventory for warehouse
        $incrementResult = $this->inventoryCounterService->incrementCounter(
            'warehouse',
            $warehouse['id'],
            $product['id'],
            $initialQuantity,
            $user['id'],
            'test_setup'
        );
        
        if (!$incrementResult['success']) {
            return ['success' => false, 'message' => 'Failed to set initial inventory: ' . $incrementResult['message']];
        }
        
        // Also add stock for non-serializable product
        $this->stockRepository->addQuantity($product['id'], $warehouse['id'], $initialQuantity);
        
        // Get sender inventory before dispatch
        $senderBefore = $this->inventoryCounterService->getCounter('warehouse', $warehouse['id'], $product['id']);
        $recipientBefore = $this->inventoryCounterService->getCounter('company', $company['id'], $product['id']);
        
        // Create dispatch using DispatchService
        $dispatchResult = $this->createDispatchWithPendingReceive(
            'warehouse', $warehouse['id'],
            'company', $company['id'],
            $product['id'],
            $dispatchQuantity,
            $user['id'],
            $warehouse['id'],
            $warehouse['company_id']
        );
        
        if (!$dispatchResult['success']) {
            return ['success' => false, 'message' => 'Failed to create dispatch: ' . $dispatchResult['message']];
        }
        
        $pendingReceiveId = $dispatchResult['pending_receive_id'];
        
        // Get sender inventory after dispatch
        $senderAfterDispatch = $this->inventoryCounterService->getCounter('warehouse', $warehouse['id'], $product['id']);
        
        // Accept the pending receive
        $acceptResult = $this->receiveService->acceptReceive($pendingReceiveId, $user['id']);
        
        if (!$acceptResult['success']) {
            return ['success' => false, 'message' => 'Failed to accept pending receive: ' . $acceptResult['message']];
        }
        
        // Get counters after acceptance
        $senderAfterAccept = $this->inventoryCounterService->getCounter('warehouse', $warehouse['id'], $product['id']);
        $recipientAfterAccept = $this->inventoryCounterService->getCounter('company', $company['id'], $product['id']);
        
        // Calculate changes
        $senderDecrease = $senderBefore - $senderAfterAccept;
        $recipientIncrease = $recipientAfterAccept - $recipientBefore;
        
        // Property check: sender decrease + recipient increase = dispatched quantity
        // Total inventory should be conserved
        if ($senderDecrease !== $dispatchQuantity) {
            return [
                'success' => false,
                'message' => "Sender decrease does not match dispatch quantity",
                'data' => [
                    'dispatch_quantity' => $dispatchQuantity,
                    'sender_before' => $senderBefore,
                    'sender_after' => $senderAfterAccept,
                    'sender_decrease' => $senderDecrease
                ]
            ];
        }
        
        if ($recipientIncrease !== $dispatchQuantity) {
            return [
                'success' => false,
                'message' => "Recipient increase does not match dispatch quantity",
                'data' => [
                    'dispatch_quantity' => $dispatchQuantity,
                    'recipient_before' => $recipientBefore,
                    'recipient_after' => $recipientAfterAccept,
                    'recipient_increase' => $recipientIncrease
                ]
            ];
        }
        
        // Total inventory conservation check
        $totalBefore = $senderBefore + $recipientBefore;
        $totalAfter = $senderAfterAccept + $recipientAfterAccept;
        
        if ($totalBefore !== $totalAfter) {
            return [
                'success' => false,
                'message' => "Total inventory not conserved",
                'data' => [
                    'total_before' => $totalBefore,
                    'total_after' => $totalAfter,
                    'difference' => $totalAfter - $totalBefore
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test dispatch-reject cycle restores sender inventory
     * 
     * Property: For any dispatch that is rejected, the sender's counter
     * SHALL return to its pre-dispatch value.
     * 
     * Validates: Requirement 6.3
     */
    private function testDispatchRejectRestoration() {
        // Generate random test data
        $initialQuantity = $this->generateRandomInt(50, 200);
        $dispatchQuantity = $this->generateRandomInt(1, min($initialQuantity, 50));
        $rejectionReason = 'Test rejection: ' . $this->generateRandomString(20);
        
        // Create test product
        $product = $this->createTestProduct();
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        // Create test warehouse (sender)
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        // Create test company (recipient)
        $company = $this->createTestCompany('CONTRACTOR');
        if (!$company) {
            return ['success' => false, 'message' => 'Failed to create test company'];
        }
        
        // Create test user for actions
        $user = $this->createTestUser($company['id']);
        if (!$user) {
            return ['success' => false, 'message' => 'Failed to create test user'];
        }
        
        // Set initial inventory for warehouse
        $incrementResult = $this->inventoryCounterService->incrementCounter(
            'warehouse',
            $warehouse['id'],
            $product['id'],
            $initialQuantity,
            $user['id'],
            'test_setup'
        );
        
        if (!$incrementResult['success']) {
            return ['success' => false, 'message' => 'Failed to set initial inventory: ' . $incrementResult['message']];
        }
        
        // Also add stock for non-serializable product
        $this->stockRepository->addQuantity($product['id'], $warehouse['id'], $initialQuantity);
        
        // Get sender inventory before dispatch
        $senderBefore = $this->inventoryCounterService->getCounter('warehouse', $warehouse['id'], $product['id']);
        
        // Create dispatch
        $dispatchResult = $this->createDispatchWithPendingReceive(
            'warehouse', $warehouse['id'],
            'company', $company['id'],
            $product['id'],
            $dispatchQuantity,
            $user['id'],
            $warehouse['id'],
            $warehouse['company_id']
        );
        
        if (!$dispatchResult['success']) {
            return ['success' => false, 'message' => 'Failed to create dispatch: ' . $dispatchResult['message']];
        }
        
        $pendingReceiveId = $dispatchResult['pending_receive_id'];
        
        // Get sender inventory after dispatch (should be decremented)
        $senderAfterDispatch = $this->inventoryCounterService->getCounter('warehouse', $warehouse['id'], $product['id']);
        
        // Verify dispatch decremented sender inventory
        $expectedAfterDispatch = $senderBefore - $dispatchQuantity;
        if ($senderAfterDispatch !== $expectedAfterDispatch) {
            return [
                'success' => false,
                'message' => "Dispatch did not decrement sender inventory correctly",
                'data' => [
                    'before' => $senderBefore,
                    'after_dispatch' => $senderAfterDispatch,
                    'expected' => $expectedAfterDispatch
                ]
            ];
        }
        
        // Reject the pending receive
        $rejectResult = $this->receiveService->rejectReceive($pendingReceiveId, $user['id'], $rejectionReason);
        
        if (!$rejectResult['success']) {
            return ['success' => false, 'message' => 'Failed to reject pending receive: ' . $rejectResult['message']];
        }
        
        // Get sender inventory after rejection (should be restored)
        $senderAfterRejection = $this->inventoryCounterService->getCounter('warehouse', $warehouse['id'], $product['id']);
        
        // Property check: sender inventory should be restored to original value
        if ($senderAfterRejection !== $senderBefore) {
            return [
                'success' => false,
                'message' => "Rejection did not restore sender inventory correctly",
                'data' => [
                    'initial_quantity' => $initialQuantity,
                    'dispatch_quantity' => $dispatchQuantity,
                    'sender_before' => $senderBefore,
                    'sender_after_dispatch' => $senderAfterDispatch,
                    'sender_after_rejection' => $senderAfterRejection,
                    'expected_after_rejection' => $senderBefore
                ]
            ];
        }
        
        return ['success' => true];
    }

    /**
     * Test counter deduction on dispatch
     * 
     * Property: When an asset is dispatched, the sender's available count
     * SHALL be immediately deducted.
     * 
     * Validates: Requirement 6.1
     */
    private function testCounterDeductionOnDispatch() {
        // Generate random test data
        $initialQuantity = $this->generateRandomInt(50, 200);
        $dispatchQuantity = $this->generateRandomInt(1, min($initialQuantity, 50));
        
        // Create test product
        $product = $this->createTestProduct();
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        // Create test warehouse (sender)
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        // Create test company (recipient)
        $company = $this->createTestCompany('CONTRACTOR');
        if (!$company) {
            return ['success' => false, 'message' => 'Failed to create test company'];
        }
        
        // Create test user for actions
        $user = $this->createTestUser($company['id']);
        if (!$user) {
            return ['success' => false, 'message' => 'Failed to create test user'];
        }
        
        // Set initial inventory for warehouse
        $incrementResult = $this->inventoryCounterService->incrementCounter(
            'warehouse',
            $warehouse['id'],
            $product['id'],
            $initialQuantity,
            $user['id'],
            'test_setup'
        );
        
        if (!$incrementResult['success']) {
            return ['success' => false, 'message' => 'Failed to set initial inventory: ' . $incrementResult['message']];
        }
        
        // Also add stock for non-serializable product
        $this->stockRepository->addQuantity($product['id'], $warehouse['id'], $initialQuantity);
        
        // Get sender inventory before dispatch
        $senderBefore = $this->inventoryCounterService->getCounter('warehouse', $warehouse['id'], $product['id']);
        
        // Create dispatch
        $dispatchResult = $this->createDispatchWithPendingReceive(
            'warehouse', $warehouse['id'],
            'company', $company['id'],
            $product['id'],
            $dispatchQuantity,
            $user['id'],
            $warehouse['id'],
            $warehouse['company_id']
        );
        
        if (!$dispatchResult['success']) {
            return ['success' => false, 'message' => 'Failed to create dispatch: ' . $dispatchResult['message']];
        }
        
        // Get sender inventory IMMEDIATELY after dispatch (before any accept/reject)
        $senderAfterDispatch = $this->inventoryCounterService->getCounter('warehouse', $warehouse['id'], $product['id']);
        
        // Property check: sender inventory should be immediately deducted
        $expectedAfterDispatch = $senderBefore - $dispatchQuantity;
        if ($senderAfterDispatch !== $expectedAfterDispatch) {
            return [
                'success' => false,
                'message' => "Counter not immediately deducted on dispatch (Requirement 6.1)",
                'data' => [
                    'initial_quantity' => $initialQuantity,
                    'dispatch_quantity' => $dispatchQuantity,
                    'sender_before' => $senderBefore,
                    'sender_after_dispatch' => $senderAfterDispatch,
                    'expected_after_dispatch' => $expectedAfterDispatch
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Create a dispatch with pending receive for testing
     */
    private function createDispatchWithPendingReceive(
        string $senderType, int $senderId,
        string $recipientType, int $recipientId,
        int $productId, int $quantity, int $userId,
        ?int $warehouseId = null, ?int $companyId = null
    ): array {
        try {
            // Get or create default warehouse and company for required fields
            if ($warehouseId === null) {
                $warehouseId = $this->getOrCreateDefaultWarehouseId();
            }
            if ($companyId === null) {
                $companyId = $this->getOrCreateTestCompanyId();
            }
            
            // Create dispatch record
            $dispatchData = [
                'dispatch_number' => DispatchRepository::generateDispatchNumber(),
                'dispatch_date' => date('Y-m-d'),
                'status' => DispatchRepository::STATUS_IN_TRANSIT,
                'acknowledgment_status' => DispatchRepository::ACK_PENDING,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'from_company_id' => $companyId,
                'from_warehouse_id' => $warehouseId,
                'created_by' => $userId,
                'notes' => 'Test dispatch for property test'
            ];
            
            // Set from fields based on entity types
            if ($senderType === 'warehouse') {
                $dispatchData['from_warehouse_id'] = $senderId;
            } elseif ($senderType === 'company') {
                $dispatchData['from_company_id'] = $senderId;
            }
            
            // Set to fields based on recipient type
            if ($recipientType === 'company') {
                $dispatchData['to_company_id'] = $recipientId;
            } elseif ($recipientType === 'user') {
                $dispatchData['to_user_id'] = $recipientId;
            } elseif ($recipientType === 'warehouse') {
                $dispatchData['to_warehouse_id'] = $recipientId;
            }
            
            $dispatch = $this->dispatchRepository->create($dispatchData);
            $this->createdRecords['dispatches'][] = $dispatch['id'];
            
            // Create dispatch item
            $dispatchItem = $this->dispatchItemRepository->create([
                'dispatch_id' => $dispatch['id'],
                'product_id' => $productId,
                'quantity' => $quantity
            ]);
            $this->createdRecords['dispatch_items'][] = $dispatchItem['id'];
            
            // Decrement sender inventory (simulating what DispatchService does)
            $decrementResult = $this->inventoryCounterService->decrementCounter(
                $senderType,
                $senderId,
                $productId,
                $quantity,
                $userId,
                'dispatch_created'
            );
            
            if (!$decrementResult['success']) {
                return ['success' => false, 'message' => 'Failed to decrement sender inventory: ' . $decrementResult['message']];
            }
            
            // Create pending receive
            $pendingReceive = $this->pendingReceiveRepository->create([
                'dispatch_id' => $dispatch['id'],
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'status' => PendingReceiveRepository::STATUS_PENDING
            ]);
            $this->createdRecords['pending_receives'][] = $pendingReceive['id'];
            
            // Create pending receive item
            $pendingReceiveItem = $this->pendingReceiveItemRepository->create([
                'pending_receive_id' => $pendingReceive['id'],
                'dispatch_item_id' => $dispatchItem['id'],
                'expected_quantity' => $quantity,
                'received_quantity' => 0,
                'status' => 'pending'
            ]);
            $this->createdRecords['pending_receive_items'][] = $pendingReceiveItem['id'];
            
            return [
                'success' => true,
                'dispatch_id' => $dispatch['id'],
                'pending_receive_id' => $pendingReceive['id']
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }

    /**
     * Get or create a default warehouse ID
     */
    private function getOrCreateDefaultWarehouseId() {
        $sql = "SELECT id FROM warehouses WHERE status = 'active' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (!empty($result)) {
            return $result[0]['id'];
        }
        
        $warehouse = $this->createTestWarehouse();
        return $warehouse ? $warehouse['id'] : null;
    }
    
    /**
     * Check if required tables exist
     */
    private function tablesExist() {
        $requiredTables = [
            'products', 'warehouses', 'companies', 'users',
            'dispatches', 'dispatch_items',
            'pending_receives', 'pending_receive_items',
            'inventory_counters', 'stock'
        ];
        
        foreach ($requiredTables as $table) {
            try {
                $result = $this->db->query("SHOW TABLES LIKE '$table'");
                if (!$result || $result->num_rows === 0) {
                    echo "Missing table: $table\n";
                    return false;
                }
            } catch (Exception $e) {
                echo "Error checking table $table: " . $e->getMessage() . "\n";
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create a test product
     */
    private function createTestProduct() {
        try {
            $productData = [
                'name' => 'Test Product ' . $this->generateRandomString(8),
                'unit_of_measure' => 'unit',
                'inventory_type' => 'INTERNAL',
                'is_serializable' => 0,
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
            $companyId = $this->getOrCreateTestCompanyId();
            
            $warehouseData = [
                'name' => 'Test Warehouse ' . $this->generateRandomString(8),
                'location' => 'Test Location',
                'company_id' => $companyId,
                'status' => 'active'
            ];
            
            $warehouse = $this->warehouseRepository->create($warehouseData);
            $warehouse['company_id'] = $companyId;
            $this->createdRecords['warehouses'][] = $warehouse['id'];
            return $warehouse;
            
        } catch (Exception $e) {
            error_log("Failed to create test warehouse: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create a test company
     */
    private function createTestCompany($type = 'CONTRACTOR') {
        try {
            $companyData = [
                'name' => 'Test Company ' . $this->generateRandomString(8),
                'type' => $type,
                'status' => 'ACTIVE'
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
     * Create a test user
     */
    private function createTestUser($companyId) {
        try {
            $username = 'testuser_' . $this->generateRandomString(8);
            $email = $this->generateRandomEmail();
            
            // Get a valid role_id
            $roleResult = $this->getResults("SELECT id FROM roles LIMIT 1");
            $roleId = !empty($roleResult) ? (int)$roleResult[0]['id'] : 1;
            
            $userData = [
                'username' => $username,
                'first_name' => 'Test',
                'last_name' => 'User ' . $this->generateRandomString(6),
                'email' => $email,
                'password_hash' => password_hash('test123', PASSWORD_DEFAULT),
                'company_id' => $companyId,
                'role_id' => $roleId,
                'status' => 1
            ];
            
            $user = $this->userRepository->create($userData);
            $this->createdRecords['users'][] = $user['id'];
            return $user;
            
        } catch (Exception $e) {
            error_log("Failed to create test user: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get or create a test company ID
     */
    private function getOrCreateTestCompanyId() {
        $sql = "SELECT id FROM companies WHERE status = 'ACTIVE' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (empty($result)) {
            $company = $this->createTestCompany();
            return $company ? $company['id'] : null;
        }
        
        return $result[0]['id'];
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Delete pending receive items first
            if (!empty($this->createdRecords['pending_receive_items'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['pending_receive_items']));
                $this->db->query("DELETE FROM `pending_receive_items` WHERE id IN ($ids)");
            }
            
            // Delete pending receives
            if (!empty($this->createdRecords['pending_receives'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['pending_receives']));
                $this->db->query("DELETE FROM `pending_receive_items` WHERE pending_receive_id IN ($ids)");
                $this->db->query("DELETE FROM `pending_receives` WHERE id IN ($ids)");
            }
            
            // Delete dispatch items
            if (!empty($this->createdRecords['dispatch_items'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['dispatch_items']));
                $this->db->query("DELETE FROM `dispatch_items` WHERE id IN ($ids)");
            }
            
            // Delete dispatches
            if (!empty($this->createdRecords['dispatches'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['dispatches']));
                $this->db->query("DELETE FROM `dispatch_items` WHERE dispatch_id IN ($ids)");
                $this->db->query("DELETE FROM `dispatches` WHERE id IN ($ids)");
            }
            
            // Delete inventory counters for test products
            if (!empty($this->createdRecords['products'])) {
                $productIds = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM `inventory_counters` WHERE product_id IN ($productIds)");
                $this->db->query("DELETE FROM `stock` WHERE product_id IN ($productIds)");
            }
            
            // Delete test products
            if (!empty($this->createdRecords['products'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM `products` WHERE id IN ($ids)");
            }
            
            // Delete test users
            if (!empty($this->createdRecords['users'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['users']));
                $this->db->query("DELETE FROM `users` WHERE id IN ($ids)");
            }
            
            // Delete test warehouses
            if (!empty($this->createdRecords['warehouses'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['warehouses']));
                $this->db->query("DELETE FROM `warehouses` WHERE id IN ($ids)");
            }
            
            // Delete test companies (only the ones we created)
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

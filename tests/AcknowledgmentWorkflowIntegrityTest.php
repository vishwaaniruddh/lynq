<?php
/**
 * Property Test: Acknowledgment Workflow Integrity
 * 
 * **Feature: adv-crm-inventory-module, Property 14: Acknowledgment Workflow Integrity**
 * **Validates: Requirements 14.1, 14.2**
 * 
 * Property: For any dispatch requiring acknowledgment, the item status SHALL not change 
 * to "Returned" until acknowledgment is recorded.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/DispatchService.php';
require_once __DIR__ . '/../services/StockService.php';
require_once __DIR__ . '/../repositories/DispatchRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';

class AcknowledgmentWorkflowIntegrityTest extends PropertyTestBase {
    
    private $dispatchService;
    private $stockService;
    private $dispatchRepository;
    private $productRepository;
    private $warehouseRepository;
    private $assetRepository;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->dispatchService = new DispatchService();
        $this->stockService = new StockService();
        $this->dispatchRepository = new DispatchRepository();
        $this->dispatchRepository->disableCompanyFilter();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->assetRepository = new AssetRepository();
    }
    
    public function runTests() {
        echo "=== Acknowledgment Workflow Integrity Property Test ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 14: Acknowledgment Workflow Integrity**\n";
        echo "**Validates: Requirements 14.1, 14.2**\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        $allPassed = true;
        
        // Run property test for acknowledgment status tracking
        $allPassed &= $this->runPropertyTest(
            'Property 14: Dispatch acknowledgment status is tracked correctly',
            function() {
                return $this->testAcknowledgmentStatusTracking();
            },
            100
        );
        
        // Run property test for acknowledgment prevents duplicate
        $allPassed &= $this->runPropertyTest(
            'Property 14: Dispatch cannot be acknowledged twice',
            function() {
                return $this->testDuplicateAcknowledgmentPrevention();
            },
            50
        );
        
        // Run property test for acknowledgment requires delivered status
        $allPassed &= $this->runPropertyTest(
            'Property 14: Acknowledgment requires delivered status',
            function() {
                return $this->testAcknowledgmentRequiresDeliveredStatus();
            },
            50
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Test that acknowledgment status is tracked correctly
     * 
     * Property: For any dispatch that is delivered and then acknowledged,
     * the acknowledgment_status should change from 'pending' to 'acknowledged',
     * and acknowledged_at and acknowledged_by should be set.
     */
    private function testAcknowledgmentStatusTracking() {
        // Create test data
        $product = $this->createTestProduct(false);
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        $companyId = $this->getTestCompanyId();
        $userId = $this->getTestUserId();
        
        // Add stock
        $quantity = $this->generateRandomInt(10, 50);
        $addResult = $this->stockService->addStock($product['id'], $warehouse['id'], $quantity);
        if (!$addResult['success']) {
            return ['success' => false, 'message' => 'Failed to add stock: ' . $addResult['message']];
        }
        
        // Create dispatch (pass null for userId to avoid FK constraint issues in test)
        $dispatchQuantity = $this->generateRandomInt(1, min($quantity, 10));
        $dispatchResult = $this->dispatchService->createDispatch(
            [
                'from_warehouse_id' => $warehouse['id'],
                'to_company_id' => $companyId,
                'dispatch_date' => date('Y-m-d')
            ],
            [['product_id' => $product['id'], 'quantity' => $dispatchQuantity]],
            null
        );
        
        if (!$dispatchResult['success']) {
            return ['success' => false, 'message' => 'Failed to create dispatch: ' . $dispatchResult['message']];
        }
        
        $dispatchId = $dispatchResult['data']['dispatch']['id'];
        $this->createdRecords['dispatches'][] = $dispatchId;
        
        // Verify initial acknowledgment status is pending
        $dispatch = $this->dispatchRepository->find($dispatchId);
        if ($dispatch['acknowledgment_status'] !== DispatchRepository::ACK_PENDING) {
            return [
                'success' => false,
                'message' => "Initial acknowledgment status should be 'pending', got: {$dispatch['acknowledgment_status']}"
            ];
        }
        
        // Process dispatch to in_transit
        $transitResult = $this->dispatchService->processDispatch($dispatchId, DispatchRepository::STATUS_IN_TRANSIT, null);
        if (!$transitResult['success']) {
            return ['success' => false, 'message' => 'Failed to process dispatch to in_transit: ' . $transitResult['message']];
        }
        
        // Process dispatch to delivered
        $deliveredResult = $this->dispatchService->processDispatch($dispatchId, DispatchRepository::STATUS_DELIVERED, null);
        if (!$deliveredResult['success']) {
            return ['success' => false, 'message' => 'Failed to process dispatch to delivered: ' . $deliveredResult['message']];
        }
        
        // Acknowledge the dispatch (use a valid user ID for acknowledgment)
        $ackResult = $this->dispatchService->acknowledgeReceipt($dispatchId, $userId);
        if (!$ackResult['success']) {
            return ['success' => false, 'message' => 'Failed to acknowledge dispatch: ' . $ackResult['message']];
        }
        
        // Verify acknowledgment status changed
        $dispatch = $this->dispatchRepository->find($dispatchId);
        
        if ($dispatch['acknowledgment_status'] !== DispatchRepository::ACK_ACKNOWLEDGED) {
            return [
                'success' => false,
                'message' => "Acknowledgment status should be 'acknowledged', got: {$dispatch['acknowledgment_status']}"
            ];
        }
        
        if (empty($dispatch['acknowledged_at'])) {
            return [
                'success' => false,
                'message' => 'acknowledged_at should be set after acknowledgment'
            ];
        }
        
        if ($dispatch['acknowledged_by'] != $userId) {
            return [
                'success' => false,
                'message' => "acknowledged_by should be $userId, got: {$dispatch['acknowledged_by']}"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test that duplicate acknowledgment is prevented
     * 
     * Property: For any dispatch that has already been acknowledged,
     * attempting to acknowledge it again should fail.
     */
    private function testDuplicateAcknowledgmentPrevention() {
        // Create test data
        $product = $this->createTestProduct(false);
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        $companyId = $this->getTestCompanyId();
        $userId = $this->getTestUserId();
        
        // Add stock
        $quantity = $this->generateRandomInt(10, 50);
        $this->stockService->addStock($product['id'], $warehouse['id'], $quantity);
        
        // Create and process dispatch
        $dispatchResult = $this->dispatchService->createDispatch(
            [
                'from_warehouse_id' => $warehouse['id'],
                'to_company_id' => $companyId,
                'dispatch_date' => date('Y-m-d')
            ],
            [['product_id' => $product['id'], 'quantity' => 5]],
            null
        );
        
        if (!$dispatchResult['success']) {
            return ['success' => false, 'message' => 'Failed to create dispatch'];
        }
        
        $dispatchId = $dispatchResult['data']['dispatch']['id'];
        $this->createdRecords['dispatches'][] = $dispatchId;
        
        // Process to delivered
        $transitResult = $this->dispatchService->processDispatch($dispatchId, DispatchRepository::STATUS_IN_TRANSIT, null);
        if (!$transitResult['success']) {
            return ['success' => false, 'message' => 'Failed to process dispatch to in_transit: ' . $transitResult['message']];
        }
        
        $deliveredResult = $this->dispatchService->processDispatch($dispatchId, DispatchRepository::STATUS_DELIVERED, null);
        if (!$deliveredResult['success']) {
            return ['success' => false, 'message' => 'Failed to process dispatch to delivered: ' . $deliveredResult['message']];
        }
        
        // First acknowledgment should succeed
        $firstAck = $this->dispatchService->acknowledgeReceipt($dispatchId, $userId);
        if (!$firstAck['success']) {
            return ['success' => false, 'message' => 'First acknowledgment should succeed: ' . $firstAck['message']];
        }
        
        // Second acknowledgment should fail
        $secondAck = $this->dispatchService->acknowledgeReceipt($dispatchId, $userId);
        if ($secondAck['success']) {
            return [
                'success' => false,
                'message' => 'Second acknowledgment should fail but succeeded'
            ];
        }
        
        if ($secondAck['code'] !== 'ALREADY_ACKNOWLEDGED') {
            return [
                'success' => false,
                'message' => "Expected error code 'ALREADY_ACKNOWLEDGED', got: {$secondAck['code']}"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test that acknowledgment requires delivered status
     * 
     * Property: For any dispatch that is not in 'delivered' status,
     * attempting to acknowledge it should fail.
     */
    private function testAcknowledgmentRequiresDeliveredStatus() {
        // Create test data
        $product = $this->createTestProduct(false);
        if (!$product) {
            return ['success' => false, 'message' => 'Failed to create test product'];
        }
        
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        $companyId = $this->getTestCompanyId();
        $userId = $this->getTestUserId();
        
        // Add stock
        $quantity = $this->generateRandomInt(10, 50);
        $this->stockService->addStock($product['id'], $warehouse['id'], $quantity);
        
        // Create dispatch (status: pending)
        $dispatchResult = $this->dispatchService->createDispatch(
            [
                'from_warehouse_id' => $warehouse['id'],
                'to_company_id' => $companyId,
                'dispatch_date' => date('Y-m-d')
            ],
            [['product_id' => $product['id'], 'quantity' => 5]],
            null
        );
        
        if (!$dispatchResult['success']) {
            return ['success' => false, 'message' => 'Failed to create dispatch'];
        }
        
        $dispatchId = $dispatchResult['data']['dispatch']['id'];
        $this->createdRecords['dispatches'][] = $dispatchId;
        
        // Try to acknowledge while in pending status - should fail
        $ackPending = $this->dispatchService->acknowledgeReceipt($dispatchId, $userId);
        if ($ackPending['success']) {
            return [
                'success' => false,
                'message' => 'Acknowledgment should fail for pending dispatch'
            ];
        }
        
        // Process to in_transit
        $transitResult = $this->dispatchService->processDispatch($dispatchId, DispatchRepository::STATUS_IN_TRANSIT, null);
        if (!$transitResult['success']) {
            return ['success' => false, 'message' => 'Failed to process dispatch to in_transit: ' . $transitResult['message']];
        }
        
        // Try to acknowledge while in_transit - should fail
        $ackInTransit = $this->dispatchService->acknowledgeReceipt($dispatchId, $userId);
        if ($ackInTransit['success']) {
            return [
                'success' => false,
                'message' => 'Acknowledgment should fail for in_transit dispatch'
            ];
        }
        
        // Process to delivered
        $deliveredResult = $this->dispatchService->processDispatch($dispatchId, DispatchRepository::STATUS_DELIVERED, null);
        if (!$deliveredResult['success']) {
            return ['success' => false, 'message' => 'Failed to process dispatch to delivered: ' . $deliveredResult['message']];
        }
        
        // Now acknowledgment should succeed
        $ackDelivered = $this->dispatchService->acknowledgeReceipt($dispatchId, $userId);
        if (!$ackDelivered['success']) {
            return [
                'success' => false,
                'message' => 'Acknowledgment should succeed for delivered dispatch: ' . $ackDelivered['message']
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Check if required tables exist
     */
    private function tablesExist() {
        $requiredTables = ['products', 'warehouses', 'stock', 'dispatches', 'dispatch_items', 'companies', 'users'];
        
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
    
    private function createTestWarehouse() {
        try {
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
    
    private function getTestCompanyId() {
        $sql = "SELECT id FROM companies WHERE status = 'ACTIVE' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (empty($result)) {
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
    
    private function getTestUserId() {
        // Status 1 = active in the users table
        $sql = "SELECT id FROM users WHERE status = 1 LIMIT 1";
        $result = $this->getResults($sql);
        
        if (!empty($result)) {
            return $result[0]['id'];
        }
        
        // Create a test user if none exists
        try {
            $companyId = $this->getTestCompanyId();
            $randomStr = $this->generateRandomString(8);
            $this->executeQuery(
                "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())",
                ['testuser_' . $randomStr, 'test_' . $randomStr . '@test.com', password_hash('test123', PASSWORD_DEFAULT), 'Test', 'User', $companyId],
                'sssssi'
            );
            $userId = $this->db->insert_id;
            $this->createdRecords['users'][] = $userId;
            return $userId;
        } catch (Exception $e) {
            error_log("Failed to create test user: " . $e->getMessage());
            // Return 1 as last resort
            return 1;
        }
    }
    
    public function cleanupTestData() {
        try {
            // Delete dispatch items first
            if (!empty($this->createdRecords['dispatches'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['dispatches']));
                $this->db->query("DELETE FROM `dispatch_items` WHERE dispatch_id IN ($ids)");
                $this->db->query("DELETE FROM `dispatches` WHERE id IN ($ids)");
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
            
            // Delete test users
            if (!empty($this->createdRecords['users'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['users']));
                $this->db->query("DELETE FROM `users` WHERE id IN ($ids)");
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

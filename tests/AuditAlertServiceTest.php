<?php
/**
 * Unit Tests for Audit and Alert Services
 * 
 * Tests audit log creation and alert generation/clearing functionality
 * 
 * Requirements: 12.1, 13.1, 13.4
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InventoryAuditService.php';
require_once __DIR__ . '/../services/InventoryAlertService.php';
require_once __DIR__ . '/../repositories/InventoryAuditLogRepository.php';
require_once __DIR__ . '/../repositories/StockAlertRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';

class AuditAlertServiceTest {
    
    private $db;
    private $auditService;
    private $alertService;
    private $auditLogRepository;
    private $alertRepository;
    private $productRepository;
    private $warehouseRepository;
    private $createdRecords = [];
    private $testsPassed = 0;
    private $testsFailed = 0;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
        $this->auditService = new InventoryAuditService();
        $this->alertService = new InventoryAlertService();
        $this->auditLogRepository = new InventoryAuditLogRepository();
        $this->alertRepository = new StockAlertRepository();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
    }
    
    public function runTests() {
        echo "=== Audit and Alert Service Unit Tests ===\n";
        echo "Requirements: 12.1, 13.1, 13.4\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        // Audit Service Tests
        $this->testAuditLogCreation();
        $this->testAuditLogValidation();
        $this->testAuditLogRetrieval();
        
        // Alert Service Tests
        $this->testAlertGeneration();
        $this->testAlertClearing();
        $this->testAlertRetrieval();
        
        // Cleanup
        $this->cleanupTestData();
        
        echo "\n=== Test Results ===\n";
        echo "Passed: {$this->testsPassed}\n";
        echo "Failed: {$this->testsFailed}\n";
        
        return $this->testsFailed === 0;
    }
    
    /**
     * Test audit log creation
     * Requirement 12.1: Log user, action type, timestamp, source location, and destination location
     */
    private function testAuditLogCreation() {
        echo "Testing audit log creation...\n";
        
        $userId = $this->getTestUserId();
        
        // Test basic log creation
        $result = $this->auditService->logAction(
            InventoryAuditService::ACTION_STOCK_ENTRY,
            InventoryAuditService::ENTITY_STOCK,
            1,
            $userId,
            [
                'to_location_type' => InventoryAuditService::LOCATION_WAREHOUSE,
                'to_location_id' => 1,
                'new_values' => ['quantity' => 100]
            ]
        );
        
        if ($result['success']) {
            $this->createdRecords['audit_logs'][] = $result['data']['id'];
            $this->pass("Audit log created successfully");
        } else {
            $this->fail("Failed to create audit log: " . $result['message']);
        }
        
        // Test log with all location data
        $result = $this->auditService->logAction(
            InventoryAuditService::ACTION_TRANSFER,
            InventoryAuditService::ENTITY_TRANSFER,
            2,
            $userId,
            [
                'from_location_type' => InventoryAuditService::LOCATION_WAREHOUSE,
                'from_location_id' => 1,
                'to_location_type' => InventoryAuditService::LOCATION_WAREHOUSE,
                'to_location_id' => 2,
                'old_values' => ['status' => 'pending'],
                'new_values' => ['status' => 'completed']
            ]
        );
        
        if ($result['success']) {
            $this->createdRecords['audit_logs'][] = $result['data']['id'];
            $this->pass("Audit log with full location data created successfully");
        } else {
            $this->fail("Failed to create audit log with location data: " . $result['message']);
        }
    }
    
    /**
     * Test audit log validation
     */
    private function testAuditLogValidation() {
        echo "Testing audit log validation...\n";
        
        // Test invalid action type
        $result = $this->auditService->logAction(
            'invalid_action',
            InventoryAuditService::ENTITY_STOCK,
            1,
            1,
            []
        );
        
        if (!$result['success'] && $result['code'] === 'INVALID_ACTION_TYPE') {
            $this->pass("Invalid action type rejected correctly");
        } else {
            $this->fail("Invalid action type should be rejected");
        }
        
        // Test invalid entity type
        $result = $this->auditService->logAction(
            InventoryAuditService::ACTION_STOCK_ENTRY,
            'invalid_entity',
            1,
            1,
            []
        );
        
        if (!$result['success'] && $result['code'] === 'INVALID_ENTITY_TYPE') {
            $this->pass("Invalid entity type rejected correctly");
        } else {
            $this->fail("Invalid entity type should be rejected");
        }
        
        // Test invalid user ID
        $result = $this->auditService->logAction(
            InventoryAuditService::ACTION_STOCK_ENTRY,
            InventoryAuditService::ENTITY_STOCK,
            1,
            0,
            []
        );
        
        if (!$result['success'] && $result['code'] === 'USER_ID_REQUIRED') {
            $this->pass("Invalid user ID rejected correctly");
        } else {
            $this->fail("Invalid user ID should be rejected");
        }
    }
    
    /**
     * Test audit log retrieval
     */
    private function testAuditLogRetrieval() {
        echo "Testing audit log retrieval...\n";
        
        $userId = $this->getTestUserId();
        
        // Create a test log
        $result = $this->auditService->logAction(
            InventoryAuditService::ACTION_STATUS_CHANGE,
            InventoryAuditService::ENTITY_ASSET,
            999,
            $userId,
            ['new_values' => ['status' => 'in_use']]
        );
        
        if ($result['success']) {
            $this->createdRecords['audit_logs'][] = $result['data']['id'];
            
            // Test retrieval by entity
            $logs = $this->auditService->getEntityHistory(InventoryAuditService::ENTITY_ASSET, 999);
            
            if (!empty($logs)) {
                $this->pass("Audit logs retrieved by entity successfully");
            } else {
                $this->fail("Failed to retrieve audit logs by entity");
            }
            
            // Test retrieval by user
            $logs = $this->auditService->getUserActivity($userId);
            
            if (!empty($logs)) {
                $this->pass("Audit logs retrieved by user successfully");
            } else {
                $this->fail("Failed to retrieve audit logs by user");
            }
        } else {
            $this->fail("Failed to create test audit log for retrieval test");
        }
    }

    
    /**
     * Test alert generation
     * Requirement 13.1: Generate low stock alert when product stock falls below defined threshold
     */
    private function testAlertGeneration() {
        echo "Testing alert generation...\n";
        
        // Create test product and warehouse
        $product = $this->createTestProduct(50);
        $warehouse = $this->createTestWarehouse();
        
        if (!$product || !$warehouse) {
            $this->fail("Failed to create test data for alert generation test");
            return;
        }
        
        // Generate alert
        $result = $this->alertService->generateAlert(
            $product['id'],
            $warehouse['id'],
            InventoryAlertService::TYPE_LOW_STOCK,
            10,
            50
        );
        
        if ($result['success']) {
            $this->createdRecords['alerts'][] = $result['data']['id'];
            $this->pass("Alert generated successfully");
            
            // Verify alert data
            $alert = $this->alertRepository->find($result['data']['id']);
            
            if ($alert['current_value'] == 10 && $alert['threshold_value'] == 50) {
                $this->pass("Alert values stored correctly");
            } else {
                $this->fail("Alert values not stored correctly");
            }
            
            if ($alert['status'] === InventoryAlertService::STATUS_ACTIVE) {
                $this->pass("Alert status is active");
            } else {
                $this->fail("Alert status should be active");
            }
        } else {
            $this->fail("Failed to generate alert: " . $result['message']);
        }
        
        // Test invalid alert type
        $result = $this->alertService->generateAlert(
            $product['id'],
            $warehouse['id'],
            'invalid_type',
            10,
            50
        );
        
        if (!$result['success'] && $result['code'] === 'INVALID_ALERT_TYPE') {
            $this->pass("Invalid alert type rejected correctly");
        } else {
            $this->fail("Invalid alert type should be rejected");
        }
    }
    
    /**
     * Test alert clearing
     * Requirement 13.4: Automatically clear alert when stock is replenished above threshold
     */
    private function testAlertClearing() {
        echo "Testing alert clearing...\n";
        
        // Create test product and warehouse
        $product = $this->createTestProduct(50);
        $warehouse = $this->createTestWarehouse();
        
        if (!$product || !$warehouse) {
            $this->fail("Failed to create test data for alert clearing test");
            return;
        }
        
        // Generate alert
        $result = $this->alertService->generateAlert(
            $product['id'],
            $warehouse['id'],
            InventoryAlertService::TYPE_LOW_STOCK,
            10,
            50
        );
        
        if (!$result['success']) {
            $this->fail("Failed to generate alert for clearing test");
            return;
        }
        
        $alertId = $result['data']['id'];
        $this->createdRecords['alerts'][] = $alertId;
        
        // Clear alert by ID
        $clearResult = $this->alertService->clearAlert($alertId);
        
        if ($clearResult['success']) {
            $this->pass("Alert cleared successfully by ID");
            
            // Verify alert status
            $alert = $this->alertRepository->find($alertId);
            
            if ($alert['status'] === InventoryAlertService::STATUS_CLEARED) {
                $this->pass("Alert status changed to cleared");
            } else {
                $this->fail("Alert status should be cleared");
            }
            
            if (!empty($alert['cleared_at'])) {
                $this->pass("Alert cleared_at timestamp set");
            } else {
                $this->fail("Alert cleared_at timestamp should be set");
            }
        } else {
            $this->fail("Failed to clear alert: " . $clearResult['message']);
        }
        
        // Test clearing non-existent alert
        $clearResult = $this->alertService->clearAlert(999999);
        
        if (!$clearResult['success'] && $clearResult['code'] === 'ALERT_NOT_FOUND') {
            $this->pass("Non-existent alert handled correctly");
        } else {
            $this->fail("Non-existent alert should return error");
        }
        
        // Test clearing by product/warehouse
        $result = $this->alertService->generateAlert(
            $product['id'],
            $warehouse['id'],
            InventoryAlertService::TYPE_LOW_STOCK,
            5,
            50
        );
        
        if ($result['success']) {
            $this->createdRecords['alerts'][] = $result['data']['id'];
            
            $clearResult = $this->alertService->clearAlertForProductWarehouse(
                $product['id'],
                $warehouse['id'],
                InventoryAlertService::TYPE_LOW_STOCK
            );
            
            if ($clearResult['success']) {
                $this->pass("Alert cleared by product/warehouse successfully");
            } else {
                $this->fail("Failed to clear alert by product/warehouse");
            }
        }
    }
    
    /**
     * Test alert retrieval
     */
    private function testAlertRetrieval() {
        echo "Testing alert retrieval...\n";
        
        // Create test product and warehouse
        $product = $this->createTestProduct(50);
        $warehouse = $this->createTestWarehouse();
        
        if (!$product || !$warehouse) {
            $this->fail("Failed to create test data for alert retrieval test");
            return;
        }
        
        // Generate alert
        $result = $this->alertService->generateAlert(
            $product['id'],
            $warehouse['id'],
            InventoryAlertService::TYPE_LOW_STOCK,
            10,
            50
        );
        
        if (!$result['success']) {
            $this->fail("Failed to generate alert for retrieval test");
            return;
        }
        
        $this->createdRecords['alerts'][] = $result['data']['id'];
        
        // Test get active alerts
        $activeAlerts = $this->alertService->getActiveAlerts();
        
        if (!empty($activeAlerts)) {
            $this->pass("Active alerts retrieved successfully");
        } else {
            $this->fail("Failed to retrieve active alerts");
        }
        
        // Test get low stock alerts
        $lowStockAlerts = $this->alertService->getLowStockAlerts();
        
        if (!empty($lowStockAlerts)) {
            $this->pass("Low stock alerts retrieved successfully");
        } else {
            $this->fail("Failed to retrieve low stock alerts");
        }
        
        // Test count active alerts
        $count = $this->alertService->countActiveAlerts();
        
        if ($count > 0) {
            $this->pass("Active alert count retrieved successfully");
        } else {
            $this->fail("Active alert count should be greater than 0");
        }
        
        // Test get alert details
        $details = $this->alertService->getAlertDetails($result['data']['id']);
        
        if ($details && isset($details['product_name'])) {
            $this->pass("Alert details with product name retrieved successfully");
        } else {
            $this->fail("Failed to retrieve alert details with product name");
        }
    }

    
    /**
     * Check if required tables exist
     */
    private function tablesExist() {
        $requiredTables = ['inventory_audit_log', 'stock_alerts', 'products', 'warehouses', 'companies', 'users'];
        
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
    
    /**
     * Get a test user ID
     */
    private function getTestUserId() {
        $sql = "SELECT id FROM users LIMIT 1";
        $result = DatabaseConfig::getInstance()->getResults($sql);
        
        if (empty($result)) {
            // Create a test user if none exists
            $companyId = $this->getTestCompanyId();
            DatabaseConfig::getInstance()->executeQuery(
                "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)",
                ['testuser' . uniqid(), 'test' . uniqid() . '@test.com', password_hash('test123', PASSWORD_DEFAULT), 'Test', 'User', $companyId, 1],
                'ssssssi'
            );
            $userId = $this->db->insert_id;
            $this->createdRecords['users'][] = $userId;
            return $userId;
        }
        
        return $result[0]['id'];
    }
    
    /**
     * Get a test company ID
     */
    private function getTestCompanyId() {
        $sql = "SELECT id FROM companies WHERE status = 'ACTIVE' LIMIT 1";
        $result = DatabaseConfig::getInstance()->getResults($sql);
        
        if (empty($result)) {
            DatabaseConfig::getInstance()->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test Company ' . uniqid(), 'ADV', 'ACTIVE'],
                'sss'
            );
            $companyId = $this->db->insert_id;
            $this->createdRecords['companies'][] = $companyId;
            return $companyId;
        }
        
        return $result[0]['id'];
    }
    
    /**
     * Create a test product
     */
    private function createTestProduct(int $threshold) {
        try {
            $productData = [
                'name' => 'Test Product ' . uniqid(),
                'unit_of_measure' => 'unit',
                'inventory_type' => 'INTERNAL',
                'is_serializable' => 0,
                'is_repairable' => 0,
                'low_stock_threshold' => $threshold,
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
            $companyId = $this->getTestCompanyId();
            
            $warehouseData = [
                'name' => 'Test Warehouse ' . uniqid(),
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
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Delete test audit logs
            if (!empty($this->createdRecords['audit_logs'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['audit_logs']));
                $this->db->query("DELETE FROM `inventory_audit_log` WHERE id IN ($ids)");
            }
            
            // Delete test alerts
            if (!empty($this->createdRecords['alerts'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['alerts']));
                $this->db->query("DELETE FROM `stock_alerts` WHERE id IN ($ids)");
            }
            
            // Delete alerts for test products
            if (!empty($this->createdRecords['products'])) {
                $productIds = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM `stock_alerts` WHERE product_id IN ($productIds)");
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
    
    private function pass($message) {
        echo "  ✓ $message\n";
        $this->testsPassed++;
    }
    
    private function fail($message) {
        echo "  ✗ $message\n";
        $this->testsFailed++;
    }
}

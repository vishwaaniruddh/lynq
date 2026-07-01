<?php
/**
 * Property Test: Audit Log Completeness
 * 
 * **Feature: adv-crm-inventory-module, Property 10: Audit Log Completeness**
 * **Validates: Requirements 12.1**
 * 
 * Property: For any inventory action (stock entry, dispatch, transfer, status change),
 * the audit log SHALL contain user ID, action type, timestamp, and location details.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/InventoryAuditService.php';
require_once __DIR__ . '/../repositories/InventoryAuditLogRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';

class AuditLogCompletenessTest extends PropertyTestBase {
    
    private $auditService;
    private $auditLogRepository;
    private $productRepository;
    private $warehouseRepository;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->auditService = new InventoryAuditService();
        $this->auditLogRepository = new InventoryAuditLogRepository();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
    }
    
    public function runTests() {
        echo "=== Audit Log Completeness Property Test ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 10: Audit Log Completeness**\n";
        echo "**Validates: Requirements 12.1**\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        $allPassed = true;
        
        // Run property test for audit log completeness
        $allPassed &= $this->runPropertyTest(
            'Property 10: Audit log contains required fields for all inventory actions',
            function() {
                return $this->testAuditLogCompleteness();
            },
            100
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Test audit log completeness
     * 
     * Property: For any inventory action, the audit log entry SHALL contain:
     * - user_id (non-null, positive integer)
     * - action_type (valid action type)
     * - created_at (timestamp)
     * - entity_type (valid entity type)
     * - entity_id (positive integer)
     * - location details when applicable
     */
    private function testAuditLogCompleteness() {
        // Generate random test data
        $actionType = $this->generateRandomChoice(InventoryAuditService::getActionTypes());
        $entityType = $this->generateRandomChoice(InventoryAuditService::getEntityTypes());
        $entityId = $this->generateRandomInt(1, 10000);
        $userId = $this->getTestUserId();
        
        // Create test warehouse for location data
        $warehouse = $this->createTestWarehouse();
        if (!$warehouse) {
            return ['success' => false, 'message' => 'Failed to create test warehouse'];
        }
        
        // Generate random location data
        $includeFromLocation = $this->generateRandomBool();
        $includeToLocation = $this->generateRandomBool();
        
        $data = [];
        if ($includeFromLocation) {
            $data['from_location_type'] = InventoryAuditService::LOCATION_WAREHOUSE;
            $data['from_location_id'] = $warehouse['id'];
        }
        if ($includeToLocation) {
            $data['to_location_type'] = InventoryAuditService::LOCATION_WAREHOUSE;
            $data['to_location_id'] = $warehouse['id'];
        }
        
        // Add some random values
        $data['new_values'] = [
            'test_field' => $this->generateRandomString(10),
            'quantity' => $this->generateRandomInt(1, 100)
        ];
        
        // Log the action
        $result = $this->auditService->logAction($actionType, $entityType, $entityId, $userId, $data);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to log action: ' . $result['message'],
                'data' => [
                    'action_type' => $actionType,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'user_id' => $userId
                ]
            ];
        }
        
        // Track created record for cleanup
        $this->createdRecords['audit_logs'][] = $result['data']['id'];
        
        // Retrieve the log entry
        $logEntry = $this->auditLogRepository->find($result['data']['id']);
        
        if (!$logEntry) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve log entry',
                'data' => ['log_id' => $result['data']['id']]
            ];
        }
        
        // Verify required fields are present and valid
        
        // Check user_id
        if (!isset($logEntry['user_id']) || $logEntry['user_id'] != $userId) {
            return [
                'success' => false,
                'message' => 'User ID mismatch or missing',
                'data' => [
                    'expected_user_id' => $userId,
                    'actual_user_id' => $logEntry['user_id'] ?? 'null'
                ]
            ];
        }
        
        // Check action_type
        if (!isset($logEntry['action_type']) || $logEntry['action_type'] !== $actionType) {
            return [
                'success' => false,
                'message' => 'Action type mismatch or missing',
                'data' => [
                    'expected_action_type' => $actionType,
                    'actual_action_type' => $logEntry['action_type'] ?? 'null'
                ]
            ];
        }
        
        // Check entity_type
        if (!isset($logEntry['entity_type']) || $logEntry['entity_type'] !== $entityType) {
            return [
                'success' => false,
                'message' => 'Entity type mismatch or missing',
                'data' => [
                    'expected_entity_type' => $entityType,
                    'actual_entity_type' => $logEntry['entity_type'] ?? 'null'
                ]
            ];
        }
        
        // Check entity_id
        if (!isset($logEntry['entity_id']) || $logEntry['entity_id'] != $entityId) {
            return [
                'success' => false,
                'message' => 'Entity ID mismatch or missing',
                'data' => [
                    'expected_entity_id' => $entityId,
                    'actual_entity_id' => $logEntry['entity_id'] ?? 'null'
                ]
            ];
        }
        
        // Check timestamp (created_at)
        if (!isset($logEntry['created_at']) || empty($logEntry['created_at'])) {
            return [
                'success' => false,
                'message' => 'Timestamp (created_at) is missing',
                'data' => ['log_entry' => $logEntry]
            ];
        }
        
        // Check location data if it was provided
        if ($includeFromLocation) {
            if (!isset($logEntry['from_location_type']) || $logEntry['from_location_type'] !== InventoryAuditService::LOCATION_WAREHOUSE) {
                return [
                    'success' => false,
                    'message' => 'From location type mismatch or missing',
                    'data' => [
                        'expected' => InventoryAuditService::LOCATION_WAREHOUSE,
                        'actual' => $logEntry['from_location_type'] ?? 'null'
                    ]
                ];
            }
            if (!isset($logEntry['from_location_id']) || $logEntry['from_location_id'] != $warehouse['id']) {
                return [
                    'success' => false,
                    'message' => 'From location ID mismatch or missing',
                    'data' => [
                        'expected' => $warehouse['id'],
                        'actual' => $logEntry['from_location_id'] ?? 'null'
                    ]
                ];
            }
        }
        
        if ($includeToLocation) {
            if (!isset($logEntry['to_location_type']) || $logEntry['to_location_type'] !== InventoryAuditService::LOCATION_WAREHOUSE) {
                return [
                    'success' => false,
                    'message' => 'To location type mismatch or missing',
                    'data' => [
                        'expected' => InventoryAuditService::LOCATION_WAREHOUSE,
                        'actual' => $logEntry['to_location_type'] ?? 'null'
                    ]
                ];
            }
            if (!isset($logEntry['to_location_id']) || $logEntry['to_location_id'] != $warehouse['id']) {
                return [
                    'success' => false,
                    'message' => 'To location ID mismatch or missing',
                    'data' => [
                        'expected' => $warehouse['id'],
                        'actual' => $logEntry['to_location_id'] ?? 'null'
                    ]
                ];
            }
        }
        
        return ['success' => true];
    }

    
    /**
     * Check if required tables exist
     */
    private function tablesExist() {
        $requiredTables = ['inventory_audit_log', 'warehouses', 'companies'];
        
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
     * Create a test warehouse
     */
    private function createTestWarehouse() {
        try {
            // Get or create a test company
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
    
    /**
     * Get a test company ID
     */
    private function getTestCompanyId() {
        $sql = "SELECT id FROM companies WHERE status = 'ACTIVE' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (empty($result)) {
            // Create a test company if none exists
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
    
    /**
     * Get a test user ID
     */
    private function getTestUserId() {
        $sql = "SELECT id FROM users WHERE status = 'ACTIVE' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (empty($result)) {
            // Try to get any user
            $sql = "SELECT id FROM users LIMIT 1";
            $result = $this->getResults($sql);
            
            if (empty($result)) {
                // Create a test user if none exists
                $companyId = $this->getTestCompanyId();
                $this->executeQuery(
                    "INSERT INTO users (name, email, password, company_id, status) VALUES (?, ?, ?, ?, ?)",
                    ['Test User ' . $this->generateRandomString(8), 'test' . $this->generateRandomString(8) . '@test.com', password_hash('test123', PASSWORD_DEFAULT), $companyId, 'ACTIVE'],
                    'sssss'
                );
                $userId = $this->db->insert_id;
                $this->createdRecords['users'][] = $userId;
                return $userId;
            }
        }
        
        return $result[0]['id'];
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

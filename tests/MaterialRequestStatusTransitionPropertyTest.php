<?php
/**
 * Property Test for Status Transition Validity
 * **Feature: material-request-module, Property 6: Status Transition Validity**
 * **Validates: Requirements 5.2, 5.3, 5.4, 9.7**
 * 
 * For any material request, status transitions should only be allowed in the valid sequence: 
 * requested→approved→dispatched→received. Invalid transitions should be rejected with an error.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/MaterialRequestService.php';
require_once __DIR__ . '/../services/MaterialMasterService.php';
require_once __DIR__ . '/../repositories/MaterialRequestRepository.php';

class MaterialRequestStatusTransitionPropertyTest extends PropertyTestBase {
    
    private $materialRequestService;
    private $materialMasterService;
    private $materialRequestRepository;
    private $createdRecords = [];
    private $testCompanyId;
    private $testUserId;
    private $testProductIds = [];
    private $testMasterIds = [];
    
    // Valid status transitions
    private $validTransitions = [
        'requested' => ['approved'],
        'approved' => ['dispatched'],
        'dispatched' => ['received'],
        'received' => []
    ];
    
    // All possible statuses
    private $allStatuses = ['requested', 'approved', 'dispatched', 'received'];
    
    public function __construct() {
        parent::__construct();
        $this->materialRequestService = new MaterialRequestService();
        $this->materialMasterService = new MaterialMasterService();
        $this->materialRequestRepository = new MaterialRequestRepository();
        $this->iterations = 20; // As specified in design doc
    }
    
    public function runTests() {
        echo "=== Material Request Status Transition Property Tests ===\n\n";
        
        // Setup test data
        $this->setupTestData();
        
        $allPassed = true;
        
        // Test Valid Transitions
        $allPassed &= $this->runPropertyTest(
            "Valid Status Transitions",
            [$this, 'testValidStatusTransitions']
        );
        
        // Test Invalid Transitions
        $allPassed &= $this->runPropertyTest(
            "Invalid Status Transitions Rejected",
            [$this, 'testInvalidStatusTransitionsRejected']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 6: Valid Status Transitions
     * Valid transitions should succeed: requested→approved→dispatched→received
     * **Feature: material-request-module, Property 6: Status Transition Validity**
     * **Validates: Requirements 5.2, 5.3, 5.4, 9.7**
     */
    public function testValidStatusTransitions() {
        try {
            // Create a new site and request
            $siteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $siteId;
            
            $masterId = $this->testMasterIds[array_rand($this->testMasterIds)];
            
            // Create request (starts at 'requested')
            $createResult = $this->materialRequestService->create(
                $siteId,
                $masterId,
                $this->testUserId,
                $this->testCompanyId
            );
            
            $this->assert($createResult['success'], "Request creation should succeed");
            $requestId = $createResult['data']['id'];
            $this->createdRecords['material_requests'][] = $requestId;
            
            // Verify initial status
            $this->assert(
                $createResult['data']['status'] === 'requested',
                "Initial status should be 'requested'"
            );
            
            // Test valid transition: requested → approved
            $approveResult = $this->materialRequestService->updateStatus(
                $requestId,
                'approved',
                $this->testUserId,
                $this->testCompanyId
            );
            $this->assert($approveResult['success'], "Transition to 'approved' should succeed");
            $this->assert(
                $approveResult['data']['status'] === 'approved',
                "Status should be 'approved' after transition"
            );
            
            // Test valid transition: approved → dispatched
            $dispatchResult = $this->materialRequestService->updateStatus(
                $requestId,
                'dispatched',
                $this->testUserId,
                $this->testCompanyId
            );
            $this->assert($dispatchResult['success'], "Transition to 'dispatched' should succeed");
            $this->assert(
                $dispatchResult['data']['status'] === 'dispatched',
                "Status should be 'dispatched' after transition"
            );
            
            // Test valid transition: dispatched → received
            $receiveResult = $this->materialRequestService->updateStatus(
                $requestId,
                'received',
                $this->testUserId,
                $this->testCompanyId
            );
            $this->assert($receiveResult['success'], "Transition to 'received' should succeed");
            $this->assert(
                $receiveResult['data']['status'] === 'received',
                "Status should be 'received' after transition"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['request_id' => $requestId ?? null]
            ];
        }
    }
    
    /**
     * Property 6: Invalid Status Transitions Rejected
     * Invalid transitions should be rejected with an error
     * **Feature: material-request-module, Property 6: Status Transition Validity**
     * **Validates: Requirements 5.2, 5.3, 5.4, 9.7**
     */
    public function testInvalidStatusTransitionsRejected() {
        try {
            // Create a new site and request
            $siteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $siteId;
            
            $masterId = $this->testMasterIds[array_rand($this->testMasterIds)];
            
            // Create request (starts at 'requested')
            $createResult = $this->materialRequestService->create(
                $siteId,
                $masterId,
                $this->testUserId,
                $this->testCompanyId
            );
            
            $this->assert($createResult['success'], "Request creation should succeed");
            $requestId = $createResult['data']['id'];
            $this->createdRecords['material_requests'][] = $requestId;
            
            $currentStatus = 'requested';
            
            // Generate a random invalid transition
            $invalidStatuses = array_diff($this->allStatuses, $this->validTransitions[$currentStatus], [$currentStatus]);
            
            if (!empty($invalidStatuses)) {
                $invalidStatus = $this->generateRandomChoice(array_values($invalidStatuses));
                
                // Try invalid transition
                $result = $this->materialRequestService->updateStatus(
                    $requestId,
                    $invalidStatus,
                    $this->testUserId,
                    $this->testCompanyId
                );
                
                $this->assert(!$result['success'], "Invalid transition from '$currentStatus' to '$invalidStatus' should fail");
                $this->assert(
                    $result['code'] === 'INVALID_TRANSITION',
                    "Error code should be 'INVALID_TRANSITION', got: " . ($result['code'] ?? 'none')
                );
                
                // Verify status hasn't changed
                $request = $this->materialRequestService->getById($requestId, $this->testCompanyId);
                $this->assert(
                    $request['status'] === $currentStatus,
                    "Status should remain '$currentStatus' after invalid transition attempt"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['request_id' => $requestId ?? null, 'current_status' => $currentStatus ?? null]
            ];
        }
    }
    
    /**
     * Create a test site
     */
    private function createTestSite(): int {
        $siteName = 'Test Site ' . $this->generateRandomString(10);
        
        $stmt = $this->executeQuery(
            "INSERT INTO sites (site_name, company_id, status, created_at) VALUES (?, ?, ?, NOW())",
            [$siteName, $this->testCompanyId, 'active'],
            'sis'
        );
        $siteId = $this->db->insert_id;
        $stmt->close();
        
        return $siteId;
    }
    
    /**
     * Setup test data
     */
    private function setupTestData() {
        try {
            // Get or create test company
            $result = $this->getResults("SELECT id FROM companies WHERE type = 'ADV' LIMIT 1");
            if (!empty($result)) {
                $this->testCompanyId = (int)$result[0]['id'];
            } else {
                $stmt = $this->executeQuery(
                    "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                    ['Test Company', 'ADV', 'ACTIVE'],
                    'sss'
                );
                $this->testCompanyId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['companies'][] = $this->testCompanyId;
            }
            
            // Get or create test user
            $result = $this->getResults("SELECT id FROM users WHERE company_id = ? LIMIT 1", [$this->testCompanyId], 'i');
            if (!empty($result)) {
                $this->testUserId = (int)$result[0]['id'];
            } else {
                $stmt = $this->executeQuery(
                    "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    ['test_user_' . $this->generateRandomString(5), 'test_' . $this->generateRandomString(5) . '@test.com', password_hash('test123', PASSWORD_DEFAULT), 'Test', 'User', $this->testCompanyId, 1, 1],
                    'sssssiii'
                );
                $this->testUserId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['users'][] = $this->testUserId;
            }
            
            // Get existing products
            $result = $this->getResults("SELECT id FROM products LIMIT 5");
            if (!empty($result)) {
                foreach ($result as $row) {
                    $this->testProductIds[] = (int)$row['id'];
                }
            }
            
            // If not enough products, create some
            while (count($this->testProductIds) < 3) {
                $stmt = $this->executeQuery(
                    "INSERT INTO products (name, unit_of_measure, is_serializable, is_repairable, inventory_type, status) VALUES (?, ?, ?, ?, ?, ?)",
                    ['Test Product ' . $this->generateRandomString(5), 'unit', 0, 0, 'consumable', 'active'],
                    'ssiiss'
                );
                $productId = $this->db->insert_id;
                $stmt->close();
                $this->testProductIds[] = $productId;
                $this->createdRecords['products'][] = $productId;
            }
            
            // Create test Material Masters
            for ($i = 0; $i < 3; $i++) {
                $masterData = [
                    'name' => 'Test Master ' . $this->generateRandomString(10),
                    'description' => 'Test description',
                    'status' => 'active',
                    'items' => [
                        ['product_id' => $this->testProductIds[0], 'quantity' => rand(1, 10)]
                    ]
                ];
                
                $result = $this->materialMasterService->create($masterData, $this->testUserId, $this->testCompanyId);
                if ($result['success']) {
                    $this->testMasterIds[] = $result['data']['id'];
                    $this->createdRecords['material_masters'][] = $result['data']['id'];
                }
            }
            
            if (empty($this->testMasterIds)) {
                throw new Exception("Failed to create test Material Masters");
            }
            
        } catch (Exception $e) {
            echo "Setup warning: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Clean up all test data
     */
    public function cleanupTestData() {
        try {
            // Delete material request items first
            if (isset($this->createdRecords['material_requests']) && !empty($this->createdRecords['material_requests'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['material_requests']));
                $this->db->query("DELETE FROM material_request_items WHERE material_request_id IN ($ids)");
                $this->db->query("DELETE FROM material_requests WHERE id IN ($ids)");
            }
            
            // Delete material master items and masters
            if (isset($this->createdRecords['material_masters']) && !empty($this->createdRecords['material_masters'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['material_masters']));
                $this->db->query("DELETE FROM material_master_items WHERE material_master_id IN ($ids)");
                $this->db->query("DELETE FROM material_masters WHERE id IN ($ids)");
            }
            
            // Delete test sites
            if (isset($this->createdRecords['sites']) && !empty($this->createdRecords['sites'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['sites']));
                $this->db->query("DELETE FROM sites WHERE id IN ($ids)");
            }
            
            // Clean up by name pattern
            $this->db->query("DELETE FROM material_request_items WHERE material_request_id IN (SELECT id FROM material_requests WHERE notes LIKE 'Test notes %')");
            $this->db->query("DELETE FROM material_requests WHERE notes LIKE 'Test notes %'");
            $this->db->query("DELETE FROM material_master_items WHERE material_master_id IN (SELECT id FROM material_masters WHERE name LIKE 'Test Master %')");
            $this->db->query("DELETE FROM material_masters WHERE name LIKE 'Test Master %'");
            $this->db->query("DELETE FROM sites WHERE site_name LIKE 'Test Site %'");
            
            // Clean up test products
            if (isset($this->createdRecords['products']) && !empty($this->createdRecords['products'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM products WHERE id IN ($ids)");
            }
            
            // Clean up test users
            if (isset($this->createdRecords['users']) && !empty($this->createdRecords['users'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['users']));
                $this->db->query("DELETE FROM users WHERE id IN ($ids)");
            }
            
            // Clean up test companies
            if (isset($this->createdRecords['companies']) && !empty($this->createdRecords['companies'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['companies']));
                $this->db->query("DELETE FROM companies WHERE id IN ($ids)");
            }
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

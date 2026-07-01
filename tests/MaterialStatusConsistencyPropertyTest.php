<?php
/**
 * Property Test for Material Status Consistency
 * **Feature: material-request-module, Property 4: Material Status Consistency**
 * **Validates: Requirements 2.2, 2.3, 2.4, 2.5, 2.6**
 * 
 * For any site, the material status returned by the API should match the actual state 
 * of its material request: "not_requested" when no request exists, "requested" when pending, 
 * "approved" when approved, "dispatched" when dispatched, and "received" when received.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/MaterialRequestService.php';
require_once __DIR__ . '/../services/MaterialMasterService.php';
require_once __DIR__ . '/../repositories/MaterialRequestRepository.php';

class MaterialStatusConsistencyPropertyTest extends PropertyTestBase {
    
    private $materialRequestService;
    private $materialMasterService;
    private $materialRequestRepository;
    private $createdRecords = [];
    private $testCompanyId;
    private $testUserId;
    private $testProductIds = [];
    private $testMasterIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->materialRequestService = new MaterialRequestService();
        $this->materialMasterService = new MaterialMasterService();
        $this->materialRequestRepository = new MaterialRequestRepository();
        $this->iterations = 20; // As specified in design doc
    }
    
    public function runTests() {
        echo "=== Material Status Consistency Property Tests ===\n\n";
        
        // Setup test data
        $this->setupTestData();
        
        $allPassed = true;
        
        // Test: Site with no request should have "not_requested" status
        $allPassed &= $this->runPropertyTest(
            "Site with no request has 'not_requested' status",
            [$this, 'testNoRequestStatus']
        );
        
        // Test: Site with requested status
        $allPassed &= $this->runPropertyTest(
            "Site with pending request has 'requested' status",
            [$this, 'testRequestedStatus']
        );
        
        // Test: Site with approved status
        $allPassed &= $this->runPropertyTest(
            "Site with approved request has 'approved' status",
            [$this, 'testApprovedStatus']
        );
        
        // Test: Site with dispatched status
        $allPassed &= $this->runPropertyTest(
            "Site with dispatched request has 'dispatched' status",
            [$this, 'testDispatchedStatus']
        );
        
        // Test: Site with received status
        $allPassed &= $this->runPropertyTest(
            "Site with received request has 'received' status",
            [$this, 'testReceivedStatus']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 4: Site with no request should have "not_requested" status
     * **Feature: material-request-module, Property 4: Material Status Consistency**
     * **Validates: Requirements 2.2**
     */
    public function testNoRequestStatus() {
        try {
            // Create a new site with no material request
            $siteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $siteId;
            
            // Get site status from service
            $status = $this->materialRequestService->getSiteStatus($siteId);
            
            $this->assert(
                $status === 'not_requested',
                "Site with no request should have 'not_requested' status, got: '$status'"
            );
            
            // Verify hasActiveRequest returns false
            $hasActive = $this->materialRequestService->hasActiveRequest($siteId);
            $this->assert(!$hasActive, "Site with no request should not have an active request");
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['site_id' => $siteId ?? null]
            ];
        }
    }
    
    /**
     * Property 4: Site with pending request should have "requested" status
     * **Feature: material-request-module, Property 4: Material Status Consistency**
     * **Validates: Requirements 2.3**
     */
    public function testRequestedStatus() {
        try {
            // Create a new site
            $siteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $siteId;
            
            // Get a random Material Master
            $masterId = $this->testMasterIds[array_rand($this->testMasterIds)];
            
            // Create Material Request (status will be 'requested')
            $result = $this->materialRequestService->create(
                $siteId,
                $masterId,
                $this->testUserId,
                $this->testCompanyId
            );
            
            $this->assert($result['success'], "Material Request creation should succeed");
            $this->createdRecords['material_requests'][] = $result['data']['id'];
            
            // Get site status from service
            $status = $this->materialRequestService->getSiteStatus($siteId);
            
            $this->assert(
                $status === 'requested',
                "Site with pending request should have 'requested' status, got: '$status'"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['site_id' => $siteId ?? null]
            ];
        }
    }
    
    /**
     * Property 4: Site with approved request should have "approved" status
     * **Feature: material-request-module, Property 4: Material Status Consistency**
     * **Validates: Requirements 2.4**
     */
    public function testApprovedStatus() {
        try {
            // Create a new site
            $siteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $siteId;
            
            // Get a random Material Master
            $masterId = $this->testMasterIds[array_rand($this->testMasterIds)];
            
            // Create Material Request
            $result = $this->materialRequestService->create(
                $siteId,
                $masterId,
                $this->testUserId,
                $this->testCompanyId
            );
            
            $this->assert($result['success'], "Material Request creation should succeed");
            $requestId = $result['data']['id'];
            $this->createdRecords['material_requests'][] = $requestId;
            
            // Update status to approved
            $updateResult = $this->materialRequestService->updateStatus(
                $requestId,
                'approved',
                $this->testUserId,
                $this->testCompanyId
            );
            
            $this->assert($updateResult['success'], "Status update to 'approved' should succeed");
            
            // Get site status from service
            $status = $this->materialRequestService->getSiteStatus($siteId);
            
            $this->assert(
                $status === 'approved',
                "Site with approved request should have 'approved' status, got: '$status'"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['site_id' => $siteId ?? null]
            ];
        }
    }
    
    /**
     * Property 4: Site with dispatched request should have "dispatched" status
     * **Feature: material-request-module, Property 4: Material Status Consistency**
     * **Validates: Requirements 2.5**
     */
    public function testDispatchedStatus() {
        try {
            // Create a new site
            $siteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $siteId;
            
            // Get a random Material Master
            $masterId = $this->testMasterIds[array_rand($this->testMasterIds)];
            
            // Create Material Request
            $result = $this->materialRequestService->create(
                $siteId,
                $masterId,
                $this->testUserId,
                $this->testCompanyId
            );
            
            $this->assert($result['success'], "Material Request creation should succeed");
            $requestId = $result['data']['id'];
            $this->createdRecords['material_requests'][] = $requestId;
            
            // Update status to approved first
            $approveResult = $this->materialRequestService->updateStatus(
                $requestId,
                'approved',
                $this->testUserId,
                $this->testCompanyId
            );
            $this->assert($approveResult['success'], "Status update to 'approved' should succeed");
            
            // Update status to dispatched
            $dispatchResult = $this->materialRequestService->updateStatus(
                $requestId,
                'dispatched',
                $this->testUserId,
                $this->testCompanyId
            );
            $this->assert($dispatchResult['success'], "Status update to 'dispatched' should succeed");
            
            // Get site status from service
            $status = $this->materialRequestService->getSiteStatus($siteId);
            
            $this->assert(
                $status === 'dispatched',
                "Site with dispatched request should have 'dispatched' status, got: '$status'"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['site_id' => $siteId ?? null]
            ];
        }
    }
    
    /**
     * Property 4: Site with received request should have "received" status
     * **Feature: material-request-module, Property 4: Material Status Consistency**
     * **Validates: Requirements 2.6**
     */
    public function testReceivedStatus() {
        try {
            // Create a new site
            $siteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $siteId;
            
            // Get a random Material Master
            $masterId = $this->testMasterIds[array_rand($this->testMasterIds)];
            
            // Create Material Request
            $result = $this->materialRequestService->create(
                $siteId,
                $masterId,
                $this->testUserId,
                $this->testCompanyId
            );
            
            $this->assert($result['success'], "Material Request creation should succeed");
            $requestId = $result['data']['id'];
            $this->createdRecords['material_requests'][] = $requestId;
            
            // Update status through the workflow: requested -> approved -> dispatched -> received
            $approveResult = $this->materialRequestService->updateStatus(
                $requestId,
                'approved',
                $this->testUserId,
                $this->testCompanyId
            );
            $this->assert($approveResult['success'], "Status update to 'approved' should succeed");
            
            $dispatchResult = $this->materialRequestService->updateStatus(
                $requestId,
                'dispatched',
                $this->testUserId,
                $this->testCompanyId
            );
            $this->assert($dispatchResult['success'], "Status update to 'dispatched' should succeed");
            
            $receiveResult = $this->materialRequestService->updateStatus(
                $requestId,
                'received',
                $this->testUserId,
                $this->testCompanyId
            );
            $this->assert($receiveResult['success'], "Status update to 'received' should succeed");
            
            // Get site status from service
            $status = $this->materialRequestService->getSiteStatus($siteId);
            
            $this->assert(
                $status === 'received',
                "Site with received request should have 'received' status, got: '$status'"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['site_id' => $siteId ?? null]
            ];
        }
    }
    
    /**
     * Create a test site
     */
    private function createTestSite(): int {
        $siteName = 'Test Site Status ' . $this->generateRandomString(10);
        
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
     * Setup test data (company, user, products, Material Masters)
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
                    ['Test Company Status', 'ADV', 'ACTIVE'],
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
                    ['test_status_' . $this->generateRandomString(5), 'test_status_' . $this->generateRandomString(5) . '@test.com', password_hash('test123', PASSWORD_DEFAULT), 'Test', 'User', $this->testCompanyId, 1, 1],
                    'sssssiii'
                );
                $this->testUserId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['users'][] = $this->testUserId;
            }
            
            // Get existing products or create test products
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
                    ['Test Product Status ' . $this->generateRandomString(5), 'unit', 0, 0, 'consumable', 'active'],
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
                    'name' => 'Test Master Status ' . $this->generateRandomString(10),
                    'description' => 'Test description for status test',
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
     * Clean up all test data created during tests
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
            $this->db->query("DELETE FROM material_request_items WHERE material_request_id IN (SELECT id FROM material_requests WHERE site_id IN (SELECT id FROM sites WHERE site_name LIKE 'Test Site Status %'))");
            $this->db->query("DELETE FROM material_requests WHERE site_id IN (SELECT id FROM sites WHERE site_name LIKE 'Test Site Status %')");
            $this->db->query("DELETE FROM material_master_items WHERE material_master_id IN (SELECT id FROM material_masters WHERE name LIKE 'Test Master Status %')");
            $this->db->query("DELETE FROM material_masters WHERE name LIKE 'Test Master Status %'");
            $this->db->query("DELETE FROM sites WHERE site_name LIKE 'Test Site Status %'");
            
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

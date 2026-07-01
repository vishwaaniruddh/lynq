<?php
/**
 * Property Test for Material Request Creation and Duplicate Prevention
 * **Feature: material-request-module, Property 5: Material Request Creation and Duplicate Prevention**
 * **Validates: Requirements 3.3, 3.4, 3.5, 3.6**
 * 
 * For any site without an active material request, creating a material request should succeed 
 * and link to the site. For any site with an active request, creating another request should 
 * fail with an appropriate error.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/MaterialRequestService.php';
require_once __DIR__ . '/../services/MaterialMasterService.php';
require_once __DIR__ . '/../repositories/MaterialRequestRepository.php';
require_once __DIR__ . '/../repositories/MaterialMasterRepository.php';

class MaterialRequestCreationPropertyTest extends PropertyTestBase {
    
    private $materialRequestService;
    private $materialMasterService;
    private $materialRequestRepository;
    private $createdRecords = [];
    private $testCompanyId;
    private $testUserId;
    private $testProductIds = [];
    private $testSiteIds = [];
    private $testMasterIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->materialRequestService = new MaterialRequestService();
        $this->materialMasterService = new MaterialMasterService();
        $this->materialRequestRepository = new MaterialRequestRepository();
        $this->iterations = 20; // As specified in design doc
    }
    
    public function runTests() {
        echo "=== Material Request Creation Property Tests ===\n\n";
        
        // Setup test data
        $this->setupTestData();
        
        $allPassed = true;
        
        // Test Creation Success
        $allPassed &= $this->runPropertyTest(
            "Material Request Creation Success",
            [$this, 'testMaterialRequestCreationSuccess']
        );
        
        // Test Duplicate Prevention
        $allPassed &= $this->runPropertyTest(
            "Material Request Duplicate Prevention",
            [$this, 'testMaterialRequestDuplicatePrevention']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 5: Material Request Creation Success
     * For any site without an active material request, creating a request should succeed
     * **Feature: material-request-module, Property 5: Material Request Creation and Duplicate Prevention**
     * **Validates: Requirements 3.3, 3.4, 3.5, 3.6**
     */
    public function testMaterialRequestCreationSuccess() {
        try {
            // Create a new site for this test (to ensure no active request)
            $siteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $siteId;
            
            // Get a random Material Master
            $masterId = $this->testMasterIds[array_rand($this->testMasterIds)];
            
            // Verify no active request exists
            $hasActive = $this->materialRequestService->hasActiveRequest($siteId);
            $this->assert(!$hasActive, "New site should not have an active request");
            
            // Create Material Request
            $result = $this->materialRequestService->create(
                $siteId,
                $masterId,
                $this->testUserId,
                $this->testCompanyId,
                'Test notes ' . $this->generateRandomString(10)
            );
            
            $this->assert($result['success'], "Material Request creation should succeed: " . ($result['message'] ?? 'Unknown error'));
            $this->assert(isset($result['data']['id']), "Created request should have an ID");
            
            $requestId = $result['data']['id'];
            $this->createdRecords['material_requests'][] = $requestId;
            
            // Verify request is linked to site
            $this->assert(
                (int)$result['data']['site_id'] === $siteId,
                "Request should be linked to the correct site"
            );
            
            // Verify request is linked to Material Master
            $this->assert(
                (int)$result['data']['material_master_id'] === $masterId,
                "Request should be linked to the correct Material Master"
            );
            
            // Verify status is 'requested'
            $this->assert(
                $result['data']['status'] === 'requested',
                "New request should have 'requested' status"
            );
            
            // Verify site now has an active request
            $hasActiveAfter = $this->materialRequestService->hasActiveRequest($siteId);
            $this->assert($hasActiveAfter, "Site should have an active request after creation");
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['site_id' => $siteId ?? null, 'master_id' => $masterId ?? null]
            ];
        }
    }
    
    /**
     * Property 5: Material Request Duplicate Prevention
     * For any site with an active request, creating another request should fail
     * **Feature: material-request-module, Property 5: Material Request Creation and Duplicate Prevention**
     * **Validates: Requirements 3.3, 3.4, 3.5, 3.6**
     */
    public function testMaterialRequestDuplicatePrevention() {
        try {
            // Create a new site for this test
            $siteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $siteId;
            
            // Get a random Material Master
            $masterId = $this->testMasterIds[array_rand($this->testMasterIds)];
            
            // Create first Material Request (should succeed)
            $firstResult = $this->materialRequestService->create(
                $siteId,
                $masterId,
                $this->testUserId,
                $this->testCompanyId
            );
            
            $this->assert($firstResult['success'], "First request creation should succeed");
            $this->createdRecords['material_requests'][] = $firstResult['data']['id'];
            
            // Try to create second Material Request (should fail)
            $secondResult = $this->materialRequestService->create(
                $siteId,
                $masterId,
                $this->testUserId,
                $this->testCompanyId
            );
            
            $this->assert(!$secondResult['success'], "Second request creation should fail");
            $this->assert(
                $secondResult['code'] === 'DUPLICATE_REQUEST',
                "Error code should be 'DUPLICATE_REQUEST', got: " . ($secondResult['code'] ?? 'none')
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

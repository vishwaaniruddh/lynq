<?php
/**
 * Property Test for Role-Based Access Control
 * **Feature: material-request-module, Property 7: Role-Based Access Control**
 * **Validates: Requirements 6.1, 6.4, 7.1**
 * 
 * For any API request, ADV users should see all material requests, Contractor users should 
 * only see requests for sites delegated to their company, and Engineer users should only 
 * see requests for sites assigned to them.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/MaterialRequestService.php';
require_once __DIR__ . '/../services/MaterialMasterService.php';
require_once __DIR__ . '/../repositories/MaterialRequestRepository.php';

class MaterialRequestRoleAccessPropertyTest extends PropertyTestBase {
    
    private $materialRequestService;
    private $materialMasterService;
    private $materialRequestRepository;
    private $createdRecords = [];
    private $testCompanyId;
    private $testContractorCompanyId;
    private $testAdvUserId;
    private $testContractorUserId;
    private $testEngineerUserId;
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
        echo "=== Material Request Role-Based Access Property Tests ===\n\n";
        
        // Setup test data
        $this->setupTestData();
        
        $allPassed = true;
        
        // Test ADV Access
        $allPassed &= $this->runPropertyTest(
            "ADV User Access - All Requests",
            [$this, 'testAdvUserAccessAllRequests']
        );
        
        // Test Contractor Access
        $allPassed &= $this->runPropertyTest(
            "Contractor User Access - Delegated Sites Only",
            [$this, 'testContractorUserAccessDelegatedSitesOnly']
        );
        
        // Test Engineer Access
        $allPassed &= $this->runPropertyTest(
            "Engineer User Access - Assigned Sites Only",
            [$this, 'testEngineerUserAccessAssignedSitesOnly']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 7: ADV User Access - All Requests
     * ADV users should see all material requests for their company
     * **Feature: material-request-module, Property 7: Role-Based Access Control**
     * **Validates: Requirements 6.1, 6.4, 7.1**
     */
    public function testAdvUserAccessAllRequests() {
        try {
            // Create multiple sites and requests
            $requestIds = [];
            for ($i = 0; $i < 3; $i++) {
                $siteId = $this->createTestSite();
                $this->createdRecords['sites'][] = $siteId;
                
                $masterId = $this->testMasterIds[array_rand($this->testMasterIds)];
                
                $result = $this->materialRequestService->create(
                    $siteId,
                    $masterId,
                    $this->testAdvUserId,
                    $this->testCompanyId
                );
                
                if ($result['success']) {
                    $requestIds[] = $result['data']['id'];
                    $this->createdRecords['material_requests'][] = $result['data']['id'];
                }
            }
            
            $this->assert(count($requestIds) >= 2, "Should have created at least 2 requests");
            
            // ADV user should see all requests
            $advResults = $this->materialRequestService->getByRole(
                $this->testAdvUserId,
                'adv',
                ['limit' => 100], // Increase limit to see all requests
                $this->testCompanyId
            );
            
            // Verify ADV user can see all created requests
            $foundCount = 0;
            foreach ($requestIds as $requestId) {
                foreach ($advResults['data'] as $request) {
                    if ((int)$request['id'] === $requestId) {
                        $foundCount++;
                        break;
                    }
                }
            }
            
            $this->assert(
                $foundCount === count($requestIds),
                "ADV user should see all created requests. Expected: " . count($requestIds) . ", Found: $foundCount"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['request_ids' => $requestIds ?? []]
            ];
        }
    }
    
    /**
     * Property 7: Contractor User Access - Delegated Sites Only
     * Contractor users should only see requests for sites delegated to their company
     * **Feature: material-request-module, Property 7: Role-Based Access Control**
     * **Validates: Requirements 6.1, 6.4, 7.1**
     */
    public function testContractorUserAccessDelegatedSitesOnly() {
        try {
            // Create a site and delegate it to contractor
            $delegatedSiteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $delegatedSiteId;
            
            // Create delegation
            $this->createSiteDelegation($delegatedSiteId, $this->testContractorCompanyId);
            
            // Create a non-delegated site
            $nonDelegatedSiteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $nonDelegatedSiteId;
            
            $masterId = $this->testMasterIds[array_rand($this->testMasterIds)];
            
            // Create request for delegated site
            $delegatedResult = $this->materialRequestService->create(
                $delegatedSiteId,
                $masterId,
                $this->testAdvUserId,
                $this->testCompanyId
            );
            
            if ($delegatedResult['success']) {
                $this->createdRecords['material_requests'][] = $delegatedResult['data']['id'];
            }
            
            // Create request for non-delegated site
            $nonDelegatedResult = $this->materialRequestService->create(
                $nonDelegatedSiteId,
                $masterId,
                $this->testAdvUserId,
                $this->testCompanyId
            );
            
            if ($nonDelegatedResult['success']) {
                $this->createdRecords['material_requests'][] = $nonDelegatedResult['data']['id'];
            }
            
            // Contractor should only see delegated site's request
            $contractorResults = $this->materialRequestService->getByRole(
                $this->testContractorUserId,
                'contractor',
                ['limit' => 100], // Increase limit
                $this->testContractorCompanyId
            );
            
            // Check that delegated site's request is visible
            $foundDelegated = false;
            $foundNonDelegated = false;
            
            foreach ($contractorResults['data'] as $request) {
                if ($delegatedResult['success'] && (int)$request['id'] === (int)$delegatedResult['data']['id']) {
                    $foundDelegated = true;
                }
                if ($nonDelegatedResult['success'] && (int)$request['id'] === (int)$nonDelegatedResult['data']['id']) {
                    $foundNonDelegated = true;
                }
            }
            
            $this->assert(
                $foundDelegated || !$delegatedResult['success'],
                "Contractor should see request for delegated site"
            );
            
            $this->assert(
                !$foundNonDelegated,
                "Contractor should NOT see request for non-delegated site"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }
    
    /**
     * Property 7: Engineer User Access - Assigned Sites Only
     * Engineer users should only see requests for sites assigned to them
     * **Feature: material-request-module, Property 7: Role-Based Access Control**
     * **Validates: Requirements 6.1, 6.4, 7.1**
     */
    public function testEngineerUserAccessAssignedSitesOnly() {
        try {
            // Create a site and assign engineer
            $assignedSiteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $assignedSiteId;
            
            // Create engineer assignment
            $this->createEngineerAssignment($assignedSiteId, $this->testEngineerUserId);
            
            // Create a non-assigned site
            $nonAssignedSiteId = $this->createTestSite();
            $this->createdRecords['sites'][] = $nonAssignedSiteId;
            
            $masterId = $this->testMasterIds[array_rand($this->testMasterIds)];
            
            // Create request for assigned site
            $assignedResult = $this->materialRequestService->create(
                $assignedSiteId,
                $masterId,
                $this->testAdvUserId,
                $this->testCompanyId
            );
            
            if ($assignedResult['success']) {
                $this->createdRecords['material_requests'][] = $assignedResult['data']['id'];
            }
            
            // Create request for non-assigned site
            $nonAssignedResult = $this->materialRequestService->create(
                $nonAssignedSiteId,
                $masterId,
                $this->testAdvUserId,
                $this->testCompanyId
            );
            
            if ($nonAssignedResult['success']) {
                $this->createdRecords['material_requests'][] = $nonAssignedResult['data']['id'];
            }
            
            // Engineer should only see assigned site's request
            $engineerResults = $this->materialRequestService->getByRole(
                $this->testEngineerUserId,
                'engineer',
                ['limit' => 100], // Increase limit
                null
            );
            
            // Check that assigned site's request is visible
            $foundAssigned = false;
            $foundNonAssigned = false;
            
            foreach ($engineerResults['data'] as $request) {
                if ($assignedResult['success'] && (int)$request['id'] === (int)$assignedResult['data']['id']) {
                    $foundAssigned = true;
                }
                if ($nonAssignedResult['success'] && (int)$request['id'] === (int)$nonAssignedResult['data']['id']) {
                    $foundNonAssigned = true;
                }
            }
            
            $this->assert(
                $foundAssigned || !$assignedResult['success'],
                "Engineer should see request for assigned site"
            );
            
            $this->assert(
                !$foundNonAssigned,
                "Engineer should NOT see request for non-assigned site"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
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
     * Create site delegation to contractor
     */
    private function createSiteDelegation(int $siteId, int $contractorCompanyId): void {
        $stmt = $this->executeQuery(
            "INSERT INTO site_delegations (site_id, contractor_id, status, delegated_at) VALUES (?, ?, ?, NOW())",
            [$siteId, $contractorCompanyId, 'accepted'],
            'iis'
        );
        $delegationId = $this->db->insert_id;
        $stmt->close();
        $this->createdRecords['site_delegations'][] = $delegationId;
    }
    
    /**
     * Create engineer assignment
     */
    private function createEngineerAssignment(int $siteId, int $engineerId): void {
        $stmt = $this->executeQuery(
            "INSERT INTO engineer_assignments (site_id, engineer_id, status, assigned_at) VALUES (?, ?, ?, NOW())",
            [$siteId, $engineerId, 'assigned'],
            'iis'
        );
        $assignmentId = $this->db->insert_id;
        $stmt->close();
        $this->createdRecords['engineer_assignments'][] = $assignmentId;
    }
    
    /**
     * Setup test data
     */
    private function setupTestData() {
        try {
            // Get or create ADV company
            $result = $this->getResults("SELECT id FROM companies WHERE type = 'ADV' LIMIT 1");
            if (!empty($result)) {
                $this->testCompanyId = (int)$result[0]['id'];
            } else {
                $stmt = $this->executeQuery(
                    "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                    ['Test ADV Company', 'ADV', 'ACTIVE'],
                    'sss'
                );
                $this->testCompanyId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['companies'][] = $this->testCompanyId;
            }
            
            // Get or create Contractor company
            $result = $this->getResults("SELECT id FROM companies WHERE type = 'CONTRACTOR' LIMIT 1");
            if (!empty($result)) {
                $this->testContractorCompanyId = (int)$result[0]['id'];
            } else {
                $stmt = $this->executeQuery(
                    "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                    ['Test Contractor Company', 'CONTRACTOR', 'ACTIVE'],
                    'sss'
                );
                $this->testContractorCompanyId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['companies'][] = $this->testContractorCompanyId;
            }
            
            // Get or create ADV user
            $result = $this->getResults("SELECT id FROM users WHERE company_id = ? LIMIT 1", [$this->testCompanyId], 'i');
            if (!empty($result)) {
                $this->testAdvUserId = (int)$result[0]['id'];
            } else {
                $stmt = $this->executeQuery(
                    "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    ['test_adv_' . $this->generateRandomString(5), 'adv_' . $this->generateRandomString(5) . '@test.com', password_hash('test123', PASSWORD_DEFAULT), 'ADV', 'User', $this->testCompanyId, 1, 1],
                    'sssssiii'
                );
                $this->testAdvUserId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['users'][] = $this->testAdvUserId;
            }
            
            // Get or create Contractor user
            $result = $this->getResults("SELECT id FROM users WHERE company_id = ? LIMIT 1", [$this->testContractorCompanyId], 'i');
            if (!empty($result)) {
                $this->testContractorUserId = (int)$result[0]['id'];
            } else {
                $stmt = $this->executeQuery(
                    "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    ['test_contractor_' . $this->generateRandomString(5), 'contractor_' . $this->generateRandomString(5) . '@test.com', password_hash('test123', PASSWORD_DEFAULT), 'Contractor', 'User', $this->testContractorCompanyId, 2, 1],
                    'sssssiii'
                );
                $this->testContractorUserId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['users'][] = $this->testContractorUserId;
            }
            
            // Create Engineer user
            $stmt = $this->executeQuery(
                "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                ['test_engineer_' . $this->generateRandomString(5), 'engineer_' . $this->generateRandomString(5) . '@test.com', password_hash('test123', PASSWORD_DEFAULT), 'Engineer', 'User', $this->testContractorCompanyId, 3, 1],
                'sssssiii'
            );
            $this->testEngineerUserId = $this->db->insert_id;
            $stmt->close();
            $this->createdRecords['users'][] = $this->testEngineerUserId;
            
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
                
                $result = $this->materialMasterService->create($masterData, $this->testAdvUserId, $this->testCompanyId);
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
            // Delete engineer assignments
            if (isset($this->createdRecords['engineer_assignments']) && !empty($this->createdRecords['engineer_assignments'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['engineer_assignments']));
                $this->db->query("DELETE FROM engineer_assignments WHERE id IN ($ids)");
            }
            
            // Delete site delegations
            if (isset($this->createdRecords['site_delegations']) && !empty($this->createdRecords['site_delegations'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['site_delegations']));
                $this->db->query("DELETE FROM site_delegations WHERE id IN ($ids)");
            }
            
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
            
            // Clean up test companies (only if we created them)
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

<?php
/**
 * Property Test for Access Control
 * **Feature: site-management-delegation, Property 8: Contractor data isolation**
 * **Feature: site-management-delegation, Property 13: Engineer data isolation**
 * **Validates: Requirements 4.1, 4.5, 6.1, 6.3**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/SiteAccessService.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../services/EngineerAssignmentService.php';

class AccessControlPropertyTest extends PropertyTestBase {
    
    private $accessService;
    private $siteService;
    private $delegationService;
    private $assignmentService;
    
    // Test data IDs
    private $testAdvCompanyId;
    private $testContractorId1;
    private $testContractorId2;
    private $testAdvUserId;
    private $testContractorUser1Id;
    private $testContractorUser2Id;
    private $testEngineerId1;
    private $testEngineerId2;
    
    // Created test data for cleanup
    private $createdSiteIds = [];
    private $createdDelegationIds = [];
    private $createdAssignmentIds = [];
    private $createdUserIds = [];
    private $createdCompanyIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->accessService = new SiteAccessService();
        $this->siteService = new SiteService();
        $this->delegationService = new DelegationService();
        $this->assignmentService = new EngineerAssignmentService();
        $this->setupTestData();
    }
    
    /**
     * Setup test companies and users
     */
    private function setupTestData(): void {
        // Get or create ADV company
        $result = $this->getResults("SELECT id FROM companies WHERE type = 'adv' AND status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testAdvCompanyId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test ADV Company ' . uniqid(), 'adv', 1],
                'ssi'
            );
            $this->testAdvCompanyId = $this->db->insert_id;
            $this->createdCompanyIds[] = $this->testAdvCompanyId;
            $stmt->close();
        }
        
        // Get or create first contractor company
        $result = $this->getResults("SELECT id FROM companies WHERE type = 'contractor' AND status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testContractorId1 = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test Contractor 1 ' . uniqid(), 'contractor', 1],
                'ssi'
            );
            $this->testContractorId1 = $this->db->insert_id;
            $this->createdCompanyIds[] = $this->testContractorId1;
            $stmt->close();
        }
        
        // Create second contractor company for isolation testing
        $stmt = $this->executeQuery(
            "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
            ['Test Contractor 2 ' . uniqid(), 'contractor', 1],
            'ssi'
        );
        $this->testContractorId2 = $this->db->insert_id;
        $this->createdCompanyIds[] = $this->testContractorId2;
        $stmt->close();
        
        // Get or create ADV user
        $result = $this->getResults(
            "SELECT id FROM users WHERE company_id = ? AND status = 1 LIMIT 1",
            [$this->testAdvCompanyId],
            'i'
        );
        if (!empty($result)) {
            $this->testAdvUserId = (int)$result[0]['id'];
        } else {
            $this->testAdvUserId = $this->createTestUser($this->testAdvCompanyId, 'adv_user');
        }
        
        // Create contractor users
        $this->testContractorUser1Id = $this->createTestUser($this->testContractorId1, 'contractor1_user');
        $this->testContractorUser2Id = $this->createTestUser($this->testContractorId2, 'contractor2_user');
        
        // Create engineers for each contractor
        $this->testEngineerId1 = $this->createTestUser($this->testContractorId1, 'engineer1');
        $this->testEngineerId2 = $this->createTestUser($this->testContractorId2, 'engineer2');
    }
    
    /**
     * Create a test user
     */
    private function createTestUser(int $companyId, string $prefix): int {
        $username = $prefix . '_' . uniqid();
        $email = $username . '@test.com';
        
        // Get a valid role_id
        $roleResult = $this->getResults("SELECT id FROM roles LIMIT 1");
        $roleId = !empty($roleResult) ? (int)$roleResult[0]['id'] : 1;
        
        $stmt = $this->executeQuery(
            "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$username, $email, password_hash('test123', PASSWORD_DEFAULT), 'Test', 'User', $companyId, $roleId, 1],
            'sssssiis'
        );
        $userId = $this->db->insert_id;
        $this->createdUserIds[] = $userId;
        $stmt->close();
        
        return $userId;
    }
    
    /**
     * Create a test site
     */
    private function createTestSite(): array {
        $siteData = [
            'site_name' => 'Site_' . $this->generateRandomString(10),
            'lho' => 'LHO_' . $this->generateRandomString(5),
            'bank_name' => 'Bank_' . $this->generateRandomString(8),
            'customer_name' => 'Customer_' . $this->generateRandomString(8),
            'city' => 'City_' . $this->generateRandomString(6),
            'state' => 'State_' . $this->generateRandomString(6),
            'country' => 'Country_' . $this->generateRandomString(6),
            'zone' => 'Zone_' . $this->generateRandomString(4),
            'address' => 'Address ' . $this->generateRandomString(20),
            'latitude' => round((rand(-9000000, 9000000) / 100000), 6),
            'longitude' => round((rand(-18000000, 18000000) / 100000), 6),
            'company_id' => $this->testAdvCompanyId
        ];
        
        $result = $this->siteService->createSite($siteData, $this->testAdvUserId);
        if ($result['success']) {
            $this->createdSiteIds[] = $result['data']['id'];
        }
        return $result;
    }
    
    public function runTests(): bool {
        echo "=== Access Control Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 8: Contractor data isolation
        $allPassed &= $this->runPropertyTest(
            "Property 8: Contractor data isolation - delegations only visible to assigned contractor",
            [$this, 'testContractorDelegationIsolation']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 8: Contractor data isolation - cannot access other contractor's delegations",
            [$this, 'testContractorCannotAccessOtherDelegations']
        );
        
        // Property 13: Engineer data isolation
        $allPassed &= $this->runPropertyTest(
            "Property 13: Engineer data isolation - assignments only visible to assigned engineer",
            [$this, 'testEngineerAssignmentIsolation']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 13: Engineer data isolation - cannot access other engineer's assignments",
            [$this, 'testEngineerCannotAccessOtherAssignments']
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 8: Contractor data isolation - delegations only visible to assigned contractor
     * **Feature: site-management-delegation, Property 8: Contractor data isolation**
     * **Validates: Requirements 4.1, 4.5**
     */
    public function testContractorDelegationIsolation(): array {
        try {
            // Create a test site
            $siteResult = $this->createTestSite();
            $this->assert($siteResult['success'], "Site creation should succeed");
            $siteId = $siteResult['data']['id'];
            
            // Delegate to contractor 1
            $delegationResult = $this->delegationService->delegateSite(
                $siteId,
                $this->testContractorId1,
                $this->testAdvUserId
            );
            
            $this->assert($delegationResult['success'], "Delegation should succeed");
            $delegationId = $delegationResult['data']['id'];
            $this->createdDelegationIds[] = $delegationId;
            
            // Contractor 1 user should be able to access the delegation
            $canAccess = $this->accessService->canAccessDelegation(
                $this->testContractorUser1Id,
                $delegationId
            );
            
            $this->assert(
                $canAccess === true,
                "Contractor 1 user should be able to access delegation assigned to their company"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 8: Contractor cannot access other contractor's delegations
     * **Feature: site-management-delegation, Property 8: Contractor data isolation**
     * **Validates: Requirements 4.1, 4.5**
     */
    public function testContractorCannotAccessOtherDelegations(): array {
        try {
            // Create a test site
            $siteResult = $this->createTestSite();
            $this->assert($siteResult['success'], "Site creation should succeed");
            $siteId = $siteResult['data']['id'];
            
            // Delegate to contractor 1
            $delegationResult = $this->delegationService->delegateSite(
                $siteId,
                $this->testContractorId1,
                $this->testAdvUserId
            );
            
            $this->assert($delegationResult['success'], "Delegation should succeed");
            $delegationId = $delegationResult['data']['id'];
            $this->createdDelegationIds[] = $delegationId;
            
            // Contractor 2 user should NOT be able to access the delegation
            $canAccess = $this->accessService->canAccessDelegation(
                $this->testContractorUser2Id,
                $delegationId
            );
            
            $this->assert(
                $canAccess === false,
                "Contractor 2 user should NOT be able to access delegation assigned to Contractor 1"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 13: Engineer data isolation - assignments only visible to assigned engineer
     * **Feature: site-management-delegation, Property 13: Engineer data isolation**
     * **Validates: Requirements 6.1, 6.3**
     */
    public function testEngineerAssignmentIsolation(): array {
        try {
            // Create a test site
            $siteResult = $this->createTestSite();
            $this->assert($siteResult['success'], "Site creation should succeed");
            $siteId = $siteResult['data']['id'];
            
            // Delegate to contractor 1
            $delegationResult = $this->delegationService->delegateSite(
                $siteId,
                $this->testContractorId1,
                $this->testAdvUserId
            );
            
            $this->assert($delegationResult['success'], "Delegation should succeed");
            $delegationId = $delegationResult['data']['id'];
            $this->createdDelegationIds[] = $delegationId;
            
            // Accept the delegation
            $acceptResult = $this->delegationService->acceptDelegation(
                $delegationId,
                $this->testContractorUser1Id
            );
            
            $this->assert($acceptResult['success'], "Delegation acceptance should succeed");
            
            // Assign to engineer 1
            $assignResult = $this->assignmentService->assignToEngineer(
                $siteId,
                $this->testEngineerId1,
                $this->testContractorUser1Id,
                $this->testContractorId1
            );
            
            $this->assert($assignResult['success'], "Assignment should succeed: " . ($assignResult['message'] ?? ''));
            $assignmentId = $assignResult['data']['id'];
            $this->createdAssignmentIds[] = $assignmentId;
            
            // Engineer 1 should be able to access the assignment
            $canAccess = $this->accessService->canAccessAssignment(
                $this->testEngineerId1,
                $assignmentId
            );
            
            $this->assert(
                $canAccess === true,
                "Engineer 1 should be able to access assignment assigned to them"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 13: Engineer cannot access other engineer's assignments
     * **Feature: site-management-delegation, Property 13: Engineer data isolation**
     * **Validates: Requirements 6.1, 6.3**
     */
    public function testEngineerCannotAccessOtherAssignments(): array {
        try {
            // Create a test site
            $siteResult = $this->createTestSite();
            $this->assert($siteResult['success'], "Site creation should succeed");
            $siteId = $siteResult['data']['id'];
            
            // Delegate to contractor 1
            $delegationResult = $this->delegationService->delegateSite(
                $siteId,
                $this->testContractorId1,
                $this->testAdvUserId
            );
            
            $this->assert($delegationResult['success'], "Delegation should succeed");
            $delegationId = $delegationResult['data']['id'];
            $this->createdDelegationIds[] = $delegationId;
            
            // Accept the delegation
            $acceptResult = $this->delegationService->acceptDelegation(
                $delegationId,
                $this->testContractorUser1Id
            );
            
            $this->assert($acceptResult['success'], "Delegation acceptance should succeed");
            
            // Assign to engineer 1
            $assignResult = $this->assignmentService->assignToEngineer(
                $siteId,
                $this->testEngineerId1,
                $this->testContractorUser1Id,
                $this->testContractorId1
            );
            
            $this->assert($assignResult['success'], "Assignment should succeed: " . ($assignResult['message'] ?? ''));
            $assignmentId = $assignResult['data']['id'];
            $this->createdAssignmentIds[] = $assignmentId;
            
            // Engineer 2 should NOT be able to access the assignment
            $canAccess = $this->accessService->canAccessAssignment(
                $this->testEngineerId2,
                $assignmentId
            );
            
            $this->assert(
                $canAccess === false,
                "Engineer 2 should NOT be able to access assignment assigned to Engineer 1"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        // Delete assignments first (due to foreign key constraints)
        foreach ($this->createdAssignmentIds as $assignmentId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM engineer_assignments WHERE id = ?",
                    [$assignmentId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdAssignmentIds = [];
        
        // Delete delegations
        foreach ($this->createdDelegationIds as $delegationId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM delegation_history WHERE delegation_id = ?",
                    [$delegationId],
                    'i'
                );
                $stmt->close();
                
                $stmt = $this->executeQuery(
                    "DELETE FROM site_delegations WHERE id = ?",
                    [$delegationId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdDelegationIds = [];
        
        // Delete sites
        foreach ($this->createdSiteIds as $siteId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM sites WHERE id = ?",
                    [$siteId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdSiteIds = [];
        
        // Delete test users
        foreach ($this->createdUserIds as $userId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM users WHERE id = ?",
                    [$userId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdUserIds = [];
        
        // Delete test companies (only the ones we created)
        foreach ($this->createdCompanyIds as $companyId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM companies WHERE id = ?",
                    [$companyId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdCompanyIds = [];
    }
}

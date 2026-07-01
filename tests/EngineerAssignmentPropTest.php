<?php
/**
 * Property Test for Engineer Assignment Operations
 * **Feature: site-management-delegation, Property 10: Engineer assignment authorization**
 * **Feature: site-management-delegation, Property 11: Engineer assignment audit trail**
 * **Feature: site-management-delegation, Property 12: No duplicate active engineer assignments**
 * **Validates: Requirements 5.1, 5.2, 5.4, 5.5**
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/EngineerAssignmentService.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../services/SiteService.php';

class EngineerAssignmentPropTest extends PropertyTestBase {
    
    private $assignmentService;
    private $delegationService;
    private $siteService;
    private $testAdvCompanyId;
    private $testContractorId;
    private $testUserId;
    private $testEngineerId;
    private $createdSiteIds = [];
    private $createdDelegationIds = [];
    private $createdAssignmentIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->assignmentService = new EngineerAssignmentService();
        $this->delegationService = new DelegationService();
        $this->siteService = new SiteService();
        $this->setupTestData();
    }
    
    private function setupTestData(): void {
        $result = $this->getResults("SELECT id FROM companies WHERE type = 'adv' AND status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testAdvCompanyId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery("INSERT INTO companies (name, type, status) VALUES (?, ?, ?)", ['Test ADV ' . uniqid(), 'adv', 1], 'ssi');
            $this->testAdvCompanyId = $this->db->insert_id;
            $stmt->close();
        }
        
        $result = $this->getResults("SELECT id FROM companies WHERE type = 'contractor' AND status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testContractorId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery("INSERT INTO companies (name, type, status) VALUES (?, ?, ?)", ['Test Contractor ' . uniqid(), 'contractor', 1], 'ssi');
            $this->testContractorId = $this->db->insert_id;
            $stmt->close();
        }

        
        $result = $this->getResults("SELECT id FROM users WHERE status = 1 LIMIT 1");
        $this->testUserId = !empty($result) ? (int)$result[0]['id'] : 1;
        
        $result = $this->getResults("SELECT id FROM users WHERE company_id = ? AND status = 1 LIMIT 1", [$this->testContractorId], 'i');
        if (!empty($result)) {
            $this->testEngineerId = (int)$result[0]['id'];
        } else {
            $result = $this->getResults("SELECT id FROM users WHERE status = 1 ORDER BY id DESC LIMIT 1");
            $this->testEngineerId = !empty($result) ? (int)$result[0]['id'] : 2;
        }
    }
    
    public function runTests(): bool {
        echo "=== Engineer Assignment Property Tests ===\n\n";
        $allPassed = true;
        
        $allPassed &= $this->runPropertyTest("Property 10: Assignment requires accepted delegation", [$this, 'testAssignmentRequiresAcceptedDelegation']);
        $allPassed &= $this->runPropertyTest("Property 10: Assignment succeeds with accepted delegation", [$this, 'testAssignmentSucceedsWithAcceptedDelegation']);
        $allPassed &= $this->runPropertyTest("Property 11: Assignment creates proper audit trail", [$this, 'testAssignmentAuditTrail']);
        $allPassed &= $this->runPropertyTest("Property 12: No duplicate active assignments", [$this, 'testNoDuplicateActiveAssignments']);
        
        $this->cleanupTestData();
        return $allPassed;
    }
    
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
            'latitude' => round(rand(-9000000, 9000000) / 100000, 6),
            'longitude' => round(rand(-18000000, 18000000) / 100000, 6),
            'company_id' => $this->testAdvCompanyId
        ];
        $result = $this->siteService->createSite($siteData, $this->testUserId);
        if ($result['success']) { $this->createdSiteIds[] = $result['data']['id']; }
        return $result;
    }
    
    private function createSiteWithAcceptedDelegation(): array {
        $siteResult = $this->createTestSite();
        if (!$siteResult['success']) { throw new Exception("Failed to create test site: " . ($siteResult['message'] ?? 'Unknown')); }
        $siteId = $siteResult['data']['id'];
        
        $delegationResult = $this->delegationService->delegateSite($siteId, $this->testContractorId, $this->testUserId);
        if (!$delegationResult['success']) { throw new Exception("Failed to delegate site: " . ($delegationResult['message'] ?? 'Unknown')); }
        $delegationId = $delegationResult['data']['id'];
        $this->createdDelegationIds[] = $delegationId;
        
        $acceptResult = $this->delegationService->acceptDelegation($delegationId, $this->testUserId);
        if (!$acceptResult['success']) { throw new Exception("Failed to accept delegation: " . ($acceptResult['message'] ?? 'Unknown')); }
        
        return ['site_id' => $siteId, 'delegation_id' => $delegationId];
    }

    
    /** Property 10: Assignment requires accepted delegation - **Validates: Requirements 5.5** */
    public function testAssignmentRequiresAcceptedDelegation(): array {
        try {
            $siteResult = $this->createTestSite();
            $this->assert($siteResult['success'], "Site creation should succeed");
            $siteId = $siteResult['data']['id'];
            
            $assignResult = $this->assignmentService->assignToEngineer($siteId, $this->testEngineerId, $this->testUserId, $this->testContractorId);
            $this->assert(!$assignResult['success'], "Assignment without accepted delegation should fail");
            $this->assert($assignResult['code'] === 'AUTHORIZATION_ERROR', "Error code should be AUTHORIZATION_ERROR. Got: " . ($assignResult['code'] ?? 'none'));
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /** Property 10: Assignment succeeds with accepted delegation - **Validates: Requirements 5.1, 5.5** */
    public function testAssignmentSucceedsWithAcceptedDelegation(): array {
        try {
            $setup = $this->createSiteWithAcceptedDelegation();
            $siteId = $setup['site_id'];
            
            $assignResult = $this->assignmentService->assignToEngineer($siteId, $this->testEngineerId, $this->testUserId, $this->testContractorId);
            $this->assert($assignResult['success'], "Assignment with accepted delegation should succeed: " . ($assignResult['message'] ?? ''));
            $this->createdAssignmentIds[] = $assignResult['data']['id'];
            $this->assert(!empty($assignResult['data']['id']), "Assignment should have an ID");
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /** Property 11: Assignment creates proper audit trail - **Validates: Requirements 5.2** */
    public function testAssignmentAuditTrail(): array {
        try {
            $setup = $this->createSiteWithAcceptedDelegation();
            $siteId = $setup['site_id'];
            
            $assignResult = $this->assignmentService->assignToEngineer($siteId, $this->testEngineerId, $this->testUserId, $this->testContractorId);
            $this->assert($assignResult['success'], "Assignment should succeed: " . ($assignResult['message'] ?? ''));
            
            $assignment = $assignResult['data'];
            $this->createdAssignmentIds[] = $assignment['id'];
            
            $this->assert($assignment['status'] === 'assigned', "Status should be 'assigned'. Got: " . ($assignment['status'] ?? 'null'));
            $this->assert((int)$assignment['assigned_by'] === $this->testUserId, "assigned_by should match user ID");
            $this->assert(!empty($assignment['assigned_at']), "assigned_at should be set");
            $this->assert(strtotime($assignment['assigned_at']) !== false, "assigned_at should be valid datetime");
            $this->assert((int)$assignment['engineer_id'] === $this->testEngineerId, "engineer_id should match");
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    
    /** Property 12: No duplicate active engineer assignments - **Validates: Requirements 5.4** */
    public function testNoDuplicateActiveAssignments(): array {
        try {
            $setup = $this->createSiteWithAcceptedDelegation();
            $siteId = $setup['site_id'];
            
            $result1 = $this->assignmentService->assignToEngineer($siteId, $this->testEngineerId, $this->testUserId, $this->testContractorId);
            $this->assert($result1['success'], "First assignment should succeed: " . ($result1['message'] ?? ''));
            $this->createdAssignmentIds[] = $result1['data']['id'];
            
            $result2 = $this->assignmentService->assignToEngineer($siteId, $this->testEngineerId, $this->testUserId, $this->testContractorId);
            $this->assert(!$result2['success'], "Duplicate assignment should fail");
            $this->assert($result2['code'] === 'DUPLICATE_ERROR', "Error code should be DUPLICATE_ERROR. Got: " . ($result2['code'] ?? 'none'));
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    protected function cleanupTestData(): void {
        foreach ($this->createdAssignmentIds as $id) {
            try { $stmt = $this->executeQuery("DELETE FROM engineer_assignments WHERE id = ?", [$id], 'i'); $stmt->close(); } catch (Exception $e) {}
        }
        $this->createdAssignmentIds = [];
        
        foreach ($this->createdDelegationIds as $id) {
            try { $stmt = $this->executeQuery("DELETE FROM delegation_history WHERE delegation_id = ?", [$id], 'i'); $stmt->close(); } catch (Exception $e) {}
            try { $stmt = $this->executeQuery("DELETE FROM site_delegations WHERE id = ?", [$id], 'i'); $stmt->close(); } catch (Exception $e) {}
        }
        $this->createdDelegationIds = [];
        
        foreach ($this->createdSiteIds as $id) {
            try { $stmt = $this->executeQuery("DELETE FROM sites WHERE id = ?", [$id], 'i'); $stmt->close(); } catch (Exception $e) {}
        }
        $this->createdSiteIds = [];
    }
}

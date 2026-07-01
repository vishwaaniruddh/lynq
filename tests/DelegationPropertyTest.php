<?php
/**
 * Property Test for Delegation Operations
 * **Feature: site-management-delegation, Property 4: Delegation creates proper audit trail**
 * **Feature: site-management-delegation, Property 5: No duplicate active delegations**
 * **Feature: site-management-delegation, Property 6: Delegation filtering returns correct results**
 * **Feature: site-management-delegation, Property 9: Rejection requires notes**
 * **Validates: Requirements 2.1, 2.2, 2.4, 3.1, 3.2, 4.3**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../services/SiteService.php';

class DelegationPropertyTest extends PropertyTestBase {
    
    private $delegationService;
    private $siteService;
    private $testAdvCompanyId;
    private $testContractorId;
    private $testUserId;
    private $createdSiteIds = [];
    private $createdDelegationIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->delegationService = new DelegationService();
        $this->siteService = new SiteService();
        $this->setupTestData();
    }
    
    /**
     * Setup test companies and user
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
            $stmt->close();
        }
        
        // Get or create contractor company
        $result = $this->getResults("SELECT id FROM companies WHERE type = 'contractor' AND status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testContractorId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test Contractor ' . uniqid(), 'contractor', 1],
                'ssi'
            );
            $this->testContractorId = $this->db->insert_id;
            $stmt->close();
        }
        
        // Get or create test user
        $result = $this->getResults("SELECT id FROM users WHERE status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testUserId = (int)$result[0]['id'];
        } else {
            $this->testUserId = 1;
        }
    }
    
    public function runTests(): bool {
        echo "=== Delegation Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 4: Delegation creates proper audit trail
        $allPassed &= $this->runPropertyTest(
            "Property 4: Delegation creates proper audit trail",
            [$this, 'testDelegationAuditTrail']
        );
        
        // Property 5: No duplicate active delegations
        $allPassed &= $this->runPropertyTest(
            "Property 5: No duplicate active delegations",
            [$this, 'testNoDuplicateActiveDelegations']
        );
        
        // Property 6: Delegation filtering returns correct results
        $allPassed &= $this->runPropertyTest(
            "Property 6: Delegation filtering by status",
            [$this, 'testDelegationFilteringByStatus']
        );
        
        // Property 9: Rejection requires notes
        $allPassed &= $this->runPropertyTest(
            "Property 9: Rejection requires notes",
            [$this, 'testRejectionRequiresNotes']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 9: Rejection with notes succeeds",
            [$this, 'testRejectionWithNotesSucceeds']
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
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
        
        $result = $this->siteService->createSite($siteData, $this->testUserId);
        if ($result['success']) {
            $this->createdSiteIds[] = $result['data']['id'];
        }
        return $result;
    }
    
    /**
     * Property 4: Delegation creates proper audit trail
     * **Feature: site-management-delegation, Property 4: Delegation creates proper audit trail**
     * **Validates: Requirements 2.1, 2.2**
     */
    public function testDelegationAuditTrail(): array {
        try {
            // Create a test site
            $siteResult = $this->createTestSite();
            $this->assert($siteResult['success'], "Site creation should succeed");
            $siteId = $siteResult['data']['id'];
            
            // Delegate the site
            $result = $this->delegationService->delegateSite(
                $siteId,
                $this->testContractorId,
                $this->testUserId
            );
            
            $this->assert($result['success'], "Delegation should succeed: " . ($result['message'] ?? ''));
            
            $delegation = $result['data'];
            $this->createdDelegationIds[] = $delegation['id'];
            
            // Verify status is 'pending' (Requirement 2.2)
            $this->assert(
                $delegation['status'] === 'pending',
                "Delegation status should be 'pending'. Got: {$delegation['status']}"
            );
            
            // Verify delegated_by is set correctly
            $this->assert(
                (int)$delegation['delegated_by'] === $this->testUserId,
                "delegated_by should be set to the delegating user's ID"
            );
            
            // Verify delegated_at is set
            $this->assert(
                !empty($delegation['delegated_at']),
                "delegated_at should be set"
            );
            
            // Verify delegated_at is a valid datetime
            $delegatedTime = strtotime($delegation['delegated_at']);
            $this->assert(
                $delegatedTime !== false && $delegatedTime > 0,
                "delegated_at should be a valid datetime"
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
     * Property 5: No duplicate active delegations
     * **Feature: site-management-delegation, Property 5: No duplicate active delegations**
     * **Validates: Requirements 2.4**
     */
    public function testNoDuplicateActiveDelegations(): array {
        try {
            // Create a test site
            $siteResult = $this->createTestSite();
            $this->assert($siteResult['success'], "Site creation should succeed");
            $siteId = $siteResult['data']['id'];
            
            // First delegation should succeed
            $result1 = $this->delegationService->delegateSite(
                $siteId,
                $this->testContractorId,
                $this->testUserId
            );
            
            $this->assert($result1['success'], "First delegation should succeed");
            $this->createdDelegationIds[] = $result1['data']['id'];
            
            // Second delegation to same contractor should fail
            $result2 = $this->delegationService->delegateSite(
                $siteId,
                $this->testContractorId,
                $this->testUserId
            );
            
            $this->assert(
                !$result2['success'],
                "Duplicate delegation should fail"
            );
            
            $this->assert(
                $result2['code'] === 'DUPLICATE_ERROR',
                "Error code should be DUPLICATE_ERROR"
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
     * Property 6: Delegation filtering by status
     * **Feature: site-management-delegation, Property 6: Delegation filtering returns correct results**
     * **Validates: Requirements 3.1, 3.2**
     */
    public function testDelegationFilteringByStatus(): array {
        try {
            // Create multiple test sites and delegations
            $pendingCount = 0;
            $acceptedCount = 0;
            
            for ($i = 0; $i < 3; $i++) {
                $siteResult = $this->createTestSite();
                $this->assert($siteResult['success'], "Site creation should succeed");
                
                $delegationResult = $this->delegationService->delegateSite(
                    $siteResult['data']['id'],
                    $this->testContractorId,
                    $this->testUserId
                );
                
                if ($delegationResult['success']) {
                    $this->createdDelegationIds[] = $delegationResult['data']['id'];
                    $pendingCount++;
                    
                    // Accept some delegations
                    if ($i % 2 === 0) {
                        $acceptResult = $this->delegationService->acceptDelegation(
                            $delegationResult['data']['id'],
                            $this->testUserId
                        );
                        if ($acceptResult['success']) {
                            $pendingCount--;
                            $acceptedCount++;
                        }
                    }
                }
            }
            
            // Filter by pending status
            $pendingResults = $this->delegationService->getDelegationsByContractor(
                $this->testContractorId,
                ['status' => 'pending']
            );
            
            // Verify all returned results have pending status
            foreach ($pendingResults['data'] as $delegation) {
                $this->assert(
                    $delegation['status'] === 'pending',
                    "Filtered results should only contain pending delegations"
                );
            }
            
            // Filter by accepted status
            $acceptedResults = $this->delegationService->getDelegationsByContractor(
                $this->testContractorId,
                ['status' => 'accepted']
            );
            
            // Verify all returned results have accepted status
            foreach ($acceptedResults['data'] as $delegation) {
                $this->assert(
                    $delegation['status'] === 'accepted',
                    "Filtered results should only contain accepted delegations"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 9: Rejection requires notes
     * **Feature: site-management-delegation, Property 9: Rejection requires notes**
     * **Validates: Requirements 4.3**
     */
    public function testRejectionRequiresNotes(): array {
        try {
            // Create a test site and delegation
            $siteResult = $this->createTestSite();
            $this->assert($siteResult['success'], "Site creation should succeed");
            
            $delegationResult = $this->delegationService->delegateSite(
                $siteResult['data']['id'],
                $this->testContractorId,
                $this->testUserId
            );
            
            $this->assert($delegationResult['success'], "Delegation should succeed");
            $this->createdDelegationIds[] = $delegationResult['data']['id'];
            
            // Try to reject with empty notes
            $rejectResult = $this->delegationService->rejectDelegation(
                $delegationResult['data']['id'],
                '',
                $this->testUserId
            );
            
            $this->assert(
                !$rejectResult['success'],
                "Rejection with empty notes should fail"
            );
            
            $this->assert(
                $rejectResult['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            // Try to reject with whitespace-only notes
            $rejectResult2 = $this->delegationService->rejectDelegation(
                $delegationResult['data']['id'],
                '   ',
                $this->testUserId
            );
            
            $this->assert(
                !$rejectResult2['success'],
                "Rejection with whitespace-only notes should fail"
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
     * Property 9: Rejection with notes succeeds
     * **Feature: site-management-delegation, Property 9: Rejection requires notes**
     * **Validates: Requirements 4.3**
     */
    public function testRejectionWithNotesSucceeds(): array {
        try {
            // Create a test site and delegation
            $siteResult = $this->createTestSite();
            $this->assert($siteResult['success'], "Site creation should succeed");
            
            $delegationResult = $this->delegationService->delegateSite(
                $siteResult['data']['id'],
                $this->testContractorId,
                $this->testUserId
            );
            
            $this->assert($delegationResult['success'], "Delegation should succeed");
            $this->createdDelegationIds[] = $delegationResult['data']['id'];
            
            // Generate random rejection notes
            $notes = 'Rejection reason: ' . $this->generateRandomString(20);
            
            // Reject with valid notes
            $rejectResult = $this->delegationService->rejectDelegation(
                $delegationResult['data']['id'],
                $notes,
                $this->testUserId
            );
            
            $this->assert(
                $rejectResult['success'],
                "Rejection with valid notes should succeed"
            );
            
            // Verify the rejection notes are stored
            $this->assert(
                $rejectResult['data']['rejection_notes'] === $notes,
                "Rejection notes should be stored correctly"
            );
            
            // Verify status is rejected
            $this->assert(
                $rejectResult['data']['status'] === 'rejected',
                "Status should be 'rejected'"
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
        // Delete delegations first (due to foreign key constraints)
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
    }
}

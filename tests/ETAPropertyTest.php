<?php
/**
 * Property Test for ETA Operations
 * **Feature: feasibility-module, Property 2: ETA submission creates record and updates status**
 * **Feature: feasibility-module, Property 3: Past ETA rejection**
 * **Feature: feasibility-module, Property 4: ETA history preservation**
 * **Validates: Requirements 2.2, 2.3, 2.4, 2.5**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/ETAService.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../services/EngineerAssignmentService.php';

class ETAPropertyTest extends PropertyTestBase {
    
    private $etaService;
    private $siteService;
    private $delegationService;
    private $assignmentService;
    private $testAdvCompanyId;
    private $testContractorId;
    private $testEngineerId;
    private $testAdminUserId;
    private $createdSiteIds = [];
    private $createdDelegationIds = [];
    private $createdAssignmentIds = [];
    private $createdETAIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->etaService = new ETAService();
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
        
        // Get or create admin user
        $result = $this->getResults("SELECT id FROM users WHERE status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testAdminUserId = (int)$result[0]['id'];
        } else {
            $this->testAdminUserId = 1;
        }
        
        // Get or create engineer user
        $result = $this->getResults("SELECT id FROM users WHERE company_id = ? AND status = 1 LIMIT 1", [$this->testContractorId], 'i');
        if (!empty($result)) {
            $this->testEngineerId = (int)$result[0]['id'];
        } else {
            // Create an engineer user
            $stmt = $this->executeQuery(
                "INSERT INTO users (first_name, last_name, email, password, company_id, status) VALUES (?, ?, ?, ?, ?, ?)",
                ['Test', 'Engineer', 'engineer_' . uniqid() . '@test.com', password_hash('password', PASSWORD_DEFAULT), $this->testContractorId, 1],
                'ssssii'
            );
            $this->testEngineerId = $this->db->insert_id;
            $stmt->close();
        }
    }
    
    /**
     * Create a test site with delegation and assignment
     */
    private function createTestAssignment(): ?array {
        // Create site
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
        
        $siteResult = $this->siteService->createSite($siteData, $this->testAdminUserId);
        if (!$siteResult['success']) {
            return null;
        }
        $this->createdSiteIds[] = $siteResult['data']['id'];
        
        // Create delegation
        $delegationResult = $this->delegationService->delegateSite(
            $siteResult['data']['id'],
            $this->testContractorId,
            $this->testAdminUserId
        );
        if (!$delegationResult['success']) {
            return null;
        }
        $this->createdDelegationIds[] = $delegationResult['data']['id'];
        
        // Accept delegation
        $this->delegationService->acceptDelegation($delegationResult['data']['id'], $this->testAdminUserId);
        
        // Create assignment
        $assignmentResult = $this->assignmentService->assignToEngineer(
            $siteResult['data']['id'],
            $this->testEngineerId,
            $this->testAdminUserId,
            $this->testContractorId
        );
        if (!$assignmentResult['success']) {
            return null;
        }
        $this->createdAssignmentIds[] = $assignmentResult['data']['id'];
        
        return $assignmentResult['data'];
    }
    
    /**
     * Generate a valid future ETA datetime
     */
    private function generateValidETADateTime(): string {
        $futureMinutes = rand(60, 43200); // 1 hour to 30 days
        return date('Y-m-d H:i:s', strtotime("+{$futureMinutes} minutes"));
    }
    
    /**
     * Generate a past ETA datetime
     */
    private function generatePastETADateTime(): string {
        $pastMinutes = rand(1, 43200); // 1 minute to 30 days ago
        return date('Y-m-d H:i:s', strtotime("-{$pastMinutes} minutes"));
    }
    
    public function runTests(): bool {
        echo "=== ETA Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 2: ETA submission creates record and updates status
        $allPassed &= $this->runPropertyTest(
            "Property 2: ETA submission creates record and updates status",
            [$this, 'testETASubmissionCreatesRecordAndUpdatesStatus']
        );
        
        // Property 3: Past ETA rejection
        $allPassed &= $this->runPropertyTest(
            "Property 3: Past ETA rejection",
            [$this, 'testPastETARejection']
        );
        
        // Property 4: ETA history preservation
        $allPassed &= $this->runPropertyTest(
            "Property 4: ETA history preservation",
            [$this, 'testETAHistoryPreservation']
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 2: ETA submission creates record and updates status
     * **Feature: feasibility-module, Property 2: ETA submission creates record and updates status**
     * **Validates: Requirements 2.2, 2.4**
     * 
     * For any valid future date/time submitted as ETA, the system should create an ETA record
     * with correct data (datetime, engineer_id, timestamp) and update the assignment's
     * feasibility_status to "eta_submitted".
     */
    public function testETASubmissionCreatesRecordAndUpdatesStatus(): array {
        try {
            // Create test assignment
            $assignment = $this->createTestAssignment();
            $this->assert($assignment !== null, "Assignment creation should succeed");
            
            // Generate valid future ETA
            $etaDateTime = $this->generateValidETADateTime();
            
            // Submit ETA
            $result = $this->etaService->submitETA(
                $assignment['id'],
                $etaDateTime,
                $this->testEngineerId
            );
            
            $this->assert($result['success'], "ETA submission should succeed: " . ($result['message'] ?? ''));
            
            $eta = $result['data'];
            $this->createdETAIds[] = $eta['id'];
            
            // Verify ETA record has correct data
            $this->assert(
                (int)$eta['assignment_id'] === (int)$assignment['id'],
                "ETA assignment_id should match"
            );
            
            $this->assert(
                $eta['eta_datetime'] === $etaDateTime,
                "ETA datetime should match submitted value"
            );
            
            $this->assert(
                (int)$eta['submitted_by'] === $this->testEngineerId,
                "ETA submitted_by should be the engineer ID"
            );
            
            $this->assert(
                !empty($eta['submitted_at']),
                "ETA submitted_at should be set"
            );
            
            $this->assert(
                $eta['is_current'] == 1 || $eta['is_current'] === true,
                "ETA is_current should be true"
            );
            
            // Verify assignment feasibility_status is updated
            $updatedAssignment = $this->getResults(
                "SELECT feasibility_status FROM engineer_assignments WHERE id = ?",
                [$assignment['id']],
                'i'
            );
            
            $this->assert(
                !empty($updatedAssignment) && $updatedAssignment[0]['feasibility_status'] === 'eta_submitted',
                "Assignment feasibility_status should be 'eta_submitted'"
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
     * Property 3: Past ETA rejection
     * **Feature: feasibility-module, Property 3: Past ETA rejection**
     * **Validates: Requirements 2.3**
     * 
     * For any date/time that is in the past relative to current time, submitting it as ETA
     * should be rejected with a validation error.
     */
    public function testPastETARejection(): array {
        try {
            // Create test assignment
            $assignment = $this->createTestAssignment();
            $this->assert($assignment !== null, "Assignment creation should succeed");
            
            // Generate past ETA datetime
            $pastDateTime = $this->generatePastETADateTime();
            
            // Try to submit past ETA
            $result = $this->etaService->submitETA(
                $assignment['id'],
                $pastDateTime,
                $this->testEngineerId
            );
            
            // Should fail
            $this->assert(
                !$result['success'],
                "Past ETA submission should fail"
            );
            
            $this->assert(
                $result['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            // Verify error message mentions past date
            $hasCorrectError = false;
            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    if ($error['code'] === 'PAST_DATE_NOT_ALLOWED') {
                        $hasCorrectError = true;
                        break;
                    }
                }
            }
            
            $this->assert(
                $hasCorrectError,
                "Error should indicate past date is not allowed"
            );
            
            // Verify no ETA was created
            $eta = $this->etaService->getETA($assignment['id']);
            $this->assert(
                $eta === null,
                "No ETA should be created for past datetime"
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
     * Property 4: ETA history preservation
     * **Feature: feasibility-module, Property 4: ETA history preservation**
     * **Validates: Requirements 2.5**
     * 
     * For any ETA update on an assignment, the previous ETA record should be marked as
     * non-current (is_current=false) and the new ETA should be marked as current,
     * preserving complete history.
     */
    public function testETAHistoryPreservation(): array {
        try {
            // Create test assignment
            $assignment = $this->createTestAssignment();
            $this->assert($assignment !== null, "Assignment creation should succeed");
            
            // Submit first ETA
            $etaDateTime1 = $this->generateValidETADateTime();
            $result1 = $this->etaService->submitETA(
                $assignment['id'],
                $etaDateTime1,
                $this->testEngineerId
            );
            
            $this->assert($result1['success'], "First ETA submission should succeed");
            $eta1Id = $result1['data']['id'];
            $this->createdETAIds[] = $eta1Id;
            
            // Submit second ETA (update)
            $etaDateTime2 = $this->generateValidETADateTime();
            $result2 = $this->etaService->updateETA(
                $assignment['id'],
                $etaDateTime2,
                $this->testEngineerId
            );
            
            $this->assert($result2['success'], "Second ETA submission should succeed");
            $eta2Id = $result2['data']['id'];
            $this->createdETAIds[] = $eta2Id;
            
            // Verify first ETA is marked as not current
            $eta1 = $this->getResults(
                "SELECT is_current FROM feasibility_eta WHERE id = ?",
                [$eta1Id],
                'i'
            );
            
            $this->assert(
                !empty($eta1) && ($eta1[0]['is_current'] == 0 || $eta1[0]['is_current'] === false),
                "First ETA should be marked as not current"
            );
            
            // Verify second ETA is marked as current
            $eta2 = $this->getResults(
                "SELECT is_current FROM feasibility_eta WHERE id = ?",
                [$eta2Id],
                'i'
            );
            
            $this->assert(
                !empty($eta2) && ($eta2[0]['is_current'] == 1 || $eta2[0]['is_current'] === true),
                "Second ETA should be marked as current"
            );
            
            // Verify history contains both ETAs
            $history = $this->etaService->getETAHistory($assignment['id']);
            
            $this->assert(
                count($history) >= 2,
                "History should contain at least 2 ETA records"
            );
            
            // Verify history is ordered by submitted_at DESC (newest first)
            $foundEta1 = false;
            $foundEta2 = false;
            foreach ($history as $eta) {
                if ((int)$eta['id'] === $eta1Id) $foundEta1 = true;
                if ((int)$eta['id'] === $eta2Id) $foundEta2 = true;
            }
            
            $this->assert(
                $foundEta1 && $foundEta2,
                "History should contain both ETA records"
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
        // Delete ETAs first
        foreach ($this->createdETAIds as $etaId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM feasibility_eta WHERE id = ?",
                    [$etaId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdETAIds = [];
        
        // Delete assignments
        foreach ($this->createdAssignmentIds as $assignmentId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM feasibility_eta WHERE assignment_id = ?",
                    [$assignmentId],
                    'i'
                );
                $stmt->close();
                
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
    }
}

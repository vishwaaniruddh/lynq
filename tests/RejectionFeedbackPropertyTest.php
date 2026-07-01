<?php
/**
 * Property Tests for Engineer Rejection Feedback UI
 * 
 * **Feature: feasibility-module, Property 26: Rejection display with highlighted sections**
 * **Feature: feasibility-module, Property 27: Editable sections restriction**
 * **Feature: feasibility-module, Property 29: Review history completeness**
 * 
 * **Validates: Requirements 12.1, 12.2, 12.3, 12.5**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/FeasibilityReviewService.php';
require_once __DIR__ . '/../services/FeasibilityService.php';
require_once __DIR__ . '/../services/ADAService.php';
require_once __DIR__ . '/../services/ETAService.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../services/EngineerAssignmentService.php';

class RejectionFeedbackPropertyTest extends PropertyTestBase {
    
    private $reviewService;
    private $feasibilityService;
    private $adaService;
    private $etaService;
    private $siteService;
    private $delegationService;
    private $assignmentService;
    
    private $testAdvCompanyId;
    private $testContractorId;
    private $testEngineerId;
    private $testContractorAdminId;
    private $testAdvUserId;
    private $testAdminUserId;
    
    private $createdSiteIds = [];
    private $createdDelegationIds = [];
    private $createdAssignmentIds = [];
    private $createdFeasibilityIds = [];
    private $createdReviewIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->reviewService = new FeasibilityReviewService();
        $this->feasibilityService = new FeasibilityService();
        $this->adaService = new ADAService();
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
        
        // Role IDs
        $engineerRoleId = 8;
        $contractorAdminRoleId = 5;
        $advAdminRoleId = 2;
        
        // Get or create engineer user
        $result = $this->getResults("SELECT id FROM users WHERE company_id = ? AND role_id = ? AND status = 1 LIMIT 1", [$this->testContractorId, $engineerRoleId], 'ii');
        if (!empty($result)) {
            $this->testEngineerId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO users (username, first_name, last_name, email, password_hash, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                ['engineer_rej_' . uniqid(), 'Test', 'Engineer', 'engineer_rej_' . uniqid() . '@test.com', password_hash('password', PASSWORD_DEFAULT), $this->testContractorId, $engineerRoleId, 1],
                'sssssiis'
            );
            $this->testEngineerId = $this->db->insert_id;
            $stmt->close();
        }
        
        // Get or create contractor admin user
        $result = $this->getResults("SELECT id FROM users WHERE company_id = ? AND role_id = ? AND status = 1 LIMIT 1", [$this->testContractorId, $contractorAdminRoleId], 'ii');
        if (!empty($result)) {
            $this->testContractorAdminId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO users (username, first_name, last_name, email, password_hash, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                ['contractor_admin_rej_' . uniqid(), 'Contractor', 'Admin', 'contractor_admin_rej_' . uniqid() . '@test.com', password_hash('password', PASSWORD_DEFAULT), $this->testContractorId, $contractorAdminRoleId, 1],
                'sssssiis'
            );
            $this->testContractorAdminId = $this->db->insert_id;
            $stmt->close();
        }
        
        // Get or create ADV user
        $result = $this->getResults("SELECT id FROM users WHERE company_id = ? AND role_id IN (1, 2, 3) AND status = 1 LIMIT 1", [$this->testAdvCompanyId], 'i');
        if (!empty($result)) {
            $this->testAdvUserId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO users (username, first_name, last_name, email, password_hash, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                ['adv_user_rej_' . uniqid(), 'ADV', 'User', 'adv_user_rej_' . uniqid() . '@test.com', password_hash('password', PASSWORD_DEFAULT), $this->testAdvCompanyId, $advAdminRoleId, 1],
                'sssssiis'
            );
            $this->testAdvUserId = $this->db->insert_id;
            $stmt->close();
        }
    }
    
    /**
     * Create a test site with delegation, assignment, and completed feasibility check
     */
    private function createTestFeasibilityCheck(): ?array {
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
        $assignmentId = $assignmentResult['data']['id'];
        
        // Submit ETA
        $etaDateTime = date('Y-m-d H:i:s', strtotime('+1 day'));
        $this->etaService->submitETA($assignmentId, $etaDateTime, $this->testEngineerId);
        
        // Submit ADA
        $latitude = round((rand(-9000000, 9000000) / 100000), 6);
        $longitude = round((rand(-18000000, 18000000) / 100000), 6);
        $this->adaService->submitADA($assignmentId, $latitude, $longitude, $this->testEngineerId);
        
        // Create feasibility check
        $feasibilityData = [
            'no_of_atm' => rand(1, 3),
            'atm_id_1' => 'ATM_' . uniqid(),
            'atm_1_status' => 'working',
            'operator' => 'Airtel',
            'signal_status' => 'good',
            'ups_available' => 'yes',
            'no_of_ups' => rand(1, 2),
            'earthing' => 'yes',
            'remarks' => 'Test remarks ' . $this->generateRandomString(50)
        ];
        
        $feasibilityResult = $this->feasibilityService->createFeasibilityCheck(
            $assignmentId,
            $feasibilityData,
            $this->testEngineerId
        );
        
        if (!$feasibilityResult['success']) {
            return null;
        }
        
        $this->createdFeasibilityIds[] = $feasibilityResult['data']['id'];
        
        return [
            'feasibility' => $feasibilityResult['data'],
            'assignment_id' => $assignmentId,
            'site_id' => $siteResult['data']['id']
        ];
    }
    
    /**
     * Generate random rejection reason with minimum length
     */
    private function generateValidRejectionReason(): string {
        $minLength = 10;
        $length = rand($minLength, 200);
        return 'Rejection reason: ' . $this->generateRandomString($length - 18);
    }
    
    /**
     * Generate random valid sections for rejection
     */
    private function generateRandomSections(): array {
        $allSections = FeasibilityReviewService::getValidSections();
        $numSections = rand(1, count($allSections));
        shuffle($allSections);
        return array_slice($allSections, 0, $numSections);
    }
    
    public function runTests(): bool {
        echo "=== Rejection Feedback Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 26: Rejection display with highlighted sections
        $allPassed &= $this->runPropertyTest(
            "Property 26: Rejection display with highlighted sections",
            [$this, 'testRejectionDisplayWithHighlightedSections']
        );
        
        // Property 27: Editable sections restriction
        $allPassed &= $this->runPropertyTest(
            "Property 27: Editable sections restriction",
            [$this, 'testEditableSectionsRestriction']
        );
        
        // Property 29: Review history completeness
        $allPassed &= $this->runPropertyTest(
            "Property 29: Review history completeness",
            [$this, 'testReviewHistoryCompleteness']
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }

    
    /**
     * Property 26: Rejection display with highlighted sections
     * **Feature: feasibility-module, Property 26: Rejection display with highlighted sections**
     * **Validates: Requirements 12.1, 12.2**
     * 
     * For any rejected feasibility check, the view should include rejection reason,
     * and for section-specific rejections, the affected sections should have visual
     * indicators (CSS classes for highlighting).
     */
    public function testRejectionDisplayWithHighlightedSections(): array {
        try {
            // Create test feasibility check
            $testData = $this->createTestFeasibilityCheck();
            $this->assert($testData !== null, "Test feasibility check creation should succeed");
            
            $feasibilityId = $testData['feasibility']['id'];
            $rejectionReason = $this->generateValidRejectionReason();
            $rejectedSections = $this->generateRandomSections();
            
            // Reject by contractor with section-specific rejection
            $result = $this->reviewService->rejectByContractor(
                $feasibilityId,
                $this->testContractorAdminId,
                'section_specific',
                $rejectedSections,
                $rejectionReason
            );
            
            $this->assert($result['success'], "Contractor rejection should succeed: " . ($result['message'] ?? ''));
            
            // Get editable sections info (this is what the UI uses to display rejection info)
            $editableInfo = $this->reviewService->getEditableSections($feasibilityId);
            
            $this->assert(
                $editableInfo['success'],
                "Getting editable sections should succeed"
            );
            
            // Verify rejection reason is available (Requirement 12.1)
            $this->assert(
                !empty($editableInfo['rejectionReason']),
                "Rejection reason should be available"
            );
            
            $this->assert(
                $editableInfo['rejectionReason'] === $rejectionReason,
                "Rejection reason should match the original reason"
            );
            
            // Verify rejected sections are identified (Requirement 12.2)
            $this->assert(
                !empty($editableInfo['editableSections']),
                "Rejected sections should be identified"
            );
            
            // Verify all rejected sections are in the editable sections list
            foreach ($rejectedSections as $section) {
                $this->assert(
                    in_array($section, $editableInfo['editableSections']),
                    "Rejected section '$section' should be in editable sections list"
                );
            }
            
            // Verify rejection type is correct
            $this->assert(
                $editableInfo['rejectionType'] === 'section_specific',
                "Rejection type should be 'section_specific'"
            );
            
            // Test overall rejection
            $testData2 = $this->createTestFeasibilityCheck();
            $this->assert($testData2 !== null, "Second test feasibility check creation should succeed");
            
            $feasibilityId2 = $testData2['feasibility']['id'];
            $rejectionReason2 = $this->generateValidRejectionReason();
            
            // Reject with overall rejection
            $result2 = $this->reviewService->rejectByContractor(
                $feasibilityId2,
                $this->testContractorAdminId,
                'overall',
                [],
                $rejectionReason2
            );
            
            $this->assert($result2['success'], "Overall rejection should succeed");
            
            $editableInfo2 = $this->reviewService->getEditableSections($feasibilityId2);
            
            $this->assert(
                $editableInfo2['success'],
                "Getting editable sections for overall rejection should succeed"
            );
            
            // For overall rejection, all sections should be editable
            $allSections = FeasibilityReviewService::getValidSections();
            $this->assert(
                count($editableInfo2['editableSections']) === count($allSections),
                "For overall rejection, all sections should be editable"
            );
            
            $this->assert(
                $editableInfo2['rejectionType'] === 'overall',
                "Rejection type should be 'overall'"
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
     * Property 27: Editable sections restriction
     * **Feature: feasibility-module, Property 27: Editable sections restriction**
     * **Validates: Requirements 12.3**
     * 
     * For any rejected feasibility check being edited, only the rejected sections
     * should be editable; non-rejected sections should be read-only.
     */
    public function testEditableSectionsRestriction(): array {
        try {
            // Create test feasibility check
            $testData = $this->createTestFeasibilityCheck();
            $this->assert($testData !== null, "Test feasibility check creation should succeed");
            
            $feasibilityId = $testData['feasibility']['id'];
            $rejectionReason = $this->generateValidRejectionReason();
            
            // Select specific sections to reject (not all)
            $allSections = FeasibilityReviewService::getValidSections();
            $numSectionsToReject = rand(1, count($allSections) - 1); // At least one non-rejected section
            shuffle($allSections);
            $rejectedSections = array_slice($allSections, 0, $numSectionsToReject);
            $nonRejectedSections = array_slice($allSections, $numSectionsToReject);
            
            // Reject by contractor with section-specific rejection
            $result = $this->reviewService->rejectByContractor(
                $feasibilityId,
                $this->testContractorAdminId,
                'section_specific',
                $rejectedSections,
                $rejectionReason
            );
            
            $this->assert($result['success'], "Contractor rejection should succeed");
            
            // Get editable sections info
            $editableInfo = $this->reviewService->getEditableSections($feasibilityId);
            
            $this->assert(
                $editableInfo['success'],
                "Getting editable sections should succeed"
            );
            
            // Verify only rejected sections are editable
            foreach ($rejectedSections as $section) {
                $this->assert(
                    in_array($section, $editableInfo['editableSections']),
                    "Rejected section '$section' should be editable"
                );
            }
            
            // Verify non-rejected sections are NOT editable
            foreach ($nonRejectedSections as $section) {
                $this->assert(
                    !in_array($section, $editableInfo['editableSections']),
                    "Non-rejected section '$section' should NOT be editable"
                );
            }
            
            // Verify editable fields correspond to rejected sections
            $sectionFields = FeasibilityReviewService::getSectionFields();
            $expectedEditableFields = [];
            foreach ($rejectedSections as $section) {
                if (isset($sectionFields[$section])) {
                    $expectedEditableFields = array_merge($expectedEditableFields, $sectionFields[$section]);
                }
            }
            $expectedEditableFields = array_unique($expectedEditableFields);
            
            // All expected fields should be in editable fields
            foreach ($expectedEditableFields as $field) {
                $this->assert(
                    in_array($field, $editableInfo['editableFields']),
                    "Field '$field' from rejected section should be editable"
                );
            }
            
            // Fields from non-rejected sections should NOT be editable
            foreach ($nonRejectedSections as $section) {
                if (isset($sectionFields[$section])) {
                    foreach ($sectionFields[$section] as $field) {
                        $this->assert(
                            !in_array($field, $editableInfo['editableFields']),
                            "Field '$field' from non-rejected section should NOT be editable"
                        );
                    }
                }
            }
            
            // Test resubmission with only editable fields
            $updatedData = [];
            foreach ($editableInfo['editableFields'] as $field) {
                // Only update text fields for simplicity
                if (!str_ends_with($field, '_snap')) {
                    $updatedData[$field] = 'Updated_' . $this->generateRandomString(10);
                }
            }
            
            // Attempt to update with valid editable fields
            $resubmitResult = $this->reviewService->resubmitFeasibility(
                $feasibilityId,
                $updatedData,
                $this->testEngineerId
            );
            
            $this->assert(
                $resubmitResult['success'],
                "Resubmission with editable fields should succeed: " . ($resubmitResult['message'] ?? '')
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
     * Property 29: Review history completeness
     * **Feature: feasibility-module, Property 29: Review history completeness**
     * **Validates: Requirements 12.5**
     * 
     * For any feasibility check with reviews, the review history should return
     * all review records with timestamps, reviewer information, and reasons.
     */
    public function testReviewHistoryCompleteness(): array {
        try {
            // Create test feasibility check
            $testData = $this->createTestFeasibilityCheck();
            $this->assert($testData !== null, "Test feasibility check creation should succeed");
            
            $feasibilityId = $testData['feasibility']['id'];
            
            // Create multiple reviews
            $reviewsToCreate = rand(2, 4);
            $createdReviews = [];
            
            for ($i = 0; $i < $reviewsToCreate; $i++) {
                $rejectionReason = $this->generateValidRejectionReason();
                $rejectedSections = $this->generateRandomSections();
                
                // Reject
                $rejectResult = $this->reviewService->rejectByContractor(
                    $feasibilityId,
                    $this->testContractorAdminId,
                    'section_specific',
                    $rejectedSections,
                    $rejectionReason
                );
                
                if ($rejectResult['success']) {
                    $createdReviews[] = [
                        'type' => 'rejection',
                        'reason' => $rejectionReason,
                        'sections' => $rejectedSections
                    ];
                    
                    // Resubmit to allow another review
                    $editableInfo = $this->reviewService->getEditableSections($feasibilityId);
                    if ($editableInfo['success'] && !empty($editableInfo['editableFields'])) {
                        $updatedData = [];
                        foreach ($editableInfo['editableFields'] as $field) {
                            if (!str_ends_with($field, '_snap')) {
                                $updatedData[$field] = 'Resubmit_' . $this->generateRandomString(8);
                            }
                        }
                        $this->reviewService->resubmitFeasibility($feasibilityId, $updatedData, $this->testEngineerId);
                    }
                }
            }
            
            // Get review history
            $reviewHistory = $this->reviewService->getReviewHistory($feasibilityId);
            
            // Verify we have review records
            $this->assert(
                count($reviewHistory) >= count($createdReviews),
                "Review history should contain at least " . count($createdReviews) . " reviews"
            );
            
            // Verify each review has required fields
            foreach ($reviewHistory as $review) {
                // Verify timestamp exists
                $this->assert(
                    !empty($review['reviewed_at']),
                    "Review should have a timestamp (reviewed_at)"
                );
                
                // Verify reviewer information exists
                $this->assert(
                    !empty($review['reviewer_id']),
                    "Review should have reviewer_id"
                );
                
                // Verify review type exists
                $this->assert(
                    !empty($review['review_type']),
                    "Review should have review_type"
                );
                
                // For rejections, verify reason exists
                if ($review['review_type'] === 'rejection') {
                    $this->assert(
                        !empty($review['reason']),
                        "Rejection review should have a reason"
                    );
                    
                    $this->assert(
                        !empty($review['rejection_type']),
                        "Rejection review should have rejection_type"
                    );
                }
                
                // Verify reviewer role exists
                $this->assert(
                    !empty($review['reviewer_role']),
                    "Review should have reviewer_role"
                );
            }
            
            // Verify reviews are ordered by timestamp (most recent first)
            for ($i = 0; $i < count($reviewHistory) - 1; $i++) {
                $currentTime = strtotime($reviewHistory[$i]['reviewed_at']);
                $nextTime = strtotime($reviewHistory[$i + 1]['reviewed_at']);
                $this->assert(
                    $currentTime >= $nextTime,
                    "Reviews should be ordered by timestamp (most recent first)"
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
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        // Clean up in reverse order of creation
        
        // Clean up reviews
        foreach ($this->createdFeasibilityIds as $feasibilityId) {
            try {
                $this->executeQuery("DELETE FROM feasibility_reviews WHERE feasibility_id = ?", [$feasibilityId], 'i');
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Clean up feasibility checks
        foreach ($this->createdFeasibilityIds as $feasibilityId) {
            try {
                $this->executeQuery("DELETE FROM feasibility_checks WHERE id = ?", [$feasibilityId], 'i');
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Clean up ADA records
        foreach ($this->createdAssignmentIds as $assignmentId) {
            try {
                $this->executeQuery("DELETE FROM feasibility_ada WHERE assignment_id = ?", [$assignmentId], 'i');
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Clean up ETA records
        foreach ($this->createdAssignmentIds as $assignmentId) {
            try {
                $this->executeQuery("DELETE FROM feasibility_eta WHERE assignment_id = ?", [$assignmentId], 'i');
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Clean up assignments
        foreach ($this->createdAssignmentIds as $assignmentId) {
            try {
                $this->executeQuery("DELETE FROM engineer_assignments WHERE id = ?", [$assignmentId], 'i');
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Clean up delegations
        foreach ($this->createdDelegationIds as $delegationId) {
            try {
                $this->executeQuery("DELETE FROM site_delegations WHERE id = ?", [$delegationId], 'i');
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Clean up sites
        foreach ($this->createdSiteIds as $siteId) {
            try {
                $this->executeQuery("DELETE FROM sites WHERE id = ?", [$siteId], 'i');
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new RejectionFeedbackPropertyTest();
    $passed = $test->runTests();
    exit($passed ? 0 : 1);
}

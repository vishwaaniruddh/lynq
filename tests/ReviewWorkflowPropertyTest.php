<?php
/**
 * Property Tests for Feasibility Review Workflow
 * 
 * **Feature: feasibility-module, Property 17: Contractor review panel visibility**
 * **Feature: feasibility-module, Property 18: Approval creates review record**
 * **Feature: feasibility-module, Property 19: Rejection validation**
 * **Feature: feasibility-module, Property 20: Contractor rejection status transition**
 * **Feature: feasibility-module, Property 21: Contractor approval status transition**
 * **Feature: feasibility-module, Property 22: ADV review panel visibility**
 * **Feature: feasibility-module, Property 23: ADV approval status transition**
 * **Feature: feasibility-module, Property 24: ADV rejection status transition**
 * **Feature: feasibility-module, Property 25: ADV-approved immutability**
 * **Feature: feasibility-module, Property 28: Resubmission status reset**
 * 
 * **Validates: Requirements 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 12.4**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/FeasibilityReviewService.php';
require_once __DIR__ . '/../services/FeasibilityService.php';
require_once __DIR__ . '/../services/ADAService.php';
require_once __DIR__ . '/../services/ETAService.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../services/EngineerAssignmentService.php';

class ReviewWorkflowPropertyTest extends PropertyTestBase {
    
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
        
        // Get Engineer role ID (role_id = 8)
        $engineerRoleId = 8;
        // Get Contractor Admin role ID (role_id = 5)
        $contractorAdminRoleId = 5;
        // Get ADV Admin role ID (role_id = 2)
        $advAdminRoleId = 2;
        
        // Get or create engineer user (contractor employee)
        $result = $this->getResults("SELECT id FROM users WHERE company_id = ? AND role_id = ? AND status = 1 LIMIT 1", [$this->testContractorId, $engineerRoleId], 'ii');
        if (!empty($result)) {
            $this->testEngineerId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO users (username, first_name, last_name, email, password_hash, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                ['engineer_' . uniqid(), 'Test', 'Engineer', 'engineer_' . uniqid() . '@test.com', password_hash('password', PASSWORD_DEFAULT), $this->testContractorId, $engineerRoleId, 1],
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
                ['contractor_admin_' . uniqid(), 'Contractor', 'Admin', 'contractor_admin_' . uniqid() . '@test.com', password_hash('password', PASSWORD_DEFAULT), $this->testContractorId, $contractorAdminRoleId, 1],
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
                ['adv_user_' . uniqid(), 'ADV', 'User', 'adv_user_' . uniqid() . '@test.com', password_hash('password', PASSWORD_DEFAULT), $this->testAdvCompanyId, $advAdminRoleId, 1],
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
     * Generate random short rejection reason (less than minimum)
     */
    private function generateShortRejectionReason(): string {
        $length = rand(1, 9);
        return $this->generateRandomString($length);
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
        echo "=== Review Workflow Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 17: Contractor review panel visibility
        $allPassed &= $this->runPropertyTest(
            "Property 17: Contractor review panel visibility",
            [$this, 'testContractorReviewPanelVisibility']
        );
        
        // Property 18: Approval creates review record
        $allPassed &= $this->runPropertyTest(
            "Property 18: Approval creates review record",
            [$this, 'testApprovalCreatesReviewRecord']
        );
        
        // Property 19: Rejection validation
        $allPassed &= $this->runPropertyTest(
            "Property 19: Rejection validation",
            [$this, 'testRejectionValidation']
        );
        
        // Property 21: Contractor approval status transition
        $allPassed &= $this->runPropertyTest(
            "Property 21: Contractor approval status transition",
            [$this, 'testContractorApprovalStatusTransition']
        );
        
        // Property 20: Contractor rejection status transition
        $allPassed &= $this->runPropertyTest(
            "Property 20: Contractor rejection status transition",
            [$this, 'testContractorRejectionStatusTransition']
        );
        
        // Property 22: ADV review panel visibility
        $allPassed &= $this->runPropertyTest(
            "Property 22: ADV review panel visibility",
            [$this, 'testADVReviewPanelVisibility']
        );
        
        // Property 23: ADV approval status transition
        $allPassed &= $this->runPropertyTest(
            "Property 23: ADV approval status transition",
            [$this, 'testADVApprovalStatusTransition']
        );
        
        // Property 24: ADV rejection status transition
        $allPassed &= $this->runPropertyTest(
            "Property 24: ADV rejection status transition",
            [$this, 'testADVRejectionStatusTransition']
        );
        
        // Property 28: Resubmission status reset
        $allPassed &= $this->runPropertyTest(
            "Property 28: Resubmission status reset",
            [$this, 'testResubmissionStatusReset']
        );
        
        // Property 25: ADV-approved immutability
        $allPassed &= $this->runPropertyTest(
            "Property 25: ADV-approved immutability",
            [$this, 'testADVApprovedImmutability']
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }

    
    /**
     * Property 17: Contractor review panel visibility
     * **Feature: feasibility-module, Property 17: Contractor review panel visibility**
     * **Validates: Requirements 10.1**
     * 
     * For any user with contractor_admin or contractor_manager role viewing a completed
     * feasibility check, the system should display the review panel with approve/reject options.
     */
    public function testContractorReviewPanelVisibility(): array {
        try {
            // Create test feasibility check
            $testData = $this->createTestFeasibilityCheck();
            $this->assert($testData !== null, "Test feasibility check creation should succeed");
            
            $feasibilityId = $testData['feasibility']['id'];
            
            // Test 1: Contractor admin should be able to review
            $canReviewAdmin = $this->reviewService->canUserReview($this->testContractorAdminId, $feasibilityId);
            
            $this->assert(
                $canReviewAdmin['canReview'] === true,
                "Contractor admin should be able to review feasibility check"
            );
            
            $this->assert(
                $canReviewAdmin['reviewerRole'] === 'contractor_admin',
                "Reviewer role should be 'contractor_admin'"
            );
            
            // Test 2: Engineer should NOT be able to review
            $canReviewEngineer = $this->reviewService->canUserReview($this->testEngineerId, $feasibilityId);
            
            $this->assert(
                $canReviewEngineer['canReview'] === false,
                "Engineer should NOT be able to review feasibility check"
            );
            
            // Test 3: ADV user should be able to review (but only after contractor approval)
            $canReviewADV = $this->reviewService->canUserReview($this->testAdvUserId, $feasibilityId);
            
            $this->assert(
                $canReviewADV['canReview'] === true,
                "ADV user should be able to review feasibility check"
            );
            
            $this->assert(
                $canReviewADV['reviewerRole'] === 'adv',
                "Reviewer role should be 'adv'"
            );
            
            // Test 4: Verify feasibility is in pending_contractor_review status
            $feasibility = $this->getResults(
                "SELECT approval_status FROM feasibility_checks WHERE id = ?",
                [$feasibilityId],
                'i'
            );
            
            $this->assert(
                !empty($feasibility) && $feasibility[0]['approval_status'] === 'pending_contractor_review',
                "New feasibility check should be in 'pending_contractor_review' status"
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
     * Property 18: Approval creates review record
     * **Feature: feasibility-module, Property 18: Approval creates review record**
     * **Validates: Requirements 10.2**
     * 
     * For any approval action by a contractor reviewer, the system should create
     * a review record with reviewer_id, timestamp, review_type='approval', and optional comments.
     */
    public function testApprovalCreatesReviewRecord(): array {
        try {
            // Create test feasibility check
            $testData = $this->createTestFeasibilityCheck();
            $this->assert($testData !== null, "Test feasibility check creation should succeed");
            
            $feasibilityId = $testData['feasibility']['id'];
            $testComments = 'Test approval comments ' . $this->generateRandomString(20);
            
            // Approve by contractor with comments
            $result = $this->reviewService->approveByContractor(
                $feasibilityId,
                $this->testContractorAdminId,
                $testComments
            );
            
            $this->assert($result['success'], "Contractor approval should succeed: " . ($result['message'] ?? ''));
            
            // Verify review record was created
            $reviews = $this->reviewService->getReviewsByFeasibility($feasibilityId);
            
            $this->assert(
                count($reviews) > 0,
                "Review record should be created"
            );
            
            $latestReview = $reviews[0];
            
            // Verify reviewer_id
            $this->assert(
                (int)$latestReview['reviewer_id'] === $this->testContractorAdminId,
                "Review should have correct reviewer_id"
            );
            
            // Verify review_type is 'approval'
            $this->assert(
                $latestReview['review_type'] === 'approval',
                "Review type should be 'approval'"
            );
            
            // Verify timestamp exists
            $this->assert(
                !empty($latestReview['reviewed_at']),
                "Review should have a timestamp"
            );
            
            // Verify comments are stored
            $this->assert(
                $latestReview['comments'] === $testComments,
                "Review should have the correct comments"
            );
            
            // Verify reviewer_role
            $this->assert(
                in_array($latestReview['reviewer_role'], ['contractor_admin', 'contractor_manager']),
                "Reviewer role should be contractor_admin or contractor_manager"
            );
            
            // Test approval without comments
            $testData2 = $this->createTestFeasibilityCheck();
            $this->assert($testData2 !== null, "Second test feasibility check creation should succeed");
            
            $feasibilityId2 = $testData2['feasibility']['id'];
            
            $result2 = $this->reviewService->approveByContractor(
                $feasibilityId2,
                $this->testContractorAdminId,
                null // No comments
            );
            
            $this->assert($result2['success'], "Approval without comments should succeed");
            
            $reviews2 = $this->reviewService->getReviewsByFeasibility($feasibilityId2);
            $latestReview2 = $reviews2[0];
            
            $this->assert(
                $latestReview2['review_type'] === 'approval',
                "Review type should be 'approval' even without comments"
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
     * Property 19: Rejection validation
     * **Feature: feasibility-module, Property 19: Rejection validation**
     * **Validates: Requirements 10.3, 10.4, 10.5, 11.4**
     * 
     * For any rejection action, the system should require:
     * (1) rejection_type selection (overall or section_specific)
     * (2) for section_specific rejections, at least one section must be selected
     * (3) reason text with minimum 10 characters
     */
    public function testRejectionValidation(): array {
        try {
            // Test 1: Missing rejection_type should fail
            $validation1 = $this->reviewService->validateReviewData([
                'review_type' => 'rejection',
                'reason' => $this->generateValidRejectionReason()
            ]);
            
            $this->assert(
                !$validation1['isValid'],
                "Missing rejection_type should fail validation"
            );
            
            // Test 2: Invalid rejection_type should fail
            $validation2 = $this->reviewService->validateReviewData([
                'review_type' => 'rejection',
                'rejection_type' => 'invalid_type',
                'reason' => $this->generateValidRejectionReason()
            ]);
            
            $this->assert(
                !$validation2['isValid'],
                "Invalid rejection_type should fail validation"
            );
            
            // Test 3: Section-specific without sections should fail
            $validation3 = $this->reviewService->validateReviewData([
                'review_type' => 'rejection',
                'rejection_type' => 'section_specific',
                'rejected_sections' => [],
                'reason' => $this->generateValidRejectionReason()
            ]);
            
            $this->assert(
                !$validation3['isValid'],
                "Section-specific rejection without sections should fail"
            );
            
            // Test 4: Short reason should fail
            $validation4 = $this->reviewService->validateReviewData([
                'review_type' => 'rejection',
                'rejection_type' => 'overall',
                'reason' => $this->generateShortRejectionReason()
            ]);
            
            $this->assert(
                !$validation4['isValid'],
                "Short rejection reason should fail validation"
            );
            
            // Test 5: Valid overall rejection should pass
            $validation5 = $this->reviewService->validateReviewData([
                'review_type' => 'rejection',
                'rejection_type' => 'overall',
                'reason' => $this->generateValidRejectionReason()
            ]);
            
            $this->assert(
                $validation5['isValid'],
                "Valid overall rejection should pass validation"
            );
            
            // Test 6: Valid section-specific rejection should pass
            $validation6 = $this->reviewService->validateReviewData([
                'review_type' => 'rejection',
                'rejection_type' => 'section_specific',
                'rejected_sections' => $this->generateRandomSections(),
                'reason' => $this->generateValidRejectionReason()
            ]);
            
            $this->assert(
                $validation6['isValid'],
                "Valid section-specific rejection should pass validation"
            );
            
            // Test 7: Invalid section names should fail
            $validation7 = $this->reviewService->validateReviewData([
                'review_type' => 'rejection',
                'rejection_type' => 'section_specific',
                'rejected_sections' => ['invalid_section_name'],
                'reason' => $this->generateValidRejectionReason()
            ]);
            
            $this->assert(
                !$validation7['isValid'],
                "Invalid section names should fail validation"
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
     * Property 21: Contractor approval status transition
     * **Feature: feasibility-module, Property 21: Contractor approval status transition**
     * **Validates: Requirements 10.7**
     * 
     * For any contractor approval, the system should update the feasibility
     * approval_status to "contractor_approved".
     */
    public function testContractorApprovalStatusTransition(): array {
        try {
            // Create test feasibility check
            $testData = $this->createTestFeasibilityCheck();
            $this->assert($testData !== null, "Test feasibility check creation should succeed");
            
            $feasibilityId = $testData['feasibility']['id'];
            
            // Approve by contractor
            $result = $this->reviewService->approveByContractor(
                $feasibilityId,
                $this->testContractorAdminId,
                'Approved by contractor admin'
            );
            
            $this->assert($result['success'], "Contractor approval should succeed: " . ($result['message'] ?? ''));
            
            // Verify status is contractor_approved
            $feasibility = $this->getResults(
                "SELECT approval_status FROM feasibility_checks WHERE id = ?",
                [$feasibilityId],
                'i'
            );
            
            $this->assert(
                !empty($feasibility) && $feasibility[0]['approval_status'] === 'contractor_approved',
                "Feasibility approval_status should be 'contractor_approved'"
            );
            
            // Verify review record was created
            $reviews = $this->reviewService->getReviewsByFeasibility($feasibilityId);
            $this->assert(
                count($reviews) > 0,
                "Review record should be created"
            );
            
            $latestReview = $reviews[0];
            $this->assert(
                $latestReview['review_type'] === 'approval',
                "Review type should be 'approval'"
            );
            
            $this->assert(
                in_array($latestReview['reviewer_role'], ['contractor_admin', 'contractor_manager']),
                "Reviewer role should be contractor_admin or contractor_manager"
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
     * Property 20: Contractor rejection status transition
     * **Feature: feasibility-module, Property 20: Contractor rejection status transition**
     * **Validates: Requirements 10.6**
     * 
     * For any contractor rejection, the system should update the feasibility
     * approval_status to "contractor_rejected".
     */
    public function testContractorRejectionStatusTransition(): array {
        try {
            // Create test feasibility check
            $testData = $this->createTestFeasibilityCheck();
            $this->assert($testData !== null, "Test feasibility check creation should succeed");
            
            $feasibilityId = $testData['feasibility']['id'];
            
            // Reject by contractor
            $result = $this->reviewService->rejectByContractor(
                $feasibilityId,
                $this->testContractorAdminId,
                'overall',
                [],
                $this->generateValidRejectionReason()
            );
            
            $this->assert($result['success'], "Contractor rejection should succeed: " . ($result['message'] ?? ''));
            
            // Verify status is contractor_rejected
            $feasibility = $this->getResults(
                "SELECT approval_status FROM feasibility_checks WHERE id = ?",
                [$feasibilityId],
                'i'
            );
            
            $this->assert(
                !empty($feasibility) && $feasibility[0]['approval_status'] === 'contractor_rejected',
                "Feasibility approval_status should be 'contractor_rejected'"
            );
            
            // Verify review record was created
            $reviews = $this->reviewService->getReviewsByFeasibility($feasibilityId);
            $this->assert(
                count($reviews) > 0,
                "Review record should be created"
            );
            
            $latestReview = $reviews[0];
            $this->assert(
                $latestReview['review_type'] === 'rejection',
                "Review type should be 'rejection'"
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
     * Property 22: ADV review panel visibility
     * **Feature: feasibility-module, Property 22: ADV review panel visibility**
     * **Validates: Requirements 11.1, 11.2**
     * 
     * For any ADV user viewing a contractor-approved feasibility check, the system
     * should display the final approval panel with previous contractor review data.
     */
    public function testADVReviewPanelVisibility(): array {
        try {
            // Create test feasibility check
            $testData = $this->createTestFeasibilityCheck();
            $this->assert($testData !== null, "Test feasibility check creation should succeed");
            
            $feasibilityId = $testData['feasibility']['id'];
            
            // Test 1: ADV user should NOT be able to review before contractor approval
            // (feasibility is in pending_contractor_review status)
            $canReviewBeforeApproval = $this->reviewService->canUserReview($this->testAdvUserId, $feasibilityId);
            
            // ADV can technically review, but the status check in approveByADV will fail
            $this->assert(
                $canReviewBeforeApproval['canReview'] === true,
                "ADV user should have review capability"
            );
            
            $this->assert(
                $canReviewBeforeApproval['reviewerRole'] === 'adv',
                "Reviewer role should be 'adv'"
            );
            
            // Try to approve by ADV before contractor approval - should fail
            $prematureApproval = $this->reviewService->approveByADV(
                $feasibilityId,
                $this->testAdvUserId,
                'Premature approval attempt'
            );
            
            $this->assert(
                !$prematureApproval['success'],
                "ADV approval should fail before contractor approval"
            );
            
            $this->assert(
                $prematureApproval['code'] === 'INVALID_STATUS',
                "Error code should indicate invalid status"
            );
            
            // Test 2: Approve by contractor first
            $contractorApprovalComments = 'Contractor approval comments ' . $this->generateRandomString(20);
            $contractorResult = $this->reviewService->approveByContractor(
                $feasibilityId,
                $this->testContractorAdminId,
                $contractorApprovalComments
            );
            
            $this->assert($contractorResult['success'], "Contractor approval should succeed");
            
            // Test 3: Verify feasibility is now contractor_approved
            $feasibility = $this->getResults(
                "SELECT approval_status FROM feasibility_checks WHERE id = ?",
                [$feasibilityId],
                'i'
            );
            
            $this->assert(
                !empty($feasibility) && $feasibility[0]['approval_status'] === 'contractor_approved',
                "Feasibility should be in 'contractor_approved' status"
            );
            
            // Test 4: ADV user should now be able to review
            $canReviewAfterApproval = $this->reviewService->canUserReview($this->testAdvUserId, $feasibilityId);
            
            $this->assert(
                $canReviewAfterApproval['canReview'] === true,
                "ADV user should be able to review contractor-approved feasibility"
            );
            
            // Test 5: Verify previous contractor review is accessible (Requirement 11.2)
            $reviewHistory = $this->reviewService->getReviewHistory($feasibilityId);
            
            $this->assert(
                count($reviewHistory) > 0,
                "Review history should contain contractor approval"
            );
            
            // Find contractor approval in history
            $contractorApproval = null;
            foreach ($reviewHistory as $review) {
                if ($review['review_type'] === 'approval' && 
                    in_array($review['reviewer_role'], ['contractor_admin', 'contractor_manager'])) {
                    $contractorApproval = $review;
                    break;
                }
            }
            
            $this->assert(
                $contractorApproval !== null,
                "Contractor approval should be in review history"
            );
            
            $this->assert(
                $contractorApproval['comments'] === $contractorApprovalComments,
                "Contractor approval comments should be preserved"
            );
            
            // Test 6: ADV approval should now succeed
            $advResult = $this->reviewService->approveByADV(
                $feasibilityId,
                $this->testAdvUserId,
                'Final approval by ADV'
            );
            
            $this->assert($advResult['success'], "ADV approval should succeed after contractor approval");
            
            // Test 7: Verify final status
            $finalFeasibility = $this->getResults(
                "SELECT approval_status FROM feasibility_checks WHERE id = ?",
                [$feasibilityId],
                'i'
            );
            
            $this->assert(
                !empty($finalFeasibility) && $finalFeasibility[0]['approval_status'] === 'adv_approved',
                "Feasibility should be in 'adv_approved' status after ADV approval"
            );
            
            // Test 8: Engineer should NOT be able to review
            $canReviewEngineer = $this->reviewService->canUserReview($this->testEngineerId, $feasibilityId);
            
            $this->assert(
                $canReviewEngineer['canReview'] === false,
                "Engineer should NOT be able to review feasibility check"
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
     * Property 23: ADV approval status transition
     * **Feature: feasibility-module, Property 23: ADV approval status transition**
     * **Validates: Requirements 11.3**
     * 
     * For any ADV final approval, the system should update the feasibility
     * approval_status to "adv_approved".
     */
    public function testADVApprovalStatusTransition(): array {
        try {
            // Create test feasibility check
            $testData = $this->createTestFeasibilityCheck();
            $this->assert($testData !== null, "Test feasibility check creation should succeed");
            
            $feasibilityId = $testData['feasibility']['id'];
            
            // First, approve by contractor
            $contractorResult = $this->reviewService->approveByContractor(
                $feasibilityId,
                $this->testContractorAdminId,
                'Approved by contractor'
            );
            
            $this->assert($contractorResult['success'], "Contractor approval should succeed");
            
            // Then, approve by ADV
            $advResult = $this->reviewService->approveByADV(
                $feasibilityId,
                $this->testAdvUserId,
                'Final approval by ADV'
            );
            
            $this->assert($advResult['success'], "ADV approval should succeed: " . ($advResult['message'] ?? ''));
            
            // Verify status is adv_approved
            $feasibility = $this->getResults(
                "SELECT approval_status FROM feasibility_checks WHERE id = ?",
                [$feasibilityId],
                'i'
            );
            
            $this->assert(
                !empty($feasibility) && $feasibility[0]['approval_status'] === 'adv_approved',
                "Feasibility approval_status should be 'adv_approved'"
            );
            
            // Verify review record was created
            $reviews = $this->reviewService->getReviewsByFeasibility($feasibilityId);
            $advReview = null;
            foreach ($reviews as $review) {
                if ($review['reviewer_role'] === 'adv' && $review['review_type'] === 'approval') {
                    $advReview = $review;
                    break;
                }
            }
            
            $this->assert(
                $advReview !== null,
                "ADV approval review record should be created"
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
     * Property 24: ADV rejection status transition
     * **Feature: feasibility-module, Property 24: ADV rejection status transition**
     * **Validates: Requirements 11.5**
     * 
     * For any ADV rejection, the system should update the feasibility
     * approval_status to "adv_rejected".
     */
    public function testADVRejectionStatusTransition(): array {
        try {
            // Create test feasibility check
            $testData = $this->createTestFeasibilityCheck();
            $this->assert($testData !== null, "Test feasibility check creation should succeed");
            
            $feasibilityId = $testData['feasibility']['id'];
            
            // First, approve by contractor
            $contractorResult = $this->reviewService->approveByContractor(
                $feasibilityId,
                $this->testContractorAdminId,
                'Approved by contractor'
            );
            
            $this->assert($contractorResult['success'], "Contractor approval should succeed");
            
            // Then, reject by ADV
            $advResult = $this->reviewService->rejectByADV(
                $feasibilityId,
                $this->testAdvUserId,
                'overall',
                [],
                $this->generateValidRejectionReason()
            );
            
            $this->assert($advResult['success'], "ADV rejection should succeed: " . ($advResult['message'] ?? ''));
            
            // Verify status is adv_rejected
            $feasibility = $this->getResults(
                "SELECT approval_status FROM feasibility_checks WHERE id = ?",
                [$feasibilityId],
                'i'
            );
            
            $this->assert(
                !empty($feasibility) && $feasibility[0]['approval_status'] === 'adv_rejected',
                "Feasibility approval_status should be 'adv_rejected'"
            );
            
            // Verify review record was created
            $reviews = $this->reviewService->getReviewsByFeasibility($feasibilityId);
            $advReview = null;
            foreach ($reviews as $review) {
                if ($review['reviewer_role'] === 'adv' && $review['review_type'] === 'rejection') {
                    $advReview = $review;
                    break;
                }
            }
            
            $this->assert(
                $advReview !== null,
                "ADV rejection review record should be created"
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
     * Property 28: Resubmission status reset
     * **Feature: feasibility-module, Property 28: Resubmission status reset**
     * **Validates: Requirements 12.4**
     * 
     * For any resubmission of a rejected feasibility check, the approval_status
     * should be reset to "pending_contractor_review".
     */
    public function testResubmissionStatusReset(): array {
        try {
            // Create test feasibility check
            $testData = $this->createTestFeasibilityCheck();
            $this->assert($testData !== null, "Test feasibility check creation should succeed");
            
            $feasibilityId = $testData['feasibility']['id'];
            
            // Reject by contractor with section-specific rejection
            $rejectedSections = ['atm_information', 'network_information'];
            $rejectResult = $this->reviewService->rejectByContractor(
                $feasibilityId,
                $this->testContractorAdminId,
                'section_specific',
                $rejectedSections,
                $this->generateValidRejectionReason()
            );
            
            $this->assert($rejectResult['success'], "Contractor rejection should succeed");
            
            // Verify status is contractor_rejected
            $feasibility1 = $this->getResults(
                "SELECT approval_status FROM feasibility_checks WHERE id = ?",
                [$feasibilityId],
                'i'
            );
            
            $this->assert(
                $feasibility1[0]['approval_status'] === 'contractor_rejected',
                "Status should be contractor_rejected before resubmission"
            );
            
            // Resubmit with updated data
            $updatedData = [
                'no_of_atm' => rand(1, 3),
                'atm_id_1' => 'ATM_UPDATED_' . uniqid(),
                'operator' => 'Jio'
            ];
            
            $resubmitResult = $this->reviewService->resubmitFeasibility(
                $feasibilityId,
                $updatedData,
                $this->testEngineerId
            );
            
            $this->assert($resubmitResult['success'], "Resubmission should succeed: " . ($resubmitResult['message'] ?? ''));
            
            // Verify status is reset to pending_contractor_review
            $feasibility2 = $this->getResults(
                "SELECT approval_status FROM feasibility_checks WHERE id = ?",
                [$feasibilityId],
                'i'
            );
            
            $this->assert(
                !empty($feasibility2) && $feasibility2[0]['approval_status'] === 'pending_contractor_review',
                "Feasibility approval_status should be reset to 'pending_contractor_review'"
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
     * Property 25: ADV-approved immutability
     * **Feature: feasibility-module, Property 25: ADV-approved immutability**
     * **Validates: Requirements 11.6**
     * 
     * For any feasibility check with approval_status "adv_approved",
     * modification attempts should be rejected.
     */
    public function testADVApprovedImmutability(): array {
        try {
            // Create test feasibility check
            $testData = $this->createTestFeasibilityCheck();
            $this->assert($testData !== null, "Test feasibility check creation should succeed");
            
            $feasibilityId = $testData['feasibility']['id'];
            
            // Approve by contractor
            $contractorResult = $this->reviewService->approveByContractor(
                $feasibilityId,
                $this->testContractorAdminId,
                'Approved by contractor'
            );
            
            $this->assert($contractorResult['success'], "Contractor approval should succeed");
            
            // Approve by ADV
            $advResult = $this->reviewService->approveByADV(
                $feasibilityId,
                $this->testAdvUserId,
                'Final approval by ADV'
            );
            
            $this->assert($advResult['success'], "ADV approval should succeed");
            
            // Verify status is adv_approved
            $feasibility = $this->getResults(
                "SELECT approval_status FROM feasibility_checks WHERE id = ?",
                [$feasibilityId],
                'i'
            );
            
            $this->assert(
                $feasibility[0]['approval_status'] === 'adv_approved',
                "Status should be adv_approved"
            );
            
            // Try to modify - should fail
            $canModify = $this->reviewService->canModifyFeasibility($feasibilityId);
            
            $this->assert(
                !$canModify['canModify'],
                "ADV-approved feasibility should not be modifiable"
            );
            
            // Try to resubmit - should fail
            $resubmitResult = $this->reviewService->resubmitFeasibility(
                $feasibilityId,
                ['remarks' => 'Updated remarks'],
                $this->testEngineerId
            );
            
            $this->assert(
                !$resubmitResult['success'],
                "Resubmission of ADV-approved feasibility should fail"
            );
            
            $this->assert(
                $resubmitResult['code'] === 'INVALID_STATUS',
                "Error code should indicate invalid status"
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
        // Delete reviews first
        foreach ($this->createdFeasibilityIds as $feasibilityId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM feasibility_reviews WHERE feasibility_id = ?",
                    [$feasibilityId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete feasibility checks
        foreach ($this->createdFeasibilityIds as $feasibilityId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM feasibility_checks WHERE id = ?",
                    [$feasibilityId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdFeasibilityIds = [];
        
        // Delete assignments
        foreach ($this->createdAssignmentIds as $assignmentId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM feasibility_ada WHERE assignment_id = ?",
                    [$assignmentId],
                    'i'
                );
                $stmt->close();
                
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

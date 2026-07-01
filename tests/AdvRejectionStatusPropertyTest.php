<?php
/**
 * Property Test: ADV Rejection Status
 * 
 * **Feature: installation-module, Property 24: ADV rejection status transition**
 * **Validates: Requirements 15.4**
 * 
 * Property: For any section rejection by ADV, the system should require a rejection
 * reason and update the overall installation status to 'adv_rejected'.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationReviewService.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/../models/InstallationCheckpoint.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class AdvRejectionStatusPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    private $reviewService;
    private $installationService;
    private $installationRepository;
    private $db;
    private $createdInstallationIds = [];
    private $createdSiteIds = [];
    private $createdFeasibilityIds = [];
    private $createdAssignmentIds = [];
    private $createdDelegationIds = [];
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->reviewService = new InstallationReviewService();
        $this->installationService = new InstallationService();
        $this->installationRepository = new InstallationRepository();
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== ADV Rejection Status Property Tests ===\n";
        echo "**Feature: installation-module, Property 24: ADV rejection status transition**\n";
        echo "**Validates: Requirements 15.4**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'ADV rejection requires reason (minimum 10 characters)',
            [$this, 'testAdvRejectionRequiresReason']
        );
        
        $this->runPropertyTest(
            'ADV rejection with short reason is rejected',
            [$this, 'testAdvRejectionWithShortReasonIsRejected']
        );
        
        $this->runPropertyTest(
            'ADV rejection updates status to adv_rejected',
            [$this, 'testAdvRejectionUpdatesStatusToAdvRejected']
        );
        
        $this->runPropertyTest(
            'ADV rejection creates review record with reason',
            [$this, 'testAdvRejectionCreatesReviewRecordWithReason']
        );
        
        // Cleanup
        $this->cleanup();
        
        // Summary
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($this->testResults));
        $total = count($this->testResults);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }
    
    /**
     * Run a property test with multiple iterations
     */
    private function runPropertyTest(string $name, callable $testFunction): void {
        echo "Testing: $name\n";
        $failures = [];
        
        for ($i = 0; $i < $this->iterations; $i++) {
            try {
                $result = $testFunction();
                if (!$result['success']) {
                    $failures[] = "Iteration $i: {$result['message']}";
                }
            } catch (Exception $e) {
                $failures[] = "Iteration $i: Exception - {$e->getMessage()}";
            }
        }
        
        if (empty($failures)) {
            echo "  ✓ Passed ({$this->iterations} iterations)\n";
            $this->testResults[$name] = true;
        } else {
            echo "  ✗ Failed\n";
            foreach (array_slice($failures, 0, 3) as $failure) {
                echo "    - $failure\n";
            }
            if (count($failures) > 3) {
                echo "    ... and " . (count($failures) - 3) . " more failures\n";
            }
            $this->testResults[$name] = false;
        }
    }


    /**
     * Property Test: ADV rejection requires reason (minimum 10 characters)
     * For any ADV rejection, a reason of at least 10 characters must be provided
     */
    private function testAdvRejectionRequiresReason(): array {
        // Create test installation in contractor_approved status
        $testData = $this->createTestInstallationInContractorApprovedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Pick a random section
        $sections = InstallationSections::getAll();
        $randomSection = $sections[array_rand($sections)];
        
        // Generate a valid reason (10+ characters)
        $validReason = 'ADV Rejection: ' . $this->generateRandomString(20);
        
        // ADV rejects the section with valid reason
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $validReason,
            InstallationCheckpoint::LEVEL_ADV
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to ADV reject section with valid reason: ' . $result['message']
            ];
        }
        
        // Verify the rejection was recorded
        if (!isset($result['data']['remark'])) {
            return [
                'success' => false,
                'message' => 'Rejection remark was not returned'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: ADV rejection with short reason is rejected
     * For any ADV rejection with reason less than 10 characters, the rejection should fail
     */
    private function testAdvRejectionWithShortReasonIsRejected(): array {
        // Create test installation in contractor_approved status
        $testData = $this->createTestInstallationInContractorApprovedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Pick a random section
        $sections = InstallationSections::getAll();
        $randomSection = $sections[array_rand($sections)];
        
        // Generate a short reason (less than 10 characters)
        $shortReasonLength = rand(1, 9);
        $shortReason = $this->generateRandomString($shortReasonLength);
        
        // ADV tries to reject the section with short reason
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $shortReason,
            InstallationCheckpoint::LEVEL_ADV
        );
        
        // Should fail with REASON_TOO_SHORT error
        if ($result['success']) {
            return [
                'success' => false,
                'message' => "ADV rejection with short reason ($shortReasonLength chars) should have failed"
            ];
        }
        
        if ($result['code'] !== 'REASON_TOO_SHORT') {
            return [
                'success' => false,
                'message' => "Expected error code 'REASON_TOO_SHORT', got '{$result['code']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: ADV rejection updates status to adv_rejected
     * For any valid ADV rejection, the installation status should be updated to 'adv_rejected'
     */
    private function testAdvRejectionUpdatesStatusToAdvRejected(): array {
        // Create test installation in contractor_approved status
        $testData = $this->createTestInstallationInContractorApprovedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Pick a random section
        $sections = InstallationSections::getAll();
        $randomSection = $sections[array_rand($sections)];
        
        // Generate a valid reason
        $validReason = 'ADV Rejection reason: ' . $this->generateRandomString(15);
        
        // ADV rejects the section
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $validReason,
            InstallationCheckpoint::LEVEL_ADV
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to ADV reject section: ' . $result['message']
            ];
        }
        
        // Verify installation status is adv_rejected
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found after ADV rejection'
            ];
        }
        
        if ($installation['status'] !== Installation::STATUS_ADV_REJECTED) {
            return [
                'success' => false,
                'message' => "Expected status 'adv_rejected', got '{$installation['status']}'"
            ];
        }
        
        // Verify the result data also indicates adv_rejected
        if ($result['data']['installation_status'] !== Installation::STATUS_ADV_REJECTED) {
            return [
                'success' => false,
                'message' => "Result data expected 'adv_rejected', got '{$result['data']['installation_status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: ADV rejection creates review record with reason
     * For any valid ADV rejection, a review record should be created with the rejection reason
     */
    private function testAdvRejectionCreatesReviewRecordWithReason(): array {
        // Create test installation in contractor_approved status
        $testData = $this->createTestInstallationInContractorApprovedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Pick a random section
        $sections = InstallationSections::getAll();
        $randomSection = $sections[array_rand($sections)];
        
        // Generate a valid reason
        $validReason = 'ADV Rejection: Quality issue - ' . $this->generateRandomString(20);
        
        // ADV rejects the section
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $validReason,
            InstallationCheckpoint::LEVEL_ADV
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to ADV reject section: ' . $result['message']
            ];
        }
        
        // Verify remark was created with correct data
        $remark = $result['data']['remark'];
        if (!$remark) {
            return [
                'success' => false,
                'message' => 'Rejection remark was not created'
            ];
        }
        
        // Verify reviewer_level is 'adv'
        if ($remark['reviewer_level'] !== InstallationCheckpoint::LEVEL_ADV) {
            return [
                'success' => false,
                'message' => "Expected reviewer_level 'adv', got '{$remark['reviewer_level']}'"
            ];
        }
        
        // Verify review_type is 'rejection'
        if ($remark['review_type'] !== 'rejection') {
            return [
                'success' => false,
                'message' => "Expected review_type 'rejection', got '{$remark['review_type']}'"
            ];
        }
        
        // Verify reason was stored
        if ($remark['remark'] !== $validReason) {
            return [
                'success' => false,
                'message' => "Expected reason '$validReason', got '{$remark['remark']}'"
            ];
        }
        
        return ['success' => true];
    }


    // ==================== Helper Methods ====================
    
    /**
     * Generate random string
     */
    private function generateRandomString(int $length = 10): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $result;
    }
    
    /**
     * Create test installation in contractor_approved status
     * This is the prerequisite for ADV review
     */
    private function createTestInstallationInContractorApprovedStatus(): array {
        try {
            // Create a test site
            $siteName = 'TestSite-' . $this->generateRandomString(8);
            $sql = "INSERT INTO sites (site_name, lho, city, state, country, company_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'active')";
            $stmt = $this->db->executeQuery($sql, [
                $siteName,
                'LHO-' . $this->generateRandomString(4),
                'City-' . $this->generateRandomString(6),
                'State-' . $this->generateRandomString(6),
                'Country',
                1
            ], 'sssssi');
            $siteId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdSiteIds[] = $siteId;
            
            // Create a site delegation
            $sql = "INSERT INTO site_delegations (site_id, contractor_id, delegated_by, status) 
                    VALUES (?, 1, 1, 'accepted')";
            $stmt = $this->db->executeQuery($sql, [$siteId], 'i');
            $delegationId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdDelegationIds[] = $delegationId;
            
            // Create an engineer assignment
            $sql = "INSERT INTO engineer_assignments (site_id, delegation_id, engineer_id, assigned_by, status, feasibility_status) 
                    VALUES (?, ?, 1, 1, 'assigned', 'feasibility_completed')";
            $stmt = $this->db->executeQuery($sql, [$siteId, $delegationId], 'ii');
            $assignmentId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdAssignmentIds[] = $assignmentId;
            
            // Create a feasibility check with ADV-approved status
            $sql = "INSERT INTO feasibility_checks (assignment_id, site_id, created_by, status, approval_status) 
                    VALUES (?, ?, 1, 'active', 'adv_approved')";
            $stmt = $this->db->executeQuery($sql, [$assignmentId, $siteId], 'ii');
            $feasibilityId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdFeasibilityIds[] = $feasibilityId;
            
            // Create installation directly in contractor_approved status
            $sql = "INSERT INTO installations (site_id, feasibility_id, initiated_by, created_by, atm_id, status) 
                    VALUES (?, ?, 1, 1, ?, 'contractor_approved')";
            $stmt = $this->db->executeQuery($sql, [$siteId, $feasibilityId, $siteName], 'iis');
            $installationId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdInstallationIds[] = $installationId;
            
            // Initialize checkpoints for the installation (all contractor-approved)
            $this->initializeContractorApprovedCheckpoints($installationId);
            
            return [
                'success' => true,
                'installation_id' => $installationId,
                'site_id' => $siteId,
                'feasibility_id' => $feasibilityId
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create test data: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Initialize checkpoints with contractor-approved status
     */
    private function initializeContractorApprovedCheckpoints(int $installationId): void {
        $sections = InstallationSections::getAll();
        
        foreach ($sections as $section) {
            $sql = "INSERT INTO installation_checkpoints 
                    (installation_id, section, contractor_status, contractor_reviewer_id, contractor_reviewed_at, adv_status) 
                    VALUES (?, ?, 'approved', 1, NOW(), 'pending')";
            $stmt = $this->db->executeQuery($sql, [$installationId, $section], 'is');
            $stmt->close();
        }
    }
    
    /**
     * Cleanup test data
     */
    private function cleanup(): void {
        try {
            // Delete remarks by installation
            if (!empty($this->createdInstallationIds)) {
                $ids = implode(',', array_map('intval', $this->createdInstallationIds));
                $this->db->executeQuery("DELETE FROM installation_section_remarks WHERE installation_id IN ($ids)", [], '');
                $this->db->executeQuery("DELETE FROM installation_checkpoints WHERE installation_id IN ($ids)", [], '');
            }
            
            // Delete installations
            if (!empty($this->createdInstallationIds)) {
                $ids = implode(',', array_map('intval', $this->createdInstallationIds));
                $this->db->executeQuery("DELETE FROM installations WHERE id IN ($ids)", [], '');
            }
            
            // Delete feasibility checks
            if (!empty($this->createdFeasibilityIds)) {
                $ids = implode(',', array_map('intval', $this->createdFeasibilityIds));
                $this->db->executeQuery("DELETE FROM feasibility_checks WHERE id IN ($ids)", [], '');
            }
            
            // Delete engineer assignments
            if (!empty($this->createdAssignmentIds)) {
                $ids = implode(',', array_map('intval', $this->createdAssignmentIds));
                $this->db->executeQuery("DELETE FROM engineer_assignments WHERE id IN ($ids)", [], '');
            }
            
            // Delete site delegations
            if (!empty($this->createdDelegationIds)) {
                $ids = implode(',', array_map('intval', $this->createdDelegationIds));
                $this->db->executeQuery("DELETE FROM site_delegations WHERE id IN ($ids)", [], '');
            }
            
            // Delete sites
            if (!empty($this->createdSiteIds)) {
                $ids = implode(',', array_map('intval', $this->createdSiteIds));
                $this->db->executeQuery("DELETE FROM sites WHERE id IN ($ids)", [], '');
            }
        } catch (Exception $e) {
            echo "Warning: Cleanup failed - " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new AdvRejectionStatusPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

<?php
/**
 * Property Test: Section Approval
 * 
 * **Feature: installation-module, Property 18: Section approval creates review record**
 * **Validates: Requirements 14.2**
 * 
 * Property: For any section approval action by a contractor reviewer, the system should
 * create a review record with reviewer_id, timestamp, review_type='approval', and update
 * the section's contractor_status to 'approved'.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationReviewService.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/../models/InstallationCheckpoint.php';
require_once __DIR__ . '/../models/InstallationSectionRemark.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class SectionApprovalPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    private $reviewService;
    private $installationService;
    private $db;
    private $createdInstallationIds = [];
    private $createdSiteIds = [];
    private $createdFeasibilityIds = [];
    private $createdAssignmentIds = [];
    private $createdDelegationIds = [];
    private $createdCheckpointIds = [];
    private $createdRemarkIds = [];
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->reviewService = new InstallationReviewService();
        $this->installationService = new InstallationService();
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Section Approval Property Tests ===\n";
        echo "**Feature: installation-module, Property 18: Section approval creates review record**\n";
        echo "**Validates: Requirements 14.2**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Section approval creates review record with correct data',
            [$this, 'testSectionApprovalCreatesReviewRecord']
        );
        
        $this->runPropertyTest(
            'Section approval updates contractor_status to approved',
            [$this, 'testSectionApprovalUpdatesContractorStatus']
        );
        
        $this->runPropertyTest(
            'Section approval records reviewer_id and timestamp',
            [$this, 'testSectionApprovalRecordsReviewerAndTimestamp']
        );
        
        $this->runPropertyTest(
            'Section approval with remarks stores remarks correctly',
            [$this, 'testSectionApprovalWithRemarks']
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
     * Property Test: Section approval creates review record with correct data
     * For any section approval, a review record should be created with review_type='approval'
     */
    private function testSectionApprovalCreatesReviewRecord(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1; // Test reviewer
        
        // Pick a random section
        $sections = InstallationSections::getAll();
        $randomSection = $sections[array_rand($sections)];
        
        // Approve the section
        $result = $this->reviewService->approveSection(
            $installationId,
            $randomSection,
            $reviewerId,
            null,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to approve section: ' . $result['message']
            ];
        }
        
        // Verify review record was created
        $remark = $result['data']['remark'];
        if (!$remark) {
            return [
                'success' => false,
                'message' => 'Review record was not created'
            ];
        }
        
        $this->createdRemarkIds[] = $remark['id'];
        
        // Verify review_type is 'approval'
        if ($remark['review_type'] !== InstallationSectionRemark::TYPE_APPROVAL) {
            return [
                'success' => false,
                'message' => "Expected review_type 'approval', got '{$remark['review_type']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Section approval updates contractor_status to approved
     * For any section approval, the checkpoint contractor_status should be 'approved'
     */
    private function testSectionApprovalUpdatesContractorStatus(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Pick a random section
        $sections = InstallationSections::getAll();
        $randomSection = $sections[array_rand($sections)];
        
        // Approve the section
        $result = $this->reviewService->approveSection(
            $installationId,
            $randomSection,
            $reviewerId,
            null,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to approve section: ' . $result['message']
            ];
        }
        
        // Verify checkpoint status
        $checkpoint = $result['data']['checkpoint'];
        if (!$checkpoint) {
            return [
                'success' => false,
                'message' => 'Checkpoint was not returned'
            ];
        }
        
        $this->createdCheckpointIds[] = $checkpoint['id'];
        
        if ($checkpoint['contractor_status'] !== InstallationCheckpoint::STATUS_APPROVED) {
            return [
                'success' => false,
                'message' => "Expected contractor_status 'approved', got '{$checkpoint['contractor_status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Section approval records reviewer_id and timestamp
     * For any section approval, reviewer_id and reviewed_at should be recorded
     */
    private function testSectionApprovalRecordsReviewerAndTimestamp(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Pick a random section
        $sections = InstallationSections::getAll();
        $randomSection = $sections[array_rand($sections)];
        
        // Approve the section
        $result = $this->reviewService->approveSection(
            $installationId,
            $randomSection,
            $reviewerId,
            null,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to approve section: ' . $result['message']
            ];
        }
        
        // Verify checkpoint has reviewer_id
        $checkpoint = $result['data']['checkpoint'];
        if ((int)$checkpoint['contractor_reviewer_id'] !== $reviewerId) {
            return [
                'success' => false,
                'message' => "Expected contractor_reviewer_id $reviewerId, got {$checkpoint['contractor_reviewer_id']}"
            ];
        }
        
        // Verify checkpoint has reviewed_at timestamp
        if (empty($checkpoint['contractor_reviewed_at'])) {
            return [
                'success' => false,
                'message' => 'contractor_reviewed_at timestamp was not recorded'
            ];
        }
        
        // Verify remark has reviewer_id
        $remark = $result['data']['remark'];
        if ((int)$remark['reviewer_id'] !== $reviewerId) {
            return [
                'success' => false,
                'message' => "Expected remark reviewer_id $reviewerId, got {$remark['reviewer_id']}"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Section approval with remarks stores remarks correctly
     * For any section approval with remarks, the remarks should be stored
     */
    private function testSectionApprovalWithRemarks(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Pick a random section
        $sections = InstallationSections::getAll();
        $randomSection = $sections[array_rand($sections)];
        
        // Generate random remarks
        $randomRemarks = 'Approval remarks: ' . $this->generateRandomString(20);
        
        // Approve the section with remarks
        $result = $this->reviewService->approveSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $randomRemarks,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to approve section: ' . $result['message']
            ];
        }
        
        // Verify remarks were stored
        $remark = $result['data']['remark'];
        if ($remark['remark'] !== $randomRemarks) {
            return [
                'success' => false,
                'message' => "Expected remarks '$randomRemarks', got '{$remark['remark']}'"
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
     * Create test installation in submitted status
     */
    private function createTestInstallationInSubmittedStatus(): array {
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
            
            // Create installation directly in submitted status
            $sql = "INSERT INTO installations (site_id, feasibility_id, initiated_by, created_by, atm_id, status) 
                    VALUES (?, ?, 1, 1, ?, 'submitted')";
            $stmt = $this->db->executeQuery($sql, [$siteId, $feasibilityId, $siteName], 'iis');
            $installationId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdInstallationIds[] = $installationId;
            
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
     * Cleanup test data
     */
    private function cleanup(): void {
        try {
            // Delete remarks
            if (!empty($this->createdRemarkIds)) {
                $ids = implode(',', array_map('intval', $this->createdRemarkIds));
                $this->db->executeQuery("DELETE FROM installation_section_remarks WHERE id IN ($ids)", [], '');
            }
            
            // Delete checkpoints
            if (!empty($this->createdCheckpointIds)) {
                $ids = implode(',', array_map('intval', $this->createdCheckpointIds));
                $this->db->executeQuery("DELETE FROM installation_checkpoints WHERE id IN ($ids)", [], '');
            }
            
            // Delete checkpoints by installation
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
    $test = new SectionApprovalPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

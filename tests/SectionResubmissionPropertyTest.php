<?php
/**
 * Property Test: Section Resubmission Status Reset
 * 
 * **Feature: installation-module, Property 28: Section resubmission status reset**
 * **Validates: Requirements 16.4**
 * 
 * Property: For any resubmission of a rejected section, that section's approval
 * status should be reset to 'pending'.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationReviewService.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../repositories/InstallationCheckpointRepository.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/../models/InstallationCheckpoint.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class SectionResubmissionPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    private $reviewService;
    private $installationService;
    private $installationRepository;
    private $checkpointRepository;
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
        $this->checkpointRepository = new InstallationCheckpointRepository();
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Section Resubmission Property Tests ===\n";
        echo "**Feature: installation-module, Property 28: Section resubmission status reset**\n";
        echo "**Validates: Requirements 16.4**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Section resubmission resets contractor status to pending',
            [$this, 'testSectionResubmissionResetsContractorStatus']
        );
        
        $this->runPropertyTest(
            'Section resubmission resets ADV status to pending',
            [$this, 'testSectionResubmissionResetsAdvStatus']
        );
        
        $this->runPropertyTest(
            'Section resubmission clears reviewer information',
            [$this, 'testSectionResubmissionClearsReviewerInfo']
        );
        
        $this->runPropertyTest(
            'Section resubmission returns success for rejected sections',
            [$this, 'testSectionResubmissionSucceedsForRejectedSections']
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
     * Property Test: Section resubmission resets contractor status to pending
     * For any rejected section resubmission, the contractor_status should be reset to 'pending'
     * Requirements: 16.4
     */
    private function testSectionResubmissionResetsContractorStatus(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        $engineerId = 1;
        
        // Pick a random section to reject
        $sections = InstallationSections::getAll();
        $rejectedSection = $sections[array_rand($sections)];
        
        // Reject the section
        $reason = 'Rejection reason: ' . $this->generateRandomString(20);
        $result = $this->reviewService->rejectSection(
            $installationId,
            $rejectedSection,
            $reviewerId,
            $reason,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to reject section: ' . $result['message']
            ];
        }
        
        // Verify section is rejected
        $checkpoint = $this->checkpointRepository->getSectionStatus($installationId, $rejectedSection);
        if ($checkpoint['contractor_status'] !== InstallationCheckpoint::STATUS_REJECTED) {
            return [
                'success' => false,
                'message' => "Expected contractor_status 'rejected', got '{$checkpoint['contractor_status']}'"
            ];
        }
        
        // Resubmit the section
        $result = $this->reviewService->resubmitSection(
            $installationId,
            $rejectedSection,
            ['test_field' => 'updated_value'],
            $engineerId
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to resubmit section: ' . $result['message']
            ];
        }
        
        // Verify contractor_status is reset to pending
        $checkpoint = $this->checkpointRepository->getSectionStatus($installationId, $rejectedSection);
        if ($checkpoint['contractor_status'] !== InstallationCheckpoint::STATUS_PENDING) {
            return [
                'success' => false,
                'message' => "Expected contractor_status 'pending' after resubmission, got '{$checkpoint['contractor_status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Section resubmission resets ADV status to pending
     * For any rejected section resubmission, the adv_status should also be reset to 'pending'
     * Requirements: 16.4
     */
    private function testSectionResubmissionResetsAdvStatus(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        $engineerId = 1;
        
        // Pick a random section to reject
        $sections = InstallationSections::getAll();
        $rejectedSection = $sections[array_rand($sections)];
        
        // Reject the section at contractor level
        $reason = 'Rejection reason: ' . $this->generateRandomString(20);
        $result = $this->reviewService->rejectSection(
            $installationId,
            $rejectedSection,
            $reviewerId,
            $reason,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to reject section: ' . $result['message']
            ];
        }
        
        // Resubmit the section
        $result = $this->reviewService->resubmitSection(
            $installationId,
            $rejectedSection,
            ['test_field' => 'updated_value'],
            $engineerId
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to resubmit section: ' . $result['message']
            ];
        }
        
        // Verify adv_status is also reset to pending
        $checkpoint = $this->checkpointRepository->getSectionStatus($installationId, $rejectedSection);
        if ($checkpoint['adv_status'] !== InstallationCheckpoint::STATUS_PENDING) {
            return [
                'success' => false,
                'message' => "Expected adv_status 'pending' after resubmission, got '{$checkpoint['adv_status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Section resubmission clears reviewer information
     * For any rejected section resubmission, the reviewer_id and reviewed_at should be cleared
     * Requirements: 16.4
     */
    private function testSectionResubmissionClearsReviewerInfo(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        $engineerId = 1;
        
        // Pick a random section to reject
        $sections = InstallationSections::getAll();
        $rejectedSection = $sections[array_rand($sections)];
        
        // Reject the section
        $reason = 'Rejection reason: ' . $this->generateRandomString(20);
        $result = $this->reviewService->rejectSection(
            $installationId,
            $rejectedSection,
            $reviewerId,
            $reason,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to reject section: ' . $result['message']
            ];
        }
        
        // Verify reviewer info is set
        $checkpoint = $this->checkpointRepository->getSectionStatus($installationId, $rejectedSection);
        if (empty($checkpoint['contractor_reviewer_id']) || empty($checkpoint['contractor_reviewed_at'])) {
            return [
                'success' => false,
                'message' => 'Reviewer info should be set after rejection'
            ];
        }
        
        // Resubmit the section
        $result = $this->reviewService->resubmitSection(
            $installationId,
            $rejectedSection,
            ['test_field' => 'updated_value'],
            $engineerId
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to resubmit section: ' . $result['message']
            ];
        }
        
        // Verify reviewer info is cleared
        $checkpoint = $this->checkpointRepository->getSectionStatus($installationId, $rejectedSection);
        if (!empty($checkpoint['contractor_reviewer_id']) || !empty($checkpoint['contractor_reviewed_at'])) {
            return [
                'success' => false,
                'message' => 'Reviewer info should be cleared after resubmission'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Section resubmission returns success for rejected sections
     * For any rejected section, resubmission should succeed and return success status
     * Requirements: 16.4
     */
    private function testSectionResubmissionSucceedsForRejectedSections(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        $engineerId = 1;
        
        // Pick a random section to reject
        $sections = InstallationSections::getAll();
        $rejectedSection = $sections[array_rand($sections)];
        
        // Reject the section
        $reason = 'Rejection reason: ' . $this->generateRandomString(20);
        $result = $this->reviewService->rejectSection(
            $installationId,
            $rejectedSection,
            $reviewerId,
            $reason,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to reject section: ' . $result['message']
            ];
        }
        
        // Resubmit the section
        $result = $this->reviewService->resubmitSection(
            $installationId,
            $rejectedSection,
            ['test_field' => 'updated_value'],
            $engineerId
        );
        
        // Verify resubmission succeeded
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Resubmission should succeed for rejected section: ' . $result['message']
            ];
        }
        
        // Verify the returned section matches
        if ($result['data']['section'] !== $rejectedSection) {
            return [
                'success' => false,
                'message' => "Expected section '$rejectedSection' in result, got '{$result['data']['section']}'"
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
            // Delete checkpoints and remarks by installation
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
    $test = new SectionResubmissionPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

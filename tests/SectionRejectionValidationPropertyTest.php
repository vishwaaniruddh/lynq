<?php
/**
 * Property Test: Section Rejection Validation
 * 
 * **Feature: installation-module, Property 19: Section rejection validation**
 * **Validates: Requirements 14.3**
 * 
 * Property: For any section rejection action, the system should require a rejection
 * reason with minimum 10 characters. Rejections with shorter reasons should be rejected.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationReviewService.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/../models/InstallationCheckpoint.php';
require_once __DIR__ . '/../models/InstallationSectionRemark.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class SectionRejectionValidationPropertyTest {
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
    
    const MIN_REJECTION_REASON_LENGTH = 10;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->reviewService = new InstallationReviewService();
        $this->installationService = new InstallationService();
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Section Rejection Validation Property Tests ===\n";
        echo "**Feature: installation-module, Property 19: Section rejection validation**\n";
        echo "**Validates: Requirements 14.3**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Rejection with reason >= 10 chars succeeds',
            [$this, 'testRejectionWithValidReasonSucceeds']
        );
        
        $this->runPropertyTest(
            'Rejection with reason < 10 chars fails',
            [$this, 'testRejectionWithShortReasonFails']
        );
        
        $this->runPropertyTest(
            'Rejection with empty reason fails',
            [$this, 'testRejectionWithEmptyReasonFails']
        );
        
        $this->runPropertyTest(
            'Rejection with whitespace-only reason fails',
            [$this, 'testRejectionWithWhitespaceOnlyReasonFails']
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
     * Property Test: Rejection with reason >= 10 chars succeeds
     * For any rejection with a reason of at least 10 characters, the rejection should succeed
     */
    private function testRejectionWithValidReasonSucceeds(): array {
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
        
        // Generate a valid reason (>= 10 characters)
        $reasonLength = rand(self::MIN_REJECTION_REASON_LENGTH, 100);
        $validReason = $this->generateRandomString($reasonLength);
        
        // Reject the section
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $validReason,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => "Rejection with valid reason ($reasonLength chars) should succeed: " . $result['message']
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Rejection with reason < 10 chars fails
     * For any rejection with a reason shorter than 10 characters, the rejection should fail
     */
    private function testRejectionWithShortReasonFails(): array {
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
        
        // Generate a short reason (< 10 characters)
        $reasonLength = rand(1, self::MIN_REJECTION_REASON_LENGTH - 1);
        $shortReason = $this->generateRandomString($reasonLength);
        
        // Attempt to reject the section
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $shortReason,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        // Should fail
        if ($result['success']) {
            return [
                'success' => false,
                'message' => "Rejection with short reason ($reasonLength chars) should fail"
            ];
        }
        
        // Verify correct error code
        if ($result['code'] !== 'REASON_TOO_SHORT') {
            return [
                'success' => false,
                'message' => "Expected error code 'REASON_TOO_SHORT', got '{$result['code']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Rejection with empty reason fails
     * For any rejection with an empty reason, the rejection should fail
     */
    private function testRejectionWithEmptyReasonFails(): array {
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
        
        // Attempt to reject with empty reason
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            '',
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        // Should fail
        if ($result['success']) {
            return [
                'success' => false,
                'message' => 'Rejection with empty reason should fail'
            ];
        }
        
        // Verify correct error code
        if ($result['code'] !== 'REASON_TOO_SHORT') {
            return [
                'success' => false,
                'message' => "Expected error code 'REASON_TOO_SHORT', got '{$result['code']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Rejection with whitespace-only reason fails
     * For any rejection with a whitespace-only reason, the rejection should fail
     */
    private function testRejectionWithWhitespaceOnlyReasonFails(): array {
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
        
        // Generate whitespace-only reason
        $whitespaceLength = rand(1, 20);
        $whitespaceReason = str_repeat(' ', $whitespaceLength);
        
        // Attempt to reject with whitespace-only reason
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $whitespaceReason,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        // Should fail
        if ($result['success']) {
            return [
                'success' => false,
                'message' => 'Rejection with whitespace-only reason should fail'
            ];
        }
        
        // Verify correct error code
        if ($result['code'] !== 'REASON_TOO_SHORT') {
            return [
                'success' => false,
                'message' => "Expected error code 'REASON_TOO_SHORT', got '{$result['code']}'"
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
    $test = new SectionRejectionValidationPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

<?php
/**
 * Property Test: Editable Sections Restriction
 * 
 * **Feature: installation-module, Property 27: Editable sections restriction**
 * **Validates: Requirements 16.3**
 * 
 * Property: For any rejected installation being edited, only the rejected sections
 * should be editable; non-rejected sections should be read-only.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationReviewService.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../repositories/InstallationCheckpointRepository.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/../models/InstallationCheckpoint.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class EditableSectionsRestrictionPropertyTest {
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
        echo "\n=== Editable Sections Restriction Property Tests ===\n";
        echo "**Feature: installation-module, Property 27: Editable sections restriction**\n";
        echo "**Validates: Requirements 16.3**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Only rejected sections are editable',
            [$this, 'testOnlyRejectedSectionsEditable']
        );
        
        $this->runPropertyTest(
            'Non-rejected sections are not in editable list',
            [$this, 'testNonRejectedSectionsNotEditable']
        );
        
        $this->runPropertyTest(
            'Approved sections are not editable',
            [$this, 'testApprovedSectionsNotEditable']
        );
        
        $this->runPropertyTest(
            'Resubmit section fails for non-rejected sections',
            [$this, 'testResubmitFailsForNonRejectedSections']
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
     * Property Test: Only rejected sections are editable
     * For any rejected installation, only the rejected sections should be in the editable list
     * Requirements: 16.3
     */
    private function testOnlyRejectedSectionsEditable(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
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
        
        // Get editable sections
        $editableSections = $this->reviewService->getEditableSections($installationId);
        
        // Verify the rejected section is in the editable list
        if (!in_array($rejectedSection, $editableSections)) {
            return [
                'success' => false,
                'message' => "Rejected section '$rejectedSection' should be in editable list"
            ];
        }
        
        // Verify only the rejected section is editable
        if (count($editableSections) !== 1) {
            return [
                'success' => false,
                'message' => "Expected 1 editable section, got " . count($editableSections)
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Non-rejected sections are not in editable list
     * For any rejected installation, non-rejected sections should not be editable
     * Requirements: 16.3
     */
    private function testNonRejectedSectionsNotEditable(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Pick a random section to reject
        $sections = InstallationSections::getAll();
        shuffle($sections);
        $rejectedSection = $sections[0];
        $nonRejectedSections = array_slice($sections, 1);
        
        // Reject one section
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
        
        // Get editable sections
        $editableSections = $this->reviewService->getEditableSections($installationId);
        
        // Verify non-rejected sections are not in the editable list
        foreach ($nonRejectedSections as $section) {
            if (in_array($section, $editableSections)) {
                return [
                    'success' => false,
                    'message' => "Non-rejected section '$section' should not be in editable list"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Approved sections are not editable
     * For any installation with approved sections, those sections should not be editable
     * Requirements: 16.3
     */
    private function testApprovedSectionsNotEditable(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Pick two random sections - one to approve, one to reject
        $sections = InstallationSections::getAll();
        shuffle($sections);
        $approvedSection = $sections[0];
        $rejectedSection = $sections[1];
        
        // Approve one section first
        $result = $this->reviewService->approveSection(
            $installationId,
            $approvedSection,
            $reviewerId,
            'Approved',
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to approve section: ' . $result['message']
            ];
        }
        
        // Reject another section
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
        
        // Get editable sections
        $editableSections = $this->reviewService->getEditableSections($installationId);
        
        // Verify approved section is not in the editable list
        if (in_array($approvedSection, $editableSections)) {
            return [
                'success' => false,
                'message' => "Approved section '$approvedSection' should not be in editable list"
            ];
        }
        
        // Verify rejected section is in the editable list
        if (!in_array($rejectedSection, $editableSections)) {
            return [
                'success' => false,
                'message' => "Rejected section '$rejectedSection' should be in editable list"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Resubmit section fails for non-rejected sections
     * For any non-rejected section, attempting to resubmit should fail
     * Requirements: 16.3
     */
    private function testResubmitFailsForNonRejectedSections(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        $engineerId = 1;
        
        // Pick two random sections - one to reject, one to leave pending
        $sections = InstallationSections::getAll();
        shuffle($sections);
        $rejectedSection = $sections[0];
        $pendingSection = $sections[1];
        
        // Reject one section
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
        
        // Try to resubmit the pending (non-rejected) section - should fail
        $result = $this->reviewService->resubmitSection(
            $installationId,
            $pendingSection,
            ['test_field' => 'test_value'],
            $engineerId
        );
        
        if ($result['success']) {
            return [
                'success' => false,
                'message' => "Resubmit should fail for non-rejected section '$pendingSection'"
            ];
        }
        
        // Verify the error code indicates section is not editable
        if ($result['code'] !== 'SECTION_NOT_EDITABLE') {
            return [
                'success' => false,
                'message' => "Expected error code 'SECTION_NOT_EDITABLE', got '{$result['code']}'"
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
    $test = new EditableSectionsRestrictionPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

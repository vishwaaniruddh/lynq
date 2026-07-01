<?php
/**
 * Property Test: ADV-Approved Immutability
 * 
 * **Feature: installation-module, Property 25: ADV-approved immutability**
 * **Validates: Requirements 15.6**
 * 
 * Property: For any ADV-approved installation, modification attempts should be rejected.
 * This includes section approvals, rejections, resubmissions, and data updates.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationReviewService.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../repositories/InstallationCheckpointRepository.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/../models/InstallationCheckpoint.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class AdvApprovedImmutabilityPropertyTest {
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
        echo "\n=== ADV-Approved Immutability Property Tests ===\n";
        echo "**Feature: installation-module, Property 25: ADV-approved immutability**\n";
        echo "**Validates: Requirements 15.6**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Cannot approve section on ADV-approved installation',
            [$this, 'testCannotApproveSectionOnAdvApproved']
        );
        
        $this->runPropertyTest(
            'Cannot reject section on ADV-approved installation',
            [$this, 'testCannotRejectSectionOnAdvApproved']
        );
        
        $this->runPropertyTest(
            'Cannot resubmit section on ADV-approved installation',
            [$this, 'testCannotResubmitSectionOnAdvApproved']
        );
        
        $this->runPropertyTest(
            'Cannot resubmit ADV-approved installation',
            [$this, 'testCannotResubmitAdvApprovedInstallation']
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
     * Property Test: Cannot approve section on ADV-approved installation
     */
    private function testCannotApproveSectionOnAdvApproved(): array {
        // Create test installation in adv_approved status
        $testData = $this->createTestInstallationInAdvApprovedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Pick a random section
        $sections = InstallationSections::getAll();
        $randomSection = $sections[array_rand($sections)];
        
        // Try to approve section at contractor level
        $result = $this->reviewService->approveSection(
            $installationId,
            $randomSection,
            $reviewerId,
            'Test approval',
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        // Should fail with INSTALLATION_LOCKED error
        if ($result['success']) {
            return [
                'success' => false,
                'message' => 'Approving section on ADV-approved installation should have failed'
            ];
        }
        
        if ($result['code'] !== 'INSTALLATION_LOCKED') {
            return [
                'success' => false,
                'message' => "Expected error code 'INSTALLATION_LOCKED', got '{$result['code']}'"
            ];
        }
        
        // Also try at ADV level
        $result = $this->reviewService->approveSection(
            $installationId,
            $randomSection,
            $reviewerId,
            'Test approval',
            InstallationCheckpoint::LEVEL_ADV
        );
        
        if ($result['success']) {
            return [
                'success' => false,
                'message' => 'Approving section at ADV level on ADV-approved installation should have failed'
            ];
        }
        
        if ($result['code'] !== 'INSTALLATION_LOCKED') {
            return [
                'success' => false,
                'message' => "Expected error code 'INSTALLATION_LOCKED' for ADV level, got '{$result['code']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Cannot reject section on ADV-approved installation
     */
    private function testCannotRejectSectionOnAdvApproved(): array {
        // Create test installation in adv_approved status
        $testData = $this->createTestInstallationInAdvApprovedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Pick a random section
        $sections = InstallationSections::getAll();
        $randomSection = $sections[array_rand($sections)];
        
        // Generate a valid rejection reason
        $validReason = 'Rejection reason: ' . $this->generateRandomString(20);
        
        // Try to reject section at contractor level
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $validReason,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        // Should fail with INSTALLATION_LOCKED error
        if ($result['success']) {
            return [
                'success' => false,
                'message' => 'Rejecting section on ADV-approved installation should have failed'
            ];
        }
        
        if ($result['code'] !== 'INSTALLATION_LOCKED') {
            return [
                'success' => false,
                'message' => "Expected error code 'INSTALLATION_LOCKED', got '{$result['code']}'"
            ];
        }
        
        // Also try at ADV level
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $validReason,
            InstallationCheckpoint::LEVEL_ADV
        );
        
        if ($result['success']) {
            return [
                'success' => false,
                'message' => 'Rejecting section at ADV level on ADV-approved installation should have failed'
            ];
        }
        
        if ($result['code'] !== 'INSTALLATION_LOCKED') {
            return [
                'success' => false,
                'message' => "Expected error code 'INSTALLATION_LOCKED' for ADV level, got '{$result['code']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Cannot resubmit section on ADV-approved installation
     */
    private function testCannotResubmitSectionOnAdvApproved(): array {
        // Create test installation in adv_approved status
        $testData = $this->createTestInstallationInAdvApprovedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = 1;
        
        // Pick a random section
        $sections = InstallationSections::getAll();
        $randomSection = $sections[array_rand($sections)];
        
        // Try to resubmit section
        $result = $this->reviewService->resubmitSection(
            $installationId,
            $randomSection,
            [],
            $engineerId
        );
        
        // Should fail with INSTALLATION_LOCKED error
        if ($result['success']) {
            return [
                'success' => false,
                'message' => 'Resubmitting section on ADV-approved installation should have failed'
            ];
        }
        
        if ($result['code'] !== 'INSTALLATION_LOCKED') {
            return [
                'success' => false,
                'message' => "Expected error code 'INSTALLATION_LOCKED', got '{$result['code']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Cannot resubmit ADV-approved installation
     */
    private function testCannotResubmitAdvApprovedInstallation(): array {
        // Create test installation in adv_approved status
        $testData = $this->createTestInstallationInAdvApprovedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = 1;
        
        // Try to resubmit installation
        $result = $this->reviewService->resubmitInstallation($installationId, $engineerId);
        
        // Should fail with INSTALLATION_LOCKED error
        if ($result['success']) {
            return [
                'success' => false,
                'message' => 'Resubmitting ADV-approved installation should have failed'
            ];
        }
        
        if ($result['code'] !== 'INSTALLATION_LOCKED') {
            return [
                'success' => false,
                'message' => "Expected error code 'INSTALLATION_LOCKED', got '{$result['code']}'"
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
     * Create test installation in adv_approved status
     */
    private function createTestInstallationInAdvApprovedStatus(): array {
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
            
            // Create installation in adv_approved status
            $sql = "INSERT INTO installations (site_id, feasibility_id, initiated_by, created_by, atm_id, status) 
                    VALUES (?, ?, 1, 1, ?, 'adv_approved')";
            $stmt = $this->db->executeQuery($sql, [$siteId, $feasibilityId, $siteName], 'iis');
            $installationId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdInstallationIds[] = $installationId;
            
            // Initialize checkpoints with all approved status
            $this->initializeAllApprovedCheckpoints($installationId);
            
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
     * Initialize checkpoints with all approved status (both contractor and ADV)
     */
    private function initializeAllApprovedCheckpoints(int $installationId): void {
        $sections = InstallationSections::getAll();
        
        foreach ($sections as $section) {
            $sql = "INSERT INTO installation_checkpoints 
                    (installation_id, section, contractor_status, contractor_reviewer_id, contractor_reviewed_at, adv_status, adv_reviewer_id, adv_reviewed_at) 
                    VALUES (?, ?, 'approved', 1, NOW(), 'approved', 1, NOW())";
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
    $test = new AdvApprovedImmutabilityPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

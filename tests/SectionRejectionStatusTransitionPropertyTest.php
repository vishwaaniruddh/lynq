<?php
/**
 * Property Test: Section Rejection Status Transition
 * 
 * **Feature: installation-module, Property 20: Section rejection status transition**
 * **Validates: Requirements 14.4, 14.6**
 * 
 * Property: For any section rejection by a contractor reviewer, the system should
 * update that section's contractor_status to 'rejected' and the overall installation
 * status to 'contractor_rejected'.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationReviewService.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/../models/InstallationCheckpoint.php';
require_once __DIR__ . '/../models/InstallationSectionRemark.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class SectionRejectionStatusTransitionPropertyTest {
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
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->reviewService = new InstallationReviewService();
        $this->installationService = new InstallationService();
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Section Rejection Status Transition Property Tests ===\n";
        echo "**Feature: installation-module, Property 20: Section rejection status transition**\n";
        echo "**Validates: Requirements 14.4, 14.6**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Contractor rejection updates section contractor_status to rejected',
            [$this, 'testContractorRejectionUpdatesSectionStatus']
        );
        
        $this->runPropertyTest(
            'Contractor rejection updates overall installation status to contractor_rejected',
            [$this, 'testContractorRejectionUpdatesInstallationStatus']
        );
        
        $this->runPropertyTest(
            'ADV rejection updates section adv_status to rejected',
            [$this, 'testAdvRejectionUpdatesSectionStatus']
        );
        
        $this->runPropertyTest(
            'ADV rejection updates overall installation status to adv_rejected',
            [$this, 'testAdvRejectionUpdatesInstallationStatus']
        );
        
        $this->runPropertyTest(
            'Rejection creates remark record with rejection type',
            [$this, 'testRejectionCreatesRemarkRecord']
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
     * Property Test: Contractor rejection updates section contractor_status to rejected
     * For any contractor rejection, the section's contractor_status should be 'rejected'
     */
    private function testContractorRejectionUpdatesSectionStatus(): array {
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
        
        // Generate valid rejection reason (>= 10 characters)
        $rejectionReason = 'Rejection reason: ' . $this->generateRandomString(20);
        
        // Reject the section at contractor level
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $rejectionReason,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to reject section: ' . $result['message']
            ];
        }
        
        // Verify checkpoint contractor_status is 'rejected'
        $checkpoint = $result['data']['checkpoint'];
        if ($checkpoint['contractor_status'] !== InstallationCheckpoint::STATUS_REJECTED) {
            return [
                'success' => false,
                'message' => "Expected contractor_status 'rejected', got '{$checkpoint['contractor_status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Contractor rejection updates overall installation status to contractor_rejected
     * For any contractor rejection, the installation status should be 'contractor_rejected'
     */
    private function testContractorRejectionUpdatesInstallationStatus(): array {
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
        
        // Generate valid rejection reason
        $rejectionReason = 'Rejection reason: ' . $this->generateRandomString(20);
        
        // Reject the section at contractor level
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $rejectionReason,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to reject section: ' . $result['message']
            ];
        }
        
        // Verify installation status is 'contractor_rejected'
        $installationStatus = $result['data']['installation_status'];
        if ($installationStatus !== Installation::STATUS_CONTRACTOR_REJECTED) {
            return [
                'success' => false,
                'message' => "Expected installation status 'contractor_rejected', got '$installationStatus'"
            ];
        }
        
        // Double-check by fetching from database
        $installation = $this->getInstallation($installationId);
        if ($installation['status'] !== Installation::STATUS_CONTRACTOR_REJECTED) {
            return [
                'success' => false,
                'message' => "Database status mismatch: expected 'contractor_rejected', got '{$installation['status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: ADV rejection updates section adv_status to rejected
     * For any ADV rejection, the section's adv_status should be 'rejected'
     */
    private function testAdvRejectionUpdatesSectionStatus(): array {
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
        
        // Generate valid rejection reason
        $rejectionReason = 'ADV rejection reason: ' . $this->generateRandomString(20);
        
        // Reject the section at ADV level
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $rejectionReason,
            InstallationCheckpoint::LEVEL_ADV
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to reject section at ADV level: ' . $result['message']
            ];
        }
        
        // Verify checkpoint adv_status is 'rejected'
        $checkpoint = $result['data']['checkpoint'];
        if ($checkpoint['adv_status'] !== InstallationCheckpoint::STATUS_REJECTED) {
            return [
                'success' => false,
                'message' => "Expected adv_status 'rejected', got '{$checkpoint['adv_status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: ADV rejection updates overall installation status to adv_rejected
     * For any ADV rejection, the installation status should be 'adv_rejected'
     */
    private function testAdvRejectionUpdatesInstallationStatus(): array {
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
        
        // Generate valid rejection reason
        $rejectionReason = 'ADV rejection reason: ' . $this->generateRandomString(20);
        
        // Reject the section at ADV level
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $rejectionReason,
            InstallationCheckpoint::LEVEL_ADV
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to reject section at ADV level: ' . $result['message']
            ];
        }
        
        // Verify installation status is 'adv_rejected'
        $installationStatus = $result['data']['installation_status'];
        if ($installationStatus !== Installation::STATUS_ADV_REJECTED) {
            return [
                'success' => false,
                'message' => "Expected installation status 'adv_rejected', got '$installationStatus'"
            ];
        }
        
        // Double-check by fetching from database
        $installation = $this->getInstallation($installationId);
        if ($installation['status'] !== Installation::STATUS_ADV_REJECTED) {
            return [
                'success' => false,
                'message' => "Database status mismatch: expected 'adv_rejected', got '{$installation['status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Rejection creates remark record with rejection type
     * For any rejection, a remark record should be created with review_type='rejection'
     */
    private function testRejectionCreatesRemarkRecord(): array {
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
        
        // Generate valid rejection reason
        $rejectionReason = 'Rejection reason: ' . $this->generateRandomString(20);
        
        // Reject the section
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
            $reviewerId,
            $rejectionReason,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to reject section: ' . $result['message']
            ];
        }
        
        // Verify remark record was created with rejection type
        $remark = $result['data']['remark'];
        if (!$remark) {
            return [
                'success' => false,
                'message' => 'Remark record was not created'
            ];
        }
        
        if ($remark['review_type'] !== InstallationSectionRemark::TYPE_REJECTION) {
            return [
                'success' => false,
                'message' => "Expected review_type 'rejection', got '{$remark['review_type']}'"
            ];
        }
        
        // Verify rejection reason was stored
        if ($remark['remark'] !== $rejectionReason) {
            return [
                'success' => false,
                'message' => "Expected remark '$rejectionReason', got '{$remark['remark']}'"
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
     * Get installation by ID
     */
    private function getInstallation(int $installationId): ?array {
        $sql = "SELECT * FROM installations WHERE id = ?";
        $result = $this->db->getResults($sql, [$installationId], 'i');
        return $result[0] ?? null;
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
     * Create test installation in contractor_approved status
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
    $test = new SectionRejectionStatusTransitionPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

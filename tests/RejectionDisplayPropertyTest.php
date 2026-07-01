<?php
/**
 * Property Test: Rejection Display with Highlighted Sections
 * 
 * **Feature: installation-module, Property 26: Rejection display with highlighted sections**
 * **Validates: Requirements 16.1, 16.2**
 * 
 * Property: For any rejected installation, the view should include rejection reasons,
 * and rejected sections should have visual indicators (CSS classes for highlighting).
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationReviewService.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../repositories/InstallationCheckpointRepository.php';
require_once __DIR__ . '/../repositories/InstallationSectionRemarkRepository.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/../models/InstallationCheckpoint.php';
require_once __DIR__ . '/../models/InstallationSectionRemark.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class RejectionDisplayPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    private $reviewService;
    private $installationService;
    private $installationRepository;
    private $checkpointRepository;
    private $remarkRepository;
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
        $this->remarkRepository = new InstallationSectionRemarkRepository();
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Rejection Display Property Tests ===\n";
        echo "**Feature: installation-module, Property 26: Rejection display with highlighted sections**\n";
        echo "**Validates: Requirements 16.1, 16.2**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Rejected installation includes rejection reasons',
            [$this, 'testRejectedInstallationIncludesReasons']
        );
        
        $this->runPropertyTest(
            'Rejected sections are identifiable for highlighting',
            [$this, 'testRejectedSectionsIdentifiable']
        );
        
        $this->runPropertyTest(
            'Multiple rejected sections all have reasons',
            [$this, 'testMultipleRejectedSectionsHaveReasons']
        );
        
        $this->runPropertyTest(
            'Rejection reasons are retrievable by section',
            [$this, 'testRejectionReasonsRetrievableBySection']
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
     * Property Test: Rejected installation includes rejection reasons
     * For any rejected installation, the rejection remarks should be retrievable
     * Requirements: 16.1
     */
    private function testRejectedInstallationIncludesReasons(): array {
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
        $reason = 'Rejection reason: ' . $this->generateRandomString(20);
        
        // Reject the section
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
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
        
        // Verify rejection remarks are retrievable
        $rejectionRemarks = $this->reviewService->getRejectionRemarks($installationId);
        
        if (empty($rejectionRemarks)) {
            return [
                'success' => false,
                'message' => 'No rejection remarks found for rejected installation'
            ];
        }
        
        // Verify the rejection reason is present
        $foundReason = false;
        foreach ($rejectionRemarks as $remark) {
            if ($remark['remark'] === $reason && $remark['section'] === $randomSection) {
                $foundReason = true;
                break;
            }
        }
        
        if (!$foundReason) {
            return [
                'success' => false,
                'message' => "Expected rejection reason '$reason' not found in remarks"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Rejected sections are identifiable for highlighting
     * For any rejected installation, the rejected sections should be identifiable
     * Requirements: 16.2
     */
    private function testRejectedSectionsIdentifiable(): array {
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
        $reason = 'Rejection reason: ' . $this->generateRandomString(20);
        
        // Reject the section
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
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
        
        // Verify rejected sections are identifiable
        $rejectedSections = $this->reviewService->getRejectedSections($installationId);
        
        if (empty($rejectedSections)) {
            return [
                'success' => false,
                'message' => 'No rejected sections found for rejected installation'
            ];
        }
        
        // Verify the rejected section is in the list
        $foundSection = false;
        foreach ($rejectedSections as $rejectedSection) {
            if ($rejectedSection['section'] === $randomSection) {
                $foundSection = true;
                // Verify it has the level information for proper highlighting
                if (!isset($rejectedSection['level'])) {
                    return [
                        'success' => false,
                        'message' => 'Rejected section missing level information for highlighting'
                    ];
                }
                break;
            }
        }
        
        if (!$foundSection) {
            return [
                'success' => false,
                'message' => "Expected rejected section '$randomSection' not found in rejected sections list"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Multiple rejected sections all have reasons
     * For any installation with multiple rejected sections, all should have reasons
     * Requirements: 16.1, 16.2
     * 
     * Note: After the first rejection, the installation status changes to contractor_rejected.
     * To test multiple rejections, we need to create separate installations for each rejection
     * and verify that each rejected installation has its rejection reason retrievable.
     */
    private function testMultipleRejectedSectionsHaveReasons(): array {
        // Pick 2-3 random sections to test
        $sections = InstallationSections::getAll();
        shuffle($sections);
        $numSections = rand(2, min(3, count($sections)));
        $selectedSections = array_slice($sections, 0, $numSections);
        
        // For each section, create a fresh installation and reject it
        // Then verify the rejection reason is retrievable
        foreach ($selectedSections as $section) {
            // Create test installation in submitted status
            $testData = $this->createTestInstallationInSubmittedStatus();
            if (!$testData['success']) {
                return $testData;
            }
            
            $installationId = $testData['installation_id'];
            $reviewerId = 1;
            
            $reason = 'Rejection for ' . $section . ': ' . $this->generateRandomString(15);
            
            $result = $this->reviewService->rejectSection(
                $installationId,
                $section,
                $reviewerId,
                $reason,
                InstallationCheckpoint::LEVEL_CONTRACTOR
            );
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'message' => "Failed to reject section $section: " . $result['message']
                ];
            }
            
            // Verify rejection remark is retrievable for this installation
            $rejectionRemarks = $this->reviewService->getRejectionRemarks($installationId);
            
            if (empty($rejectionRemarks)) {
                return [
                    'success' => false,
                    'message' => "No rejection remarks found for installation with rejected section $section"
                ];
            }
            
            // Verify the specific rejection reason is present
            $found = false;
            foreach ($rejectionRemarks as $remark) {
                if ($remark['section'] === $section && $remark['remark'] === $reason) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                return [
                    'success' => false,
                    'message' => "Rejection reason for section '$section' not found"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Rejection reasons are retrievable by section
     * For any rejected section, the rejection reason should be retrievable by section
     * Requirements: 16.1
     */
    private function testRejectionReasonsRetrievableBySection(): array {
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
        $reason = 'Section-specific rejection: ' . $this->generateRandomString(20);
        
        // Reject the section
        $result = $this->reviewService->rejectSection(
            $installationId,
            $randomSection,
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
        
        // Verify rejection reason is retrievable by section
        $sectionHistory = $this->reviewService->getSectionReviewHistory($installationId, $randomSection);
        
        if (empty($sectionHistory)) {
            return [
                'success' => false,
                'message' => 'No review history found for rejected section'
            ];
        }
        
        // Find the rejection remark
        $foundRejection = false;
        foreach ($sectionHistory as $remark) {
            if ($remark['review_type'] === InstallationSectionRemark::TYPE_REJECTION && 
                $remark['remark'] === $reason) {
                $foundRejection = true;
                break;
            }
        }
        
        if (!$foundRejection) {
            return [
                'success' => false,
                'message' => "Rejection reason not found in section review history"
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
    $test = new RejectionDisplayPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

<?php
/**
 * Property Test: All Sections Approved Status
 * 
 * **Feature: installation-module, Property 21: All sections approved triggers contractor_approved status**
 * **Validates: Requirements 14.5, 14.7**
 * 
 * Property: For any installation where all sections have contractor_status='approved',
 * the overall installation status should be 'contractor_approved'.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationReviewService.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/../models/InstallationCheckpoint.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class AllSectionsApprovedStatusPropertyTest {
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
        echo "\n=== All Sections Approved Status Property Tests ===\n";
        echo "**Feature: installation-module, Property 21: All sections approved triggers contractor_approved status**\n";
        echo "**Validates: Requirements 14.5, 14.7**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Approving all sections individually triggers contractor_approved status',
            [$this, 'testApprovingAllSectionsIndividuallyTriggersContractorApproved']
        );
        
        $this->runPropertyTest(
            'Using approveAllSections triggers contractor_approved status',
            [$this, 'testApproveAllSectionsTriggersContractorApproved']
        );
        
        $this->runPropertyTest(
            'Partial approval does not trigger contractor_approved status',
            [$this, 'testPartialApprovalDoesNotTriggerContractorApproved']
        );
        
        $this->runPropertyTest(
            'areAllSectionsApproved returns true when all sections approved',
            [$this, 'testAreAllSectionsApprovedReturnsTrue']
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
     * Property Test: Approving all sections individually triggers contractor_approved status
     * For any installation, when all sections are approved one by one, status becomes contractor_approved
     */
    private function testApprovingAllSectionsIndividuallyTriggersContractorApproved(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Get all sections
        $sections = InstallationSections::getAll();
        
        // Approve all sections one by one
        foreach ($sections as $section) {
            $result = $this->reviewService->approveSection(
                $installationId,
                $section,
                $reviewerId,
                null,
                InstallationCheckpoint::LEVEL_CONTRACTOR
            );
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'message' => "Failed to approve section $section: " . $result['message']
                ];
            }
        }
        
        // Verify installation status is contractor_approved
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found after approvals'
            ];
        }
        
        if ($installation['status'] !== Installation::STATUS_CONTRACTOR_APPROVED) {
            return [
                'success' => false,
                'message' => "Expected status 'contractor_approved', got '{$installation['status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Using approveAllSections triggers contractor_approved status
     * For any installation, using bulk approve sets status to contractor_approved
     */
    private function testApproveAllSectionsTriggersContractorApproved(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Use bulk approve
        $result = $this->reviewService->approveAllSections(
            $installationId,
            $reviewerId,
            'Bulk approval test',
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to approve all sections: ' . $result['message']
            ];
        }
        
        // Verify installation status is contractor_approved
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found after bulk approval'
            ];
        }
        
        if ($installation['status'] !== Installation::STATUS_CONTRACTOR_APPROVED) {
            return [
                'success' => false,
                'message' => "Expected status 'contractor_approved', got '{$installation['status']}'"
            ];
        }
        
        // Verify the result data also indicates contractor_approved
        if ($result['data']['installation_status'] !== Installation::STATUS_CONTRACTOR_APPROVED) {
            return [
                'success' => false,
                'message' => "Result data expected 'contractor_approved', got '{$result['data']['installation_status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Partial approval does not trigger contractor_approved status
     * For any installation with only some sections approved, status should not be contractor_approved
     */
    private function testPartialApprovalDoesNotTriggerContractorApproved(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Get all sections
        $sections = InstallationSections::getAll();
        
        // Approve only half of the sections (at least 1, at most all-1)
        $sectionsToApprove = max(1, min(count($sections) - 1, rand(1, count($sections) - 1)));
        $approvedSections = array_slice($sections, 0, $sectionsToApprove);
        
        foreach ($approvedSections as $section) {
            $result = $this->reviewService->approveSection(
                $installationId,
                $section,
                $reviewerId,
                null,
                InstallationCheckpoint::LEVEL_CONTRACTOR
            );
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'message' => "Failed to approve section $section: " . $result['message']
                ];
            }
        }
        
        // Verify installation status is NOT contractor_approved
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found after partial approvals'
            ];
        }
        
        if ($installation['status'] === Installation::STATUS_CONTRACTOR_APPROVED) {
            return [
                'success' => false,
                'message' => "Status should not be 'contractor_approved' with only $sectionsToApprove of " . count($sections) . " sections approved"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: areAllSectionsApproved returns true when all sections approved
     * For any installation with all sections approved, areAllSectionsApproved should return true
     */
    private function testAreAllSectionsApprovedReturnsTrue(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $reviewerId = 1;
        
        // Initially should be false
        $initialCheck = $this->reviewService->areAllSectionsApproved(
            $installationId, 
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if ($initialCheck === true) {
            return [
                'success' => false,
                'message' => 'areAllSectionsApproved should be false initially'
            ];
        }
        
        // Approve all sections
        $result = $this->reviewService->approveAllSections(
            $installationId,
            $reviewerId,
            null,
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to approve all sections: ' . $result['message']
            ];
        }
        
        // Now should be true
        $finalCheck = $this->reviewService->areAllSectionsApproved(
            $installationId, 
            InstallationCheckpoint::LEVEL_CONTRACTOR
        );
        
        if ($finalCheck !== true) {
            return [
                'success' => false,
                'message' => 'areAllSectionsApproved should be true after approving all sections'
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
    $test = new AllSectionsApprovedStatusPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

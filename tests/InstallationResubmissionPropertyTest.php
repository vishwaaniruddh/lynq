<?php
/**
 * Property Test: Installation Resubmission Status Reset
 * 
 * **Feature: installation-module, Property 29: Installation resubmission status reset**
 * **Validates: Requirements 16.5**
 * 
 * Property: For any resubmission of a rejected installation, the overall status
 * should be reset to 'pending_contractor_review'.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationReviewService.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../repositories/InstallationCheckpointRepository.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/../models/InstallationCheckpoint.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class InstallationResubmissionPropertyTest {
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
        echo "\n=== Installation Resubmission Property Tests ===\n";
        echo "**Feature: installation-module, Property 24: Installation resubmission status reset**\n";
        echo "**Validates: Requirements 14.5**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Resubmitting contractor-rejected installation resets status to pending_contractor_review',
            [$this, 'testResubmitContractorRejectedInstallation']
        );
        
        $this->runPropertyTest(
            'Resubmitting ADV-rejected installation resets status to pending_contractor_review',
            [$this, 'testResubmitAdvRejectedInstallation']
        );
        
        $this->runPropertyTest(
            'Resubmitting installation resets all section statuses to pending',
            [$this, 'testResubmitResetsAllSectionStatuses']
        );
        
        $this->runPropertyTest(
            'Cannot resubmit non-rejected installation',
            [$this, 'testCannotResubmitNonRejectedInstallation']
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
     * Property Test: Resubmitting contractor-rejected installation resets status
     */
    private function testResubmitContractorRejectedInstallation(): array {
        // Create test installation in contractor_rejected status
        $testData = $this->createTestInstallationInRejectedStatus('contractor');
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = 1;
        
        // Verify installation is contractor_rejected before resubmission
        $installationBefore = $this->installationRepository->findById($installationId);
        if ($installationBefore['status'] !== Installation::STATUS_CONTRACTOR_REJECTED) {
            return [
                'success' => false,
                'message' => 'Installation should be contractor_rejected before resubmission'
            ];
        }
        
        // Resubmit the installation
        $result = $this->reviewService->resubmitInstallation($installationId, $engineerId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to resubmit installation: ' . $result['message']
            ];
        }
        
        // Verify installation status is pending_contractor_review
        $installationAfter = $this->installationRepository->findById($installationId);
        if ($installationAfter['status'] !== Installation::STATUS_PENDING_CONTRACTOR_REVIEW) {
            return [
                'success' => false,
                'message' => "Expected status 'pending_contractor_review', got '{$installationAfter['status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Resubmitting ADV-rejected installation resets status
     */
    private function testResubmitAdvRejectedInstallation(): array {
        // Create test installation in adv_rejected status
        $testData = $this->createTestInstallationInRejectedStatus('adv');
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = 1;
        
        // Verify installation is adv_rejected before resubmission
        $installationBefore = $this->installationRepository->findById($installationId);
        if ($installationBefore['status'] !== Installation::STATUS_ADV_REJECTED) {
            return [
                'success' => false,
                'message' => 'Installation should be adv_rejected before resubmission'
            ];
        }
        
        // Resubmit the installation
        $result = $this->reviewService->resubmitInstallation($installationId, $engineerId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to resubmit installation: ' . $result['message']
            ];
        }
        
        // Verify installation status is pending_contractor_review
        $installationAfter = $this->installationRepository->findById($installationId);
        if ($installationAfter['status'] !== Installation::STATUS_PENDING_CONTRACTOR_REVIEW) {
            return [
                'success' => false,
                'message' => "Expected status 'pending_contractor_review', got '{$installationAfter['status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Resubmitting installation resets all section statuses to pending
     */
    private function testResubmitResetsAllSectionStatuses(): array {
        // Create test installation in contractor_rejected status with some rejected sections
        $testData = $this->createTestInstallationInRejectedStatus('contractor');
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = 1;
        
        // Resubmit the installation
        $result = $this->reviewService->resubmitInstallation($installationId, $engineerId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to resubmit installation: ' . $result['message']
            ];
        }
        
        // Verify all section statuses are reset to pending
        $allStatuses = $this->checkpointRepository->getAllSectionStatuses($installationId);
        $sections = InstallationSections::getAll();
        
        foreach ($sections as $section) {
            if (!isset($allStatuses[$section])) {
                return [
                    'success' => false,
                    'message' => "Section '$section' checkpoint not found after resubmission"
                ];
            }
            
            $checkpoint = $allStatuses[$section];
            
            // Check contractor status is pending
            if ($checkpoint['contractor_status'] !== InstallationCheckpoint::STATUS_PENDING) {
                return [
                    'success' => false,
                    'message' => "Section '$section' contractor_status expected 'pending', got '{$checkpoint['contractor_status']}'"
                ];
            }
            
            // Check ADV status is pending
            if ($checkpoint['adv_status'] !== InstallationCheckpoint::STATUS_PENDING) {
                return [
                    'success' => false,
                    'message' => "Section '$section' adv_status expected 'pending', got '{$checkpoint['adv_status']}'"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Cannot resubmit non-rejected installation
     */
    private function testCannotResubmitNonRejectedInstallation(): array {
        // Create test installation in submitted status (not rejected)
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = 1;
        
        // Try to resubmit a non-rejected installation
        $result = $this->reviewService->resubmitInstallation($installationId, $engineerId);
        
        // Should fail with INVALID_STATUS error
        if ($result['success']) {
            return [
                'success' => false,
                'message' => 'Resubmitting non-rejected installation should have failed'
            ];
        }
        
        if ($result['code'] !== 'INVALID_STATUS') {
            return [
                'success' => false,
                'message' => "Expected error code 'INVALID_STATUS', got '{$result['code']}'"
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
     * Create test installation in rejected status
     */
    private function createTestInstallationInRejectedStatus(string $rejectionLevel = 'contractor'): array {
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
            
            // Determine installation status based on rejection level
            $installationStatus = $rejectionLevel === 'contractor' 
                ? Installation::STATUS_CONTRACTOR_REJECTED 
                : Installation::STATUS_ADV_REJECTED;
            
            // Create installation
            $sql = "INSERT INTO installations (site_id, feasibility_id, initiated_by, created_by, atm_id, status) 
                    VALUES (?, ?, 1, 1, ?, ?)";
            $stmt = $this->db->executeQuery($sql, [$siteId, $feasibilityId, $siteName, $installationStatus], 'iiss');
            $installationId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdInstallationIds[] = $installationId;
            
            // Initialize checkpoints with some rejected sections
            $this->initializeCheckpointsWithRejections($installationId, $rejectionLevel);
            
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
            
            // Create installation in submitted status
            $sql = "INSERT INTO installations (site_id, feasibility_id, initiated_by, created_by, atm_id, status) 
                    VALUES (?, ?, 1, 1, ?, 'submitted')";
            $stmt = $this->db->executeQuery($sql, [$siteId, $feasibilityId, $siteName], 'iis');
            $installationId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdInstallationIds[] = $installationId;
            
            // Initialize checkpoints with pending status
            $this->initializePendingCheckpoints($installationId);
            
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
     * Initialize checkpoints with some rejected sections
     */
    private function initializeCheckpointsWithRejections(int $installationId, string $level): void {
        $sections = InstallationSections::getAll();
        
        // Randomly select 1-3 sections to reject
        $numRejected = rand(1, min(3, count($sections)));
        $rejectedSections = array_rand(array_flip($sections), $numRejected);
        if (!is_array($rejectedSections)) {
            $rejectedSections = [$rejectedSections];
        }
        
        foreach ($sections as $section) {
            $isRejected = in_array($section, $rejectedSections);
            
            if ($isRejected) {
                if ($level === 'contractor') {
                    $sql = "INSERT INTO installation_checkpoints 
                            (installation_id, section, contractor_status, contractor_reviewer_id, contractor_reviewed_at, adv_status) 
                            VALUES (?, ?, 'rejected', 1, NOW(), 'pending')";
                } else {
                    // ADV rejection - contractor must have approved first
                    $sql = "INSERT INTO installation_checkpoints 
                            (installation_id, section, contractor_status, contractor_reviewer_id, contractor_reviewed_at, adv_status, adv_reviewer_id, adv_reviewed_at) 
                            VALUES (?, ?, 'approved', 1, NOW(), 'rejected', 1, NOW())";
                }
            } else {
                // Other sections are pending or approved based on level
                if ($level === 'contractor') {
                    $sql = "INSERT INTO installation_checkpoints 
                            (installation_id, section, contractor_status, adv_status) 
                            VALUES (?, ?, 'pending', 'pending')";
                } else {
                    // For ADV rejection, contractor has approved all
                    $sql = "INSERT INTO installation_checkpoints 
                            (installation_id, section, contractor_status, contractor_reviewer_id, contractor_reviewed_at, adv_status) 
                            VALUES (?, ?, 'approved', 1, NOW(), 'pending')";
                }
            }
            $stmt = $this->db->executeQuery($sql, [$installationId, $section], 'is');
            $stmt->close();
        }
    }
    
    /**
     * Initialize checkpoints with pending status
     */
    private function initializePendingCheckpoints(int $installationId): void {
        $sections = InstallationSections::getAll();
        
        foreach ($sections as $section) {
            $sql = "INSERT INTO installation_checkpoints 
                    (installation_id, section, contractor_status, adv_status) 
                    VALUES (?, ?, 'pending', 'pending')";
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
    $test = new InstallationResubmissionPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

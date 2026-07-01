<?php
/**
 * Property Test: Installation Button Visibility After ADA
 * 
 * **Feature: installation-module, Property 7: Installation button visibility after ADA**
 * **Validates: Requirements 3.6**
 * 
 * Property: For any installation with status "pending_materials" or later (after ADA submission),
 * the system should display an "Installation" button/link to access the installation form.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationButtonAfterADAPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    private $installationService;
    private $db;
    private $createdInstallationIds = [];
    private $createdSiteIds = [];
    private $createdFeasibilityIds = [];
    private $createdAssignmentIds = [];
    private $createdDelegationIds = [];
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->installationService = new InstallationService();
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Installation Button After ADA Property Tests ===\n";
        echo "**Feature: installation-module, Property 7: Installation button visibility after ADA**\n";
        echo "**Validates: Requirements 3.6**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Installation button visible after ADA submission (pending_materials or later)',
            [$this, 'testButtonVisibleAfterADA']
        );
        
        $this->runPropertyTest(
            'Installation button hidden before ADA submission',
            [$this, 'testButtonHiddenBeforeADA']
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
     * Property Test: Installation button visible after ADA submission
     * Requirement 3.6: Display "Installation" button/link after ADA submission
     * 
     * ADA submission moves status to pending_materials, so button should be visible
     * for pending_materials and all later statuses.
     */
    private function testButtonVisibleAfterADA(): array {
        // Statuses after ADA submission (pending_materials and later)
        $visibleStatuses = [
            Installation::STATUS_PENDING_MATERIALS,
            Installation::STATUS_MATERIALS_RECEIVED,
            Installation::STATUS_IN_PROGRESS,
            Installation::STATUS_SUBMITTED,
            Installation::STATUS_PENDING_CONTRACTOR_REVIEW,
            Installation::STATUS_CONTRACTOR_APPROVED,
            Installation::STATUS_CONTRACTOR_REJECTED,
            Installation::STATUS_ADV_APPROVED,
            Installation::STATUS_ADV_REJECTED
        ];
        
        // Pick a random status after ADA
        $randomStatus = $visibleStatuses[array_rand($visibleStatuses)];
        
        // Create test installation with the random status
        $testData = $this->createTestInstallation($randomStatus);
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        
        // Check button visibility
        $canShow = $this->installationService->canShowInstallationButton($installationId);
        
        if (!$canShow) {
            return [
                'success' => false,
                'message' => "Installation button should be visible for status '$randomStatus' (after ADA)"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Installation button hidden before ADA submission
     * Requirement 3.6: Button should not be visible before ADA is submitted
     * 
     * Before ADA submission, status is pending_assignment, pending_eta, or pending_ada.
     */
    private function testButtonHiddenBeforeADA(): array {
        // Statuses before ADA submission
        $hiddenStatuses = [
            Installation::STATUS_PENDING_ASSIGNMENT,
            Installation::STATUS_PENDING_ETA,
            Installation::STATUS_PENDING_ADA
        ];
        
        // Pick a random status before ADA
        $randomStatus = $hiddenStatuses[array_rand($hiddenStatuses)];
        
        // Create test installation with the random status
        $testData = $this->createTestInstallation($randomStatus);
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        
        // Check button visibility
        $canShow = $this->installationService->canShowInstallationButton($installationId);
        
        if ($canShow) {
            return [
                'success' => false,
                'message' => "Installation button should be hidden for status '$randomStatus' (before ADA)"
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
     * Create test installation with specified status
     */
    private function createTestInstallation(string $status): array {
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
            
            // Create a feasibility check
            $sql = "INSERT INTO feasibility_checks (assignment_id, site_id, created_by, status, approval_status) 
                    VALUES (?, ?, 1, 'active', 'adv_approved')";
            $stmt = $this->db->executeQuery($sql, [$assignmentId, $siteId], 'ii');
            $feasibilityId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdFeasibilityIds[] = $feasibilityId;
            
            // Create installation with specified status
            $sql = "INSERT INTO installations (site_id, feasibility_id, initiated_by, created_by, atm_id, status) 
                    VALUES (?, ?, 1, 1, ?, ?)";
            $stmt = $this->db->executeQuery($sql, [
                $siteId,
                $feasibilityId,
                'ATM-' . $this->generateRandomString(8),
                $status
            ], 'iiss');
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
    $test = new InstallationButtonAfterADAPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

<?php
/**
 * Property Test: Initiate Installation Button Visibility
 * 
 * **Feature: installation-module, Property 1: Initiate Installation button visibility based on feasibility status**
 * **Validates: Requirements 1.1, 1.6, 1.7**
 * 
 * Property: For any site with a given feasibility approval status, the "Initiate Installation" 
 * button should be visible only when the feasibility status is "adv_approved" and no active 
 * installation exists, and hidden for all other cases.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationButtonVisibilityPropertyTest {
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
        echo "\n=== Initiate Installation Button Visibility Property Tests ===\n";
        echo "**Feature: installation-module, Property 1: Initiate Installation button visibility based on feasibility status**\n";
        echo "**Validates: Requirements 1.1, 1.6, 1.7**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Button visible for ADV-approved feasibility with no installation',
            [$this, 'testButtonVisibleForAdvApprovedNoInstallation']
        );
        
        $this->runPropertyTest(
            'Button hidden for non-ADV-approved feasibility',
            [$this, 'testButtonHiddenForNonAdvApproved']
        );
        
        $this->runPropertyTest(
            'Button hidden when installation already exists',
            [$this, 'testButtonHiddenWhenInstallationExists']
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
     * Property Test: Button visible for ADV-approved feasibility with no installation
     * Requirement 1.1: Display button for ADV-approved feasibility
     */
    private function testButtonVisibleForAdvApprovedNoInstallation(): array {
        // Create test data with ADV-approved feasibility
        $testData = $this->createTestSiteAndFeasibility('adv_approved');
        if (!$testData['success']) {
            return $testData;
        }
        
        $siteId = $testData['site_id'];
        $feasibilityId = $testData['feasibility_id'];
        
        // Check button visibility
        $result = $this->installationService->canShowInitiateButton($siteId, $feasibilityId);
        
        if (!$result['visible']) {
            return [
                'success' => false,
                'message' => "Button should be visible for ADV-approved feasibility: {$result['reason']}"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Button hidden for non-ADV-approved feasibility
     * Requirement 1.6: Hide button when feasibility is not ADV-approved
     */
    private function testButtonHiddenForNonAdvApproved(): array {
        // Test with various non-approved statuses
        $nonApprovedStatuses = ['pending_contractor_review', 'contractor_approved', 'contractor_rejected', 'adv_rejected', null];
        $randomStatus = $nonApprovedStatuses[array_rand($nonApprovedStatuses)];
        
        // Create test data with non-approved status
        $testData = $this->createTestSiteAndFeasibility($randomStatus);
        if (!$testData['success']) {
            return $testData;
        }
        
        $siteId = $testData['site_id'];
        $feasibilityId = $testData['feasibility_id'];
        
        // Check button visibility
        $result = $this->installationService->canShowInitiateButton($siteId, $feasibilityId);
        
        if ($result['visible']) {
            return [
                'success' => false,
                'message' => "Button should be hidden for status '$randomStatus'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Button hidden when installation already exists
     * Requirement 1.7: Hide button when installation already exists
     */
    private function testButtonHiddenWhenInstallationExists(): array {
        // Create test data with ADV-approved feasibility
        $testData = $this->createTestSiteAndFeasibility('adv_approved');
        if (!$testData['success']) {
            return $testData;
        }
        
        $siteId = $testData['site_id'];
        $feasibilityId = $testData['feasibility_id'];
        
        // Create an installation for this site
        $installationResult = $this->createTestInstallation($siteId, $feasibilityId);
        if (!$installationResult['success']) {
            return $installationResult;
        }
        
        // Check button visibility - should be hidden now
        $result = $this->installationService->canShowInitiateButton($siteId, $feasibilityId);
        
        if ($result['visible']) {
            return [
                'success' => false,
                'message' => 'Button should be hidden when installation already exists'
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
     * Create test site and feasibility check
     */
    private function createTestSiteAndFeasibility(?string $approvalStatus): array {
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
            
            // Create a feasibility check with the specified approval status
            $sql = "INSERT INTO feasibility_checks (assignment_id, site_id, created_by, status, approval_status) 
                    VALUES (?, ?, 1, 'active', ?)";
            $stmt = $this->db->executeQuery($sql, [$assignmentId, $siteId, $approvalStatus], 'iis');
            $feasibilityId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdFeasibilityIds[] = $feasibilityId;
            
            return [
                'success' => true,
                'site_id' => $siteId,
                'feasibility_id' => $feasibilityId,
                'assignment_id' => $assignmentId
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create test data: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Create test installation
     */
    private function createTestInstallation(int $siteId, int $feasibilityId): array {
        try {
            $sql = "INSERT INTO installations (site_id, feasibility_id, initiated_by, created_by, atm_id, status) 
                    VALUES (?, ?, 1, 1, ?, ?)";
            $stmt = $this->db->executeQuery($sql, [
                $siteId,
                $feasibilityId,
                'ATM-' . $this->generateRandomString(8),
                Installation::STATUS_PENDING_ASSIGNMENT
            ], 'iiss');
            $installationId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdInstallationIds[] = $installationId;
            
            return [
                'success' => true,
                'installation_id' => $installationId
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create test installation: ' . $e->getMessage()
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
    $test = new InstallationButtonVisibilityPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

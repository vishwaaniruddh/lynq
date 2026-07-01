<?php
/**
 * Property Test: Form Access Control
 * 
 * **Feature: installation-module, Property 9: Form access control based on material receipt status**
 * **Validates: Requirements 4.4, 4.5**
 * 
 * Property: For any installation with status "pending_materials" or earlier workflow statuses
 * (pending_assignment, pending_eta, pending_ada), form access should be denied.
 * For any installation with status "materials_received" or later, form access should be enabled.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationFormAccessPropertyTest {
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
        echo "\n=== Installation Form Access Property Tests ===\n";
        echo "**Feature: installation-module, Property 9: Form access control based on material receipt status**\n";
        echo "**Validates: Requirements 4.4, 4.5**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Form access denied for pending_materials status',
            [$this, 'testFormAccessDeniedForPendingMaterials']
        );
        
        $this->runPropertyTest(
            'Form access denied for early workflow statuses (pending_assignment, pending_eta, pending_ada)',
            [$this, 'testFormAccessDeniedForEarlyWorkflowStatuses']
        );
        
        $this->runPropertyTest(
            'Form access enabled for materials_received or later status',
            [$this, 'testFormAccessEnabledForMaterialsReceivedOrLater']
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
     * Property Test: Form access denied for pending_materials status
     * Requirement 4.4
     */
    private function testFormAccessDeniedForPendingMaterials(): array {
        // Create test installation with pending_materials status
        $testData = $this->createTestInstallation(Installation::STATUS_PENDING_MATERIALS);
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        
        // Check form access
        $canAccess = $this->installationService->canAccessForm($installationId);
        
        if ($canAccess) {
            return [
                'success' => false,
                'message' => 'Form access should be denied for pending_materials status'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Form access denied for early workflow statuses
     * Requirement 4.4 - Form access denied for pending_assignment, pending_eta, pending_ada
     */
    private function testFormAccessDeniedForEarlyWorkflowStatuses(): array {
        // Test with early workflow statuses that should deny form access
        $deniedStatuses = [
            Installation::STATUS_PENDING_ASSIGNMENT,
            Installation::STATUS_PENDING_ETA,
            Installation::STATUS_PENDING_ADA
        ];
        
        // Pick a random denied status
        $randomStatus = $deniedStatuses[array_rand($deniedStatuses)];
        
        // Create test installation with the random status
        $testData = $this->createTestInstallation($randomStatus);
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        
        // Check form access
        $canAccess = $this->installationService->canAccessForm($installationId);
        
        if ($canAccess) {
            return [
                'success' => false,
                'message' => "Form access should be denied for status '$randomStatus'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Form access enabled for materials_received or later status
     * Requirement 4.5
     */
    private function testFormAccessEnabledForMaterialsReceivedOrLater(): array {
        // Test with various statuses that should allow form access
        $allowedStatuses = [
            Installation::STATUS_MATERIALS_RECEIVED,
            Installation::STATUS_IN_PROGRESS,
            Installation::STATUS_SUBMITTED,
            Installation::STATUS_PENDING_CONTRACTOR_REVIEW,
            Installation::STATUS_CONTRACTOR_APPROVED,
            Installation::STATUS_CONTRACTOR_REJECTED,
            Installation::STATUS_ADV_APPROVED,
            Installation::STATUS_ADV_REJECTED
        ];
        
        // Pick a random allowed status
        $randomStatus = $allowedStatuses[array_rand($allowedStatuses)];
        
        // Create test installation with the random status
        $testData = $this->createTestInstallation($randomStatus);
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        
        // Check form access
        $canAccess = $this->installationService->canAccessForm($installationId);
        
        if (!$canAccess) {
            return [
                'success' => false,
                'message' => "Form access should be enabled for status '$randomStatus'"
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
    $test = new InstallationFormAccessPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

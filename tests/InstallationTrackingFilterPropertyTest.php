<?php
/**
 * Property Test: Tracking Filter Returns Correct Results
 * 
 * **Feature: installation-module, Property 33: Tracking filter returns correct results**
 * **Validates: Requirements 18.3**
 * 
 * Property: For any filter criteria (status), the filtered tracking results
 * should contain only installations that match the specified status.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationTrackingFilterPropertyTest {
    private $testResults = [];
    private $iterations = 50; // Reduced iterations due to database operations
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
        echo "\n=== Installation Tracking Filter Property Tests ===\n";
        echo "**Feature: installation-module, Property 33: Tracking filter returns correct results**\n";
        echo "**Validates: Requirements 18.3**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Status filter returns only matching installations',
            [$this, 'testStatusFilterReturnsMatchingInstallations']
        );
        
        $this->runPropertyTest(
            'Empty filter returns all installations',
            [$this, 'testEmptyFilterReturnsAll']
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
     * Property Test: Status filter returns only matching installations
     */
    private function testStatusFilterReturnsMatchingInstallations(): array {
        // Create test installations with different statuses
        $statuses = Installation::getStatuses();
        $targetStatus = $statuses[array_rand($statuses)];
        
        // Create an installation with the target status
        $testData = $this->createTestInstallation($targetStatus);
        if (!$testData['success']) {
            return $testData;
        }
        
        // Get filtered results
        $result = $this->installationService->getInstallationTracking([
            'status' => $targetStatus,
            'limit' => 100
        ]);
        
        // Verify all returned installations have the target status
        foreach ($result['data'] as $installation) {
            if ($installation['status'] !== $targetStatus) {
                return [
                    'success' => false,
                    'message' => "Expected status '$targetStatus', got '{$installation['status']}'"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Empty filter returns all installations
     */
    private function testEmptyFilterReturnsAll(): array {
        // Create a test installation
        $statuses = Installation::getStatuses();
        $randomStatus = $statuses[array_rand($statuses)];
        
        $testData = $this->createTestInstallation($randomStatus);
        if (!$testData['success']) {
            return $testData;
        }
        
        // Get all results without filter
        $result = $this->installationService->getInstallationTracking([
            'limit' => 100
        ]);
        
        // Should return results (at least the one we created)
        if ($result['total'] < 1) {
            return [
                'success' => false,
                'message' => 'Expected at least 1 installation in results'
            ];
        }
        
        // Verify our created installation is in the results
        $found = false;
        foreach ($result['data'] as $installation) {
            if ((int)$installation['id'] === $testData['installation_id']) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            return [
                'success' => false,
                'message' => 'Created installation not found in unfiltered results'
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
    $test = new InstallationTrackingFilterPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

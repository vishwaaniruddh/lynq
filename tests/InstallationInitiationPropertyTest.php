<?php
/**
 * Property Test: Installation Initiation
 * 
 * **Feature: installation-module, Property 2: Installation delegation creates record with correct initial status**
 * **Validates: Requirements 1.4**
 * 
 * Property: For any valid installation initiation on a site with ADV-approved feasibility,
 * the system should create an installation record linked to the site and feasibility check,
 * with status set to "pending_assignment" (no contractor assigned yet).
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationInitiationPropertyTest {
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
        echo "\n=== Installation Initiation Property Tests ===\n";
        echo "**Feature: installation-module, Property 2: Installation delegation creates record with correct initial status**\n";
        echo "**Validates: Requirements 1.4**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Installation initiation creates record with pending_assignment status',
            [$this, 'testInitiationCreatesRecordWithCorrectStatus']
        );
        
        $this->runPropertyTest(
            'Installation is linked to site and feasibility',
            [$this, 'testInstallationLinkedToSiteAndFeasibility']
        );
        
        $this->runPropertyTest(
            'Cannot initiate installation for non-ADV-approved feasibility',
            [$this, 'testCannotInitiateForNonApprovedFeasibility']
        );
        
        $this->runPropertyTest(
            'Cannot initiate duplicate installation for same site',
            [$this, 'testCannotInitiateDuplicateInstallation']
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
     * Property Test: Installation initiation creates record with pending_assignment status
     * For any valid initiation, the created installation should have status "pending_assignment"
     * Requirement 1.4: Create installation record with status "pending_assignment"
     */
    private function testInitiationCreatesRecordWithCorrectStatus(): array {
        // Create test data
        $testData = $this->createTestSiteAndFeasibility('adv_approved');
        if (!$testData['success']) {
            return $testData;
        }
        
        $siteId = $testData['site_id'];
        $feasibilityId = $testData['feasibility_id'];
        $userId = 1; // Test user
        
        // Initiate installation
        $result = $this->installationService->initiateInstallation($siteId, $feasibilityId, $userId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to initiate installation: ' . $result['message']
            ];
        }
        
        $installation = $result['data'];
        $this->createdInstallationIds[] = $installation['id'];
        
        // Verify status is pending_assignment (Requirement 1.4)
        if ($installation['status'] !== Installation::STATUS_PENDING_ASSIGNMENT) {
            return [
                'success' => false,
                'message' => "Expected status 'pending_assignment', got '{$installation['status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Installation is linked to site and feasibility
     * For any valid initiation, the installation should be linked to the correct site and feasibility
     */
    private function testInstallationLinkedToSiteAndFeasibility(): array {
        // Create test data
        $testData = $this->createTestSiteAndFeasibility('adv_approved');
        if (!$testData['success']) {
            return $testData;
        }
        
        $siteId = $testData['site_id'];
        $feasibilityId = $testData['feasibility_id'];
        $userId = 1;
        
        // Initiate installation
        $result = $this->installationService->initiateInstallation($siteId, $feasibilityId, $userId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to initiate installation: ' . $result['message']
            ];
        }
        
        $installation = $result['data'];
        $this->createdInstallationIds[] = $installation['id'];
        
        // Verify site_id link (Requirement 1.2)
        if ((int)$installation['site_id'] !== $siteId) {
            return [
                'success' => false,
                'message' => "Expected site_id $siteId, got {$installation['site_id']}"
            ];
        }
        
        // Verify feasibility_id link (Requirement 1.2)
        if ((int)$installation['feasibility_id'] !== $feasibilityId) {
            return [
                'success' => false,
                'message' => "Expected feasibility_id $feasibilityId, got {$installation['feasibility_id']}"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Cannot initiate installation for non-ADV-approved feasibility
     * For any feasibility that is not ADV-approved, initiation should fail
     */
    private function testCannotInitiateForNonApprovedFeasibility(): array {
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
        $userId = 1;
        
        // Attempt to initiate installation
        $result = $this->installationService->initiateInstallation($siteId, $feasibilityId, $userId);
        
        // Should fail
        if ($result['success']) {
            $this->createdInstallationIds[] = $result['data']['id'];
            return [
                'success' => false,
                'message' => "Installation should not be initiated for status '$randomStatus'"
            ];
        }
        
        // Verify correct error code
        if ($result['code'] !== 'FEASIBILITY_NOT_APPROVED') {
            return [
                'success' => false,
                'message' => "Expected error code 'FEASIBILITY_NOT_APPROVED', got '{$result['code']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Cannot initiate duplicate installation for same site
     * For any site that already has an installation, initiation should fail
     */
    private function testCannotInitiateDuplicateInstallation(): array {
        // Create test data
        $testData = $this->createTestSiteAndFeasibility('adv_approved');
        if (!$testData['success']) {
            return $testData;
        }
        
        $siteId = $testData['site_id'];
        $feasibilityId = $testData['feasibility_id'];
        $userId = 1;
        
        // First initiation should succeed
        $result1 = $this->installationService->initiateInstallation($siteId, $feasibilityId, $userId);
        if (!$result1['success']) {
            return [
                'success' => false,
                'message' => 'First initiation failed: ' . $result1['message']
            ];
        }
        $this->createdInstallationIds[] = $result1['data']['id'];
        
        // Second initiation should fail
        $result2 = $this->installationService->initiateInstallation($siteId, $feasibilityId, $userId);
        if ($result2['success']) {
            $this->createdInstallationIds[] = $result2['data']['id'];
            return [
                'success' => false,
                'message' => 'Duplicate installation should not be allowed'
            ];
        }
        
        // Verify correct error code
        if ($result2['code'] !== 'INSTALLATION_EXISTS') {
            return [
                'success' => false,
                'message' => "Expected error code 'INSTALLATION_EXISTS', got '{$result2['code']}'"
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
    $test = new InstallationInitiationPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

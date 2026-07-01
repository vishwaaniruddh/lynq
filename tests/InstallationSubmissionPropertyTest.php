<?php
/**
 * Property Test: Installation Submission Status
 * 
 * **Feature: installation-module, Property 13: Installation submission updates status**
 * **Validates: Requirements 5.5**
 * 
 * Property: For any successful installation form submission, the installation status
 * should be updated to "submitted".
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationSubmissionPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    private $installationService;
    private $db;
    private $createdInstallationIds = [];
    private $createdSiteIds = [];
    private $createdFeasibilityIds = [];
    private $createdAssignmentIds = [];
    private $createdDelegationIds = [];
    private $createdMaterialReceiptIds = [];
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->installationService = new InstallationService();
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Installation Submission Property Tests ===\n";
        echo "**Feature: installation-module, Property 13: Installation submission updates status**\n";
        echo "**Validates: Requirements 5.5**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Successful submission updates status to submitted',
            [$this, 'testSubmissionUpdatesStatus']
        );
        
        $this->runPropertyTest(
            'Submission records submitted_by and submitted_at',
            [$this, 'testSubmissionRecordsAuditFields']
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
     * Property Test: Successful submission updates status to submitted
     * Requirement 5.5
     */
    private function testSubmissionUpdatesStatus(): array {
        // Create test installation with all required data
        $testData = $this->createTestInstallationWithData();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = 1;
        
        // Submit the installation
        $result = $this->installationService->submitInstallation($installationId, $engineerId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Submission failed: ' . $result['message']
            ];
        }
        
        // Verify status is "submitted" (Requirement 5.5)
        $installation = $result['data'];
        if ($installation['status'] !== Installation::STATUS_SUBMITTED) {
            return [
                'success' => false,
                'message' => "Expected status 'submitted', got '{$installation['status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Submission records submitted_by and submitted_at
     */
    private function testSubmissionRecordsAuditFields(): array {
        // Create test installation with all required data
        $testData = $this->createTestInstallationWithData();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = 1;
        $beforeSubmit = date('Y-m-d H:i:s');
        
        // Submit the installation
        $result = $this->installationService->submitInstallation($installationId, $engineerId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Submission failed: ' . $result['message']
            ];
        }
        
        $installation = $result['data'];
        
        // Verify submitted_by is set
        if ((int)$installation['submitted_by'] !== $engineerId) {
            return [
                'success' => false,
                'message' => "Expected submitted_by=$engineerId, got {$installation['submitted_by']}"
            ];
        }
        
        // Verify submitted_at is set and reasonable
        if (empty($installation['submitted_at'])) {
            return [
                'success' => false,
                'message' => 'submitted_at should be set'
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
     * Create test installation with all required data for submission
     */
    private function createTestInstallationWithData(): array {
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
            
            // Create installation with all required fields populated
            $sql = "INSERT INTO installations (
                site_id, feasibility_id, initiated_by, created_by, atm_id, status,
                vendor_name, engineer_name, engineer_number,
                router_serial, router_make, router_model, router_fixed, router_status,
                adaptor_installed, adaptor_status,
                lan_cable_installed, lan_cable_status,
                antenna_installed, antenna_status,
                gps_installed, gps_status,
                wifi_installed, wifi_status,
                airtel_sim_installed, airtel_sim_status,
                vodafone_sim_installed, vodafone_sim_status,
                jio_sim_installed, jio_sim_status,
                signature_image
            ) VALUES (?, ?, 1, 1, ?, 'materials_received',
                ?, ?, ?,
                ?, ?, ?, 'yes', 'working',
                'yes', 'working',
                'yes', 'working',
                'yes', 'working',
                'yes', 'working',
                'yes', 'working',
                'yes', 'working',
                'yes', 'working',
                'yes', 'working',
                ?
            )";
            $stmt = $this->db->executeQuery($sql, [
                $siteId,
                $feasibilityId,
                'ATM-' . $this->generateRandomString(8),
                'Vendor-' . $this->generateRandomString(8),
                'Engineer-' . $this->generateRandomString(8),
                '9' . rand(100000000, 999999999),
                'RSN-' . $this->generateRandomString(12),
                'Make-' . $this->generateRandomString(6),
                'Model-' . $this->generateRandomString(6),
                'uploads/signature_' . $this->generateRandomString(8) . '.png'
            ], 'iissssssss');
            $installationId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdInstallationIds[] = $installationId;
            
            // Create material receipt to enable form access
            $sql = "INSERT INTO installation_material_receipts (installation_id, confirmed_by) VALUES (?, 1)";
            $stmt = $this->db->executeQuery($sql, [$installationId], 'i');
            $receiptId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdMaterialReceiptIds[] = $receiptId;
            
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
            // Delete material receipts
            if (!empty($this->createdMaterialReceiptIds)) {
                $ids = implode(',', array_map('intval', $this->createdMaterialReceiptIds));
                $this->db->executeQuery("DELETE FROM installation_material_receipts WHERE id IN ($ids)", [], '');
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
    $test = new InstallationSubmissionPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

<?php
/**
 * Property Test: Installation Pre-populated Site Information
 * 
 * **Feature: installation-module, Property 10: Installation form displays pre-populated site information**
 * **Validates: Requirements 5.1**
 * 
 * Property: For any installation form, the displayed site information (ATM ID, address, city, 
 * location, LHO, state) should match the linked site data exactly.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationSiteInfoPropertyTest {
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
        echo "\n=== Installation Site Info Property Tests ===\n";
        echo "**Feature: installation-module, Property 10: Installation form displays pre-populated site information**\n";
        echo "**Validates: Requirements 5.1**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Installation contains pre-populated site information',
            [$this, 'testInstallationContainsSiteInfo']
        );
        
        $this->runPropertyTest(
            'Site information matches original site data',
            [$this, 'testSiteInfoMatchesOriginal']
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
     * Property Test: Installation contains pre-populated site information
     * For any installation, the ATM ID, address, city, location, LHO, state should be present
     * 
     * Requirements: 5.1
     */
    private function testInstallationContainsSiteInfo(): array {
        // Create test data with random site info
        $siteInfo = $this->generateRandomSiteInfo();
        $testData = $this->createTestSiteAndFeasibility('adv_approved', $siteInfo);
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
        
        // Verify site information is present
        $requiredFields = ['atm_id', 'city', 'lho', 'state'];
        foreach ($requiredFields as $field) {
            if (!isset($installation[$field]) || $installation[$field] === null) {
                return [
                    'success' => false,
                    'message' => "Missing site information field: $field"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Site information matches original site data
     * For any installation, the site info should exactly match the linked site
     * 
     * Requirements: 5.1
     */
    private function testSiteInfoMatchesOriginal(): array {
        // Create test data with random site info
        $siteInfo = $this->generateRandomSiteInfo();
        $testData = $this->createTestSiteAndFeasibility('adv_approved', $siteInfo);
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
        
        // Verify ATM ID matches site_name (Requirement 5.1)
        if ($installation['atm_id'] !== $siteInfo['site_name']) {
            return [
                'success' => false,
                'message' => "ATM ID mismatch: expected '{$siteInfo['site_name']}', got '{$installation['atm_id']}'"
            ];
        }
        
        // Verify address matches (Requirement 5.1)
        if ($installation['address'] !== $siteInfo['address']) {
            return [
                'success' => false,
                'message' => "Address mismatch: expected '{$siteInfo['address']}', got '{$installation['address']}'"
            ];
        }
        
        // Verify city matches (Requirement 5.1)
        if ($installation['city'] !== $siteInfo['city']) {
            return [
                'success' => false,
                'message' => "City mismatch: expected '{$siteInfo['city']}', got '{$installation['city']}'"
            ];
        }
        
        // Verify LHO matches (Requirement 5.1)
        if ($installation['lho'] !== $siteInfo['lho']) {
            return [
                'success' => false,
                'message' => "LHO mismatch: expected '{$siteInfo['lho']}', got '{$installation['lho']}'"
            ];
        }
        
        // Verify state matches (Requirement 5.1)
        if ($installation['state'] !== $siteInfo['state']) {
            return [
                'success' => false,
                'message' => "State mismatch: expected '{$siteInfo['state']}', got '{$installation['state']}'"
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
     * Generate random site information
     */
    private function generateRandomSiteInfo(): array {
        return [
            'site_name' => 'ATM-' . $this->generateRandomString(8),
            'address' => $this->generateRandomString(20) . ' Street',
            'city' => 'City-' . $this->generateRandomString(6),
            'lho' => 'LHO-' . $this->generateRandomString(4),
            'state' => 'State-' . $this->generateRandomString(6),
            'country' => 'Country'
        ];
    }
    
    /**
     * Create test site and feasibility check
     */
    private function createTestSiteAndFeasibility(?string $approvalStatus, array $siteInfo = []): array {
        try {
            // Use provided site info or generate random
            if (empty($siteInfo)) {
                $siteInfo = $this->generateRandomSiteInfo();
            }
            
            // Create a test site
            $sql = "INSERT INTO sites (site_name, address, lho, city, state, country, company_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
            $stmt = $this->db->executeQuery($sql, [
                $siteInfo['site_name'],
                $siteInfo['address'],
                $siteInfo['lho'],
                $siteInfo['city'],
                $siteInfo['state'],
                $siteInfo['country'],
                1
            ], 'ssssssi');
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
                'assignment_id' => $assignmentId,
                'site_info' => $siteInfo
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
    $test = new InstallationSiteInfoPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

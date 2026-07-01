<?php
/**
 * Property Test: Installation Delegation
 * 
 * **Feature: installation-module, Property 2: Installation delegation creates record with correct initial status**
 * **Validates: Requirements 1.4**
 * 
 * Property: For any valid installation delegation to a contractor, the system should create 
 * an installation record with status set to "pending_assignment" and link it to the selected contractor.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationDelegationService.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationDelegationPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    private $delegationService;
    private $db;
    private $createdInstallationIds = [];
    private $createdSiteIds = [];
    private $createdFeasibilityIds = [];
    private $createdAssignmentIds = [];
    private $createdDelegationIds = [];
    private $createdCompanyIds = [];
    private $testUserId;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->delegationService = new InstallationDelegationService();
        $this->testUserId = $this->getValidUserId();
    }
    
    /**
     * Get a valid user ID for testing
     */
    private function getValidUserId(): int {
        $result = $this->db->getResults('SELECT id FROM users WHERE status = 1 LIMIT 1');
        if (!empty($result)) {
            return (int)$result[0]['id'];
        }
        // Fallback - create a test user if none exists
        return 1;
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Installation Delegation Property Tests ===\n";
        echo "**Feature: installation-module, Property 2: Installation delegation creates record with correct initial status**\n";
        echo "**Validates: Requirements 1.4**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Delegation creates record with pending_assignment status',
            [$this, 'testDelegationCreatesRecordWithCorrectStatus']
        );
        
        $this->runPropertyTest(
            'Delegation links installation to contractor',
            [$this, 'testDelegationLinksToContractor']
        );
        
        $this->runPropertyTest(
            'Delegation records delegated_by and delegated_at',
            [$this, 'testDelegationRecordsDelegationInfo']
        );
        
        $this->runPropertyTest(
            'Cannot delegate to invalid contractor',
            [$this, 'testCannotDelegateToInvalidContractor']
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
     * Property Test: Delegation creates record with pending_assignment status
     * For any valid delegation, the created installation should have status "pending_assignment"
     * 
     * **Feature: installation-module, Property 2: Installation delegation creates record with correct initial status**
     * **Validates: Requirements 1.4**
     */
    private function testDelegationCreatesRecordWithCorrectStatus(): array {
        // Create test data
        $testData = $this->createTestSiteAndFeasibility('adv_approved');
        if (!$testData['success']) {
            return $testData;
        }
        
        $siteId = $testData['site_id'];
        $feasibilityId = $testData['feasibility_id'];
        $contractorId = $testData['contractor_id'];
        $userId = $this->testUserId; // Use valid test user
        
        // Delegate installation
        $result = $this->delegationService->delegateInstallation($siteId, $feasibilityId, $contractorId, $userId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to delegate installation: ' . $result['message']
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
     * Property Test: Delegation links installation to contractor
     * For any valid delegation, the installation should be linked to the correct contractor
     * 
     * **Feature: installation-module, Property 2: Installation delegation creates record with correct initial status**
     * **Validates: Requirements 1.4**
     */
    private function testDelegationLinksToContractor(): array {
        // Create test data
        $testData = $this->createTestSiteAndFeasibility('adv_approved');
        if (!$testData['success']) {
            return $testData;
        }
        
        $siteId = $testData['site_id'];
        $feasibilityId = $testData['feasibility_id'];
        $contractorId = $testData['contractor_id'];
        $userId = $this->testUserId;
        
        // Delegate installation
        $result = $this->delegationService->delegateInstallation($siteId, $feasibilityId, $contractorId, $userId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to delegate installation: ' . $result['message']
            ];
        }
        
        $installation = $result['data'];
        $this->createdInstallationIds[] = $installation['id'];
        
        // Verify contractor_id link (Requirement 1.4)
        if ((int)$installation['contractor_id'] !== $contractorId) {
            return [
                'success' => false,
                'message' => "Expected contractor_id $contractorId, got {$installation['contractor_id']}"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Delegation records delegated_by and delegated_at
     * For any valid delegation, the installation should record who delegated and when
     * 
     * **Feature: installation-module, Property 2: Installation delegation creates record with correct initial status**
     * **Validates: Requirements 1.4**
     */
    private function testDelegationRecordsDelegationInfo(): array {
        // Create test data
        $testData = $this->createTestSiteAndFeasibility('adv_approved');
        if (!$testData['success']) {
            return $testData;
        }
        
        $siteId = $testData['site_id'];
        $feasibilityId = $testData['feasibility_id'];
        $contractorId = $testData['contractor_id'];
        $userId = $this->testUserId;
        
        $beforeDelegation = date('Y-m-d H:i:s');
        
        // Delegate installation
        $result = $this->delegationService->delegateInstallation($siteId, $feasibilityId, $contractorId, $userId);
        
        $afterDelegation = date('Y-m-d H:i:s');
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to delegate installation: ' . $result['message']
            ];
        }
        
        $installation = $result['data'];
        $this->createdInstallationIds[] = $installation['id'];
        
        // Verify delegated_by is set
        if ((int)$installation['delegated_by'] !== $userId) {
            return [
                'success' => false,
                'message' => "Expected delegated_by $userId, got {$installation['delegated_by']}"
            ];
        }
        
        // Verify delegated_at is set and within expected range
        if (empty($installation['delegated_at'])) {
            return [
                'success' => false,
                'message' => 'delegated_at should be set'
            ];
        }
        
        $delegatedAt = $installation['delegated_at'];
        if ($delegatedAt < $beforeDelegation || $delegatedAt > $afterDelegation) {
            return [
                'success' => false,
                'message' => "delegated_at '$delegatedAt' is not within expected range"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Cannot delegate to invalid contractor
     * For any invalid contractor ID, delegation should fail
     */
    private function testCannotDelegateToInvalidContractor(): array {
        // Create test data
        $testData = $this->createTestSiteAndFeasibility('adv_approved');
        if (!$testData['success']) {
            return $testData;
        }
        
        $siteId = $testData['site_id'];
        $feasibilityId = $testData['feasibility_id'];
        $userId = $this->testUserId;
        
        // Use a non-existent contractor ID
        $invalidContractorId = 999999;
        
        // Attempt to delegate installation
        $result = $this->delegationService->delegateInstallation($siteId, $feasibilityId, $invalidContractorId, $userId);
        
        // Should fail
        if ($result['success']) {
            $this->createdInstallationIds[] = $result['data']['id'];
            return [
                'success' => false,
                'message' => 'Delegation should not succeed with invalid contractor ID'
            ];
        }
        
        // Verify correct error code
        if ($result['code'] !== 'INVALID_CONTRACTOR') {
            return [
                'success' => false,
                'message' => "Expected error code 'INVALID_CONTRACTOR', got '{$result['code']}'"
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
     * Create test site and feasibility check with contractor
     */
    private function createTestSiteAndFeasibility(?string $approvalStatus): array {
        try {
            // First, get or create a contractor company
            $contractorId = $this->getOrCreateContractor();
            if (!$contractorId) {
                return [
                    'success' => false,
                    'message' => 'Failed to get or create contractor'
                ];
            }
            
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
                    VALUES (?, ?, ?, 'accepted')";
            $stmt = $this->db->executeQuery($sql, [$siteId, $contractorId, $this->testUserId], 'iii');
            $delegationId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdDelegationIds[] = $delegationId;
            
            // Create an engineer assignment
            $sql = "INSERT INTO engineer_assignments (site_id, delegation_id, engineer_id, assigned_by, status, feasibility_status) 
                    VALUES (?, ?, ?, ?, 'assigned', 'feasibility_completed')";
            $stmt = $this->db->executeQuery($sql, [$siteId, $delegationId, $this->testUserId, $this->testUserId], 'iiii');
            $assignmentId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdAssignmentIds[] = $assignmentId;
            
            // Create a feasibility check with the specified approval status
            $sql = "INSERT INTO feasibility_checks (assignment_id, site_id, created_by, status, approval_status) 
                    VALUES (?, ?, ?, 'active', ?)";
            $stmt = $this->db->executeQuery($sql, [$assignmentId, $siteId, $this->testUserId, $approvalStatus], 'iiis');
            $feasibilityId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdFeasibilityIds[] = $feasibilityId;
            
            return [
                'success' => true,
                'site_id' => $siteId,
                'feasibility_id' => $feasibilityId,
                'assignment_id' => $assignmentId,
                'contractor_id' => $contractorId
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create test data: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get or create a contractor company for testing
     */
    private function getOrCreateContractor(): ?int {
        try {
            // First try to find an existing active contractor
            $sql = "SELECT id FROM companies WHERE type = 'CONTRACTOR' AND status = 'ACTIVE' LIMIT 1";
            $result = $this->db->getResults($sql);
            
            if (!empty($result)) {
                return (int)$result[0]['id'];
            }
            
            // Create a new contractor company
            $companyName = 'TestContractor-' . $this->generateRandomString(6);
            $sql = "INSERT INTO companies (name, type, status, email) VALUES (?, 'CONTRACTOR', 'ACTIVE', ?)";
            $stmt = $this->db->executeQuery($sql, [
                $companyName,
                strtolower($companyName) . '@test.com'
            ], 'ss');
            $companyId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdCompanyIds[] = $companyId;
            
            return $companyId;
        } catch (Exception $e) {
            error_log("Failed to get or create contractor: " . $e->getMessage());
            return null;
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
            
            // Delete test companies (only the ones we created)
            if (!empty($this->createdCompanyIds)) {
                $ids = implode(',', array_map('intval', $this->createdCompanyIds));
                $this->db->executeQuery("DELETE FROM companies WHERE id IN ($ids)", [], '');
            }
        } catch (Exception $e) {
            echo "Warning: Cleanup failed - " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new InstallationDelegationPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

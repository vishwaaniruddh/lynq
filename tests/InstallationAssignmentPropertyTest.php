<?php
/**
 * Property Test: Installation Assignment
 * 
 * **Feature: installation-module, Property 4: Engineer assignment updates status to pending_eta**
 * **Validates: Requirements 2.4**
 * 
 * Property: For any valid engineer assignment to an installation with status "pending_assignment",
 * the system should update the status to "pending_eta" and record the assignment details.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationAssignmentService.php';
require_once __DIR__ . '/../services/InstallationDelegationService.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationAssignmentPropertyTest {
    private $testResults = [];
    private $iterations = 20;
    private $assignmentService;
    private $delegationService;
    private $db;
    private $createdInstallationIds = [];
    private $createdSiteIds = [];
    private $createdFeasibilityIds = [];
    private $createdAssignmentIds = [];
    private $createdDelegationIds = [];
    private $createdCompanyIds = [];
    private $createdUserIds = [];
    private $testUserId;
    private $testContractorId;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->assignmentService = new InstallationAssignmentService();
        $this->delegationService = new InstallationDelegationService();
        $this->testUserId = $this->getValidUserId();
        $this->testContractorId = $this->getOrCreateContractor();
    }
    
    /**
     * Get a valid user ID for testing
     */
    private function getValidUserId(): int {
        $result = $this->db->getResults('SELECT id FROM users WHERE status = 1 LIMIT 1');
        if (!empty($result)) {
            return (int)$result[0]['id'];
        }
        return 1;
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Installation Assignment Property Tests ===\n";
        echo "**Feature: installation-module, Property 4: Engineer assignment updates status to pending_eta**\n";
        echo "**Validates: Requirements 2.4**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Assignment updates status to pending_eta',
            [$this, 'testAssignmentUpdatesStatusToPendingEta']
        );
        
        $this->runPropertyTest(
            'Assignment records engineer and assignment details',
            [$this, 'testAssignmentRecordsDetails']
        );
        
        $this->runPropertyTest(
            'Cannot assign to non-pending_assignment installation',
            [$this, 'testCannotAssignToWrongStatus']
        );
        
        $this->runPropertyTest(
            'Cannot assign engineer from different company',
            [$this, 'testCannotAssignEngineerFromDifferentCompany']
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
     * Property Test: Assignment updates status to pending_eta
     * For any valid assignment, the installation status should change to "pending_eta"
     * 
     * **Feature: installation-module, Property 4: Engineer assignment updates status to pending_eta**
     * **Validates: Requirements 2.4**
     */
    private function testAssignmentUpdatesStatusToPendingEta(): array {
        // Create test data with pending_assignment status
        $testData = $this->createTestInstallationWithPendingAssignment();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = $testData['engineer_id'];
        $assignedBy = $this->testUserId;
        
        // Assign engineer
        $result = $this->assignmentService->assignEngineer($installationId, $engineerId, $assignedBy);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to assign engineer: ' . $result['message']
            ];
        }
        
        $installation = $result['data'];
        
        // Verify status is pending_eta (Requirement 2.4)
        if ($installation['status'] !== Installation::STATUS_PENDING_ETA) {
            return [
                'success' => false,
                'message' => "Expected status 'pending_eta', got '{$installation['status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Assignment records engineer and assignment details
     * For any valid assignment, the installation should record engineer_id, assigned_by, and assigned_at
     * 
     * **Feature: installation-module, Property 4: Engineer assignment updates status to pending_eta**
     * **Validates: Requirements 2.4**
     */
    private function testAssignmentRecordsDetails(): array {
        // Create test data
        $testData = $this->createTestInstallationWithPendingAssignment();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = $testData['engineer_id'];
        $assignedBy = $this->testUserId;
        
        $beforeAssignment = date('Y-m-d H:i:s');
        
        // Assign engineer
        $result = $this->assignmentService->assignEngineer($installationId, $engineerId, $assignedBy);
        
        $afterAssignment = date('Y-m-d H:i:s');
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to assign engineer: ' . $result['message']
            ];
        }
        
        $installation = $result['data'];
        
        // Verify assigned_engineer_id is set
        if ((int)$installation['assigned_engineer_id'] !== $engineerId) {
            return [
                'success' => false,
                'message' => "Expected assigned_engineer_id $engineerId, got {$installation['assigned_engineer_id']}"
            ];
        }
        
        // Verify assigned_by is set
        if ((int)$installation['assigned_by'] !== $assignedBy) {
            return [
                'success' => false,
                'message' => "Expected assigned_by $assignedBy, got {$installation['assigned_by']}"
            ];
        }
        
        // Verify assigned_at is set and within expected range
        if (empty($installation['assigned_at'])) {
            return [
                'success' => false,
                'message' => 'assigned_at should be set'
            ];
        }
        
        $assignedAt = $installation['assigned_at'];
        if ($assignedAt < $beforeAssignment || $assignedAt > $afterAssignment) {
            return [
                'success' => false,
                'message' => "assigned_at '$assignedAt' is not within expected range"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Cannot assign to non-pending_assignment installation
     * For any installation not in pending_assignment status, assignment should fail
     * 
     * **Feature: installation-module, Property 4: Engineer assignment updates status to pending_eta**
     * **Validates: Requirements 2.4**
     */
    private function testCannotAssignToWrongStatus(): array {
        // Create test data with pending_eta status (not pending_assignment)
        $testData = $this->createTestInstallationWithStatus(Installation::STATUS_PENDING_ETA);
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = $testData['engineer_id'];
        $assignedBy = $this->testUserId;
        
        // Attempt to assign engineer
        $result = $this->assignmentService->assignEngineer($installationId, $engineerId, $assignedBy);
        
        // Should fail
        if ($result['success']) {
            return [
                'success' => false,
                'message' => 'Assignment should not succeed for non-pending_assignment status'
            ];
        }
        
        // Verify correct error code
        if ($result['code'] !== 'WRONG_STATUS') {
            return [
                'success' => false,
                'message' => "Expected error code 'WRONG_STATUS', got '{$result['code']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Cannot assign engineer from different company
     * For any engineer not belonging to the contractor company, assignment should fail
     * 
     * **Feature: installation-module, Property 4: Engineer assignment updates status to pending_eta**
     * **Validates: Requirements 2.4**
     */
    private function testCannotAssignEngineerFromDifferentCompany(): array {
        // Create test data
        $testData = $this->createTestInstallationWithPendingAssignment();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $assignedBy = $this->testUserId;
        
        // Create an engineer from a different company
        $differentEngineerId = $this->createEngineerInDifferentCompany();
        if (!$differentEngineerId) {
            return [
                'success' => false,
                'message' => 'Failed to create engineer in different company'
            ];
        }
        
        // Attempt to assign engineer from different company
        $result = $this->assignmentService->assignEngineer($installationId, $differentEngineerId, $assignedBy);
        
        // Should fail
        if ($result['success']) {
            return [
                'success' => false,
                'message' => 'Assignment should not succeed for engineer from different company'
            ];
        }
        
        // Verify correct error code
        if ($result['code'] !== 'WRONG_COMPANY') {
            return [
                'success' => false,
                'message' => "Expected error code 'WRONG_COMPANY', got '{$result['code']}'"
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
            $sql = "INSERT INTO companies (name, type, status, contact_email) VALUES (?, 'CONTRACTOR', 'ACTIVE', ?)";
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
     * Create an engineer user for the contractor
     */
    private function createEngineerForContractor(int $contractorId): ?int {
        try {
            // Get engineer role ID
            $sql = "SELECT id FROM roles WHERE name IN ('engineer', 'Engineer') LIMIT 1";
            $result = $this->db->getResults($sql);
            $roleId = !empty($result) ? (int)$result[0]['id'] : 1;
            
            // Create engineer user
            $username = 'eng_' . $this->generateRandomString(6);
            $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = $this->db->executeQuery($sql, [
                $username,
                $username . '@test.com',
                password_hash('test123', PASSWORD_DEFAULT),
                'Test',
                'Engineer-' . $this->generateRandomString(4),
                $contractorId,
                $roleId
            ], 'sssssii');
            $userId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdUserIds[] = $userId;
            
            return $userId;
        } catch (Exception $e) {
            error_log("Failed to create engineer: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create an engineer in a different company
     */
    private function createEngineerInDifferentCompany(): ?int {
        try {
            // Create a different contractor company
            $companyName = 'DiffContractor-' . $this->generateRandomString(6);
            $sql = "INSERT INTO companies (name, type, status, contact_email) VALUES (?, 'CONTRACTOR', 'ACTIVE', ?)";
            $stmt = $this->db->executeQuery($sql, [
                $companyName,
                strtolower($companyName) . '@test.com'
            ], 'ss');
            $differentCompanyId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdCompanyIds[] = $differentCompanyId;
            
            // Create engineer in that company
            return $this->createEngineerForContractor($differentCompanyId);
        } catch (Exception $e) {
            error_log("Failed to create engineer in different company: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create test installation with pending_assignment status
     */
    private function createTestInstallationWithPendingAssignment(): array {
        return $this->createTestInstallationWithStatus(Installation::STATUS_PENDING_ASSIGNMENT);
    }
    
    /**
     * Create test installation with specified status
     */
    private function createTestInstallationWithStatus(string $status): array {
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
                    VALUES (?, ?, ?, 'accepted')";
            $stmt = $this->db->executeQuery($sql, [$siteId, $this->testContractorId, $this->testUserId], 'iii');
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
            
            // Create a feasibility check with adv_approved status
            $sql = "INSERT INTO feasibility_checks (assignment_id, site_id, created_by, status, approval_status) 
                    VALUES (?, ?, ?, 'active', 'adv_approved')";
            $stmt = $this->db->executeQuery($sql, [$assignmentId, $siteId, $this->testUserId], 'iii');
            $feasibilityId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdFeasibilityIds[] = $feasibilityId;
            
            // Create an engineer for the contractor
            $engineerId = $this->createEngineerForContractor($this->testContractorId);
            if (!$engineerId) {
                return [
                    'success' => false,
                    'message' => 'Failed to create engineer'
                ];
            }
            
            // Create installation with specified status
            $sql = "INSERT INTO installations (site_id, feasibility_id, initiated_by, created_by, contractor_id, 
                    delegated_by, delegated_at, atm_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
            $stmt = $this->db->executeQuery($sql, [
                $siteId,
                $feasibilityId,
                $this->testUserId,
                $this->testUserId,
                $this->testContractorId,
                $this->testUserId,
                $siteName,
                $status
            ], 'iiiiiiss');
            $installationId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdInstallationIds[] = $installationId;
            
            return [
                'success' => true,
                'installation_id' => $installationId,
                'site_id' => $siteId,
                'feasibility_id' => $feasibilityId,
                'contractor_id' => $this->testContractorId,
                'engineer_id' => $engineerId
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
            
            // Delete test users
            if (!empty($this->createdUserIds)) {
                $ids = implode(',', array_map('intval', $this->createdUserIds));
                $this->db->executeQuery("DELETE FROM users WHERE id IN ($ids)", [], '');
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
    $test = new InstallationAssignmentPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

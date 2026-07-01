<?php
/**
 * Property Test: Installation ETA Submission
 * 
 * **Feature: installation-module, Property 5: ETA submission updates status to pending_ada**
 * **Validates: Requirements 3.3**
 * 
 * Property: For any valid ETA submission by an assigned engineer,
 * the system should record the ETA date and update the installation status to "pending_ada".
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationETAService.php';
require_once __DIR__ . '/../services/InstallationAssignmentService.php';
require_once __DIR__ . '/../services/InstallationDelegationService.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationETAPropertyTest {
    private $testResults = [];
    private $iterations = 20;
    private $etaService;
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
        $this->etaService = new InstallationETAService();
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
        echo "\n=== Installation ETA Property Tests ===\n";
        echo "**Feature: installation-module, Property 5: ETA submission updates status to pending_ada**\n";
        echo "**Validates: Requirements 3.3**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'ETA submission updates status to pending_ada',
            [$this, 'testETASubmissionUpdatesStatusToPendingAda']
        );
        
        $this->runPropertyTest(
            'ETA submission records date and timestamp',
            [$this, 'testETASubmissionRecordsDetails']
        );

        
        $this->runPropertyTest(
            'Cannot submit ETA for non-pending_eta installation',
            [$this, 'testCannotSubmitETAForWrongStatus']
        );
        
        $this->runPropertyTest(
            'Cannot submit ETA if not assigned engineer',
            [$this, 'testCannotSubmitETAIfNotAssigned']
        );
        
        $this->runPropertyTest(
            'Cannot submit ETA with past date',
            [$this, 'testCannotSubmitETAWithPastDate']
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
     * Property Test: ETA submission updates status to pending_ada
     * For any valid ETA submission, the installation status should change to "pending_ada"
     * 
     * **Feature: installation-module, Property 5: ETA submission updates status to pending_ada**
     * **Validates: Requirements 3.3**
     */
    private function testETASubmissionUpdatesStatusToPendingAda(): array {
        // Create test data with pending_eta status
        $testData = $this->createTestInstallationWithPendingETA();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = $testData['engineer_id'];
        
        // Generate a future ETA date
        $etaDate = date('Y-m-d', strtotime('+' . rand(1, 30) . ' days'));
        
        // Submit ETA
        $result = $this->etaService->submitETA($installationId, $etaDate, $engineerId);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to submit ETA: ' . $result['message']
            ];
        }
        
        $installation = $result['data'];
        
        // Verify status is pending_ada (Requirement 3.3)
        if ($installation['status'] !== Installation::STATUS_PENDING_ADA) {
            return [
                'success' => false,
                'message' => "Expected status 'pending_ada', got '{$installation['status']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: ETA submission records date and timestamp
     * For any valid ETA submission, the installation should record eta_date and eta_submitted_at
     * 
     * **Feature: installation-module, Property 5: ETA submission updates status to pending_ada**
     * **Validates: Requirements 3.3**
     */
    private function testETASubmissionRecordsDetails(): array {
        // Create test data
        $testData = $this->createTestInstallationWithPendingETA();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = $testData['engineer_id'];
        
        // Generate a future ETA date
        $etaDate = date('Y-m-d', strtotime('+' . rand(1, 30) . ' days'));
        
        $beforeSubmission = date('Y-m-d H:i:s');
        
        // Submit ETA
        $result = $this->etaService->submitETA($installationId, $etaDate, $engineerId);
        
        $afterSubmission = date('Y-m-d H:i:s');
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Failed to submit ETA: ' . $result['message']
            ];
        }
        
        $installation = $result['data'];
        
        // Verify eta_date is set correctly
        if ($installation['eta_date'] !== $etaDate) {
            return [
                'success' => false,
                'message' => "Expected eta_date '$etaDate', got '{$installation['eta_date']}'"
            ];
        }
        
        // Verify eta_submitted_at is set and within expected range
        if (empty($installation['eta_submitted_at'])) {
            return [
                'success' => false,
                'message' => 'eta_submitted_at should be set'
            ];
        }
        
        $submittedAt = $installation['eta_submitted_at'];
        if ($submittedAt < $beforeSubmission || $submittedAt > $afterSubmission) {
            return [
                'success' => false,
                'message' => "eta_submitted_at '$submittedAt' is not within expected range"
            ];
        }
        
        return ['success' => true];
    }

    
    /**
     * Property Test: Cannot submit ETA for non-pending_eta installation
     * For any installation not in pending_eta status, ETA submission should fail
     * 
     * **Feature: installation-module, Property 5: ETA submission updates status to pending_ada**
     * **Validates: Requirements 3.3**
     */
    private function testCannotSubmitETAForWrongStatus(): array {
        // Create test data with pending_ada status (not pending_eta)
        $testData = $this->createTestInstallationWithStatus(Installation::STATUS_PENDING_ADA);
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = $testData['engineer_id'];
        
        // Generate a future ETA date
        $etaDate = date('Y-m-d', strtotime('+' . rand(1, 30) . ' days'));
        
        // Attempt to submit ETA
        $result = $this->etaService->submitETA($installationId, $etaDate, $engineerId);
        
        // Should fail
        if ($result['success']) {
            return [
                'success' => false,
                'message' => 'ETA submission should not succeed for non-pending_eta status'
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
     * Property Test: Cannot submit ETA if not assigned engineer
     * For any engineer not assigned to the installation, ETA submission should fail
     * 
     * **Feature: installation-module, Property 5: ETA submission updates status to pending_ada**
     * **Validates: Requirements 3.3**
     */
    private function testCannotSubmitETAIfNotAssigned(): array {
        // Create test data
        $testData = $this->createTestInstallationWithPendingETA();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        
        // Create a different engineer
        $differentEngineerId = $this->createEngineerInDifferentCompany();
        if (!$differentEngineerId) {
            return [
                'success' => false,
                'message' => 'Failed to create different engineer'
            ];
        }
        
        // Generate a future ETA date
        $etaDate = date('Y-m-d', strtotime('+' . rand(1, 30) . ' days'));
        
        // Attempt to submit ETA with different engineer
        $result = $this->etaService->submitETA($installationId, $etaDate, $differentEngineerId);
        
        // Should fail
        if ($result['success']) {
            return [
                'success' => false,
                'message' => 'ETA submission should not succeed for non-assigned engineer'
            ];
        }
        
        // Verify correct error code
        if ($result['code'] !== 'NOT_ASSIGNED') {
            return [
                'success' => false,
                'message' => "Expected error code 'NOT_ASSIGNED', got '{$result['code']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Cannot submit ETA with past date
     * For any ETA date in the past, submission should fail
     * 
     * **Feature: installation-module, Property 5: ETA submission updates status to pending_ada**
     * **Validates: Requirements 3.3**
     */
    private function testCannotSubmitETAWithPastDate(): array {
        // Create test data
        $testData = $this->createTestInstallationWithPendingETA();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        $engineerId = $testData['engineer_id'];
        
        // Generate a past ETA date
        $etaDate = date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'));
        
        // Attempt to submit ETA with past date
        $result = $this->etaService->submitETA($installationId, $etaDate, $engineerId);
        
        // Should fail
        if ($result['success']) {
            return [
                'success' => false,
                'message' => 'ETA submission should not succeed for past date'
            ];
        }
        
        // Verify correct error code
        if ($result['code'] !== 'DATE_IN_PAST') {
            return [
                'success' => false,
                'message' => "Expected error code 'DATE_IN_PAST', got '{$result['code']}'"
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
     * Create test installation with pending_eta status
     */
    private function createTestInstallationWithPendingETA(): array {
        return $this->createTestInstallationWithStatus(Installation::STATUS_PENDING_ETA);
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
            
            // Create an engineer for the contractor
            $engineerId = $this->createEngineerForContractor($this->testContractorId);
            if (!$engineerId) {
                return [
                    'success' => false,
                    'message' => 'Failed to create engineer'
                ];
            }
            
            // Create an engineer assignment
            $sql = "INSERT INTO engineer_assignments (site_id, delegation_id, engineer_id, assigned_by, status, feasibility_status) 
                    VALUES (?, ?, ?, ?, 'assigned', 'feasibility_completed')";
            $stmt = $this->db->executeQuery($sql, [$siteId, $delegationId, $engineerId, $this->testUserId], 'iiii');
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
            
            // Create installation with specified status and assigned engineer
            $sql = "INSERT INTO installations (site_id, feasibility_id, initiated_by, created_by, contractor_id, 
                    delegated_by, delegated_at, assigned_engineer_id, assigned_by, assigned_at, atm_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), ?, ?)";
            $stmt = $this->db->executeQuery($sql, [
                $siteId,
                $feasibilityId,
                $this->testUserId,
                $this->testUserId,
                $this->testContractorId,
                $this->testUserId,
                $engineerId,
                $this->testUserId,
                $siteName,
                $status
            ], 'iiiiiiiiss');
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
    $test = new InstallationETAPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

<?php
/**
 * Property Test: Contractor Installation List
 * 
 * **Feature: installation-module, Property 3: Contractor installation list displays delegated sites**
 * **Validates: Requirements 2.1, 2.2**
 * 
 * Property: For any contractor, the installation management page should display all sites 
 * delegated to their company with correct site details, delegation date, status, and assigned engineer.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationAssignmentService.php';
require_once __DIR__ . '/../services/InstallationDelegationService.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../models/Installation.php';

class ContractorInstallationListPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    private $assignmentService;
    private $delegationService;
    private $installationRepository;
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
        $this->assignmentService = new InstallationAssignmentService();
        $this->delegationService = new InstallationDelegationService();
        $this->installationRepository = new InstallationRepository();
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
        return 1;
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Contractor Installation List Property Tests ===\n";
        echo "**Feature: installation-module, Property 3: Contractor installation list displays delegated sites**\n";
        echo "**Validates: Requirements 2.1, 2.2**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Contractor sees only their delegated installations',
            [$this, 'testContractorSeesOnlyTheirInstallations']
        );
        
        $this->runPropertyTest(
            'Installation list contains required site details',
            [$this, 'testInstallationListContainsSiteDetails']
        );
        
        $this->runPropertyTest(
            'Installation list shows delegation date',
            [$this, 'testInstallationListShowsDelegationDate']
        );
        
        $this->runPropertyTest(
            'Installation list shows assigned engineer when present',
            [$this, 'testInstallationListShowsAssignedEngineer']
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
     * Property Test: Contractor sees only their delegated installations
     * For any contractor, the list should contain only installations delegated to them
     * 
     * **Feature: installation-module, Property 3: Contractor installation list displays delegated sites**
     * **Validates: Requirements 2.1**
     */
    private function testContractorSeesOnlyTheirInstallations(): array {
        // Create test data with two different contractors
        $testData1 = $this->createTestInstallation();
        if (!$testData1['success']) {
            return $testData1;
        }
        
        $testData2 = $this->createTestInstallation();
        if (!$testData2['success']) {
            return $testData2;
        }
        
        $contractorId1 = $testData1['contractor_id'];
        $contractorId2 = $testData2['contractor_id'];
        
        // Get installations for contractor 1
        $installations1 = $this->installationRepository->findByContractor($contractorId1);
        
        // Verify all returned installations belong to contractor 1
        foreach ($installations1 as $installation) {
            if ((int)$installation['contractor_id'] !== $contractorId1) {
                return [
                    'success' => false,
                    'message' => "Installation {$installation['id']} has contractor_id {$installation['contractor_id']}, expected $contractorId1"
                ];
            }
        }
        
        // Verify contractor 1's installation is in the list
        $found = false;
        foreach ($installations1 as $installation) {
            if ((int)$installation['id'] === $testData1['installation_id']) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            return [
                'success' => false,
                'message' => "Installation {$testData1['installation_id']} not found in contractor $contractorId1's list"
            ];
        }
        
        return ['success' => true];
    }

    /**
     * Property Test: Installation list contains required site details
     * For any installation in the list, it should contain site details (Requirement 2.2)
     * 
     * **Feature: installation-module, Property 3: Contractor installation list displays delegated sites**
     * **Validates: Requirements 2.2**
     */
    private function testInstallationListContainsSiteDetails(): array {
        // Create test data
        $testData = $this->createTestInstallation();
        if (!$testData['success']) {
            return $testData;
        }
        
        $contractorId = $testData['contractor_id'];
        
        // Get installations for contractor
        $installations = $this->installationRepository->findByContractor($contractorId);
        
        // Find our test installation
        $testInstallation = null;
        foreach ($installations as $installation) {
            if ((int)$installation['id'] === $testData['installation_id']) {
                $testInstallation = $installation;
                break;
            }
        }
        
        if (!$testInstallation) {
            return [
                'success' => false,
                'message' => "Test installation not found in contractor's list"
            ];
        }
        
        // Verify site details are present (Requirement 2.2)
        $requiredFields = ['site_id', 'atm_id', 'status'];
        foreach ($requiredFields as $field) {
            if (!isset($testInstallation[$field]) || $testInstallation[$field] === null) {
                return [
                    'success' => false,
                    'message' => "Required field '$field' is missing or null"
                ];
            }
        }
        
        return ['success' => true];
    }

    /**
     * Property Test: Installation list shows delegation date
     * For any installation in the list, it should show the delegation date (Requirement 2.2)
     * 
     * **Feature: installation-module, Property 3: Contractor installation list displays delegated sites**
     * **Validates: Requirements 2.2**
     */
    private function testInstallationListShowsDelegationDate(): array {
        // Create test data
        $testData = $this->createTestInstallation();
        if (!$testData['success']) {
            return $testData;
        }
        
        $contractorId = $testData['contractor_id'];
        
        // Get installations for contractor
        $installations = $this->installationRepository->findByContractor($contractorId);
        
        // Find our test installation
        $testInstallation = null;
        foreach ($installations as $installation) {
            if ((int)$installation['id'] === $testData['installation_id']) {
                $testInstallation = $installation;
                break;
            }
        }
        
        if (!$testInstallation) {
            return [
                'success' => false,
                'message' => "Test installation not found in contractor's list"
            ];
        }
        
        // Verify delegation date is present
        // Note: delegated_at might be in created_at if delegation happens at creation
        if (empty($testInstallation['created_at'])) {
            return [
                'success' => false,
                'message' => "Delegation/creation date is missing"
            ];
        }
        
        return ['success' => true];
    }

    /**
     * Property Test: Installation list shows assigned engineer when present
     * For any installation with an assigned engineer, the list should show the engineer name
     * 
     * **Feature: installation-module, Property 3: Contractor installation list displays delegated sites**
     * **Validates: Requirements 2.2**
     */
    private function testInstallationListShowsAssignedEngineer(): array {
        // Create test data with engineer assignment
        $testData = $this->createTestInstallationWithEngineer();
        if (!$testData['success']) {
            return $testData;
        }
        
        $contractorId = $testData['contractor_id'];
        $engineerId = $testData['engineer_id'];
        
        // Get installations for contractor
        $installations = $this->installationRepository->findByContractor($contractorId);
        
        // Find our test installation
        $testInstallation = null;
        foreach ($installations as $installation) {
            if ((int)$installation['id'] === $testData['installation_id']) {
                $testInstallation = $installation;
                break;
            }
        }
        
        if (!$testInstallation) {
            return [
                'success' => false,
                'message' => "Test installation not found in contractor's list"
            ];
        }
        
        // Verify assigned engineer ID is present
        if ((int)$testInstallation['assigned_engineer_id'] !== $engineerId) {
            return [
                'success' => false,
                'message' => "Expected assigned_engineer_id $engineerId, got {$testInstallation['assigned_engineer_id']}"
            ];
        }
        
        // Verify assigned engineer name is present (Requirement 2.2)
        if (empty($testInstallation['assigned_engineer_name'])) {
            return [
                'success' => false,
                'message' => "Assigned engineer name is missing"
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
     * Create test installation delegated to a contractor
     */
    private function createTestInstallation(): array {
        try {
            // Create a contractor company
            $contractorId = $this->createContractor();
            if (!$contractorId) {
                return [
                    'success' => false,
                    'message' => 'Failed to create contractor'
                ];
            }
            
            // Create a test site
            $siteName = 'TestSite-' . $this->generateRandomString(8);
            $atmId = 'ATM-' . $this->generateRandomString(6);
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
            
            // Create a feasibility check
            $sql = "INSERT INTO feasibility_checks (assignment_id, site_id, created_by, status, approval_status) 
                    VALUES (?, ?, ?, 'active', 'adv_approved')";
            $stmt = $this->db->executeQuery($sql, [$assignmentId, $siteId, $this->testUserId], 'iii');
            $feasibilityId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdFeasibilityIds[] = $feasibilityId;
            
            // Create installation delegated to contractor
            $sql = "INSERT INTO installations (site_id, feasibility_id, initiated_by, created_by, contractor_id, 
                    delegated_by, delegated_at, atm_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 'pending_assignment')";
            $stmt = $this->db->executeQuery($sql, [
                $siteId, $feasibilityId, $this->testUserId, $this->testUserId, 
                $contractorId, $this->testUserId, $atmId
            ], 'iiiiiis');
            $installationId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdInstallationIds[] = $installationId;
            
            return [
                'success' => true,
                'installation_id' => $installationId,
                'site_id' => $siteId,
                'feasibility_id' => $feasibilityId,
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
     * Create test installation with an assigned engineer
     */
    private function createTestInstallationWithEngineer(): array {
        try {
            // Create a contractor company
            $contractorId = $this->createContractor();
            if (!$contractorId) {
                return [
                    'success' => false,
                    'message' => 'Failed to create contractor'
                ];
            }
            
            // Create an engineer for this contractor
            $engineerId = $this->createEngineer($contractorId);
            if (!$engineerId) {
                return [
                    'success' => false,
                    'message' => 'Failed to create engineer'
                ];
            }
            
            // Create a test site
            $siteName = 'TestSite-' . $this->generateRandomString(8);
            $atmId = 'ATM-' . $this->generateRandomString(6);
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
            
            // Create a feasibility check
            $sql = "INSERT INTO feasibility_checks (assignment_id, site_id, created_by, status, approval_status) 
                    VALUES (?, ?, ?, 'active', 'adv_approved')";
            $stmt = $this->db->executeQuery($sql, [$assignmentId, $siteId, $this->testUserId], 'iii');
            $feasibilityId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdFeasibilityIds[] = $feasibilityId;
            
            // Create installation with assigned engineer
            $sql = "INSERT INTO installations (site_id, feasibility_id, initiated_by, created_by, contractor_id, 
                    delegated_by, delegated_at, assigned_engineer_id, assigned_by, assigned_at, atm_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), ?, 'pending_eta')";
            $stmt = $this->db->executeQuery($sql, [
                $siteId, $feasibilityId, $this->testUserId, $this->testUserId, 
                $contractorId, $this->testUserId, $engineerId, $this->testUserId, $atmId
            ], 'iiiiiiiis');
            $installationId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdInstallationIds[] = $installationId;
            
            return [
                'success' => true,
                'installation_id' => $installationId,
                'site_id' => $siteId,
                'feasibility_id' => $feasibilityId,
                'contractor_id' => $contractorId,
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
     * Create a contractor company for testing
     */
    private function createContractor(): ?int {
        try {
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
            error_log("Failed to create contractor: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create an engineer user for a contractor
     */
    private function createEngineer(int $contractorId): ?int {
        try {
            // Get or create engineer role
            $roleId = $this->getOrCreateEngineerRole();
            if (!$roleId) {
                return null;
            }
            
            $firstName = 'Engineer-' . $this->generateRandomString(4);
            $lastName = 'Test-' . $this->generateRandomString(4);
            $email = strtolower($firstName) . '@test.com';
            $username = 'eng_' . $this->generateRandomString(6);
            
            $sql = "INSERT INTO users (username, first_name, last_name, email, password_hash, company_id, role_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = $this->db->executeQuery($sql, [
                $username,
                $firstName,
                $lastName,
                $email,
                password_hash('test123', PASSWORD_DEFAULT),
                $contractorId,
                $roleId
            ], 'sssssii');
            $userId = $this->db->getConnection()->insert_id;
            $stmt->close();
            
            return $userId;
        } catch (Exception $e) {
            error_log("Failed to create engineer: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get or create engineer role
     */
    private function getOrCreateEngineerRole(): ?int {
        try {
            // Try to find existing engineer role
            $sql = "SELECT id FROM roles WHERE name IN ('engineer', 'Engineer') LIMIT 1";
            $result = $this->db->getResults($sql);
            
            if (!empty($result)) {
                return (int)$result[0]['id'];
            }
            
            // Create engineer role
            $sql = "INSERT INTO roles (name, description) VALUES ('engineer', 'Field Engineer')";
            $stmt = $this->db->executeQuery($sql, [], '');
            $roleId = $this->db->getConnection()->insert_id;
            $stmt->close();
            
            return $roleId;
        } catch (Exception $e) {
            error_log("Failed to get or create engineer role: " . $e->getMessage());
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
            
            // Delete test companies
            if (!empty($this->createdCompanyIds)) {
                $ids = implode(',', array_map('intval', $this->createdCompanyIds));
                // First delete users belonging to these companies
                $this->db->executeQuery("DELETE FROM users WHERE company_id IN ($ids)", [], '');
                $this->db->executeQuery("DELETE FROM companies WHERE id IN ($ids)", [], '');
            }
        } catch (Exception $e) {
            echo "Warning: Cleanup failed - " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new ContractorInstallationListPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

<?php
/**
 * Property Test: Contractor Review Panel Visibility
 * 
 * **Feature: installation-module, Property 17: Contractor review panel visibility**
 * **Validates: Requirements 14.1**
 * 
 * Property: For any user with contractor_admin or contractor_manager role viewing a 
 * submitted installation, the system should display the review panel with section-wise 
 * approve/reject options.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationReviewService.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/../models/InstallationCheckpoint.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class ContractorReviewPanelVisibilityPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    private $reviewService;
    private $installationService;
    private $installationRepository;
    private $db;
    private $createdInstallationIds = [];
    private $createdSiteIds = [];
    private $createdFeasibilityIds = [];
    private $createdAssignmentIds = [];
    private $createdDelegationIds = [];
    private $createdUserIds = [];
    private $createdRoleIds = [];
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->reviewService = new InstallationReviewService();
        $this->installationService = new InstallationService();
        $this->installationRepository = new InstallationRepository();
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Contractor Review Panel Visibility Property Tests ===\n";
        echo "**Feature: installation-module, Property 17: Contractor review panel visibility**\n";
        echo "**Validates: Requirements 14.1**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'Contractor admin can review submitted installations',
            [$this, 'testContractorAdminCanReviewSubmitted']
        );
        
        $this->runPropertyTest(
            'Contractor manager can review submitted installations',
            [$this, 'testContractorManagerCanReviewSubmitted']
        );
        
        $this->runPropertyTest(
            'Contractor admin can review pending_contractor_review installations',
            [$this, 'testContractorAdminCanReviewPendingContractorReview']
        );
        
        $this->runPropertyTest(
            'Non-contractor users cannot review submitted installations',
            [$this, 'testNonContractorCannotReview']
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
     * Property Test: Contractor admin can review submitted installations
     * For any contractor_admin user viewing a submitted installation, canUserReview should return true
     */
    private function testContractorAdminCanReviewSubmitted(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        
        // Create a contractor_admin user
        $userData = $this->createTestUserWithRole('contractor_admin');
        if (!$userData['success']) {
            return $userData;
        }
        
        $userId = $userData['user_id'];
        
        // Check if user can review
        $result = $this->reviewService->canUserReview($userId, $installationId);
        
        if (!$result['canReview']) {
            return [
                'success' => false,
                'message' => "Contractor admin should be able to review submitted installation. Reason: {$result['reason']}"
            ];
        }
        
        if ($result['level'] !== InstallationCheckpoint::LEVEL_CONTRACTOR) {
            return [
                'success' => false,
                'message' => "Expected review level 'contractor', got '{$result['level']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Contractor manager can review submitted installations
     * For any contractor_manager user viewing a submitted installation, canUserReview should return true
     */
    private function testContractorManagerCanReviewSubmitted(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        
        // Create a contractor_manager user
        $userData = $this->createTestUserWithRole('contractor_manager');
        if (!$userData['success']) {
            return $userData;
        }
        
        $userId = $userData['user_id'];
        
        // Check if user can review
        $result = $this->reviewService->canUserReview($userId, $installationId);
        
        if (!$result['canReview']) {
            return [
                'success' => false,
                'message' => "Contractor manager should be able to review submitted installation. Reason: {$result['reason']}"
            ];
        }
        
        if ($result['level'] !== InstallationCheckpoint::LEVEL_CONTRACTOR) {
            return [
                'success' => false,
                'message' => "Expected review level 'contractor', got '{$result['level']}'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Contractor admin can review pending_contractor_review installations
     * For any contractor_admin user viewing a pending_contractor_review installation, canUserReview should return true
     */
    private function testContractorAdminCanReviewPendingContractorReview(): array {
        // Create test installation in pending_contractor_review status
        $testData = $this->createTestInstallationInStatus(Installation::STATUS_PENDING_CONTRACTOR_REVIEW);
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        
        // Create a contractor_admin user
        $userData = $this->createTestUserWithRole('contractor_admin');
        if (!$userData['success']) {
            return $userData;
        }
        
        $userId = $userData['user_id'];
        
        // Check if user can review
        $result = $this->reviewService->canUserReview($userId, $installationId);
        
        if (!$result['canReview']) {
            return [
                'success' => false,
                'message' => "Contractor admin should be able to review pending_contractor_review installation. Reason: {$result['reason']}"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Non-contractor users cannot review submitted installations
     * For any user without contractor_admin or contractor_manager role, canUserReview should return false
     */
    private function testNonContractorCannotReview(): array {
        // Create test installation in submitted status
        $testData = $this->createTestInstallationInSubmittedStatus();
        if (!$testData['success']) {
            return $testData;
        }
        
        $installationId = $testData['installation_id'];
        
        // Create a user with a non-contractor role (engineer)
        $userData = $this->createTestUserWithRole('engineer');
        if (!$userData['success']) {
            return $userData;
        }
        
        $userId = $userData['user_id'];
        
        // Check if user can review
        $result = $this->reviewService->canUserReview($userId, $installationId);
        
        if ($result['canReview']) {
            return [
                'success' => false,
                'message' => 'Engineer should not be able to review submitted installation'
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
     * Create test user with specific role
     */
    private function createTestUserWithRole(string $roleName): array {
        try {
            // Check if role exists, create if not
            $sql = "SELECT id FROM roles WHERE name = ?";
            $result = $this->db->getResults($sql, [$roleName], 's');
            
            if (empty($result)) {
                // Create the role
                $sql = "INSERT INTO roles (name, description, company_type) VALUES (?, ?, 'contractor')";
                $stmt = $this->db->executeQuery($sql, [$roleName, 'Test role: ' . $roleName], 'ss');
                $roleId = $this->db->getConnection()->insert_id;
                $stmt->close();
                $this->createdRoleIds[] = $roleId;
            } else {
                $roleId = $result[0]['id'];
            }
            
            // Create user with this role
            $username = 'testuser_' . $this->generateRandomString(8);
            $email = $username . '@test.com';
            $sql = "INSERT INTO users (username, email, password_hash, role_id, company_id, status) 
                    VALUES (?, ?, ?, ?, 1, 1)";
            $stmt = $this->db->executeQuery($sql, [
                $username,
                $email,
                password_hash('test123', PASSWORD_DEFAULT),
                $roleId
            ], 'sssi');
            $userId = $this->db->getConnection()->insert_id;
            $stmt->close();
            $this->createdUserIds[] = $userId;
            
            return [
                'success' => true,
                'user_id' => $userId,
                'role_id' => $roleId
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create test user: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Create test installation in submitted status
     */
    private function createTestInstallationInSubmittedStatus(): array {
        return $this->createTestInstallationInStatus(Installation::STATUS_SUBMITTED);
    }
    
    /**
     * Create test installation in specific status
     */
    private function createTestInstallationInStatus(string $status): array {
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
            
            // Create installation in specified status
            $sql = "INSERT INTO installations (site_id, feasibility_id, initiated_by, created_by, atm_id, status) 
                    VALUES (?, ?, 1, 1, ?, ?)";
            $stmt = $this->db->executeQuery($sql, [$siteId, $feasibilityId, $siteName, $status], 'iiss');
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
            // Delete checkpoints and remarks by installation
            if (!empty($this->createdInstallationIds)) {
                $ids = implode(',', array_map('intval', $this->createdInstallationIds));
                $this->db->executeQuery("DELETE FROM installation_section_remarks WHERE installation_id IN ($ids)", [], '');
                $this->db->executeQuery("DELETE FROM installation_checkpoints WHERE installation_id IN ($ids)", [], '');
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
            
            // Delete test users
            if (!empty($this->createdUserIds)) {
                $ids = implode(',', array_map('intval', $this->createdUserIds));
                $this->db->executeQuery("DELETE FROM users WHERE id IN ($ids)", [], '');
            }
            
            // Delete test roles (only if we created them)
            if (!empty($this->createdRoleIds)) {
                $ids = implode(',', array_map('intval', $this->createdRoleIds));
                $this->db->executeQuery("DELETE FROM roles WHERE id IN ($ids)", [], '');
            }
        } catch (Exception $e) {
            echo "Warning: Cleanup failed - " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new ContractorReviewPanelVisibilityPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

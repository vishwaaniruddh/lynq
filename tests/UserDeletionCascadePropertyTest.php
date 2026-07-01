<?php
/**
 * Property Test for User Deletion Cascade
 * **Feature: lho-manager-assignment, Property 6: User Deletion Cascade**
 * **Validates: Requirements 4.1**
 * 
 * For any user with LHO manager assignments, deleting or deactivating that user 
 * should result in zero remaining assignments for that user.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../repositories/LhoManagerRepository.php';
require_once __DIR__ . '/../services/UserService.php';

class UserDeletionCascadePropertyTest extends PropertyTestBase {
    
    private $lhoManagerRepository;
    private $userService;
    private $createdRecords = [];
    private $testLhoIds = [];
    private $testCompanyId;
    private $testRoleId;
    private $actingUserId;
    
    public function __construct() {
        parent::__construct();
        $this->lhoManagerRepository = new LhoManagerRepository();
        $this->userService = new UserService();
        $this->iterations = 100;
    }
    
    public function runTests() {
        echo "=== User Deletion Cascade Property Tests ===\n\n";
        
        // Setup test data
        $this->setupTestData();
        
        $allPassed = true;
        
        // Test User Deactivation Cascade
        $allPassed &= $this->runPropertyTest(
            "User Deactivation Removes LHO Manager Assignments",
            [$this, 'testUserDeactivationCascade']
        );
        
        // Test User Hard Deletion Cascade (via database CASCADE)
        $allPassed &= $this->runPropertyTest(
            "User Hard Deletion Removes LHO Manager Assignments",
            [$this, 'testUserHardDeletionCascade']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 6: User Deletion Cascade (Deactivation)
     * For any user with LHO manager assignments, deactivating that user 
     * should result in zero remaining assignments for that user.
     * **Feature: lho-manager-assignment, Property 6: User Deletion Cascade**
     * **Validates: Requirements 4.1**
     */
    public function testUserDeactivationCascade() {
        try {
            // Create a test user
            $username = 'cascade_test_' . $this->generateRandomString(8);
            $email = $username . '@test.com';
            
            $userData = [
                'username' => $username,
                'first_name' => 'Cascade',
                'last_name' => 'Test',
                'email' => $email,
                'password' => 'TestPass123!',
                'company_id' => $this->testCompanyId,
                'role_id' => $this->testRoleId,
                'status' => 1
            ];
            
            $user = $this->userService->createUser($userData, $this->actingUserId);
            $userId = (int)$user['id'];
            $this->createdRecords['users'][] = $userId;
            
            // Assign user to random LHOs as manager
            $numLhos = rand(1, min(3, count($this->testLhoIds)));
            $shuffledLhos = $this->testLhoIds;
            shuffle($shuffledLhos);
            $selectedLhoIds = array_slice($shuffledLhos, 0, $numLhos);
            
            foreach ($selectedLhoIds as $lhoId) {
                $this->lhoManagerRepository->syncManagers($lhoId, [$userId], $this->actingUserId);
            }
            
            // Verify assignments exist
            $assignmentsBefore = $this->lhoManagerRepository->countLhosByUserId($userId);
            $this->assert(
                $assignmentsBefore === $numLhos,
                "Expected $numLhos assignments before deactivation, got $assignmentsBefore"
            );
            
            // Deactivate the user (status = 0)
            $this->userService->updateUser($userId, ['status' => 0], $this->actingUserId);
            
            // Verify all assignments are removed
            $assignmentsAfter = $this->lhoManagerRepository->countLhosByUserId($userId);
            $this->assert(
                $assignmentsAfter === 0,
                "Expected 0 assignments after deactivation, got $assignmentsAfter"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'user_id' => $userId ?? null,
                    'selected_lho_ids' => $selectedLhoIds ?? null
                ]
            ];
        }
    }

    
    /**
     * Property 6: User Deletion Cascade (Hard Delete)
     * For any user with LHO manager assignments, hard deleting that user 
     * should result in zero remaining assignments for that user (via database CASCADE).
     * **Feature: lho-manager-assignment, Property 6: User Deletion Cascade**
     * **Validates: Requirements 4.1**
     */
    public function testUserHardDeletionCascade() {
        try {
            // Create a test user
            $username = 'harddelete_test_' . $this->generateRandomString(8);
            $email = $username . '@test.com';
            
            $userData = [
                'username' => $username,
                'first_name' => 'HardDelete',
                'last_name' => 'Test',
                'email' => $email,
                'password' => 'TestPass123!',
                'company_id' => $this->testCompanyId,
                'role_id' => $this->testRoleId,
                'status' => 1
            ];
            
            $user = $this->userService->createUser($userData, $this->actingUserId);
            $userId = (int)$user['id'];
            // Don't add to createdRecords since we'll delete it
            
            // Assign user to random LHOs as manager
            $numLhos = rand(1, min(3, count($this->testLhoIds)));
            $shuffledLhos = $this->testLhoIds;
            shuffle($shuffledLhos);
            $selectedLhoIds = array_slice($shuffledLhos, 0, $numLhos);
            
            foreach ($selectedLhoIds as $lhoId) {
                $this->lhoManagerRepository->syncManagers($lhoId, [$userId], $this->actingUserId);
            }
            
            // Verify assignments exist
            $assignmentsBefore = $this->lhoManagerRepository->countLhosByUserId($userId);
            $this->assert(
                $assignmentsBefore === $numLhos,
                "Expected $numLhos assignments before deletion, got $assignmentsBefore"
            );
            
            // Hard delete the user (database CASCADE should remove assignments)
            $this->userService->deleteUser($userId, $this->actingUserId);
            
            // Verify all assignments are removed (via database CASCADE)
            $assignmentsAfter = $this->lhoManagerRepository->countLhosByUserId($userId);
            $this->assert(
                $assignmentsAfter === 0,
                "Expected 0 assignments after hard deletion, got $assignmentsAfter"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'user_id' => $userId ?? null,
                    'selected_lho_ids' => $selectedLhoIds ?? null
                ]
            ];
        }
    }
    
    /**
     * Setup test data (LHOs and ADV company/role)
     */
    private function setupTestData() {
        try {
            // Get or create test ADV company
            $result = $this->getResults("SELECT id FROM companies WHERE type = 'ADV' LIMIT 1");
            if (!empty($result)) {
                $this->testCompanyId = (int)$result[0]['id'];
            } else {
                $stmt = $this->executeQuery(
                    "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                    ['Test ADV Company Cascade', 'ADV', 1],
                    'ssi'
                );
                $this->testCompanyId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['companies'][] = $this->testCompanyId;
            }
            
            // Get a role ID for test users
            $roleResult = $this->getResults("SELECT id FROM roles LIMIT 1");
            $this->testRoleId = !empty($roleResult) ? (int)$roleResult[0]['id'] : 1;
            
            // Get or create acting user (must be different from test users)
            $result = $this->getResults(
                "SELECT u.id FROM users u 
                 JOIN companies c ON u.company_id = c.id 
                 WHERE c.type = 'ADV' AND u.status = 1 
                 LIMIT 1"
            );
            
            if (!empty($result)) {
                $this->actingUserId = (int)$result[0]['id'];
            } else {
                $username = 'acting_user_' . $this->generateRandomString(8);
                $email = $username . '@test.com';
                $stmt = $this->executeQuery(
                    "INSERT INTO users (username, first_name, last_name, email, password_hash, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$username, 'Acting', 'User', $email, password_hash('test123', PASSWORD_DEFAULT), $this->testCompanyId, $this->testRoleId, 1],
                    'sssssiii'
                );
                $this->actingUserId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['acting_user'] = $this->actingUserId;
            }
            
            // Get existing LHOs or create test LHOs
            $result = $this->getResults("SELECT id FROM lhos WHERE status = 'active' LIMIT 5");
            
            if (!empty($result)) {
                foreach ($result as $row) {
                    $this->testLhoIds[] = (int)$row['id'];
                }
            }
            
            // If not enough LHOs, create some
            while (count($this->testLhoIds) < 3) {
                $lhoName = 'Cascade Test LHO ' . $this->generateRandomString(8);
                $stmt = $this->executeQuery(
                    "INSERT INTO lhos (lho_name, status, created_by) VALUES (?, ?, ?)",
                    [$lhoName, 'active', $this->actingUserId],
                    'ssi'
                );
                $lhoId = $this->db->insert_id;
                $stmt->close();
                $this->testLhoIds[] = $lhoId;
                $this->createdRecords['lhos'][] = $lhoId;
            }
            
            echo "Setup complete: " . count($this->testLhoIds) . " LHOs, acting user ID: " . $this->actingUserId . "\n";
            
        } catch (Exception $e) {
            echo "Setup warning: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Clean up lho_managers for test LHOs
            if (!empty($this->testLhoIds)) {
                $ids = implode(',', array_map('intval', $this->testLhoIds));
                $this->db->query("DELETE FROM lho_managers WHERE lho_id IN ($ids)");
            }
            
            // Clean up created LHOs
            if (isset($this->createdRecords['lhos']) && !empty($this->createdRecords['lhos'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['lhos']));
                $this->db->query("DELETE FROM lhos WHERE id IN ($ids)");
            }
            
            // Clean up test LHOs by name pattern
            $this->db->query("DELETE FROM lho_managers WHERE lho_id IN (SELECT id FROM lhos WHERE lho_name LIKE 'Cascade Test LHO %')");
            $this->db->query("DELETE FROM lhos WHERE lho_name LIKE 'Cascade Test LHO %'");
            
            // Clean up created users
            if (isset($this->createdRecords['users']) && !empty($this->createdRecords['users'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['users']));
                $this->db->query("DELETE FROM lho_managers WHERE user_id IN ($ids)");
                $this->db->query("DELETE FROM users WHERE id IN ($ids)");
            }
            
            // Clean up test users by name pattern
            $this->db->query("DELETE FROM lho_managers WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'cascade_test_%' OR username LIKE 'harddelete_test_%')");
            $this->db->query("DELETE FROM users WHERE username LIKE 'cascade_test_%' OR username LIKE 'harddelete_test_%'");
            
            // Clean up acting user if created
            if (isset($this->createdRecords['acting_user'])) {
                $this->db->query("DELETE FROM users WHERE id = " . (int)$this->createdRecords['acting_user']);
            }
            
            // Clean up created companies
            if (isset($this->createdRecords['companies']) && !empty($this->createdRecords['companies'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['companies']));
                $this->db->query("DELETE FROM companies WHERE id IN ($ids)");
            }
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

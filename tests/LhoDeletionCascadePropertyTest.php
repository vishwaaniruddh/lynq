<?php
/**
 * Property Test for LHO Deletion Cascade
 * **Feature: lho-manager-assignment, Property 7: LHO Deletion Cascade**
 * **Validates: Requirements 4.2**
 * 
 * For any LHO with manager assignments, deleting that LHO should result in 
 * zero remaining manager assignments for that LHO.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../repositories/LhoManagerRepository.php';
require_once __DIR__ . '/../services/LocationService.php';

class LhoDeletionCascadePropertyTest extends PropertyTestBase {
    
    private $lhoManagerRepository;
    private $locationService;
    private $createdRecords = [];
    private $testUserIds = [];
    private $testCompanyId;
    private $actingUserId;
    
    public function __construct() {
        parent::__construct();
        $this->lhoManagerRepository = new LhoManagerRepository();
        $this->locationService = new LocationService();
        $this->iterations = 100;
    }
    
    public function runTests() {
        echo "=== LHO Deletion Cascade Property Tests ===\n\n";
        
        // Setup test data
        $this->setupTestData();
        
        $allPassed = true;
        
        // Test LHO Deletion Cascade
        $allPassed &= $this->runPropertyTest(
            "LHO Deletion Removes Manager Assignments",
            [$this, 'testLhoDeletionCascade']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 7: LHO Deletion Cascade
     * For any LHO with manager assignments, deleting that LHO should result in 
     * zero remaining manager assignments for that LHO.
     * **Feature: lho-manager-assignment, Property 7: LHO Deletion Cascade**
     * **Validates: Requirements 4.2**
     */
    public function testLhoDeletionCascade() {
        try {
            // Create a test LHO
            $lhoName = 'LHO Cascade Test ' . $this->generateRandomString(8);
            
            $lhoResult = $this->locationService->createLho([
                'lho_name' => $lhoName,
                'status' => 'active'
            ], $this->actingUserId);
            
            $this->assert($lhoResult['success'] === true, "Failed to create LHO: " . ($lhoResult['message'] ?? 'Unknown error'));
            
            $lhoId = (int)$lhoResult['data']['id'];
            // Don't add to createdRecords since we'll delete it
            
            // Assign random managers to the LHO
            $numManagers = rand(1, min(3, count($this->testUserIds)));
            $shuffledUsers = $this->testUserIds;
            shuffle($shuffledUsers);
            $selectedUserIds = array_slice($shuffledUsers, 0, $numManagers);
            
            $this->lhoManagerRepository->syncManagers($lhoId, $selectedUserIds, $this->actingUserId);
            
            // Verify assignments exist
            $assignmentsBefore = $this->lhoManagerRepository->countManagersByLhoId($lhoId);
            $this->assert(
                $assignmentsBefore === $numManagers,
                "Expected $numManagers assignments before deletion, got $assignmentsBefore"
            );
            
            // Delete the LHO (database CASCADE should remove assignments)
            $deleteResult = $this->locationService->deleteLho($lhoId, $this->actingUserId);
            $this->assert($deleteResult['success'] === true, "Failed to delete LHO: " . ($deleteResult['message'] ?? 'Unknown error'));
            
            // Verify all assignments are removed (via database CASCADE)
            $assignmentsAfter = $this->lhoManagerRepository->countManagersByLhoId($lhoId);
            $this->assert(
                $assignmentsAfter === 0,
                "Expected 0 assignments after LHO deletion, got $assignmentsAfter"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'lho_id' => $lhoId ?? null,
                    'selected_user_ids' => $selectedUserIds ?? null
                ]
            ];
        }
    }

    
    /**
     * Setup test data (ADV users)
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
                    ['Test ADV Company LHO Cascade', 'ADV', 1],
                    'ssi'
                );
                $this->testCompanyId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['companies'][] = $this->testCompanyId;
            }
            
            // Get a role ID for test users
            $roleResult = $this->getResults("SELECT id FROM roles LIMIT 1");
            $testRoleId = !empty($roleResult) ? (int)$roleResult[0]['id'] : 1;
            
            // Get existing ADV users
            $result = $this->getResults(
                "SELECT u.id FROM users u 
                 JOIN companies c ON u.company_id = c.id 
                 WHERE c.type = 'ADV' AND u.status = 1 
                 LIMIT 5"
            );
            
            if (!empty($result)) {
                foreach ($result as $row) {
                    $this->testUserIds[] = (int)$row['id'];
                }
            }
            
            // If not enough users, create some
            while (count($this->testUserIds) < 3) {
                $username = 'lho_cascade_user_' . $this->generateRandomString(8);
                $email = $username . '@test.com';
                $stmt = $this->executeQuery(
                    "INSERT INTO users (username, first_name, last_name, email, password_hash, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$username, 'LHO', 'CascadeUser' . count($this->testUserIds), $email, password_hash('test123', PASSWORD_DEFAULT), $this->testCompanyId, $testRoleId, 1],
                    'sssssiii'
                );
                $userId = $this->db->insert_id;
                $stmt->close();
                $this->testUserIds[] = $userId;
                $this->createdRecords['users'][] = $userId;
            }
            
            // Set acting user ID
            $this->actingUserId = $this->testUserIds[0];
            
            echo "Setup complete: " . count($this->testUserIds) . " users, acting user ID: " . $this->actingUserId . "\n";
            
        } catch (Exception $e) {
            echo "Setup warning: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Clean up test LHOs by name pattern (in case any weren't deleted)
            $this->db->query("DELETE FROM lho_managers WHERE lho_id IN (SELECT id FROM lhos WHERE lho_name LIKE 'LHO Cascade Test %')");
            $this->db->query("DELETE FROM lhos WHERE lho_name LIKE 'LHO Cascade Test %'");
            
            // Clean up created users
            if (isset($this->createdRecords['users']) && !empty($this->createdRecords['users'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['users']));
                $this->db->query("DELETE FROM lho_managers WHERE user_id IN ($ids)");
                $this->db->query("DELETE FROM users WHERE id IN ($ids)");
            }
            
            // Clean up test users by name pattern
            $this->db->query("DELETE FROM lho_managers WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'lho_cascade_user_%')");
            $this->db->query("DELETE FROM users WHERE username LIKE 'lho_cascade_user_%'");
            
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

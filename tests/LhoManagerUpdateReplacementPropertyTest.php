<?php
/**
 * Property Test for LHO Manager Update Replacement
 * **Feature: lho-manager-assignment, Property 2: Manager Update Replacement**
 * **Validates: Requirements 1.4**
 * 
 * For any LHO with existing manager assignments, updating with a new set of manager IDs 
 * should result in only the new set being present (complete replacement, not merge).
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../repositories/LhoManagerRepository.php';

class LhoManagerUpdateReplacementPropertyTest extends PropertyTestBase {
    
    private $lhoManagerRepository;
    private $createdRecords = [];
    private $testLhoIds = [];
    private $testUserIds = [];
    private $testCreatorId;
    
    public function __construct() {
        parent::__construct();
        $this->lhoManagerRepository = new LhoManagerRepository();
        $this->iterations = 100; // As specified in design doc
    }
    
    public function runTests() {
        echo "=== LHO Manager Update Replacement Property Tests ===\n\n";
        
        // Setup test data
        $this->setupTestData();
        
        $allPassed = true;
        
        // Test Manager Update Replacement
        $allPassed &= $this->runPropertyTest(
            "Manager Update Replacement (Complete Replacement, Not Merge)",
            [$this, 'testManagerUpdateReplacement']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 2: Manager Update Replacement
     * For any LHO with existing manager assignments, updating with a new set 
     * should result in only the new set being present (complete replacement).
     * **Feature: lho-manager-assignment, Property 2: Manager Update Replacement**
     * **Validates: Requirements 1.4**
     */
    public function testManagerUpdateReplacement() {
        try {
            // Ensure we have test data
            if (empty($this->testLhoIds) || count($this->testUserIds) < 2) {
                return [
                    'success' => false,
                    'message' => 'Not enough test LHOs or users available (need at least 2 users)'
                ];
            }
            
            // Pick a random LHO
            $lhoId = $this->generateRandomChoice($this->testLhoIds);
            
            // Generate initial set of managers (at least 1)
            $numInitialManagers = rand(1, max(1, count($this->testUserIds) - 1));
            $shuffledUsers = $this->testUserIds;
            shuffle($shuffledUsers);
            $initialUserIds = array_slice($shuffledUsers, 0, $numInitialManagers);
            sort($initialUserIds);
            
            // Sync initial managers
            $this->lhoManagerRepository->syncManagers($lhoId, $initialUserIds, $this->testCreatorId);
            
            // Verify initial state
            $initialRetrieved = $this->lhoManagerRepository->getManagerIdsByLhoId($lhoId);
            $initialRetrieved = array_map('intval', $initialRetrieved);
            sort($initialRetrieved);
            
            $this->assert(
                $initialRetrieved === $initialUserIds,
                "Initial sync failed: expected [" . implode(',', $initialUserIds) . "], got [" . implode(',', $initialRetrieved) . "]"
            );
            
            // Generate a DIFFERENT set of managers for update
            // This could be completely different, partially overlapping, or empty
            $updateType = rand(0, 3);
            $newUserIds = [];
            
            switch ($updateType) {
                case 0:
                    // Empty set (remove all managers)
                    $newUserIds = [];
                    break;
                case 1:
                    // Completely different set (no overlap)
                    $remainingUsers = array_diff($this->testUserIds, $initialUserIds);
                    if (!empty($remainingUsers)) {
                        $numNew = rand(1, count($remainingUsers));
                        shuffle($remainingUsers);
                        $newUserIds = array_slice(array_values($remainingUsers), 0, $numNew);
                    }
                    break;
                case 2:
                    // Partially overlapping set
                    shuffle($shuffledUsers);
                    $numNew = rand(1, count($this->testUserIds));
                    $newUserIds = array_slice($shuffledUsers, 0, $numNew);
                    break;
                case 3:
                    // Single user (could be same or different)
                    $newUserIds = [$this->generateRandomChoice($this->testUserIds)];
                    break;
            }
            
            sort($newUserIds);
            
            // Update with new set
            $this->lhoManagerRepository->syncManagers($lhoId, $newUserIds, $this->testCreatorId);
            
            // Retrieve and verify
            $updatedRetrieved = $this->lhoManagerRepository->getManagerIdsByLhoId($lhoId);
            $updatedRetrieved = array_map('intval', $updatedRetrieved);
            sort($updatedRetrieved);
            
            // Verify complete replacement (not merge)
            $this->assert(
                count($updatedRetrieved) === count($newUserIds),
                "Manager count mismatch after update: expected " . count($newUserIds) . ", got " . count($updatedRetrieved)
            );
            
            $this->assert(
                $updatedRetrieved === $newUserIds,
                "Manager IDs mismatch after update: expected [" . implode(',', $newUserIds) . "], got [" . implode(',', $updatedRetrieved) . "]"
            );
            
            // Verify no old managers remain (unless they were in the new set)
            $oldOnlyManagers = array_diff($initialUserIds, $newUserIds);
            foreach ($oldOnlyManagers as $oldManagerId) {
                $this->assert(
                    !in_array($oldManagerId, $updatedRetrieved),
                    "Old manager $oldManagerId should have been removed but is still present"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'lho_id' => $lhoId ?? null,
                    'initial_user_ids' => $initialUserIds ?? null,
                    'new_user_ids' => $newUserIds ?? null,
                    'update_type' => $updateType ?? null
                ]
            ];
        }
    }

    
    /**
     * Setup test data (LHOs and ADV users)
     */
    private function setupTestData() {
        try {
            // Get or create test ADV company
            $result = $this->getResults("SELECT id FROM companies WHERE type = 'ADV' LIMIT 1");
            if (!empty($result)) {
                $testCompanyId = (int)$result[0]['id'];
            } else {
                // Create test company
                $stmt = $this->executeQuery(
                    "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                    ['Test ADV Company', 'ADV', 1],
                    'ssi'
                );
                $testCompanyId = $this->db->insert_id;
                $stmt->close();
                $this->createdRecords['companies'][] = $testCompanyId;
            }
            
            // Get a role ID for test users
            $roleResult = $this->getResults("SELECT id FROM roles LIMIT 1");
            $testRoleId = !empty($roleResult) ? (int)$roleResult[0]['id'] : 1;
            
            // Get existing ADV users or create test users
            // status = 1 means active in the users table
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
            
            // If not enough users, create some (need at least 4 for good test coverage)
            while (count($this->testUserIds) < 4) {
                $username = 'test_adv_' . $this->generateRandomString(8);
                $email = $username . '@test.com';
                $stmt = $this->executeQuery(
                    "INSERT INTO users (username, first_name, last_name, email, password_hash, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$username, 'Test', 'User' . count($this->testUserIds), $email, password_hash('test123', PASSWORD_DEFAULT), $testCompanyId, $testRoleId, 1],
                    'sssssiii'
                );
                $userId = $this->db->insert_id;
                $stmt->close();
                $this->testUserIds[] = $userId;
                $this->createdRecords['users'][] = $userId;
            }
            
            // Set creator ID
            $this->testCreatorId = $this->testUserIds[0];
            
            // Get existing LHOs or create test LHOs
            $result = $this->getResults("SELECT id FROM lhos WHERE status = 'active' LIMIT 5");
            
            if (!empty($result)) {
                foreach ($result as $row) {
                    $this->testLhoIds[] = (int)$row['id'];
                }
            }
            
            // If not enough LHOs, create some
            while (count($this->testLhoIds) < 3) {
                $lhoName = 'Test LHO ' . $this->generateRandomString(8);
                $stmt = $this->executeQuery(
                    "INSERT INTO lhos (lho_name, status, created_by) VALUES (?, ?, ?)",
                    [$lhoName, 'active', $this->testCreatorId],
                    'ssi'
                );
                $lhoId = $this->db->insert_id;
                $stmt->close();
                $this->testLhoIds[] = $lhoId;
                $this->createdRecords['lhos'][] = $lhoId;
            }
            
            echo "Setup complete: " . count($this->testLhoIds) . " LHOs, " . count($this->testUserIds) . " users\n";
            
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
            $this->db->query("DELETE FROM lho_managers WHERE lho_id IN (SELECT id FROM lhos WHERE lho_name LIKE 'Test LHO %')");
            $this->db->query("DELETE FROM lhos WHERE lho_name LIKE 'Test LHO %'");
            
            // Clean up created users
            if (isset($this->createdRecords['users']) && !empty($this->createdRecords['users'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['users']));
                $this->db->query("DELETE FROM users WHERE id IN ($ids)");
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

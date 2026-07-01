<?php
/**
 * Property Test for LHO Manager Assignment Round-Trip
 * **Feature: lho-manager-assignment, Property 1: Manager Assignment Round Trip**
 * **Validates: Requirements 1.3**
 * 
 * For any LHO and any set of valid ADV user IDs, saving the LHO with those manager 
 * assignments and then retrieving the LHO should return exactly the same set of manager IDs.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../repositories/LhoManagerRepository.php';

class LhoManagerRoundTripPropertyTest extends PropertyTestBase {
    
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
        echo "=== LHO Manager Round-Trip Property Tests ===\n\n";
        
        // Setup test data
        $this->setupTestData();
        
        $allPassed = true;
        
        // Test Manager Assignment Round-Trip
        $allPassed &= $this->runPropertyTest(
            "Manager Assignment Round-Trip Persistence",
            [$this, 'testManagerAssignmentRoundTrip']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 1: Manager Assignment Round Trip
     * For any LHO and any set of valid ADV user IDs, saving and retrieving 
     * should return exactly the same set of manager IDs.
     * **Feature: lho-manager-assignment, Property 1: Manager Assignment Round Trip**
     * **Validates: Requirements 1.3**
     */
    public function testManagerAssignmentRoundTrip() {
        try {
            // Ensure we have test data
            if (empty($this->testLhoIds) || empty($this->testUserIds)) {
                return [
                    'success' => false,
                    'message' => 'No test LHOs or users available'
                ];
            }
            
            // Pick a random LHO
            $lhoId = $this->generateRandomChoice($this->testLhoIds);
            
            // Generate a random subset of user IDs (0 to all users)
            $numManagers = rand(0, count($this->testUserIds));
            $shuffledUsers = $this->testUserIds;
            shuffle($shuffledUsers);
            $selectedUserIds = array_slice($shuffledUsers, 0, $numManagers);
            
            // Sort for consistent comparison
            sort($selectedUserIds);
            
            // Sync managers via repository
            $syncResult = $this->lhoManagerRepository->syncManagers(
                $lhoId, 
                $selectedUserIds, 
                $this->testCreatorId
            );
            
            $this->assert($syncResult === true, "Sync managers failed");
            
            // Retrieve manager IDs
            $retrievedIds = $this->lhoManagerRepository->getManagerIdsByLhoId($lhoId);
            
            // Convert to integers and sort for comparison
            $retrievedIds = array_map('intval', $retrievedIds);
            sort($retrievedIds);
            
            // Verify round-trip consistency
            $this->assert(
                count($retrievedIds) === count($selectedUserIds),
                "Manager count mismatch: expected " . count($selectedUserIds) . ", got " . count($retrievedIds)
            );
            
            $this->assert(
                $retrievedIds === $selectedUserIds,
                "Manager IDs mismatch: expected [" . implode(',', $selectedUserIds) . "], got [" . implode(',', $retrievedIds) . "]"
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
            
            // If not enough users, create some
            while (count($this->testUserIds) < 3) {
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

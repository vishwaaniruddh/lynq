<?php
/**
 * Property Test for User LHO Query Completeness
 * **Feature: lho-manager-assignment, Property 5: User LHO Query Completeness**
 * **Validates: Requirements 3.1, 3.2**
 * 
 * For any user assigned to N LHOs as manager, querying LHOs by that user 
 * should return exactly N LHO records.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../repositories/LhoManagerRepository.php';
require_once __DIR__ . '/../services/LocationService.php';

class UserLhoQueryCompletenessPropertyTest extends PropertyTestBase {
    
    private $lhoManagerRepository;
    private $locationService;
    private $createdRecords = [];
    private $testLhoIds = [];
    private $testUserIds = [];
    private $testCreatorId;
    
    public function __construct() {
        parent::__construct();
        $this->lhoManagerRepository = new LhoManagerRepository();
        $this->locationService = new LocationService();
        $this->iterations = 100; // As specified in design doc
    }
    
    public function runTests() {
        echo "=== User LHO Query Completeness Property Tests ===\n\n";
        
        // Setup test data
        $this->setupTestData();
        
        $allPassed = true;
        
        // Test User LHO Query Completeness
        $allPassed &= $this->runPropertyTest(
            "User LHO Query Completeness",
            [$this, 'testUserLhoQueryCompleteness']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 5: User LHO Query Completeness
     * For any user assigned to N LHOs as manager, querying LHOs by that user 
     * should return exactly N LHO records.
     * **Feature: lho-manager-assignment, Property 5: User LHO Query Completeness**
     * **Validates: Requirements 3.1, 3.2**
     */
    public function testUserLhoQueryCompleteness() {
        try {
            // Ensure we have test data
            if (empty($this->testLhoIds) || empty($this->testUserIds)) {
                return [
                    'success' => false,
                    'message' => 'No test LHOs or users available'
                ];
            }
            
            // Pick a random user
            $userId = $this->generateRandomChoice($this->testUserIds);
            
            // Generate a random subset of LHO IDs to assign to this user (0 to all LHOs)
            $numLhos = rand(0, count($this->testLhoIds));
            $shuffledLhos = $this->testLhoIds;
            shuffle($shuffledLhos);
            $selectedLhoIds = array_slice($shuffledLhos, 0, $numLhos);
            
            // Clear any existing assignments for this user first
            $this->lhoManagerRepository->removeAllByUserId($userId);
            
            // Assign user to selected LHOs
            foreach ($selectedLhoIds as $lhoId) {
                $this->lhoManagerRepository->syncManagers(
                    $lhoId,
                    [$userId],
                    $this->testCreatorId
                );
            }
            
            // Query LHOs by user using the service method
            $retrievedLhos = $this->locationService->getLhosByManager($userId);
            
            // Verify count matches
            $this->assert(
                count($retrievedLhos) === count($selectedLhoIds),
                "LHO count mismatch: expected " . count($selectedLhoIds) . ", got " . count($retrievedLhos)
            );
            
            // Verify all expected LHO IDs are present
            $retrievedLhoIds = array_map(function($lho) {
                return (int)$lho['lho_id'];
            }, $retrievedLhos);
            sort($retrievedLhoIds);
            
            $expectedLhoIds = array_map('intval', $selectedLhoIds);
            sort($expectedLhoIds);
            
            $this->assert(
                $retrievedLhoIds === $expectedLhoIds,
                "LHO IDs mismatch: expected [" . implode(',', $expectedLhoIds) . "], got [" . implode(',', $retrievedLhoIds) . "]"
            );
            
            // Verify each returned LHO has the expected fields (Requirements 3.1, 3.2)
            foreach ($retrievedLhos as $lho) {
                $this->assert(
                    isset($lho['lho_id']) && isset($lho['lho_name']),
                    "LHO record missing required fields (lho_id, lho_name)"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'user_id' => $userId ?? null,
                    'selected_lho_ids' => $selectedLhoIds ?? null,
                    'retrieved_count' => isset($retrievedLhos) ? count($retrievedLhos) : null
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
                $username = 'test_lho_query_' . $this->generateRandomString(8);
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
            while (count($this->testLhoIds) < 5) {
                $lhoName = 'Test LHO Query ' . $this->generateRandomString(8);
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
            
            // Clean up lho_managers for test users
            if (!empty($this->testUserIds)) {
                $ids = implode(',', array_map('intval', $this->testUserIds));
                $this->db->query("DELETE FROM lho_managers WHERE user_id IN ($ids)");
            }
            
            // Clean up created LHOs
            if (isset($this->createdRecords['lhos']) && !empty($this->createdRecords['lhos'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['lhos']));
                $this->db->query("DELETE FROM lhos WHERE id IN ($ids)");
            }
            
            // Clean up test LHOs by name pattern
            $this->db->query("DELETE FROM lho_managers WHERE lho_id IN (SELECT id FROM lhos WHERE lho_name LIKE 'Test LHO Query %')");
            $this->db->query("DELETE FROM lhos WHERE lho_name LIKE 'Test LHO Query %'");
            
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

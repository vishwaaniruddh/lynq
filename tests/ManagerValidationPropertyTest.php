<?php
/**
 * Property Test for Manager Validation
 * **Feature: lho-manager-assignment, Property 8: Manager Validation**
 * **Validates: Requirements 4.3, 4.4**
 * 
 * For any set of user IDs submitted as managers, only IDs corresponding to 
 * active ADV users should be accepted; all others should be rejected with 
 * appropriate errors.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/LocationService.php';

class ManagerValidationPropertyTest extends PropertyTestBase {
    
    private $locationService;
    private $createdRecords = [];
    private $testAdvCompanyId;
    private $testContractorCompanyId;
    private $testRoleId;
    
    public function __construct() {
        parent::__construct();
        $this->locationService = new LocationService();
        $this->iterations = 100; // As specified in design doc
    }
    
    public function runTests() {
        echo "=== Manager Validation Property Tests ===\n\n";
        
        // Setup test data
        $this->setupTestData();
        
        $allPassed = true;
        
        // Test valid ADV users are accepted
        $allPassed &= $this->runPropertyTest(
            "Valid Active ADV Users Are Accepted",
            [$this, 'testValidAdvUsersAccepted']
        );
        
        // Test invalid users are rejected
        $allPassed &= $this->runPropertyTest(
            "Invalid Users Are Rejected With Appropriate Errors",
            [$this, 'testInvalidUsersRejected']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 8: Manager Validation - Valid ADV Users Accepted
     * For any set of valid active ADV user IDs, validation should accept them.
     * **Feature: lho-manager-assignment, Property 8: Manager Validation**
     * **Validates: Requirements 4.3, 4.4**
     */
    public function testValidAdvUsersAccepted() {
        try {
            // Get active ADV users
            $sql = "SELECT u.id FROM users u
                    INNER JOIN companies c ON u.company_id = c.id
                    WHERE c.type = 'ADV' AND u.status = 1
                    LIMIT 10";
            
            $advUsers = $this->getResults($sql, [], '');
            
            if (empty($advUsers)) {
                // Skip if no ADV users exist
                return ['success' => true];
            }
            
            // Pick a random subset of ADV users
            $numUsers = rand(1, min(5, count($advUsers)));
            shuffle($advUsers);
            $selectedUsers = array_slice($advUsers, 0, $numUsers);
            $userIds = array_column($selectedUsers, 'id');
            
            // Validate the user IDs
            $result = $this->locationService->validateManagerAssignments($userIds);
            
            // All should be valid
            $this->assert(
                $result['valid'] === true,
                "Valid ADV users should be accepted, but validation failed: " . 
                json_encode($result['errors'])
            );
            
            // All IDs should be in valid_ids
            $this->assert(
                count($result['valid_ids']) === count($userIds),
                "All valid ADV user IDs should be returned in valid_ids"
            );
            
            // No errors should be present
            $this->assert(
                empty($result['errors']),
                "No errors should be present for valid ADV users"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['user_ids' => $userIds ?? null]
            ];
        }
    }
    
    /**
     * Property 8: Manager Validation - Invalid Users Rejected
     * For any set of invalid user IDs (non-existent, inactive, or non-ADV), 
     * validation should reject them with appropriate error codes.
     * **Feature: lho-manager-assignment, Property 8: Manager Validation**
     * **Validates: Requirements 4.3, 4.4**
     */
    public function testInvalidUsersRejected() {
        try {
            // Test case 1: Non-existent user ID
            $nonExistentId = 999999999;
            $result = $this->locationService->validateManagerAssignments([$nonExistentId]);
            
            $this->assert(
                $result['valid'] === false,
                "Non-existent user ID should be rejected"
            );
            
            $this->assert(
                !empty($result['errors']),
                "Errors should be present for non-existent user"
            );
            
            $errorCodes = array_column($result['errors'], 'code');
            $this->assert(
                in_array('INVALID_MANAGER', $errorCodes),
                "Error code should be INVALID_MANAGER for non-existent user"
            );
            
            // Test case 2: Inactive user (if exists)
            $sql = "SELECT u.id FROM users u
                    INNER JOIN companies c ON u.company_id = c.id
                    WHERE c.type = 'ADV' AND u.status = 0
                    LIMIT 1";
            
            $inactiveUsers = $this->getResults($sql, [], '');
            
            if (!empty($inactiveUsers)) {
                $inactiveUserId = (int)$inactiveUsers[0]['id'];
                $result = $this->locationService->validateManagerAssignments([$inactiveUserId]);
                
                $this->assert(
                    $result['valid'] === false,
                    "Inactive user should be rejected"
                );
                
                $errorCodes = array_column($result['errors'], 'code');
                $this->assert(
                    in_array('INACTIVE_USER', $errorCodes),
                    "Error code should be INACTIVE_USER for inactive user"
                );
            }
            
            // Test case 3: Non-ADV user (contractor user)
            $sql = "SELECT u.id FROM users u
                    INNER JOIN companies c ON u.company_id = c.id
                    WHERE c.type = 'CONTRACTOR' AND u.status = 1
                    LIMIT 1";
            
            $contractorUsers = $this->getResults($sql, [], '');
            
            if (!empty($contractorUsers)) {
                $contractorUserId = (int)$contractorUsers[0]['id'];
                $result = $this->locationService->validateManagerAssignments([$contractorUserId]);
                
                $this->assert(
                    $result['valid'] === false,
                    "Non-ADV (contractor) user should be rejected"
                );
                
                $errorCodes = array_column($result['errors'], 'code');
                $this->assert(
                    in_array('NON_ADV_USER', $errorCodes),
                    "Error code should be NON_ADV_USER for contractor user"
                );
            }
            
            // Test case 4: Invalid user ID (zero or negative)
            $result = $this->locationService->validateManagerAssignments([0, -1]);
            
            $this->assert(
                $result['valid'] === false,
                "Invalid user IDs (0, -1) should be rejected"
            );
            
            // Test case 5: Empty array should be valid
            $result = $this->locationService->validateManagerAssignments([]);
            
            $this->assert(
                $result['valid'] === true,
                "Empty array should be valid (no managers is allowed)"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Setup test data
     */
    private function setupTestData() {
        try {
            // Get or create ADV company
            $result = $this->getResults("SELECT id FROM companies WHERE type = 'ADV' LIMIT 1");
            if (!empty($result)) {
                $this->testAdvCompanyId = (int)$result[0]['id'];
            }
            
            // Get or create Contractor company
            $result = $this->getResults("SELECT id FROM companies WHERE type = 'CONTRACTOR' LIMIT 1");
            if (!empty($result)) {
                $this->testContractorCompanyId = (int)$result[0]['id'];
            }
            
            // Get a role ID
            $result = $this->getResults("SELECT id FROM roles LIMIT 1");
            if (!empty($result)) {
                $this->testRoleId = (int)$result[0]['id'];
            }
            
        } catch (Exception $e) {
            echo "Setup warning: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Clean up created users
            if (isset($this->createdRecords['users']) && !empty($this->createdRecords['users'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['users']));
                $this->db->query("DELETE FROM users WHERE id IN ($ids)");
            }
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

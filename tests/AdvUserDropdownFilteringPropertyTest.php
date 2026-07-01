<?php
/**
 * Property Test for ADV User Dropdown Filtering
 * **Feature: lho-manager-assignment, Property 11: ADV User Dropdown Filtering**
 * **Validates: Requirements 1.1**
 * 
 * For any set of users in the system, the manager dropdown should contain only 
 * users who are both active AND belong to an ADV company type.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/LocationService.php';

class AdvUserDropdownFilteringPropertyTest extends PropertyTestBase {
    
    private $locationService;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->locationService = new LocationService();
        $this->iterations = 100; // As specified in design doc
    }
    
    public function runTests() {
        echo "=== ADV User Dropdown Filtering Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test ADV User Dropdown Filtering
        $allPassed &= $this->runPropertyTest(
            "ADV User Dropdown Contains Only Active ADV Users",
            [$this, 'testAdvUserDropdownFiltering']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 11: ADV User Dropdown Filtering
     * For any set of users in the system, the manager dropdown should contain only 
     * users who are both active AND belong to an ADV company type.
     * **Feature: lho-manager-assignment, Property 11: ADV User Dropdown Filtering**
     * **Validates: Requirements 1.1**
     */
    public function testAdvUserDropdownFiltering() {
        try {
            // Get the dropdown users from LocationService
            $dropdownUsers = $this->locationService->getActiveAdvUsers();
            
            // For each user in the dropdown, verify they are active AND ADV
            foreach ($dropdownUsers as $user) {
                $userId = (int)$user['id'];
                
                // Query the database to verify user properties
                $sql = "SELECT u.id, u.status, c.type as company_type
                        FROM users u
                        INNER JOIN companies c ON u.company_id = c.id
                        WHERE u.id = ?";
                
                $result = $this->getResults($sql, [$userId], 'i');
                
                $this->assert(
                    !empty($result),
                    "User ID {$userId} from dropdown does not exist in database"
                );
                
                $dbUser = $result[0];
                
                // Verify user is active (status = 1)
                $this->assert(
                    (int)$dbUser['status'] === 1,
                    "User ID {$userId} in dropdown is not active (status = {$dbUser['status']})"
                );
                
                // Verify user belongs to ADV company
                $this->assert(
                    strtoupper($dbUser['company_type']) === 'ADV',
                    "User ID {$userId} in dropdown is not from ADV company (type = {$dbUser['company_type']})"
                );
            }
            
            // Also verify that NO active ADV users are missing from the dropdown
            $sql = "SELECT u.id FROM users u
                    INNER JOIN companies c ON u.company_id = c.id
                    WHERE c.type = 'ADV' AND u.status = 1";
            
            $allActiveAdvUsers = $this->getResults($sql, [], '');
            $dropdownUserIds = array_column($dropdownUsers, 'id');
            
            foreach ($allActiveAdvUsers as $advUser) {
                $this->assert(
                    in_array($advUser['id'], $dropdownUserIds),
                    "Active ADV user ID {$advUser['id']} is missing from dropdown"
                );
            }
            
            // Verify counts match
            $this->assert(
                count($dropdownUsers) === count($allActiveAdvUsers),
                "Dropdown count (" . count($dropdownUsers) . ") does not match active ADV user count (" . count($allActiveAdvUsers) . ")"
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
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        // No test data created in this test
    }
}

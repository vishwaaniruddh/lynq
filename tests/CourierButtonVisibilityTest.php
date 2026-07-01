<?php
/**
 * Property Test for Courier Permission-Based Button Visibility
 * **Feature: crm-sidebar-restructure, Property 10: Permission-Based Courier Button Visibility**
 * **Validates: Requirements 5.2**
 * 
 * For any user viewing the Courier page, action buttons (create, edit, delete) 
 * should only be visible if the user has the corresponding permission.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../middleware/MasterModuleMiddleware.php';
require_once __DIR__ . '/../services/PermissionEngine.php';

class CourierButtonVisibilityTest extends PropertyTestBase {
    
    private $createdRecords = [];
    private $masterMiddleware;
    private $permissionEngine;
    
    // Test user IDs
    private $advUserId = null;
    private $contractorUserId = null;
    private $advCompanyId = null;
    private $contractorCompanyId = null;
    
    // Courier actions
    private $actions = ['view', 'create', 'edit', 'delete'];
    
    public function __construct() {
        parent::__construct();
        $this->masterMiddleware = new MasterModuleMiddleware();
        $this->permissionEngine = new PermissionEngine();
    }
    
    public function runTests() {
        echo "=== Courier Permission-Based Button Visibility Property Tests ===\n\n";
        
        // Setup test users
        $this->setupTestUsers();
        
        $allPassed = true;
        
        // Test 1: getUserModulePermissions returns correct structure for couriers
        $allPassed &= $this->runPropertyTest(
            "Courier Permissions Structure Is Correct",
            [$this, 'testCourierPermissionStructure'],
            100
        );
        
        // Test 2: Permission values match actual permission checks for couriers
        $allPassed &= $this->runPropertyTest(
            "Courier Permission Values Match Actual Checks",
            [$this, 'testCourierPermissionValuesMatchChecks'],
            100
        );
        
        // Test 3: Non-ADV users get all false permissions for couriers
        $allPassed &= $this->runPropertyTest(
            "Non-ADV Users Get All False Courier Permissions",
            [$this, 'testNonAdvUsersGetFalseCourierPermissions'],
            100
        );
        
        // Test 4: Button visibility is deterministic for couriers
        $allPassed &= $this->runPropertyTest(
            "Courier Button Visibility Is Deterministic",
            [$this, 'testCourierButtonVisibilityDeterministic'],
            100
        );
        
        return $allPassed;
    }
    
    /**
     * Setup test users for property testing
     */
    private function setupTestUsers() {
        try {
            // Get or create ADV company
            $advCompany = $this->getOrCreateAdvCompany();
            $this->advCompanyId = $advCompany['id'];
            
            // Get or create Contractor company
            $contractorCompany = $this->getOrCreateContractorCompany();
            $this->contractorCompanyId = $contractorCompany['id'];
            
            // Get or create ADV user
            $advUser = $this->getOrCreateAdvUser($this->advCompanyId);
            $this->advUserId = $advUser['id'];
            
            // Get or create Contractor user
            $contractorUser = $this->getOrCreateContractorUser($this->contractorCompanyId);
            $this->contractorUserId = $contractorUser['id'];
            
            echo "Test users setup complete:\n";
            echo "  ADV User ID: {$this->advUserId}\n";
            echo "  Contractor User ID: {$this->contractorUserId}\n\n";
            
        } catch (Exception $e) {
            echo "Error setting up test users: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Property 10: Courier Permissions Structure Is Correct
     * For couriers module, getUserModulePermissions should return an array with 
     * view, create, edit, delete keys, all with boolean values
     */
    public function testCourierPermissionStructure() {
        try {
            // Get permissions for the couriers module
            $permissions = $this->masterMiddleware->getUserModulePermissions('couriers', $this->advUserId);
            
            // Verify structure
            $this->assert(
                is_array($permissions),
                "Courier permissions should be an array"
            );
            
            // Verify all required keys exist
            foreach ($this->actions as $action) {
                $this->assert(
                    array_key_exists($action, $permissions),
                    "Courier permissions should have '$action' key"
                );
                
                $this->assert(
                    is_bool($permissions[$action]),
                    "Courier permission '$action' should be a boolean, got " . gettype($permissions[$action])
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'permissions' => $permissions ?? null
                ]
            ];
        }
    }
    
    /**
     * Property 10: Courier Permission Values Match Actual Checks
     * For any user and courier action, the value returned by 
     * getUserModulePermissions should match the result of hasPermission
     */
    public function testCourierPermissionValuesMatchChecks() {
        try {
            // Pick a random action
            $action = $this->generateRandomChoice($this->actions);
            
            // Get permissions array
            $permissions = $this->masterMiddleware->getUserModulePermissions('couriers', $this->advUserId);
            
            // Get individual permission check
            $hasPermission = $this->masterMiddleware->hasPermission('couriers', $action, $this->advUserId);
            
            // Verify they match
            $this->assert(
                $permissions[$action] === $hasPermission,
                "getUserModulePermissions[$action] should match hasPermission result for couriers. " .
                "Got array value: " . ($permissions[$action] ? 'true' : 'false') . 
                ", hasPermission: " . ($hasPermission ? 'true' : 'false')
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'action' => $action ?? null,
                    'userId' => $this->advUserId
                ]
            ];
        }
    }
    
    /**
     * Property 10: Non-ADV Users Get All False Courier Permissions
     * For any non-ADV user, all courier permission values should be false
     */
    public function testNonAdvUsersGetFalseCourierPermissions() {
        try {
            // Pick a random action
            $action = $this->generateRandomChoice($this->actions);
            
            // Get permissions for the couriers module for contractor user
            $permissions = $this->masterMiddleware->getUserModulePermissions('couriers', $this->contractorUserId);
            
            // Verify the specific action permission is false
            $this->assert(
                $permissions[$action] === false,
                "Non-ADV user should have false for courier '$action' permission, got " . 
                ($permissions[$action] ? 'true' : 'false')
            );
            
            // Also verify via hasPermission
            $hasPermission = $this->masterMiddleware->hasPermission('couriers', $action, $this->contractorUserId);
            $this->assert(
                $hasPermission === false,
                "Non-ADV user hasPermission should return false for courier '$action'"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'action' => $action ?? null,
                    'permissions' => $permissions ?? null
                ]
            ];
        }
    }
    
    /**
     * Property 10: Courier Button Visibility Is Deterministic
     * For any user, calling getUserModulePermissions multiple times 
     * should return the same result
     */
    public function testCourierButtonVisibilityDeterministic() {
        try {
            // Pick a random action
            $action = $this->generateRandomChoice($this->actions);
            
            // Get permissions multiple times
            $permissions1 = $this->masterMiddleware->getUserModulePermissions('couriers', $this->advUserId);
            $permissions2 = $this->masterMiddleware->getUserModulePermissions('couriers', $this->advUserId);
            
            // Verify they match
            $this->assert(
                $permissions1[$action] === $permissions2[$action],
                "Courier permission check should be deterministic for '$action'. " .
                "First call: " . ($permissions1[$action] ? 'true' : 'false') . 
                ", Second call: " . ($permissions2[$action] ? 'true' : 'false')
            );
            
            // Also verify hasPermission is deterministic
            $hasPermission1 = $this->masterMiddleware->hasPermission('couriers', $action, $this->advUserId);
            $hasPermission2 = $this->masterMiddleware->hasPermission('couriers', $action, $this->advUserId);
            
            $this->assert(
                $hasPermission1 === $hasPermission2,
                "hasPermission should be deterministic for courier '$action'"
            );
            
            // Verify consistency between methods
            $this->assert(
                $permissions1[$action] === $hasPermission1,
                "getUserModulePermissions and hasPermission should return same value for courier '$action'"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'action' => $action ?? null
                ]
            ];
        }
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Get or create ADV company
     */
    private function getOrCreateAdvCompany() {
        $sql = "SELECT * FROM companies WHERE type = 'ADV' AND status = 'ACTIVE' LIMIT 1";
        $result = $this->db->query($sql);
        $company = $result->fetch_assoc();
        
        if ($company) {
            return $company;
        }
        
        $companyData = [
            'name' => 'Test ADV Company CourierBtn ' . $this->generateRandomString(6),
            'type' => 'ADV',
            'status' => 'ACTIVE'
        ];
        
        $sql = "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('sss', $companyData['name'], $companyData['type'], $companyData['status']);
        $stmt->execute();
        
        $companyId = $this->db->insert_id;
        $this->createdRecords['companies'][] = $companyId;
        
        return array_merge($companyData, ['id' => $companyId]);
    }
    
    /**
     * Get or create Contractor company
     */
    private function getOrCreateContractorCompany() {
        $sql = "SELECT * FROM companies WHERE type = 'CONTRACTOR' AND status = 'ACTIVE' LIMIT 1";
        $result = $this->db->query($sql);
        $company = $result->fetch_assoc();
        
        if ($company) {
            return $company;
        }
        
        $companyData = [
            'name' => 'Test Contractor Company CourierBtn ' . $this->generateRandomString(6),
            'type' => 'CONTRACTOR',
            'status' => 'ACTIVE'
        ];
        
        $sql = "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('sss', $companyData['name'], $companyData['type'], $companyData['status']);
        $stmt->execute();
        
        $companyId = $this->db->insert_id;
        $this->createdRecords['companies'][] = $companyId;
        
        return array_merge($companyData, ['id' => $companyId]);
    }
    
    /**
     * Get or create ADV user
     */
    private function getOrCreateAdvUser($companyId) {
        $sql = "SELECT u.* FROM users u 
                INNER JOIN companies c ON u.company_id = c.id 
                WHERE c.type = 'ADV' AND u.status = 1 
                LIMIT 1";
        $result = $this->db->query($sql);
        $user = $result->fetch_assoc();
        
        if ($user) {
            return $user;
        }
        
        $sql = "SELECT id FROM roles WHERE company_type IN ('ADV', 'BOTH') AND is_active = 1 LIMIT 1";
        $result = $this->db->query($sql);
        $role = $result->fetch_assoc();
        
        if (!$role) {
            throw new Exception("No ADV role found");
        }
        
        $userData = [
            'username' => 'test_adv_courierbtn_' . $this->generateRandomString(6),
            'email' => 'test_adv_courierbtn_' . $this->generateRandomString(6) . '@example.com',
            'password_hash' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'ADV CourierBtn User',
            'company_id' => $companyId,
            'role_id' => $role['id'],
            'status' => 1
        ];
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(
            'sssssiii',
            $userData['username'],
            $userData['email'],
            $userData['password_hash'],
            $userData['first_name'],
            $userData['last_name'],
            $userData['company_id'],
            $userData['role_id'],
            $userData['status']
        );
        $stmt->execute();
        
        $userId = $this->db->insert_id;
        $this->createdRecords['users'][] = $userId;
        
        return array_merge($userData, ['id' => $userId]);
    }
    
    /**
     * Get or create Contractor user
     */
    private function getOrCreateContractorUser($companyId) {
        $sql = "SELECT u.* FROM users u 
                INNER JOIN companies c ON u.company_id = c.id 
                WHERE c.type = 'CONTRACTOR' AND u.status = 1 
                LIMIT 1";
        $result = $this->db->query($sql);
        $user = $result->fetch_assoc();
        
        if ($user) {
            return $user;
        }
        
        $sql = "SELECT id FROM roles WHERE company_type IN ('CONTRACTOR', 'BOTH') AND is_active = 1 LIMIT 1";
        $result = $this->db->query($sql);
        $role = $result->fetch_assoc();
        
        if (!$role) {
            throw new Exception("No Contractor role found");
        }
        
        $userData = [
            'username' => 'test_contractor_courierbtn_' . $this->generateRandomString(6),
            'email' => 'test_contractor_courierbtn_' . $this->generateRandomString(6) . '@example.com',
            'password_hash' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'Contractor CourierBtn User',
            'company_id' => $companyId,
            'role_id' => $role['id'],
            'status' => 1
        ];
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(
            'sssssiii',
            $userData['username'],
            $userData['email'],
            $userData['password_hash'],
            $userData['first_name'],
            $userData['last_name'],
            $userData['company_id'],
            $userData['role_id'],
            $userData['status']
        );
        $stmt->execute();
        
        $userId = $this->db->insert_id;
        $this->createdRecords['users'][] = $userId;
        
        return array_merge($userData, ['id' => $userId]);
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            if (isset($this->createdRecords['users']) && !empty($this->createdRecords['users'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['users']));
                $this->db->query("DELETE FROM users WHERE id IN ($ids)");
            }
            
            if (isset($this->createdRecords['companies']) && !empty($this->createdRecords['companies'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['companies']));
                $this->db->query("DELETE FROM companies WHERE id IN ($ids)");
            }
            
            $this->db->query("DELETE FROM users WHERE username LIKE 'test_adv_courierbtn_%' OR username LIKE 'test_contractor_courierbtn_%'");
            $this->db->query("DELETE FROM companies WHERE name LIKE 'Test ADV Company CourierBtn %' OR name LIKE 'Test Contractor Company CourierBtn %'");
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

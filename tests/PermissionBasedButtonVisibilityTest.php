<?php
/**
 * Property Test for Permission-Based Button Visibility
 * **Feature: crm-master-modules, Property 11: Permission-Based Button Visibility**
 * **Validates: Requirements 8.3**
 * 
 * For any user viewing a master module page, action buttons (create, edit, delete) 
 * should only be visible if the user has the corresponding permission.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../middleware/MasterModuleMiddleware.php';
require_once __DIR__ . '/../services/PermissionEngine.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';

class PermissionBasedButtonVisibilityTest extends PropertyTestBase {
    
    private $createdRecords = [];
    private $masterMiddleware;
    private $permissionEngine;
    private $userRepository;
    
    // Test user IDs
    private $advUserId = null;
    private $advCompanyId = null;
    
    // Master modules to test
    private $modules = ['banks', 'customers', 'locations'];
    private $actions = ['view', 'create', 'edit', 'delete'];
    
    public function __construct() {
        parent::__construct();
        $this->masterMiddleware = new MasterModuleMiddleware();
        $this->permissionEngine = new PermissionEngine();
        $this->userRepository = new UserRepository();
    }
    
    public function runTests() {
        echo "=== Permission-Based Button Visibility Property Tests ===\n\n";
        
        // Setup test users
        $this->setupTestUsers();
        
        $allPassed = true;
        
        // Test 1: getUserModulePermissions returns correct structure
        $allPassed &= $this->runPropertyTest(
            "getUserModulePermissions Returns Correct Structure",
            [$this, 'testPermissionStructure'],
            50
        );
        
        // Test 2: Permission values match actual permission checks
        $allPassed &= $this->runPropertyTest(
            "Permission Values Match Actual Checks",
            [$this, 'testPermissionValuesMatchChecks'],
            100
        );
        
        // Test 3: Non-ADV users get all false permissions
        $allPassed &= $this->runPropertyTest(
            "Non-ADV Users Get All False Permissions",
            [$this, 'testNonAdvUsersGetFalsePermissions'],
            50
        );
        
        // Test 4: Button visibility consistency across modules
        $allPassed &= $this->runPropertyTest(
            "Button Visibility Consistency Across Modules",
            [$this, 'testButtonVisibilityConsistency'],
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
            
            // Get or create ADV user
            $advUser = $this->getOrCreateAdvUser($this->advCompanyId);
            $this->advUserId = $advUser['id'];
            
            echo "Test users setup complete:\n";
            echo "  ADV User ID: {$this->advUserId}\n\n";
            
        } catch (Exception $e) {
            echo "Error setting up test users: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Property 11: getUserModulePermissions Returns Correct Structure
     * For any module, getUserModulePermissions should return an array with 
     * view, create, edit, delete keys, all with boolean values
     */
    public function testPermissionStructure() {
        try {
            // Pick a random module
            $module = $this->generateRandomChoice($this->modules);
            
            // Get permissions for the module
            $permissions = $this->masterMiddleware->getUserModulePermissions($module, $this->advUserId);
            
            // Verify structure
            $this->assert(
                is_array($permissions),
                "Permissions should be an array"
            );
            
            // Verify all required keys exist
            foreach ($this->actions as $action) {
                $this->assert(
                    array_key_exists($action, $permissions),
                    "Permissions should have '$action' key"
                );
                
                $this->assert(
                    is_bool($permissions[$action]),
                    "Permission '$action' should be a boolean, got " . gettype($permissions[$action])
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'module' => $module ?? null,
                    'permissions' => $permissions ?? null
                ]
            ];
        }
    }
    
    /**
     * Property 11: Permission Values Match Actual Checks
     * For any user and module/action combination, the value returned by 
     * getUserModulePermissions should match the result of hasPermission
     */
    public function testPermissionValuesMatchChecks() {
        try {
            // Pick a random module and action
            $module = $this->generateRandomChoice($this->modules);
            $action = $this->generateRandomChoice($this->actions);
            
            // Get permissions array
            $permissions = $this->masterMiddleware->getUserModulePermissions($module, $this->advUserId);
            
            // Get individual permission check
            $hasPermission = $this->masterMiddleware->hasPermission($module, $action, $this->advUserId);
            
            // Verify they match
            $this->assert(
                $permissions[$action] === $hasPermission,
                "getUserModulePermissions[$action] should match hasPermission result. " .
                "Got array value: " . ($permissions[$action] ? 'true' : 'false') . 
                ", hasPermission: " . ($hasPermission ? 'true' : 'false')
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'module' => $module ?? null,
                    'action' => $action ?? null,
                    'userId' => $this->advUserId
                ]
            ];
        }
    }
    
    /**
     * Property 11: Non-ADV Users Get All False Permissions
     * For any non-ADV user and any module, all permission values should be false
     */
    public function testNonAdvUsersGetFalsePermissions() {
        try {
            // Get or create a contractor user
            $contractorCompany = $this->getOrCreateContractorCompany();
            $contractorUser = $this->getOrCreateContractorUser($contractorCompany['id']);
            
            // Pick a random module
            $module = $this->generateRandomChoice($this->modules);
            
            // Get permissions for the module
            $permissions = $this->masterMiddleware->getUserModulePermissions($module, $contractorUser['id']);
            
            // Verify all permissions are false
            foreach ($this->actions as $action) {
                $this->assert(
                    $permissions[$action] === false,
                    "Non-ADV user should have false for '$action' permission, got " . 
                    ($permissions[$action] ? 'true' : 'false')
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'module' => $module ?? null,
                    'permissions' => $permissions ?? null
                ]
            ];
        }
    }
    
    /**
     * Property 11: Button Visibility Consistency Across Modules
     * For any user, if they have a permission for one module, the permission 
     * check should be consistent (same user, same action, different modules 
     * should follow the same permission pattern based on role)
     */
    public function testButtonVisibilityConsistency() {
        try {
            // Pick a random action
            $action = $this->generateRandomChoice($this->actions);
            
            // Get permissions for all modules
            $permissionResults = [];
            foreach ($this->modules as $module) {
                $permissions = $this->masterMiddleware->getUserModulePermissions($module, $this->advUserId);
                $permissionResults[$module] = $permissions[$action];
            }
            
            // Verify that the permission check is deterministic
            // (calling it again should return the same result)
            foreach ($this->modules as $module) {
                $permissions = $this->masterMiddleware->getUserModulePermissions($module, $this->advUserId);
                
                $this->assert(
                    $permissions[$action] === $permissionResults[$module],
                    "Permission check should be deterministic for $module.$action"
                );
            }
            
            // Verify that hasPermission also returns consistent results
            foreach ($this->modules as $module) {
                $hasPermission = $this->masterMiddleware->hasPermission($module, $action, $this->advUserId);
                
                $this->assert(
                    $hasPermission === $permissionResults[$module],
                    "hasPermission should match getUserModulePermissions for $module.$action"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'action' => $action ?? null,
                    'permissionResults' => $permissionResults ?? null
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
            'name' => 'Test ADV Company ' . $this->generateRandomString(6),
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
            'name' => 'Test Contractor Company ' . $this->generateRandomString(6),
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
            'username' => 'test_adv_btn_' . $this->generateRandomString(6),
            'email' => 'test_adv_btn_' . $this->generateRandomString(6) . '@example.com',
            'password_hash' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'ADV User',
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
            'username' => 'test_contractor_btn_' . $this->generateRandomString(6),
            'email' => 'test_contractor_btn_' . $this->generateRandomString(6) . '@example.com',
            'password_hash' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'Contractor User',
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
            
            $this->db->query("DELETE FROM users WHERE username LIKE 'test_adv_btn_%' OR username LIKE 'test_contractor_btn_%'");
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

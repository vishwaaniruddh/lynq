<?php
/**
 * Property Test for Consistent Permission Checking
 * **Feature: adv-crm-users-module, Property 11: Consistent Permission Checking**
 * **Validates: Requirements 7.1, 7.3**
 */

require_once 'PropertyTestBase.php';

class ConsistentPermissionCheckingTest extends PropertyTestBase {
    private $permissionEngine;
    private $userModel;
    private $companyModel;
    private $permissionModel;
    private $testUsers = [];
    private $testCompanies = [];
    private $testPermissions = [];
    
    public function __construct() {
        parent::__construct();
        $this->permissionEngine = new PermissionEngine();
        $this->userModel = new User();
        $this->companyModel = new Company();
        $this->permissionModel = new Permission();
    }
    
    public function runTests() {
        echo "=== Consistent Permission Checking Tests ===\n";
        
        $this->setupTestData();
        
        $result = $this->runPropertyTest(
            "Consistent Permission Checking",
            [$this, 'testConsistentPermissionChecking']
        );
        
        $this->cleanupTestData();
        
        return $result;
    }
    
    /**
     * Property 11: Consistent Permission Checking
     * For any user action requiring authorization, the system should use the can() function 
     * to verify permissions before allowing the action
     */
    public function testConsistentPermissionChecking() {
        try {
            // Generate random test scenario
            $userType = $this->generateRandomChoice(['adv', 'contractor']);
            $user = $this->generateRandomChoice($this->testUsers[$userType]);
            $permission = $this->generateRandomChoice($this->testPermissions);
            
            // Test 1: Direct PermissionEngine.can() call
            $engineResult = $this->permissionEngine->can($user['id'], $permission['name']);
            
            // Test 2: Global can() function call (should use same engine)
            $globalResult = can($permission['name'], $user['id']);
            
            // Test 3: Permission model direct check (for comparison)
            $directRoleCheck = $this->permissionModel->userHasPermission($user['id'], $permission['name']);
            
            // For contractor users, also check company delegation
            $companyDelegationCheck = false;
            if ($userType === 'contractor') {
                $companyDelegationCheck = $this->permissionModel->companyHasPermission(
                    $user['company_id'], 
                    $permission['name']
                );
            }
            
            // Test 4: Multiple calls should return same result (consistency)
            $secondEngineCall = $this->permissionEngine->can($user['id'], $permission['name']);
            $secondGlobalCall = can($permission['name'], $user['id']);
            
            // Test 5: Permission checking logic consistency
            $expectedResult = $this->calculateExpectedPermissionResult(
                $user, 
                $permission, 
                $directRoleCheck, 
                $companyDelegationCheck
            );
            
            // Verify consistency across all methods
            $consistencyCheck = (
                $engineResult === $globalResult &&           // Engine and global function agree
                $engineResult === $secondEngineCall &&      // Multiple engine calls agree
                $globalResult === $secondGlobalCall &&      // Multiple global calls agree
                $engineResult === $expectedResult           // Result matches expected logic
            );
            
            if (!$consistencyCheck) {
                return [
                    'success' => false,
                    'message' => 'Inconsistent permission checking results',
                    'data' => [
                        'user_type' => $userType,
                        'user_id' => $user['id'],
                        'permission' => $permission['name'],
                        'engine_result' => $engineResult,
                        'global_result' => $globalResult,
                        'second_engine_call' => $secondEngineCall,
                        'second_global_call' => $secondGlobalCall,
                        'expected_result' => $expectedResult,
                        'direct_role_check' => $directRoleCheck,
                        'company_delegation_check' => $companyDelegationCheck
                    ]
                ];
            }
            
            // Test 6: Error handling consistency
            $invalidPermissionResult1 = $this->permissionEngine->can($user['id'], 'invalid.permission');
            $invalidPermissionResult2 = can('invalid.permission', $user['id']);
            $invalidUserResult1 = $this->permissionEngine->can(99999, $permission['name']);
            $invalidUserResult2 = can($permission['name'], 99999);
            
            $errorHandlingConsistent = (
                $invalidPermissionResult1 === false &&
                $invalidPermissionResult2 === false &&
                $invalidUserResult1 === false &&
                $invalidUserResult2 === false
            );
            
            if (!$errorHandlingConsistent) {
                return [
                    'success' => false,
                    'message' => 'Inconsistent error handling in permission checking',
                    'data' => [
                        'invalid_permission_engine' => $invalidPermissionResult1,
                        'invalid_permission_global' => $invalidPermissionResult2,
                        'invalid_user_engine' => $invalidUserResult1,
                        'invalid_user_global' => $invalidUserResult2
                    ]
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during consistent permission checking test: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate expected permission result based on business logic
     * Per Requirement 4.2: Contractor users must have permission through company delegation
     */
    private function calculateExpectedPermissionResult($user, $permission, $directRoleCheck, $companyDelegationCheck) {
        // ADV users: only need direct role permission
        if ($user['company_type'] === 'ADV') {
            return $directRoleCheck;
        }
        
        // Contractor users: need ONLY company delegation
        // Per Requirement 4.2: verify the permission exists in their company's delegated permissions
        if ($user['company_type'] === 'CONTRACTOR') {
            return $companyDelegationCheck;
        }
        
        return false;
    }
    
    private function setupTestData() {
        // Create test ADV company
        $advCompanyData = [
            'name' => 'Test ADV Company Consistent',
            'type' => 'ADV',
            'status' => 'ACTIVE'
        ];
        $advCompanyRecord = $this->companyModel->create($advCompanyData);
        $this->testCompanies['adv'] = $advCompanyRecord;
        
        // Create test contractor company
        $contractorData = [
            'name' => 'Test Contractor Consistent',
            'type' => 'CONTRACTOR',
            'status' => 'ACTIVE'
        ];
        $contractorRecord = $this->companyModel->create($contractorData);
        $this->testCompanies['contractor'] = $contractorRecord;
        
        // Create test ADV users
        $this->testUsers['adv'] = [];
        for ($i = 0; $i < 2; $i++) {
            $userData = [
                'username' => 'testadvcons' . $i . '_' . time() . '_' . mt_rand(1000, 9999),
                'email' => 'testadvcons' . $i . '_' . time() . '_' . mt_rand(1000, 9999) . '@test.com',
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'first_name' => 'Test',
                'last_name' => 'ADV Cons ' . $i,
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1, // Assume role 1 is ADV Super Admin
                'status' => 1
            ];
            $userRecord = $this->userModel->create($userData);
            $user = $this->userModel->findWithRelations($userRecord['id']);
            $this->testUsers['adv'][] = $user;
        }
        
        // Create test contractor users
        $this->testUsers['contractor'] = [];
        for ($i = 0; $i < 2; $i++) {
            $userData = [
                'username' => 'testcontractorcons' . $i . '_' . time() . '_' . mt_rand(1000, 9999),
                'email' => 'testcontractorcons' . $i . '_' . time() . '_' . mt_rand(1000, 9999) . '@test.com',
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'first_name' => 'Test',
                'last_name' => 'Contractor Cons ' . $i,
                'company_id' => $this->testCompanies['contractor']['id'],
                'role_id' => 2, // Assume role 2 is Contractor Admin
                'status' => 1
            ];
            $userRecord = $this->userModel->create($userData);
            $user = $this->userModel->findWithRelations($userRecord['id']);
            $this->testUsers['contractor'][] = $user;
        }
        
        // Get available permissions for testing
        $this->testPermissions = $this->permissionModel->findContractorAccessible();
        if (empty($this->testPermissions)) {
            // Create some test permissions if none exist
            $testPerms = [
                ['name' => 'test.view', 'module' => 'test', 'action' => 'view', 'description' => 'View test', 'is_adv_only' => 0],
                ['name' => 'test.create', 'module' => 'test', 'action' => 'create', 'description' => 'Create test', 'is_adv_only' => 0],
                ['name' => 'test.edit', 'module' => 'test', 'action' => 'edit', 'description' => 'Edit test', 'is_adv_only' => 0]
            ];
            
            foreach ($testPerms as $perm) {
                $permId = $this->permissionModel->create($perm);
                $this->testPermissions[] = $this->permissionModel->find($permId);
            }
        }
        
        // Randomly delegate some permissions to contractor company for testing
        $advUser = $this->testUsers['adv'][0];
        $numPermissionsToDelegate = rand(1, min(2, count($this->testPermissions)));
        $permissionsToDelegate = array_slice($this->testPermissions, 0, $numPermissionsToDelegate);
        
        foreach ($permissionsToDelegate as $permission) {
            try {
                $this->permissionEngine->delegatePermission(
                    $this->testCompanies['contractor']['id'],
                    $permission['name'],
                    $advUser['id']
                );
            } catch (Exception $e) {
                // Ignore delegation errors during setup
            }
        }
    }
    
    protected function cleanupTestData() {
        // Clean up in correct order to avoid foreign key constraints
        
        // First, clean up delegation records and audit logs (they reference users and companies)
        if (isset($this->testCompanies['contractor'])) {
            $this->executeQuery("DELETE FROM company_permissions WHERE company_id = ?", [$this->testCompanies['contractor']['id']], 'i');
            $this->executeQuery("DELETE FROM permission_audit_log WHERE company_id = ?", [$this->testCompanies['contractor']['id']], 'i');
        }
        
        // Clean up test users
        if (!empty($this->testUsers['adv'])) {
            foreach ($this->testUsers['adv'] as $user) {
                $this->executeQuery("DELETE FROM users WHERE id = ?", [$user['id']], 'i');
            }
        }
        
        if (!empty($this->testUsers['contractor'])) {
            foreach ($this->testUsers['contractor'] as $user) {
                $this->executeQuery("DELETE FROM users WHERE id = ?", [$user['id']], 'i');
            }
        }
        
        // Clean up test companies
        if (isset($this->testCompanies['adv'])) {
            $this->executeQuery("DELETE FROM companies WHERE id = ?", [$this->testCompanies['adv']['id']], 'i');
        }
        
        if (isset($this->testCompanies['contractor'])) {
            $this->executeQuery("DELETE FROM companies WHERE id = ?", [$this->testCompanies['contractor']['id']], 'i');
        }
        
        // Clean up test permissions (if we created them)
        $this->executeQuery("DELETE FROM permissions WHERE name LIKE 'test.%' AND description LIKE '%test%'");
    }
}

// Run the test if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ConsistentPermissionCheckingTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}
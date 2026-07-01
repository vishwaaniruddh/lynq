<?php
/**
 * Property Test for Permission Revocation Immediate Effect
 * **Feature: adv-crm-users-module, Property 7: Permission Revocation Immediate Effect**
 * **Validates: Requirements 4.3**
 */

require_once 'PropertyTestBase.php';

class PermissionRevocationTest extends PropertyTestBase {
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
        echo "=== Permission Revocation Immediate Effect Tests ===\n";
        
        $this->setupTestData();
        
        $result = $this->runPropertyTest(
            "Permission Revocation Immediate Effect",
            [$this, 'testPermissionRevocationImmediateEffect']
        );
        
        $this->cleanupTestData();
        
        return $result;
    }
    
    /**
     * Property 7: Permission Revocation Immediate Effect
     * For any permission that is revoked from a contractor company, 
     * all users from that company should immediately lose access to functions requiring that permission
     */
    public function testPermissionRevocationImmediateEffect() {
        try {
            // Generate random test scenario
            $contractorCompany = $this->generateRandomChoice($this->testCompanies['contractors']);
            $permission = $this->generateRandomChoice($this->testPermissions);
            $advUser = $this->generateRandomChoice($this->testUsers['adv']);
            
            // Create multiple contractor users for this company
            $contractorUsers = [];
            $numUsers = rand(2, 4);
            for ($i = 0; $i < $numUsers; $i++) {
                $contractorUsers[] = $this->createContractorUser($contractorCompany['id']);
            }
            
            // Step 1: Delegate permission to contractor company
            $delegationResult = $this->permissionEngine->delegatePermission(
                $contractorCompany['id'], 
                $permission['name'], 
                $advUser['id']
            );
            
            if (!$delegationResult) {
                throw new Exception("Failed to delegate permission for test setup");
            }
            
            // Step 2: Verify all contractor users have the permission
            $allUsersHavePermissionBefore = true;
            foreach ($contractorUsers as $user) {
                if (!$this->permissionEngine->can($user['id'], $permission['name'])) {
                    $allUsersHavePermissionBefore = false;
                    break;
                }
            }
            
            // Step 3: Revoke the permission from the company
            $revocationResult = $this->permissionEngine->revokePermission(
                $contractorCompany['id'], 
                $permission['name'], 
                $advUser['id']
            );
            
            // Step 4: Verify ALL contractor users immediately lose the permission
            $allUsersLostPermissionAfter = true;
            foreach ($contractorUsers as $user) {
                if ($this->permissionEngine->can($user['id'], $permission['name'])) {
                    $allUsersLostPermissionAfter = false;
                    break;
                }
            }
            
            // Step 5: Verify company no longer has the delegated permission
            $companyLostPermission = !$this->permissionModel->companyHasPermission(
                $contractorCompany['id'], 
                $permission['name']
            );
            
            // Verify the property holds
            $propertyHolds = (
                $allUsersHavePermissionBefore &&  // All users had permission before revocation
                $revocationResult &&               // Revocation should succeed
                $allUsersLostPermissionAfter &&   // All users should lose permission immediately
                $companyLostPermission             // Company should no longer have delegated permission
            );
            
            if (!$propertyHolds) {
                return [
                    'success' => false,
                    'message' => 'Permission revocation immediate effect failed',
                    'data' => [
                        'contractor_company' => $contractorCompany['name'],
                        'permission' => $permission['name'],
                        'num_users' => count($contractorUsers),
                        'all_users_had_permission_before' => $allUsersHavePermissionBefore,
                        'revocation_result' => $revocationResult,
                        'all_users_lost_permission_after' => $allUsersLostPermissionAfter,
                        'company_lost_permission' => $companyLostPermission
                    ]
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during permission revocation test: ' . $e->getMessage()
            ];
        }
    }
    
    private function setupTestData() {
        // Create test ADV company if not exists
        $advCompanyData = [
            'name' => 'Test ADV Company Rev',
            'type' => 'ADV',
            'status' => 'ACTIVE'
        ];
        $advCompanyRecord = $this->companyModel->create($advCompanyData);
        $this->testCompanies['adv'] = $advCompanyRecord;
        
        // Create test contractor companies
        $this->testCompanies['contractors'] = [];
        for ($i = 0; $i < 3; $i++) {
            $contractorData = [
                'name' => 'Test Contractor Rev ' . ($i + 1),
                'type' => 'CONTRACTOR',
                'status' => 'ACTIVE'
            ];
            $contractorRecord = $this->companyModel->create($contractorData);
            $this->testCompanies['contractors'][] = $contractorRecord;
        }
        
        // Create test ADV users
        $this->testUsers['adv'] = [];
        for ($i = 0; $i < 2; $i++) {
            $userData = [
                'username' => 'testadvrev' . $i . '_' . time() . '_' . mt_rand(1000, 9999),
                'email' => 'testadvrev' . $i . '_' . time() . '_' . mt_rand(1000, 9999) . '@test.com',
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'first_name' => 'Test',
                'last_name' => 'ADV Rev ' . $i,
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1, // Assume role 1 is ADV Super Admin
                'status' => 1
            ];
            $userRecord = $this->userModel->create($userData);
            $this->testUsers['adv'][] = $this->userModel->findWithRelations($userRecord['id']);
        }
        
        // Get available permissions for testing
        $this->testPermissions = $this->permissionModel->findContractorAccessible();
        if (empty($this->testPermissions)) {
            // Create some test permissions if none exist
            $testPerms = [
                ['name' => 'users.edit', 'module' => 'users', 'action' => 'edit', 'description' => 'Edit users', 'is_adv_only' => 0],
                ['name' => 'users.delete', 'module' => 'users', 'action' => 'delete', 'description' => 'Delete users', 'is_adv_only' => 0],
                ['name' => 'reports.create', 'module' => 'reports', 'action' => 'create', 'description' => 'Create reports', 'is_adv_only' => 0]
            ];
            
            foreach ($testPerms as $perm) {
                $permId = $this->permissionModel->create($perm);
                $this->testPermissions[] = $this->permissionModel->find($permId);
            }
        }
    }
    
    private function createContractorUser($companyId) {
        $uniqueId = uniqid('', true) . '_' . mt_rand(100000, 999999);
        $userData = [
            'username' => 'testcontractorrev_' . $uniqueId,
            'email' => 'testcontractorrev_' . $uniqueId . '@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'Contractor Rev',
            'company_id' => $companyId,
            'role_id' => 2, // Assume role 2 is Contractor Admin
            'status' => 1
        ];
        $userRecord = $this->userModel->create($userData);
        return $this->userModel->findWithRelations($userRecord['id']);
    }
    
    protected function cleanupTestData() {
        // Clean up in correct order to avoid foreign key constraints
        
        // First, clean up delegation records and audit logs (they reference users and companies)
        if (!empty($this->testCompanies['contractors'])) {
            foreach ($this->testCompanies['contractors'] as $company) {
                $this->executeQuery("DELETE FROM company_permissions WHERE company_id = ?", [$company['id']], 'i');
                $this->executeQuery("DELETE FROM permission_audit_log WHERE company_id = ?", [$company['id']], 'i');
            }
        }
        
        // Clean up contractor users (created during test)
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testcontractorrev_%'");
        
        // Clean up test ADV users
        if (!empty($this->testUsers['adv'])) {
            foreach ($this->testUsers['adv'] as $user) {
                $this->executeQuery("DELETE FROM users WHERE id = ?", [$user['id']], 'i');
            }
        }
        
        // Clean up test companies
        if (isset($this->testCompanies['adv'])) {
            $this->executeQuery("DELETE FROM companies WHERE id = ?", [$this->testCompanies['adv']['id']], 'i');
        }
        
        if (!empty($this->testCompanies['contractors'])) {
            foreach ($this->testCompanies['contractors'] as $company) {
                $this->executeQuery("DELETE FROM companies WHERE id = ?", [$company['id']], 'i');
            }
        }
        
        // Clean up test permissions (if we created them)
        $this->executeQuery("DELETE FROM permissions WHERE name IN ('users.edit', 'users.delete', 'reports.create') AND description LIKE '%test%'");
    }
}

// Run the test if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $test = new PermissionRevocationTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}
<?php
/**
 * Property Test for Permission Delegation Verification
 * **Feature: adv-crm-users-module, Property 6: Permission Delegation Verification**
 * **Validates: Requirements 4.2, 4.4**
 */

require_once 'PropertyTestBase.php';

class PermissionDelegationTest extends PropertyTestBase {
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
        echo "=== Permission Delegation Verification Tests ===\n";
        
        $this->setupTestData();
        
        $result = $this->runPropertyTest(
            "Permission Delegation Verification",
            [$this, 'testPermissionDelegationVerification']
        );
        
        $this->cleanupTestData();
        
        return $result;
    }
    
    /**
     * Property 6: Permission Delegation Verification
     * For any contractor user attempting an action, they should only succeed 
     * if their company has been explicitly granted that permission by ADV
     */
    public function testPermissionDelegationVerification() {
        try {
            // Generate random test scenario
            $contractorCompany = $this->generateRandomChoice($this->testCompanies['contractors']);
            $advUser = $this->generateRandomChoice($this->testUsers['adv']);
            
            // Create a contractor user
            $contractorUser = $this->createContractorUser($contractorCompany['id']);
            
            // Create a unique permission that definitely doesn't exist yet
            $uniqueId = time() . '_' . rand(10000, 99999);
            $uniquePermName = 'test.delegate_' . $uniqueId;
            $permData = [
                'name' => $uniquePermName,
                'module' => 'test',
                'action' => 'delegate_' . $uniqueId,
                'description' => 'Test delegation permission',
                'is_adv_only' => 0
            ];
            $permRecord = $this->permissionModel->create($permData);
            $permissionToTest = $this->permissionModel->find($permRecord['id']);
            
            // Test 1: Contractor user should NOT have permission before delegation
            $hasPermissionBefore = $this->permissionEngine->can($contractorUser['id'], $permissionToTest['name']);
            
            // Test 2: Company should not have delegated permission initially
            $companyHasPermissionBefore = $this->permissionModel->companyHasPermission(
                $contractorCompany['id'], 
                $permissionToTest['name']
            );
            
            // Test 3: Delegate permission from ADV to contractor company
            $delegationResult = $this->permissionEngine->delegatePermission(
                $contractorCompany['id'], 
                $permissionToTest['name'], 
                $advUser['id']
            );
            
            // Test 4: Contractor user should NOW have permission after delegation
            $hasPermissionAfter = $this->permissionEngine->can($contractorUser['id'], $permissionToTest['name']);
            
            // Test 5: Verify delegation is recorded in company_permissions
            $companyHasPermissionAfter = $this->permissionModel->companyHasPermission(
                $contractorCompany['id'], 
                $permissionToTest['name']
            );
            
            // Verify the property holds
            $propertyHolds = (
                !$hasPermissionBefore &&           // Should not have permission initially
                !$companyHasPermissionBefore &&    // Company should not have permission initially
                $delegationResult &&                // Delegation should succeed
                $hasPermissionAfter &&              // Should have permission after delegation
                $companyHasPermissionAfter          // Company should have delegated permission
            );
            
            if (!$propertyHolds) {
                return [
                    'success' => false,
                    'message' => 'Permission delegation verification failed',
                    'data' => [
                        'contractor_company' => $contractorCompany['name'],
                        'permission' => $permissionToTest['name'],
                        'has_permission_before' => $hasPermissionBefore,
                        'company_has_permission_before' => $companyHasPermissionBefore,
                        'delegation_result' => $delegationResult,
                        'has_permission_after' => $hasPermissionAfter,
                        'company_has_permission_after' => $companyHasPermissionAfter
                    ]
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during permission delegation test: ' . $e->getMessage()
            ];
        }
    }
    
    private function setupTestData() {
        // Use existing ADV company instead of creating new one
        $existingAdvCompany = $this->companyModel->findByType('ADV');
        if (!empty($existingAdvCompany)) {
            $this->testCompanies['adv'] = $existingAdvCompany[0];
        } else {
            // Create test ADV company if none exists
            $advCompanyData = [
                'name' => 'Test ADV Company',
                'type' => 'ADV',
                'status' => 'ACTIVE'
            ];
            $advCompanyId = $this->companyModel->create($advCompanyData);
            $this->testCompanies['adv'] = $this->companyModel->find($advCompanyId);
        }
        
        // Create test contractor companies
        $this->testCompanies['contractors'] = [];
        for ($i = 0; $i < 3; $i++) {
            $contractorData = [
                'name' => 'Test Contractor Del ' . ($i + 1) . '_' . time(),
                'type' => 'CONTRACTOR',
                'status' => 'ACTIVE'
            ];
            $companyRecord = $this->companyModel->create($contractorData);
            $this->testCompanies['contractors'][] = $this->companyModel->find($companyRecord['id']);
        }
        
        // Create test ADV users
        $this->testUsers['adv'] = [];
        for ($i = 0; $i < 2; $i++) {
            $userData = [
                'username' => 'testadvdel' . $i . '_' . time(),
                'email' => 'testadvdel' . $i . '_' . time() . '@test.com',
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'first_name' => 'Test',
                'last_name' => 'ADV Del ' . $i,
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1, // Super Admin role
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
                ['name' => 'users.view_del', 'module' => 'users', 'action' => 'view_del', 'description' => 'View users del test', 'is_adv_only' => 0],
                ['name' => 'users.create_del', 'module' => 'users', 'action' => 'create_del', 'description' => 'Create users del test', 'is_adv_only' => 0],
                ['name' => 'reports.view_del', 'module' => 'reports', 'action' => 'view_del', 'description' => 'View reports del test', 'is_adv_only' => 0]
            ];
            
            foreach ($testPerms as $perm) {
                $permRecord = $this->permissionModel->create($perm);
                $this->testPermissions[] = $this->permissionModel->find($permRecord['id']);
            }
        }
    }
    
    private function createContractorUser($companyId) {
        $uniqueId = uniqid('', true) . '_' . mt_rand(100000, 999999);
        $userData = [
            'username' => 'testcontractordel_' . $uniqueId,
            'email' => 'testcontractordel_' . $uniqueId . '@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'Contractor Del',
            'company_id' => $companyId,
            'role_id' => 5, // Contractor Admin role
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
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testcontractordel_%'");
        
        // Clean up test ADV users
        if (!empty($this->testUsers['adv'])) {
            foreach ($this->testUsers['adv'] as $user) {
                $this->executeQuery("DELETE FROM users WHERE id = ?", [$user['id']], 'i');
            }
        }
        
        // Clean up test companies (only the ones we created)
        if (!empty($this->testCompanies['contractors'])) {
            foreach ($this->testCompanies['contractors'] as $company) {
                $this->executeQuery("DELETE FROM companies WHERE id = ?", [$company['id']], 'i');
            }
        }
        
        // Don't delete the ADV company if we used an existing one
        if (isset($this->testCompanies['adv']) && strpos($this->testCompanies['adv']['name'], 'Test ADV Company') !== false) {
            $this->executeQuery("DELETE FROM companies WHERE id = ?", [$this->testCompanies['adv']['id']], 'i');
        }
        
        // Clean up test permissions (if we created them)
        $this->executeQuery("DELETE FROM permissions WHERE name LIKE '%_del' AND description LIKE '%del test%'");
        $this->executeQuery("DELETE FROM permissions WHERE name LIKE 'test.delegate_%' AND description = 'Test delegation permission'");
    }
}

// Run the test if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $test = new PermissionDelegationTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}
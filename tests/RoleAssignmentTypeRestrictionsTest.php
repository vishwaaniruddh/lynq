<?php
/**
 * Property Test for Role Assignment Type Restrictions
 * **Feature: adv-crm-users-module, Property 3: Role Assignment Type Restrictions**
 * **Validates: Requirements 1.5, 2.3, 2.5**
 * 
 * For any user role assignment, ADV roles should never be assigned to contractor users, 
 * and contractor roles should never be assigned to ADV users.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/RoleService.php';
require_once __DIR__ . '/../services/UserService.php';

class RoleAssignmentTypeRestrictionsTest extends PropertyTestBase {
    private $roleService;
    private $userService;
    private $roleModel;
    private $companyModel;
    private $userModel;
    private $testCompanies = [];
    private $testUsers = [];
    private $createdUsers = [];
    
    public function __construct() {
        parent::__construct();
        $this->roleService = new RoleService();
        $this->userService = new UserService();
        $this->roleModel = new Role();
        $this->companyModel = new Company();
        $this->userModel = new User();
    }
    
    public function runTests() {
        echo "=== Role Assignment Type Restrictions Tests ===\n";
        
        $this->setupTestData();
        
        $results = [];
        
        // Test 1: ADV roles should never be assigned to contractor users
        $results[] = $this->runPropertyTest(
            "ADV roles cannot be assigned to contractor users",
            [$this, 'testAdvRolesCannotBeAssignedToContractorUsers']
        );
        
        // Test 2: Contractor roles should never be assigned to ADV users
        $results[] = $this->runPropertyTest(
            "Contractor roles cannot be assigned to ADV users",
            [$this, 'testContractorRolesCannotBeAssignedToAdvUsers']
        );
        
        // Test 3: BOTH type roles can be assigned to any user
        $results[] = $this->runPropertyTest(
            "BOTH type roles can be assigned to any user",
            [$this, 'testBothTypeRolesCanBeAssignedToAnyUser']
        );
        
        // Test 4: Valid role assignments succeed
        $results[] = $this->runPropertyTest(
            "Valid role assignments succeed",
            [$this, 'testValidRoleAssignmentsSucceed']
        );
        
        $this->cleanupTestData();
        
        return !in_array(false, $results);
    }
    
    /**
     * Property 3a: ADV roles cannot be assigned to contractor users
     * For any ADV-only role and any contractor company, attempting to assign
     * the role to a user in that company should fail.
     */
    public function testAdvRolesCannotBeAssignedToContractorUsers() {
        try {
            // Get ADV-only roles
            $advOnlyRoles = $this->roleService->getAdvOnlyRoles();
            if (empty($advOnlyRoles)) {
                return [
                    'success' => true,
                    'message' => 'No ADV-only roles to test'
                ];
            }
            
            // Randomly select an ADV-only role
            $advRole = $this->generateRandomChoice($advOnlyRoles);
            
            // Randomly select a contractor company
            if (empty($this->testCompanies['contractors'])) {
                return [
                    'success' => false,
                    'message' => 'No contractor companies available for testing'
                ];
            }
            $contractorCompany = $this->generateRandomChoice($this->testCompanies['contractors']);
            
            // Validate that this role assignment should fail
            $validation = $this->roleService->validateRoleAssignment($advRole['id'], $contractorCompany['id']);
            
            if ($validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'ADV role was incorrectly allowed to be assigned to contractor company',
                    'data' => [
                        'role' => $advRole['name'],
                        'role_type' => $advRole['company_type'],
                        'company' => $contractorCompany['name'],
                        'company_type' => $contractorCompany['type']
                    ]
                ];
            }
            
            // Also verify through UserService that user creation would fail
            $advUser = $this->generateRandomChoice($this->testUsers['adv']);
            $timestamp = time() . '_' . $this->generateRandomInt(1000, 9999);
            
            $userData = [
                'username' => 'testprop3a_' . $timestamp,
                'email' => 'testprop3a_' . $timestamp . '@test.com',
                'password' => 'TestPassword123!',
                'first_name' => 'Test',
                'last_name' => 'Property3a',
                'company_id' => $contractorCompany['id'],
                'role_id' => $advRole['id'],
                'status' => 1
            ];
            
            try {
                $createdUser = $this->userService->createUser($userData, $advUser['id']);
                $this->createdUsers[] = $createdUser['id'];
                
                // If we get here, the assignment succeeded when it shouldn't have
                return [
                    'success' => false,
                    'message' => 'User creation with ADV role in contractor company should have failed',
                    'data' => [
                        'role' => $advRole['name'],
                        'role_type' => $advRole['company_type'],
                        'company' => $contractorCompany['name'],
                        'company_type' => $contractorCompany['type']
                    ]
                ];
            } catch (InvalidArgumentException $e) {
                // Expected - role assignment should be rejected
                if (strpos($e->getMessage(), 'ADV roles cannot be assigned') !== false) {
                    return ['success' => true];
                }
                // Some other validation error
                return [
                    'success' => false,
                    'message' => 'Unexpected error: ' . $e->getMessage()
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during test: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 3b: Contractor roles cannot be assigned to ADV users
     * For any contractor-only role and any ADV company, attempting to assign
     * the role to a user in that company should fail.
     */
    public function testContractorRolesCannotBeAssignedToAdvUsers() {
        try {
            // Get contractor-only roles
            $contractorOnlyRoles = $this->roleService->getContractorOnlyRoles();
            if (empty($contractorOnlyRoles)) {
                return [
                    'success' => true,
                    'message' => 'No contractor-only roles to test'
                ];
            }
            
            // Randomly select a contractor-only role
            $contractorRole = $this->generateRandomChoice($contractorOnlyRoles);
            
            // Use the ADV company
            $advCompany = $this->testCompanies['adv'];
            
            // Validate that this role assignment should fail
            $validation = $this->roleService->validateRoleAssignment($contractorRole['id'], $advCompany['id']);
            
            if ($validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Contractor role was incorrectly allowed to be assigned to ADV company',
                    'data' => [
                        'role' => $contractorRole['name'],
                        'role_type' => $contractorRole['company_type'],
                        'company' => $advCompany['name'],
                        'company_type' => $advCompany['type']
                    ]
                ];
            }
            
            // Also verify through UserService that user creation would fail
            $advUser = $this->generateRandomChoice($this->testUsers['adv']);
            $timestamp = time() . '_' . $this->generateRandomInt(1000, 9999);
            
            $userData = [
                'username' => 'testprop3b_' . $timestamp,
                'email' => 'testprop3b_' . $timestamp . '@test.com',
                'password' => 'TestPassword123!',
                'first_name' => 'Test',
                'last_name' => 'Property3b',
                'company_id' => $advCompany['id'],
                'role_id' => $contractorRole['id'],
                'status' => 1
            ];
            
            try {
                $createdUser = $this->userService->createUser($userData, $advUser['id']);
                $this->createdUsers[] = $createdUser['id'];
                
                // If we get here, the assignment succeeded when it shouldn't have
                return [
                    'success' => false,
                    'message' => 'User creation with contractor role in ADV company should have failed',
                    'data' => [
                        'role' => $contractorRole['name'],
                        'role_type' => $contractorRole['company_type'],
                        'company' => $advCompany['name'],
                        'company_type' => $advCompany['type']
                    ]
                ];
            } catch (InvalidArgumentException $e) {
                // Expected - role assignment should be rejected
                if (strpos($e->getMessage(), 'Contractor roles cannot be assigned') !== false) {
                    return ['success' => true];
                }
                // Some other validation error
                return [
                    'success' => false,
                    'message' => 'Unexpected error: ' . $e->getMessage()
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during test: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 3c: BOTH type roles can be assigned to any user
     * For any role with company_type='BOTH', it should be assignable to users
     * in both ADV and contractor companies.
     */
    public function testBothTypeRolesCanBeAssignedToAnyUser() {
        try {
            // Get BOTH type roles
            $bothRoles = $this->getBothTypeRoles();
            if (empty($bothRoles)) {
                return [
                    'success' => true,
                    'message' => 'No BOTH type roles to test'
                ];
            }
            
            // Randomly select a BOTH type role
            $bothRole = $this->generateRandomChoice($bothRoles);
            
            // Randomly select any company (ADV or contractor)
            $allCompanies = array_merge(
                [$this->testCompanies['adv']], 
                $this->testCompanies['contractors']
            );
            $targetCompany = $this->generateRandomChoice($allCompanies);
            
            // Validate that this role assignment should succeed
            $validation = $this->roleService->validateRoleAssignment($bothRole['id'], $targetCompany['id']);
            
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'BOTH type role was incorrectly rejected for assignment',
                    'data' => [
                        'role' => $bothRole['name'],
                        'role_type' => $bothRole['company_type'],
                        'company' => $targetCompany['name'],
                        'company_type' => $targetCompany['type'],
                        'validation_message' => $validation['message']
                    ]
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during test: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 3d: Valid role assignments succeed
     * For any role and company where the role type matches the company type,
     * the assignment should succeed.
     */
    public function testValidRoleAssignmentsSucceed() {
        try {
            // Randomly decide to test ADV or contractor
            $testAdv = $this->generateRandomBool();
            
            if ($testAdv) {
                // Test ADV role with ADV company
                $advRoles = $this->roleService->getAdvOnlyRoles();
                if (empty($advRoles)) {
                    return ['success' => true, 'message' => 'No ADV roles to test'];
                }
                $role = $this->generateRandomChoice($advRoles);
                $company = $this->testCompanies['adv'];
            } else {
                // Test contractor role with contractor company
                $contractorRoles = $this->roleService->getContractorOnlyRoles();
                if (empty($contractorRoles)) {
                    return ['success' => true, 'message' => 'No contractor roles to test'];
                }
                $role = $this->generateRandomChoice($contractorRoles);
                
                if (empty($this->testCompanies['contractors'])) {
                    return ['success' => true, 'message' => 'No contractor companies to test'];
                }
                $company = $this->generateRandomChoice($this->testCompanies['contractors']);
            }
            
            // Validate that this role assignment should succeed
            $validation = $this->roleService->validateRoleAssignment($role['id'], $company['id']);
            
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Valid role assignment was incorrectly rejected',
                    'data' => [
                        'role' => $role['name'],
                        'role_type' => $role['company_type'],
                        'company' => $company['name'],
                        'company_type' => $company['type'],
                        'validation_message' => $validation['message']
                    ]
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during test: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get roles with company_type = 'BOTH'
     */
    private function getBothTypeRoles() {
        $sql = "SELECT * FROM roles WHERE company_type = 'BOTH' AND is_active = 1";
        return $this->getResults($sql);
    }
    
    private function setupTestData() {
        $timestamp = time();
        
        // Get or create ADV company
        $existingAdvCompany = $this->companyModel->findByType('ADV');
        if (!empty($existingAdvCompany)) {
            $this->testCompanies['adv'] = $existingAdvCompany[0];
        } else {
            $advCompanyData = [
                'name' => "Test ADV Company Prop3 $timestamp",
                'type' => 'ADV',
                'status' => 'ACTIVE'
            ];
            $advCompanyRecord = $this->companyModel->create($advCompanyData);
            $this->testCompanies['adv'] = $this->companyModel->find($advCompanyRecord['id']);
        }
        
        // Create contractor companies
        $this->testCompanies['contractors'] = [];
        for ($i = 0; $i < 2; $i++) {
            $contractorData = [
                'name' => "Test Contractor Prop3 {$i}_{$timestamp}",
                'type' => 'CONTRACTOR',
                'status' => 'ACTIVE'
            ];
            $companyRecord = $this->companyModel->create($contractorData);
            $this->testCompanies['contractors'][] = $this->companyModel->find($companyRecord['id']);
        }
        
        // Create ADV users for testing
        $this->testUsers['adv'] = [];
        $userData = [
            'username' => "testadvprop3_{$timestamp}",
            'email' => "testadvprop3_{$timestamp}@test.com",
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'ADV Prop3',
            'company_id' => $this->testCompanies['adv']['id'],
            'role_id' => 1, // Super Admin role
            'status' => 1
        ];
        $userRecord = $this->userModel->create($userData);
        $this->testUsers['adv'][] = $this->userModel->findWithRelations($userRecord['id']);
    }
    
    protected function cleanupTestData() {
        // Clean up created users from property tests
        foreach ($this->createdUsers as $userId) {
            $this->executeQuery("DELETE FROM users WHERE id = ?", [$userId], 'i');
        }
        
        // Clean up test ADV users
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testadvprop3%'");
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testprop3%'");
        
        // Clean up contractor companies
        if (!empty($this->testCompanies['contractors'])) {
            foreach ($this->testCompanies['contractors'] as $company) {
                $this->executeQuery("DELETE FROM company_access_log WHERE target_company_id = ?", [$company['id']], 'i');
                $this->executeQuery("DELETE FROM companies WHERE id = ?", [$company['id']], 'i');
            }
        }
        
        // Don't delete the ADV company if we used an existing one
        if (isset($this->testCompanies['adv']) && strpos($this->testCompanies['adv']['name'], 'Test ADV Company Prop3') !== false) {
            $this->executeQuery("DELETE FROM company_access_log WHERE target_company_id = ?", [$this->testCompanies['adv']['id']], 'i');
            $this->executeQuery("DELETE FROM companies WHERE id = ?", [$this->testCompanies['adv']['id']], 'i');
        }
    }
}

// Run the test if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $test = new RoleAssignmentTypeRestrictionsTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}

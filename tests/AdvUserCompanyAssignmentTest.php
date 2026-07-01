<?php
/**
 * Property Test for ADV User Company Assignment Freedom
 * **Feature: adv-crm-users-module, Property 1: ADV User Company Assignment Freedom**
 * **Validates: Requirements 1.1**
 * 
 * For any ADV Super Admin user and any company in the system, the user should be able 
 * to assign new users to that company without restriction.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../services/CompanyIsolationService.php';

class AdvUserCompanyAssignmentTest extends PropertyTestBase {
    private $userService;
    private $companyIsolationService;
    private $userModel;
    private $companyModel;
    private $roleModel;
    private $testUsers = [];
    private $testCompanies = [];
    private $createdUsers = [];
    
    public function __construct() {
        parent::__construct();
        $this->userService = new UserService();
        $this->companyIsolationService = new CompanyIsolationService();
        $this->userModel = new User();
        $this->companyModel = new Company();
        $this->roleModel = new Role();
    }
    
    public function runTests() {
        echo "=== ADV User Company Assignment Freedom Tests ===\n";
        
        $this->setupTestData();
        
        $result = $this->runPropertyTest(
            "ADV User Company Assignment Freedom",
            [$this, 'testAdvUserCompanyAssignmentFreedom']
        );
        
        $this->cleanupTestData();
        
        return $result;
    }
    
    /**
     * Property 1: ADV User Company Assignment Freedom
     * For any ADV Super Admin user and any company in the system, the user should be able 
     * to assign new users to that company without restriction.
     */
    public function testAdvUserCompanyAssignmentFreedom() {
        try {
            // Randomly select an ADV user
            $advUser = $this->generateRandomChoice($this->testUsers['adv']);
            
            // Randomly select any company (ADV or contractor)
            $allCompanies = array_merge(
                [$this->testCompanies['adv']], 
                $this->testCompanies['contractors']
            );
            $targetCompany = $this->generateRandomChoice($allCompanies);
            
            // Get appropriate role for the target company
            $availableRoles = $this->roleModel->findByCompanyType($targetCompany['type']);
            if (empty($availableRoles)) {
                return [
                    'success' => false,
                    'message' => 'No roles available for company type: ' . $targetCompany['type'],
                    'data' => ['company_type' => $targetCompany['type']]
                ];
            }
            $role = $this->generateRandomChoice($availableRoles);
            
            // Generate random user data
            $timestamp = time() . '_' . $this->generateRandomInt(1000, 9999) . '_' . mt_rand(10000, 99999);
            $userData = [
                'username' => 'testprop1_' . $timestamp,
                'email' => 'testprop1_' . $timestamp . '@test.com',
                'password' => 'TestPassword123!',
                'first_name' => 'Test',
                'last_name' => 'Property1',
                'company_id' => $targetCompany['id'],
                'role_id' => $role['id'],
                'status' => 1
            ];
            
            // ADV user should be able to create user in any company
            try {
                $createdUser = $this->userService->createUser($userData, $advUser['id']);
                $this->createdUsers[] = $createdUser['id'];
                
                // Verify user was created in the correct company
                if ((int)$createdUser['company_id'] !== (int)$targetCompany['id']) {
                    return [
                        'success' => false,
                        'message' => 'User was not created in the target company',
                        'data' => [
                            'adv_user' => $advUser['username'],
                            'target_company' => $targetCompany['name'],
                            'expected_company_id' => $targetCompany['id'],
                            'actual_company_id' => $createdUser['company_id']
                        ]
                    ];
                }
                
                return ['success' => true];
                
            } catch (CompanyAccessDeniedException $e) {
                // ADV user should never get company access denied
                return [
                    'success' => false,
                    'message' => 'ADV user was denied access to create user in company',
                    'data' => [
                        'adv_user' => $advUser['username'],
                        'target_company' => $targetCompany['name'],
                        'target_company_type' => $targetCompany['type'],
                        'error' => $e->getMessage()
                    ]
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during ADV user company assignment test: ' . $e->getMessage()
            ];
        }
    }
    
    private function setupTestData() {
        $timestamp = time();
        
        // Get or create ADV company
        $existingAdvCompany = $this->companyModel->findByType('ADV');
        if (!empty($existingAdvCompany)) {
            $this->testCompanies['adv'] = $existingAdvCompany[0];
        } else {
            $advCompanyData = [
                'name' => "Test ADV Company Prop1 $timestamp",
                'type' => 'ADV',
                'status' => 'ACTIVE'
            ];
            $advCompanyRecord = $this->companyModel->create($advCompanyData);
            $this->testCompanies['adv'] = $this->companyModel->find($advCompanyRecord['id']);
        }
        
        // Create multiple contractor companies
        $this->testCompanies['contractors'] = [];
        for ($i = 0; $i < 3; $i++) {
            $contractorData = [
                'name' => "Test Contractor Prop1 {$i}_{$timestamp}",
                'type' => 'CONTRACTOR',
                'status' => 'ACTIVE'
            ];
            $companyRecord = $this->companyModel->create($contractorData);
            $this->testCompanies['contractors'][] = $this->companyModel->find($companyRecord['id']);
        }
        
        // Create ADV users (Super Admins)
        $this->testUsers['adv'] = [];
        for ($i = 0; $i < 2; $i++) {
            $userData = [
                'username' => "testadvprop1{$i}_{$timestamp}",
                'email' => "testadvprop1{$i}_{$timestamp}@test.com",
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'first_name' => 'Test',
                'last_name' => "ADV Prop1 $i",
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1, // Super Admin role
                'status' => 1
            ];
            $userRecord = $this->userModel->create($userData);
            $this->testUsers['adv'][] = $this->userModel->findWithRelations($userRecord['id']);
        }
    }
    
    protected function cleanupTestData() {
        // Clean up created users from property tests
        foreach ($this->createdUsers as $userId) {
            $this->executeQuery("DELETE FROM users WHERE id = ?", [$userId], 'i');
        }
        
        // Clean up test ADV users
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testadvprop1%'");
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testprop1_%'");
        
        // Clean up contractor companies
        if (!empty($this->testCompanies['contractors'])) {
            foreach ($this->testCompanies['contractors'] as $company) {
                $this->executeQuery("DELETE FROM company_access_log WHERE target_company_id = ?", [$company['id']], 'i');
                $this->executeQuery("DELETE FROM companies WHERE id = ?", [$company['id']], 'i');
            }
        }
        
        // Don't delete the ADV company if we used an existing one
        if (isset($this->testCompanies['adv']) && strpos($this->testCompanies['adv']['name'], 'Test ADV Company Prop1') !== false) {
            $this->executeQuery("DELETE FROM company_access_log WHERE target_company_id = ?", [$this->testCompanies['adv']['id']], 'i');
            $this->executeQuery("DELETE FROM companies WHERE id = ?", [$this->testCompanies['adv']['id']], 'i');
        }
    }
}

// Run the test if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $test = new AdvUserCompanyAssignmentTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}

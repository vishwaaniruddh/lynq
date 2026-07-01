<?php
/**
 * Property Test for Contractor Company Scope Restriction
 * **Feature: adv-crm-users-module, Property 4: Contractor Company Scope Restriction**
 * **Validates: Requirements 2.1, 2.2**
 * 
 * For any contractor user attempting to create or access users, 
 * they should only succeed for users within their own company.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/CompanyIsolationService.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';

class ContractorScopeRestrictionTest extends PropertyTestBase {
    private $companyIsolationService;
    private $userRepository;
    private $companyRepository;
    private $userModel;
    private $companyModel;
    private $testUsers = [];
    private $testCompanies = [];
    
    public function __construct() {
        parent::__construct();
        $this->companyIsolationService = new CompanyIsolationService();
        $this->userRepository = new UserRepository();
        $this->companyRepository = new CompanyRepository();
        $this->userModel = new User();
        $this->companyModel = new Company();
    }
    
    public function runTests() {
        echo "=== Contractor Company Scope Restriction Tests ===\n";
        
        $this->setupTestData();
        
        $result = $this->runPropertyTest(
            "Contractor Company Scope Restriction",
            [$this, 'testContractorScopeRestriction']
        );
        
        $this->cleanupTestData();
        
        return $result;
    }
    
    /**
     * Property 4: Contractor Company Scope Restriction
     * For any contractor user attempting to create or access users, 
     * they should only succeed for users within their own company.
     */
    public function testContractorScopeRestriction() {
        try {
            // Select a random contractor user
            $contractorUser = $this->generateRandomChoice($this->testUsers['contractors']);
            $contractorCompanyId = (int)$contractorUser['company_id'];
            
            // Select a random target company (could be same or different)
            $allCompanies = array_merge(
                [$this->testCompanies['adv']], 
                $this->testCompanies['contractors']
            );
            $targetCompany = $this->generateRandomChoice($allCompanies);
            $targetCompanyId = (int)$targetCompany['id'];
            
            // Test 1: Contractor should only be able to access their own company
            $canAccess = $this->companyIsolationService->canAccessCompany(
                $contractorUser['id'], 
                $targetCompanyId
            );
            
            $shouldHaveAccess = $contractorCompanyId === $targetCompanyId;
            
            if ($canAccess !== $shouldHaveAccess) {
                return [
                    'success' => false,
                    'message' => 'Contractor access check failed',
                    'data' => [
                        'contractor_user' => $contractorUser['username'],
                        'contractor_company_id' => $contractorCompanyId,
                        'target_company_id' => $targetCompanyId,
                        'can_access' => $canAccess,
                        'should_have_access' => $shouldHaveAccess
                    ]
                ];
            }
            
            // Test 2: Contractor should only be able to create users in their own company
            $this->userRepository->setCurrentUser($contractorUser['id']);
            
            $newUserData = [
                'username' => 'testcreate_' . time() . '_' . rand(10000, 99999),
                'email' => 'testcreate_' . time() . '_' . rand(10000, 99999) . '@test.com',
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'first_name' => 'Test',
                'last_name' => 'Create',
                'company_id' => $targetCompanyId,
                'role_id' => 5, // Contractor Admin role
                'status' => 1
            ];
            
            $creationSucceeded = false;
            $creationDenied = false;
            $createdUserId = null;
            
            try {
                $result = $this->userRepository->createWithValidation($newUserData, $contractorUser['id']);
                $creationSucceeded = true;
                $createdUserId = $result['id'];
            } catch (CompanyAccessDeniedException $e) {
                $creationDenied = true;
            } catch (Exception $e) {
                // Other exceptions are unexpected
                return [
                    'success' => false,
                    'message' => 'Unexpected exception during user creation: ' . $e->getMessage()
                ];
            }
            
            // Clean up created user if any
            if ($createdUserId) {
                $this->executeQuery("DELETE FROM users WHERE id = ?", [$createdUserId], 'i');
            }
            
            // Verify the property: creation should succeed only for own company
            if ($shouldHaveAccess && !$creationSucceeded) {
                return [
                    'success' => false,
                    'message' => 'Contractor should be able to create user in own company but was denied',
                    'data' => [
                        'contractor_user' => $contractorUser['username'],
                        'contractor_company_id' => $contractorCompanyId,
                        'target_company_id' => $targetCompanyId
                    ]
                ];
            }
            
            if (!$shouldHaveAccess && !$creationDenied) {
                return [
                    'success' => false,
                    'message' => 'Contractor should NOT be able to create user in different company but succeeded',
                    'data' => [
                        'contractor_user' => $contractorUser['username'],
                        'contractor_company_id' => $contractorCompanyId,
                        'target_company_id' => $targetCompanyId
                    ]
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during contractor scope restriction test: ' . $e->getMessage()
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
                'name' => "Test ADV Company Scope $timestamp",
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
                'name' => "Test Contractor Scope {$i}_{$timestamp}",
                'type' => 'CONTRACTOR',
                'status' => 'ACTIVE'
            ];
            $companyRecord = $this->companyModel->create($contractorData);
            $this->testCompanies['contractors'][] = $this->companyModel->find($companyRecord['id']);
        }
        
        // Create ADV users
        $this->testUsers['adv'] = [];
        for ($i = 0; $i < 2; $i++) {
            $userData = [
                'username' => "testadvscope{$i}_{$timestamp}",
                'email' => "testadvscope{$i}_{$timestamp}@test.com",
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'first_name' => 'Test',
                'last_name' => "ADV Scope $i",
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => 1, // Super Admin role
                'status' => 1
            ];
            $userRecord = $this->userModel->create($userData);
            $this->testUsers['adv'][] = $this->userModel->findWithRelations($userRecord['id']);
        }
        
        // Create contractor users in each contractor company
        $this->testUsers['contractors'] = [];
        foreach ($this->testCompanies['contractors'] as $index => $company) {
            for ($i = 0; $i < 2; $i++) {
                $userData = [
                    'username' => "testcontractorscope{$index}_{$i}_{$timestamp}",
                    'email' => "testcontractorscope{$index}_{$i}_{$timestamp}@test.com",
                    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                    'first_name' => 'Test',
                    'last_name' => "Contractor Scope {$index}_{$i}",
                    'company_id' => $company['id'],
                    'role_id' => 5, // Contractor Admin role
                    'status' => 1
                ];
                $userRecord = $this->userModel->create($userData);
                $this->testUsers['contractors'][] = $this->userModel->findWithRelations($userRecord['id']);
            }
        }
    }
    
    protected function cleanupTestData() {
        // Clean up test users
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testadvscope%'");
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testcontractorscope%'");
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testcreate_%'");
        
        // Clean up contractor companies
        if (!empty($this->testCompanies['contractors'])) {
            foreach ($this->testCompanies['contractors'] as $company) {
                $this->executeQuery("DELETE FROM company_access_log WHERE target_company_id = ?", [$company['id']], 'i');
                $this->executeQuery("DELETE FROM companies WHERE id = ?", [$company['id']], 'i');
            }
        }
        
        // Don't delete the ADV company if we used an existing one
        if (isset($this->testCompanies['adv']) && strpos($this->testCompanies['adv']['name'], 'Test ADV Company Scope') !== false) {
            $this->executeQuery("DELETE FROM company_access_log WHERE target_company_id = ?", [$this->testCompanies['adv']['id']], 'i');
            $this->executeQuery("DELETE FROM companies WHERE id = ?", [$this->testCompanies['adv']['id']], 'i');
        }
    }
}

// Run the test if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ContractorScopeRestrictionTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}

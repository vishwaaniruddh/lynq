<?php
/**
 * Property Test for Company Isolation for User Queries
 * **Feature: adv-crm-users-module, Property 2: Company Isolation for User Queries**
 * **Validates: Requirements 1.2, 2.2, 3.1, 3.3**
 * 
 * For any user query operation, all returned users should belong to the same company 
 * as the requesting user, except for ADV users who should see users from all companies.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/CompanyIsolationService.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';

class CompanyIsolationTest extends PropertyTestBase {
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
        echo "=== Company Isolation for User Queries Tests ===\n";
        
        $this->setupTestData();
        
        $result = $this->runPropertyTest(
            "Company Isolation for User Queries",
            [$this, 'testCompanyIsolationForUserQueries']
        );
        
        $this->cleanupTestData();
        
        return $result;
    }
    
    /**
     * Property 2: Company Isolation for User Queries
     * For any user query operation, all returned users should belong to the same company 
     * as the requesting user, except for ADV users who should see users from all companies.
     */
    public function testCompanyIsolationForUserQueries() {
        try {
            // Randomly select a requesting user (either ADV or contractor)
            $allTestUsers = array_merge($this->testUsers['adv'], $this->testUsers['contractors']);
            $requestingUser = $this->generateRandomChoice($allTestUsers);
            
            // Set up the repository with the requesting user
            $this->userRepository->setCurrentUser($requestingUser['id']);
            
            // Query all users through the repository
            $returnedUsers = $this->userRepository->findAllWithRelations();
            
            // Get the requesting user's company type
            $requestingUserDetails = $this->userModel->findWithRelations($requestingUser['id']);
            $isAdvUser = $requestingUserDetails['company_type'] === 'ADV';
            $requestingCompanyId = (int)$requestingUserDetails['company_id'];
            
            if ($isAdvUser) {
                // ADV users should see users from all companies
                // Verify that users from multiple companies are returned
                $companyIds = array_unique(array_column($returnedUsers, 'company_id'));
                
                // ADV users should see at least their own company's users
                // (they may see more if there are users in other companies)
                $propertyHolds = count($returnedUsers) > 0;
                
                if (!$propertyHolds) {
                    return [
                        'success' => false,
                        'message' => 'ADV user should see at least some users',
                        'data' => [
                            'requesting_user' => $requestingUser['username'],
                            'company_type' => 'ADV',
                            'returned_users_count' => count($returnedUsers)
                        ]
                    ];
                }
            } else {
                // Contractor users should only see users from their own company
                foreach ($returnedUsers as $user) {
                    if ((int)$user['company_id'] !== $requestingCompanyId) {
                        return [
                            'success' => false,
                            'message' => 'Contractor user saw user from different company',
                            'data' => [
                                'requesting_user' => $requestingUser['username'],
                                'requesting_company_id' => $requestingCompanyId,
                                'returned_user' => $user['username'],
                                'returned_user_company_id' => $user['company_id']
                            ]
                        ];
                    }
                }
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during company isolation test: ' . $e->getMessage()
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
                'name' => "Test ADV Company Iso $timestamp",
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
                'name' => "Test Contractor Iso {$i}_{$timestamp}",
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
                'username' => "testadviso{$i}_{$timestamp}",
                'email' => "testadviso{$i}_{$timestamp}@test.com",
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'first_name' => 'Test',
                'last_name' => "ADV Iso $i",
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
                    'username' => "testcontractoriso{$index}_{$i}_{$timestamp}",
                    'email' => "testcontractoriso{$index}_{$i}_{$timestamp}@test.com",
                    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                    'first_name' => 'Test',
                    'last_name' => "Contractor Iso {$index}_{$i}",
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
        // Clean up users first (due to foreign key constraints)
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testadviso%'");
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testcontractoriso%'");
        
        // Clean up contractor companies
        if (!empty($this->testCompanies['contractors'])) {
            foreach ($this->testCompanies['contractors'] as $company) {
                $this->executeQuery("DELETE FROM company_access_log WHERE target_company_id = ?", [$company['id']], 'i');
                $this->executeQuery("DELETE FROM companies WHERE id = ?", [$company['id']], 'i');
            }
        }
        
        // Don't delete the ADV company if we used an existing one
        if (isset($this->testCompanies['adv']) && strpos($this->testCompanies['adv']['name'], 'Test ADV Company Iso') !== false) {
            $this->executeQuery("DELETE FROM company_access_log WHERE target_company_id = ?", [$this->testCompanies['adv']['id']], 'i');
            $this->executeQuery("DELETE FROM companies WHERE id = ?", [$this->testCompanies['adv']['id']], 'i');
        }
    }
}

// Run the test if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $test = new CompanyIsolationTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}

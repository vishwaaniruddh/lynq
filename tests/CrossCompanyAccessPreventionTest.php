<?php
/**
 * Property Test for Cross-Company Access Prevention
 * **Feature: adv-crm-users-module, Property 13: Cross-Company Access Prevention**
 * **Validates: Requirements 3.2, 3.5**
 * 
 * For any user attempting to access data from another company, the system should 
 * deny the request and log the attempt unless explicitly authorized through delegation.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/CompanyIsolationService.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';

class CrossCompanyAccessPreventionTest extends PropertyTestBase {
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
        echo "=== Cross-Company Access Prevention Tests ===\n";
        
        $this->setupTestData();
        
        $result = $this->runPropertyTest(
            "Cross-Company Access Prevention",
            [$this, 'testCrossCompanyAccessPrevention']
        );
        
        $this->cleanupTestData();
        
        return $result;
    }
    
    /**
     * Property 13: Cross-Company Access Prevention
     * For any user attempting to access data from another company, the system should 
     * deny the request and log the attempt unless explicitly authorized through delegation.
     */
    public function testCrossCompanyAccessPrevention() {
        try {
            // Select a random contractor user
            $contractorUser = $this->generateRandomChoice($this->testUsers['contractors']);
            $contractorCompanyId = (int)$contractorUser['company_id'];
            
            // Select a different company (not the contractor's company)
            $otherCompanies = array_filter(
                $this->testCompanies['contractors'],
                function($company) use ($contractorCompanyId) {
                    return (int)$company['id'] !== $contractorCompanyId;
                }
            );
            
            if (empty($otherCompanies)) {
                // If no other contractor companies, use ADV company
                $targetCompany = $this->testCompanies['adv'];
            } else {
                $targetCompany = $this->generateRandomChoice(array_values($otherCompanies));
            }
            
            $targetCompanyId = (int)$targetCompany['id'];
            
            // Get the max log ID before our test
            $maxLogIdBefore = $this->getMaxAccessLogId();
            
            // Test 1: Attempt cross-company access (should be denied)
            $canAccess = $this->companyIsolationService->canAccessCompany(
                $contractorUser['id'], 
                $targetCompanyId
            );
            
            if ($canAccess) {
                return [
                    'success' => false,
                    'message' => 'Cross-company access should be denied but was allowed',
                    'data' => [
                        'contractor_user' => $contractorUser['username'],
                        'contractor_company_id' => $contractorCompanyId,
                        'target_company_id' => $targetCompanyId
                    ]
                ];
            }
            
            // Test 2: Verify the access attempt was logged (get log entry created after our test started)
            $newLogEntry = $this->getAccessLogAfter($maxLogIdBefore, $contractorUser['id'], $targetCompanyId);
            
            if (!$newLogEntry) {
                return [
                    'success' => false,
                    'message' => 'Cross-company access attempt should be logged but was not',
                    'data' => [
                        'contractor_user' => $contractorUser['username'],
                        'contractor_user_id' => $contractorUser['id'],
                        'target_company_id' => $targetCompanyId,
                        'max_log_id_before' => $maxLogIdBefore
                    ]
                ];
            }
            
            // Test 3: Verify the log entry has correct details
            if ($newLogEntry['access_result'] !== 'DENIED') {
                return [
                    'success' => false,
                    'message' => 'Access log should show DENIED result',
                    'data' => [
                        'log_result' => $newLogEntry['access_result']
                    ]
                ];
            }
            
            if ((int)$newLogEntry['user_id'] !== (int)$contractorUser['id']) {
                return [
                    'success' => false,
                    'message' => 'Access log should record correct user ID',
                    'data' => [
                        'expected_user_id' => $contractorUser['id'],
                        'logged_user_id' => $newLogEntry['user_id']
                    ]
                ];
            }
            
            // Test 4: Attempt to access user data from another company via repository
            $this->userRepository->setCurrentUser($contractorUser['id']);
            
            // Try to find a user from the target company
            $targetCompanyUsers = $this->getDirectUsersFromCompany($targetCompanyId);
            
            if (!empty($targetCompanyUsers)) {
                $targetUser = $targetCompanyUsers[0];
                
                // Try to access this user through the repository
                $accessedUser = $this->userRepository->find($targetUser['id']);
                
                // Should return null because of company isolation
                if ($accessedUser !== null) {
                    return [
                        'success' => false,
                        'message' => 'Contractor should not be able to access user from different company via repository',
                        'data' => [
                            'contractor_user' => $contractorUser['username'],
                            'target_user' => $targetUser['username'],
                            'target_company_id' => $targetCompanyId
                        ]
                    ];
                }
            }
            
            // Test 5: Verify validateCompanyAccess throws exception for cross-company access
            $exceptionThrown = false;
            try {
                $this->companyIsolationService->validateCompanyAccess(
                    $contractorUser['id'], 
                    $targetCompanyId
                );
            } catch (CompanyAccessDeniedException $e) {
                $exceptionThrown = true;
            }
            
            if (!$exceptionThrown) {
                return [
                    'success' => false,
                    'message' => 'validateCompanyAccess should throw exception for cross-company access',
                    'data' => [
                        'contractor_user' => $contractorUser['username'],
                        'target_company_id' => $targetCompanyId
                    ]
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during cross-company access prevention test: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get max access log ID
     */
    private function getMaxAccessLogId() {
        $result = $this->getResults(
            "SELECT MAX(id) as max_id FROM company_access_log"
        );
        return $result[0]['max_id'] ?? 0;
    }
    
    /**
     * Get access log entry created after a specific ID for a specific user and company
     */
    private function getAccessLogAfter($afterId, $userId, $companyId) {
        $result = $this->getResults(
            "SELECT * FROM company_access_log WHERE id > ? AND user_id = ? AND target_company_id = ? ORDER BY id DESC LIMIT 1",
            [$afterId, $userId, $companyId],
            'iii'
        );
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get access log count for a company
     */
    private function getAccessLogCount($companyId) {
        $result = $this->getResults(
            "SELECT COUNT(*) as count FROM company_access_log WHERE target_company_id = ?",
            [$companyId],
            'i'
        );
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Get latest access log entry for a company
     */
    private function getLatestAccessLog($companyId) {
        $result = $this->getResults(
            "SELECT * FROM company_access_log WHERE target_company_id = ? ORDER BY timestamp DESC LIMIT 1",
            [$companyId],
            'i'
        );
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get users directly from a company (bypassing isolation)
     */
    private function getDirectUsersFromCompany($companyId) {
        return $this->getResults(
            "SELECT * FROM users WHERE company_id = ?",
            [$companyId],
            'i'
        );
    }
    
    private function setupTestData() {
        $timestamp = time();
        
        // Get or create ADV company
        $existingAdvCompany = $this->companyModel->findByType('ADV');
        if (!empty($existingAdvCompany)) {
            $this->testCompanies['adv'] = $existingAdvCompany[0];
        } else {
            $advCompanyData = [
                'name' => "Test ADV Company Cross $timestamp",
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
                'name' => "Test Contractor Cross {$i}_{$timestamp}",
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
                'username' => "testadvcross{$i}_{$timestamp}",
                'email' => "testadvcross{$i}_{$timestamp}@test.com",
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'first_name' => 'Test',
                'last_name' => "ADV Cross $i",
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
                    'username' => "testcontractorcross{$index}_{$i}_{$timestamp}",
                    'email' => "testcontractorcross{$index}_{$i}_{$timestamp}@test.com",
                    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                    'first_name' => 'Test',
                    'last_name' => "Contractor Cross {$index}_{$i}",
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
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testadvcross%'");
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testcontractorcross%'");
        
        // Clean up contractor companies and their access logs
        if (!empty($this->testCompanies['contractors'])) {
            foreach ($this->testCompanies['contractors'] as $company) {
                $this->executeQuery("DELETE FROM company_access_log WHERE target_company_id = ?", [$company['id']], 'i');
                $this->executeQuery("DELETE FROM companies WHERE id = ?", [$company['id']], 'i');
            }
        }
        
        // Don't delete the ADV company if we used an existing one
        if (isset($this->testCompanies['adv']) && strpos($this->testCompanies['adv']['name'], 'Test ADV Company Cross') !== false) {
            $this->executeQuery("DELETE FROM company_access_log WHERE target_company_id = ?", [$this->testCompanies['adv']['id']], 'i');
            $this->executeQuery("DELETE FROM companies WHERE id = ?", [$this->testCompanies['adv']['id']], 'i');
        }
    }
}

// Run the test if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $test = new CrossCompanyAccessPreventionTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}

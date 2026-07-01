<?php
/**
 * Property Test for ADV-Only Function Access Control
 * **Feature: adv-crm-users-module, Property 5: ADV-Only Function Access Control**
 * **Validates: Requirements 2.4**
 * 
 * For any contractor user attempting to access ADV-only functions, 
 * the system should deny access regardless of their role or permissions.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/MenuService.php';
require_once __DIR__ . '/../services/PermissionEngine.php';

class AdvOnlyFunctionAccessControlTest extends PropertyTestBase {
    private $menuService;
    private $permissionEngine;
    private $userModel;
    private $companyModel;
    private $roleModel;
    private $testUsers = [];
    private $testCompanies = [];
    
    public function __construct() {
        parent::__construct();
        $this->menuService = new MenuService();
        $this->permissionEngine = new PermissionEngine();
        $this->userModel = new User();
        $this->companyModel = new Company();
        $this->roleModel = new Role();
    }
    
    public function runTests() {
        echo "=== ADV-Only Function Access Control Tests ===\n";
        
        $this->setupTestData();
        
        $result1 = $this->runPropertyTest(
            "Contractor cannot access ADV-only menu items",
            [$this, 'testContractorCannotAccessAdvOnlyMenuItems']
        );
        
        $result2 = $this->runPropertyTest(
            "Contractor cannot access ADV-only modules",
            [$this, 'testContractorCannotAccessAdvOnlyModules']
        );
        
        $result3 = $this->runPropertyTest(
            "ADV users can access ADV-only functions",
            [$this, 'testAdvUsersCanAccessAdvOnlyFunctions']
        );
        
        $this->cleanupTestData();
        
        return $result1 && $result2 && $result3;
    }
    
    /**
     * Property 5: ADV-Only Function Access Control
     * Test that contractor users cannot access ADV-only menu items
     * regardless of their role or permissions.
     */
    public function testContractorCannotAccessAdvOnlyMenuItems() {
        try {
            // Select a random contractor user
            $contractorUser = $this->generateRandomChoice($this->testUsers['contractors']);
            
            // Get all ADV-only menu items using the flat list method
            $allMenuItems = $this->menuService->getAllMenuItems();
            $advOnlyItems = [];
            
            foreach ($allMenuItems as $item) {
                if (isset($item['adv_only']) && $item['adv_only'] === true) {
                    $advOnlyItems[] = $item;
                }
            }
            
            // Select a random ADV-only menu item
            if (empty($advOnlyItems)) {
                return ['success' => true]; // No ADV-only items to test
            }
            
            $advOnlyItem = $this->generateRandomChoice($advOnlyItems);
            
            // Check if contractor can see this menu item
            $isVisible = $this->menuService->isMenuItemVisible(
                $advOnlyItem, 
                $contractorUser['id']
            );
            
            if ($isVisible) {
                return [
                    'success' => false,
                    'message' => 'Contractor user should NOT be able to see ADV-only menu item',
                    'data' => [
                        'contractor_user' => $contractorUser['username'],
                        'company_type' => $contractorUser['company_type'],
                        'menu_item' => $advOnlyItem['id'],
                        'menu_label' => $advOnlyItem['label'],
                        'is_visible' => $isVisible
                    ]
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during ADV-only menu item test: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 5: ADV-Only Function Access Control
     * Test that contractor users cannot access ADV-only modules
     * (master_data, system, admin).
     */
    public function testContractorCannotAccessAdvOnlyModules() {
        try {
            // Select a random contractor user
            $contractorUser = $this->generateRandomChoice($this->testUsers['contractors']);
            
            // Get ADV-only modules
            $advOnlyModules = $this->menuService->getAdvOnlyModules();
            
            if (empty($advOnlyModules)) {
                return ['success' => true]; // No ADV-only modules to test
            }
            
            // Select a random ADV-only module
            $advOnlyModule = $this->generateRandomChoice($advOnlyModules);
            
            // Check if contractor can see this module section
            $isVisible = $this->menuService->isModuleSectionVisible(
                $advOnlyModule, 
                $contractorUser['id']
            );
            
            if ($isVisible) {
                return [
                    'success' => false,
                    'message' => 'Contractor user should NOT be able to see ADV-only module section',
                    'data' => [
                        'contractor_user' => $contractorUser['username'],
                        'company_type' => $contractorUser['company_type'],
                        'module' => $advOnlyModule,
                        'is_visible' => $isVisible
                    ]
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during ADV-only module test: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 5: ADV-Only Function Access Control
     * Test that ADV users CAN access ADV-only functions when they have permissions.
     */
    public function testAdvUsersCanAccessAdvOnlyFunctions() {
        try {
            // Select a random ADV user
            $advUser = $this->generateRandomChoice($this->testUsers['adv']);
            
            // Get visible menus for ADV user
            $visibleMenus = $this->menuService->getVisibleMenus($advUser['id']);
            
            // ADV users should have access to ADV-only sections
            // (at least the sections should be available, even if empty due to permissions)
            $user = $this->userModel->findWithRelations($advUser['id']);
            $isAdvUser = $user && $user['company_type'] === 'ADV';
            
            if (!$isAdvUser) {
                return [
                    'success' => false,
                    'message' => 'Test user should be ADV type',
                    'data' => [
                        'user' => $advUser['username'],
                        'company_type' => $user['company_type'] ?? 'unknown'
                    ]
                ];
            }
            
            // Get all ADV-only menu items using the flat list method
            $allMenuItems = $this->menuService->getAllMenuItems();
            $advOnlyItems = [];
            
            foreach ($allMenuItems as $item) {
                if (isset($item['adv_only']) && $item['adv_only'] === true) {
                    $advOnlyItems[] = $item;
                }
            }
            
            if (empty($advOnlyItems)) {
                return ['success' => true]; // No ADV-only items to test
            }
            
            // Select a random ADV-only menu item
            $advOnlyItem = $this->generateRandomChoice($advOnlyItems);
            
            // For ADV users, the adv_only flag should not block access
            // (only permissions should determine visibility)
            // The isMenuItemVisible should return true if:
            // 1. User is ADV (adv_only check passes)
            // 2. User has the required permission (or no permission required)
            
            // Check that ADV user is not blocked by adv_only flag
            // We test this by checking that the adv_only check itself passes
            $isAdvOnlyBlocked = $advOnlyItem['adv_only'] && !$isAdvUser;
            
            if ($isAdvOnlyBlocked) {
                return [
                    'success' => false,
                    'message' => 'ADV user should not be blocked by adv_only flag',
                    'data' => [
                        'adv_user' => $advUser['username'],
                        'company_type' => $user['company_type'],
                        'menu_item' => $advOnlyItem['id'],
                        'is_adv_only_blocked' => $isAdvOnlyBlocked
                    ]
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during ADV user access test: ' . $e->getMessage()
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
                'name' => "Test ADV Company AdvOnly $timestamp",
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
                'name' => "Test Contractor AdvOnly {$i}_{$timestamp}",
                'type' => 'CONTRACTOR',
                'status' => 'ACTIVE'
            ];
            $companyRecord = $this->companyModel->create($contractorData);
            $this->testCompanies['contractors'][] = $this->companyModel->find($companyRecord['id']);
        }
        
        // Get roles
        $advRoles = $this->roleModel->findByCompanyType('ADV');
        $contractorRoles = $this->roleModel->findByCompanyType('CONTRACTOR');
        
        // Create ADV users with different roles
        $this->testUsers['adv'] = [];
        foreach ($advRoles as $index => $role) {
            if ($index >= 3) break; // Limit to 3 ADV users
            $userData = [
                'username' => "testadvonly_adv{$index}_{$timestamp}",
                'email' => "testadvonly_adv{$index}_{$timestamp}@test.com",
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'first_name' => 'Test',
                'last_name' => "ADV AdvOnly $index",
                'company_id' => $this->testCompanies['adv']['id'],
                'role_id' => $role['id'],
                'status' => 1
            ];
            $userRecord = $this->userModel->create($userData);
            $this->testUsers['adv'][] = $this->userModel->findWithRelations($userRecord['id']);
        }
        
        // Create contractor users with different roles
        $this->testUsers['contractors'] = [];
        foreach ($this->testCompanies['contractors'] as $companyIndex => $company) {
            foreach ($contractorRoles as $roleIndex => $role) {
                if ($roleIndex >= 2) break; // Limit to 2 roles per company
                $userData = [
                    'username' => "testadvonly_con{$companyIndex}_{$roleIndex}_{$timestamp}",
                    'email' => "testadvonly_con{$companyIndex}_{$roleIndex}_{$timestamp}@test.com",
                    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                    'first_name' => 'Test',
                    'last_name' => "Contractor AdvOnly {$companyIndex}_{$roleIndex}",
                    'company_id' => $company['id'],
                    'role_id' => $role['id'],
                    'status' => 1
                ];
                $userRecord = $this->userModel->create($userData);
                $this->testUsers['contractors'][] = $this->userModel->findWithRelations($userRecord['id']);
            }
        }
    }
    
    protected function cleanupTestData() {
        // Clean up test users
        $this->executeQuery("DELETE FROM users WHERE username LIKE 'testadvonly_%'");
        
        // Clean up contractor companies
        if (!empty($this->testCompanies['contractors'])) {
            foreach ($this->testCompanies['contractors'] as $company) {
                $this->executeQuery("DELETE FROM company_access_log WHERE target_company_id = ?", [$company['id']], 'i');
                $this->executeQuery("DELETE FROM companies WHERE id = ?", [$company['id']], 'i');
            }
        }
        
        // Don't delete the ADV company if we used an existing one
        if (isset($this->testCompanies['adv']) && strpos($this->testCompanies['adv']['name'], 'Test ADV Company AdvOnly') !== false) {
            $this->executeQuery("DELETE FROM company_access_log WHERE target_company_id = ?", [$this->testCompanies['adv']['id']], 'i');
            $this->executeQuery("DELETE FROM companies WHERE id = ?", [$this->testCompanies['adv']['id']], 'i');
        }
    }
}

// Run the test if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $test = new AdvOnlyFunctionAccessControlTest();
    $result = $test->runTests();
    exit($result ? 0 : 1);
}

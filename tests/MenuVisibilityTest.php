<?php
/**
 * Property Test: Permission-Based Menu Visibility
 * 
 * **Feature: adv-crm-users-module, Property 10: Permission-Based Menu Visibility**
 * **Validates: Requirements 6.1, 6.3, 6.4, 6.5**
 * 
 * Property: For any user accessing the system, menu visibility should be determined 
 * by their specific permissions, and contractor users should never see Master Data, 
 * System, or Admin menus.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/MenuService.php';

class MenuVisibilityTest extends PropertyTestBase {
    private $menuService;
    private $userModel;
    private $companyModel;
    private $roleModel;
    private $permissionModel;
    private $testUsers = [];
    private $testCompanies = [];
    
    public function __construct() {
        parent::__construct();
        $this->menuService = new MenuService();
        $this->userModel = new User();
        $this->companyModel = new Company();
        $this->roleModel = new Role();
        $this->permissionModel = new Permission();
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests() {
        echo "\n=== Menu Visibility Property Tests ===\n";
        echo "**Feature: adv-crm-users-module, Property 10: Permission-Based Menu Visibility**\n";
        echo "**Validates: Requirements 6.1, 6.3, 6.4, 6.5**\n\n";
        
        $results = [];
        
        // Property 10.1: Menu visibility is determined by permissions
        $results['menu_visibility_by_permission'] = $this->runPropertyTest(
            'Property 10.1: Menu visibility determined by permissions',
            [$this, 'testMenuVisibilityByPermission']
        );
        
        // Property 10.2: Contractor users never see ADV-only modules
        $results['contractor_no_adv_modules'] = $this->runPropertyTest(
            'Property 10.2: Contractor users never see Master Data, System, or Admin menus',
            [$this, 'testContractorNoAdvOnlyModules']
        );
        
        // Property 10.3: ADV users can see ADV-only menus if they have permission
        $results['adv_users_see_adv_menus'] = $this->runPropertyTest(
            'Property 10.3: ADV users see ADV-only menus when they have permission',
            [$this, 'testAdvUsersSeeAdvMenus']
        );
        
        // Property 10.4: Menu visibility is consistent with can() function
        $results['menu_consistent_with_can'] = $this->runPropertyTest(
            'Property 10.4: Menu visibility consistent with can() function',
            [$this, 'testMenuConsistentWithCan']
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        // Summary
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($results));
        $total = count($results);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }
    
    /**
     * Property 10.1: Menu visibility is determined by user's specific permissions
     * 
     * For any user, a menu item should only be visible if:
     * 1. The menu item has no permission requirement, OR
     * 2. The user has the required permission
     */
    protected function testMenuVisibilityByPermission() {
        // Get a random existing user
        $user = $this->getRandomUser();
        if (!$user) {
            return ['success' => true, 'message' => 'No users to test'];
        }
        
        $userId = $user['id'];
        $isAdvUser = $user['company_type'] === 'ADV';
        
        // Get all menu items
        $allMenuItems = $this->menuService->getAllMenuItems();
        
        // Get visible menus for this user
        $visibleMenus = $this->menuService->getVisibleMenus($userId);
        $visibleIds = $this->extractVisibleMenuIds($visibleMenus);
        
        // For each menu item, verify visibility matches permission
        foreach ($allMenuItems as $item) {
            $isVisible = in_array($item['id'], $visibleIds);
            $shouldBeVisible = $this->menuService->isMenuItemVisible($item, $userId, $isAdvUser);
            
            // If item is ADV-only and user is contractor, should never be visible
            if ($item['adv_only'] && !$isAdvUser) {
                if ($isVisible) {
                    return [
                        'success' => false,
                        'message' => "ADV-only menu '{$item['id']}' visible to contractor user",
                        'data' => ['user' => $user, 'menu_item' => $item]
                    ];
                }
                continue;
            }
            
            // If item has permission requirement, check permission
            if (!empty($item['permission'])) {
                $hasPermission = $this->permissionEngine()->can($userId, $item['permission']);
                
                if ($hasPermission && !$isVisible && $shouldBeVisible) {
                    return [
                        'success' => false,
                        'message' => "Menu '{$item['id']}' should be visible (user has permission '{$item['permission']}')",
                        'data' => ['user' => $user, 'menu_item' => $item]
                    ];
                }
                
                if (!$hasPermission && $isVisible) {
                    return [
                        'success' => false,
                        'message' => "Menu '{$item['id']}' visible but user lacks permission '{$item['permission']}'",
                        'data' => ['user' => $user, 'menu_item' => $item]
                    ];
                }
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 10.2: Contractor users should NEVER see Master Data, System, or Admin menus
     * 
     * For any contractor user, regardless of their role or permissions,
     * the Master Data, System, and Admin menu sections should never be visible.
     */
    protected function testContractorNoAdvOnlyModules() {
        // Get or create a contractor user
        $contractorUser = $this->getOrCreateContractorUser();
        if (!$contractorUser) {
            return ['success' => true, 'message' => 'Could not create contractor user'];
        }
        
        $userId = $contractorUser['id'];
        
        // Get visible menus
        $visibleMenus = $this->menuService->getVisibleMenus($userId);
        
        // ADV-only modules that contractors should never see
        $advOnlyModules = $this->menuService->getAdvOnlyModules();
        
        // Check that none of the ADV-only module sections are present
        foreach ($advOnlyModules as $module) {
            if (isset($visibleMenus[$module]) && !empty($visibleMenus[$module])) {
                return [
                    'success' => false,
                    'message' => "Contractor user can see ADV-only module '$module'",
                    'data' => [
                        'user' => $contractorUser,
                        'visible_module' => $module,
                        'visible_items' => $visibleMenus[$module]
                    ]
                ];
            }
        }
        
        // Also check that no ADV-only items appear in any section
        $allVisibleIds = $this->extractVisibleMenuIds($visibleMenus);
        $allMenuItems = $this->menuService->getAllMenuItems();
        
        foreach ($allMenuItems as $item) {
            if ($item['adv_only'] && in_array($item['id'], $allVisibleIds)) {
                return [
                    'success' => false,
                    'message' => "ADV-only menu item '{$item['id']}' visible to contractor",
                    'data' => ['user' => $contractorUser, 'menu_item' => $item]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 10.3: ADV users can see ADV-only menus when they have permission
     * 
     * For any ADV user with appropriate permissions, ADV-only menu sections
     * should be visible.
     */
    protected function testAdvUsersSeeAdvMenus() {
        // Get or create an ADV user with permissions
        $advUser = $this->getOrCreateAdvUserWithPermissions();
        if (!$advUser) {
            return ['success' => true, 'message' => 'Could not create ADV user'];
        }
        
        $userId = $advUser['id'];
        
        // Get visible menus
        $visibleMenus = $this->menuService->getVisibleMenus($userId);
        
        // Get all ADV-only menu items
        $allMenuItems = $this->menuService->getAllMenuItems();
        $advOnlyItems = array_filter($allMenuItems, function($item) {
            return $item['adv_only'];
        });
        
        // For each ADV-only item, if user has permission, it should be visible
        foreach ($advOnlyItems as $item) {
            if (empty($item['permission'])) {
                continue;
            }
            
            $hasPermission = $this->permissionEngine()->can($userId, $item['permission']);
            $isVisible = $this->isItemInVisibleMenus($item['id'], $visibleMenus);
            
            if ($hasPermission && !$isVisible) {
                return [
                    'success' => false,
                    'message' => "ADV user has permission '{$item['permission']}' but menu '{$item['id']}' not visible",
                    'data' => ['user' => $advUser, 'menu_item' => $item]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 10.4: Menu visibility is consistent with can() function
     * 
     * For any user and any menu item with a permission requirement,
     * the menu visibility should match the result of can(permission).
     */
    protected function testMenuConsistentWithCan() {
        // Get a random user
        $user = $this->getRandomUser();
        if (!$user) {
            return ['success' => true, 'message' => 'No users to test'];
        }
        
        $userId = $user['id'];
        $isAdvUser = $user['company_type'] === 'ADV';
        
        // Get all menu items with permissions
        $allMenuItems = $this->menuService->getAllMenuItems();
        $itemsWithPermissions = array_filter($allMenuItems, function($item) {
            return !empty($item['permission']);
        });
        
        foreach ($itemsWithPermissions as $item) {
            // Skip ADV-only items for contractor users
            if ($item['adv_only'] && !$isAdvUser) {
                continue;
            }
            
            $canResult = $this->permissionEngine()->can($userId, $item['permission']);
            $menuVisible = $this->menuService->isMenuItemVisible($item, $userId, $isAdvUser);
            
            if ($canResult !== $menuVisible) {
                return [
                    'success' => false,
                    'message' => "Menu visibility inconsistent with can() for '{$item['id']}'",
                    'data' => [
                        'user' => $user,
                        'menu_item' => $item,
                        'can_result' => $canResult,
                        'menu_visible' => $menuVisible
                    ]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Helper: Get permission engine instance
     */
    private function permissionEngine() {
        static $engine = null;
        if ($engine === null) {
            $engine = new PermissionEngine();
        }
        return $engine;
    }
    
    /**
     * Helper: Get a random existing user
     */
    private function getRandomUser() {
        $sql = "SELECT u.*, c.type as company_type 
                FROM users u 
                INNER JOIN companies c ON u.company_id = c.id 
                WHERE u.status = 1 
                ORDER BY RAND() 
                LIMIT 1";
        $results = $this->getResults($sql);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Helper: Get or create a contractor user for testing
     */
    private function getOrCreateContractorUser() {
        // Try to find existing contractor user
        $sql = "SELECT u.*, c.type as company_type 
                FROM users u 
                INNER JOIN companies c ON u.company_id = c.id 
                WHERE c.type = 'CONTRACTOR' AND u.status = 1 
                LIMIT 1";
        $results = $this->getResults($sql);
        
        if (!empty($results)) {
            return $results[0];
        }
        
        // Create contractor company if needed
        $companyId = $this->getOrCreateContractorCompany();
        if (!$companyId) {
            return null;
        }
        
        // Get contractor role
        $sql = "SELECT id FROM roles WHERE company_type IN ('CONTRACTOR', 'BOTH') LIMIT 1";
        $roles = $this->getResults($sql);
        if (empty($roles)) {
            return null;
        }
        $roleId = $roles[0]['id'];
        
        // Create test user
        $username = 'test_contractor_' . $this->generateRandomString(6);
        $email = $username . '@test.com';
        $passwordHash = password_hash('Test123!', PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) 
                VALUES (?, ?, ?, 'Test', 'Contractor', ?, ?, 1)";
        $stmt = $this->executeQuery($sql, [$username, $email, $passwordHash, $companyId, $roleId], 'sssii');
        $userId = $this->db->insert_id;
        $stmt->close();
        
        $this->testUsers[] = $userId;
        
        return $this->userModel->findWithRelations($userId);
    }
    
    /**
     * Helper: Get or create contractor company
     */
    private function getOrCreateContractorCompany() {
        $sql = "SELECT id FROM companies WHERE type = 'CONTRACTOR' LIMIT 1";
        $results = $this->getResults($sql);
        
        if (!empty($results)) {
            return $results[0]['id'];
        }
        
        // Create contractor company
        $name = 'Test Contractor ' . $this->generateRandomString(6);
        $sql = "INSERT INTO companies (name, type, status) VALUES (?, 'CONTRACTOR', 'ACTIVE')";
        $stmt = $this->executeQuery($sql, [$name], 's');
        $companyId = $this->db->insert_id;
        $stmt->close();
        
        $this->testCompanies[] = $companyId;
        
        return $companyId;
    }
    
    /**
     * Helper: Get or create ADV user with permissions
     */
    private function getOrCreateAdvUserWithPermissions() {
        // Try to find existing ADV user with permissions
        $sql = "SELECT u.*, c.type as company_type 
                FROM users u 
                INNER JOIN companies c ON u.company_id = c.id 
                WHERE c.type = 'ADV' AND u.status = 1 
                LIMIT 1";
        $results = $this->getResults($sql);
        
        if (!empty($results)) {
            return $results[0];
        }
        
        // Get ADV company
        $sql = "SELECT id FROM companies WHERE type = 'ADV' LIMIT 1";
        $companies = $this->getResults($sql);
        if (empty($companies)) {
            return null;
        }
        $companyId = $companies[0]['id'];
        
        // Get Super Admin role (has all permissions)
        $sql = "SELECT id FROM roles WHERE name = 'Super Admin' LIMIT 1";
        $roles = $this->getResults($sql);
        if (empty($roles)) {
            return null;
        }
        $roleId = $roles[0]['id'];
        
        // Create test user
        $username = 'test_adv_' . $this->generateRandomString(6);
        $email = $username . '@test.com';
        $passwordHash = password_hash('Test123!', PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) 
                VALUES (?, ?, ?, 'Test', 'ADV', ?, ?, 1)";
        $stmt = $this->executeQuery($sql, [$username, $email, $passwordHash, $companyId, $roleId], 'sssii');
        $userId = $this->db->insert_id;
        $stmt->close();
        
        $this->testUsers[] = $userId;
        
        return $this->userModel->findWithRelations($userId);
    }
    
    /**
     * Helper: Extract visible menu IDs from nested menu structure
     */
    private function extractVisibleMenuIds($visibleMenus) {
        $ids = [];
        foreach ($visibleMenus as $section => $data) {
            // Handle collapsible sections
            if (isset($data['collapsible']) && $data['collapsible'] && isset($data['items'])) {
                foreach ($data['items'] as $item) {
                    if (isset($item['collapsible']) && $item['collapsible'] && isset($item['items'])) {
                        // Nested collapsible
                        foreach ($item['items'] as $nestedItem) {
                            if (isset($nestedItem['id'])) {
                                $ids[] = $nestedItem['id'];
                            }
                        }
                    } else if (isset($item['id'])) {
                        $ids[] = $item['id'];
                    }
                }
            } else if (is_array($data)) {
                // Flat menu array
                foreach ($data as $item) {
                    if (is_array($item) && isset($item['id'])) {
                        $ids[] = $item['id'];
                    }
                }
            }
        }
        return $ids;
    }
    
    /**
     * Helper: Check if item ID is in visible menus
     */
    private function isItemInVisibleMenus($itemId, $visibleMenus) {
        foreach ($visibleMenus as $section => $data) {
            // Handle collapsible sections
            if (isset($data['collapsible']) && $data['collapsible'] && isset($data['items'])) {
                foreach ($data['items'] as $item) {
                    if (isset($item['collapsible']) && $item['collapsible'] && isset($item['items'])) {
                        // Nested collapsible
                        foreach ($item['items'] as $nestedItem) {
                            if (isset($nestedItem['id']) && $nestedItem['id'] === $itemId) {
                                return true;
                            }
                        }
                    } else if (isset($item['id']) && $item['id'] === $itemId) {
                        return true;
                    }
                }
            } else if (is_array($data)) {
                // Flat menu array
                foreach ($data as $item) {
                    if (is_array($item) && isset($item['id']) && $item['id'] === $itemId) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        // Delete test users
        if (!empty($this->testUsers)) {
            $ids = implode(',', array_map('intval', $this->testUsers));
            $this->executeQuery("DELETE FROM users WHERE id IN ($ids)");
        }
        
        // Delete test companies
        if (!empty($this->testCompanies)) {
            $ids = implode(',', array_map('intval', $this->testCompanies));
            $this->executeQuery("DELETE FROM companies WHERE id IN ($ids)");
        }
        
        $this->testUsers = [];
        $this->testCompanies = [];
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new MenuVisibilityTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}

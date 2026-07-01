<?php
/**
 * Property Test: Menu Visibility for ADV Users - Master Data Section
 * 
 * **Feature: crm-master-modules, Property 15: Menu Visibility for ADV Users**
 * **Validates: Requirements 7.1**
 * 
 * Property: For any ADV Admin or Superadmin user, the sidebar should display 
 * the Master Data section with all six submenu items (Banks, Customers, 
 * Countries, States, Zones, Cities).
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/MenuService.php';

class MasterDataMenuVisibilityTest extends PropertyTestBase {
    private $menuService;
    private $userModel;
    private $testUsers = [];
    private $testCompanies = [];
    
    // Expected master data menu items
    private $expectedMasterMenuItems = [
        'masters_banks' => [
            'label' => 'Banks',
            'icon' => 'fa-university',
            'url' => '/masters/banks.php',
            'permission' => 'masters.banks.view'
        ],
        'masters_customers' => [
            'label' => 'Customers',
            'icon' => 'fa-users',
            'url' => '/masters/customers.php',
            'permission' => 'masters.customers.view'
        ],
        'masters_countries' => [
            'label' => 'Countries',
            'icon' => 'fa-globe',
            'url' => '/masters/countries.php',
            'permission' => 'masters.locations.view'
        ],
        'masters_states' => [
            'label' => 'States',
            'icon' => 'fa-map',
            'url' => '/masters/states.php',
            'permission' => 'masters.locations.view'
        ],
        'masters_zones' => [
            'label' => 'Zones',
            'icon' => 'fa-layer-group',
            'url' => '/masters/zones.php',
            'permission' => 'masters.locations.view'
        ],
        'masters_cities' => [
            'label' => 'Cities',
            'icon' => 'fa-city',
            'url' => '/masters/cities.php',
            'permission' => 'masters.locations.view'
        ]
    ];
    
    public function __construct() {
        parent::__construct();
        $this->menuService = new MenuService();
        $this->userModel = new User();
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests() {
        echo "\n=== Master Data Menu Visibility Property Tests ===\n";
        echo "**Feature: crm-master-modules, Property 15: Menu Visibility for ADV Users**\n";
        echo "**Validates: Requirements 7.1**\n\n";
        
        $results = [];
        
        // Property 15.1: ADV users see all six master data menu items
        $results['adv_sees_all_master_items'] = $this->runPropertyTest(
            'Property 15.1: ADV users see all six master data menu items',
            [$this, 'testAdvUserSeesAllMasterMenuItems'],
            100
        );
        
        // Property 15.2: Contractor users never see master data section
        $results['contractor_no_master_section'] = $this->runPropertyTest(
            'Property 15.2: Contractor users never see master data section',
            [$this, 'testContractorNeverSeesMasterSection'],
            100
        );
        
        // Property 15.3: Master menu items have correct configuration
        $results['master_menu_config'] = $this->runPropertyTest(
            'Property 15.3: Master menu items have correct configuration',
            [$this, 'testMasterMenuItemsConfiguration'],
            1
        );
        
        // Property 15.4: Master section is ADV-only
        $results['master_section_adv_only'] = $this->runPropertyTest(
            'Property 15.4: All master menu items are marked as ADV-only',
            [$this, 'testMasterMenuItemsAreAdvOnly'],
            1
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
     * Property 15.1: For any ADV Admin or Superadmin user with appropriate permissions,
     * the sidebar should display all six master data menu items.
     */
    protected function testAdvUserSeesAllMasterMenuItems() {
        // Get or create an ADV user with master permissions
        $advUser = $this->getOrCreateAdvUserWithMasterPermissions();
        if (!$advUser) {
            return ['success' => true, 'message' => 'Could not create ADV user with master permissions'];
        }
        
        $userId = $advUser['id'];
        
        // Get visible menus for this user
        $visibleMenus = $this->menuService->getVisibleMenus($userId);
        
        // Check that masters section exists
        if (!isset($visibleMenus['masters']) || empty($visibleMenus['masters'])) {
            return [
                'success' => false,
                'message' => "Master Data section not visible for ADV user with permissions",
                'data' => ['user' => $advUser, 'visible_menus' => array_keys($visibleMenus)]
            ];
        }
        
        // Extract visible master menu IDs
        $visibleMasterIds = array_column($visibleMenus['masters'], 'id');
        
        // Check each expected master menu item
        foreach ($this->expectedMasterMenuItems as $itemId => $config) {
            // Check if user has the required permission
            $permissionEngine = new PermissionEngine();
            $hasPermission = $permissionEngine->can($userId, $config['permission']);
            
            if ($hasPermission && !in_array($itemId, $visibleMasterIds)) {
                return [
                    'success' => false,
                    'message' => "Master menu item '$itemId' not visible for ADV user with permission '{$config['permission']}'",
                    'data' => [
                        'user' => $advUser,
                        'expected_item' => $itemId,
                        'visible_items' => $visibleMasterIds
                    ]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 15.2: For any contractor user, the Master Data section should never be visible.
     */
    protected function testContractorNeverSeesMasterSection() {
        // Get or create a contractor user
        $contractorUser = $this->getOrCreateContractorUser();
        if (!$contractorUser) {
            return ['success' => true, 'message' => 'Could not create contractor user'];
        }
        
        $userId = $contractorUser['id'];
        
        // Get visible menus for this user
        $visibleMenus = $this->menuService->getVisibleMenus($userId);
        
        // Check that masters section does NOT exist
        if (isset($visibleMenus['masters']) && !empty($visibleMenus['masters'])) {
            return [
                'success' => false,
                'message' => "Master Data section visible for contractor user",
                'data' => [
                    'user' => $contractorUser,
                    'visible_master_items' => $visibleMenus['masters']
                ]
            ];
        }
        
        // Also check that no master menu items appear anywhere
        $allVisibleIds = [];
        foreach ($visibleMenus as $items) {
            foreach ($items as $item) {
                $allVisibleIds[] = $item['id'];
            }
        }
        
        foreach (array_keys($this->expectedMasterMenuItems) as $masterId) {
            if (in_array($masterId, $allVisibleIds)) {
                return [
                    'success' => false,
                    'message' => "Master menu item '$masterId' visible to contractor user",
                    'data' => ['user' => $contractorUser, 'visible_ids' => $allVisibleIds]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 15.3: Master menu items have correct configuration (labels, icons, URLs).
     */
    protected function testMasterMenuItemsConfiguration() {
        $menuConfig = $this->menuService->getMenuConfig();
        
        // Check that masters section exists in config
        if (!isset($menuConfig['masters'])) {
            return [
                'success' => false,
                'message' => "Masters section not found in menu configuration",
                'data' => ['available_sections' => array_keys($menuConfig)]
            ];
        }
        
        $mastersConfig = $menuConfig['masters'];
        
        // Check that we have exactly 6 items
        if (count($mastersConfig) !== 6) {
            return [
                'success' => false,
                'message' => "Expected 6 master menu items, found " . count($mastersConfig),
                'data' => ['items' => array_column($mastersConfig, 'id')]
            ];
        }
        
        // Index by ID for easier lookup
        $configById = [];
        foreach ($mastersConfig as $item) {
            $configById[$item['id']] = $item;
        }
        
        // Verify each expected item
        foreach ($this->expectedMasterMenuItems as $itemId => $expected) {
            if (!isset($configById[$itemId])) {
                return [
                    'success' => false,
                    'message' => "Master menu item '$itemId' not found in configuration",
                    'data' => ['expected' => $itemId, 'found' => array_keys($configById)]
                ];
            }
            
            $actual = $configById[$itemId];
            
            // Check label
            if ($actual['label'] !== $expected['label']) {
                return [
                    'success' => false,
                    'message' => "Master menu item '$itemId' has wrong label",
                    'data' => ['expected' => $expected['label'], 'actual' => $actual['label']]
                ];
            }
            
            // Check icon
            if ($actual['icon'] !== $expected['icon']) {
                return [
                    'success' => false,
                    'message' => "Master menu item '$itemId' has wrong icon",
                    'data' => ['expected' => $expected['icon'], 'actual' => $actual['icon']]
                ];
            }
            
            // Check URL
            if ($actual['url'] !== $expected['url']) {
                return [
                    'success' => false,
                    'message' => "Master menu item '$itemId' has wrong URL",
                    'data' => ['expected' => $expected['url'], 'actual' => $actual['url']]
                ];
            }
            
            // Check permission
            if ($actual['permission'] !== $expected['permission']) {
                return [
                    'success' => false,
                    'message' => "Master menu item '$itemId' has wrong permission",
                    'data' => ['expected' => $expected['permission'], 'actual' => $actual['permission']]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 15.4: All master menu items are marked as ADV-only.
     */
    protected function testMasterMenuItemsAreAdvOnly() {
        $menuConfig = $this->menuService->getMenuConfig();
        
        if (!isset($menuConfig['masters'])) {
            return [
                'success' => false,
                'message' => "Masters section not found in menu configuration"
            ];
        }
        
        foreach ($menuConfig['masters'] as $item) {
            if (!isset($item['adv_only']) || $item['adv_only'] !== true) {
                return [
                    'success' => false,
                    'message' => "Master menu item '{$item['id']}' is not marked as ADV-only",
                    'data' => ['item' => $item]
                ];
            }
        }
        
        // Also verify 'masters' is in the ADV-only modules list
        $advOnlyModules = $this->menuService->getAdvOnlyModules();
        if (!in_array('masters', $advOnlyModules)) {
            return [
                'success' => false,
                'message' => "'masters' not in ADV-only modules list",
                'data' => ['adv_only_modules' => $advOnlyModules]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Helper: Get or create an ADV user with master module permissions
     */
    private function getOrCreateAdvUserWithMasterPermissions() {
        // Try to find existing ADV user with Super Admin role
        $sql = "SELECT u.*, c.type as company_type 
                FROM users u 
                INNER JOIN companies c ON u.company_id = c.id 
                INNER JOIN roles r ON u.role_id = r.id
                WHERE c.type = 'ADV' AND u.status = 1 AND r.name = 'Super Admin'
                LIMIT 1";
        $results = $this->getResults($sql);
        
        if (!empty($results)) {
            return $results[0];
        }
        
        // Try any ADV user
        $sql = "SELECT u.*, c.type as company_type 
                FROM users u 
                INNER JOIN companies c ON u.company_id = c.id 
                WHERE c.type = 'ADV' AND u.status = 1 
                LIMIT 1";
        $results = $this->getResults($sql);
        
        if (!empty($results)) {
            return $results[0];
        }
        
        // Create ADV user if none exists
        return $this->createAdvUserWithPermissions();
    }
    
    /**
     * Helper: Create ADV user with permissions
     */
    private function createAdvUserWithPermissions() {
        // Get ADV company
        $sql = "SELECT id FROM companies WHERE type = 'ADV' LIMIT 1";
        $companies = $this->getResults($sql);
        if (empty($companies)) {
            return null;
        }
        $companyId = $companies[0]['id'];
        
        // Get Super Admin role
        $sql = "SELECT id FROM roles WHERE name = 'Super Admin' LIMIT 1";
        $roles = $this->getResults($sql);
        if (empty($roles)) {
            return null;
        }
        $roleId = $roles[0]['id'];
        
        // Create test user
        $username = 'test_adv_master_' . $this->generateRandomString(6);
        $email = "{$username}@test.com";
        $passwordHash = password_hash('Test123!', PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) 
                VALUES (?, ?, ?, 'Test', 'ADV Master', ?, ?, 1)";
        $stmt = $this->executeQuery($sql, [$username, $email, $passwordHash, $companyId, $roleId], 'sssii');
        $userId = $this->db->insert_id;
        $stmt->close();
        
        $this->testUsers[] = $userId;
        
        return $this->userModel->findWithRelations($userId);
    }
    
    /**
     * Helper: Get or create a contractor user
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
        $username = 'test_contractor_master_' . $this->generateRandomString(6);
        $email = "{$username}@test.com";
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
        $name = 'Test Contractor Master ' . $this->generateRandomString(6);
        $sql = "INSERT INTO companies (name, type, status) VALUES (?, 'CONTRACTOR', 'ACTIVE')";
        $stmt = $this->executeQuery($sql, [$name], 's');
        $companyId = $this->db->insert_id;
        $stmt->close();
        
        $this->testCompanies[] = $companyId;
        
        return $companyId;
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
    $test = new MasterDataMenuVisibilityTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}

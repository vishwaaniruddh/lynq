<?php
/**
 * Property Test: Engineer Status Update Restriction
 * **Feature: adv-crm-inventory-module, Property 19: Engineer Status Update Restriction**
 * **Validates: Requirements 11.2**
 * 
 * Property: For any engineer updating an asset status, only the statuses 
 * "In Use", "Returned", "Working", "Not Working" SHALL be accepted.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/InventoryAccessService.php';
require_once __DIR__ . '/../services/AssetStatusService.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Role.php';

class EngineerStatusUpdateRestrictionTest extends PropertyTestBase {
    private $inventoryAccessService;
    private $assetStatusService;
    private $assetRepository;
    private $productRepository;
    private $warehouseRepository;
    private $companyRepository;
    private $userModel;
    private $roleModel;
    
    private $createdAssetIds = [];
    private $createdProductIds = [];
    private $createdWarehouseIds = [];
    private $createdCompanyIds = [];
    private $createdUserIds = [];
    private $createdRoleIds = [];
    
    // Engineer-allowed status updates (Requirement 11.2)
    private static $engineerAllowedStatuses = [
        AssetRepository::STATUS_IN_USE,
        AssetRepository::STATUS_RETURNED
    ];
    
    // Engineer-allowed working condition updates
    private static $engineerAllowedConditions = [
        AssetRepository::CONDITION_WORKING,
        AssetRepository::CONDITION_NOT_WORKING
    ];
    
    // All possible statuses (for testing rejection of disallowed statuses)
    private static $allStatuses = [
        AssetRepository::STATUS_IN_STOCK,
        AssetRepository::STATUS_DISPATCHED,
        AssetRepository::STATUS_ASSIGNED,
        AssetRepository::STATUS_IN_USE,
        AssetRepository::STATUS_RETURNED,
        AssetRepository::STATUS_UNDER_REPAIR,
        AssetRepository::STATUS_SCRAPPED,
        AssetRepository::STATUS_LOST
    ];
    
    public function __construct() {
        parent::__construct();
        $this->inventoryAccessService = new InventoryAccessService();
        $this->assetStatusService = new AssetStatusService();
        $this->assetRepository = new AssetRepository();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->companyRepository = new CompanyRepository();
        $this->companyRepository->disableCompanyFilter();
        $this->userModel = new User();
        $this->roleModel = new Role();
    }
    
    /**
     * Run all property tests
     */
    public function runTests() {
        echo "\n=== Engineer Status Update Restriction Property Tests ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 19: Engineer Status Update Restriction**\n";
        echo "**Validates: Requirements 11.2**\n\n";
        
        $results = [];
        
        // Property 19a: Engineer can update to allowed statuses
        $results['engineer_allowed_statuses'] = $this->runPropertyTest(
            'Property 19a: Engineer can update to allowed statuses (In Use, Returned)',
            function() {
                return $this->testEngineerAllowedStatusUpdates();
            },
            30
        );
        
        // Property 19b: Engineer cannot update to disallowed statuses
        $results['engineer_disallowed_statuses'] = $this->runPropertyTest(
            'Property 19b: Engineer cannot update to disallowed statuses',
            function() {
                return $this->testEngineerDisallowedStatusUpdates();
            },
            30
        );
        
        // Property 19c: Engineer can update working condition to Working/Not Working
        $results['engineer_allowed_conditions'] = $this->runPropertyTest(
            'Property 19c: Engineer can update working condition (Working, Not Working)',
            function() {
                return $this->testEngineerAllowedConditionUpdates();
            },
            30
        );
        
        // Property 19d: Engineer can only update assets assigned to them
        $results['engineer_assigned_only'] = $this->runPropertyTest(
            'Property 19d: Engineer can only update assets assigned to them',
            function() {
                return $this->testEngineerCanOnlyUpdateAssignedAssets();
            },
            30
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($results));
        $total = count($results);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }
    
    /**
     * Property 19a: Engineer can update to allowed statuses (In Use, Returned)
     * Requirement 11.2: Limit engineer status updates to: In Use, Returned
     */
    private function testEngineerAllowedStatusUpdates(): array {
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractor['id']);
        
        // Create engineer role (low level)
        $engineerRole = $this->createTestRole('Engineer', 1);
        $engineer = $this->createTestUser($contractor['id'], $engineerRole['id']);
        
        // Create product and asset assigned to engineer
        $product = $this->createTestProduct(true);
        $asset = $this->createTestAssetAssignedToUser($product['id'], $contractorWarehouse['id'], $engineer['id']);
        
        // Pick a random allowed status
        $allowedStatus = $this->generateRandomChoice(self::$engineerAllowedStatuses);
        
        // Check if engineer can update to this status
        $result = $this->inventoryAccessService->canUpdateAssetStatus(
            $engineer['id'],
            $asset['id'],
            $allowedStatus
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => "Engineer should be able to update to allowed status '$allowedStatus'",
                'data' => [
                    'engineer_id' => $engineer['id'],
                    'asset_id' => $asset['id'],
                    'requested_status' => $allowedStatus,
                    'result' => $result
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 19b: Engineer cannot update to disallowed statuses
     * Requirement 11.2: Only In Use, Returned are allowed for engineers
     */
    private function testEngineerDisallowedStatusUpdates(): array {
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractor['id']);
        
        // Create engineer role (low level)
        $engineerRole = $this->createTestRole('Engineer', 1);
        $engineer = $this->createTestUser($contractor['id'], $engineerRole['id']);
        
        // Create product and asset assigned to engineer
        $product = $this->createTestProduct(true);
        $asset = $this->createTestAssetAssignedToUser($product['id'], $contractorWarehouse['id'], $engineer['id']);
        
        // Get disallowed statuses (all statuses except allowed ones)
        $disallowedStatuses = array_diff(self::$allStatuses, self::$engineerAllowedStatuses);
        
        // Pick a random disallowed status
        $disallowedStatus = $this->generateRandomChoice($disallowedStatuses);
        
        // Check if engineer can update to this status (should fail)
        $result = $this->inventoryAccessService->canUpdateAssetStatus(
            $engineer['id'],
            $asset['id'],
            $disallowedStatus
        );
        
        if ($result['success']) {
            return [
                'success' => false,
                'message' => "Engineer should NOT be able to update to disallowed status '$disallowedStatus'",
                'data' => [
                    'engineer_id' => $engineer['id'],
                    'asset_id' => $asset['id'],
                    'requested_status' => $disallowedStatus,
                    'allowed_statuses' => self::$engineerAllowedStatuses
                ]
            ];
        }
        
        // Verify the error code indicates status not allowed
        if (!isset($result['code']) || $result['code'] !== 'STATUS_NOT_ALLOWED') {
            return [
                'success' => false,
                'message' => "Expected error code 'STATUS_NOT_ALLOWED' for disallowed status",
                'data' => [
                    'engineer_id' => $engineer['id'],
                    'requested_status' => $disallowedStatus,
                    'actual_code' => $result['code'] ?? 'none'
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 19c: Engineer can update working condition to Working/Not Working
     * Requirement 11.2: Engineers can update working condition
     */
    private function testEngineerAllowedConditionUpdates(): array {
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractor['id']);
        
        // Create engineer role (low level)
        $engineerRole = $this->createTestRole('Engineer', 1);
        $engineer = $this->createTestUser($contractor['id'], $engineerRole['id']);
        
        // Create product and asset assigned to engineer
        $product = $this->createTestProduct(true);
        $asset = $this->createTestAssetAssignedToUser($product['id'], $contractorWarehouse['id'], $engineer['id']);
        
        // Pick a random allowed condition
        $allowedCondition = $this->generateRandomChoice(self::$engineerAllowedConditions);
        
        // Check if engineer can update to this condition
        $result = $this->inventoryAccessService->canUpdateWorkingCondition(
            $engineer['id'],
            $asset['id'],
            $allowedCondition
        );
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => "Engineer should be able to update to allowed condition '$allowedCondition'",
                'data' => [
                    'engineer_id' => $engineer['id'],
                    'asset_id' => $asset['id'],
                    'requested_condition' => $allowedCondition,
                    'result' => $result
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 19d: Engineer can only update assets assigned to them
     * Requirement 11.2: Engineers have limited status update capabilities
     */
    private function testEngineerCanOnlyUpdateAssignedAssets(): array {
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractor['id']);
        
        // Create engineer role (low level)
        $engineerRole = $this->createTestRole('Engineer', 1);
        $engineer1 = $this->createTestUser($contractor['id'], $engineerRole['id']);
        $engineer2 = $this->createTestUser($contractor['id'], $engineerRole['id']);
        
        // Create product and asset assigned to engineer2 (not engineer1)
        $product = $this->createTestProduct(true);
        $asset = $this->createTestAssetAssignedToUser($product['id'], $contractorWarehouse['id'], $engineer2['id']);
        
        // Pick a random allowed status
        $allowedStatus = $this->generateRandomChoice(self::$engineerAllowedStatuses);
        
        // Engineer1 tries to update asset assigned to engineer2 (should fail)
        $result = $this->inventoryAccessService->canUpdateAssetStatus(
            $engineer1['id'],
            $asset['id'],
            $allowedStatus
        );
        
        if ($result['success']) {
            return [
                'success' => false,
                'message' => "Engineer should NOT be able to update assets not assigned to them",
                'data' => [
                    'engineer1_id' => $engineer1['id'],
                    'engineer2_id' => $engineer2['id'],
                    'asset_id' => $asset['id'],
                    'asset_holder_id' => $asset['current_holder_id']
                ]
            ];
        }
        
        // Verify the error code indicates not assigned
        if (!isset($result['code']) || $result['code'] !== 'NOT_ASSIGNED') {
            return [
                'success' => false,
                'message' => "Expected error code 'NOT_ASSIGNED' when engineer tries to update unassigned asset",
                'data' => [
                    'engineer_id' => $engineer1['id'],
                    'actual_code' => $result['code'] ?? 'none'
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Create test company
     */
    private function createTestCompany(string $type = 'CONTRACTOR'): array {
        $data = [
            'name' => 'Test Company ' . $this->generateRandomString(8),
            'type' => $type,
            'status' => 'ACTIVE'
        ];
        
        $company = $this->companyRepository->create($data);
        $this->createdCompanyIds[] = $company['id'];
        return $company;
    }
    
    /**
     * Create test warehouse
     */
    private function createTestWarehouse(int $companyId): array {
        $data = [
            'name' => 'Test Warehouse ' . $this->generateRandomString(8),
            'location' => 'Test Location',
            'company_id' => $companyId,
            'status' => 'active'
        ];
        
        $warehouse = $this->warehouseRepository->create($data);
        $this->createdWarehouseIds[] = $warehouse['id'];
        return $warehouse;
    }
    
    /**
     * Create test product
     */
    private function createTestProduct(bool $serializable = true): array {
        $data = [
            'name' => 'Test Product ' . $this->generateRandomString(8),
            'unit_of_measure' => 'unit',
            'inventory_type' => 'INTERNAL',
            'is_serializable' => $serializable ? 1 : 0,
            'is_repairable' => 1,
            'status' => 'active'
        ];
        
        $product = $this->productRepository->create($data);
        $this->createdProductIds[] = $product['id'];
        return $product;
    }
    
    /**
     * Create test asset assigned to a user
     */
    private function createTestAssetAssignedToUser(int $productId, int $warehouseId, int $userId): array {
        $data = [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'serial_number' => 'SN-' . $this->generateRandomString(12),
            'status' => AssetRepository::STATUS_ASSIGNED,
            'working_condition' => AssetRepository::CONDITION_WORKING,
            'current_holder_type' => AssetRepository::HOLDER_USER,
            'current_holder_id' => $userId,
            'source_warehouse_id' => $warehouseId
        ];
        
        $asset = $this->assetRepository->create($data);
        $this->createdAssetIds[] = $asset['id'];
        return $asset;
    }
    
    /**
     * Create test role
     */
    private function createTestRole(string $name, int $level): array {
        $data = [
            'name' => $name . ' ' . $this->generateRandomString(6),
            'level' => $level,
            'description' => 'Test role'
        ];
        
        $role = $this->roleModel->create($data);
        $this->createdRoleIds[] = $role['id'];
        return $role;
    }
    
    /**
     * Create test user
     */
    private function createTestUser(int $companyId, int $roleId): array {
        $username = 'testuser_' . $this->generateRandomString(8);
        $data = [
            'username' => $username,
            'email' => $username . '@test.com',
            'password_hash' => password_hash('test123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'User',
            'company_id' => $companyId,
            'role_id' => $roleId,
            'status' => 1
        ];
        
        $user = $this->userModel->create($data);
        $this->createdUserIds[] = $user['id'];
        return $user;
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        // Delete assets first (foreign key constraints)
        foreach ($this->createdAssetIds as $id) {
            try {
                $this->assetRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete users
        foreach ($this->createdUserIds as $id) {
            try {
                $this->userModel->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete roles
        foreach ($this->createdRoleIds as $id) {
            try {
                $this->roleModel->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete products
        foreach ($this->createdProductIds as $id) {
            try {
                $this->productRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete warehouses
        foreach ($this->createdWarehouseIds as $id) {
            try {
                $this->warehouseRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete companies
        foreach ($this->createdCompanyIds as $id) {
            try {
                $this->companyRepository->delete($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        $this->createdAssetIds = [];
        $this->createdUserIds = [];
        $this->createdRoleIds = [];
        $this->createdProductIds = [];
        $this->createdWarehouseIds = [];
        $this->createdCompanyIds = [];
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new EngineerStatusUpdateRestrictionTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

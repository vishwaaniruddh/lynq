<?php
/**
 * Property Test: Role-Based Inventory Visibility
 * **Feature: adv-crm-inventory-module, Property 5: Role-Based Inventory Visibility**
 * **Validates: Requirements 8.1, 8.2, 8.3**
 * 
 * Property: For any user accessing inventory, the returned inventory items SHALL only include 
 * items within the user's permission scope (ADV sees all, Contractor sees delegated, Engineer sees assigned).
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/InventoryAccessService.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Role.php';

class RoleBasedInventoryVisibilityTest extends PropertyTestBase {
    private $inventoryAccessService;
    private $assetRepository;
    private $productRepository;
    private $warehouseRepository;
    private $companyRepository;
    private $stockRepository;
    private $userModel;
    private $roleModel;
    
    private $createdAssetIds = [];
    private $createdProductIds = [];
    private $createdWarehouseIds = [];
    private $createdCompanyIds = [];
    private $createdUserIds = [];
    private $createdRoleIds = [];
    private $createdStockIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->inventoryAccessService = new InventoryAccessService();
        $this->assetRepository = new AssetRepository();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->companyRepository = new CompanyRepository();
        $this->companyRepository->disableCompanyFilter();
        $this->stockRepository = new StockRepository();
        $this->userModel = new User();
        $this->roleModel = new Role();
    }
    
    /**
     * Run all property tests
     */
    public function runTests() {
        echo "\n=== Role-Based Inventory Visibility Property Tests ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 5: Role-Based Inventory Visibility**\n";
        echo "**Validates: Requirements 8.1, 8.2, 8.3**\n\n";
        
        $results = [];
        
        // Property 5a: ADV users see all inventory
        $results['adv_sees_all'] = $this->runPropertyTest(
            'Property 5a: ADV users can access all inventory',
            function() {
                return $this->testAdvUserSeesAllInventory();
            },
            30
        );
        
        // Property 5b: Contractor users see only delegated inventory
        $results['contractor_sees_delegated'] = $this->runPropertyTest(
            'Property 5b: Contractor users can only access delegated inventory',
            function() {
                return $this->testContractorSeesOnlyDelegated();
            },
            30
        );
        
        // Property 5c: Engineer users see only assigned inventory
        $results['engineer_sees_assigned'] = $this->runPropertyTest(
            'Property 5c: Engineer users can only access assigned inventory',
            function() {
                return $this->testEngineerSeesOnlyAssigned();
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
     * Property 5a: ADV users can access all inventory
     * Requirement 8.1: ADV users can access all warehouses, all contractor allocations
     */
    private function testAdvUserSeesAllInventory(): array {
        // Create ADV company and user
        $advCompany = $this->createTestCompany('ADV');
        $advRole = $this->createTestRole('ADV Admin', 10);
        $advUser = $this->createTestUser($advCompany['id'], $advRole['id']);
        
        // Create contractor company with warehouse and assets
        $contractorCompany = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractorCompany['id']);
        
        // Create ADV warehouse with assets
        $advWarehouse = $this->createTestWarehouse($advCompany['id']);
        
        // Create products and assets in both warehouses
        $product = $this->createTestProduct(true);
        $advAsset = $this->createTestAsset($product['id'], $advWarehouse['id']);
        $contractorAsset = $this->createTestAsset($product['id'], $contractorWarehouse['id']);
        
        // Get accessible inventory for ADV user
        $inventory = $this->inventoryAccessService->getAccessibleInventory($advUser['id']);
        
        // ADV user should see both assets
        $assetIds = array_column($inventory['assets'] ?? [], 'id');
        
        $seesAdvAsset = in_array($advAsset['id'], $assetIds);
        $seesContractorAsset = in_array($contractorAsset['id'], $assetIds);
        
        if (!$seesAdvAsset || !$seesContractorAsset) {
            return [
                'success' => false,
                'message' => 'ADV user does not see all inventory',
                'data' => [
                    'adv_user_id' => $advUser['id'],
                    'sees_adv_asset' => $seesAdvAsset,
                    'sees_contractor_asset' => $seesContractorAsset,
                    'visible_asset_ids' => $assetIds
                ]
            ];
        }
        
        // Also check warehouses
        $warehouses = $this->inventoryAccessService->getAccessibleWarehouses($advUser['id']);
        $warehouseIds = array_column($warehouses, 'id');
        
        $seesAdvWarehouse = in_array($advWarehouse['id'], $warehouseIds);
        $seesContractorWarehouse = in_array($contractorWarehouse['id'], $warehouseIds);
        
        if (!$seesAdvWarehouse || !$seesContractorWarehouse) {
            return [
                'success' => false,
                'message' => 'ADV user does not see all warehouses',
                'data' => [
                    'adv_user_id' => $advUser['id'],
                    'sees_adv_warehouse' => $seesAdvWarehouse,
                    'sees_contractor_warehouse' => $seesContractorWarehouse
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 5b: Contractor users can only access delegated inventory
     * Requirement 8.2: Contractor users can only access inventory delegated to their company
     */
    private function testContractorSeesOnlyDelegated(): array {
        // Create ADV company with warehouse and assets
        $advCompany = $this->createTestCompany('ADV');
        $advWarehouse = $this->createTestWarehouse($advCompany['id']);
        
        // Create two contractor companies
        $contractor1 = $this->createTestCompany('CONTRACTOR');
        $contractor1Warehouse = $this->createTestWarehouse($contractor1['id']);
        $contractor1Role = $this->createTestRole('Contractor Manager', 5);
        $contractor1User = $this->createTestUser($contractor1['id'], $contractor1Role['id']);
        
        $contractor2 = $this->createTestCompany('CONTRACTOR');
        $contractor2Warehouse = $this->createTestWarehouse($contractor2['id']);
        
        // Create products and assets
        $product = $this->createTestProduct(true);
        
        // Asset in ADV warehouse
        $advAsset = $this->createTestAsset($product['id'], $advWarehouse['id']);
        
        // Asset delegated to contractor1 (held by company)
        $contractor1Asset = $this->createTestAssetWithHolder(
            $product['id'], 
            $contractor1Warehouse['id'],
            'company',
            $contractor1['id']
        );
        
        // Asset in contractor2's warehouse
        $contractor2Asset = $this->createTestAssetWithHolder(
            $product['id'],
            $contractor2Warehouse['id'],
            'company',
            $contractor2['id']
        );
        
        // Get accessible inventory for contractor1 user
        $inventory = $this->inventoryAccessService->getAccessibleInventory($contractor1User['id']);
        $assetIds = array_column($inventory['assets'] ?? [], 'id');
        
        // Contractor1 should see their own asset
        $seesOwnAsset = in_array($contractor1Asset['id'], $assetIds);
        
        // Contractor1 should NOT see ADV asset or contractor2 asset
        $seesAdvAsset = in_array($advAsset['id'], $assetIds);
        $seesContractor2Asset = in_array($contractor2Asset['id'], $assetIds);
        
        if (!$seesOwnAsset) {
            return [
                'success' => false,
                'message' => 'Contractor user does not see their own delegated inventory',
                'data' => [
                    'contractor_user_id' => $contractor1User['id'],
                    'expected_asset_id' => $contractor1Asset['id'],
                    'visible_asset_ids' => $assetIds
                ]
            ];
        }
        
        if ($seesAdvAsset || $seesContractor2Asset) {
            return [
                'success' => false,
                'message' => 'Contractor user sees inventory outside their scope',
                'data' => [
                    'contractor_user_id' => $contractor1User['id'],
                    'sees_adv_asset' => $seesAdvAsset,
                    'sees_other_contractor_asset' => $seesContractor2Asset
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 5c: Engineer users can only access assigned inventory
     * Requirement 8.3: Engineers can only access items assigned to them
     */
    private function testEngineerSeesOnlyAssigned(): array {
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractor['id']);
        
        // Create engineer role (low level)
        $engineerRole = $this->createTestRole('Engineer', 1);
        
        // Create two engineers
        $engineer1 = $this->createTestUser($contractor['id'], $engineerRole['id']);
        $engineer2 = $this->createTestUser($contractor['id'], $engineerRole['id']);
        
        // Create product and assets
        $product = $this->createTestProduct(true);
        
        // Asset assigned to engineer1
        $engineer1Asset = $this->createTestAssetWithHolder(
            $product['id'],
            $contractorWarehouse['id'],
            'user',
            $engineer1['id']
        );
        
        // Asset assigned to engineer2
        $engineer2Asset = $this->createTestAssetWithHolder(
            $product['id'],
            $contractorWarehouse['id'],
            'user',
            $engineer2['id']
        );
        
        // Asset in warehouse (not assigned to anyone)
        $warehouseAsset = $this->createTestAsset($product['id'], $contractorWarehouse['id']);
        
        // Get accessible inventory for engineer1
        $inventory = $this->inventoryAccessService->getAccessibleInventory($engineer1['id']);
        $assetIds = array_column($inventory['assets'] ?? [], 'id');
        
        // Engineer1 should see their own assigned asset
        $seesOwnAsset = in_array($engineer1Asset['id'], $assetIds);
        
        // Engineer1 should NOT see engineer2's asset or warehouse asset
        $seesEngineer2Asset = in_array($engineer2Asset['id'], $assetIds);
        $seesWarehouseAsset = in_array($warehouseAsset['id'], $assetIds);
        
        if (!$seesOwnAsset) {
            return [
                'success' => false,
                'message' => 'Engineer does not see their assigned inventory',
                'data' => [
                    'engineer_id' => $engineer1['id'],
                    'expected_asset_id' => $engineer1Asset['id'],
                    'visible_asset_ids' => $assetIds
                ]
            ];
        }
        
        if ($seesEngineer2Asset || $seesWarehouseAsset) {
            return [
                'success' => false,
                'message' => 'Engineer sees inventory outside their scope',
                'data' => [
                    'engineer_id' => $engineer1['id'],
                    'sees_other_engineer_asset' => $seesEngineer2Asset,
                    'sees_warehouse_asset' => $seesWarehouseAsset
                ]
            ];
        }
        
        // Engineers should not have access to stock
        if (!empty($inventory['stock'])) {
            return [
                'success' => false,
                'message' => 'Engineer has access to stock (should not)',
                'data' => [
                    'engineer_id' => $engineer1['id'],
                    'stock_count' => count($inventory['stock'])
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Create test company
     */
    private function createTestCompany(string $type = 'ADV'): array {
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
     * Create test asset
     */
    private function createTestAsset(int $productId, int $warehouseId): array {
        $data = [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'serial_number' => 'SN-' . $this->generateRandomString(12),
            'status' => AssetRepository::STATUS_IN_STOCK,
            'working_condition' => AssetRepository::CONDITION_WORKING,
            'current_holder_type' => AssetRepository::HOLDER_WAREHOUSE,
            'current_holder_id' => $warehouseId,
            'source_warehouse_id' => $warehouseId
        ];
        
        $asset = $this->assetRepository->create($data);
        $this->createdAssetIds[] = $asset['id'];
        return $asset;
    }
    
    /**
     * Create test asset with specific holder
     */
    private function createTestAssetWithHolder(int $productId, int $warehouseId, string $holderType, int $holderId): array {
        $data = [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'serial_number' => 'SN-' . $this->generateRandomString(12),
            'status' => AssetRepository::STATUS_ASSIGNED,
            'working_condition' => AssetRepository::CONDITION_WORKING,
            'current_holder_type' => $holderType,
            'current_holder_id' => $holderId,
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
        
        // Delete stock
        foreach ($this->createdStockIds as $id) {
            try {
                $this->stockRepository->delete($id);
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
        $this->createdStockIds = [];
        $this->createdUserIds = [];
        $this->createdRoleIds = [];
        $this->createdProductIds = [];
        $this->createdWarehouseIds = [];
        $this->createdCompanyIds = [];
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new RoleBasedInventoryVisibilityTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

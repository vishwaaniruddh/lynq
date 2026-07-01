<?php
/**
 * Unit Tests: Inventory Access Control
 * Tests role-based access control for inventory operations
 * 
 * Requirements: 8.1, 8.2, 8.3
 * - 8.1: ADV users can access all warehouses, all contractor allocations, and full repair/scrap history
 * - 8.2: Contractor users can only access inventory delegated to their company and their engineers
 * - 8.3: Engineers can only access items assigned to them with limited status update capabilities
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InventoryAccessService.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Role.php';

class InventoryAccessControlTest {
    private $inventoryAccessService;
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
    
    private $testResults = [];
    
    public function __construct() {
        $this->inventoryAccessService = new InventoryAccessService();
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
     * Run all unit tests
     */
    public function runTests() {
        echo "\n=== Inventory Access Control Unit Tests ===\n";
        echo "Requirements: 8.1, 8.2, 8.3\n\n";
        
        // ADV User Tests (Requirement 8.1)
        $this->runTest('ADV user can access all warehouses', [$this, 'testAdvUserAccessAllWarehouses']);
        $this->runTest('ADV user can access all inventory', [$this, 'testAdvUserAccessAllInventory']);
        $this->runTest('ADV user can dispatch from any warehouse', [$this, 'testAdvUserCanDispatchFromAnyWarehouse']);
        $this->runTest('ADV user can dispatch to any destination', [$this, 'testAdvUserCanDispatchToAnyDestination']);
        
        // Contractor User Tests (Requirement 8.2)
        $this->runTest('Contractor user can only access own warehouses', [$this, 'testContractorAccessOwnWarehouses']);
        $this->runTest('Contractor user can only access delegated inventory', [$this, 'testContractorAccessDelegatedInventory']);
        $this->runTest('Contractor user cannot access other company inventory', [$this, 'testContractorCannotAccessOtherCompanyInventory']);
        $this->runTest('Contractor user can dispatch to own engineers', [$this, 'testContractorCanDispatchToOwnEngineers']);
        
        // Engineer User Tests (Requirement 8.3)
        $this->runTest('Engineer can only access assigned items', [$this, 'testEngineerAccessAssignedItems']);
        $this->runTest('Engineer cannot dispatch', [$this, 'testEngineerCannotDispatch']);
        $this->runTest('Engineer has limited status update capabilities', [$this, 'testEngineerLimitedStatusUpdates']);
        
        // Cleanup
        $this->cleanupTestData();
        
        // Summary
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($this->testResults));
        $total = count($this->testResults);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }
    
    /**
     * Run a single test
     */
    private function runTest(string $name, callable $testFunction): void {
        try {
            $result = $testFunction();
            if ($result['success']) {
                echo "✓ $name\n";
                $this->testResults[$name] = true;
            } else {
                echo "✗ $name: {$result['message']}\n";
                $this->testResults[$name] = false;
            }
        } catch (Exception $e) {
            echo "✗ $name: Exception - {$e->getMessage()}\n";
            $this->testResults[$name] = false;
        }
    }
    
    // ==================== ADV User Tests (Requirement 8.1) ====================
    
    /**
     * Test: ADV user can access all warehouses
     */
    private function testAdvUserAccessAllWarehouses(): array {
        // Create ADV company and user
        $advCompany = $this->createTestCompany('ADV');
        $advRole = $this->createTestRole('ADV Admin', 10);
        $advUser = $this->createTestUser($advCompany['id'], $advRole['id']);
        
        // Create contractor company with warehouse
        $contractorCompany = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractorCompany['id']);
        
        // Create ADV warehouse
        $advWarehouse = $this->createTestWarehouse($advCompany['id']);
        
        // Get accessible warehouses for ADV user
        $warehouses = $this->inventoryAccessService->getAccessibleWarehouses($advUser['id']);
        $warehouseIds = array_column($warehouses, 'id');
        
        // ADV user should see both warehouses
        if (!in_array($advWarehouse['id'], $warehouseIds)) {
            return ['success' => false, 'message' => 'ADV user cannot see ADV warehouse'];
        }
        
        if (!in_array($contractorWarehouse['id'], $warehouseIds)) {
            return ['success' => false, 'message' => 'ADV user cannot see contractor warehouse'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: ADV user can access all inventory
     */
    private function testAdvUserAccessAllInventory(): array {
        // Create ADV company and user
        $advCompany = $this->createTestCompany('ADV');
        $advRole = $this->createTestRole('ADV Admin', 10);
        $advUser = $this->createTestUser($advCompany['id'], $advRole['id']);
        
        // Create contractor company with warehouse and asset
        $contractorCompany = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractorCompany['id']);
        
        // Create product and assets
        $product = $this->createTestProduct(true);
        $advWarehouse = $this->createTestWarehouse($advCompany['id']);
        $advAsset = $this->createTestAsset($product['id'], $advWarehouse['id']);
        $contractorAsset = $this->createTestAsset($product['id'], $contractorWarehouse['id']);
        
        // Get accessible inventory for ADV user
        $inventory = $this->inventoryAccessService->getAccessibleInventory($advUser['id']);
        $assetIds = array_column($inventory['assets'] ?? [], 'id');
        
        // ADV user should see both assets
        if (!in_array($advAsset['id'], $assetIds)) {
            return ['success' => false, 'message' => 'ADV user cannot see ADV asset'];
        }
        
        if (!in_array($contractorAsset['id'], $assetIds)) {
            return ['success' => false, 'message' => 'ADV user cannot see contractor asset'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: ADV user can dispatch from any warehouse
     */
    private function testAdvUserCanDispatchFromAnyWarehouse(): array {
        // Create ADV company and user
        $advCompany = $this->createTestCompany('ADV');
        $advRole = $this->createTestRole('ADV Admin', 10);
        $advUser = $this->createTestUser($advCompany['id'], $advRole['id']);
        
        // Create contractor company with warehouse
        $contractorCompany = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractorCompany['id']);
        
        // ADV user should be able to dispatch from contractor warehouse
        $canDispatch = $this->inventoryAccessService->canDispatchFrom($advUser['id'], $contractorWarehouse['id']);
        
        if (!$canDispatch) {
            return ['success' => false, 'message' => 'ADV user cannot dispatch from contractor warehouse'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: ADV user can dispatch to any destination
     */
    private function testAdvUserCanDispatchToAnyDestination(): array {
        // Create ADV company and user
        $advCompany = $this->createTestCompany('ADV');
        $advRole = $this->createTestRole('ADV Admin', 10);
        $advUser = $this->createTestUser($advCompany['id'], $advRole['id']);
        
        // Create contractor company with warehouse and user
        $contractorCompany = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractorCompany['id']);
        $contractorRole = $this->createTestRole('Contractor Manager', 5);
        $contractorUser = $this->createTestUser($contractorCompany['id'], $contractorRole['id']);
        
        // ADV user should be able to dispatch to contractor company
        $canDispatchToCompany = $this->inventoryAccessService->canDispatchTo($advUser['id'], $contractorCompany['id'], 'company');
        if (!$canDispatchToCompany) {
            return ['success' => false, 'message' => 'ADV user cannot dispatch to contractor company'];
        }
        
        // ADV user should be able to dispatch to contractor user
        $canDispatchToUser = $this->inventoryAccessService->canDispatchTo($advUser['id'], $contractorUser['id'], 'user');
        if (!$canDispatchToUser) {
            return ['success' => false, 'message' => 'ADV user cannot dispatch to contractor user'];
        }
        
        // ADV user should be able to dispatch to contractor warehouse
        $canDispatchToWarehouse = $this->inventoryAccessService->canDispatchTo($advUser['id'], $contractorWarehouse['id'], 'warehouse');
        if (!$canDispatchToWarehouse) {
            return ['success' => false, 'message' => 'ADV user cannot dispatch to contractor warehouse'];
        }
        
        return ['success' => true];
    }
    
    // ==================== Contractor User Tests (Requirement 8.2) ====================
    
    /**
     * Test: Contractor user can only access own warehouses
     */
    private function testContractorAccessOwnWarehouses(): array {
        // Create two contractor companies
        $contractor1 = $this->createTestCompany('CONTRACTOR');
        $contractor1Warehouse = $this->createTestWarehouse($contractor1['id']);
        $contractor1Role = $this->createTestRole('Contractor Manager', 5);
        $contractor1User = $this->createTestUser($contractor1['id'], $contractor1Role['id']);
        
        $contractor2 = $this->createTestCompany('CONTRACTOR');
        $contractor2Warehouse = $this->createTestWarehouse($contractor2['id']);
        
        // Get accessible warehouses for contractor1 user
        $warehouses = $this->inventoryAccessService->getAccessibleWarehouses($contractor1User['id']);
        $warehouseIds = array_column($warehouses, 'id');
        
        // Contractor1 should see their own warehouse
        if (!in_array($contractor1Warehouse['id'], $warehouseIds)) {
            return ['success' => false, 'message' => 'Contractor cannot see own warehouse'];
        }
        
        // Contractor1 should NOT see contractor2's warehouse
        if (in_array($contractor2Warehouse['id'], $warehouseIds)) {
            return ['success' => false, 'message' => 'Contractor can see other contractor warehouse'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Contractor user can only access delegated inventory
     */
    private function testContractorAccessDelegatedInventory(): array {
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractor['id']);
        $contractorRole = $this->createTestRole('Contractor Manager', 5);
        $contractorUser = $this->createTestUser($contractor['id'], $contractorRole['id']);
        
        // Create product and asset delegated to contractor
        $product = $this->createTestProduct(true);
        $contractorAsset = $this->createTestAssetWithHolder(
            $product['id'],
            $contractorWarehouse['id'],
            'company',
            $contractor['id']
        );
        
        // Get accessible inventory for contractor user
        $inventory = $this->inventoryAccessService->getAccessibleInventory($contractorUser['id']);
        $assetIds = array_column($inventory['assets'] ?? [], 'id');
        
        // Contractor should see their delegated asset
        if (!in_array($contractorAsset['id'], $assetIds)) {
            return ['success' => false, 'message' => 'Contractor cannot see delegated inventory'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Contractor user cannot access other company inventory
     */
    private function testContractorCannotAccessOtherCompanyInventory(): array {
        // Create ADV company with warehouse and asset
        $advCompany = $this->createTestCompany('ADV');
        $advWarehouse = $this->createTestWarehouse($advCompany['id']);
        
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        $contractorRole = $this->createTestRole('Contractor Manager', 5);
        $contractorUser = $this->createTestUser($contractor['id'], $contractorRole['id']);
        
        // Create product and asset in ADV warehouse
        $product = $this->createTestProduct(true);
        $advAsset = $this->createTestAsset($product['id'], $advWarehouse['id']);
        
        // Get accessible inventory for contractor user
        $inventory = $this->inventoryAccessService->getAccessibleInventory($contractorUser['id']);
        $assetIds = array_column($inventory['assets'] ?? [], 'id');
        
        // Contractor should NOT see ADV asset
        if (in_array($advAsset['id'], $assetIds)) {
            return ['success' => false, 'message' => 'Contractor can see ADV inventory'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Contractor user can dispatch to own engineers
     */
    private function testContractorCanDispatchToOwnEngineers(): array {
        // Create contractor company with warehouse and users
        $contractor = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractor['id']);
        $contractorRole = $this->createTestRole('Contractor Manager', 5);
        $contractorUser = $this->createTestUser($contractor['id'], $contractorRole['id']);
        
        $engineerRole = $this->createTestRole('Engineer', 1);
        $engineer = $this->createTestUser($contractor['id'], $engineerRole['id']);
        
        // Create another contractor with engineer
        $otherContractor = $this->createTestCompany('CONTRACTOR');
        $otherEngineer = $this->createTestUser($otherContractor['id'], $engineerRole['id']);
        
        // Contractor should be able to dispatch to own engineer
        $canDispatchToOwnEngineer = $this->inventoryAccessService->canDispatchTo($contractorUser['id'], $engineer['id'], 'user');
        if (!$canDispatchToOwnEngineer) {
            return ['success' => false, 'message' => 'Contractor cannot dispatch to own engineer'];
        }
        
        // Contractor should NOT be able to dispatch to other contractor's engineer
        $canDispatchToOtherEngineer = $this->inventoryAccessService->canDispatchTo($contractorUser['id'], $otherEngineer['id'], 'user');
        if ($canDispatchToOtherEngineer) {
            return ['success' => false, 'message' => 'Contractor can dispatch to other contractor engineer'];
        }
        
        return ['success' => true];
    }
    
    // ==================== Engineer User Tests (Requirement 8.3) ====================
    
    /**
     * Test: Engineer can only access assigned items
     */
    private function testEngineerAccessAssignedItems(): array {
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractor['id']);
        
        // Create engineer
        $engineerRole = $this->createTestRole('Engineer', 1);
        $engineer = $this->createTestUser($contractor['id'], $engineerRole['id']);
        
        // Create product and assets
        $product = $this->createTestProduct(true);
        
        // Asset assigned to engineer
        $assignedAsset = $this->createTestAssetWithHolder(
            $product['id'],
            $contractorWarehouse['id'],
            'user',
            $engineer['id']
        );
        
        // Asset in warehouse (not assigned)
        $warehouseAsset = $this->createTestAsset($product['id'], $contractorWarehouse['id']);
        
        // Get accessible inventory for engineer
        $inventory = $this->inventoryAccessService->getAccessibleInventory($engineer['id']);
        $assetIds = array_column($inventory['assets'] ?? [], 'id');
        
        // Engineer should see assigned asset
        if (!in_array($assignedAsset['id'], $assetIds)) {
            return ['success' => false, 'message' => 'Engineer cannot see assigned asset'];
        }
        
        // Engineer should NOT see warehouse asset
        if (in_array($warehouseAsset['id'], $assetIds)) {
            return ['success' => false, 'message' => 'Engineer can see unassigned warehouse asset'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Engineer cannot dispatch
     */
    private function testEngineerCannotDispatch(): array {
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractor['id']);
        
        // Create engineer
        $engineerRole = $this->createTestRole('Engineer', 1);
        $engineer = $this->createTestUser($contractor['id'], $engineerRole['id']);
        
        // Engineer should NOT be able to dispatch from warehouse
        $canDispatchFrom = $this->inventoryAccessService->canDispatchFrom($engineer['id'], $contractorWarehouse['id']);
        if ($canDispatchFrom) {
            return ['success' => false, 'message' => 'Engineer can dispatch from warehouse'];
        }
        
        // Engineer should NOT be able to dispatch to any destination
        $canDispatchTo = $this->inventoryAccessService->canDispatchTo($engineer['id'], $contractorWarehouse['id'], 'warehouse');
        if ($canDispatchTo) {
            return ['success' => false, 'message' => 'Engineer can dispatch to warehouse'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Engineer has limited status update capabilities
     */
    private function testEngineerLimitedStatusUpdates(): array {
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractor['id']);
        
        // Create engineer
        $engineerRole = $this->createTestRole('Engineer', 1);
        $engineer = $this->createTestUser($contractor['id'], $engineerRole['id']);
        
        // Create product and asset assigned to engineer
        $product = $this->createTestProduct(true);
        $asset = $this->createTestAssetWithHolder(
            $product['id'],
            $contractorWarehouse['id'],
            'user',
            $engineer['id']
        );
        
        // Engineer should be able to update to 'in_use'
        $canUpdateToInUse = $this->inventoryAccessService->canUpdateAssetStatus($engineer['id'], $asset['id'], 'in_use');
        if (!$canUpdateToInUse['success']) {
            return ['success' => false, 'message' => 'Engineer cannot update to in_use status'];
        }
        
        // Engineer should be able to update to 'returned'
        $canUpdateToReturned = $this->inventoryAccessService->canUpdateAssetStatus($engineer['id'], $asset['id'], 'returned');
        if (!$canUpdateToReturned['success']) {
            return ['success' => false, 'message' => 'Engineer cannot update to returned status'];
        }
        
        // Engineer should NOT be able to update to 'scrapped'
        $canUpdateToScrapped = $this->inventoryAccessService->canUpdateAssetStatus($engineer['id'], $asset['id'], 'scrapped');
        if ($canUpdateToScrapped['success']) {
            return ['success' => false, 'message' => 'Engineer can update to scrapped status'];
        }
        
        // Engineer should NOT be able to update to 'under_repair'
        $canUpdateToRepair = $this->inventoryAccessService->canUpdateAssetStatus($engineer['id'], $asset['id'], 'under_repair');
        if ($canUpdateToRepair['success']) {
            return ['success' => false, 'message' => 'Engineer can update to under_repair status'];
        }
        
        return ['success' => true];
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Generate random string
     */
    private function generateRandomString(int $length = 10): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $result;
    }
    
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
    private function cleanupTestData() {
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
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new InventoryAccessControlTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

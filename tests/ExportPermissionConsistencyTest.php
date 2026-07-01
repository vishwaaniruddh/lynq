<?php
/**
 * Property Test: Export Permission Consistency
 * 
 * **Feature: adv-crm-inventory-module, Property 15: Export Permission Consistency**
 * **Validates: Requirements 15.2**
 * 
 * Property: For any inventory export, the exported records SHALL match exactly 
 * the records visible in the UI for that user.
 * 
 * This test verifies that:
 * 1. ADV users export all inventory data
 * 2. Contractor users only export inventory delegated to their company
 * 3. Engineers only export items assigned to them
 * 4. Export data matches what getAccessibleInventory returns
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InventoryExportService.php';
require_once __DIR__ . '/../services/InventoryAccessService.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Role.php';

class ExportPermissionConsistencyTest {
    private $exportService;
    private $accessService;
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
    private $iterations = 10; // Number of property test iterations
    
    public function __construct() {
        $this->exportService = new InventoryExportService();
        $this->accessService = new InventoryAccessService();
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
        echo "\n=== Export Permission Consistency Property Tests ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 15: Export Permission Consistency**\n";
        echo "**Validates: Requirements 15.2**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'ADV user export matches accessible inventory',
            [$this, 'testAdvUserExportMatchesAccessible']
        );
        
        $this->runPropertyTest(
            'Contractor user export matches accessible inventory',
            [$this, 'testContractorExportMatchesAccessible']
        );
        
        $this->runPropertyTest(
            'Engineer user export matches accessible inventory',
            [$this, 'testEngineerExportMatchesAccessible']
        );
        
        $this->runPropertyTest(
            'Export excludes inaccessible inventory',
            [$this, 'testExportExcludesInaccessible']
        );
        
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
     * Run a property test with multiple iterations
     */
    private function runPropertyTest(string $name, callable $testFunction): void {
        echo "Testing: $name\n";
        $failures = [];
        
        for ($i = 0; $i < $this->iterations; $i++) {
            try {
                $result = $testFunction();
                if (!$result['success']) {
                    $failures[] = "Iteration $i: {$result['message']}";
                }
            } catch (Exception $e) {
                $failures[] = "Iteration $i: Exception - {$e->getMessage()}";
            }
        }
        
        if (empty($failures)) {
            echo "  ✓ Passed ({$this->iterations} iterations)\n";
            $this->testResults[$name] = true;
        } else {
            echo "  ✗ Failed\n";
            foreach (array_slice($failures, 0, 3) as $failure) {
                echo "    - $failure\n";
            }
            if (count($failures) > 3) {
                echo "    ... and " . (count($failures) - 3) . " more failures\n";
            }
            $this->testResults[$name] = false;
        }
    }

    
    /**
     * Property Test: ADV user export matches accessible inventory
     * For any ADV user, exported assets should match getAccessibleInventory results
     */
    private function testAdvUserExportMatchesAccessible(): array {
        // Create ADV company and user
        $advCompany = $this->createTestCompany('ADV');
        $advRole = $this->createTestRole('ADV Admin', 10);
        $advUser = $this->createTestUser($advCompany['id'], $advRole['id']);
        
        // Create random number of warehouses and assets
        $numWarehouses = rand(1, 3);
        $numAssetsPerWarehouse = rand(1, 3);
        
        for ($w = 0; $w < $numWarehouses; $w++) {
            $warehouse = $this->createTestWarehouse($advCompany['id']);
            $product = $this->createTestProduct(true);
            
            for ($a = 0; $a < $numAssetsPerWarehouse; $a++) {
                $this->createTestAsset($product['id'], $warehouse['id']);
            }
        }
        
        // Get accessible inventory via service
        $accessibleInventory = $this->accessService->getAccessibleInventory($advUser['id']);
        $accessibleAssetIds = array_column($accessibleInventory['assets'] ?? [], 'id');
        
        // Get exported data
        $exportResult = $this->exportService->export($advUser['id'], 'assets', 'json');
        
        if (!$exportResult['success']) {
            return ['success' => false, 'message' => 'Export failed: ' . $exportResult['message']];
        }
        
        // Parse exported JSON
        $exportedData = json_decode($exportResult['data']['content'], true);
        $exportedAssetIds = array_column($exportedData['data'] ?? [], 'id');
        
        // Verify exported assets match accessible assets
        sort($accessibleAssetIds);
        sort($exportedAssetIds);
        
        if ($accessibleAssetIds !== $exportedAssetIds) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Export mismatch: accessible=%d, exported=%d',
                    count($accessibleAssetIds),
                    count($exportedAssetIds)
                )
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Contractor user export matches accessible inventory
     * For any contractor user, exported assets should only include delegated inventory
     */
    private function testContractorExportMatchesAccessible(): array {
        // Create ADV company with warehouse and assets
        $advCompany = $this->createTestCompany('ADV');
        $advWarehouse = $this->createTestWarehouse($advCompany['id']);
        $product = $this->createTestProduct(true);
        
        // Create some ADV assets (should NOT be in contractor export)
        $numAdvAssets = rand(1, 3);
        for ($i = 0; $i < $numAdvAssets; $i++) {
            $this->createTestAsset($product['id'], $advWarehouse['id']);
        }
        
        // Create contractor company with warehouse
        $contractor = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractor['id']);
        $contractorRole = $this->createTestRole('Contractor Manager', 5);
        $contractorUser = $this->createTestUser($contractor['id'], $contractorRole['id']);
        
        // Create contractor assets (should be in export)
        $numContractorAssets = rand(1, 3);
        $contractorAssetIds = [];
        for ($i = 0; $i < $numContractorAssets; $i++) {
            $asset = $this->createTestAssetWithHolder(
                $product['id'],
                $contractorWarehouse['id'],
                'company',
                $contractor['id']
            );
            $contractorAssetIds[] = $asset['id'];
        }
        
        // Get accessible inventory via service
        $accessibleInventory = $this->accessService->getAccessibleInventory($contractorUser['id']);
        $accessibleAssetIds = array_column($accessibleInventory['assets'] ?? [], 'id');
        
        // Get exported data
        $exportResult = $this->exportService->export($contractorUser['id'], 'assets', 'json');
        
        if (!$exportResult['success']) {
            return ['success' => false, 'message' => 'Export failed: ' . $exportResult['message']];
        }
        
        // Parse exported JSON
        $exportedData = json_decode($exportResult['data']['content'], true);
        $exportedAssetIds = array_column($exportedData['data'] ?? [], 'id');
        
        // Verify exported assets match accessible assets
        sort($accessibleAssetIds);
        sort($exportedAssetIds);
        
        if ($accessibleAssetIds !== $exportedAssetIds) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Export mismatch: accessible=%d, exported=%d',
                    count($accessibleAssetIds),
                    count($exportedAssetIds)
                )
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Engineer user export matches accessible inventory
     * For any engineer, exported assets should only include assigned items
     */
    private function testEngineerExportMatchesAccessible(): array {
        // Create contractor company
        $contractor = $this->createTestCompany('CONTRACTOR');
        $contractorWarehouse = $this->createTestWarehouse($contractor['id']);
        
        // Create engineer
        $engineerRole = $this->createTestRole('Engineer', 1);
        $engineer = $this->createTestUser($contractor['id'], $engineerRole['id']);
        
        // Create product
        $product = $this->createTestProduct(true);
        
        // Create some warehouse assets (should NOT be in engineer export)
        $numWarehouseAssets = rand(1, 3);
        for ($i = 0; $i < $numWarehouseAssets; $i++) {
            $this->createTestAsset($product['id'], $contractorWarehouse['id']);
        }
        
        // Create assets assigned to engineer (should be in export)
        $numAssignedAssets = rand(1, 3);
        $assignedAssetIds = [];
        for ($i = 0; $i < $numAssignedAssets; $i++) {
            $asset = $this->createTestAssetWithHolder(
                $product['id'],
                $contractorWarehouse['id'],
                'user',
                $engineer['id']
            );
            $assignedAssetIds[] = $asset['id'];
        }
        
        // Get accessible inventory via service
        $accessibleInventory = $this->accessService->getAccessibleInventory($engineer['id']);
        $accessibleAssetIds = array_column($accessibleInventory['assets'] ?? [], 'id');
        
        // Get exported data
        $exportResult = $this->exportService->export($engineer['id'], 'assets', 'json');
        
        if (!$exportResult['success']) {
            return ['success' => false, 'message' => 'Export failed: ' . $exportResult['message']];
        }
        
        // Parse exported JSON
        $exportedData = json_decode($exportResult['data']['content'], true);
        $exportedAssetIds = array_column($exportedData['data'] ?? [], 'id');
        
        // Verify exported assets match accessible assets
        sort($accessibleAssetIds);
        sort($exportedAssetIds);
        
        if ($accessibleAssetIds !== $exportedAssetIds) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Export mismatch: accessible=%d, exported=%d',
                    count($accessibleAssetIds),
                    count($exportedAssetIds)
                )
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Export excludes inaccessible inventory
     * For any user, export should never include assets from inaccessible warehouses
     */
    private function testExportExcludesInaccessible(): array {
        // Create two contractor companies
        $contractor1 = $this->createTestCompany('CONTRACTOR');
        $contractor1Warehouse = $this->createTestWarehouse($contractor1['id']);
        $contractor1Role = $this->createTestRole('Contractor Manager', 5);
        $contractor1User = $this->createTestUser($contractor1['id'], $contractor1Role['id']);
        
        $contractor2 = $this->createTestCompany('CONTRACTOR');
        $contractor2Warehouse = $this->createTestWarehouse($contractor2['id']);
        
        // Create product
        $product = $this->createTestProduct(true);
        
        // Create assets for contractor1 (should be in export)
        $numContractor1Assets = rand(1, 3);
        for ($i = 0; $i < $numContractor1Assets; $i++) {
            $this->createTestAssetWithHolder(
                $product['id'],
                $contractor1Warehouse['id'],
                'company',
                $contractor1['id']
            );
        }
        
        // Create assets for contractor2 (should NOT be in contractor1's export)
        $numContractor2Assets = rand(1, 3);
        $contractor2AssetIds = [];
        for ($i = 0; $i < $numContractor2Assets; $i++) {
            $asset = $this->createTestAssetWithHolder(
                $product['id'],
                $contractor2Warehouse['id'],
                'company',
                $contractor2['id']
            );
            $contractor2AssetIds[] = $asset['id'];
        }
        
        // Get exported data for contractor1
        $exportResult = $this->exportService->export($contractor1User['id'], 'assets', 'json');
        
        if (!$exportResult['success']) {
            return ['success' => false, 'message' => 'Export failed: ' . $exportResult['message']];
        }
        
        // Parse exported JSON
        $exportedData = json_decode($exportResult['data']['content'], true);
        $exportedAssetIds = array_column($exportedData['data'] ?? [], 'id');
        
        // Verify contractor2's assets are NOT in contractor1's export
        $intersection = array_intersect($exportedAssetIds, $contractor2AssetIds);
        
        if (!empty($intersection)) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Export contains %d inaccessible assets',
                    count($intersection)
                )
            ];
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
        
        // Reset arrays
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
    $test = new ExportPermissionConsistencyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

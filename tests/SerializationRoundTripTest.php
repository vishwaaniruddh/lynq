<?php
/**
 * Property Test: Serialization Round-Trip
 * 
 * **Feature: adv-crm-inventory-module, Property 16: Serialization Round-Trip**
 * **Validates: Requirements 15.4**
 * 
 * Property: For any valid inventory record, serializing to export format and 
 * deserializing back SHALL produce an equivalent record.
 * 
 * This test verifies that:
 * 1. JSON export/import produces equivalent records
 * 2. CSV export/import produces equivalent records
 * 3. All field types are preserved through serialization
 * 4. Null values are handled correctly
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InventoryExportService.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Role.php';

class SerializationRoundTripTest {
    private $exportService;
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
    private $iterations = 20; // Number of property test iterations
    
    public function __construct() {
        $this->exportService = new InventoryExportService();
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
        echo "\n=== Serialization Round-Trip Property Tests ===\n";
        echo "**Feature: adv-crm-inventory-module, Property 16: Serialization Round-Trip**\n";
        echo "**Validates: Requirements 15.4**\n\n";
        
        // Property tests
        $this->runPropertyTest(
            'JSON round-trip preserves asset records',
            [$this, 'testJsonRoundTripAssets']
        );
        
        $this->runPropertyTest(
            'CSV round-trip preserves asset records',
            [$this, 'testCsvRoundTripAssets']
        );
        
        $this->runPropertyTest(
            'JSON round-trip preserves stock records',
            [$this, 'testJsonRoundTripStock']
        );
        
        $this->runPropertyTest(
            'Null values are preserved through serialization',
            [$this, 'testNullValuePreservation']
        );
        
        $this->runPropertyTest(
            'Special characters are preserved through serialization',
            [$this, 'testSpecialCharacterPreservation']
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
     * Property Test: JSON round-trip preserves asset records
     * For any asset record, export to JSON and parse back should produce equivalent record
     */
    private function testJsonRoundTripAssets(): array {
        // Create test data
        $company = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true);
        $role = $this->createTestRole('Admin', 10);
        $user = $this->createTestUser($company['id'], $role['id']);
        
        // Create random asset with various field values
        $originalAsset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Export to JSON
        $exportResult = $this->exportService->export($user['id'], 'assets', 'json');
        
        if (!$exportResult['success']) {
            return ['success' => false, 'message' => 'Export failed: ' . $exportResult['message']];
        }
        
        // Parse JSON back
        $parseResult = $this->exportService->parseImport(
            $exportResult['data']['content'],
            'json',
            'assets'
        );
        
        if (!$parseResult['success']) {
            return ['success' => false, 'message' => 'Parse failed: ' . $parseResult['message']];
        }
        
        // Find the original asset in parsed data
        $parsedRecords = $parseResult['data']['records'] ?? [];
        $foundAsset = null;
        
        foreach ($parsedRecords as $record) {
            if ($record['id'] == $originalAsset['id']) {
                $foundAsset = $record;
                break;
            }
        }
        
        if (!$foundAsset) {
            return ['success' => false, 'message' => 'Original asset not found in parsed data'];
        }
        
        // Verify key fields are preserved
        $fieldsToCheck = ['serial_number', 'product_id', 'warehouse_id', 'status', 'working_condition'];
        
        foreach ($fieldsToCheck as $field) {
            $originalValue = $originalAsset[$field] ?? null;
            $parsedValue = $foundAsset[$field] ?? null;
            
            // Handle type coercion for comparison
            if (is_numeric($originalValue) && is_numeric($parsedValue)) {
                if ((int)$originalValue !== (int)$parsedValue) {
                    return [
                        'success' => false,
                        'message' => "Field '$field' mismatch: original=$originalValue, parsed=$parsedValue"
                    ];
                }
            } elseif ($originalValue !== $parsedValue) {
                return [
                    'success' => false,
                    'message' => "Field '$field' mismatch: original=$originalValue, parsed=$parsedValue"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: CSV round-trip preserves asset records
     * For any asset record, export to CSV and parse back should produce equivalent record
     */
    private function testCsvRoundTripAssets(): array {
        // Create test data
        $company = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true);
        $role = $this->createTestRole('Admin', 10);
        $user = $this->createTestUser($company['id'], $role['id']);
        
        // Create random asset
        $originalAsset = $this->createTestAsset($product['id'], $warehouse['id']);
        
        // Export to CSV
        $exportResult = $this->exportService->export($user['id'], 'assets', 'csv');
        
        if (!$exportResult['success']) {
            return ['success' => false, 'message' => 'Export failed: ' . $exportResult['message']];
        }
        
        // Parse CSV back
        $parseResult = $this->exportService->parseImport(
            $exportResult['data']['content'],
            'csv',
            'assets'
        );
        
        if (!$parseResult['success']) {
            return ['success' => false, 'message' => 'Parse failed: ' . $parseResult['message']];
        }
        
        // Find the original asset in parsed data
        $parsedRecords = $parseResult['data']['records'] ?? [];
        $foundAsset = null;
        
        foreach ($parsedRecords as $record) {
            if ($record['id'] == $originalAsset['id']) {
                $foundAsset = $record;
                break;
            }
        }
        
        if (!$foundAsset) {
            return ['success' => false, 'message' => 'Original asset not found in parsed data'];
        }
        
        // Verify key fields are preserved
        $fieldsToCheck = ['serial_number', 'product_id', 'warehouse_id', 'status'];
        
        foreach ($fieldsToCheck as $field) {
            $originalValue = (string)($originalAsset[$field] ?? '');
            $parsedValue = (string)($foundAsset[$field] ?? '');
            
            if ($originalValue !== $parsedValue) {
                return [
                    'success' => false,
                    'message' => "Field '$field' mismatch: original=$originalValue, parsed=$parsedValue"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: JSON round-trip preserves stock records
     */
    private function testJsonRoundTripStock(): array {
        // Create test data
        $company = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(false); // Non-serializable
        $role = $this->createTestRole('Admin', 10);
        $user = $this->createTestUser($company['id'], $role['id']);
        
        // Create stock entry
        $stockRepository = new StockRepository();
        $originalStock = $stockRepository->addQuantity(
            $product['id'],
            $warehouse['id'],
            rand(10, 100),
            $user['id']
        );
        
        // Export to JSON
        $exportResult = $this->exportService->export($user['id'], 'stock', 'json');
        
        if (!$exportResult['success']) {
            return ['success' => false, 'message' => 'Export failed: ' . $exportResult['message']];
        }
        
        // Parse JSON back
        $parseResult = $this->exportService->parseImport(
            $exportResult['data']['content'],
            'json',
            'stock'
        );
        
        if (!$parseResult['success']) {
            return ['success' => false, 'message' => 'Parse failed: ' . $parseResult['message']];
        }
        
        // Find the original stock in parsed data
        $parsedRecords = $parseResult['data']['records'] ?? [];
        $foundStock = null;
        
        foreach ($parsedRecords as $record) {
            if ($record['product_id'] == $product['id'] && $record['warehouse_id'] == $warehouse['id']) {
                $foundStock = $record;
                break;
            }
        }
        
        if (!$foundStock) {
            return ['success' => false, 'message' => 'Original stock not found in parsed data'];
        }
        
        // Verify quantity is preserved
        if ((int)$foundStock['quantity'] !== (int)$originalStock['quantity']) {
            return [
                'success' => false,
                'message' => "Quantity mismatch: original={$originalStock['quantity']}, parsed={$foundStock['quantity']}"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Null values are preserved through serialization
     */
    private function testNullValuePreservation(): array {
        // Create test data
        $company = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true);
        $role = $this->createTestRole('Admin', 10);
        $user = $this->createTestUser($company['id'], $role['id']);
        
        // Create asset with null optional fields
        $assetData = [
            'product_id' => $product['id'],
            'warehouse_id' => $warehouse['id'],
            'serial_number' => 'SN-' . $this->generateRandomString(12),
            'status' => AssetRepository::STATUS_IN_STOCK,
            'working_condition' => AssetRepository::CONDITION_WORKING,
            'current_holder_type' => AssetRepository::HOLDER_WAREHOUSE,
            'current_holder_id' => $warehouse['id'],
            'source_warehouse_id' => $warehouse['id'],
            'warranty_expiry' => null, // Null value
            'notes' => null // Null value
        ];
        
        $originalAsset = $this->assetRepository->create($assetData);
        $this->createdAssetIds[] = $originalAsset['id'];
        
        // Export to JSON
        $exportResult = $this->exportService->export($user['id'], 'assets', 'json');
        
        if (!$exportResult['success']) {
            return ['success' => false, 'message' => 'Export failed: ' . $exportResult['message']];
        }
        
        // Parse JSON back
        $parseResult = $this->exportService->parseImport(
            $exportResult['data']['content'],
            'json',
            'assets'
        );
        
        if (!$parseResult['success']) {
            return ['success' => false, 'message' => 'Parse failed: ' . $parseResult['message']];
        }
        
        // Find the original asset in parsed data
        $parsedRecords = $parseResult['data']['records'] ?? [];
        $foundAsset = null;
        
        foreach ($parsedRecords as $record) {
            if ($record['id'] == $originalAsset['id']) {
                $foundAsset = $record;
                break;
            }
        }
        
        if (!$foundAsset) {
            return ['success' => false, 'message' => 'Original asset not found in parsed data'];
        }
        
        // Verify null fields are preserved (as null or empty string)
        $nullableFields = ['warranty_expiry', 'notes'];
        
        foreach ($nullableFields as $field) {
            $parsedValue = $foundAsset[$field] ?? null;
            // Accept null or empty string as equivalent to null
            if ($parsedValue !== null && $parsedValue !== '') {
                return [
                    'success' => false,
                    'message' => "Null field '$field' was not preserved: got '$parsedValue'"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Special characters are preserved through serialization
     */
    private function testSpecialCharacterPreservation(): array {
        // Create test data
        $company = $this->createTestCompany('ADV');
        $warehouse = $this->createTestWarehouse($company['id']);
        $product = $this->createTestProduct(true);
        $role = $this->createTestRole('Admin', 10);
        $user = $this->createTestUser($company['id'], $role['id']);
        
        // Create asset with special characters in notes
        $specialChars = 'Test with "quotes", commas, and special chars: <>&\'';
        $assetData = [
            'product_id' => $product['id'],
            'warehouse_id' => $warehouse['id'],
            'serial_number' => 'SN-' . $this->generateRandomString(12),
            'status' => AssetRepository::STATUS_IN_STOCK,
            'working_condition' => AssetRepository::CONDITION_WORKING,
            'current_holder_type' => AssetRepository::HOLDER_WAREHOUSE,
            'current_holder_id' => $warehouse['id'],
            'source_warehouse_id' => $warehouse['id'],
            'notes' => $specialChars
        ];
        
        $originalAsset = $this->assetRepository->create($assetData);
        $this->createdAssetIds[] = $originalAsset['id'];
        
        // Export to JSON
        $exportResult = $this->exportService->export($user['id'], 'assets', 'json');
        
        if (!$exportResult['success']) {
            return ['success' => false, 'message' => 'Export failed: ' . $exportResult['message']];
        }
        
        // Parse JSON back
        $parseResult = $this->exportService->parseImport(
            $exportResult['data']['content'],
            'json',
            'assets'
        );
        
        if (!$parseResult['success']) {
            return ['success' => false, 'message' => 'Parse failed: ' . $parseResult['message']];
        }
        
        // Find the original asset in parsed data
        $parsedRecords = $parseResult['data']['records'] ?? [];
        $foundAsset = null;
        
        foreach ($parsedRecords as $record) {
            if ($record['id'] == $originalAsset['id']) {
                $foundAsset = $record;
                break;
            }
        }
        
        if (!$foundAsset) {
            return ['success' => false, 'message' => 'Original asset not found in parsed data'];
        }
        
        // Verify special characters are preserved
        if ($foundAsset['notes'] !== $specialChars) {
            return [
                'success' => false,
                'message' => "Special characters not preserved: expected='$specialChars', got='{$foundAsset['notes']}'"
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
    $test = new SerializationRoundTripTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}

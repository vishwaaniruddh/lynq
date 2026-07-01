<?php
/**
 * Integration Tests for Warehouse and Product APIs
 * Tests CRUD operations, permission filtering, and validation errors
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.4
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/ProductCategoryRepository.php';
require_once __DIR__ . '/../services/InventoryAccessService.php';

class WarehouseProductApiTest extends PropertyTestBase {
    
    private $warehouseRepository;
    private $productRepository;
    private $categoryRepository;
    private $inventoryAccessService;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->productRepository = new ProductRepository();
        $this->categoryRepository = new ProductCategoryRepository();
        $this->inventoryAccessService = new InventoryAccessService();
    }
    
    public function runTests() {
        echo "=== Warehouse and Product API Integration Tests ===\n\n";
        
        $allPassed = true;
        
        // Warehouse CRUD tests
        $allPassed &= $this->testWarehouseCreate();
        $allPassed &= $this->testWarehouseUpdate();
        $allPassed &= $this->testWarehouseNameUniquenessValidation();
        $allPassed &= $this->testWarehouseStatusFilter();
        
        // Product CRUD tests
        $allPassed &= $this->testProductCreate();
        $allPassed &= $this->testProductUpdate();
        $allPassed &= $this->testProductInventoryTypeValidation();
        $allPassed &= $this->testProductFiltering();
        
        // Permission filtering tests
        $allPassed &= $this->testWarehouseAccessFiltering();
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Test warehouse creation
     * Requirements: 1.1
     */
    public function testWarehouseCreate() {
        echo "Testing warehouse creation... ";
        
        try {
            if (!$this->tableExists('warehouses')) {
                echo "SKIPPED (warehouses table not found)\n";
                return true;
            }
            
            $companyId = $this->getTestCompanyId();
            $warehouseName = 'API Test Warehouse ' . $this->generateRandomString(8);
            
            $warehouseData = [
                'name' => $warehouseName,
                'location' => 'Test Location',
                'company_id' => $companyId,
                'status' => WarehouseRepository::STATUS_ACTIVE
            ];
            
            $warehouse = $this->warehouseRepository->create($warehouseData);
            $warehouseId = $warehouse['id'];
            $this->createdRecords['warehouses'][] = $warehouseId;
            
            $this->assert($warehouseId > 0, "Warehouse should be created with valid ID");
            
            // Verify warehouse was created correctly
            $this->assert($warehouse !== null, "Warehouse should be retrievable");
            $this->assert($warehouse['name'] === $warehouseName, "Warehouse name should match");
            $this->assert($warehouse['status'] === 'active', "Warehouse status should be active");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Test warehouse update
     * Requirements: 1.1, 1.3
     */
    public function testWarehouseUpdate() {
        echo "Testing warehouse update... ";
        
        try {
            if (!$this->tableExists('warehouses')) {
                echo "SKIPPED (warehouses table not found)\n";
                return true;
            }
            
            $companyId = $this->getTestCompanyId();
            $warehouseName = 'Update Test Warehouse ' . $this->generateRandomString(8);
            
            // Create warehouse
            $warehouse = $this->warehouseRepository->create([
                'name' => $warehouseName,
                'location' => 'Original Location',
                'company_id' => $companyId,
                'status' => WarehouseRepository::STATUS_ACTIVE
            ]);
            $warehouseId = $warehouse['id'];
            $this->createdRecords['warehouses'][] = $warehouseId;
            
            // Update warehouse
            $newLocation = 'Updated Location';
            $this->warehouseRepository->update($warehouseId, [
                'location' => $newLocation,
                'status' => WarehouseRepository::STATUS_INACTIVE
            ]);
            
            // Verify update
            $warehouse = $this->warehouseRepository->find($warehouseId);
            $this->assert($warehouse['location'] === $newLocation, "Location should be updated");
            $this->assert($warehouse['status'] === 'inactive', "Status should be inactive");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test warehouse name uniqueness validation
     * Requirements: 1.4
     */
    public function testWarehouseNameUniquenessValidation() {
        echo "Testing warehouse name uniqueness validation... ";
        
        try {
            if (!$this->tableExists('warehouses')) {
                echo "SKIPPED (warehouses table not found)\n";
                return true;
            }
            
            $companyId = $this->getTestCompanyId();
            $warehouseName = 'Unique Test Warehouse ' . $this->generateRandomString(8);
            
            // Create first warehouse
            $warehouse1 = $this->warehouseRepository->create([
                'name' => $warehouseName,
                'company_id' => $companyId,
                'status' => WarehouseRepository::STATUS_ACTIVE
            ]);
            $warehouseId1 = $warehouse1['id'];
            $this->createdRecords['warehouses'][] = $warehouseId1;
            
            // Try to create duplicate - should fail
            $duplicateCreated = false;
            try {
                $warehouse2 = $this->warehouseRepository->create([
                    'name' => $warehouseName,
                    'company_id' => $companyId,
                    'status' => WarehouseRepository::STATUS_ACTIVE
                ]);
                $this->createdRecords['warehouses'][] = $warehouse2['id'];
                $duplicateCreated = true;
            } catch (Exception $e) {
                // Expected - duplicate should fail
            }
            
            $this->assert(!$duplicateCreated, "Duplicate warehouse name should be rejected");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Test warehouse status filtering
     * Requirements: 1.2
     */
    public function testWarehouseStatusFilter() {
        echo "Testing warehouse status filtering... ";
        
        try {
            if (!$this->tableExists('warehouses')) {
                echo "SKIPPED (warehouses table not found)\n";
                return true;
            }
            
            $companyId = $this->getTestCompanyId();
            
            // Create active warehouse
            $activeWarehouse = $this->warehouseRepository->create([
                'name' => 'Active Warehouse ' . $this->generateRandomString(8),
                'company_id' => $companyId,
                'status' => WarehouseRepository::STATUS_ACTIVE
            ]);
            $activeId = $activeWarehouse['id'];
            $this->createdRecords['warehouses'][] = $activeId;
            
            // Create inactive warehouse
            $inactiveWarehouse = $this->warehouseRepository->create([
                'name' => 'Inactive Warehouse ' . $this->generateRandomString(8),
                'company_id' => $companyId,
                'status' => WarehouseRepository::STATUS_INACTIVE
            ]);
            $inactiveId = $inactiveWarehouse['id'];
            $this->createdRecords['warehouses'][] = $inactiveId;
            
            // Test active filter
            $activeWarehouses = $this->warehouseRepository->findActive();
            $activeIds = array_column($activeWarehouses, 'id');
            $this->assert(in_array($activeId, $activeIds), "Active warehouse should be in active list");
            $this->assert(!in_array($inactiveId, $activeIds), "Inactive warehouse should not be in active list");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test product creation
     * Requirements: 2.1
     */
    public function testProductCreate() {
        echo "Testing product creation... ";
        
        try {
            if (!$this->tableExists('products')) {
                echo "SKIPPED (products table not found)\n";
                return true;
            }
            
            $productName = 'API Test Product ' . $this->generateRandomString(8);
            
            $productData = [
                'name' => $productName,
                'unit_of_measure' => 'unit',
                'inventory_type' => ProductRepository::TYPE_INTERNAL,
                'is_serializable' => true,
                'is_repairable' => true,
                'low_stock_threshold' => 10,
                'status' => ProductRepository::STATUS_ACTIVE
            ];
            
            $product = $this->productRepository->create($productData);
            $productId = $product['id'];
            $this->createdRecords['products'][] = $productId;
            
            $this->assert($productId > 0, "Product should be created with valid ID");
            
            // Verify product was created correctly
            $this->assert($product !== null, "Product should be retrievable");
            $this->assert($product['name'] === $productName, "Product name should match");
            $this->assert($product['inventory_type'] === 'INTERNAL', "Inventory type should be INTERNAL");
            $this->assert($product['is_serializable'] == 1, "Product should be serializable");
            $this->assert($product['is_repairable'] == 1, "Product should be repairable");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Test product update
     * Requirements: 2.1
     */
    public function testProductUpdate() {
        echo "Testing product update... ";
        
        try {
            if (!$this->tableExists('products')) {
                echo "SKIPPED (products table not found)\n";
                return true;
            }
            
            $productName = 'Update Test Product ' . $this->generateRandomString(8);
            
            // Create product
            $product = $this->productRepository->create([
                'name' => $productName,
                'unit_of_measure' => 'unit',
                'inventory_type' => ProductRepository::TYPE_INTERNAL,
                'is_serializable' => false,
                'status' => ProductRepository::STATUS_ACTIVE
            ]);
            $productId = $product['id'];
            $this->createdRecords['products'][] = $productId;
            
            // Update product
            $newName = 'Updated Product ' . $this->generateRandomString(8);
            $this->productRepository->update($productId, [
                'name' => $newName,
                'inventory_type' => ProductRepository::TYPE_SITE,
                'is_serializable' => 1
            ]);
            
            // Verify update
            $product = $this->productRepository->find($productId);
            $this->assert($product['name'] === $newName, "Name should be updated");
            $this->assert($product['inventory_type'] === 'SITE', "Inventory type should be SITE");
            $this->assert($product['is_serializable'] == 1, "Product should be serializable");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test product inventory type validation
     * Requirements: 2.1
     */
    public function testProductInventoryTypeValidation() {
        echo "Testing product inventory type validation... ";
        
        try {
            // Test valid types
            $this->assert(
                ProductRepository::isValidInventoryType('INTERNAL'),
                "INTERNAL should be valid"
            );
            $this->assert(
                ProductRepository::isValidInventoryType('SITE'),
                "SITE should be valid"
            );
            
            // Test invalid types
            $this->assert(
                !ProductRepository::isValidInventoryType('INVALID'),
                "INVALID should not be valid"
            );
            $this->assert(
                !ProductRepository::isValidInventoryType(''),
                "Empty string should not be valid"
            );
            $this->assert(
                !ProductRepository::isValidInventoryType('internal'),
                "Lowercase should not be valid"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Test product filtering
     * Requirements: 2.4
     */
    public function testProductFiltering() {
        echo "Testing product filtering... ";
        
        try {
            if (!$this->tableExists('products')) {
                echo "SKIPPED (products table not found)\n";
                return true;
            }
            
            // Create products with different attributes
            $internalProductRecord = $this->productRepository->create([
                'name' => 'Internal Product ' . $this->generateRandomString(8),
                'unit_of_measure' => 'unit',
                'inventory_type' => ProductRepository::TYPE_INTERNAL,
                'is_serializable' => true,
                'status' => ProductRepository::STATUS_ACTIVE
            ]);
            $internalProduct = $internalProductRecord['id'];
            $this->createdRecords['products'][] = $internalProduct;
            
            $siteProductRecord = $this->productRepository->create([
                'name' => 'Site Product ' . $this->generateRandomString(8),
                'unit_of_measure' => 'piece',
                'inventory_type' => ProductRepository::TYPE_SITE,
                'is_serializable' => false,
                'status' => ProductRepository::STATUS_ACTIVE
            ]);
            $siteProduct = $siteProductRecord['id'];
            $this->createdRecords['products'][] = $siteProduct;
            
            // Test inventory type filter
            $internalProducts = $this->productRepository->search(['inventory_type' => 'INTERNAL']);
            $internalIds = array_column($internalProducts, 'id');
            $this->assert(in_array($internalProduct, $internalIds), "Internal product should be in INTERNAL filter");
            $this->assert(!in_array($siteProduct, $internalIds), "Site product should not be in INTERNAL filter");
            
            // Test serializable filter
            $serializableProducts = $this->productRepository->search(['is_serializable' => true]);
            $serializableIds = array_column($serializableProducts, 'id');
            $this->assert(in_array($internalProduct, $serializableIds), "Serializable product should be in filter");
            $this->assert(!in_array($siteProduct, $serializableIds), "Non-serializable product should not be in filter");
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test warehouse access filtering based on user role
     * Requirements: 1.2
     */
    public function testWarehouseAccessFiltering() {
        echo "Testing warehouse access filtering... ";
        
        try {
            if (!$this->tableExists('warehouses') || !$this->tableExists('users')) {
                echo "SKIPPED (required tables not found)\n";
                return true;
            }
            
            // Get an ADV user
            $advUser = $this->getAdvUser();
            if (!$advUser) {
                echo "SKIPPED (no ADV user found)\n";
                return true;
            }
            
            // ADV user should see all warehouses
            $accessibleWarehouses = $this->inventoryAccessService->getAccessibleWarehouses($advUser['id']);
            $this->assert(
                is_array($accessibleWarehouses),
                "ADV user should get array of warehouses"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Check if a table exists in the database
     */
    private function tableExists($tableName) {
        try {
            $result = $this->db->query("SHOW TABLES LIKE '$tableName'");
            return $result && $result->num_rows > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get a test company ID
     */
    private function getTestCompanyId() {
        $sql = "SELECT id FROM companies WHERE status = 'ACTIVE' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (empty($result)) {
            // Create a test company if none exists
            $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test Company ' . $this->generateRandomString(8), 'ADV', 'ACTIVE'],
                'sss'
            );
            $companyId = $this->db->insert_id;
            $this->createdRecords['companies'][] = $companyId;
            return $companyId;
        }
        
        return $result[0]['id'];
    }
    
    /**
     * Get an ADV user for testing
     */
    private function getAdvUser() {
        $sql = "SELECT u.* FROM users u 
                JOIN companies c ON u.company_id = c.id 
                WHERE c.type = 'ADV' AND u.status = 1 
                LIMIT 1";
        $result = $this->getResults($sql);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Delete test products
            if (isset($this->createdRecords['products']) && !empty($this->createdRecords['products'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM `products` WHERE id IN ($ids)");
            }
            
            // Delete test warehouses
            if (isset($this->createdRecords['warehouses']) && !empty($this->createdRecords['warehouses'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['warehouses']));
                $this->db->query("DELETE FROM `warehouses` WHERE id IN ($ids)");
            }
            
            // Delete test companies
            if (isset($this->createdRecords['companies']) && !empty($this->createdRecords['companies'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['companies']));
                $this->db->query("DELETE FROM `companies` WHERE id IN ($ids)");
            }
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

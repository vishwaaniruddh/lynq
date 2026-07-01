<?php
/**
 * Property Test for Serial Number Global Uniqueness
 * **Feature: adv-crm-inventory-module, Property 2: Serial Number Global Uniqueness**
 * **Validates: Requirements 3.3**
 * 
 * For any two assets in the system, their serial numbers SHALL be distinct 
 * regardless of product or warehouse.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';

class SerialNumberUniquenessTest extends PropertyTestBase {
    
    private $assetRepository;
    private $productRepository;
    private $warehouseRepository;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->assetRepository = new AssetRepository();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
    }
    
    public function runTests() {
        echo "=== Serial Number Global Uniqueness Property Tests ===\n\n";
        
        // Check if required tables exist
        if (!$this->tableExists('assets') || !$this->tableExists('products') || !$this->tableExists('warehouses')) {
            echo "SKIPPED: Required tables (assets, products, warehouses) not found - run migrations first\n";
            echo "Property tests require database tables to be created.\n";
            return true; // Skip but don't fail
        }
        
        $allPassed = true;
        
        // Test duplicate serial number on create
        $allPassed &= $this->runPropertyTest(
            "Serial Number Uniqueness on Create",
            [$this, 'testSerialNumberUniquenessOnCreate']
        );
        
        // Test duplicate serial number across different products
        $allPassed &= $this->runPropertyTest(
            "Serial Number Uniqueness Across Products",
            [$this, 'testSerialNumberUniquenessAcrossProducts']
        );
        
        // Test duplicate serial number across different warehouses
        $allPassed &= $this->runPropertyTest(
            "Serial Number Uniqueness Across Warehouses",
            [$this, 'testSerialNumberUniquenessAcrossWarehouses']
        );
        
        // Test duplicate serial number on update
        $allPassed &= $this->runPropertyTest(
            "Serial Number Uniqueness on Update",
            [$this, 'testSerialNumberUniquenessOnUpdate']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
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
     * Property 2: Serial Number Uniqueness on Create
     * Creating an asset with a duplicate serial number should fail
     * **Feature: adv-crm-inventory-module, Property 2: Serial Number Global Uniqueness**
     * **Validates: Requirements 3.3**
     */
    public function testSerialNumberUniquenessOnCreate() {
        try {
            // Get test product and warehouse
            $productId = $this->getTestProductId();
            $warehouseId = $this->getTestWarehouseId();
            
            // Generate unique serial number
            $serialNumber = 'SN-' . $this->generateRandomString(15);
            
            // Create first asset
            $asset1 = $this->assetRepository->create([
                'product_id' => $productId,
                'serial_number' => $serialNumber,
                'warehouse_id' => $warehouseId,
                'status' => 'in_stock',
                'working_condition' => 'working',
                'current_holder_type' => 'warehouse',
                'current_holder_id' => $warehouseId,
                'source_warehouse_id' => $warehouseId
            ]);
            $this->createdRecords['assets'][] = $asset1['id'];
            
            // Try to create second asset with same serial number
            $duplicateCreated = false;
            try {
                $asset2 = $this->assetRepository->create([
                    'product_id' => $productId,
                    'serial_number' => $serialNumber,
                    'warehouse_id' => $warehouseId,
                    'status' => 'in_stock',
                    'working_condition' => 'working',
                    'current_holder_type' => 'warehouse',
                    'current_holder_id' => $warehouseId,
                    'source_warehouse_id' => $warehouseId
                ]);
                $duplicateCreated = true;
                $this->createdRecords['assets'][] = $asset2['id'];
            } catch (Exception $e) {
                // Expected - duplicate should be rejected
                $duplicateCreated = false;
            }
            
            $this->assert(
                !$duplicateCreated,
                "Second asset creation with duplicate serial number should fail"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['serialNumber' => $serialNumber ?? null]
            ];
        }
    }
    
    /**
     * Property 2: Serial Number Uniqueness Across Products
     * Creating assets with the same serial number for different products should fail
     * **Feature: adv-crm-inventory-module, Property 2: Serial Number Global Uniqueness**
     * **Validates: Requirements 3.3**
     */
    public function testSerialNumberUniquenessAcrossProducts() {
        try {
            // Get two different test products
            $productIds = $this->getTwoTestProductIds();
            $warehouseId = $this->getTestWarehouseId();
            
            if (count($productIds) < 2) {
                return ['success' => true, 'message' => 'Skipped - need at least 2 products'];
            }
            
            // Generate unique serial number
            $serialNumber = 'SN-' . $this->generateRandomString(15);
            
            // Create asset for first product
            $asset1 = $this->assetRepository->create([
                'product_id' => $productIds[0],
                'serial_number' => $serialNumber,
                'warehouse_id' => $warehouseId,
                'status' => 'in_stock',
                'working_condition' => 'working',
                'current_holder_type' => 'warehouse',
                'current_holder_id' => $warehouseId,
                'source_warehouse_id' => $warehouseId
            ]);
            $this->createdRecords['assets'][] = $asset1['id'];
            
            // Try to create asset with same serial number for different product
            $duplicateCreated = false;
            try {
                $asset2 = $this->assetRepository->create([
                    'product_id' => $productIds[1],
                    'serial_number' => $serialNumber,
                    'warehouse_id' => $warehouseId,
                    'status' => 'in_stock',
                    'working_condition' => 'working',
                    'current_holder_type' => 'warehouse',
                    'current_holder_id' => $warehouseId,
                    'source_warehouse_id' => $warehouseId
                ]);
                $duplicateCreated = true;
                $this->createdRecords['assets'][] = $asset2['id'];
            } catch (Exception $e) {
                // Expected - duplicate should be rejected
                $duplicateCreated = false;
            }
            
            $this->assert(
                !$duplicateCreated,
                "Asset creation with duplicate serial number for different product should fail"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['serialNumber' => $serialNumber ?? null]
            ];
        }
    }
    
    /**
     * Property 2: Serial Number Uniqueness Across Warehouses
     * Creating assets with the same serial number in different warehouses should fail
     * **Feature: adv-crm-inventory-module, Property 2: Serial Number Global Uniqueness**
     * **Validates: Requirements 3.3**
     */
    public function testSerialNumberUniquenessAcrossWarehouses() {
        try {
            // Get test product and two different warehouses
            $productId = $this->getTestProductId();
            $warehouseIds = $this->getTwoTestWarehouseIds();
            
            if (count($warehouseIds) < 2) {
                return ['success' => true, 'message' => 'Skipped - need at least 2 warehouses'];
            }
            
            // Generate unique serial number
            $serialNumber = 'SN-' . $this->generateRandomString(15);
            
            // Create asset in first warehouse
            $asset1 = $this->assetRepository->create([
                'product_id' => $productId,
                'serial_number' => $serialNumber,
                'warehouse_id' => $warehouseIds[0],
                'status' => 'in_stock',
                'working_condition' => 'working',
                'current_holder_type' => 'warehouse',
                'current_holder_id' => $warehouseIds[0],
                'source_warehouse_id' => $warehouseIds[0]
            ]);
            $this->createdRecords['assets'][] = $asset1['id'];
            
            // Try to create asset with same serial number in different warehouse
            $duplicateCreated = false;
            try {
                $asset2 = $this->assetRepository->create([
                    'product_id' => $productId,
                    'serial_number' => $serialNumber,
                    'warehouse_id' => $warehouseIds[1],
                    'status' => 'in_stock',
                    'working_condition' => 'working',
                    'current_holder_type' => 'warehouse',
                    'current_holder_id' => $warehouseIds[1],
                    'source_warehouse_id' => $warehouseIds[1]
                ]);
                $duplicateCreated = true;
                $this->createdRecords['assets'][] = $asset2['id'];
            } catch (Exception $e) {
                // Expected - duplicate should be rejected
                $duplicateCreated = false;
            }
            
            $this->assert(
                !$duplicateCreated,
                "Asset creation with duplicate serial number in different warehouse should fail"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['serialNumber' => $serialNumber ?? null]
            ];
        }
    }
    
    /**
     * Property 2: Serial Number Uniqueness on Update
     * Updating an asset to have a duplicate serial number should fail
     * **Feature: adv-crm-inventory-module, Property 2: Serial Number Global Uniqueness**
     * **Validates: Requirements 3.3**
     */
    public function testSerialNumberUniquenessOnUpdate() {
        try {
            // Get test product and warehouse
            $productId = $this->getTestProductId();
            $warehouseId = $this->getTestWarehouseId();
            
            // Generate two unique serial numbers
            $serialNumber1 = 'SN-' . $this->generateRandomString(15);
            $serialNumber2 = 'SN-' . $this->generateRandomString(15);
            
            // Create first asset
            $asset1 = $this->assetRepository->create([
                'product_id' => $productId,
                'serial_number' => $serialNumber1,
                'warehouse_id' => $warehouseId,
                'status' => 'in_stock',
                'working_condition' => 'working',
                'current_holder_type' => 'warehouse',
                'current_holder_id' => $warehouseId,
                'source_warehouse_id' => $warehouseId
            ]);
            $this->createdRecords['assets'][] = $asset1['id'];
            
            // Create second asset
            $asset2 = $this->assetRepository->create([
                'product_id' => $productId,
                'serial_number' => $serialNumber2,
                'warehouse_id' => $warehouseId,
                'status' => 'in_stock',
                'working_condition' => 'working',
                'current_holder_type' => 'warehouse',
                'current_holder_id' => $warehouseId,
                'source_warehouse_id' => $warehouseId
            ]);
            $this->createdRecords['assets'][] = $asset2['id'];
            
            // Try to update second asset to have first asset's serial number
            $updateSucceeded = false;
            try {
                $this->assetRepository->update($asset2['id'], ['serial_number' => $serialNumber1]);
                $updateSucceeded = true;
            } catch (Exception $e) {
                // Expected - duplicate should be rejected
                $updateSucceeded = false;
            }
            
            $this->assert(
                !$updateSucceeded,
                "Update to duplicate serial number should fail"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['serialNumber1' => $serialNumber1 ?? null, 'serialNumber2' => $serialNumber2 ?? null]
            ];
        }
    }
    
    /**
     * Get a test product ID (serializable)
     */
    private function getTestProductId() {
        $sql = "SELECT id FROM products WHERE is_serializable = 1 AND status = 'active' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (empty($result)) {
            // Create a test product if none exists
            $this->executeQuery(
                "INSERT INTO products (name, unit_of_measure, inventory_type, is_serializable, status) VALUES (?, ?, ?, ?, ?)",
                ['Test Product ' . $this->generateRandomString(8), 'unit', 'INTERNAL', 1, 'active'],
                'sssis'
            );
            $productId = $this->db->insert_id;
            $this->createdRecords['products'][] = $productId;
            return $productId;
        }
        
        return $result[0]['id'];
    }
    
    /**
     * Get two different test product IDs
     */
    private function getTwoTestProductIds() {
        $sql = "SELECT id FROM products WHERE is_serializable = 1 AND status = 'active' LIMIT 2";
        $result = $this->getResults($sql);
        
        $productIds = array_column($result, 'id');
        
        // Create additional products if needed
        while (count($productIds) < 2) {
            $this->executeQuery(
                "INSERT INTO products (name, unit_of_measure, inventory_type, is_serializable, status) VALUES (?, ?, ?, ?, ?)",
                ['Test Product ' . $this->generateRandomString(8), 'unit', 'INTERNAL', 1, 'active'],
                'sssis'
            );
            $productId = $this->db->insert_id;
            $this->createdRecords['products'][] = $productId;
            $productIds[] = $productId;
        }
        
        return $productIds;
    }
    
    /**
     * Get a test warehouse ID
     */
    private function getTestWarehouseId() {
        $sql = "SELECT id FROM warehouses WHERE status = 'active' LIMIT 1";
        $result = $this->getResults($sql);
        
        if (empty($result)) {
            // Get a company ID first
            $companyId = $this->getTestCompanyId();
            
            // Create a test warehouse
            $this->executeQuery(
                "INSERT INTO warehouses (name, company_id, status) VALUES (?, ?, ?)",
                ['Test Warehouse ' . $this->generateRandomString(8), $companyId, 'active'],
                'sis'
            );
            $warehouseId = $this->db->insert_id;
            $this->createdRecords['warehouses'][] = $warehouseId;
            return $warehouseId;
        }
        
        return $result[0]['id'];
    }
    
    /**
     * Get two different test warehouse IDs
     */
    private function getTwoTestWarehouseIds() {
        $sql = "SELECT id FROM warehouses WHERE status = 'active' LIMIT 2";
        $result = $this->getResults($sql);
        
        $warehouseIds = array_column($result, 'id');
        
        // Create additional warehouses if needed
        while (count($warehouseIds) < 2) {
            $companyId = $this->getTestCompanyId();
            $this->executeQuery(
                "INSERT INTO warehouses (name, company_id, status) VALUES (?, ?, ?)",
                ['Test Warehouse ' . $this->generateRandomString(8), $companyId, 'active'],
                'sis'
            );
            $warehouseId = $this->db->insert_id;
            $this->createdRecords['warehouses'][] = $warehouseId;
            $warehouseIds[] = $warehouseId;
        }
        
        return $warehouseIds;
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
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Delete test assets
            if (isset($this->createdRecords['assets']) && !empty($this->createdRecords['assets'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['assets']));
                $this->db->query("DELETE FROM `assets` WHERE id IN ($ids)");
            }
            
            // Delete test warehouses
            if (isset($this->createdRecords['warehouses']) && !empty($this->createdRecords['warehouses'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['warehouses']));
                $this->db->query("DELETE FROM `warehouses` WHERE id IN ($ids)");
            }
            
            // Delete test products
            if (isset($this->createdRecords['products']) && !empty($this->createdRecords['products'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['products']));
                $this->db->query("DELETE FROM `products` WHERE id IN ($ids)");
            }
            
            // Delete test companies
            if (isset($this->createdRecords['companies']) && !empty($this->createdRecords['companies'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['companies']));
                $this->db->query("DELETE FROM `companies` WHERE id IN ($ids)");
            }
            
            // Also clean up any test records by name pattern
            $this->db->query("DELETE FROM assets WHERE serial_number LIKE 'SN-Test%'");
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

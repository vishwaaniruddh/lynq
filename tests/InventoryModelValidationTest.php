<?php
/**
 * Unit Tests for Inventory Model Validation Rules
 * Tests Product validation (required fields, enum values)
 * Tests Asset status transitions
 * Tests Warehouse unique constraint
 * 
 * Requirements: 2.1, 6.1, 1.4
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';

class InventoryModelValidationTest extends PropertyTestBase {
    
    private $productRepository;
    private $assetRepository;
    private $warehouseRepository;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->productRepository = new ProductRepository();
        $this->assetRepository = new AssetRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
    }
    
    public function runTests() {
        echo "=== Inventory Model Validation Unit Tests ===\n\n";
        
        $allPassed = true;
        
        // Product validation tests
        $allPassed &= $this->testProductInventoryTypeValidation();
        $allPassed &= $this->testProductStatusValidation();
        $allPassed &= $this->testProductRequiredFieldsValidation();
        
        // Asset status tests
        $allPassed &= $this->testAssetStatusValidation();
        $allPassed &= $this->testAssetWorkingConditionValidation();
        $allPassed &= $this->testAssetHolderTypeValidation();
        $allPassed &= $this->testAssetLockedStatuses();
        
        // Warehouse tests
        $allPassed &= $this->testWarehouseStatusValidation();
        $allPassed &= $this->testWarehouseNameUniquenessValidation();
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Test Product inventory type validation
     * Requirements: 2.1
     */
    public function testProductInventoryTypeValidation() {
        echo "Testing Product inventory type validation... ";
        
        try {
            // Test valid inventory types
            $validTypes = ProductRepository::getInventoryTypes();
            $this->assert(
                in_array('INTERNAL', $validTypes),
                "INTERNAL should be a valid inventory type"
            );
            $this->assert(
                in_array('SITE', $validTypes),
                "SITE should be a valid inventory type"
            );
            
            // Test isValidInventoryType method
            $this->assert(
                ProductRepository::isValidInventoryType('INTERNAL'),
                "INTERNAL should pass validation"
            );
            $this->assert(
                ProductRepository::isValidInventoryType('SITE'),
                "SITE should pass validation"
            );
            $this->assert(
                !ProductRepository::isValidInventoryType('INVALID'),
                "INVALID should fail validation"
            );
            $this->assert(
                !ProductRepository::isValidInventoryType(''),
                "Empty string should fail validation"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test Product status validation
     * Requirements: 2.1
     */
    public function testProductStatusValidation() {
        echo "Testing Product status validation... ";
        
        try {
            // Test valid statuses
            $validStatuses = ProductRepository::getStatuses();
            $this->assert(
                in_array('active', $validStatuses),
                "active should be a valid status"
            );
            $this->assert(
                in_array('inactive', $validStatuses),
                "inactive should be a valid status"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test Product required fields validation
     * Requirements: 2.1
     */
    public function testProductRequiredFieldsValidation() {
        echo "Testing Product required fields validation... ";
        
        try {
            // Test validation with missing fields
            $errors = $this->productRepository->validate([]);
            $this->assert(
                count($errors) > 0,
                "Empty data should produce validation errors"
            );
            $this->assert(
                in_array('Product name is required', $errors),
                "Missing name should produce error"
            );
            $this->assert(
                in_array('Unit of measure is required', $errors),
                "Missing unit_of_measure should produce error"
            );
            $this->assert(
                in_array('Inventory type is required', $errors),
                "Missing inventory_type should produce error"
            );
            
            // Test validation with valid data
            $errors = $this->productRepository->validate([
                'name' => 'Test Product',
                'unit_of_measure' => 'unit',
                'inventory_type' => 'INTERNAL'
            ]);
            $this->assert(
                count($errors) === 0,
                "Valid data should produce no errors"
            );
            
            // Test validation with invalid inventory type
            $errors = $this->productRepository->validate([
                'name' => 'Test Product',
                'unit_of_measure' => 'unit',
                'inventory_type' => 'INVALID'
            ]);
            $this->assert(
                count($errors) > 0,
                "Invalid inventory type should produce error"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test Asset status validation
     * Requirements: 6.1
     */
    public function testAssetStatusValidation() {
        echo "Testing Asset status validation... ";
        
        try {
            // Test all valid statuses
            $validStatuses = AssetRepository::getStatuses();
            $expectedStatuses = [
                'in_stock', 'dispatched', 'assigned', 'in_use',
                'returned', 'under_repair', 'scrapped', 'lost'
            ];
            
            foreach ($expectedStatuses as $status) {
                $this->assert(
                    in_array($status, $validStatuses),
                    "$status should be a valid status"
                );
                $this->assert(
                    AssetRepository::isValidStatus($status),
                    "$status should pass validation"
                );
            }
            
            // Test invalid status
            $this->assert(
                !AssetRepository::isValidStatus('invalid_status'),
                "invalid_status should fail validation"
            );
            $this->assert(
                !AssetRepository::isValidStatus(''),
                "Empty string should fail validation"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test Asset working condition validation
     * Requirements: 6.1
     */
    public function testAssetWorkingConditionValidation() {
        echo "Testing Asset working condition validation... ";
        
        try {
            // Test valid working conditions
            $this->assert(
                AssetRepository::CONDITION_WORKING === 'working',
                "CONDITION_WORKING should be 'working'"
            );
            $this->assert(
                AssetRepository::CONDITION_NOT_WORKING === 'not_working',
                "CONDITION_NOT_WORKING should be 'not_working'"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test Asset holder type validation
     * Requirements: 6.1
     */
    public function testAssetHolderTypeValidation() {
        echo "Testing Asset holder type validation... ";
        
        try {
            // Test valid holder types
            $this->assert(
                AssetRepository::HOLDER_WAREHOUSE === 'warehouse',
                "HOLDER_WAREHOUSE should be 'warehouse'"
            );
            $this->assert(
                AssetRepository::HOLDER_COMPANY === 'company',
                "HOLDER_COMPANY should be 'company'"
            );
            $this->assert(
                AssetRepository::HOLDER_USER === 'user',
                "HOLDER_USER should be 'user'"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test Asset locked statuses
     * Requirements: 6.1
     */
    public function testAssetLockedStatuses() {
        echo "Testing Asset locked statuses... ";
        
        try {
            // Test locked statuses
            $lockedStatuses = AssetRepository::getLockedStatuses();
            $this->assert(
                in_array('scrapped', $lockedStatuses),
                "scrapped should be a locked status"
            );
            $this->assert(
                in_array('lost', $lockedStatuses),
                "lost should be a locked status"
            );
            $this->assert(
                !in_array('in_stock', $lockedStatuses),
                "in_stock should not be a locked status"
            );
            $this->assert(
                !in_array('dispatched', $lockedStatuses),
                "dispatched should not be a locked status"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test Warehouse status validation
     * Requirements: 1.4
     */
    public function testWarehouseStatusValidation() {
        echo "Testing Warehouse status validation... ";
        
        try {
            // Test valid statuses
            $validStatuses = WarehouseRepository::getStatuses();
            $this->assert(
                in_array('active', $validStatuses),
                "active should be a valid status"
            );
            $this->assert(
                in_array('inactive', $validStatuses),
                "inactive should be a valid status"
            );
            
            // Test isValidStatus method
            $this->assert(
                WarehouseRepository::isValidStatus('active'),
                "active should pass validation"
            );
            $this->assert(
                WarehouseRepository::isValidStatus('inactive'),
                "inactive should pass validation"
            );
            $this->assert(
                !WarehouseRepository::isValidStatus('invalid'),
                "invalid should fail validation"
            );
            
            echo "PASSED\n";
            return true;
            
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test Warehouse name uniqueness validation
     * Requirements: 1.4
     */
    public function testWarehouseNameUniquenessValidation() {
        echo "Testing Warehouse name uniqueness validation... ";
        
        try {
            // Check if warehouses table exists
            if (!$this->tableExists('warehouses')) {
                echo "SKIPPED (warehouses table not found - run migrations first)\n";
                return true; // Skip but don't fail
            }
            
            // Get a test company
            $companyId = $this->getTestCompanyId();
            
            // Create a warehouse
            $warehouseName = 'Test Warehouse ' . $this->generateRandomString(10);
            $warehouse = $this->warehouseRepository->create([
                'name' => $warehouseName,
                'company_id' => $companyId,
                'status' => 'active'
            ]);
            $this->createdRecords['warehouses'][] = $warehouse['id'];
            
            // Test isNameUniqueInCompany - should return false for existing name
            $isUnique = $this->warehouseRepository->isNameUniqueInCompany($warehouseName, $companyId);
            $this->assert(
                !$isUnique,
                "Existing name should not be unique"
            );
            
            // Test isNameUniqueInCompany - should return true for new name
            $newName = 'New Warehouse ' . $this->generateRandomString(10);
            $isUnique = $this->warehouseRepository->isNameUniqueInCompany($newName, $companyId);
            $this->assert(
                $isUnique,
                "New name should be unique"
            );
            
            // Test isNameUniqueInCompany with exclude - should return true when excluding self
            $isUnique = $this->warehouseRepository->isNameUniqueInCompany($warehouseName, $companyId, $warehouse['id']);
            $this->assert(
                $isUnique,
                "Same name should be unique when excluding self"
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
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
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

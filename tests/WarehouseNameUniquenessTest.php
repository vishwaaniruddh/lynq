<?php
/**
 * Property Test for Warehouse Name Uniqueness Within Company
 * **Feature: adv-crm-inventory-module, Property 1: Warehouse Name Uniqueness Within Company**
 * **Validates: Requirements 1.4**
 * 
 * For any company and any two warehouses within that company, 
 * the warehouse names SHALL be distinct.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';

class WarehouseNameUniquenessTest extends PropertyTestBase {
    
    private $warehouseRepository;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->warehouseRepository = new WarehouseRepository();
        // Disable company filter for testing
        $this->warehouseRepository->disableCompanyFilter();
    }
    
    public function runTests() {
        echo "=== Warehouse Name Uniqueness Property Tests ===\n\n";
        
        // Check if warehouses table exists
        if (!$this->tableExists('warehouses')) {
            echo "SKIPPED: warehouses table not found - run migrations first\n";
            echo "Property tests require database tables to be created.\n";
            return true; // Skip but don't fail
        }
        
        $allPassed = true;
        
        // Test duplicate name on create within same company
        $allPassed &= $this->runPropertyTest(
            "Warehouse Name Uniqueness on Create Within Company",
            [$this, 'testWarehouseNameUniquenessOnCreate']
        );
        
        // Test duplicate name on update within same company
        $allPassed &= $this->runPropertyTest(
            "Warehouse Name Uniqueness on Update Within Company",
            [$this, 'testWarehouseNameUniquenessOnUpdate']
        );
        
        // Test same name allowed in different companies
        $allPassed &= $this->runPropertyTest(
            "Same Warehouse Name Allowed in Different Companies",
            [$this, 'testSameNameAllowedInDifferentCompanies']
        );
        
        // Test same name update allowed for same record
        $allPassed &= $this->runPropertyTest(
            "Warehouse Same Name Update Allowed",
            [$this, 'testWarehouseSameNameUpdateAllowed']
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
     * Property 1: Warehouse Name Uniqueness on Create Within Company
     * Creating a warehouse with a duplicate name in the same company should fail
     * **Feature: adv-crm-inventory-module, Property 1: Warehouse Name Uniqueness Within Company**
     * **Validates: Requirements 1.4**
     */
    public function testWarehouseNameUniquenessOnCreate() {
        try {
            // Get a valid company ID
            $companyId = $this->getTestCompanyId();
            
            // Generate unique warehouse name
            $warehouseName = 'Test Warehouse ' . $this->generateRandomString(15);
            
            // Create first warehouse
            $warehouse1 = $this->warehouseRepository->create([
                'name' => $warehouseName,
                'location' => 'Test Location 1',
                'company_id' => $companyId,
                'status' => 'active'
            ]);
            $this->createdRecords['warehouses'][] = $warehouse1['id'];
            
            // Try to create second warehouse with same name in same company
            $duplicateCreated = false;
            try {
                $warehouse2 = $this->warehouseRepository->create([
                    'name' => $warehouseName,
                    'location' => 'Test Location 2',
                    'company_id' => $companyId,
                    'status' => 'active'
                ]);
                // If we get here, duplicate was created (which is wrong)
                $duplicateCreated = true;
                $this->createdRecords['warehouses'][] = $warehouse2['id'];
            } catch (Exception $e) {
                // Expected - duplicate should be rejected
                $duplicateCreated = false;
            }
            
            $this->assert(
                !$duplicateCreated,
                "Second warehouse creation with duplicate name in same company should fail"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['warehouseName' => $warehouseName ?? null]
            ];
        }
    }
    
    /**
     * Property 1: Warehouse Name Uniqueness on Update Within Company
     * Updating a warehouse to have a duplicate name in the same company should fail
     * **Feature: adv-crm-inventory-module, Property 1: Warehouse Name Uniqueness Within Company**
     * **Validates: Requirements 1.4**
     */
    public function testWarehouseNameUniquenessOnUpdate() {
        try {
            // Get a valid company ID
            $companyId = $this->getTestCompanyId();
            
            // Generate two unique warehouse names
            $warehouseName1 = 'Test Warehouse ' . $this->generateRandomString(15);
            $warehouseName2 = 'Test Warehouse ' . $this->generateRandomString(15);
            
            // Create first warehouse
            $warehouse1 = $this->warehouseRepository->create([
                'name' => $warehouseName1,
                'location' => 'Test Location 1',
                'company_id' => $companyId,
                'status' => 'active'
            ]);
            $this->createdRecords['warehouses'][] = $warehouse1['id'];
            
            // Create second warehouse
            $warehouse2 = $this->warehouseRepository->create([
                'name' => $warehouseName2,
                'location' => 'Test Location 2',
                'company_id' => $companyId,
                'status' => 'active'
            ]);
            $this->createdRecords['warehouses'][] = $warehouse2['id'];
            
            // Try to update second warehouse to have first warehouse's name
            $updateSucceeded = false;
            try {
                $this->warehouseRepository->update($warehouse2['id'], ['name' => $warehouseName1]);
                $updateSucceeded = true;
            } catch (Exception $e) {
                // Expected - duplicate should be rejected
                $updateSucceeded = false;
            }
            
            $this->assert(
                !$updateSucceeded,
                "Update to duplicate name in same company should fail"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['warehouseName1' => $warehouseName1 ?? null, 'warehouseName2' => $warehouseName2 ?? null]
            ];
        }
    }
    
    /**
     * Property 1: Same Warehouse Name Allowed in Different Companies
     * Creating warehouses with the same name in different companies should succeed
     * **Feature: adv-crm-inventory-module, Property 1: Warehouse Name Uniqueness Within Company**
     * **Validates: Requirements 1.4**
     */
    public function testSameNameAllowedInDifferentCompanies() {
        try {
            // Get two different company IDs
            $companyIds = $this->getTwoTestCompanyIds();
            
            if (count($companyIds) < 2) {
                // Skip test if we don't have two companies
                return ['success' => true, 'message' => 'Skipped - need at least 2 companies'];
            }
            
            // Generate unique warehouse name
            $warehouseName = 'Test Warehouse ' . $this->generateRandomString(15);
            
            // Create warehouse in first company
            $warehouse1 = $this->warehouseRepository->create([
                'name' => $warehouseName,
                'location' => 'Test Location 1',
                'company_id' => $companyIds[0],
                'status' => 'active'
            ]);
            $this->createdRecords['warehouses'][] = $warehouse1['id'];
            
            // Create warehouse with same name in second company (should succeed)
            $warehouse2 = $this->warehouseRepository->create([
                'name' => $warehouseName,
                'location' => 'Test Location 2',
                'company_id' => $companyIds[1],
                'status' => 'active'
            ]);
            $this->createdRecords['warehouses'][] = $warehouse2['id'];
            
            $this->assert(
                $warehouse1['id'] !== $warehouse2['id'],
                "Two warehouses with same name in different companies should be created"
            );
            
            $this->assert(
                $warehouse1['name'] === $warehouse2['name'],
                "Both warehouses should have the same name"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['warehouseName' => $warehouseName ?? null]
            ];
        }
    }
    
    /**
     * Property 1: Warehouse Same Name Update Allowed
     * Updating a warehouse with its own name should succeed (not trigger duplicate error)
     * **Feature: adv-crm-inventory-module, Property 1: Warehouse Name Uniqueness Within Company**
     * **Validates: Requirements 1.4**
     */
    public function testWarehouseSameNameUpdateAllowed() {
        try {
            // Get a valid company ID
            $companyId = $this->getTestCompanyId();
            
            // Generate unique warehouse name
            $warehouseName = 'Test Warehouse ' . $this->generateRandomString(15);
            
            // Create warehouse
            $warehouse = $this->warehouseRepository->create([
                'name' => $warehouseName,
                'location' => 'Test Location',
                'company_id' => $companyId,
                'status' => 'active'
            ]);
            $this->createdRecords['warehouses'][] = $warehouse['id'];
            
            // Update warehouse with same name (should succeed)
            $updatedWarehouse = $this->warehouseRepository->update($warehouse['id'], [
                'name' => $warehouseName,
                'location' => 'Updated Location'
            ]);
            
            $this->assert(
                $updatedWarehouse !== null,
                "Update with same name should succeed"
            );
            
            $this->assert(
                $updatedWarehouse['name'] === $warehouseName,
                "Warehouse name should remain unchanged"
            );
            
            $this->assert(
                $updatedWarehouse['location'] === 'Updated Location',
                "Other fields should be updated"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['warehouseName' => $warehouseName ?? null]
            ];
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
     * Get two different test company IDs
     */
    private function getTwoTestCompanyIds() {
        $sql = "SELECT id FROM companies WHERE status = 'ACTIVE' LIMIT 2";
        $result = $this->getResults($sql);
        
        $companyIds = array_column($result, 'id');
        
        // Create additional companies if needed
        while (count($companyIds) < 2) {
            $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test Company ' . $this->generateRandomString(8), 'CONTRACTOR', 'ACTIVE'],
                'sss'
            );
            $companyId = $this->db->insert_id;
            $this->createdRecords['companies'][] = $companyId;
            $companyIds[] = $companyId;
        }
        
        return $companyIds;
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
            
            // Also clean up any test records by name pattern
            $this->db->query("DELETE FROM warehouses WHERE name LIKE 'Test Warehouse %'");
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

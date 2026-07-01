<?php
/**
 * Property Test for Courier Name Uniqueness
 * **Feature: crm-sidebar-restructure, Property 7: Courier Name Uniqueness**
 * **Validates: Requirements 2.2**
 * 
 * For any attempt to create or update a courier with a duplicate name, 
 * the operation should fail with a duplicate error.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/CourierService.php';

class CourierNameUniquenessTest extends PropertyTestBase {
    
    private $courierService;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->courierService = new CourierService();
    }
    
    public function runTests() {
        echo "=== Courier Name Uniqueness Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test duplicate name on create
        $allPassed &= $this->runPropertyTest(
            "Courier Name Uniqueness on Create",
            [$this, 'testCourierNameUniquenessOnCreate']
        );
        
        // Test duplicate name on update
        $allPassed &= $this->runPropertyTest(
            "Courier Name Uniqueness on Update",
            [$this, 'testCourierNameUniquenessOnUpdate']
        );
        
        // Test same name update allowed for same record
        $allPassed &= $this->runPropertyTest(
            "Courier Same Name Update Allowed",
            [$this, 'testCourierSameNameUpdateAllowed']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 7: Courier Name Uniqueness on Create
     * Creating a courier with a duplicate name should fail
     * **Feature: crm-sidebar-restructure, Property 7: Courier Name Uniqueness**
     * **Validates: Requirements 2.2**
     */
    public function testCourierNameUniquenessOnCreate() {
        try {
            // Generate unique courier name
            $courierName = 'Test Courier ' . $this->generateRandomString(15);
            
            // Create first courier
            $result1 = $this->courierService->create(['name' => $courierName]);
            $this->assert($result1['success'], "First courier creation should succeed: " . ($result1['message'] ?? ''));
            $this->createdRecords['couriers'][] = $result1['data']['id'];
            
            // Try to create second courier with same name
            $result2 = $this->courierService->create(['name' => $courierName]);
            $this->assert(!$result2['success'], "Second courier creation with duplicate name should fail");
            $this->assert(
                $result2['code'] === 'DUPLICATE_ERROR',
                "Error code should be DUPLICATE_ERROR, got: " . ($result2['code'] ?? 'none')
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['courierName' => $courierName ?? null]
            ];
        }
    }
    
    /**
     * Property 7: Courier Name Uniqueness on Update
     * Updating a courier to have a duplicate name should fail
     * **Feature: crm-sidebar-restructure, Property 7: Courier Name Uniqueness**
     * **Validates: Requirements 2.2**
     */
    public function testCourierNameUniquenessOnUpdate() {
        try {
            // Generate two unique courier names
            $courierName1 = 'Test Courier ' . $this->generateRandomString(15);
            $courierName2 = 'Test Courier ' . $this->generateRandomString(15);
            
            // Create first courier
            $result1 = $this->courierService->create(['name' => $courierName1]);
            $this->assert($result1['success'], "First courier creation should succeed");
            $this->createdRecords['couriers'][] = $result1['data']['id'];
            
            // Create second courier
            $result2 = $this->courierService->create(['name' => $courierName2]);
            $this->assert($result2['success'], "Second courier creation should succeed");
            $courierId2 = $result2['data']['id'];
            $this->createdRecords['couriers'][] = $courierId2;
            
            // Try to update second courier to have first courier's name
            $updateResult = $this->courierService->update($courierId2, ['name' => $courierName1]);
            $this->assert(!$updateResult['success'], "Update to duplicate name should fail");
            $this->assert(
                $updateResult['code'] === 'DUPLICATE_ERROR',
                "Error code should be DUPLICATE_ERROR, got: " . ($updateResult['code'] ?? 'none')
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['courierName1' => $courierName1 ?? null, 'courierName2' => $courierName2 ?? null]
            ];
        }
    }
    
    /**
     * Property 7: Courier Same Name Update Allowed
     * Updating a courier with its own name should succeed (not trigger duplicate error)
     * **Feature: crm-sidebar-restructure, Property 7: Courier Name Uniqueness**
     * **Validates: Requirements 2.2**
     */
    public function testCourierSameNameUpdateAllowed() {
        try {
            // Generate unique courier name
            $courierName = 'Test Courier ' . $this->generateRandomString(15);
            
            // Create courier
            $result = $this->courierService->create(['name' => $courierName]);
            $this->assert($result['success'], "Courier creation should succeed");
            $courierId = $result['data']['id'];
            $this->createdRecords['couriers'][] = $courierId;
            
            // Update courier with same name (should succeed)
            $updateResult = $this->courierService->update($courierId, ['name' => $courierName]);
            $this->assert(
                $updateResult['success'], 
                "Update with same name should succeed: " . ($updateResult['message'] ?? '')
            );
            
            // Verify name is still the same
            $courier = $this->courierService->getById($courierId);
            $this->assert(
                $courier['name'] === $courierName,
                "Courier name should remain unchanged"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['courierName' => $courierName ?? null]
            ];
        }
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            if (isset($this->createdRecords['couriers']) && !empty($this->createdRecords['couriers'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['couriers']));
                $this->db->query("DELETE FROM `couriers` WHERE id IN ($ids)");
            }
            
            // Also clean up any test records by name pattern
            $this->db->query("DELETE FROM couriers WHERE name LIKE 'Test Courier %'");
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

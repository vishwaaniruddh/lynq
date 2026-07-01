<?php
/**
 * Property Test for Courier Soft Delete Status Change
 * **Feature: crm-sidebar-restructure, Property 5: Courier Soft Delete Status Change**
 * **Validates: Requirements 2.4**
 * 
 * For any courier that is deleted, the status should be set to inactive 
 * while preserving the record in the database.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../repositories/CourierRepository.php';
require_once __DIR__ . '/../services/CourierService.php';

class CourierSoftDeleteTest extends PropertyTestBase {
    
    private $createdRecords = [];
    private $courierRepository;
    private $courierService;
    
    public function __construct() {
        parent::__construct();
        $this->courierRepository = new CourierRepository();
        $this->courierService = new CourierService();
    }
    
    public function runTests() {
        echo "=== Courier Soft Delete Status Change Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test soft delete via repository
        $allPassed &= $this->runPropertyTest(
            "Courier Soft Delete Status Change (Repository)",
            [$this, 'testCourierSoftDeleteRepository']
        );
        
        // Test soft delete via service
        $allPassed &= $this->runPropertyTest(
            "Courier Soft Delete Status Change (Service)",
            [$this, 'testCourierSoftDeleteService']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 5: Courier Soft Delete Status Change (Repository)
     * For any courier, soft deleting via repository should set status to inactive (0) 
     * while preserving the record
     * **Feature: crm-sidebar-restructure, Property 5: Courier Soft Delete Status Change**
     * **Validates: Requirements 2.4**
     */
    public function testCourierSoftDeleteRepository() {
        try {
            // Generate and create a random courier with active status
            $courierData = $this->generateCourierData();
            $courierData['status'] = 1; // Ensure it starts as active
            
            // Create courier using repository
            $courierId = $this->courierRepository->createCourier($courierData);
            $this->assert($courierId > 0, "Courier creation failed");
            $this->createdRecords['couriers'][] = $courierId;
            
            // Verify courier was created with active status
            $courierBefore = $this->courierRepository->findById($courierId);
            $this->assert($courierBefore !== null, "Courier not found after creation");
            $this->assert((int)$courierBefore['status'] === 1, "Courier should be active before soft delete");
            
            // Perform soft delete
            $deleteResult = $this->courierRepository->softDelete($courierId);
            $this->assert($deleteResult === true, "Soft delete operation failed");
            
            // Verify courier still exists but status is inactive
            $courierAfter = $this->courierRepository->findById($courierId);
            $this->assert($courierAfter !== null, "Courier record should still exist after soft delete");
            $this->assert((int)$courierAfter['status'] === 0, "Courier status should be 0 (inactive) after soft delete");
            
            // Verify other data is preserved
            $this->assert(
                $courierAfter['name'] === $courierBefore['name'],
                "Courier name should be preserved after soft delete"
            );
            $this->assert(
                $courierAfter['id'] === $courierBefore['id'],
                "Courier ID should be preserved after soft delete"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $courierData ?? null
            ];
        }
    }
    
    /**
     * Property 5: Courier Soft Delete Status Change (Service)
     * For any courier, soft deleting via service should set status to inactive (0) 
     * while preserving the record
     * **Feature: crm-sidebar-restructure, Property 5: Courier Soft Delete Status Change**
     * **Validates: Requirements 2.4**
     */
    public function testCourierSoftDeleteService() {
        try {
            // Generate and create a random courier with active status
            $courierData = [
                'name' => 'Test Courier ' . $this->generateRandomString(10),
                'status' => 1 // Ensure it starts as active
            ];
            
            // Create courier using service
            $createResult = $this->courierService->create($courierData);
            $this->assert($createResult['success'], "Courier creation should succeed: " . ($createResult['message'] ?? ''));
            $courierId = $createResult['data']['id'];
            $this->createdRecords['couriers'][] = $courierId;
            
            // Verify courier was created with active status
            $courierBefore = $this->courierService->getById($courierId);
            $this->assert($courierBefore !== null, "Courier not found after creation");
            $this->assert((int)$courierBefore['status'] === 1, "Courier should be active before soft delete");
            
            // Perform soft delete via service
            $deleteResult = $this->courierService->delete($courierId);
            $this->assert($deleteResult['success'], "Soft delete operation should succeed: " . ($deleteResult['message'] ?? ''));
            
            // Verify courier still exists but status is inactive
            $courierAfter = $this->courierService->getById($courierId);
            $this->assert($courierAfter !== null, "Courier record should still exist after soft delete");
            $this->assert((int)$courierAfter['status'] === 0, "Courier status should be 0 (inactive) after soft delete");
            
            // Verify other data is preserved
            $this->assert(
                $courierAfter['name'] === $courierBefore['name'],
                "Courier name should be preserved after soft delete"
            );
            $this->assert(
                $courierAfter['id'] === $courierBefore['id'],
                "Courier ID should be preserved after soft delete"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $courierData ?? null
            ];
        }
    }
    
    /**
     * Generate random courier data
     */
    private function generateCourierData() {
        return [
            'name' => 'Test Courier ' . $this->generateRandomString(10),
            'status' => 1,
            'created_by' => null
        ];
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

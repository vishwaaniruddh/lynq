<?php
/**
 * Property Test for Courier CRUD Round-Trip Consistency
 * **Feature: crm-sidebar-restructure, Property 4: Courier CRUD Round-Trip Consistency**
 * **Validates: Requirements 2.2, 2.3**
 * 
 * For any courier with valid data, creating the courier and then retrieving it by ID 
 * should return equivalent data to what was submitted.
 */

require_once 'PropertyTestBase.php';

class CourierCrudRoundTripTest extends PropertyTestBase {
    
    private $createdRecords = [];
    
    public function runTests() {
        echo "=== Courier CRUD Round-Trip Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test CRUD round-trip for Couriers
        $allPassed &= $this->runPropertyTest(
            "Courier CRUD Round-Trip Consistency",
            [$this, 'testCourierCrudRoundTrip']
        );
        
        // Test Update round-trip for Couriers
        $allPassed &= $this->runPropertyTest(
            "Courier Update Round-Trip Consistency",
            [$this, 'testCourierUpdateRoundTrip']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 4: Courier CRUD Round-Trip Consistency
     * For any courier with valid data, creating and retrieving should return equivalent data
     * **Feature: crm-sidebar-restructure, Property 4: Courier CRUD Round-Trip Consistency**
     * **Validates: Requirements 2.2, 2.3**
     */
    public function testCourierCrudRoundTrip() {
        try {
            // Generate random courier data
            $courierData = $this->generateCourierData();
            
            // Create courier
            $insertSql = "INSERT INTO couriers (name, status, created_by) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($insertSql);
            $status = $courierData['status'];
            $createdBy = $courierData['created_by'];
            $stmt->bind_param('sii', $courierData['name'], $status, $createdBy);
            $stmt->execute();
            $insertedId = $this->db->insert_id;
            $stmt->close();
            
            $this->assert($insertedId > 0, "Courier insertion failed");
            $this->createdRecords['couriers'][] = $insertedId;
            
            // Retrieve courier
            $selectSql = "SELECT * FROM couriers WHERE id = ?";
            $stmt = $this->db->prepare($selectSql);
            $stmt->bind_param('i', $insertedId);
            $stmt->execute();
            $result = $stmt->get_result();
            $retrievedCourier = $result->fetch_assoc();
            $stmt->close();
            
            $this->assert($retrievedCourier !== null, "Courier retrieval failed");
            
            // Verify round-trip consistency
            $this->assert(
                $retrievedCourier['name'] === $courierData['name'],
                "Courier name mismatch: expected '{$courierData['name']}', got '{$retrievedCourier['name']}'"
            );
            $this->assert(
                (int)$retrievedCourier['status'] === $courierData['status'],
                "Courier status mismatch: expected {$courierData['status']}, got {$retrievedCourier['status']}"
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
     * Property 4 (Update): Courier Update Round-Trip Consistency
     * For any courier update with valid data, updating and retrieving should return equivalent data
     * **Feature: crm-sidebar-restructure, Property 4: Courier CRUD Round-Trip Consistency**
     * **Validates: Requirements 2.2, 2.3**
     */
    public function testCourierUpdateRoundTrip() {
        try {
            // First create a courier
            $initialData = $this->generateCourierData();
            
            $insertSql = "INSERT INTO couriers (name, status) VALUES (?, ?)";
            $stmt = $this->db->prepare($insertSql);
            $status = $initialData['status'];
            $stmt->bind_param('si', $initialData['name'], $status);
            $stmt->execute();
            $courierId = $this->db->insert_id;
            $stmt->close();
            
            $this->assert($courierId > 0, "Initial courier insertion failed");
            $this->createdRecords['couriers'][] = $courierId;
            
            // Generate new data for update
            $updateData = $this->generateCourierData();
            
            // Update courier
            $updateSql = "UPDATE couriers SET name = ?, status = ? WHERE id = ?";
            $stmt = $this->db->prepare($updateSql);
            $newStatus = $updateData['status'];
            $stmt->bind_param('sii', $updateData['name'], $newStatus, $courierId);
            $stmt->execute();
            $stmt->close();
            
            // Retrieve updated courier
            $selectSql = "SELECT * FROM couriers WHERE id = ?";
            $stmt = $this->db->prepare($selectSql);
            $stmt->bind_param('i', $courierId);
            $stmt->execute();
            $result = $stmt->get_result();
            $retrievedCourier = $result->fetch_assoc();
            $stmt->close();
            
            $this->assert($retrievedCourier !== null, "Courier retrieval after update failed");
            
            // Verify update round-trip consistency
            $this->assert(
                $retrievedCourier['name'] === $updateData['name'],
                "Updated courier name mismatch: expected '{$updateData['name']}', got '{$retrievedCourier['name']}'"
            );
            $this->assert(
                (int)$retrievedCourier['status'] === $updateData['status'],
                "Updated courier status mismatch: expected {$updateData['status']}, got {$retrievedCourier['status']}"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $updateData ?? null
            ];
        }
    }
    
    /**
     * Generate random courier data
     */
    private function generateCourierData() {
        return [
            'name' => 'Test Courier ' . $this->generateRandomString(10),
            'status' => $this->generateRandomChoice([0, 1]),
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

<?php
/**
 * Property Test for Soft Delete Status Change
 * **Feature: crm-master-modules, Property 2: Soft Delete Status Change**
 * **Validates: Requirements 1.4, 2.4, 6.5**
 * 
 * For any master entity that supports soft delete (banks, customers, cities),
 * deleting the entity should set its status to inactive while preserving the record in the database.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../repositories/BankRepository.php';
require_once __DIR__ . '/../repositories/CustomerRepository.php';
require_once __DIR__ . '/../repositories/LocationRepository.php';

class SoftDeleteStatusChangeTest extends PropertyTestBase {
    
    private $createdRecords = [];
    private $bankRepository;
    private $customerRepository;
    private $locationRepository;
    
    public function __construct() {
        parent::__construct();
        $this->bankRepository = new BankRepository();
        $this->customerRepository = new CustomerRepository();
        $this->locationRepository = new LocationRepository();
    }
    
    public function runTests() {
        echo "=== Soft Delete Status Change Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test soft delete for Banks
        $allPassed &= $this->runPropertyTest(
            "Bank Soft Delete Status Change",
            [$this, 'testBankSoftDelete']
        );
        
        // Test soft delete for Customers
        $allPassed &= $this->runPropertyTest(
            "Customer Soft Delete Status Change",
            [$this, 'testCustomerSoftDelete']
        );
        
        // Test soft delete for Cities
        $allPassed &= $this->runPropertyTest(
            "City Soft Delete Status Change",
            [$this, 'testCitySoftDelete']
        );
        
        return $allPassed;
    }

    
    /**
     * Property 2: Bank Soft Delete Status Change
     * For any bank, soft deleting should set status to inactive (0) while preserving the record
     */
    public function testBankSoftDelete() {
        try {
            // Generate and create a random bank with active status
            $bankData = $this->generateBankData();
            $bankData['status'] = 1; // Ensure it starts as active
            
            // Create bank using repository
            $bankId = $this->bankRepository->createBank($bankData);
            $this->assert($bankId > 0, "Bank creation failed");
            $this->createdRecords['banks'][] = $bankId;
            
            // Verify bank was created with active status
            $bankBefore = $this->bankRepository->findById($bankId);
            $this->assert($bankBefore !== null, "Bank not found after creation");
            $this->assert((int)$bankBefore['status'] === 1, "Bank should be active before soft delete");
            
            // Perform soft delete
            $deleteResult = $this->bankRepository->softDelete($bankId);
            $this->assert($deleteResult === true, "Soft delete operation failed");
            
            // Verify bank still exists but status is inactive
            $bankAfter = $this->bankRepository->findById($bankId);
            $this->assert($bankAfter !== null, "Bank record should still exist after soft delete");
            $this->assert((int)$bankAfter['status'] === 0, "Bank status should be 0 (inactive) after soft delete");
            
            // Verify other data is preserved
            $this->assert(
                $bankAfter['name'] === $bankBefore['name'],
                "Bank name should be preserved after soft delete"
            );
            $this->assert(
                $bankAfter['id'] === $bankBefore['id'],
                "Bank ID should be preserved after soft delete"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $bankData ?? null
            ];
        }
    }
    
    /**
     * Property 2: Customer Soft Delete Status Change
     * For any customer, soft deleting should set status to inactive (0) while preserving the record
     */
    public function testCustomerSoftDelete() {
        try {
            // Generate and create a random customer with active status
            $customerData = $this->generateCustomerData();
            $customerData['status'] = 1; // Ensure it starts as active
            
            // Create customer using repository
            $customerId = $this->customerRepository->createCustomer($customerData);
            $this->assert($customerId > 0, "Customer creation failed");
            $this->createdRecords['customers'][] = $customerId;
            
            // Verify customer was created with active status
            $customerBefore = $this->customerRepository->findById($customerId);
            $this->assert($customerBefore !== null, "Customer not found after creation");
            $this->assert((int)$customerBefore['status'] === 1, "Customer should be active before soft delete");
            
            // Perform soft delete
            $deleteResult = $this->customerRepository->softDelete($customerId);
            $this->assert($deleteResult === true, "Soft delete operation failed");
            
            // Verify customer still exists but status is inactive
            $customerAfter = $this->customerRepository->findById($customerId);
            $this->assert($customerAfter !== null, "Customer record should still exist after soft delete");
            $this->assert((int)$customerAfter['status'] === 0, "Customer status should be 0 (inactive) after soft delete");
            
            // Verify other data is preserved
            $this->assert(
                $customerAfter['name'] === $customerBefore['name'],
                "Customer name should be preserved after soft delete"
            );
            $this->assert(
                $customerAfter['email'] === $customerBefore['email'],
                "Customer email should be preserved after soft delete"
            );
            $this->assert(
                $customerAfter['id'] === $customerBefore['id'],
                "Customer ID should be preserved after soft delete"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $customerData ?? null
            ];
        }
    }

    
    /**
     * Property 2: City Soft Delete Status Change
     * For any city, soft deleting should set status to inactive while preserving the record
     */
    public function testCitySoftDelete() {
        try {
            // First create required parent records (country, zone, state)
            $countryId = $this->createTestCountry();
            $zoneId = $this->createTestZone();
            $stateId = $this->createTestState($countryId, $zoneId);
            
            // Generate and create a random city with active status
            $cityData = $this->generateCityData($stateId, $zoneId);
            $cityData['status'] = 'active'; // Ensure it starts as active
            
            // Create city using repository
            $cityId = $this->locationRepository->createCity($cityData);
            $this->assert($cityId > 0, "City creation failed");
            $this->createdRecords['cities'][] = $cityId;
            
            // Verify city was created with active status
            $cityBefore = $this->locationRepository->findCityById($cityId);
            $this->assert($cityBefore !== null, "City not found after creation");
            $this->assert($cityBefore['status'] === 'active', "City should be active before soft delete");
            
            // Perform soft delete
            $deleteResult = $this->locationRepository->softDeleteCity($cityId);
            $this->assert($deleteResult === true, "Soft delete operation failed");
            
            // Verify city still exists but status is inactive
            $cityAfter = $this->locationRepository->findCityById($cityId);
            $this->assert($cityAfter !== null, "City record should still exist after soft delete");
            $this->assert($cityAfter['status'] === 'inactive', "City status should be 'inactive' after soft delete");
            
            // Verify other data is preserved
            $this->assert(
                $cityAfter['name'] === $cityBefore['name'],
                "City name should be preserved after soft delete"
            );
            $this->assert(
                (int)$cityAfter['state_id'] === (int)$cityBefore['state_id'],
                "City state_id should be preserved after soft delete"
            );
            $this->assert(
                $cityAfter['id'] === $cityBefore['id'],
                "City ID should be preserved after soft delete"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $cityData ?? null
            ];
        }
    }
    
    // ==================== Data Generators ====================
    
    /**
     * Generate random bank data
     */
    private function generateBankData() {
        return [
            'name' => 'Test Bank ' . $this->generateRandomString(10),
            'status' => 1,
            'created_by' => null
        ];
    }
    
    /**
     * Generate random customer data
     */
    private function generateCustomerData() {
        return [
            'name' => 'Test Customer ' . $this->generateRandomString(8),
            'email' => 'test_' . $this->generateRandomString(8) . '@example.com',
            'phone' => '+91' . $this->generateRandomString(10, '0123456789'),
            'address' => $this->generateRandomString(30) . ' Street',
            'city' => 'Test City ' . $this->generateRandomString(5),
            'state' => 'Test State ' . $this->generateRandomString(5),
            'country' => 'India',
            'postal_code' => $this->generateRandomString(6, '0123456789'),
            'status' => 1,
            'created_by' => null
        ];
    }
    
    /**
     * Generate random city data
     */
    private function generateCityData($stateId, $zoneId) {
        return [
            'name' => 'Test City ' . $this->generateRandomString(10),
            'state_id' => $stateId,
            'zone_id' => $zoneId,
            'status' => 'active',
            'created_by' => null
        ];
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Create a test country and return its ID
     */
    private function createTestCountry() {
        $countryData = [
            'name' => 'Test Country ' . $this->generateRandomString(10),
            'status' => 'active'
        ];
        
        $id = $this->locationRepository->createCountry($countryData);
        $this->createdRecords['countries'][] = $id;
        return $id;
    }
    
    /**
     * Create a test zone and return its ID
     */
    private function createTestZone() {
        $zoneData = [
            'name' => 'Test Zone ' . $this->generateRandomString(10),
            'status' => 'active'
        ];
        
        $id = $this->locationRepository->createZone($zoneData);
        $this->createdRecords['zones'][] = $id;
        return $id;
    }
    
    /**
     * Create a test state and return its ID
     */
    private function createTestState($countryId, $zoneId) {
        $stateData = [
            'name' => 'Test State ' . $this->generateRandomString(10),
            'country_id' => $countryId,
            'zone_id' => $zoneId,
            'status' => 'active'
        ];
        
        $id = $this->locationRepository->createState($stateData);
        $this->createdRecords['states'][] = $id;
        return $id;
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Delete in reverse order of dependencies
            $tables = ['cities', 'states', 'zones', 'countries', 'customers', 'banks'];
            
            foreach ($tables as $table) {
                if (isset($this->createdRecords[$table]) && !empty($this->createdRecords[$table])) {
                    $ids = implode(',', array_map('intval', $this->createdRecords[$table]));
                    $this->db->query("DELETE FROM `$table` WHERE id IN ($ids)");
                }
            }
            
            // Also clean up any test records by name pattern
            $this->db->query("DELETE FROM cities WHERE name LIKE 'Test City %'");
            $this->db->query("DELETE FROM states WHERE name LIKE 'Test State %'");
            $this->db->query("DELETE FROM zones WHERE name LIKE 'Test Zone %'");
            $this->db->query("DELETE FROM countries WHERE name LIKE 'Test Country %'");
            $this->db->query("DELETE FROM customers WHERE name LIKE 'Test Customer %'");
            $this->db->query("DELETE FROM banks WHERE name LIKE 'Test Bank %'");
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

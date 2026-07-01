<?php
/**
 * Property Test for Master Module CRUD Round-Trip Consistency
 * **Feature: crm-master-modules, Property 1: CRUD Round-Trip Consistency**
 * **Validates: Requirements 1.2, 2.2, 3.2, 4.2, 5.2, 6.2**
 * 
 * For any master entity (bank, customer, country, state, zone, city) with valid data,
 * creating the entity and then retrieving it by ID should return equivalent data to what was submitted.
 */

require_once 'PropertyTestBase.php';

class MasterModuleCrudRoundTripTest extends PropertyTestBase {
    
    private $createdRecords = [];
    
    public function runTests() {
        echo "=== Master Module CRUD Round-Trip Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test CRUD round-trip for Banks
        $allPassed &= $this->runPropertyTest(
            "Bank CRUD Round-Trip Consistency",
            [$this, 'testBankCrudRoundTrip']
        );
        
        // Test CRUD round-trip for Customers
        $allPassed &= $this->runPropertyTest(
            "Customer CRUD Round-Trip Consistency",
            [$this, 'testCustomerCrudRoundTrip']
        );
        
        // Test CRUD round-trip for Countries
        $allPassed &= $this->runPropertyTest(
            "Country CRUD Round-Trip Consistency",
            [$this, 'testCountryCrudRoundTrip']
        );
        
        // Test CRUD round-trip for Zones
        $allPassed &= $this->runPropertyTest(
            "Zone CRUD Round-Trip Consistency",
            [$this, 'testZoneCrudRoundTrip']
        );
        
        // Test CRUD round-trip for States (depends on Country and Zone)
        $allPassed &= $this->runPropertyTest(
            "State CRUD Round-Trip Consistency",
            [$this, 'testStateCrudRoundTrip']
        );
        
        // Test CRUD round-trip for Cities (depends on State)
        $allPassed &= $this->runPropertyTest(
            "City CRUD Round-Trip Consistency",
            [$this, 'testCityCrudRoundTrip']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 1: Bank CRUD Round-Trip Consistency
     * For any bank with valid data, creating and retrieving should return equivalent data
     */
    public function testBankCrudRoundTrip() {
        try {
            // Generate random bank data
            $bankData = $this->generateBankData();
            
            // Create bank
            $insertSql = "INSERT INTO banks (name, status, created_by) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($insertSql);
            $status = $bankData['status'];
            $createdBy = $bankData['created_by'];
            $stmt->bind_param('sii', $bankData['name'], $status, $createdBy);
            $stmt->execute();
            $insertedId = $this->db->insert_id;
            $stmt->close();
            
            $this->assert($insertedId > 0, "Bank insertion failed");
            $this->createdRecords['banks'][] = $insertedId;
            
            // Retrieve bank
            $selectSql = "SELECT * FROM banks WHERE id = ?";
            $stmt = $this->db->prepare($selectSql);
            $stmt->bind_param('i', $insertedId);
            $stmt->execute();
            $result = $stmt->get_result();
            $retrievedBank = $result->fetch_assoc();
            $stmt->close();
            
            $this->assert($retrievedBank !== null, "Bank retrieval failed");
            
            // Verify round-trip consistency
            $this->assert(
                $retrievedBank['name'] === $bankData['name'],
                "Bank name mismatch: expected '{$bankData['name']}', got '{$retrievedBank['name']}'"
            );
            $this->assert(
                (int)$retrievedBank['status'] === $bankData['status'],
                "Bank status mismatch: expected {$bankData['status']}, got {$retrievedBank['status']}"
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
     * Property 1: Customer CRUD Round-Trip Consistency
     * For any customer with valid data, creating and retrieving should return equivalent data
     */
    public function testCustomerCrudRoundTrip() {
        try {
            // Generate random customer data
            $customerData = $this->generateCustomerData();
            
            // Create customer
            $insertSql = "INSERT INTO customers (name, email, phone, address, city, state, country, postal_code, status, created_by) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($insertSql);
            $status = $customerData['status'];
            $createdBy = $customerData['created_by'];
            $stmt->bind_param('ssssssssii', 
                $customerData['name'],
                $customerData['email'],
                $customerData['phone'],
                $customerData['address'],
                $customerData['city'],
                $customerData['state'],
                $customerData['country'],
                $customerData['postal_code'],
                $status,
                $createdBy
            );
            $stmt->execute();
            $insertedId = $this->db->insert_id;
            $stmt->close();
            
            $this->assert($insertedId > 0, "Customer insertion failed");
            $this->createdRecords['customers'][] = $insertedId;
            
            // Retrieve customer
            $selectSql = "SELECT * FROM customers WHERE id = ?";
            $stmt = $this->db->prepare($selectSql);
            $stmt->bind_param('i', $insertedId);
            $stmt->execute();
            $result = $stmt->get_result();
            $retrievedCustomer = $result->fetch_assoc();
            $stmt->close();
            
            $this->assert($retrievedCustomer !== null, "Customer retrieval failed");
            
            // Verify round-trip consistency
            $this->assert(
                $retrievedCustomer['name'] === $customerData['name'],
                "Customer name mismatch"
            );
            $this->assert(
                $retrievedCustomer['email'] === $customerData['email'],
                "Customer email mismatch"
            );
            $this->assert(
                $retrievedCustomer['phone'] === $customerData['phone'],
                "Customer phone mismatch"
            );
            $this->assert(
                $retrievedCustomer['country'] === $customerData['country'],
                "Customer country mismatch"
            );
            $this->assert(
                (int)$retrievedCustomer['status'] === $customerData['status'],
                "Customer status mismatch"
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
     * Property 1: Country CRUD Round-Trip Consistency
     * For any country with valid data, creating and retrieving should return equivalent data
     */
    public function testCountryCrudRoundTrip() {
        try {
            // Generate random country data
            $countryData = $this->generateCountryData();
            
            // Create country
            $insertSql = "INSERT INTO countries (name, status, created_by) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($insertSql);
            $createdBy = $countryData['created_by'];
            $stmt->bind_param('ssi', $countryData['name'], $countryData['status'], $createdBy);
            $stmt->execute();
            $insertedId = $this->db->insert_id;
            $stmt->close();
            
            $this->assert($insertedId > 0, "Country insertion failed");
            $this->createdRecords['countries'][] = $insertedId;
            
            // Retrieve country
            $selectSql = "SELECT * FROM countries WHERE id = ?";
            $stmt = $this->db->prepare($selectSql);
            $stmt->bind_param('i', $insertedId);
            $stmt->execute();
            $result = $stmt->get_result();
            $retrievedCountry = $result->fetch_assoc();
            $stmt->close();
            
            $this->assert($retrievedCountry !== null, "Country retrieval failed");
            
            // Verify round-trip consistency
            $this->assert(
                $retrievedCountry['name'] === $countryData['name'],
                "Country name mismatch"
            );
            $this->assert(
                $retrievedCountry['status'] === $countryData['status'],
                "Country status mismatch"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $countryData ?? null
            ];
        }
    }
    
    /**
     * Property 1: Zone CRUD Round-Trip Consistency
     * For any zone with valid data, creating and retrieving should return equivalent data
     */
    public function testZoneCrudRoundTrip() {
        try {
            // Generate random zone data
            $zoneData = $this->generateZoneData();
            
            // Create zone
            $insertSql = "INSERT INTO zones (name, status, created_by) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($insertSql);
            $createdBy = $zoneData['created_by'];
            $stmt->bind_param('ssi', $zoneData['name'], $zoneData['status'], $createdBy);
            $stmt->execute();
            $insertedId = $this->db->insert_id;
            $stmt->close();
            
            $this->assert($insertedId > 0, "Zone insertion failed");
            $this->createdRecords['zones'][] = $insertedId;
            
            // Retrieve zone
            $selectSql = "SELECT * FROM zones WHERE id = ?";
            $stmt = $this->db->prepare($selectSql);
            $stmt->bind_param('i', $insertedId);
            $stmt->execute();
            $result = $stmt->get_result();
            $retrievedZone = $result->fetch_assoc();
            $stmt->close();
            
            $this->assert($retrievedZone !== null, "Zone retrieval failed");
            
            // Verify round-trip consistency
            $this->assert(
                $retrievedZone['name'] === $zoneData['name'],
                "Zone name mismatch"
            );
            $this->assert(
                $retrievedZone['status'] === $zoneData['status'],
                "Zone status mismatch"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $zoneData ?? null
            ];
        }
    }
    
    /**
     * Property 1: State CRUD Round-Trip Consistency
     * For any state with valid data, creating and retrieving should return equivalent data
     */
    public function testStateCrudRoundTrip() {
        try {
            // First create a country and zone for the state
            $countryId = $this->createTestCountry();
            $zoneId = $this->createTestZone();
            
            // Generate random state data
            $stateData = $this->generateStateData($countryId, $zoneId);
            
            // Create state
            $insertSql = "INSERT INTO states (name, country_id, zone_id, status, created_by) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($insertSql);
            $createdBy = $stateData['created_by'];
            $stmt->bind_param('siisi', 
                $stateData['name'], 
                $stateData['country_id'], 
                $stateData['zone_id'], 
                $stateData['status'],
                $createdBy
            );
            $stmt->execute();
            $insertedId = $this->db->insert_id;
            $stmt->close();
            
            $this->assert($insertedId > 0, "State insertion failed");
            $this->createdRecords['states'][] = $insertedId;
            
            // Retrieve state
            $selectSql = "SELECT * FROM states WHERE id = ?";
            $stmt = $this->db->prepare($selectSql);
            $stmt->bind_param('i', $insertedId);
            $stmt->execute();
            $result = $stmt->get_result();
            $retrievedState = $result->fetch_assoc();
            $stmt->close();
            
            $this->assert($retrievedState !== null, "State retrieval failed");
            
            // Verify round-trip consistency
            $this->assert(
                $retrievedState['name'] === $stateData['name'],
                "State name mismatch"
            );
            $this->assert(
                (int)$retrievedState['country_id'] === $stateData['country_id'],
                "State country_id mismatch"
            );
            $this->assert(
                (int)$retrievedState['zone_id'] === $stateData['zone_id'],
                "State zone_id mismatch"
            );
            $this->assert(
                $retrievedState['status'] === $stateData['status'],
                "State status mismatch"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $stateData ?? null
            ];
        }
    }
    
    /**
     * Property 1: City CRUD Round-Trip Consistency
     * For any city with valid data, creating and retrieving should return equivalent data
     */
    public function testCityCrudRoundTrip() {
        try {
            // First create a country, zone, and state for the city
            $countryId = $this->createTestCountry();
            $zoneId = $this->createTestZone();
            $stateId = $this->createTestState($countryId, $zoneId);
            
            // Generate random city data
            $cityData = $this->generateCityData($stateId, $zoneId);
            
            // Create city
            $insertSql = "INSERT INTO cities (name, state_id, zone_id, status, created_by) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($insertSql);
            $createdBy = $cityData['created_by'];
            $stmt->bind_param('siisi', 
                $cityData['name'], 
                $cityData['state_id'], 
                $cityData['zone_id'], 
                $cityData['status'],
                $createdBy
            );
            $stmt->execute();
            $insertedId = $this->db->insert_id;
            $stmt->close();
            
            $this->assert($insertedId > 0, "City insertion failed");
            $this->createdRecords['cities'][] = $insertedId;
            
            // Retrieve city
            $selectSql = "SELECT * FROM cities WHERE id = ?";
            $stmt = $this->db->prepare($selectSql);
            $stmt->bind_param('i', $insertedId);
            $stmt->execute();
            $result = $stmt->get_result();
            $retrievedCity = $result->fetch_assoc();
            $stmt->close();
            
            $this->assert($retrievedCity !== null, "City retrieval failed");
            
            // Verify round-trip consistency
            $this->assert(
                $retrievedCity['name'] === $cityData['name'],
                "City name mismatch"
            );
            $this->assert(
                (int)$retrievedCity['state_id'] === $cityData['state_id'],
                "City state_id mismatch"
            );
            $this->assert(
                (int)$retrievedCity['zone_id'] === $cityData['zone_id'],
                "City zone_id mismatch"
            );
            $this->assert(
                $retrievedCity['status'] === $cityData['status'],
                "City status mismatch"
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
            'status' => $this->generateRandomChoice([0, 1]),
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
            'status' => $this->generateRandomChoice([0, 1]),
            'created_by' => null
        ];
    }
    
    /**
     * Generate random country data
     */
    private function generateCountryData() {
        return [
            'name' => 'Test Country ' . $this->generateRandomString(10),
            'status' => $this->generateRandomChoice(['active', 'inactive']),
            'created_by' => null
        ];
    }
    
    /**
     * Generate random zone data
     */
    private function generateZoneData() {
        return [
            'name' => 'Test Zone ' . $this->generateRandomString(10),
            'status' => $this->generateRandomChoice(['active', 'inactive']),
            'created_by' => null
        ];
    }
    
    /**
     * Generate random state data
     */
    private function generateStateData($countryId, $zoneId) {
        return [
            'name' => 'Test State ' . $this->generateRandomString(10),
            'country_id' => $countryId,
            'zone_id' => $zoneId,
            'status' => $this->generateRandomChoice(['active', 'inactive']),
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
            'status' => $this->generateRandomChoice(['active', 'inactive']),
            'created_by' => null
        ];
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Create a test country and return its ID
     */
    private function createTestCountry() {
        $name = 'Test Country ' . $this->generateRandomString(10);
        $status = 'active';
        
        $sql = "INSERT INTO countries (name, status) VALUES (?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ss', $name, $status);
        $stmt->execute();
        $id = $this->db->insert_id;
        $stmt->close();
        
        $this->createdRecords['countries'][] = $id;
        return $id;
    }
    
    /**
     * Create a test zone and return its ID
     */
    private function createTestZone() {
        $name = 'Test Zone ' . $this->generateRandomString(10);
        $status = 'active';
        
        $sql = "INSERT INTO zones (name, status) VALUES (?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ss', $name, $status);
        $stmt->execute();
        $id = $this->db->insert_id;
        $stmt->close();
        
        $this->createdRecords['zones'][] = $id;
        return $id;
    }
    
    /**
     * Create a test state and return its ID
     */
    private function createTestState($countryId, $zoneId) {
        $name = 'Test State ' . $this->generateRandomString(10);
        $status = 'active';
        
        $sql = "INSERT INTO states (name, country_id, zone_id, status) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('siis', $name, $countryId, $zoneId, $status);
        $stmt->execute();
        $id = $this->db->insert_id;
        $stmt->close();
        
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

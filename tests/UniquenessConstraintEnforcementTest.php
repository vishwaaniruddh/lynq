<?php
/**
 * Property Test for Uniqueness Constraint Enforcement
 * **Feature: crm-master-modules, Property 4: Uniqueness Constraint Enforcement**
 * **Validates: Requirements 2.2, 3.2, 4.2, 5.2, 6.2, 9.2**
 * 
 * For any attempt to create or update a record with a duplicate unique field 
 * (bank name, customer email, country name, zone name, state name within country, 
 * city name within state), the operation should fail with a duplicate error.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/BankService.php';
require_once __DIR__ . '/../services/CustomerService.php';
require_once __DIR__ . '/../services/LocationService.php';

class UniquenessConstraintEnforcementTest extends PropertyTestBase {
    
    private $bankService;
    private $customerService;
    private $locationService;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->bankService = new BankService();
        $this->customerService = new CustomerService();
        $this->locationService = new LocationService();
    }
    
    public function runTests() {
        echo "=== Uniqueness Constraint Enforcement Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test bank name uniqueness
        $allPassed &= $this->runPropertyTest(
            "Bank Name Uniqueness Constraint",
            [$this, 'testBankNameUniqueness']
        );
        
        // Test customer email uniqueness
        $allPassed &= $this->runPropertyTest(
            "Customer Email Uniqueness Constraint",
            [$this, 'testCustomerEmailUniqueness']
        );
        
        // Test country name uniqueness
        $allPassed &= $this->runPropertyTest(
            "Country Name Uniqueness Constraint",
            [$this, 'testCountryNameUniqueness']
        );
        
        // Test zone name uniqueness
        $allPassed &= $this->runPropertyTest(
            "Zone Name Uniqueness Constraint",
            [$this, 'testZoneNameUniqueness']
        );
        
        // Test state name uniqueness within country
        $allPassed &= $this->runPropertyTest(
            "State Name Uniqueness Within Country",
            [$this, 'testStateNameUniquenessWithinCountry']
        );
        
        // Test city name uniqueness within state
        $allPassed &= $this->runPropertyTest(
            "City Name Uniqueness Within State",
            [$this, 'testCityNameUniquenessWithinState']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 4: Bank name uniqueness constraint
     * Creating a bank with a duplicate name should fail
     */
    public function testBankNameUniqueness() {
        try {
            // Generate unique bank name
            $bankName = 'Test Bank ' . $this->generateRandomString(15);
            
            // Create first bank
            $result1 = $this->bankService->create(['name' => $bankName]);
            $this->assert($result1['success'], "First bank creation should succeed");
            $this->createdRecords['banks'][] = $result1['data']['id'];
            
            // Try to create second bank with same name
            $result2 = $this->bankService->create(['name' => $bankName]);
            $this->assert(!$result2['success'], "Second bank creation with duplicate name should fail");
            $this->assert(
                $result2['code'] === 'DUPLICATE_ERROR',
                "Error code should be DUPLICATE_ERROR, got: " . ($result2['code'] ?? 'none')
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['bankName' => $bankName ?? null]
            ];
        }
    }
    
    /**
     * Property 4: Customer email uniqueness constraint
     * Creating a customer with a duplicate email should fail
     */
    public function testCustomerEmailUniqueness() {
        try {
            // Generate unique email
            $email = 'test_' . $this->generateRandomString(15) . '@example.com';
            
            // Create first customer
            $result1 = $this->customerService->create([
                'name' => 'Test Customer 1',
                'email' => $email
            ]);
            $this->assert($result1['success'], "First customer creation should succeed");
            $this->createdRecords['customers'][] = $result1['data']['id'];
            
            // Try to create second customer with same email
            $result2 = $this->customerService->create([
                'name' => 'Test Customer 2',
                'email' => $email
            ]);
            $this->assert(!$result2['success'], "Second customer creation with duplicate email should fail");
            $this->assert(
                $result2['code'] === 'DUPLICATE_ERROR',
                "Error code should be DUPLICATE_ERROR, got: " . ($result2['code'] ?? 'none')
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['email' => $email ?? null]
            ];
        }
    }
    
    /**
     * Property 4: Country name uniqueness constraint
     * Creating a country with a duplicate name should fail
     */
    public function testCountryNameUniqueness() {
        try {
            // Generate unique country name
            $countryName = 'Test Country ' . $this->generateRandomString(15);
            
            // Create first country
            $result1 = $this->locationService->createCountry(['name' => $countryName]);
            $this->assert($result1['success'], "First country creation should succeed");
            $this->createdRecords['countries'][] = $result1['data']['id'];
            
            // Try to create second country with same name
            $result2 = $this->locationService->createCountry(['name' => $countryName]);
            $this->assert(!$result2['success'], "Second country creation with duplicate name should fail");
            $this->assert(
                $result2['code'] === 'DUPLICATE_ERROR',
                "Error code should be DUPLICATE_ERROR, got: " . ($result2['code'] ?? 'none')
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['countryName' => $countryName ?? null]
            ];
        }
    }
    
    /**
     * Property 4: Zone name uniqueness constraint
     * Creating a zone with a duplicate name should fail
     */
    public function testZoneNameUniqueness() {
        try {
            // Generate unique zone name
            $zoneName = 'Test Zone ' . $this->generateRandomString(15);
            
            // Create first zone
            $result1 = $this->locationService->createZone(['name' => $zoneName]);
            $this->assert($result1['success'], "First zone creation should succeed");
            $this->createdRecords['zones'][] = $result1['data']['id'];
            
            // Try to create second zone with same name
            $result2 = $this->locationService->createZone(['name' => $zoneName]);
            $this->assert(!$result2['success'], "Second zone creation with duplicate name should fail");
            $this->assert(
                $result2['code'] === 'DUPLICATE_ERROR',
                "Error code should be DUPLICATE_ERROR, got: " . ($result2['code'] ?? 'none')
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['zoneName' => $zoneName ?? null]
            ];
        }
    }
    
    /**
     * Property 4: State name uniqueness within country
     * Creating a state with a duplicate name in the same country should fail
     */
    public function testStateNameUniquenessWithinCountry() {
        try {
            // Create a country first
            $countryName = 'Test Country ' . $this->generateRandomString(15);
            $countryResult = $this->locationService->createCountry(['name' => $countryName]);
            $this->assert($countryResult['success'], "Country creation should succeed");
            $countryId = $countryResult['data']['id'];
            $this->createdRecords['countries'][] = $countryId;
            
            // Generate unique state name
            $stateName = 'Test State ' . $this->generateRandomString(15);
            
            // Create first state
            $result1 = $this->locationService->createState([
                'name' => $stateName,
                'country_id' => $countryId
            ]);
            $this->assert($result1['success'], "First state creation should succeed");
            $this->createdRecords['states'][] = $result1['data']['id'];
            
            // Try to create second state with same name in same country
            $result2 = $this->locationService->createState([
                'name' => $stateName,
                'country_id' => $countryId
            ]);
            $this->assert(!$result2['success'], "Second state creation with duplicate name in same country should fail");
            $this->assert(
                $result2['code'] === 'DUPLICATE_ERROR',
                "Error code should be DUPLICATE_ERROR, got: " . ($result2['code'] ?? 'none')
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['stateName' => $stateName ?? null, 'countryId' => $countryId ?? null]
            ];
        }
    }
    
    /**
     * Property 4: City name uniqueness within state
     * Creating a city with a duplicate name in the same state should fail
     */
    public function testCityNameUniquenessWithinState() {
        try {
            // Create a country first
            $countryName = 'Test Country ' . $this->generateRandomString(15);
            $countryResult = $this->locationService->createCountry(['name' => $countryName]);
            $this->assert($countryResult['success'], "Country creation should succeed");
            $countryId = $countryResult['data']['id'];
            $this->createdRecords['countries'][] = $countryId;
            
            // Create a state
            $stateName = 'Test State ' . $this->generateRandomString(15);
            $stateResult = $this->locationService->createState([
                'name' => $stateName,
                'country_id' => $countryId
            ]);
            $this->assert($stateResult['success'], "State creation should succeed");
            $stateId = $stateResult['data']['id'];
            $this->createdRecords['states'][] = $stateId;
            
            // Generate unique city name
            $cityName = 'Test City ' . $this->generateRandomString(15);
            
            // Create first city
            $result1 = $this->locationService->createCity([
                'name' => $cityName,
                'state_id' => $stateId
            ]);
            $this->assert($result1['success'], "First city creation should succeed");
            $this->createdRecords['cities'][] = $result1['data']['id'];
            
            // Try to create second city with same name in same state
            $result2 = $this->locationService->createCity([
                'name' => $cityName,
                'state_id' => $stateId
            ]);
            $this->assert(!$result2['success'], "Second city creation with duplicate name in same state should fail");
            $this->assert(
                $result2['code'] === 'DUPLICATE_ERROR',
                "Error code should be DUPLICATE_ERROR, got: " . ($result2['code'] ?? 'none')
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['cityName' => $cityName ?? null, 'stateId' => $stateId ?? null]
            ];
        }
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

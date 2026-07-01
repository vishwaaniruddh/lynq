<?php
/**
 * Property Test for Required Field Validation
 * **Feature: crm-master-modules, Property 12: Required Field Validation**
 * **Validates: Requirements 9.1**
 * 
 * For any form submission with empty required fields, the operation should fail 
 * with validation errors before database interaction.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/BankService.php';
require_once __DIR__ . '/../services/CustomerService.php';
require_once __DIR__ . '/../services/LocationService.php';

class RequiredFieldValidationTest extends PropertyTestBase {
    
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
        echo "=== Required Field Validation Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test bank required fields
        $allPassed &= $this->runPropertyTest(
            "Bank Required Field Validation",
            [$this, 'testBankRequiredFields']
        );
        
        // Test customer required fields
        $allPassed &= $this->runPropertyTest(
            "Customer Required Field Validation",
            [$this, 'testCustomerRequiredFields']
        );
        
        // Test country required fields
        $allPassed &= $this->runPropertyTest(
            "Country Required Field Validation",
            [$this, 'testCountryRequiredFields']
        );
        
        // Test zone required fields
        $allPassed &= $this->runPropertyTest(
            "Zone Required Field Validation",
            [$this, 'testZoneRequiredFields']
        );
        
        // Test state required fields
        $allPassed &= $this->runPropertyTest(
            "State Required Field Validation",
            [$this, 'testStateRequiredFields']
        );
        
        // Test city required fields
        $allPassed &= $this->runPropertyTest(
            "City Required Field Validation",
            [$this, 'testCityRequiredFields']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 12: Bank required field validation
     * Creating a bank without required fields should fail with validation error
     */
    public function testBankRequiredFields() {
        try {
            // Test with empty name
            $result1 = $this->bankService->create(['name' => '']);
            $this->assert(!$result1['success'], "Bank creation with empty name should fail");
            $this->assert(
                $result1['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR, got: " . ($result1['code'] ?? 'none')
            );
            $this->assert(
                isset($result1['errors']['name']),
                "Errors should include 'name' field"
            );
            
            // Test with whitespace-only name
            $result2 = $this->bankService->create(['name' => '   ']);
            $this->assert(!$result2['success'], "Bank creation with whitespace name should fail");
            $this->assert(
                $result2['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            // Test with missing name field
            $result3 = $this->bankService->create([]);
            $this->assert(!$result3['success'], "Bank creation with missing name should fail");
            $this->assert(
                $result3['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 12: Customer required field validation
     * Creating a customer without required fields should fail with validation error
     */
    public function testCustomerRequiredFields() {
        try {
            // Test with empty name
            $result1 = $this->customerService->create([
                'name' => '',
                'email' => 'test@example.com'
            ]);
            $this->assert(!$result1['success'], "Customer creation with empty name should fail");
            $this->assert(
                $result1['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR, got: " . ($result1['code'] ?? 'none')
            );
            $this->assert(
                isset($result1['errors']['name']),
                "Errors should include 'name' field"
            );
            
            // Test with empty email
            $result2 = $this->customerService->create([
                'name' => 'Test Customer',
                'email' => ''
            ]);
            $this->assert(!$result2['success'], "Customer creation with empty email should fail");
            $this->assert(
                $result2['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            $this->assert(
                isset($result2['errors']['email']),
                "Errors should include 'email' field"
            );
            
            // Test with invalid email format
            $result3 = $this->customerService->create([
                'name' => 'Test Customer',
                'email' => 'invalid-email'
            ]);
            $this->assert(!$result3['success'], "Customer creation with invalid email should fail");
            $this->assert(
                $result3['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            // Test with missing both required fields
            $result4 = $this->customerService->create([]);
            $this->assert(!$result4['success'], "Customer creation with missing fields should fail");
            $this->assert(
                $result4['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 12: Country required field validation
     * Creating a country without required fields should fail with validation error
     */
    public function testCountryRequiredFields() {
        try {
            // Test with empty name
            $result1 = $this->locationService->createCountry(['name' => '']);
            $this->assert(!$result1['success'], "Country creation with empty name should fail");
            $this->assert(
                $result1['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR, got: " . ($result1['code'] ?? 'none')
            );
            $this->assert(
                isset($result1['errors']['name']),
                "Errors should include 'name' field"
            );
            
            // Test with whitespace-only name
            $result2 = $this->locationService->createCountry(['name' => '   ']);
            $this->assert(!$result2['success'], "Country creation with whitespace name should fail");
            $this->assert(
                $result2['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            // Test with missing name field
            $result3 = $this->locationService->createCountry([]);
            $this->assert(!$result3['success'], "Country creation with missing name should fail");
            $this->assert(
                $result3['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 12: Zone required field validation
     * Creating a zone without required fields should fail with validation error
     */
    public function testZoneRequiredFields() {
        try {
            // Test with empty name
            $result1 = $this->locationService->createZone(['name' => '']);
            $this->assert(!$result1['success'], "Zone creation with empty name should fail");
            $this->assert(
                $result1['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR, got: " . ($result1['code'] ?? 'none')
            );
            $this->assert(
                isset($result1['errors']['name']),
                "Errors should include 'name' field"
            );
            
            // Test with whitespace-only name
            $result2 = $this->locationService->createZone(['name' => '   ']);
            $this->assert(!$result2['success'], "Zone creation with whitespace name should fail");
            $this->assert(
                $result2['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            // Test with missing name field
            $result3 = $this->locationService->createZone([]);
            $this->assert(!$result3['success'], "Zone creation with missing name should fail");
            $this->assert(
                $result3['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 12: State required field validation
     * Creating a state without required fields should fail with validation error
     */
    public function testStateRequiredFields() {
        try {
            // First create a country for valid state creation tests
            $countryName = 'Test Country ' . $this->generateRandomString(15);
            $countryResult = $this->locationService->createCountry(['name' => $countryName]);
            $this->assert($countryResult['success'], "Country creation should succeed");
            $countryId = $countryResult['data']['id'];
            $this->createdRecords['countries'][] = $countryId;
            
            // Test with empty name
            $result1 = $this->locationService->createState([
                'name' => '',
                'country_id' => $countryId
            ]);
            $this->assert(!$result1['success'], "State creation with empty name should fail");
            $this->assert(
                $result1['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR, got: " . ($result1['code'] ?? 'none')
            );
            $this->assert(
                isset($result1['errors']['name']),
                "Errors should include 'name' field"
            );
            
            // Test with missing country_id
            $result2 = $this->locationService->createState([
                'name' => 'Test State'
            ]);
            $this->assert(!$result2['success'], "State creation with missing country_id should fail");
            $this->assert(
                $result2['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            $this->assert(
                isset($result2['errors']['country_id']),
                "Errors should include 'country_id' field"
            );
            
            // Test with invalid country_id (0)
            $result3 = $this->locationService->createState([
                'name' => 'Test State',
                'country_id' => 0
            ]);
            $this->assert(!$result3['success'], "State creation with invalid country_id should fail");
            $this->assert(
                $result3['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            // Test with missing both required fields
            $result4 = $this->locationService->createState([]);
            $this->assert(!$result4['success'], "State creation with missing fields should fail");
            $this->assert(
                $result4['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 12: City required field validation
     * Creating a city without required fields should fail with validation error
     */
    public function testCityRequiredFields() {
        try {
            // First create a country and state for valid city creation tests
            $countryName = 'Test Country ' . $this->generateRandomString(15);
            $countryResult = $this->locationService->createCountry(['name' => $countryName]);
            $this->assert($countryResult['success'], "Country creation should succeed");
            $countryId = $countryResult['data']['id'];
            $this->createdRecords['countries'][] = $countryId;
            
            $stateName = 'Test State ' . $this->generateRandomString(15);
            $stateResult = $this->locationService->createState([
                'name' => $stateName,
                'country_id' => $countryId
            ]);
            $this->assert($stateResult['success'], "State creation should succeed");
            $stateId = $stateResult['data']['id'];
            $this->createdRecords['states'][] = $stateId;
            
            // Test with empty name
            $result1 = $this->locationService->createCity([
                'name' => '',
                'state_id' => $stateId
            ]);
            $this->assert(!$result1['success'], "City creation with empty name should fail");
            $this->assert(
                $result1['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR, got: " . ($result1['code'] ?? 'none')
            );
            $this->assert(
                isset($result1['errors']['name']),
                "Errors should include 'name' field"
            );
            
            // Test with missing state_id
            $result2 = $this->locationService->createCity([
                'name' => 'Test City'
            ]);
            $this->assert(!$result2['success'], "City creation with missing state_id should fail");
            $this->assert(
                $result2['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            $this->assert(
                isset($result2['errors']['state_id']),
                "Errors should include 'state_id' field"
            );
            
            // Test with invalid state_id (0)
            $result3 = $this->locationService->createCity([
                'name' => 'Test City',
                'state_id' => 0
            ]);
            $this->assert(!$result3['success'], "City creation with invalid state_id should fail");
            $this->assert(
                $result3['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            // Test with missing both required fields
            $result4 = $this->locationService->createCity([]);
            $this->assert(!$result4['success'], "City creation with missing fields should fail");
            $this->assert(
                $result4['code'] === 'VALIDATION_ERROR',
                "Error code should be VALIDATION_ERROR"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Delete in reverse order of dependencies
            $tables = ['cities', 'states', 'zones', 'countries'];
            
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
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

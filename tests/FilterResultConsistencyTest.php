<?php
/**
 * Filter Result Consistency Property Test
 * 
 * **Feature: crm-master-modules, Property 7: Filter Result Consistency**
 * **Validates: Requirements 3.5, 4.5, 6.4, 10.2**
 * 
 * Property: For any filter applied (status, country, state, zone), 
 * all returned records should match the filter criteria.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/LocationService.php';
require_once __DIR__ . '/../services/BankService.php';
require_once __DIR__ . '/../services/CustomerService.php';

class FilterResultConsistencyTest extends PropertyTestBase {
    private $locationService;
    private $bankService;
    private $customerService;
    private $createdCountries = [];
    private $createdStates = [];
    private $createdZones = [];
    private $createdCities = [];
    private $createdBanks = [];
    private $createdCustomers = [];
    
    public function __construct() {
        parent::__construct();
        $this->locationService = new LocationService();
        $this->bankService = new BankService();
        $this->customerService = new CustomerService();
    }
    
    /**
     * Run all filter consistency property tests
     */
    public function runAllTests() {
        echo "\n=== Filter Result Consistency Property Tests ===\n";
        echo "**Feature: crm-master-modules, Property 7: Filter Result Consistency**\n";
        echo "**Validates: Requirements 3.5, 4.5, 6.4, 10.2**\n\n";
        
        $results = [];
        
        // Setup test data
        $this->setupTestData();
        
        try {
            // Test 1: Status filter consistency for countries
            $results['country_status_filter'] = $this->runPropertyTest(
                'Country Status Filter Consistency',
                function() { return $this->testCountryStatusFilter(); },
                50
            );
            
            // Test 2: Status filter consistency for states
            $results['state_status_filter'] = $this->runPropertyTest(
                'State Status Filter Consistency',
                function() { return $this->testStateStatusFilter(); },
                50
            );
            
            // Test 3: Country filter consistency for states
            $results['state_country_filter'] = $this->runPropertyTest(
                'State Country Filter Consistency',
                function() { return $this->testStateCountryFilter(); },
                50
            );
            
            // Test 4: Zone filter consistency for states
            $results['state_zone_filter'] = $this->runPropertyTest(
                'State Zone Filter Consistency',
                function() { return $this->testStateZoneFilter(); },
                50
            );
            
            // Test 5: Status filter consistency for cities
            $results['city_status_filter'] = $this->runPropertyTest(
                'City Status Filter Consistency',
                function() { return $this->testCityStatusFilter(); },
                50
            );
            
            // Test 6: State filter consistency for cities
            $results['city_state_filter'] = $this->runPropertyTest(
                'City State Filter Consistency',
                function() { return $this->testCityStateFilter(); },
                50
            );
            
            // Test 7: Zone filter consistency for cities
            $results['city_zone_filter'] = $this->runPropertyTest(
                'City Zone Filter Consistency',
                function() { return $this->testCityZoneFilter(); },
                50
            );
            
            // Test 8: Status filter consistency for banks
            $results['bank_status_filter'] = $this->runPropertyTest(
                'Bank Status Filter Consistency',
                function() { return $this->testBankStatusFilter(); },
                50
            );
            
            // Test 9: Status filter consistency for customers
            $results['customer_status_filter'] = $this->runPropertyTest(
                'Customer Status Filter Consistency',
                function() { return $this->testCustomerStatusFilter(); },
                50
            );
            
        } finally {
            $this->cleanupTestData();
        }
        
        // Summary
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($results));
        $total = count($results);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }
    
    /**
     * Setup test data for filter tests
     */
    private function setupTestData() {
        echo "Setting up test data...\n";
        
        // Create zones
        for ($i = 0; $i < 3; $i++) {
            $status = $this->generateRandomChoice(['active', 'inactive']);
            $result = $this->locationService->createZone([
                'name' => 'TestZone_' . $this->generateRandomString(8),
                'status' => $status
            ]);
            if ($result['success']) {
                $this->createdZones[] = $result['data'];
            }
        }
        
        // Create countries
        for ($i = 0; $i < 3; $i++) {
            $status = $this->generateRandomChoice(['active', 'inactive']);
            $result = $this->locationService->createCountry([
                'name' => 'TestCountry_' . $this->generateRandomString(8),
                'status' => $status
            ]);
            if ($result['success']) {
                $this->createdCountries[] = $result['data'];
            }
        }
        
        // Create states
        foreach ($this->createdCountries as $country) {
            for ($i = 0; $i < 2; $i++) {
                $status = $this->generateRandomChoice(['active', 'inactive']);
                $zone = !empty($this->createdZones) ? $this->generateRandomChoice($this->createdZones) : null;
                $result = $this->locationService->createState([
                    'name' => 'TestState_' . $this->generateRandomString(8),
                    'country_id' => $country['id'],
                    'zone_id' => $zone ? $zone['id'] : null,
                    'status' => $status
                ]);
                if ($result['success']) {
                    $this->createdStates[] = $result['data'];
                }
            }
        }
        
        // Create cities
        foreach ($this->createdStates as $state) {
            for ($i = 0; $i < 2; $i++) {
                $status = $this->generateRandomChoice(['active', 'inactive']);
                $zone = !empty($this->createdZones) ? $this->generateRandomChoice($this->createdZones) : null;
                $result = $this->locationService->createCity([
                    'name' => 'TestCity_' . $this->generateRandomString(8),
                    'state_id' => $state['id'],
                    'zone_id' => $zone ? $zone['id'] : null,
                    'status' => $status
                ]);
                if ($result['success']) {
                    $this->createdCities[] = $result['data'];
                }
            }
        }
        
        // Create banks
        for ($i = 0; $i < 5; $i++) {
            $status = $this->generateRandomChoice([0, 1]);
            $result = $this->bankService->create([
                'name' => 'TestBank_' . $this->generateRandomString(8),
                'status' => $status
            ]);
            if ($result['success']) {
                $this->createdBanks[] = $result['data'];
            }
        }
        
        // Create customers
        for ($i = 0; $i < 5; $i++) {
            $status = $this->generateRandomChoice([0, 1]);
            $result = $this->customerService->create([
                'name' => 'TestCustomer_' . $this->generateRandomString(8),
                'email' => $this->generateRandomEmail(),
                'status' => $status
            ]);
            if ($result['success']) {
                $this->createdCustomers[] = $result['data'];
            }
        }
        
        echo "Test data created: " . count($this->createdCountries) . " countries, " 
            . count($this->createdStates) . " states, " 
            . count($this->createdZones) . " zones, "
            . count($this->createdCities) . " cities, "
            . count($this->createdBanks) . " banks, "
            . count($this->createdCustomers) . " customers\n\n";
    }
    
    /**
     * Test: Country status filter returns only matching records
     */
    private function testCountryStatusFilter() {
        $status = $this->generateRandomChoice(['active', 'inactive']);
        
        $result = $this->locationService->getAllCountries(['status' => $status, 'limit' => 100]);
        
        foreach ($result['data'] as $country) {
            if ($country['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Country with status '{$country['status']}' returned when filtering for '$status'",
                    'data' => ['country' => $country, 'expected_status' => $status]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: State status filter returns only matching records
     */
    private function testStateStatusFilter() {
        $status = $this->generateRandomChoice(['active', 'inactive']);
        
        $result = $this->locationService->getAllStates(['status' => $status, 'limit' => 100]);
        
        foreach ($result['data'] as $state) {
            if ($state['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "State with status '{$state['status']}' returned when filtering for '$status'",
                    'data' => ['state' => $state, 'expected_status' => $status]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: State country filter returns only matching records
     */
    private function testStateCountryFilter() {
        if (empty($this->createdCountries)) {
            return ['success' => true]; // Skip if no test data
        }
        
        $country = $this->generateRandomChoice($this->createdCountries);
        
        $result = $this->locationService->getAllStates(['country_id' => $country['id'], 'limit' => 100]);
        
        foreach ($result['data'] as $state) {
            if ((int)$state['country_id'] !== (int)$country['id']) {
                return [
                    'success' => false,
                    'message' => "State with country_id '{$state['country_id']}' returned when filtering for country '{$country['id']}'",
                    'data' => ['state' => $state, 'expected_country_id' => $country['id']]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: State zone filter returns only matching records
     */
    private function testStateZoneFilter() {
        if (empty($this->createdZones)) {
            return ['success' => true]; // Skip if no test data
        }
        
        $zone = $this->generateRandomChoice($this->createdZones);
        
        $result = $this->locationService->getAllStates(['zone_id' => $zone['id'], 'limit' => 100]);
        
        foreach ($result['data'] as $state) {
            if ($state['zone_id'] !== null && (int)$state['zone_id'] !== (int)$zone['id']) {
                return [
                    'success' => false,
                    'message' => "State with zone_id '{$state['zone_id']}' returned when filtering for zone '{$zone['id']}'",
                    'data' => ['state' => $state, 'expected_zone_id' => $zone['id']]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: City status filter returns only matching records
     */
    private function testCityStatusFilter() {
        $status = $this->generateRandomChoice(['active', 'inactive']);
        
        $result = $this->locationService->getAllCities(['status' => $status, 'limit' => 100]);
        
        foreach ($result['data'] as $city) {
            if ($city['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "City with status '{$city['status']}' returned when filtering for '$status'",
                    'data' => ['city' => $city, 'expected_status' => $status]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: City state filter returns only matching records
     */
    private function testCityStateFilter() {
        if (empty($this->createdStates)) {
            return ['success' => true]; // Skip if no test data
        }
        
        $state = $this->generateRandomChoice($this->createdStates);
        
        $result = $this->locationService->getAllCities(['state_id' => $state['id'], 'limit' => 100]);
        
        foreach ($result['data'] as $city) {
            if ((int)$city['state_id'] !== (int)$state['id']) {
                return [
                    'success' => false,
                    'message' => "City with state_id '{$city['state_id']}' returned when filtering for state '{$state['id']}'",
                    'data' => ['city' => $city, 'expected_state_id' => $state['id']]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: City zone filter returns only matching records
     */
    private function testCityZoneFilter() {
        if (empty($this->createdZones)) {
            return ['success' => true]; // Skip if no test data
        }
        
        $zone = $this->generateRandomChoice($this->createdZones);
        
        $result = $this->locationService->getAllCities(['zone_id' => $zone['id'], 'limit' => 100]);
        
        foreach ($result['data'] as $city) {
            if ($city['zone_id'] !== null && (int)$city['zone_id'] !== (int)$zone['id']) {
                return [
                    'success' => false,
                    'message' => "City with zone_id '{$city['zone_id']}' returned when filtering for zone '{$zone['id']}'",
                    'data' => ['city' => $city, 'expected_zone_id' => $zone['id']]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Bank status filter returns only matching records
     */
    private function testBankStatusFilter() {
        $status = $this->generateRandomChoice([0, 1]);
        
        $result = $this->bankService->getAll(['status' => $status, 'limit' => 100]);
        
        foreach ($result['data'] as $bank) {
            if ((int)$bank['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Bank with status '{$bank['status']}' returned when filtering for '$status'",
                    'data' => ['bank' => $bank, 'expected_status' => $status]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Customer status filter returns only matching records
     */
    private function testCustomerStatusFilter() {
        $status = $this->generateRandomChoice([0, 1]);
        
        $result = $this->customerService->getAll(['status' => $status, 'limit' => 100]);
        
        foreach ($result['data'] as $customer) {
            if ((int)$customer['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Customer with status '{$customer['status']}' returned when filtering for '$status'",
                    'data' => ['customer' => $customer, 'expected_status' => $status]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        echo "\nCleaning up test data...\n";
        
        // Delete cities first (no dependencies)
        foreach ($this->createdCities as $city) {
            try {
                $this->locationService->deleteCity($city['id']);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete states (after cities)
        foreach ($this->createdStates as $state) {
            try {
                $this->locationService->deleteState($state['id']);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete countries (after states)
        foreach ($this->createdCountries as $country) {
            try {
                $this->locationService->deleteCountry($country['id']);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete zones
        foreach ($this->createdZones as $zone) {
            try {
                $this->locationService->deleteZone($zone['id']);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete banks
        foreach ($this->createdBanks as $bank) {
            try {
                $this->bankService->delete($bank['id']);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete customers
        foreach ($this->createdCustomers as $customer) {
            try {
                $this->customerService->delete($customer['id']);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        echo "Cleanup complete.\n";
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new FilterResultConsistencyTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}

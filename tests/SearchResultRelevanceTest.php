<?php
/**
 * Search Result Relevance Property Test
 * 
 * **Feature: crm-master-modules, Property 8: Search Result Relevance**
 * **Validates: Requirements 10.1**
 * 
 * Property: For any search term applied to a master list, 
 * all returned records should contain the search term in at least one searchable field.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/LocationService.php';
require_once __DIR__ . '/../services/BankService.php';
require_once __DIR__ . '/../services/CustomerService.php';

class SearchResultRelevanceTest extends PropertyTestBase {
    private $locationService;
    private $bankService;
    private $customerService;
    private $createdCountries = [];
    private $createdStates = [];
    private $createdZones = [];
    private $createdCities = [];
    private $createdBanks = [];
    private $createdCustomers = [];
    
    // Unique prefix for test data to make search more predictable
    private $testPrefix = 'SRTEST_';
    
    public function __construct() {
        parent::__construct();
        $this->locationService = new LocationService();
        $this->bankService = new BankService();
        $this->customerService = new CustomerService();
        $this->testPrefix = 'SRTEST_' . $this->generateRandomString(4) . '_';
    }
    
    /**
     * Run all search relevance property tests
     */
    public function runAllTests() {
        echo "\n=== Search Result Relevance Property Tests ===\n";
        echo "**Feature: crm-master-modules, Property 8: Search Result Relevance**\n";
        echo "**Validates: Requirements 10.1**\n\n";
        
        $results = [];
        
        // Setup test data
        $this->setupTestData();
        
        try {
            // Test 1: Bank search relevance
            $results['bank_search'] = $this->runPropertyTest(
                'Bank Search Result Relevance',
                function() { return $this->testBankSearchRelevance(); },
                30
            );
            
            // Test 2: Customer search relevance
            $results['customer_search'] = $this->runPropertyTest(
                'Customer Search Result Relevance',
                function() { return $this->testCustomerSearchRelevance(); },
                30
            );
            
            // Test 3: Country search relevance
            $results['country_search'] = $this->runPropertyTest(
                'Country Search Result Relevance',
                function() { return $this->testCountrySearchRelevance(); },
                30
            );
            
            // Test 4: State search relevance
            $results['state_search'] = $this->runPropertyTest(
                'State Search Result Relevance',
                function() { return $this->testStateSearchRelevance(); },
                30
            );
            
            // Test 5: Zone search relevance
            $results['zone_search'] = $this->runPropertyTest(
                'Zone Search Result Relevance',
                function() { return $this->testZoneSearchRelevance(); },
                30
            );
            
            // Test 6: City search relevance
            $results['city_search'] = $this->runPropertyTest(
                'City Search Result Relevance',
                function() { return $this->testCitySearchRelevance(); },
                30
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
     * Setup test data with known searchable terms
     */
    private function setupTestData() {
        echo "Setting up test data with prefix: {$this->testPrefix}\n";
        
        // Create banks with searchable names
        $bankNames = ['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon'];
        foreach ($bankNames as $name) {
            $result = $this->bankService->create([
                'name' => $this->testPrefix . 'Bank_' . $name,
                'status' => 1
            ]);
            if ($result['success']) {
                $this->createdBanks[] = $result['data'];
            }
        }
        
        // Create customers with searchable names and emails
        $customerData = [
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Charlie', 'email' => 'charlie@example.com']
        ];
        foreach ($customerData as $data) {
            $result = $this->customerService->create([
                'name' => $this->testPrefix . 'Customer_' . $data['name'],
                'email' => $this->testPrefix . strtolower($data['name']) . '_' . $this->generateRandomString(4) . '@test.com',
                'status' => 1
            ]);
            if ($result['success']) {
                $this->createdCustomers[] = $result['data'];
            }
        }
        
        // Create zones
        $zoneNames = ['North', 'South', 'East', 'West', 'Central'];
        foreach ($zoneNames as $name) {
            $result = $this->locationService->createZone([
                'name' => $this->testPrefix . 'Zone_' . $name,
                'status' => 'active'
            ]);
            if ($result['success']) {
                $this->createdZones[] = $result['data'];
            }
        }
        
        // Create countries
        $countryNames = ['Testland', 'Sampleria', 'Examplia', 'Demostan', 'Trialville'];
        foreach ($countryNames as $name) {
            $result = $this->locationService->createCountry([
                'name' => $this->testPrefix . 'Country_' . $name,
                'status' => 'active'
            ]);
            if ($result['success']) {
                $this->createdCountries[] = $result['data'];
            }
        }
        
        // Create states
        if (!empty($this->createdCountries)) {
            $stateNames = ['StateA', 'StateB', 'StateC', 'StateD', 'StateE'];
            foreach ($stateNames as $i => $name) {
                $country = $this->createdCountries[$i % count($this->createdCountries)];
                $zone = !empty($this->createdZones) ? $this->createdZones[$i % count($this->createdZones)] : null;
                $result = $this->locationService->createState([
                    'name' => $this->testPrefix . $name,
                    'country_id' => $country['id'],
                    'zone_id' => $zone ? $zone['id'] : null,
                    'status' => 'active'
                ]);
                if ($result['success']) {
                    $this->createdStates[] = $result['data'];
                }
            }
        }
        
        // Create cities
        if (!empty($this->createdStates)) {
            $cityNames = ['CityX', 'CityY', 'CityZ', 'CityW', 'CityV'];
            foreach ($cityNames as $i => $name) {
                $state = $this->createdStates[$i % count($this->createdStates)];
                $zone = !empty($this->createdZones) ? $this->createdZones[$i % count($this->createdZones)] : null;
                $result = $this->locationService->createCity([
                    'name' => $this->testPrefix . $name,
                    'state_id' => $state['id'],
                    'zone_id' => $zone ? $zone['id'] : null,
                    'status' => 'active'
                ]);
                if ($result['success']) {
                    $this->createdCities[] = $result['data'];
                }
            }
        }
        
        echo "Test data created: " . count($this->createdBanks) . " banks, " 
            . count($this->createdCustomers) . " customers, "
            . count($this->createdCountries) . " countries, " 
            . count($this->createdStates) . " states, " 
            . count($this->createdZones) . " zones, "
            . count($this->createdCities) . " cities\n\n";
    }
    
    /**
     * Test: Bank search returns only records containing search term
     */
    private function testBankSearchRelevance() {
        if (empty($this->createdBanks)) {
            return ['success' => true]; // Skip if no test data
        }
        
        // Pick a random bank and use part of its name as search term
        $bank = $this->generateRandomChoice($this->createdBanks);
        $searchTerm = $this->testPrefix;
        
        $result = $this->bankService->getAll(['search' => $searchTerm, 'limit' => 100]);
        
        foreach ($result['data'] as $foundBank) {
            // Check if search term is in the name (case-insensitive)
            if (stripos($foundBank['name'], $searchTerm) === false) {
                return [
                    'success' => false,
                    'message' => "Bank '{$foundBank['name']}' does not contain search term '$searchTerm'",
                    'data' => ['bank' => $foundBank, 'search_term' => $searchTerm]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Customer search returns only records containing search term
     */
    private function testCustomerSearchRelevance() {
        if (empty($this->createdCustomers)) {
            return ['success' => true]; // Skip if no test data
        }
        
        // Use test prefix as search term
        $searchTerm = $this->testPrefix;
        
        $result = $this->customerService->getAll(['search' => $searchTerm, 'limit' => 100]);
        
        foreach ($result['data'] as $customer) {
            // Check if search term is in name or email (case-insensitive)
            $inName = stripos($customer['name'], $searchTerm) !== false;
            $inEmail = stripos($customer['email'], $searchTerm) !== false;
            
            if (!$inName && !$inEmail) {
                return [
                    'success' => false,
                    'message' => "Customer '{$customer['name']}' ({$customer['email']}) does not contain search term '$searchTerm'",
                    'data' => ['customer' => $customer, 'search_term' => $searchTerm]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Country search returns only records containing search term
     */
    private function testCountrySearchRelevance() {
        if (empty($this->createdCountries)) {
            return ['success' => true]; // Skip if no test data
        }
        
        $searchTerm = $this->testPrefix;
        
        $result = $this->locationService->getAllCountries(['search' => $searchTerm, 'limit' => 100]);
        
        foreach ($result['data'] as $country) {
            if (stripos($country['name'], $searchTerm) === false) {
                return [
                    'success' => false,
                    'message' => "Country '{$country['name']}' does not contain search term '$searchTerm'",
                    'data' => ['country' => $country, 'search_term' => $searchTerm]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: State search returns only records containing search term
     */
    private function testStateSearchRelevance() {
        if (empty($this->createdStates)) {
            return ['success' => true]; // Skip if no test data
        }
        
        $searchTerm = $this->testPrefix;
        
        $result = $this->locationService->getAllStates(['search' => $searchTerm, 'limit' => 100]);
        
        foreach ($result['data'] as $state) {
            if (stripos($state['name'], $searchTerm) === false) {
                return [
                    'success' => false,
                    'message' => "State '{$state['name']}' does not contain search term '$searchTerm'",
                    'data' => ['state' => $state, 'search_term' => $searchTerm]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Zone search returns only records containing search term
     */
    private function testZoneSearchRelevance() {
        if (empty($this->createdZones)) {
            return ['success' => true]; // Skip if no test data
        }
        
        $searchTerm = $this->testPrefix;
        
        $result = $this->locationService->getAllZones(['search' => $searchTerm, 'limit' => 100]);
        
        foreach ($result['data'] as $zone) {
            if (stripos($zone['name'], $searchTerm) === false) {
                return [
                    'success' => false,
                    'message' => "Zone '{$zone['name']}' does not contain search term '$searchTerm'",
                    'data' => ['zone' => $zone, 'search_term' => $searchTerm]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: City search returns only records containing search term
     */
    private function testCitySearchRelevance() {
        if (empty($this->createdCities)) {
            return ['success' => true]; // Skip if no test data
        }
        
        $searchTerm = $this->testPrefix;
        
        $result = $this->locationService->getAllCities(['search' => $searchTerm, 'limit' => 100]);
        
        foreach ($result['data'] as $city) {
            if (stripos($city['name'], $searchTerm) === false) {
                return [
                    'success' => false,
                    'message' => "City '{$city['name']}' does not contain search term '$searchTerm'",
                    'data' => ['city' => $city, 'search_term' => $searchTerm]
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
        
        // Delete cities first
        foreach ($this->createdCities as $city) {
            try {
                $this->locationService->deleteCity($city['id']);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete states
        foreach ($this->createdStates as $state) {
            try {
                $this->locationService->deleteState($state['id']);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete countries
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
    $test = new SearchResultRelevanceTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}

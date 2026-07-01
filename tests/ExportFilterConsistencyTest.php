<?php
/**
 * Export Filter Consistency Property Test
 * 
 * **Feature: crm-master-modules, Property 13: Export Filter Consistency**
 * **Validates: Requirements 1.6, 10.3**
 * 
 * Property: For any export operation, the exported data should match 
 * the currently applied filters and search criteria.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/LocationService.php';
require_once __DIR__ . '/../services/BankService.php';
require_once __DIR__ . '/../services/CustomerService.php';

class ExportFilterConsistencyTest extends PropertyTestBase {
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
     * Run all export filter consistency property tests
     */
    public function runAllTests() {
        echo "\n=== Export Filter Consistency Property Tests ===\n";
        echo "**Feature: crm-master-modules, Property 13: Export Filter Consistency**\n";
        echo "**Validates: Requirements 1.6, 10.3**\n\n";
        
        $results = [];
        
        // Setup test data
        $this->setupTestData();
        
        try {
            // Test 1: Bank export with status filter
            $results['bank_export_status'] = $this->runPropertyTest(
                'Bank Export Status Filter Consistency',
                function() { return $this->testBankExportStatusFilter(); },
                50
            );
            
            // Test 2: Bank export with search filter
            $results['bank_export_search'] = $this->runPropertyTest(
                'Bank Export Search Filter Consistency',
                function() { return $this->testBankExportSearchFilter(); },
                50
            );
            
            // Test 3: Customer export with status filter
            $results['customer_export_status'] = $this->runPropertyTest(
                'Customer Export Status Filter Consistency',
                function() { return $this->testCustomerExportStatusFilter(); },
                50
            );
            
            // Test 4: Customer export with search filter
            $results['customer_export_search'] = $this->runPropertyTest(
                'Customer Export Search Filter Consistency',
                function() { return $this->testCustomerExportSearchFilter(); },
                50
            );
            
            // Test 5: Country export with status filter
            $results['country_export_status'] = $this->runPropertyTest(
                'Country Export Status Filter Consistency',
                function() { return $this->testCountryExportStatusFilter(); },
                50
            );
            
            // Test 6: State export with country filter
            $results['state_export_country'] = $this->runPropertyTest(
                'State Export Country Filter Consistency',
                function() { return $this->testStateExportCountryFilter(); },
                50
            );
            
            // Test 7: State export with zone filter
            $results['state_export_zone'] = $this->runPropertyTest(
                'State Export Zone Filter Consistency',
                function() { return $this->testStateExportZoneFilter(); },
                50
            );
            
            // Test 8: Zone export with status filter
            $results['zone_export_status'] = $this->runPropertyTest(
                'Zone Export Status Filter Consistency',
                function() { return $this->testZoneExportStatusFilter(); },
                50
            );
            
            // Test 9: City export with state filter
            $results['city_export_state'] = $this->runPropertyTest(
                'City Export State Filter Consistency',
                function() { return $this->testCityExportStateFilter(); },
                50
            );
            
            // Test 10: City export with zone filter
            $results['city_export_zone'] = $this->runPropertyTest(
                'City Export Zone Filter Consistency',
                function() { return $this->testCityExportZoneFilter(); },
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
     * Setup test data for export filter tests
     */
    private function setupTestData() {
        echo "Setting up test data...\n";
        
        // Create zones with varied status
        for ($i = 0; $i < 4; $i++) {
            $status = $i % 2 === 0 ? 'active' : 'inactive';
            $result = $this->locationService->createZone([
                'name' => 'ExportTestZone_' . $this->generateRandomString(8),
                'status' => $status
            ]);
            if ($result['success']) {
                $this->createdZones[] = $result['data'];
            }
        }
        
        // Create countries with varied status
        for ($i = 0; $i < 4; $i++) {
            $status = $i % 2 === 0 ? 'active' : 'inactive';
            $result = $this->locationService->createCountry([
                'name' => 'ExportTestCountry_' . $this->generateRandomString(8),
                'status' => $status
            ]);
            if ($result['success']) {
                $this->createdCountries[] = $result['data'];
            }
        }
        
        // Create states with varied country and zone assignments
        foreach ($this->createdCountries as $country) {
            for ($i = 0; $i < 2; $i++) {
                $status = $i % 2 === 0 ? 'active' : 'inactive';
                $zone = !empty($this->createdZones) ? $this->createdZones[$i % count($this->createdZones)] : null;
                $result = $this->locationService->createState([
                    'name' => 'ExportTestState_' . $this->generateRandomString(8),
                    'country_id' => $country['id'],
                    'zone_id' => $zone ? $zone['id'] : null,
                    'status' => $status
                ]);
                if ($result['success']) {
                    $this->createdStates[] = $result['data'];
                }
            }
        }
        
        // Create cities with varied state and zone assignments
        foreach ($this->createdStates as $state) {
            for ($i = 0; $i < 2; $i++) {
                $status = $i % 2 === 0 ? 'active' : 'inactive';
                $zone = !empty($this->createdZones) ? $this->createdZones[$i % count($this->createdZones)] : null;
                $result = $this->locationService->createCity([
                    'name' => 'ExportTestCity_' . $this->generateRandomString(8),
                    'state_id' => $state['id'],
                    'zone_id' => $zone ? $zone['id'] : null,
                    'status' => $status
                ]);
                if ($result['success']) {
                    $this->createdCities[] = $result['data'];
                }
            }
        }
        
        // Create banks with varied status
        for ($i = 0; $i < 6; $i++) {
            $status = $i % 2 === 0 ? 1 : 0;
            $result = $this->bankService->create([
                'name' => 'ExportTestBank_' . $this->generateRandomString(8),
                'status' => $status
            ]);
            if ($result['success']) {
                $this->createdBanks[] = $result['data'];
            }
        }
        
        // Create customers with varied status
        for ($i = 0; $i < 6; $i++) {
            $status = $i % 2 === 0 ? 1 : 0;
            $result = $this->customerService->create([
                'name' => 'ExportTestCustomer_' . $this->generateRandomString(8),
                'email' => 'exporttest_' . $this->generateRandomEmail(),
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
     * Test: Bank export with status filter returns only matching records
     */
    private function testBankExportStatusFilter() {
        $status = $this->generateRandomChoice([0, 1]);
        
        $exportedBanks = $this->bankService->export(['status' => $status]);
        
        foreach ($exportedBanks as $bank) {
            if ((int)$bank['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Exported bank with status '{$bank['status']}' when filtering for '$status'",
                    'data' => ['bank' => $bank, 'expected_status' => $status]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Bank export with search filter returns only matching records
     */
    private function testBankExportSearchFilter() {
        if (empty($this->createdBanks)) {
            return ['success' => true];
        }
        
        // Use a known search term from our test data
        $searchTerm = 'ExportTestBank';
        
        $exportedBanks = $this->bankService->export(['search' => $searchTerm]);
        
        foreach ($exportedBanks as $bank) {
            if (stripos($bank['name'], $searchTerm) === false) {
                return [
                    'success' => false,
                    'message' => "Exported bank '{$bank['name']}' does not contain search term '$searchTerm'",
                    'data' => ['bank' => $bank, 'search_term' => $searchTerm]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Customer export with status filter returns only matching records
     */
    private function testCustomerExportStatusFilter() {
        $status = $this->generateRandomChoice([0, 1]);
        
        $exportedCustomers = $this->customerService->export(['status' => $status]);
        
        foreach ($exportedCustomers as $customer) {
            if ((int)$customer['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Exported customer with status '{$customer['status']}' when filtering for '$status'",
                    'data' => ['customer' => $customer, 'expected_status' => $status]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Customer export with search filter returns only matching records
     */
    private function testCustomerExportSearchFilter() {
        if (empty($this->createdCustomers)) {
            return ['success' => true];
        }
        
        $searchTerm = 'ExportTestCustomer';
        
        $exportedCustomers = $this->customerService->export(['search' => $searchTerm]);
        
        foreach ($exportedCustomers as $customer) {
            $matchesName = stripos($customer['name'], $searchTerm) !== false;
            $matchesEmail = stripos($customer['email'], $searchTerm) !== false;
            
            if (!$matchesName && !$matchesEmail) {
                return [
                    'success' => false,
                    'message' => "Exported customer '{$customer['name']}' does not contain search term '$searchTerm'",
                    'data' => ['customer' => $customer, 'search_term' => $searchTerm]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Country export with status filter returns only matching records
     */
    private function testCountryExportStatusFilter() {
        $status = $this->generateRandomChoice(['active', 'inactive']);
        
        $exportedCountries = $this->locationService->exportCountries(['status' => $status]);
        
        foreach ($exportedCountries as $country) {
            if ($country['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Exported country with status '{$country['status']}' when filtering for '$status'",
                    'data' => ['country' => $country, 'expected_status' => $status]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: State export with country filter returns only matching records
     */
    private function testStateExportCountryFilter() {
        if (empty($this->createdCountries)) {
            return ['success' => true];
        }
        
        $country = $this->generateRandomChoice($this->createdCountries);
        
        $exportedStates = $this->locationService->exportStates(['country_id' => $country['id']]);
        
        foreach ($exportedStates as $state) {
            if ((int)$state['country_id'] !== (int)$country['id']) {
                return [
                    'success' => false,
                    'message' => "Exported state with country_id '{$state['country_id']}' when filtering for country '{$country['id']}'",
                    'data' => ['state' => $state, 'expected_country_id' => $country['id']]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: State export with zone filter returns only matching records
     */
    private function testStateExportZoneFilter() {
        if (empty($this->createdZones)) {
            return ['success' => true];
        }
        
        $zone = $this->generateRandomChoice($this->createdZones);
        
        $exportedStates = $this->locationService->exportStates(['zone_id' => $zone['id']]);
        
        foreach ($exportedStates as $state) {
            if ($state['zone_id'] !== null && (int)$state['zone_id'] !== (int)$zone['id']) {
                return [
                    'success' => false,
                    'message' => "Exported state with zone_id '{$state['zone_id']}' when filtering for zone '{$zone['id']}'",
                    'data' => ['state' => $state, 'expected_zone_id' => $zone['id']]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Zone export with status filter returns only matching records
     */
    private function testZoneExportStatusFilter() {
        $status = $this->generateRandomChoice(['active', 'inactive']);
        
        $exportedZones = $this->locationService->exportZones(['status' => $status]);
        
        foreach ($exportedZones as $zone) {
            if ($zone['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Exported zone with status '{$zone['status']}' when filtering for '$status'",
                    'data' => ['zone' => $zone, 'expected_status' => $status]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: City export with state filter returns only matching records
     */
    private function testCityExportStateFilter() {
        if (empty($this->createdStates)) {
            return ['success' => true];
        }
        
        $state = $this->generateRandomChoice($this->createdStates);
        
        $exportedCities = $this->locationService->exportCities(['state_id' => $state['id']]);
        
        foreach ($exportedCities as $city) {
            if ((int)$city['state_id'] !== (int)$state['id']) {
                return [
                    'success' => false,
                    'message' => "Exported city with state_id '{$city['state_id']}' when filtering for state '{$state['id']}'",
                    'data' => ['city' => $city, 'expected_state_id' => $state['id']]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: City export with zone filter returns only matching records
     */
    private function testCityExportZoneFilter() {
        if (empty($this->createdZones)) {
            return ['success' => true];
        }
        
        $zone = $this->generateRandomChoice($this->createdZones);
        
        $exportedCities = $this->locationService->exportCities(['zone_id' => $zone['id']]);
        
        foreach ($exportedCities as $city) {
            if ($city['zone_id'] !== null && (int)$city['zone_id'] !== (int)$zone['id']) {
                return [
                    'success' => false,
                    'message' => "Exported city with zone_id '{$city['zone_id']}' when filtering for zone '{$zone['id']}'",
                    'data' => ['city' => $city, 'expected_zone_id' => $zone['id']]
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
    $test = new ExportFilterConsistencyTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}

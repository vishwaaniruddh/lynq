<?php
/**
 * Pagination State Preservation Property Test
 * 
 * **Feature: crm-master-modules, Property 14: Pagination State Preservation**
 * **Validates: Requirements 10.4**
 * 
 * Property: For any page change during pagination, the current filter 
 * and search state should be maintained.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/LocationService.php';
require_once __DIR__ . '/../services/BankService.php';
require_once __DIR__ . '/../services/CustomerService.php';

class PaginationStatePreservationTest extends PropertyTestBase {
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
     * Run all pagination state preservation property tests
     */
    public function runAllTests() {
        echo "\n=== Pagination State Preservation Property Tests ===\n";
        echo "**Feature: crm-master-modules, Property 14: Pagination State Preservation**\n";
        echo "**Validates: Requirements 10.4**\n\n";
        
        $results = [];
        
        // Setup test data
        $this->setupTestData();
        
        try {
            // Test 1: Bank pagination preserves status filter
            $results['bank_pagination_status'] = $this->runPropertyTest(
                'Bank Pagination Preserves Status Filter',
                function() { return $this->testBankPaginationPreservesStatusFilter(); },
                30
            );
            
            // Test 2: Bank pagination preserves search filter
            $results['bank_pagination_search'] = $this->runPropertyTest(
                'Bank Pagination Preserves Search Filter',
                function() { return $this->testBankPaginationPreservesSearchFilter(); },
                30
            );
            
            // Test 3: Customer pagination preserves status filter
            $results['customer_pagination_status'] = $this->runPropertyTest(
                'Customer Pagination Preserves Status Filter',
                function() { return $this->testCustomerPaginationPreservesStatusFilter(); },
                30
            );
            
            // Test 4: Country pagination preserves status filter
            $results['country_pagination_status'] = $this->runPropertyTest(
                'Country Pagination Preserves Status Filter',
                function() { return $this->testCountryPaginationPreservesStatusFilter(); },
                30
            );
            
            // Test 5: State pagination preserves country filter
            $results['state_pagination_country'] = $this->runPropertyTest(
                'State Pagination Preserves Country Filter',
                function() { return $this->testStatePaginationPreservesCountryFilter(); },
                30
            );
            
            // Test 6: State pagination preserves zone filter
            $results['state_pagination_zone'] = $this->runPropertyTest(
                'State Pagination Preserves Zone Filter',
                function() { return $this->testStatePaginationPreservesZoneFilter(); },
                30
            );
            
            // Test 7: City pagination preserves state filter
            $results['city_pagination_state'] = $this->runPropertyTest(
                'City Pagination Preserves State Filter',
                function() { return $this->testCityPaginationPreservesStateFilter(); },
                30
            );
            
            // Test 8: Zone pagination preserves status filter
            $results['zone_pagination_status'] = $this->runPropertyTest(
                'Zone Pagination Preserves Status Filter',
                function() { return $this->testZonePaginationPreservesStatusFilter(); },
                30
            );
            
            // Test 9: Pagination returns consistent total across pages
            $results['pagination_consistent_total'] = $this->runPropertyTest(
                'Pagination Returns Consistent Total Across Pages',
                function() { return $this->testPaginationConsistentTotal(); },
                30
            );
            
            // Test 10: Page navigation returns correct page number
            $results['pagination_correct_page'] = $this->runPropertyTest(
                'Pagination Returns Correct Page Number',
                function() { return $this->testPaginationReturnsCorrectPage(); },
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
     * Setup test data for pagination tests
     */
    private function setupTestData() {
        echo "Setting up test data...\n";
        
        // Create zones with varied status
        for ($i = 0; $i < 6; $i++) {
            $status = $i % 2 === 0 ? 'active' : 'inactive';
            $result = $this->locationService->createZone([
                'name' => 'PaginationTestZone_' . $this->generateRandomString(8),
                'status' => $status
            ]);
            if ($result['success']) {
                $this->createdZones[] = $result['data'];
            }
        }
        
        // Create countries with varied status
        for ($i = 0; $i < 6; $i++) {
            $status = $i % 2 === 0 ? 'active' : 'inactive';
            $result = $this->locationService->createCountry([
                'name' => 'PaginationTestCountry_' . $this->generateRandomString(8),
                'status' => $status
            ]);
            if ($result['success']) {
                $this->createdCountries[] = $result['data'];
            }
        }
        
        // Create states with varied country and zone assignments
        foreach ($this->createdCountries as $country) {
            for ($i = 0; $i < 3; $i++) {
                $status = $i % 2 === 0 ? 'active' : 'inactive';
                $zone = !empty($this->createdZones) ? $this->createdZones[$i % count($this->createdZones)] : null;
                $result = $this->locationService->createState([
                    'name' => 'PaginationTestState_' . $this->generateRandomString(8),
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
                    'name' => 'PaginationTestCity_' . $this->generateRandomString(8),
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
        for ($i = 0; $i < 15; $i++) {
            $status = $i % 2 === 0 ? 1 : 0;
            $result = $this->bankService->create([
                'name' => 'PaginationTestBank_' . $this->generateRandomString(8),
                'status' => $status
            ]);
            if ($result['success']) {
                $this->createdBanks[] = $result['data'];
            }
        }
        
        // Create customers with varied status
        for ($i = 0; $i < 15; $i++) {
            $status = $i % 2 === 0 ? 1 : 0;
            $result = $this->customerService->create([
                'name' => 'PaginationTestCustomer_' . $this->generateRandomString(8),
                'email' => 'paginationtest_' . $this->generateRandomEmail(),
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
     * Test: Bank pagination preserves status filter across pages
     */
    private function testBankPaginationPreservesStatusFilter() {
        $status = $this->generateRandomChoice([0, 1]);
        $limit = 5;
        
        // Get first page with filter
        $page1 = $this->bankService->getAll(['status' => $status, 'page' => 1, 'limit' => $limit]);
        
        // Get second page with same filter
        $page2 = $this->bankService->getAll(['status' => $status, 'page' => 2, 'limit' => $limit]);
        
        // Verify all records on both pages match the filter
        foreach ($page1['data'] as $bank) {
            if ((int)$bank['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Page 1: Bank with status '{$bank['status']}' returned when filtering for '$status'",
                    'data' => ['bank' => $bank, 'expected_status' => $status, 'page' => 1]
                ];
            }
        }
        
        foreach ($page2['data'] as $bank) {
            if ((int)$bank['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Page 2: Bank with status '{$bank['status']}' returned when filtering for '$status'",
                    'data' => ['bank' => $bank, 'expected_status' => $status, 'page' => 2]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Bank pagination preserves search filter across pages
     */
    private function testBankPaginationPreservesSearchFilter() {
        $searchTerm = 'PaginationTestBank';
        $limit = 5;
        
        // Get first page with search
        $page1 = $this->bankService->getAll(['search' => $searchTerm, 'page' => 1, 'limit' => $limit]);
        
        // Get second page with same search
        $page2 = $this->bankService->getAll(['search' => $searchTerm, 'page' => 2, 'limit' => $limit]);
        
        // Verify all records on both pages match the search
        foreach ($page1['data'] as $bank) {
            if (stripos($bank['name'], $searchTerm) === false) {
                return [
                    'success' => false,
                    'message' => "Page 1: Bank '{$bank['name']}' does not contain search term '$searchTerm'",
                    'data' => ['bank' => $bank, 'search_term' => $searchTerm, 'page' => 1]
                ];
            }
        }
        
        foreach ($page2['data'] as $bank) {
            if (stripos($bank['name'], $searchTerm) === false) {
                return [
                    'success' => false,
                    'message' => "Page 2: Bank '{$bank['name']}' does not contain search term '$searchTerm'",
                    'data' => ['bank' => $bank, 'search_term' => $searchTerm, 'page' => 2]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Customer pagination preserves status filter across pages
     */
    private function testCustomerPaginationPreservesStatusFilter() {
        $status = $this->generateRandomChoice([0, 1]);
        $limit = 5;
        
        $page1 = $this->customerService->getAll(['status' => $status, 'page' => 1, 'limit' => $limit]);
        $page2 = $this->customerService->getAll(['status' => $status, 'page' => 2, 'limit' => $limit]);
        
        foreach ($page1['data'] as $customer) {
            if ((int)$customer['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Page 1: Customer with status '{$customer['status']}' returned when filtering for '$status'",
                    'data' => ['customer' => $customer, 'expected_status' => $status, 'page' => 1]
                ];
            }
        }
        
        foreach ($page2['data'] as $customer) {
            if ((int)$customer['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Page 2: Customer with status '{$customer['status']}' returned when filtering for '$status'",
                    'data' => ['customer' => $customer, 'expected_status' => $status, 'page' => 2]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Country pagination preserves status filter across pages
     */
    private function testCountryPaginationPreservesStatusFilter() {
        $status = $this->generateRandomChoice(['active', 'inactive']);
        $limit = 3;
        
        $page1 = $this->locationService->getAllCountries(['status' => $status, 'page' => 1, 'limit' => $limit]);
        $page2 = $this->locationService->getAllCountries(['status' => $status, 'page' => 2, 'limit' => $limit]);
        
        foreach ($page1['data'] as $country) {
            if ($country['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Page 1: Country with status '{$country['status']}' returned when filtering for '$status'",
                    'data' => ['country' => $country, 'expected_status' => $status, 'page' => 1]
                ];
            }
        }
        
        foreach ($page2['data'] as $country) {
            if ($country['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Page 2: Country with status '{$country['status']}' returned when filtering for '$status'",
                    'data' => ['country' => $country, 'expected_status' => $status, 'page' => 2]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: State pagination preserves country filter across pages
     */
    private function testStatePaginationPreservesCountryFilter() {
        if (empty($this->createdCountries)) {
            return ['success' => true];
        }
        
        $country = $this->generateRandomChoice($this->createdCountries);
        $limit = 2;
        
        $page1 = $this->locationService->getAllStates(['country_id' => $country['id'], 'page' => 1, 'limit' => $limit]);
        $page2 = $this->locationService->getAllStates(['country_id' => $country['id'], 'page' => 2, 'limit' => $limit]);
        
        foreach ($page1['data'] as $state) {
            if ((int)$state['country_id'] !== (int)$country['id']) {
                return [
                    'success' => false,
                    'message' => "Page 1: State with country_id '{$state['country_id']}' returned when filtering for country '{$country['id']}'",
                    'data' => ['state' => $state, 'expected_country_id' => $country['id'], 'page' => 1]
                ];
            }
        }
        
        foreach ($page2['data'] as $state) {
            if ((int)$state['country_id'] !== (int)$country['id']) {
                return [
                    'success' => false,
                    'message' => "Page 2: State with country_id '{$state['country_id']}' returned when filtering for country '{$country['id']}'",
                    'data' => ['state' => $state, 'expected_country_id' => $country['id'], 'page' => 2]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: State pagination preserves zone filter across pages
     */
    private function testStatePaginationPreservesZoneFilter() {
        if (empty($this->createdZones)) {
            return ['success' => true];
        }
        
        $zone = $this->generateRandomChoice($this->createdZones);
        $limit = 2;
        
        $page1 = $this->locationService->getAllStates(['zone_id' => $zone['id'], 'page' => 1, 'limit' => $limit]);
        $page2 = $this->locationService->getAllStates(['zone_id' => $zone['id'], 'page' => 2, 'limit' => $limit]);
        
        foreach ($page1['data'] as $state) {
            if ($state['zone_id'] !== null && (int)$state['zone_id'] !== (int)$zone['id']) {
                return [
                    'success' => false,
                    'message' => "Page 1: State with zone_id '{$state['zone_id']}' returned when filtering for zone '{$zone['id']}'",
                    'data' => ['state' => $state, 'expected_zone_id' => $zone['id'], 'page' => 1]
                ];
            }
        }
        
        foreach ($page2['data'] as $state) {
            if ($state['zone_id'] !== null && (int)$state['zone_id'] !== (int)$zone['id']) {
                return [
                    'success' => false,
                    'message' => "Page 2: State with zone_id '{$state['zone_id']}' returned when filtering for zone '{$zone['id']}'",
                    'data' => ['state' => $state, 'expected_zone_id' => $zone['id'], 'page' => 2]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: City pagination preserves state filter across pages
     */
    private function testCityPaginationPreservesStateFilter() {
        if (empty($this->createdStates)) {
            return ['success' => true];
        }
        
        $state = $this->generateRandomChoice($this->createdStates);
        $limit = 2;
        
        $page1 = $this->locationService->getAllCities(['state_id' => $state['id'], 'page' => 1, 'limit' => $limit]);
        $page2 = $this->locationService->getAllCities(['state_id' => $state['id'], 'page' => 2, 'limit' => $limit]);
        
        foreach ($page1['data'] as $city) {
            if ((int)$city['state_id'] !== (int)$state['id']) {
                return [
                    'success' => false,
                    'message' => "Page 1: City with state_id '{$city['state_id']}' returned when filtering for state '{$state['id']}'",
                    'data' => ['city' => $city, 'expected_state_id' => $state['id'], 'page' => 1]
                ];
            }
        }
        
        foreach ($page2['data'] as $city) {
            if ((int)$city['state_id'] !== (int)$state['id']) {
                return [
                    'success' => false,
                    'message' => "Page 2: City with state_id '{$city['state_id']}' returned when filtering for state '{$state['id']}'",
                    'data' => ['city' => $city, 'expected_state_id' => $state['id'], 'page' => 2]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Zone pagination preserves status filter across pages
     */
    private function testZonePaginationPreservesStatusFilter() {
        $status = $this->generateRandomChoice(['active', 'inactive']);
        $limit = 3;
        
        $page1 = $this->locationService->getAllZones(['status' => $status, 'page' => 1, 'limit' => $limit]);
        $page2 = $this->locationService->getAllZones(['status' => $status, 'page' => 2, 'limit' => $limit]);
        
        foreach ($page1['data'] as $zone) {
            if ($zone['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Page 1: Zone with status '{$zone['status']}' returned when filtering for '$status'",
                    'data' => ['zone' => $zone, 'expected_status' => $status, 'page' => 1]
                ];
            }
        }
        
        foreach ($page2['data'] as $zone) {
            if ($zone['status'] !== $status) {
                return [
                    'success' => false,
                    'message' => "Page 2: Zone with status '{$zone['status']}' returned when filtering for '$status'",
                    'data' => ['zone' => $zone, 'expected_status' => $status, 'page' => 2]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Pagination returns consistent total across pages
     */
    private function testPaginationConsistentTotal() {
        $status = $this->generateRandomChoice([0, 1]);
        $limit = 5;
        
        $page1 = $this->bankService->getAll(['status' => $status, 'page' => 1, 'limit' => $limit]);
        $page2 = $this->bankService->getAll(['status' => $status, 'page' => 2, 'limit' => $limit]);
        
        if ($page1['total'] !== $page2['total']) {
            return [
                'success' => false,
                'message' => "Total count changed between pages: page 1 = {$page1['total']}, page 2 = {$page2['total']}",
                'data' => ['page1_total' => $page1['total'], 'page2_total' => $page2['total']]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Pagination returns correct page number
     */
    private function testPaginationReturnsCorrectPage() {
        $requestedPage = $this->generateRandomInt(1, 3);
        $limit = 5;
        
        $result = $this->bankService->getAll(['page' => $requestedPage, 'limit' => $limit]);
        
        if ((int)$result['page'] !== $requestedPage) {
            return [
                'success' => false,
                'message' => "Requested page $requestedPage but got page {$result['page']}",
                'data' => ['requested_page' => $requestedPage, 'returned_page' => $result['page']]
            ];
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
    $test = new PaginationStatePreservationTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}

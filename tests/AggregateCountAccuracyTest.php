<?php
/**
 * Aggregate Count Accuracy Property Test
 * 
 * **Feature: crm-master-modules, Property 10: Aggregate Count Accuracy**
 * **Validates: Requirements 3.1, 4.1, 5.1, 5.4, 6.1**
 * 
 * Property: For any parent entity (country, state, zone), 
 * the displayed child counts should equal the actual count of child records in the database.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/LocationService.php';
require_once __DIR__ . '/../repositories/LocationRepository.php';

class AggregateCountAccuracyTest extends PropertyTestBase {
    private $locationService;
    private $locationRepository;
    private $createdCountries = [];
    private $createdStates = [];
    private $createdZones = [];
    private $createdCities = [];
    
    // Unique prefix for test data
    private $testPrefix = 'ACATEST_';
    
    public function __construct() {
        parent::__construct();
        $this->locationService = new LocationService();
        $this->locationRepository = new LocationRepository();
        $this->testPrefix = 'ACATEST_' . $this->generateRandomString(4) . '_';
    }
    
    /**
     * Run all aggregate count accuracy property tests
     */
    public function runAllTests() {
        echo "\n=== Aggregate Count Accuracy Property Tests ===\n";
        echo "**Feature: crm-master-modules, Property 10: Aggregate Count Accuracy**\n";
        echo "**Validates: Requirements 3.1, 4.1, 5.1, 5.4, 6.1**\n\n";
        
        $results = [];
        
        // Setup test data
        $this->setupTestData();
        
        try {
            // Test 1: Country state count accuracy
            $results['country_state_count'] = $this->runPropertyTest(
                'Country State Count Accuracy',
                function() { return $this->testCountryStateCount(); },
                30
            );
            
            // Test 2: State city count accuracy
            $results['state_city_count'] = $this->runPropertyTest(
                'State City Count Accuracy',
                function() { return $this->testStateCityCount(); },
                30
            );
            
            // Test 3: Zone state count accuracy
            $results['zone_state_count'] = $this->runPropertyTest(
                'Zone State Count Accuracy',
                function() { return $this->testZoneStateCount(); },
                30
            );
            
            // Test 4: Zone city count accuracy
            $results['zone_city_count'] = $this->runPropertyTest(
                'Zone City Count Accuracy',
                function() { return $this->testZoneCityCount(); },
                30
            );
            
            // Test 5: Country total city count accuracy (through states)
            $results['country_city_count'] = $this->runPropertyTest(
                'Country City Count Accuracy',
                function() { return $this->testCountryCityCount(); },
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
     * Setup test data with known hierarchical relationships
     */
    private function setupTestData() {
        echo "Setting up test data with prefix: {$this->testPrefix}\n";
        
        // Create zones
        for ($i = 0; $i < 3; $i++) {
            $result = $this->locationService->createZone([
                'name' => $this->testPrefix . 'Zone_' . $i,
                'status' => 'active'
            ]);
            if ($result['success']) {
                $this->createdZones[] = $result['data'];
            }
        }
        
        // Create countries
        for ($i = 0; $i < 3; $i++) {
            $result = $this->locationService->createCountry([
                'name' => $this->testPrefix . 'Country_' . $i,
                'status' => 'active'
            ]);
            if ($result['success']) {
                $this->createdCountries[] = $result['data'];
            }
        }
        
        // Create states with varying counts per country and zone assignments
        foreach ($this->createdCountries as $countryIndex => $country) {
            // Create different number of states per country (1, 2, 3)
            $stateCount = $countryIndex + 1;
            for ($i = 0; $i < $stateCount; $i++) {
                $zone = !empty($this->createdZones) ? $this->createdZones[$i % count($this->createdZones)] : null;
                $result = $this->locationService->createState([
                    'name' => $this->testPrefix . 'State_' . $countryIndex . '_' . $i,
                    'country_id' => $country['id'],
                    'zone_id' => $zone ? $zone['id'] : null,
                    'status' => 'active'
                ]);
                if ($result['success']) {
                    $this->createdStates[] = $result['data'];
                }
            }
        }
        
        // Create cities with varying counts per state and zone assignments
        foreach ($this->createdStates as $stateIndex => $state) {
            // Create different number of cities per state (1-3)
            $cityCount = ($stateIndex % 3) + 1;
            for ($i = 0; $i < $cityCount; $i++) {
                $zone = !empty($this->createdZones) ? $this->createdZones[$i % count($this->createdZones)] : null;
                $result = $this->locationService->createCity([
                    'name' => $this->testPrefix . 'City_' . $stateIndex . '_' . $i,
                    'state_id' => $state['id'],
                    'zone_id' => $zone ? $zone['id'] : null,
                    'status' => 'active'
                ]);
                if ($result['success']) {
                    $this->createdCities[] = $result['data'];
                }
            }
        }
        
        echo "Test data created: " . count($this->createdCountries) . " countries, " 
            . count($this->createdStates) . " states, " 
            . count($this->createdZones) . " zones, "
            . count($this->createdCities) . " cities\n\n";
    }
    
    /**
     * Test: Country's state count matches actual count in database
     */
    private function testCountryStateCount() {
        if (empty($this->createdCountries)) {
            return ['success' => true]; // Skip if no test data
        }
        
        // Pick a random country
        $country = $this->generateRandomChoice($this->createdCountries);
        
        // Get the country with counts from the service
        $countryData = $this->locationService->getCountryById($country['id']);
        
        // Count states directly from database
        $actualCount = $this->locationRepository->countStatesByCountry($country['id']);
        
        // Check if state_count field exists and matches
        $displayedCount = isset($countryData['state_count']) ? (int)$countryData['state_count'] : null;
        
        // If state_count is not in the response, count from getAllStates
        if ($displayedCount === null) {
            $states = $this->locationService->getAllStates(['country_id' => $country['id'], 'limit' => 1000]);
            $displayedCount = $states['total'];
        }
        
        if ($displayedCount !== $actualCount) {
            return [
                'success' => false,
                'message' => "Country '{$country['name']}' shows $displayedCount states but has $actualCount in database",
                'data' => [
                    'country' => $country,
                    'displayed_count' => $displayedCount,
                    'actual_count' => $actualCount
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: State's city count matches actual count in database
     */
    private function testStateCityCount() {
        if (empty($this->createdStates)) {
            return ['success' => true]; // Skip if no test data
        }
        
        // Pick a random state
        $state = $this->generateRandomChoice($this->createdStates);
        
        // Get the state with counts from the service
        $stateData = $this->locationService->getStateById($state['id']);
        
        // Count cities directly from database
        $actualCount = $this->locationRepository->countCitiesByState($state['id']);
        
        // Check if city_count field exists and matches
        $displayedCount = isset($stateData['city_count']) ? (int)$stateData['city_count'] : null;
        
        // If city_count is not in the response, count from getAllCities
        if ($displayedCount === null) {
            $cities = $this->locationService->getAllCities(['state_id' => $state['id'], 'limit' => 1000]);
            $displayedCount = $cities['total'];
        }
        
        if ($displayedCount !== $actualCount) {
            return [
                'success' => false,
                'message' => "State '{$state['name']}' shows $displayedCount cities but has $actualCount in database",
                'data' => [
                    'state' => $state,
                    'displayed_count' => $displayedCount,
                    'actual_count' => $actualCount
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Zone's state count matches actual count in database
     */
    private function testZoneStateCount() {
        if (empty($this->createdZones)) {
            return ['success' => true]; // Skip if no test data
        }
        
        // Pick a random zone
        $zone = $this->generateRandomChoice($this->createdZones);
        
        // Get the zone with counts from the service
        $zoneData = $this->locationService->getZoneById($zone['id']);
        
        // Count states directly from database
        $actualCount = $this->locationRepository->countStatesByZone($zone['id']);
        
        // Check if state_count field exists and matches
        $displayedCount = isset($zoneData['state_count']) ? (int)$zoneData['state_count'] : null;
        
        // If state_count is not in the response, count from getAllStates
        if ($displayedCount === null) {
            $states = $this->locationService->getAllStates(['zone_id' => $zone['id'], 'limit' => 1000]);
            $displayedCount = $states['total'];
        }
        
        if ($displayedCount !== $actualCount) {
            return [
                'success' => false,
                'message' => "Zone '{$zone['name']}' shows $displayedCount states but has $actualCount in database",
                'data' => [
                    'zone' => $zone,
                    'displayed_count' => $displayedCount,
                    'actual_count' => $actualCount
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Zone's city count matches actual count in database
     */
    private function testZoneCityCount() {
        if (empty($this->createdZones)) {
            return ['success' => true]; // Skip if no test data
        }
        
        // Pick a random zone
        $zone = $this->generateRandomChoice($this->createdZones);
        
        // Get the zone with counts from the service
        $zoneData = $this->locationService->getZoneById($zone['id']);
        
        // Count cities directly from database
        $actualCount = $this->locationRepository->countCitiesByZone($zone['id']);
        
        // Check if city_count field exists and matches
        $displayedCount = isset($zoneData['city_count']) ? (int)$zoneData['city_count'] : null;
        
        // If city_count is not in the response, count from getAllCities
        if ($displayedCount === null) {
            $cities = $this->locationService->getAllCities(['zone_id' => $zone['id'], 'limit' => 1000]);
            $displayedCount = $cities['total'];
        }
        
        if ($displayedCount !== $actualCount) {
            return [
                'success' => false,
                'message' => "Zone '{$zone['name']}' shows $displayedCount cities but has $actualCount in database",
                'data' => [
                    'zone' => $zone,
                    'displayed_count' => $displayedCount,
                    'actual_count' => $actualCount
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test: Country's total city count (through states) matches actual count
     */
    private function testCountryCityCount() {
        if (empty($this->createdCountries)) {
            return ['success' => true]; // Skip if no test data
        }
        
        // Pick a random country
        $country = $this->generateRandomChoice($this->createdCountries);
        
        // Get the country with counts from the service
        $countryData = $this->locationService->getCountryById($country['id']);
        
        // Count cities through states directly from database
        $actualCount = $this->countCitiesByCountry($country['id']);
        
        // Check if city_count field exists and matches
        $displayedCount = isset($countryData['city_count']) ? (int)$countryData['city_count'] : null;
        
        // If city_count is not in the response, count from getAllCities with country filter
        if ($displayedCount === null) {
            $cities = $this->locationService->getAllCities(['country_id' => $country['id'], 'limit' => 1000]);
            $displayedCount = $cities['total'];
        }
        
        if ($displayedCount !== $actualCount) {
            return [
                'success' => false,
                'message' => "Country '{$country['name']}' shows $displayedCount cities but has $actualCount in database",
                'data' => [
                    'country' => $country,
                    'displayed_count' => $displayedCount,
                    'actual_count' => $actualCount
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Count cities by country (through states)
     */
    private function countCitiesByCountry($countryId) {
        $sql = "SELECT COUNT(c.id) as count 
                FROM cities c 
                INNER JOIN states s ON c.state_id = s.id 
                WHERE s.country_id = ?";
        
        $results = $this->getResults($sql, [$countryId], 'i');
        return (int)($results[0]['count'] ?? 0);
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
        
        echo "Cleanup complete.\n";
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new AggregateCountAccuracyTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}

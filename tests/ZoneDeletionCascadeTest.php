<?php
/**
 * Property Test for Zone Deletion Cascade
 * **Feature: crm-master-modules, Property 6: Zone Deletion Cascade**
 * **Validates: Requirements 5.5**
 * 
 * For any zone that is deleted, all states and cities referencing that zone 
 * should have their zone_id set to NULL while preserving the state and city records.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/LocationService.php';

class ZoneDeletionCascadeTest extends PropertyTestBase {
    
    private $locationService;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->locationService = new LocationService();
    }
    
    public function runTests() {
        echo "=== Zone Deletion Cascade Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test zone deletion cascades to states
        $allPassed &= $this->runPropertyTest(
            "Zone Deletion Cascades to States",
            [$this, 'testZoneDeletionCascadesToStates']
        );
        
        // Test zone deletion cascades to cities
        $allPassed &= $this->runPropertyTest(
            "Zone Deletion Cascades to Cities",
            [$this, 'testZoneDeletionCascadesToCities']
        );
        
        // Test zone deletion preserves state records
        $allPassed &= $this->runPropertyTest(
            "Zone Deletion Preserves State Records",
            [$this, 'testZoneDeletionPreservesStateRecords']
        );
        
        // Test zone deletion preserves city records
        $allPassed &= $this->runPropertyTest(
            "Zone Deletion Preserves City Records",
            [$this, 'testZoneDeletionPreservesCityRecords']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 6: Zone deletion cascades to states
     * When a zone is deleted, states referencing it should have zone_id set to NULL
     */
    public function testZoneDeletionCascadesToStates() {
        try {
            // Create a zone
            $zoneName = 'Test Zone ' . $this->generateRandomString(15);
            $zoneResult = $this->locationService->createZone(['name' => $zoneName]);
            $this->assert($zoneResult['success'], "Zone creation should succeed");
            $zoneId = $zoneResult['data']['id'];
            // Don't add to createdRecords since we're deleting it
            
            // Create a country
            $countryName = 'Test Country ' . $this->generateRandomString(15);
            $countryResult = $this->locationService->createCountry(['name' => $countryName]);
            $this->assert($countryResult['success'], "Country creation should succeed");
            $countryId = $countryResult['data']['id'];
            $this->createdRecords['countries'][] = $countryId;
            
            // Create a state with the zone
            $stateName = 'Test State ' . $this->generateRandomString(15);
            $stateResult = $this->locationService->createState([
                'name' => $stateName,
                'country_id' => $countryId,
                'zone_id' => $zoneId
            ]);
            $this->assert($stateResult['success'], "State creation should succeed");
            $stateId = $stateResult['data']['id'];
            $this->createdRecords['states'][] = $stateId;
            
            // Verify state has zone_id set
            $stateBefore = $this->locationService->getStateById($stateId);
            $this->assert(
                (int)$stateBefore['zone_id'] === $zoneId,
                "State should have zone_id set before zone deletion"
            );
            
            // Delete the zone
            $deleteResult = $this->locationService->deleteZone($zoneId);
            $this->assert($deleteResult['success'], "Zone deletion should succeed");
            
            // Verify state's zone_id is now NULL
            $stateAfter = $this->locationService->getStateById($stateId);
            $this->assert(
                $stateAfter['zone_id'] === null || $stateAfter['zone_id'] === '',
                "State's zone_id should be NULL after zone deletion, got: " . var_export($stateAfter['zone_id'], true)
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['zoneId' => $zoneId ?? null, 'stateId' => $stateId ?? null]
            ];
        }
    }
    
    /**
     * Property 6: Zone deletion cascades to cities
     * When a zone is deleted, cities referencing it should have zone_id set to NULL
     */
    public function testZoneDeletionCascadesToCities() {
        try {
            // Create a zone
            $zoneName = 'Test Zone ' . $this->generateRandomString(15);
            $zoneResult = $this->locationService->createZone(['name' => $zoneName]);
            $this->assert($zoneResult['success'], "Zone creation should succeed");
            $zoneId = $zoneResult['data']['id'];
            // Don't add to createdRecords since we're deleting it
            
            // Create a country
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
            
            // Create a city with the zone
            $cityName = 'Test City ' . $this->generateRandomString(15);
            $cityResult = $this->locationService->createCity([
                'name' => $cityName,
                'state_id' => $stateId,
                'zone_id' => $zoneId
            ]);
            $this->assert($cityResult['success'], "City creation should succeed");
            $cityId = $cityResult['data']['id'];
            $this->createdRecords['cities'][] = $cityId;
            
            // Verify city has zone_id set
            $cityBefore = $this->locationService->getCityById($cityId);
            $this->assert(
                (int)$cityBefore['zone_id'] === $zoneId,
                "City should have zone_id set before zone deletion"
            );
            
            // Delete the zone
            $deleteResult = $this->locationService->deleteZone($zoneId);
            $this->assert($deleteResult['success'], "Zone deletion should succeed");
            
            // Verify city's zone_id is now NULL
            $cityAfter = $this->locationService->getCityById($cityId);
            $this->assert(
                $cityAfter['zone_id'] === null || $cityAfter['zone_id'] === '',
                "City's zone_id should be NULL after zone deletion, got: " . var_export($cityAfter['zone_id'], true)
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['zoneId' => $zoneId ?? null, 'cityId' => $cityId ?? null]
            ];
        }
    }
    
    /**
     * Property 6: Zone deletion preserves state records
     * When a zone is deleted, states should still exist (only zone_id is nullified)
     */
    public function testZoneDeletionPreservesStateRecords() {
        try {
            // Create a zone
            $zoneName = 'Test Zone ' . $this->generateRandomString(15);
            $zoneResult = $this->locationService->createZone(['name' => $zoneName]);
            $this->assert($zoneResult['success'], "Zone creation should succeed");
            $zoneId = $zoneResult['data']['id'];
            
            // Create a country
            $countryName = 'Test Country ' . $this->generateRandomString(15);
            $countryResult = $this->locationService->createCountry(['name' => $countryName]);
            $this->assert($countryResult['success'], "Country creation should succeed");
            $countryId = $countryResult['data']['id'];
            $this->createdRecords['countries'][] = $countryId;
            
            // Create multiple states with the zone
            $stateIds = [];
            $stateNames = [];
            $numStates = $this->generateRandomInt(2, 5);
            
            for ($i = 0; $i < $numStates; $i++) {
                $stateName = 'Test State ' . $this->generateRandomString(15);
                $stateNames[] = $stateName;
                $stateResult = $this->locationService->createState([
                    'name' => $stateName,
                    'country_id' => $countryId,
                    'zone_id' => $zoneId
                ]);
                $this->assert($stateResult['success'], "State creation should succeed");
                $stateIds[] = $stateResult['data']['id'];
                $this->createdRecords['states'][] = $stateResult['data']['id'];
            }
            
            // Delete the zone
            $deleteResult = $this->locationService->deleteZone($zoneId);
            $this->assert($deleteResult['success'], "Zone deletion should succeed");
            
            // Verify all states still exist with their original data (except zone_id)
            for ($i = 0; $i < $numStates; $i++) {
                $state = $this->locationService->getStateById($stateIds[$i]);
                $this->assert($state !== null, "State should still exist after zone deletion");
                $this->assert(
                    $state['name'] === $stateNames[$i],
                    "State name should be preserved after zone deletion"
                );
                $this->assert(
                    (int)$state['country_id'] === $countryId,
                    "State country_id should be preserved after zone deletion"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['zoneId' => $zoneId ?? null, 'stateIds' => $stateIds ?? null]
            ];
        }
    }
    
    /**
     * Property 6: Zone deletion preserves city records
     * When a zone is deleted, cities should still exist (only zone_id is nullified)
     */
    public function testZoneDeletionPreservesCityRecords() {
        try {
            // Create a zone
            $zoneName = 'Test Zone ' . $this->generateRandomString(15);
            $zoneResult = $this->locationService->createZone(['name' => $zoneName]);
            $this->assert($zoneResult['success'], "Zone creation should succeed");
            $zoneId = $zoneResult['data']['id'];
            
            // Create a country
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
            
            // Create multiple cities with the zone
            $cityIds = [];
            $cityNames = [];
            $numCities = $this->generateRandomInt(2, 5);
            
            for ($i = 0; $i < $numCities; $i++) {
                $cityName = 'Test City ' . $this->generateRandomString(15);
                $cityNames[] = $cityName;
                $cityResult = $this->locationService->createCity([
                    'name' => $cityName,
                    'state_id' => $stateId,
                    'zone_id' => $zoneId
                ]);
                $this->assert($cityResult['success'], "City creation should succeed");
                $cityIds[] = $cityResult['data']['id'];
                $this->createdRecords['cities'][] = $cityResult['data']['id'];
            }
            
            // Delete the zone
            $deleteResult = $this->locationService->deleteZone($zoneId);
            $this->assert($deleteResult['success'], "Zone deletion should succeed");
            
            // Verify all cities still exist with their original data (except zone_id)
            for ($i = 0; $i < $numCities; $i++) {
                $city = $this->locationService->getCityById($cityIds[$i]);
                $this->assert($city !== null, "City should still exist after zone deletion");
                $this->assert(
                    $city['name'] === $cityNames[$i],
                    "City name should be preserved after zone deletion"
                );
                $this->assert(
                    (int)$city['state_id'] === $stateId,
                    "City state_id should be preserved after zone deletion"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['zoneId' => $zoneId ?? null, 'cityIds' => $cityIds ?? null]
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

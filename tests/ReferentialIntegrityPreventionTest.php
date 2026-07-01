<?php
/**
 * Property Test for Referential Integrity Prevention
 * **Feature: crm-master-modules, Property 5: Referential Integrity Prevention**
 * **Validates: Requirements 3.4, 4.4, 9.3**
 * 
 * For any parent record (country with states, state with cities) that has child dependencies,
 * deletion should be prevented and return an error listing the dependencies.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/LocationService.php';

class ReferentialIntegrityPreventionTest extends PropertyTestBase {
    
    private $locationService;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->locationService = new LocationService();
    }
    
    public function runTests() {
        echo "=== Referential Integrity Prevention Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test country deletion prevention when states exist
        $allPassed &= $this->runPropertyTest(
            "Country Deletion Prevention With States",
            [$this, 'testCountryDeletionPreventionWithStates']
        );
        
        // Test state deletion prevention when cities exist
        $allPassed &= $this->runPropertyTest(
            "State Deletion Prevention With Cities",
            [$this, 'testStateDeletionPreventionWithCities']
        );
        
        // Test country deletion allowed when no states
        $allPassed &= $this->runPropertyTest(
            "Country Deletion Allowed Without States",
            [$this, 'testCountryDeletionAllowedWithoutStates']
        );
        
        // Test state deletion allowed when no cities
        $allPassed &= $this->runPropertyTest(
            "State Deletion Allowed Without Cities",
            [$this, 'testStateDeletionAllowedWithoutCities']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 5: Country deletion prevention with states
     * Deleting a country that has states should fail with referential integrity error
     */
    public function testCountryDeletionPreventionWithStates() {
        try {
            // Create a country
            $countryName = 'Test Country ' . $this->generateRandomString(15);
            $countryResult = $this->locationService->createCountry(['name' => $countryName]);
            $this->assert($countryResult['success'], "Country creation should succeed");
            $countryId = $countryResult['data']['id'];
            $this->createdRecords['countries'][] = $countryId;
            
            // Create a state in that country
            $stateName = 'Test State ' . $this->generateRandomString(15);
            $stateResult = $this->locationService->createState([
                'name' => $stateName,
                'country_id' => $countryId
            ]);
            $this->assert($stateResult['success'], "State creation should succeed");
            $stateId = $stateResult['data']['id'];
            $this->createdRecords['states'][] = $stateId;
            
            // Try to delete the country
            $deleteResult = $this->locationService->deleteCountry($countryId);
            
            // Deletion should fail
            $this->assert(!$deleteResult['success'], "Country deletion should fail when states exist");
            $this->assert(
                $deleteResult['code'] === 'REFERENTIAL_INTEGRITY_ERROR',
                "Error code should be REFERENTIAL_INTEGRITY_ERROR, got: " . ($deleteResult['code'] ?? 'none')
            );
            $this->assert(
                isset($deleteResult['dependencies']['states']),
                "Response should include state dependency count"
            );
            $this->assert(
                $deleteResult['dependencies']['states'] >= 1,
                "State dependency count should be at least 1"
            );
            
            // Verify country still exists
            $country = $this->locationService->getCountryById($countryId);
            $this->assert($country !== null, "Country should still exist after failed deletion");
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['countryId' => $countryId ?? null, 'stateId' => $stateId ?? null]
            ];
        }
    }
    
    /**
     * Property 5: State deletion prevention with cities
     * Deleting a state that has cities should fail with referential integrity error
     */
    public function testStateDeletionPreventionWithCities() {
        try {
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
            
            // Create a city in that state
            $cityName = 'Test City ' . $this->generateRandomString(15);
            $cityResult = $this->locationService->createCity([
                'name' => $cityName,
                'state_id' => $stateId
            ]);
            $this->assert($cityResult['success'], "City creation should succeed");
            $cityId = $cityResult['data']['id'];
            $this->createdRecords['cities'][] = $cityId;
            
            // Try to delete the state
            $deleteResult = $this->locationService->deleteState($stateId);
            
            // Deletion should fail
            $this->assert(!$deleteResult['success'], "State deletion should fail when cities exist");
            $this->assert(
                $deleteResult['code'] === 'REFERENTIAL_INTEGRITY_ERROR',
                "Error code should be REFERENTIAL_INTEGRITY_ERROR, got: " . ($deleteResult['code'] ?? 'none')
            );
            $this->assert(
                isset($deleteResult['dependencies']['cities']),
                "Response should include city dependency count"
            );
            $this->assert(
                $deleteResult['dependencies']['cities'] >= 1,
                "City dependency count should be at least 1"
            );
            
            // Verify state still exists
            $state = $this->locationService->getStateById($stateId);
            $this->assert($state !== null, "State should still exist after failed deletion");
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['stateId' => $stateId ?? null, 'cityId' => $cityId ?? null]
            ];
        }
    }
    
    /**
     * Property 5: Country deletion allowed without states
     * Deleting a country that has no states should succeed
     */
    public function testCountryDeletionAllowedWithoutStates() {
        try {
            // Create a country
            $countryName = 'Test Country ' . $this->generateRandomString(15);
            $countryResult = $this->locationService->createCountry(['name' => $countryName]);
            $this->assert($countryResult['success'], "Country creation should succeed");
            $countryId = $countryResult['data']['id'];
            // Don't add to createdRecords since we're deleting it
            
            // Delete the country (no states exist)
            $deleteResult = $this->locationService->deleteCountry($countryId);
            
            // Deletion should succeed
            $this->assert($deleteResult['success'], "Country deletion should succeed when no states exist");
            
            // Verify country no longer exists
            $country = $this->locationService->getCountryById($countryId);
            $this->assert($country === null, "Country should not exist after deletion");
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['countryId' => $countryId ?? null]
            ];
        }
    }
    
    /**
     * Property 5: State deletion allowed without cities
     * Deleting a state that has no cities should succeed
     */
    public function testStateDeletionAllowedWithoutCities() {
        try {
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
            // Don't add to createdRecords since we're deleting it
            
            // Delete the state (no cities exist)
            $deleteResult = $this->locationService->deleteState($stateId);
            
            // Deletion should succeed
            $this->assert($deleteResult['success'], "State deletion should succeed when no cities exist");
            
            // Verify state no longer exists
            $state = $this->locationService->getStateById($stateId);
            $this->assert($state === null, "State should not exist after deletion");
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['stateId' => $stateId ?? null]
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

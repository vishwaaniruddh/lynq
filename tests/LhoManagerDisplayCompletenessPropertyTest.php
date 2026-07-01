<?php
/**
 * Property Test for Manager Display Completeness
 * **Feature: lho-manager-assignment, Property 3: Manager Display Completeness**
 * **Validates: Requirements 2.1, 2.2**
 * 
 * For any LHO with N assigned managers, the API response should include 
 * exactly N manager records with their names.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/LocationService.php';
require_once __DIR__ . '/../repositories/LhoManagerRepository.php';

class LhoManagerDisplayCompletenessPropertyTest extends PropertyTestBase {
    
    private $locationService;
    private $lhoManagerRepository;
    private $createdLhos = [];
    private $createdUsers = [];
    
    public function __construct() {
        parent::__construct();
        $this->locationService = new LocationService();
        $this->lhoManagerRepository = new LhoManagerRepository();
        $this->iterations = 100;
    }
    
    public function runTests() {
        echo "=== Manager Display Completeness Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test Manager Display Completeness
        $allPassed &= $this->runPropertyTest(
            "Manager Display Completeness",
            [$this, 'testManagerDisplayCompleteness']
        );
        
        // Cleanup test data
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 3: Manager Display Completeness
     * For any LHO with N assigned managers, the API response should include 
     * exactly N manager records with their names.
     * **Feature: lho-manager-assignment, Property 3: Manager Display Completeness**
     * **Validates: Requirements 2.1, 2.2**
     */
    public function testManagerDisplayCompleteness() {
        try {
            // Get all LHOs from the database
            $sql = "SELECT id FROM lhos";
            $lhos = $this->getResults($sql, [], '');
            
            if (empty($lhos)) {
                // No LHOs to test - this is valid
                return ['success' => true];
            }
            
            foreach ($lhos as $lhoRecord) {
                $lhoId = (int)$lhoRecord['id'];
                
                // Get the actual manager count from the database
                $countSql = "SELECT COUNT(*) as count FROM lho_managers WHERE lho_id = ?";
                $countResult = $this->getResults($countSql, [$lhoId], 'i');
                $actualManagerCount = (int)($countResult[0]['count'] ?? 0);
                
                // Get the LHO with managers via the service
                $lhoWithManagers = $this->locationService->getLhoWithManagers($lhoId);
                
                $this->assert(
                    $lhoWithManagers !== null,
                    "LHO ID {$lhoId} should be retrievable via getLhoWithManagers"
                );
                
                // Check that managers array exists
                $this->assert(
                    isset($lhoWithManagers['managers']),
                    "LHO ID {$lhoId} response should include 'managers' array"
                );
                
                // Check that manager count matches
                $returnedManagerCount = count($lhoWithManagers['managers']);
                $this->assert(
                    $returnedManagerCount === $actualManagerCount,
                    "LHO ID {$lhoId}: Expected {$actualManagerCount} managers, got {$returnedManagerCount}"
                );
                
                // Check that manager_names array exists and has correct count
                $this->assert(
                    isset($lhoWithManagers['manager_names']),
                    "LHO ID {$lhoId} response should include 'manager_names' array"
                );
                
                $this->assert(
                    count($lhoWithManagers['manager_names']) === $actualManagerCount,
                    "LHO ID {$lhoId}: manager_names count should match actual manager count"
                );
                
                // Check that manager_ids array exists and has correct count
                $this->assert(
                    isset($lhoWithManagers['manager_ids']),
                    "LHO ID {$lhoId} response should include 'manager_ids' array"
                );
                
                $this->assert(
                    count($lhoWithManagers['manager_ids']) === $actualManagerCount,
                    "LHO ID {$lhoId}: manager_ids count should match actual manager count"
                );
                
                // Verify each manager has a name
                foreach ($lhoWithManagers['managers'] as $manager) {
                    $this->assert(
                        isset($manager['manager_name']) && !empty($manager['manager_name']),
                        "LHO ID {$lhoId}: Each manager should have a non-empty manager_name"
                    );
                    
                    $this->assert(
                        isset($manager['user_id']) && (int)$manager['user_id'] > 0,
                        "LHO ID {$lhoId}: Each manager should have a valid user_id"
                    );
                }
            }
            
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
        // No test data created in this test - it uses existing data
    }
}

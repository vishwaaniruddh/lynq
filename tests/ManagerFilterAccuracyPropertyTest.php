<?php
/**
 * Property Test for Manager Filter Accuracy
 * **Feature: lho-manager-assignment, Property 9: Manager Filter Accuracy**
 * **Validates: Requirements 5.2**
 * 
 * For any manager filter applied to the LHO list, all returned LHOs should have 
 * that manager assigned, and no LHOs without that manager should be returned.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/LocationService.php';
require_once __DIR__ . '/../repositories/LhoManagerRepository.php';

class ManagerFilterAccuracyPropertyTest extends PropertyTestBase {
    
    private $locationService;
    private $lhoManagerRepository;
    
    public function __construct() {
        parent::__construct();
        $this->locationService = new LocationService();
        $this->lhoManagerRepository = new LhoManagerRepository();
        $this->iterations = 100;
    }
    
    public function runTests() {
        echo "=== Manager Filter Accuracy Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test Manager Filter Accuracy
        $allPassed &= $this->runPropertyTest(
            "Manager Filter Accuracy",
            [$this, 'testManagerFilterAccuracy']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 9: Manager Filter Accuracy
     * For any manager filter applied to the LHO list, all returned LHOs should have 
     * that manager assigned, and no LHOs without that manager should be returned.
     * **Feature: lho-manager-assignment, Property 9: Manager Filter Accuracy**
     * **Validates: Requirements 5.2**
     */
    public function testManagerFilterAccuracy() {
        try {
            // Get all users who are managers of at least one LHO
            $sql = "SELECT DISTINCT user_id FROM lho_managers";
            $managers = $this->getResults($sql, [], '');
            
            if (empty($managers)) {
                // No managers to test - this is valid
                return ['success' => true];
            }
            
            foreach ($managers as $managerRecord) {
                $managerId = (int)$managerRecord['user_id'];
                
                // Get the expected LHO IDs for this manager from the database
                $expectedSql = "SELECT lho_id FROM lho_managers WHERE user_id = ?";
                $expectedResults = $this->getResults($expectedSql, [$managerId], 'i');
                $expectedLhoIds = array_map('intval', array_column($expectedResults, 'lho_id'));
                
                // Get LHOs filtered by this manager via the service
                $filters = ['manager_id' => $managerId];
                $result = $this->locationService->getAllLhosWithManagers($filters);
                
                $returnedLhoIds = array_map('intval', array_column($result['data'], 'id'));
                
                // Check that all returned LHOs have this manager assigned
                foreach ($returnedLhoIds as $lhoId) {
                    $this->assert(
                        in_array($lhoId, $expectedLhoIds),
                        "Manager ID {$managerId}: LHO ID {$lhoId} was returned but should not have this manager"
                    );
                    
                    // Also verify via the manager_ids in the response
                    $lhoData = null;
                    foreach ($result['data'] as $lho) {
                        if ((int)$lho['id'] === $lhoId) {
                            $lhoData = $lho;
                            break;
                        }
                    }
                    
                    $this->assert(
                        $lhoData !== null && isset($lhoData['manager_ids']),
                        "Manager ID {$managerId}: LHO ID {$lhoId} should have manager_ids in response"
                    );
                    
                    $this->assert(
                        in_array($managerId, $lhoData['manager_ids']),
                        "Manager ID {$managerId}: LHO ID {$lhoId} response should include this manager in manager_ids"
                    );
                }
                
                // Check that no expected LHOs are missing from the results
                foreach ($expectedLhoIds as $expectedLhoId) {
                    $this->assert(
                        in_array($expectedLhoId, $returnedLhoIds),
                        "Manager ID {$managerId}: Expected LHO ID {$expectedLhoId} is missing from filtered results"
                    );
                }
                
                // Verify counts match
                $this->assert(
                    count($returnedLhoIds) === count($expectedLhoIds),
                    "Manager ID {$managerId}: Expected " . count($expectedLhoIds) . " LHOs, got " . count($returnedLhoIds)
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

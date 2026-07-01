<?php
/**
 * Property Test for Manager Search Inclusion
 * **Feature: lho-manager-assignment, Property 10: Manager Search Inclusion**
 * **Validates: Requirements 5.3**
 * 
 * For any search term matching a manager's name, LHOs assigned to that manager 
 * should appear in search results.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/LocationService.php';
require_once __DIR__ . '/../repositories/LhoManagerRepository.php';

class ManagerSearchInclusionPropertyTest extends PropertyTestBase {
    
    private $locationService;
    private $lhoManagerRepository;
    
    public function __construct() {
        parent::__construct();
        $this->locationService = new LocationService();
        $this->lhoManagerRepository = new LhoManagerRepository();
        $this->iterations = 100;
    }
    
    public function runTests() {
        echo "=== Manager Search Inclusion Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test Manager Search Inclusion
        $allPassed &= $this->runPropertyTest(
            "Manager Search Inclusion",
            [$this, 'testManagerSearchInclusion']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 10: Manager Search Inclusion
     * For any search term matching a manager's name, LHOs assigned to that manager 
     * should appear in search results.
     * **Feature: lho-manager-assignment, Property 10: Manager Search Inclusion**
     * **Validates: Requirements 5.3**
     */
    public function testManagerSearchInclusion() {
        try {
            // Get all managers with their names and assigned LHOs
            $sql = "SELECT DISTINCT lm.user_id, lm.lho_id, 
                           CONCAT(u.first_name, ' ', u.last_name) as manager_name,
                           u.first_name, u.last_name
                    FROM lho_managers lm
                    INNER JOIN users u ON lm.user_id = u.id";
            $managerAssignments = $this->getResults($sql, [], '');
            
            if (empty($managerAssignments)) {
                // No manager assignments to test - this is valid
                return ['success' => true];
            }
            
            // Group by manager to get unique managers
            $managersByName = [];
            foreach ($managerAssignments as $assignment) {
                $managerName = $assignment['manager_name'];
                if (!isset($managersByName[$managerName])) {
                    $managersByName[$managerName] = [
                        'user_id' => $assignment['user_id'],
                        'first_name' => $assignment['first_name'],
                        'last_name' => $assignment['last_name'],
                        'lho_ids' => []
                    ];
                }
                $managersByName[$managerName]['lho_ids'][] = (int)$assignment['lho_id'];
            }
            
            foreach ($managersByName as $managerName => $managerData) {
                // Test searching by first name
                $firstName = $managerData['first_name'];
                if (strlen($firstName) >= 2) {
                    $searchTerm = substr($firstName, 0, min(5, strlen($firstName)));
                    $this->verifySearchIncludesManagerLhos($searchTerm, $managerData['lho_ids'], $managerName);
                }
                
                // Test searching by last name
                $lastName = $managerData['last_name'];
                if (strlen($lastName) >= 2) {
                    $searchTerm = substr($lastName, 0, min(5, strlen($lastName)));
                    $this->verifySearchIncludesManagerLhos($searchTerm, $managerData['lho_ids'], $managerName);
                }
                
                // Test searching by full name (partial)
                if (strlen($managerName) >= 3) {
                    $searchTerm = substr($managerName, 0, min(8, strlen($managerName)));
                    $this->verifySearchIncludesManagerLhos($searchTerm, $managerData['lho_ids'], $managerName);
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
     * Helper method to verify that search results include LHOs managed by a manager
     * whose name matches the search term
     */
    private function verifySearchIncludesManagerLhos(string $searchTerm, array $expectedLhoIds, string $managerName) {
        // Get search results via the service
        $filters = ['search' => $searchTerm];
        $result = $this->locationService->getAllLhosWithManagers($filters);
        
        $returnedLhoIds = array_map('intval', array_column($result['data'], 'id'));
        
        // Check that all expected LHOs (managed by this manager) are in the results
        foreach ($expectedLhoIds as $expectedLhoId) {
            $this->assert(
                in_array($expectedLhoId, $returnedLhoIds),
                "Search term '{$searchTerm}' (matching manager '{$managerName}'): " .
                "Expected LHO ID {$expectedLhoId} to be in search results"
            );
        }
    }
}

<?php
/**
 * Property Test for Export Manager Inclusion
 * **Feature: lho-manager-assignment, Property 4: Export Manager Inclusion**
 * **Validates: Requirements 2.4**
 * 
 * For any LHO with assigned managers, the export output should contain 
 * all manager names for that LHO.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/LocationService.php';
require_once __DIR__ . '/../repositories/LhoManagerRepository.php';

class LhoManagerExportInclusionPropertyTest extends PropertyTestBase {
    
    private $locationService;
    private $lhoManagerRepository;
    
    public function __construct() {
        parent::__construct();
        $this->locationService = new LocationService();
        $this->lhoManagerRepository = new LhoManagerRepository();
        $this->iterations = 100;
    }
    
    public function runTests() {
        echo "=== Export Manager Inclusion Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Test Export Manager Inclusion
        $allPassed &= $this->runPropertyTest(
            "Export Manager Inclusion",
            [$this, 'testExportManagerInclusion']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 4: Export Manager Inclusion
     * For any LHO with assigned managers, the export output should contain 
     * all manager names for that LHO.
     * **Feature: lho-manager-assignment, Property 4: Export Manager Inclusion**
     * **Validates: Requirements 2.4**
     */
    public function testExportManagerInclusion() {
        try {
            // Get export data via the service
            $exportData = $this->locationService->exportLhosWithManagers([]);
            
            if (empty($exportData)) {
                // No LHOs to test - this is valid
                return ['success' => true];
            }
            
            foreach ($exportData as $lhoExport) {
                $lhoId = (int)$lhoExport['id'];
                
                // Get the actual managers from the database
                $managers = $this->lhoManagerRepository->getManagersByLhoId($lhoId);
                $actualManagerNames = array_column($managers, 'manager_name');
                
                // Check that 'managers' field exists in export
                $this->assert(
                    array_key_exists('managers', $lhoExport),
                    "LHO ID {$lhoId}: Export should include 'managers' field"
                );
                
                $exportedManagers = $lhoExport['managers'];
                
                // If no managers, the field should be empty
                if (empty($actualManagerNames)) {
                    $this->assert(
                        empty($exportedManagers),
                        "LHO ID {$lhoId}: Export managers field should be empty when no managers assigned"
                    );
                    continue;
                }
                
                // Check that all manager names are included in the export
                foreach ($actualManagerNames as $managerName) {
                    $this->assert(
                        strpos($exportedManagers, $managerName) !== false,
                        "LHO ID {$lhoId}: Export should contain manager name '{$managerName}'"
                    );
                }
                
                // Check manager_count matches
                $this->assert(
                    isset($lhoExport['manager_count']),
                    "LHO ID {$lhoId}: Export should include 'manager_count' field"
                );
                
                $this->assert(
                    (int)$lhoExport['manager_count'] === count($actualManagerNames),
                    "LHO ID {$lhoId}: Export manager_count should match actual count"
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
    
    /**
     * Test export with manager filter
     */
    public function testExportWithManagerFilter() {
        try {
            // Get all ADV users who are managers
            $sql = "SELECT DISTINCT user_id FROM lho_managers";
            $managerUsers = $this->getResults($sql, [], '');
            
            if (empty($managerUsers)) {
                // No managers to test
                return ['success' => true];
            }
            
            // Pick a random manager to filter by
            $randomIndex = array_rand($managerUsers);
            $managerId = (int)$managerUsers[$randomIndex]['user_id'];
            
            // Get export data filtered by this manager
            $exportData = $this->locationService->exportLhosWithManagers([
                'manager_id' => $managerId
            ]);
            
            // Get LHOs managed by this user
            $managedLhos = $this->lhoManagerRepository->getLhosByUserId($managerId);
            $managedLhoIds = array_column($managedLhos, 'lho_id');
            
            // Verify all exported LHOs are managed by this user
            foreach ($exportData as $lhoExport) {
                $lhoId = (int)$lhoExport['id'];
                
                $this->assert(
                    in_array($lhoId, $managedLhoIds),
                    "LHO ID {$lhoId}: Filtered export should only include LHOs managed by user {$managerId}"
                );
            }
            
            // Verify all managed LHOs are in the export
            $exportedLhoIds = array_column($exportData, 'id');
            foreach ($managedLhoIds as $managedLhoId) {
                // Check if the LHO exists and is not filtered out by status
                $lhoSql = "SELECT id FROM lhos WHERE id = ?";
                $lhoExists = $this->getResults($lhoSql, [$managedLhoId], 'i');
                
                if (!empty($lhoExists)) {
                    $this->assert(
                        in_array($managedLhoId, $exportedLhoIds),
                        "LHO ID {$managedLhoId}: Should be included in filtered export for manager {$managerId}"
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
}

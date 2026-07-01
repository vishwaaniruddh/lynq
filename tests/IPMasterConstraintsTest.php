<?php
/**
 * Property Tests: IP_Master Edit/Delete Constraints
 * 
 * **Feature: ip-configuration-management, Property 4: Configured IP Edit Prevention**
 * **Feature: ip-configuration-management, Property 5: IP Deletion Constraint**
 * **Validates: Requirements 1.4, 1.5**
 * 
 * Property 4: For any IP_Master with status 'configured', all edit operations SHALL be rejected.
 * Property 5: For any IP_Master with status 'configured' or 'locked', delete operations SHALL be rejected.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../services/IPMasterService.php';

class IPMasterConstraintsTest extends PropertyTestBase {
    private $repository;
    private $service;
    private $createdIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->repository = new IPMasterRepository();
        $this->service = new IPMasterService();
    }
    
    /**
     * Generate a valid IPv4 address
     */
    protected function generateValidIP(): string {
        $octets = [];
        for ($i = 0; $i < 4; $i++) {
            $octets[] = rand(0, 255);
        }
        return implode('.', $octets);
    }
    
    /**
     * Generate a unique IP combination
     */
    protected function generateUniqueIPCombination(): array {
        return [
            'network_ip' => $this->generateValidIP(),
            'router_ip' => $this->generateValidIP(),
            'site_ip' => $this->generateValidIP(),
            'subnet_mask' => '255.255.255.' . rand(0, 255),
        ];
    }
    
    /**
     * Create an IP_Master with a specific status
     */
    protected function createIPMasterWithStatus(string $status): ?int {
        $ipData = $this->generateUniqueIPCombination();
        
        try {
            $id = $this->repository->createIPMaster($ipData);
            $this->createdIds[] = $id;
            
            // Update status if not available
            if ($status !== IPMaster::STATUS_AVAILABLE) {
                $this->repository->updateStatus($id, $status);
            }
            
            return $id;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        foreach ($this->createdIds as $id) {
            try {
                // First reset status to available so we can delete
                $this->repository->updateStatus($id, IPMaster::STATUS_AVAILABLE);
                $this->repository->deleteIPMaster($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdIds = [];
    }
    
    /**
     * Property Test 4: Configured IP Edit Prevention
     * 
     * For any IP_Master with status 'configured', all edit operations SHALL be rejected.
     */
    public function testConfiguredIPEditPrevention(): bool {
        echo "\n=== Property Test 4: Configured IP Edit Prevention ===\n";
        
        $this->cleanupTestData();
        
        return $this->runPropertyTest(
            'Configured IPs cannot be edited',
            function() {
                // Create an IP_Master with configured status
                $id = $this->createIPMasterWithStatus(IPMaster::STATUS_CONFIGURED);
                if ($id === null) {
                    return ['success' => true]; // Skip if creation fails
                }
                
                // Attempt to edit the configured IP
                $newData = [
                    'network_ip' => $this->generateValidIP(),
                ];
                
                $result = $this->service->update($id, $newData);
                
                // Edit should be rejected
                if ($result['success']) {
                    return [
                        'success' => false,
                        'message' => 'Edit was allowed on configured IP_Master',
                        'data' => ['id' => $id, 'result' => $result]
                    ];
                }
                
                // Verify the error code is correct
                if ($result['code'] !== 'CONFIGURED_ERROR') {
                    return [
                        'success' => false,
                        'message' => 'Wrong error code for configured IP edit rejection',
                        'data' => ['expected' => 'CONFIGURED_ERROR', 'actual' => $result['code']]
                    ];
                }
                
                return ['success' => true];
            },
            50
        );
    }
    
    /**
     * Property Test: Available IPs can be edited
     * 
     * For any IP_Master with status 'available', edit operations SHALL be allowed.
     */
    public function testAvailableIPCanBeEdited(): bool {
        echo "\n=== Property Test: Available IPs Can Be Edited ===\n";
        
        $this->cleanupTestData();
        
        return $this->runPropertyTest(
            'Available IPs can be edited',
            function() {
                // Create an IP_Master with available status
                $id = $this->createIPMasterWithStatus(IPMaster::STATUS_AVAILABLE);
                if ($id === null) {
                    return ['success' => true]; // Skip if creation fails
                }
                
                // Attempt to edit the available IP
                $newIP = $this->generateValidIP();
                $newData = [
                    'network_ip' => $newIP,
                ];
                
                $result = $this->service->update($id, $newData);
                
                // Edit should be allowed
                if (!$result['success']) {
                    return [
                        'success' => false,
                        'message' => 'Edit was rejected on available IP_Master',
                        'data' => ['id' => $id, 'result' => $result]
                    ];
                }
                
                // Verify the change was applied
                $updated = $this->repository->findById($id);
                if ($updated['network_ip'] !== $newIP) {
                    return [
                        'success' => false,
                        'message' => 'Edit did not apply the change',
                        'data' => ['expected' => $newIP, 'actual' => $updated['network_ip']]
                    ];
                }
                
                return ['success' => true];
            },
            50
        );
    }
    
    /**
     * Property Test 5: IP Deletion Constraint - Configured
     * 
     * For any IP_Master with status 'configured', delete operations SHALL be rejected.
     */
    public function testConfiguredIPDeletionPrevention(): bool {
        echo "\n=== Property Test 5a: Configured IP Deletion Prevention ===\n";
        
        $this->cleanupTestData();
        
        return $this->runPropertyTest(
            'Configured IPs cannot be deleted',
            function() {
                // Create an IP_Master with configured status
                $id = $this->createIPMasterWithStatus(IPMaster::STATUS_CONFIGURED);
                if ($id === null) {
                    return ['success' => true]; // Skip if creation fails
                }
                
                // Attempt to delete the configured IP
                $result = $this->service->delete($id);
                
                // Delete should be rejected
                if ($result['success']) {
                    return [
                        'success' => false,
                        'message' => 'Delete was allowed on configured IP_Master',
                        'data' => ['id' => $id, 'result' => $result]
                    ];
                }
                
                // Verify the error code is correct
                if ($result['code'] !== 'CONFIGURED_ERROR') {
                    return [
                        'success' => false,
                        'message' => 'Wrong error code for configured IP delete rejection',
                        'data' => ['expected' => 'CONFIGURED_ERROR', 'actual' => $result['code']]
                    ];
                }
                
                // Verify the record still exists
                $existing = $this->repository->findById($id);
                if (!$existing) {
                    return [
                        'success' => false,
                        'message' => 'Configured IP_Master was deleted despite rejection',
                        'data' => ['id' => $id]
                    ];
                }
                
                return ['success' => true];
            },
            50
        );
    }
    
    /**
     * Property Test 5: IP Deletion Constraint - Locked
     * 
     * For any IP_Master with status 'locked', delete operations SHALL be rejected.
     */
    public function testLockedIPDeletionPrevention(): bool {
        echo "\n=== Property Test 5b: Locked IP Deletion Prevention ===\n";
        
        $this->cleanupTestData();
        
        return $this->runPropertyTest(
            'Locked IPs cannot be deleted',
            function() {
                // Create an IP_Master with locked status
                $id = $this->createIPMasterWithStatus(IPMaster::STATUS_LOCKED);
                if ($id === null) {
                    return ['success' => true]; // Skip if creation fails
                }
                
                // Attempt to delete the locked IP
                $result = $this->service->delete($id);
                
                // Delete should be rejected
                if ($result['success']) {
                    return [
                        'success' => false,
                        'message' => 'Delete was allowed on locked IP_Master',
                        'data' => ['id' => $id, 'result' => $result]
                    ];
                }
                
                // Verify the error code is correct
                if ($result['code'] !== 'LOCKED_ERROR') {
                    return [
                        'success' => false,
                        'message' => 'Wrong error code for locked IP delete rejection',
                        'data' => ['expected' => 'LOCKED_ERROR', 'actual' => $result['code']]
                    ];
                }
                
                // Verify the record still exists
                $existing = $this->repository->findById($id);
                if (!$existing) {
                    return [
                        'success' => false,
                        'message' => 'Locked IP_Master was deleted despite rejection',
                        'data' => ['id' => $id]
                    ];
                }
                
                return ['success' => true];
            },
            50
        );
    }
    
    /**
     * Property Test: Available IPs can be deleted
     * 
     * For any IP_Master with status 'available', delete operations SHALL be allowed.
     */
    public function testAvailableIPCanBeDeleted(): bool {
        echo "\n=== Property Test: Available IPs Can Be Deleted ===\n";
        
        $this->cleanupTestData();
        
        return $this->runPropertyTest(
            'Available IPs can be deleted',
            function() {
                // Create an IP_Master with available status
                $id = $this->createIPMasterWithStatus(IPMaster::STATUS_AVAILABLE);
                if ($id === null) {
                    return ['success' => true]; // Skip if creation fails
                }
                
                // Remove from cleanup list since we're deleting it
                $this->createdIds = array_filter($this->createdIds, fn($i) => $i !== $id);
                
                // Attempt to delete the available IP
                $result = $this->service->delete($id);
                
                // Delete should be allowed
                if (!$result['success']) {
                    // Add back to cleanup list if delete failed
                    $this->createdIds[] = $id;
                    return [
                        'success' => false,
                        'message' => 'Delete was rejected on available IP_Master',
                        'data' => ['id' => $id, 'result' => $result]
                    ];
                }
                
                // Verify the record no longer exists
                $existing = $this->repository->findById($id);
                if ($existing) {
                    return [
                        'success' => false,
                        'message' => 'Available IP_Master still exists after deletion',
                        'data' => ['id' => $id]
                    ];
                }
                
                return ['success' => true];
            },
            50
        );
    }
    
    /**
     * Property Test: canEdit and canDelete helper methods
     * 
     * Tests that the helper methods correctly reflect the constraints.
     */
    public function testHelperMethods(): bool {
        echo "\n=== Property Test: Helper Methods ===\n";
        
        $this->cleanupTestData();
        
        return $this->runPropertyTest(
            'canEdit and canDelete helper methods work correctly',
            function() {
                // Test for each status
                $statuses = [
                    IPMaster::STATUS_AVAILABLE => ['canEdit' => true, 'canDelete' => true],
                    IPMaster::STATUS_LOCKED => ['canEdit' => true, 'canDelete' => false],
                    IPMaster::STATUS_CONFIGURED => ['canEdit' => false, 'canDelete' => false],
                ];
                
                $status = array_rand($statuses);
                $expected = $statuses[$status];
                
                $id = $this->createIPMasterWithStatus($status);
                if ($id === null) {
                    return ['success' => true]; // Skip if creation fails
                }
                
                $canEdit = $this->service->canEdit($id);
                $canDelete = $this->service->canDelete($id);
                
                if ($canEdit !== $expected['canEdit']) {
                    return [
                        'success' => false,
                        'message' => "canEdit returned wrong value for status '$status'",
                        'data' => ['expected' => $expected['canEdit'], 'actual' => $canEdit]
                    ];
                }
                
                if ($canDelete !== $expected['canDelete']) {
                    return [
                        'success' => false,
                        'message' => "canDelete returned wrong value for status '$status'",
                        'data' => ['expected' => $expected['canDelete'], 'actual' => $canDelete]
                    ];
                }
                
                return ['success' => true];
            },
            50
        );
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): array {
        $results = [];
        
        $results['configured_edit_prevention'] = $this->testConfiguredIPEditPrevention();
        $this->cleanupTestData();
        
        $results['available_can_edit'] = $this->testAvailableIPCanBeEdited();
        $this->cleanupTestData();
        
        $results['configured_delete_prevention'] = $this->testConfiguredIPDeletionPrevention();
        $this->cleanupTestData();
        
        $results['locked_delete_prevention'] = $this->testLockedIPDeletionPrevention();
        $this->cleanupTestData();
        
        $results['available_can_delete'] = $this->testAvailableIPCanBeDeleted();
        $this->cleanupTestData();
        
        $results['helper_methods'] = $this->testHelperMethods();
        $this->cleanupTestData();
        
        $passed = array_filter($results);
        $total = count($results);
        $passedCount = count($passed);
        
        echo "\n=== Summary ===\n";
        echo "Passed: $passedCount / $total\n";
        
        if ($passedCount === $total) {
            echo "✓ All property tests passed!\n";
        } else {
            echo "✗ Some property tests failed.\n";
            foreach ($results as $name => $result) {
                if (!$result) {
                    echo "  - Failed: $name\n";
                }
            }
        }
        
        return $results;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new IPMasterConstraintsTest();
    $results = $test->runAllTests();
    exit(in_array(false, $results, true) ? 1 : 0);
}

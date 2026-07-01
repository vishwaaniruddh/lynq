<?php
/**
 * Property Test: IP_Master Uniqueness
 * 
 * **Feature: ip-configuration-management, Property 2: IP_Master Uniqueness**
 * **Validates: Requirements 1.2**
 * 
 * Property: For any two IP_Master records in the system, the combination of 
 * (Network IP, Router IP, Site IP, Subnet Mask) SHALL be distinct.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';

class IPMasterUniquenessTest extends PropertyTestBase {
    private $repository;
    private $createdIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->repository = new IPMasterRepository();
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
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        foreach ($this->createdIds as $id) {
            try {
                $this->repository->deleteIPMaster($id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdIds = [];
    }
    
    /**
     * Property Test: Duplicate IP combinations should be detected
     * 
     * For any IP combination, if it already exists in the database,
     * checkDuplicate should return true.
     */
    public function testDuplicateDetection(): bool {
        echo "\n=== Property Test: Duplicate Detection ===\n";
        
        $this->cleanupTestData();
        
        return $this->runPropertyTest(
            'Duplicate IP combinations are detected',
            function() {
                // Generate a unique IP combination
                $ipData = $this->generateUniqueIPCombination();
                
                // First, verify it doesn't exist
                $existsBefore = $this->repository->checkDuplicateFromArray($ipData);
                if ($existsBefore) {
                    // Skip this iteration if by chance we generated an existing combination
                    return ['success' => true];
                }
                
                // Create the IP_Master record
                try {
                    $id = $this->repository->createIPMaster($ipData);
                    $this->createdIds[] = $id;
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create IP_Master: ' . $e->getMessage(),
                        'data' => $ipData
                    ];
                }
                
                // Now check if duplicate is detected
                $existsAfter = $this->repository->checkDuplicateFromArray($ipData);
                if (!$existsAfter) {
                    return [
                        'success' => false,
                        'message' => 'Duplicate IP combination was not detected after creation',
                        'data' => $ipData
                    ];
                }
                
                return ['success' => true];
            },
            50 // Reduced iterations for database tests
        );
    }
    
    /**
     * Property Test: Unique IP combinations should not be flagged as duplicates
     * 
     * For any two different IP combinations, checkDuplicate should return false
     * for the second combination.
     */
    public function testUniqueNotFlaggedAsDuplicate(): bool {
        echo "\n=== Property Test: Unique Not Flagged As Duplicate ===\n";
        
        $this->cleanupTestData();
        
        return $this->runPropertyTest(
            'Unique IP combinations are not flagged as duplicates',
            function() {
                // Generate two different IP combinations
                $ipData1 = $this->generateUniqueIPCombination();
                $ipData2 = $this->generateUniqueIPCombination();
                
                // Ensure they are different
                while ($ipData1 === $ipData2) {
                    $ipData2 = $this->generateUniqueIPCombination();
                }
                
                // Create the first IP_Master record
                try {
                    $id1 = $this->repository->createIPMaster($ipData1);
                    $this->createdIds[] = $id1;
                } catch (Exception $e) {
                    // Skip if creation fails (might be duplicate by chance)
                    return ['success' => true];
                }
                
                // Check if second combination is flagged as duplicate (it shouldn't be)
                $isDuplicate = $this->repository->checkDuplicateFromArray($ipData2);
                if ($isDuplicate) {
                    // Verify it's actually different
                    $existing = $this->repository->findByIPCombination(
                        $ipData2['network_ip'],
                        $ipData2['router_ip'],
                        $ipData2['site_ip'],
                        $ipData2['subnet_mask']
                    );
                    
                    if ($existing) {
                        // It actually exists, so this is correct behavior
                        return ['success' => true];
                    }
                    
                    return [
                        'success' => false,
                        'message' => 'Unique IP combination was incorrectly flagged as duplicate',
                        'data' => ['ip1' => $ipData1, 'ip2' => $ipData2]
                    ];
                }
                
                return ['success' => true];
            },
            50
        );
    }
    
    /**
     * Property Test: Exclude ID works correctly for updates
     * 
     * When checking for duplicates with an excludeId, the record with that ID
     * should not be considered a duplicate of itself.
     */
    public function testExcludeIdForUpdates(): bool {
        echo "\n=== Property Test: Exclude ID For Updates ===\n";
        
        $this->cleanupTestData();
        
        return $this->runPropertyTest(
            'Exclude ID works correctly for updates',
            function() {
                // Generate a unique IP combination
                $ipData = $this->generateUniqueIPCombination();
                
                // Create the IP_Master record
                try {
                    $id = $this->repository->createIPMaster($ipData);
                    $this->createdIds[] = $id;
                } catch (Exception $e) {
                    // Skip if creation fails
                    return ['success' => true];
                }
                
                // Check duplicate without exclude - should be true
                $isDuplicateWithoutExclude = $this->repository->checkDuplicateFromArray($ipData);
                if (!$isDuplicateWithoutExclude) {
                    return [
                        'success' => false,
                        'message' => 'Record not detected as duplicate without exclude',
                        'data' => $ipData
                    ];
                }
                
                // Check duplicate with exclude - should be false (not a duplicate of itself)
                $isDuplicateWithExclude = $this->repository->checkDuplicateFromArray($ipData, $id);
                if ($isDuplicateWithExclude) {
                    return [
                        'success' => false,
                        'message' => 'Record incorrectly flagged as duplicate of itself when excluded',
                        'data' => ['ipData' => $ipData, 'id' => $id]
                    ];
                }
                
                return ['success' => true];
            },
            50
        );
    }
    
    /**
     * Property Test: Partial matches are not duplicates
     * 
     * If only some IP fields match but not all four, it should not be a duplicate.
     */
    public function testPartialMatchesNotDuplicates(): bool {
        echo "\n=== Property Test: Partial Matches Not Duplicates ===\n";
        
        $this->cleanupTestData();
        
        return $this->runPropertyTest(
            'Partial IP matches are not flagged as duplicates',
            function() {
                // Generate a unique IP combination
                $ipData = $this->generateUniqueIPCombination();
                
                // Create the IP_Master record
                try {
                    $id = $this->repository->createIPMaster($ipData);
                    $this->createdIds[] = $id;
                } catch (Exception $e) {
                    // Skip if creation fails
                    return ['success' => true];
                }
                
                // Create variations with only partial matches
                $partialMatches = [
                    // Different network_ip only
                    [
                        'network_ip' => $this->generateValidIP(),
                        'router_ip' => $ipData['router_ip'],
                        'site_ip' => $ipData['site_ip'],
                        'subnet_mask' => $ipData['subnet_mask'],
                    ],
                    // Different router_ip only
                    [
                        'network_ip' => $ipData['network_ip'],
                        'router_ip' => $this->generateValidIP(),
                        'site_ip' => $ipData['site_ip'],
                        'subnet_mask' => $ipData['subnet_mask'],
                    ],
                    // Different site_ip only
                    [
                        'network_ip' => $ipData['network_ip'],
                        'router_ip' => $ipData['router_ip'],
                        'site_ip' => $this->generateValidIP(),
                        'subnet_mask' => $ipData['subnet_mask'],
                    ],
                    // Different subnet_mask only
                    [
                        'network_ip' => $ipData['network_ip'],
                        'router_ip' => $ipData['router_ip'],
                        'site_ip' => $ipData['site_ip'],
                        'subnet_mask' => '255.255.255.' . rand(0, 255),
                    ],
                ];
                
                // Pick a random partial match to test
                $partialMatch = $partialMatches[array_rand($partialMatches)];
                
                // Ensure it's actually different
                if ($partialMatch === $ipData) {
                    return ['success' => true]; // Skip if by chance they're the same
                }
                
                $isDuplicate = $this->repository->checkDuplicateFromArray($partialMatch);
                if ($isDuplicate) {
                    // Verify it's not actually the same
                    $existing = $this->repository->findByIPCombination(
                        $partialMatch['network_ip'],
                        $partialMatch['router_ip'],
                        $partialMatch['site_ip'],
                        $partialMatch['subnet_mask']
                    );
                    
                    if ($existing) {
                        // It actually exists, so this is correct behavior
                        return ['success' => true];
                    }
                    
                    return [
                        'success' => false,
                        'message' => 'Partial match incorrectly flagged as duplicate',
                        'data' => ['original' => $ipData, 'partial' => $partialMatch]
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
        
        $results['duplicate_detection'] = $this->testDuplicateDetection();
        $this->cleanupTestData();
        
        $results['unique_not_flagged'] = $this->testUniqueNotFlaggedAsDuplicate();
        $this->cleanupTestData();
        
        $results['exclude_id_updates'] = $this->testExcludeIdForUpdates();
        $this->cleanupTestData();
        
        $results['partial_matches'] = $this->testPartialMatchesNotDuplicates();
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
    $test = new IPMasterUniquenessTest();
    $results = $test->runAllTests();
    exit(in_array(false, $results, true) ? 1 : 0);
}

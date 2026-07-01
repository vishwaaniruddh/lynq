<?php
/**
 * Property Test: Configuration Dashboard Accuracy
 * 
 * **Feature: ip-configuration-management, Property 18: Dashboard Router Count Accuracy**
 * **Feature: ip-configuration-management, Property 19: Dashboard IP Count Accuracy**
 * **Validates: Requirements 7.1, 7.2**
 * 
 * Property 18: For any dashboard view, the sum of (Configured + Unconfigured + In Progress) 
 * router counts SHALL equal the total router count in the system.
 * 
 * Property 19: For any dashboard view, the sum of (Available + Locked + Configured) IP counts 
 * SHALL equal the total IP_Master count in the system.
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/ConfigurationDashboardService.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../repositories/IPLockRepository.php';
require_once __DIR__ . '/../repositories/RouterIPBindingRepository.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../models/IPLock.php';
require_once __DIR__ . '/../models/RouterIPBinding.php';

class ConfigurationDashboardAccuracyTest extends PropertyTestBase {
    
    private $dashboardService;
    private $ipMasterRepository;
    private $ipLockRepository;
    private $bindingRepository;
    private $createdRecords = [];
    
    public function __construct() {
        parent::__construct();
        $this->dashboardService = new ConfigurationDashboardService();
        $this->ipMasterRepository = new IPMasterRepository();
        $this->ipLockRepository = new IPLockRepository();
        $this->bindingRepository = new RouterIPBindingRepository();
    }
    
    public function runTests() {
        echo "=== Configuration Dashboard Accuracy Property Test ===\n";
        echo "**Feature: ip-configuration-management, Property 18 & 19**\n";
        echo "**Validates: Requirements 7.1, 7.2**\n\n";
        
        // Check if required tables exist
        if (!$this->tablesExist()) {
            echo "SKIPPED: Required tables not found. Run migrations first.\n";
            return true;
        }
        
        $allPassed = true;
        
        // Run property test for IP count accuracy (Property 19)
        $allPassed &= $this->runPropertyTest(
            'Property 19: Dashboard IP Count Accuracy - sum of status counts equals total',
            function() {
                return $this->testIPCountAccuracy();
            },
            100
        );
        
        // Run property test for IP status breakdown accuracy
        $allPassed &= $this->runPropertyTest(
            'Property 19b: Dashboard IP Status Breakdown matches database',
            function() {
                return $this->testIPStatusBreakdownAccuracy();
            },
            100
        );
        
        // Run property test for router count consistency (Property 18)
        $allPassed &= $this->runPropertyTest(
            'Property 18: Dashboard Router Count Accuracy - status sum equals total',
            function() {
                return $this->testRouterCountAccuracy();
            },
            100
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Test IP count accuracy
     * 
     * Property 19: For any dashboard view, the sum of (Available + Locked + Configured) 
     * IP counts SHALL equal the total IP_Master count in the system.
     */
    private function testIPCountAccuracy() {
        // Create random IP_Master records with random statuses
        $numIPs = $this->generateRandomInt(1, 10);
        $statuses = [IPMaster::STATUS_AVAILABLE, IPMaster::STATUS_LOCKED, IPMaster::STATUS_CONFIGURED];
        
        for ($i = 0; $i < $numIPs; $i++) {
            $status = $this->generateRandomChoice($statuses);
            $ipMaster = $this->createTestIPMaster($status);
            if (!$ipMaster) {
                return ['success' => false, 'message' => 'Failed to create test IP_Master'];
            }
        }
        
        // Get dashboard IP stats
        $ipStats = $this->dashboardService->getIPStats();
        
        // Calculate sum of status counts
        $statusSum = $ipStats['available'] + $ipStats['locked'] + $ipStats['configured'];
        
        // Verify sum equals total
        if ($statusSum !== $ipStats['total']) {
            return [
                'success' => false,
                'message' => 'IP status sum does not equal total',
                'data' => [
                    'total' => $ipStats['total'],
                    'available' => $ipStats['available'],
                    'locked' => $ipStats['locked'],
                    'configured' => $ipStats['configured'],
                    'status_sum' => $statusSum
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test IP status breakdown accuracy
     * 
     * Property 19: Dashboard IP status counts match actual database counts
     */
    private function testIPStatusBreakdownAccuracy() {
        // Create test IP_Master records with known statuses
        $expectedCounts = [
            IPMaster::STATUS_AVAILABLE => $this->generateRandomInt(0, 5),
            IPMaster::STATUS_LOCKED => $this->generateRandomInt(0, 3),
            IPMaster::STATUS_CONFIGURED => $this->generateRandomInt(0, 5)
        ];
        
        foreach ($expectedCounts as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $ipMaster = $this->createTestIPMaster($status);
                if (!$ipMaster) {
                    return ['success' => false, 'message' => "Failed to create test IP_Master with status $status"];
                }
            }
        }
        
        // Get dashboard IP stats
        $ipStats = $this->dashboardService->getIPStats();
        
        // Get actual counts from database for our test IPs
        $actualCounts = $this->getActualIPCountsByStatus();
        
        // Verify dashboard counts match database counts
        // Note: We compare the total counts since other tests may have created IPs
        $dashboardTotal = $ipStats['available'] + $ipStats['locked'] + $ipStats['configured'];
        $actualTotal = $actualCounts[IPMaster::STATUS_AVAILABLE] + 
                       $actualCounts[IPMaster::STATUS_LOCKED] + 
                       $actualCounts[IPMaster::STATUS_CONFIGURED];
        
        if ($dashboardTotal !== $actualTotal) {
            return [
                'success' => false,
                'message' => 'Dashboard total does not match database total',
                'data' => [
                    'dashboard_total' => $dashboardTotal,
                    'actual_total' => $actualTotal,
                    'dashboard_stats' => $ipStats,
                    'actual_counts' => $actualCounts
                ]
            ];
        }
        
        // Verify each status count matches
        if ($ipStats['available'] !== $actualCounts[IPMaster::STATUS_AVAILABLE]) {
            return [
                'success' => false,
                'message' => 'Available count mismatch',
                'data' => [
                    'dashboard_available' => $ipStats['available'],
                    'actual_available' => $actualCounts[IPMaster::STATUS_AVAILABLE]
                ]
            ];
        }
        
        if ($ipStats['locked'] !== $actualCounts[IPMaster::STATUS_LOCKED]) {
            return [
                'success' => false,
                'message' => 'Locked count mismatch',
                'data' => [
                    'dashboard_locked' => $ipStats['locked'],
                    'actual_locked' => $actualCounts[IPMaster::STATUS_LOCKED]
                ]
            ];
        }
        
        if ($ipStats['configured'] !== $actualCounts[IPMaster::STATUS_CONFIGURED]) {
            return [
                'success' => false,
                'message' => 'Configured count mismatch',
                'data' => [
                    'dashboard_configured' => $ipStats['configured'],
                    'actual_configured' => $actualCounts[IPMaster::STATUS_CONFIGURED]
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Test router count accuracy
     * 
     * Property 18: For any dashboard view, the sum of (Configured + Unconfigured + In Progress) 
     * router counts SHALL equal the total router count in the system.
     */
    private function testRouterCountAccuracy() {
        // Get dashboard router stats
        $routerStats = $this->dashboardService->getRouterStats();
        
        // Calculate sum of status counts
        $statusSum = $routerStats['configured'] + $routerStats['unconfigured'] + $routerStats['in_progress'];
        
        // Verify sum equals total
        if ($statusSum !== $routerStats['total']) {
            return [
                'success' => false,
                'message' => 'Router status sum does not equal total',
                'data' => [
                    'total' => $routerStats['total'],
                    'configured' => $routerStats['configured'],
                    'unconfigured' => $routerStats['unconfigured'],
                    'in_progress' => $routerStats['in_progress'],
                    'status_sum' => $statusSum
                ]
            ];
        }
        
        // Verify configured count matches active bindings
        $actualConfigured = $this->bindingRepository->countActive();
        if ($routerStats['configured'] !== $actualConfigured) {
            return [
                'success' => false,
                'message' => 'Configured router count does not match active bindings',
                'data' => [
                    'dashboard_configured' => $routerStats['configured'],
                    'actual_bindings' => $actualConfigured
                ]
            ];
        }
        
        // Verify in_progress count matches active locks
        $actualInProgress = $this->ipLockRepository->getActiveLockCount();
        if ($routerStats['in_progress'] !== $actualInProgress) {
            return [
                'success' => false,
                'message' => 'In-progress router count does not match active locks',
                'data' => [
                    'dashboard_in_progress' => $routerStats['in_progress'],
                    'actual_locks' => $actualInProgress
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Get actual IP counts by status from database
     */
    private function getActualIPCountsByStatus(): array {
        $sql = "SELECT status, COUNT(*) as count FROM ip_master GROUP BY status";
        $results = $this->getResults($sql, [], '');
        
        $counts = [
            IPMaster::STATUS_AVAILABLE => 0,
            IPMaster::STATUS_LOCKED => 0,
            IPMaster::STATUS_CONFIGURED => 0
        ];
        
        foreach ($results as $row) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int)$row['count'];
            }
        }
        
        return $counts;
    }
    
    /**
     * Check if required tables exist
     */
    private function tablesExist(): bool {
        $requiredTables = ['ip_master', 'ip_locks', 'router_ip_bindings', 'configuration_audit_log'];
        
        foreach ($requiredTables as $table) {
            try {
                $result = $this->db->query("SHOW TABLES LIKE '$table'");
                if (!$result || $result->num_rows === 0) {
                    return false;
                }
            } catch (Exception $e) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create a test IP_Master record
     */
    private function createTestIPMaster(string $status = IPMaster::STATUS_AVAILABLE): ?array {
        try {
            $ipData = [
                'network_ip' => $this->generateRandomIP(),
                'router_ip' => $this->generateRandomIP(),
                'site_ip' => $this->generateRandomIP(),
                'subnet_mask' => '255.255.255.0',
                'status' => $status
            ];
            
            $id = $this->ipMasterRepository->createIPMaster($ipData);
            $this->createdRecords['ip_master'][] = $id;
            
            return $this->ipMasterRepository->findById($id);
            
        } catch (Exception $e) {
            error_log("Failed to create test IP_Master: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate a random valid IP address
     */
    private function generateRandomIP(): string {
        return sprintf(
            '%d.%d.%d.%d',
            $this->generateRandomInt(1, 254),
            $this->generateRandomInt(0, 255),
            $this->generateRandomInt(0, 255),
            $this->generateRandomInt(1, 254)
        );
    }
    
    /**
     * Clean up all test data created during tests
     */
    public function cleanupTestData() {
        try {
            // Delete test IP_Master records
            if (!empty($this->createdRecords['ip_master'])) {
                $ids = implode(',', array_map('intval', $this->createdRecords['ip_master']));
                
                // First delete any locks referencing these IPs
                $this->db->query("DELETE FROM `ip_locks` WHERE ip_master_id IN ($ids)");
                
                // Then delete any bindings referencing these IPs
                $this->db->query("DELETE FROM `router_ip_bindings` WHERE ip_master_id IN ($ids)");
                
                // Finally delete the IP_Master records
                $this->db->query("DELETE FROM `ip_master` WHERE id IN ($ids)");
            }
            
            $this->createdRecords = [];
            
        } catch (Exception $e) {
            echo "Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

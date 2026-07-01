<?php
/**
 * Configuration Dashboard Service
 * Provides dashboard statistics for IP Configuration Management
 * 
 * Requirements: 7.1, 7.2, 7.3, 7.4
 * - 7.1: Display total routers count with breakdown by configuration status
 * - 7.2: Display total IP_Master count with breakdown by status
 * - 7.3: Display count of currently locked IPs with remaining lock time
 * - 7.4: Display recent configuration activities with timestamps and users
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../repositories/IPLockRepository.php';
require_once __DIR__ . '/../repositories/RouterIPBindingRepository.php';
require_once __DIR__ . '/../repositories/ConfigurationAuditLogRepository.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../models/IPLock.php';
require_once __DIR__ . '/../models/RouterIPBinding.php';

class ConfigurationDashboardService {
    private $db;
    private $ipMasterRepository;
    private $ipLockRepository;
    private $bindingRepository;
    private $auditLogRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->ipMasterRepository = new IPMasterRepository();
        $this->ipLockRepository = new IPLockRepository();
        $this->bindingRepository = new RouterIPBindingRepository();
        $this->auditLogRepository = new ConfigurationAuditLogRepository();
    }
    
    /**
     * Get router statistics for dashboard
     * Returns configured/unconfigured/in-progress counts
     * 
     * @return array Router statistics
     * 
     * Requirements: 7.1
     */
    public function getRouterStats(): array {
        // First, expire any timed-out locks to ensure accurate counts
        $this->ipLockRepository->expireTimedOutLocks();
        
        // Get count of configured routers (active bindings)
        $configuredCount = $this->bindingRepository->countActive();
        
        // Get count of routers in progress (active locks)
        $inProgressCount = $this->ipLockRepository->getActiveLockCount();
        
        // Get total routers from inventory
        // Note: This queries the inventory table for routers
        $totalRouters = $this->getTotalRoutersFromInventory();
        
        // Calculate unconfigured routers
        $unconfiguredCount = max(0, $totalRouters - $configuredCount - $inProgressCount);
        
        return [
            'total' => $totalRouters,
            'configured' => $configuredCount,
            'unconfigured' => $unconfiguredCount,
            'in_progress' => $inProgressCount
        ];
    }
    
    /**
     * Get IP statistics for dashboard
     * Returns available/locked/configured counts
     * 
     * @return array IP statistics
     * 
     * Requirements: 7.2
     */
    public function getIPStats(): array {
        // First, expire any timed-out locks to ensure accurate counts
        $this->ipLockRepository->expireTimedOutLocks();
        
        // Get counts by status from IP_Master table
        $counts = $this->ipMasterRepository->getCountByStatus();
        
        return [
            'total' => $counts['total'],
            'available' => $counts[IPMaster::STATUS_AVAILABLE] ?? 0,
            'locked' => $counts[IPMaster::STATUS_LOCKED] ?? 0,
            'configured' => $counts[IPMaster::STATUS_CONFIGURED] ?? 0
        ];
    }
    
    /**
     * Get locked IPs with remaining time for dashboard
     * 
     * @return array Locked IPs with details and remaining time
     * 
     * Requirements: 7.3
     */
    public function getLockedIPsWithTime(): array {
        // First, expire any timed-out locks
        $this->ipLockRepository->expireTimedOutLocks();
        
        // Get active locks with details
        $activeLocks = $this->ipLockRepository->getActiveLocks();
        
        $result = [];
        foreach ($activeLocks as $lock) {
            $remainingSeconds = max(0, (int)($lock['remaining_seconds'] ?? 0));
            $remainingMinutes = ceil($remainingSeconds / 60);
            
            $result[] = [
                'lock_id' => $lock['id'],
                'ip_master_id' => $lock['ip_master_id'],
                'network_ip' => $lock['network_ip'] ?? null,
                'router_ip' => $lock['router_ip'] ?? null,
                'site_ip' => $lock['site_ip'] ?? null,
                'subnet_mask' => $lock['subnet_mask'] ?? null,
                'router_serial_number' => $lock['router_serial_number'],
                'locked_by' => $lock['locked_by'],
                'locked_by_username' => $lock['locked_by_username'] ?? null,
                'locked_at' => $lock['locked_at'],
                'expires_at' => $lock['expires_at'],
                'remaining_seconds' => $remainingSeconds,
                'remaining_minutes' => $remainingMinutes,
                'remaining_formatted' => $this->formatRemainingTime($remainingSeconds)
            ];
        }
        
        return $result;
    }
    
    /**
     * Get recent configuration activities for dashboard
     * 
     * @param int $limit Number of activities to return
     * @return array Recent activities with timestamps and users
     * 
     * Requirements: 7.4
     */
    public function getRecentActivities(int $limit = 10): array {
        return $this->auditLogRepository->getRecentActivities($limit);
    }
    
    /**
     * Get all dashboard data in a single call
     * 
     * @param int $recentActivitiesLimit Number of recent activities to include
     * @return array Complete dashboard data
     * 
     * Requirements: 7.1, 7.2, 7.3, 7.4
     */
    public function getDashboardData(int $recentActivitiesLimit = 10): array {
        return [
            'router_stats' => $this->getRouterStats(),
            'ip_stats' => $this->getIPStats(),
            'locked_ips' => $this->getLockedIPsWithTime(),
            'recent_activities' => $this->getRecentActivities($recentActivitiesLimit),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get total routers from inventory
     * Queries the inventory system for router count
     * 
     * @return int Total router count
     */
    private function getTotalRoutersFromInventory(): int {
        try {
            // Query the inventory/assets table for routers
            // Routers are typically identified by product category or type
            $sql = "SELECT COUNT(DISTINCT serial_number) as count 
                    FROM assets a
                    JOIN products p ON a.product_id = p.id
                    JOIN product_categories pc ON p.category_id = pc.id
                    WHERE pc.name LIKE '%router%' 
                    OR pc.name LIKE '%Router%'
                    OR p.name LIKE '%router%'
                    OR p.name LIKE '%Router%'";
            
            $result = $this->db->getResults($sql, [], '');
            
            if (!empty($result) && isset($result[0]['count'])) {
                return (int)$result[0]['count'];
            }
            
            // Fallback: count unique router serial numbers from bindings and locks
            return $this->getRouterCountFromConfigurationData();
            
        } catch (Exception $e) {
            error_log("Error getting router count from inventory: " . $e->getMessage());
            return $this->getRouterCountFromConfigurationData();
        }
    }
    
    /**
     * Get router count from configuration data (fallback)
     * Counts unique router serial numbers from bindings and locks
     * 
     * @return int Router count
     */
    private function getRouterCountFromConfigurationData(): int {
        try {
            $sql = "SELECT COUNT(DISTINCT router_serial_number) as count FROM (
                        SELECT router_serial_number FROM router_ip_bindings WHERE status = ?
                        UNION
                        SELECT router_serial_number FROM ip_locks WHERE status = ? AND expires_at > NOW()
                    ) as routers";
            
            $result = $this->db->getResults($sql, [
                RouterIPBinding::STATUS_ACTIVE,
                IPLock::STATUS_ACTIVE
            ], 'ss');
            
            return (int)($result[0]['count'] ?? 0);
            
        } catch (Exception $e) {
            error_log("Error getting router count from configuration data: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Format remaining time in human-readable format
     * 
     * @param int $seconds Remaining seconds
     * @return string Formatted time string
     */
    private function formatRemainingTime(int $seconds): string {
        if ($seconds <= 0) {
            return 'Expired';
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        if ($minutes > 0) {
            return sprintf('%d min %d sec', $minutes, $remainingSeconds);
        }
        
        return sprintf('%d sec', $remainingSeconds);
    }
    
    /**
     * Get configuration summary for a specific time period
     * 
     * @param string $dateFrom Start date (Y-m-d format)
     * @param string $dateTo End date (Y-m-d format)
     * @return array Configuration summary
     */
    public function getConfigurationSummary(string $dateFrom, string $dateTo): array {
        try {
            // Get configurations in date range
            $sql = "SELECT COUNT(*) as count FROM router_ip_bindings 
                    WHERE configured_at >= ? AND configured_at <= ?";
            $configResult = $this->db->getResults($sql, [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ], 'ss');
            
            // Get unbinds in date range
            $sql = "SELECT COUNT(*) as count FROM router_ip_bindings 
                    WHERE unbound_at >= ? AND unbound_at <= ?";
            $unbindResult = $this->db->getResults($sql, [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ], 'ss');
            
            // Get expired locks in date range
            $sql = "SELECT COUNT(*) as count FROM ip_locks 
                    WHERE status = ? AND expires_at >= ? AND expires_at <= ?";
            $expiredResult = $this->db->getResults($sql, [
                IPLock::STATUS_EXPIRED,
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ], 'sss');
            
            return [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'configurations' => (int)($configResult[0]['count'] ?? 0),
                'unbinds' => (int)($unbindResult[0]['count'] ?? 0),
                'expired_locks' => (int)($expiredResult[0]['count'] ?? 0)
            ];
            
        } catch (Exception $e) {
            error_log("Error getting configuration summary: " . $e->getMessage());
            return [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'configurations' => 0,
                'unbinds' => 0,
                'expired_locks' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get IP utilization percentage
     * 
     * @return array Utilization data
     */
    public function getIPUtilization(): array {
        $stats = $this->getIPStats();
        $total = $stats['total'];
        
        if ($total === 0) {
            return [
                'total' => 0,
                'utilized' => 0,
                'utilization_percentage' => 0
            ];
        }
        
        $utilized = $stats['configured'] + $stats['locked'];
        $utilizationPercentage = round(($utilized / $total) * 100, 2);
        
        return [
            'total' => $total,
            'utilized' => $utilized,
            'available' => $stats['available'],
            'utilization_percentage' => $utilizationPercentage
        ];
    }
}

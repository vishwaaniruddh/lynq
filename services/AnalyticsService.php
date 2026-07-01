<?php
/**
 * ADV Clarity Management System - Analytics Service
 * Handles PWA usage analytics and metrics tracking
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/BaseModel.php';

class AnalyticsService {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
    }
    
    /**
     * Track PWA usage event
     */
    public function trackPWAEvent($eventData) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO pwa_analytics 
                (user_id, company_id, event_type, event_data, user_agent, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $eventDataJson = json_encode($eventData['event_data']);
            
            $stmt->bind_param("iissss",
                $eventData['user_id'],
                $eventData['company_id'],
                $eventData['event_type'],
                $eventDataJson,
                $eventData['user_agent'],
                $eventData['ip_address']
            );
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Failed to track PWA event: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get PWA analytics for user/company
     */
    public function getPWAAnalytics($userId, $companyId, $timeframe = '7d') {
        try {
            $days = $this->parseTimeframe($timeframe);
            
            // Get event counts by type
            $eventStats = $this->getEventStatistics($userId, $companyId, $days);
            
            // Get installation metrics
            $installationStats = $this->getInstallationStatistics($companyId, $days);
            
            // Get offline usage metrics
            $offlineStats = $this->getOfflineStatistics($userId, $companyId, $days);
            
            // Get performance metrics
            $performanceStats = $this->getPerformanceStatistics($userId, $companyId, $days);
            
            return [
                'timeframe' => $timeframe,
                'events' => $eventStats,
                'installations' => $installationStats,
                'offline' => $offlineStats,
                'performance' => $performanceStats,
                'summary' => $this->generateSummary($eventStats, $installationStats, $offlineStats)
            ];
            
        } catch (Exception $e) {
            error_log("Failed to get PWA analytics: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Parse timeframe string to days
     */
    private function parseTimeframe($timeframe) {
        $timeframes = [
            '1d' => 1,
            '7d' => 7,
            '30d' => 30,
            '90d' => 90
        ];
        
        return $timeframes[$timeframe] ?? 7;
    }
    
    /**
     * Get event statistics
     */
    private function getEventStatistics($userId, $companyId, $days) {
        $stmt = $this->db->prepare("
            SELECT 
                event_type,
                COUNT(*) as count,
                DATE(created_at) as date
            FROM pwa_analytics 
            WHERE user_id = ? AND company_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY event_type, DATE(created_at)
            ORDER BY date DESC, count DESC
        ");
        
        $stmt->bind_param("iii", $userId, $companyId, $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
        
        return $events;
    }
    
    /**
     * Get installation statistics
     */
    private function getInstallationStatistics($companyId, $days) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(CASE WHEN event_type = 'pwa_install_prompt_shown' THEN 1 END) as prompts_shown,
                COUNT(CASE WHEN event_type = 'pwa_install_accepted' THEN 1 END) as installs_accepted,
                COUNT(CASE WHEN event_type = 'pwa_install_dismissed' THEN 1 END) as installs_dismissed,
                COUNT(CASE WHEN event_type = 'pwa_installed' THEN 1 END) as installs_completed
            FROM pwa_analytics 
            WHERE company_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND event_type IN ('pwa_install_prompt_shown', 'pwa_install_accepted', 'pwa_install_dismissed', 'pwa_installed')
        ");
        
        $stmt->bind_param("ii", $companyId, $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stats = $result->fetch_assoc();
        
        // Calculate conversion rates
        $promptsShown = (int)$stats['prompts_shown'];
        $installsAccepted = (int)$stats['installs_accepted'];
        
        $stats['conversion_rate'] = $promptsShown > 0 ? 
            round(($installsAccepted / $promptsShown) * 100, 2) : 0;
        
        return $stats;
    }
    
    /**
     * Get offline usage statistics
     */
    private function getOfflineStatistics($userId, $companyId, $days) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(CASE WHEN event_type = 'offline_action_queued' THEN 1 END) as actions_queued,
                COUNT(CASE WHEN event_type = 'offline_action_synced' THEN 1 END) as actions_synced,
                COUNT(CASE WHEN event_type = 'offline_page_served' THEN 1 END) as offline_pages_served,
                COUNT(CASE WHEN event_type = 'cache_hit' THEN 1 END) as cache_hits,
                COUNT(CASE WHEN event_type = 'cache_miss' THEN 1 END) as cache_misses
            FROM pwa_analytics 
            WHERE user_id = ? AND company_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND event_type IN ('offline_action_queued', 'offline_action_synced', 'offline_page_served', 'cache_hit', 'cache_miss')
        ");
        
        $stmt->bind_param("iii", $userId, $companyId, $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stats = $result->fetch_assoc();
        
        // Calculate cache hit rate
        $cacheHits = (int)$stats['cache_hits'];
        $cacheMisses = (int)$stats['cache_misses'];
        $totalCacheRequests = $cacheHits + $cacheMisses;
        
        $stats['cache_hit_rate'] = $totalCacheRequests > 0 ? 
            round(($cacheHits / $totalCacheRequests) * 100, 2) : 0;
        
        return $stats;
    }
    
    /**
     * Get performance statistics
     */
    private function getPerformanceStatistics($userId, $companyId, $days) {
        $stmt = $this->db->prepare("
            SELECT 
                AVG(CAST(JSON_EXTRACT(event_data, '$.loadTime') AS DECIMAL(10,2))) as avg_load_time,
                AVG(CAST(JSON_EXTRACT(event_data, '$.cacheSize') AS DECIMAL(10,2))) as avg_cache_size,
                AVG(CAST(JSON_EXTRACT(event_data, '$.responseTime') AS DECIMAL(10,2))) as avg_response_time,
                AVG(CAST(JSON_EXTRACT(event_data, '$.hitRate') AS DECIMAL(10,4))) as avg_hit_rate,
                COUNT(CASE WHEN event_type = 'service_worker_update' THEN 1 END) as sw_updates,
                COUNT(CASE WHEN event_type = 'cache_optimization' THEN 1 END) as cache_optimizations,
                COUNT(CASE WHEN event_type = 'slow_resource' THEN 1 END) as slow_resources,
                COUNT(CASE WHEN event_type = 'slow_api_request' THEN 1 END) as slow_api_requests
            FROM pwa_analytics 
            WHERE user_id = ? AND company_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND (event_type IN ('page_load', 'service_worker_update', 'cache_optimization', 'slow_resource', 'slow_api_request', 'pwa_performance_metrics') 
                 OR JSON_EXTRACT(event_data, '$.loadTime') IS NOT NULL
                 OR JSON_EXTRACT(event_data, '$.responseTime') IS NOT NULL)
        ");
        
        $stmt->bind_param("iii", $userId, $companyId, $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stats = $result->fetch_assoc();
        
        // Add performance score calculation
        $stats['performance_score'] = $this->calculatePerformanceScore($stats);
        
        return $stats;
    }
    
    /**
     * Generate analytics summary
     */
    private function generateSummary($eventStats, $installationStats, $offlineStats) {
        $totalEvents = array_sum(array_column($eventStats, 'count'));
        
        return [
            'total_events' => $totalEvents,
            'install_conversion_rate' => $installationStats['conversion_rate'],
            'cache_hit_rate' => $offlineStats['cache_hit_rate'],
            'offline_actions_queued' => $offlineStats['actions_queued'],
            'offline_actions_synced' => $offlineStats['actions_synced']
        ];
    }
    
    /**
     * Calculate performance score based on metrics
     */
    private function calculatePerformanceScore($stats) {
        $score = 100; // Start with perfect score
        
        // Deduct points for slow load times
        $avgLoadTime = (float)$stats['avg_load_time'];
        if ($avgLoadTime > 3000) { // > 3 seconds
            $score -= 30;
        } elseif ($avgLoadTime > 2000) { // > 2 seconds
            $score -= 20;
        } elseif ($avgLoadTime > 1000) { // > 1 second
            $score -= 10;
        }
        
        // Deduct points for slow response times
        $avgResponseTime = (float)$stats['avg_response_time'];
        if ($avgResponseTime > 1000) { // > 1 second
            $score -= 20;
        } elseif ($avgResponseTime > 500) { // > 500ms
            $score -= 10;
        }
        
        // Deduct points for low cache hit rate
        $hitRate = (float)$stats['avg_hit_rate'];
        if ($hitRate < 0.5) { // < 50%
            $score -= 25;
        } elseif ($hitRate < 0.7) { // < 70%
            $score -= 15;
        } elseif ($hitRate < 0.8) { // < 80%
            $score -= 10;
        }
        
        // Deduct points for slow resources
        $slowResources = (int)$stats['slow_resources'];
        if ($slowResources > 10) {
            $score -= 15;
        } elseif ($slowResources > 5) {
            $score -= 10;
        } elseif ($slowResources > 0) {
            $score -= 5;
        }
        
        // Deduct points for slow API requests
        $slowApiRequests = (int)$stats['slow_api_requests'];
        if ($slowApiRequests > 5) {
            $score -= 15;
        } elseif ($slowApiRequests > 2) {
            $score -= 10;
        } elseif ($slowApiRequests > 0) {
            $score -= 5;
        }
        
        return max(0, min(100, $score)); // Ensure score is between 0-100
    }
    
    /**
     * Get performance insights and recommendations
     */
    public function getPerformanceInsights($userId, $companyId, $timeframe = '7d') {
        try {
            $days = $this->parseTimeframe($timeframe);
            $performanceStats = $this->getPerformanceStatistics($userId, $companyId, $days);
            
            $insights = [
                'score' => $performanceStats['performance_score'],
                'recommendations' => [],
                'metrics' => $performanceStats
            ];
            
            // Generate recommendations based on metrics
            if ($performanceStats['avg_load_time'] > 2000) {
                $insights['recommendations'][] = [
                    'type' => 'performance',
                    'priority' => 'high',
                    'title' => 'Optimize Page Load Time',
                    'description' => 'Average page load time is ' . round($performanceStats['avg_load_time']/1000, 2) . 's. Consider optimizing images, minifying CSS/JS, and leveraging browser caching.'
                ];
            }
            
            if ($performanceStats['avg_hit_rate'] < 0.7) {
                $insights['recommendations'][] = [
                    'type' => 'caching',
                    'priority' => 'medium',
                    'title' => 'Improve Cache Hit Rate',
                    'description' => 'Cache hit rate is ' . round($performanceStats['avg_hit_rate'] * 100, 1) . '%. Review caching strategies and consider preloading critical resources.'
                ];
            }
            
            if ($performanceStats['slow_resources'] > 5) {
                $insights['recommendations'][] = [
                    'type' => 'resources',
                    'priority' => 'medium',
                    'title' => 'Optimize Slow Resources',
                    'description' => $performanceStats['slow_resources'] . ' slow-loading resources detected. Consider lazy loading, compression, or CDN usage.'
                ];
            }
            
            if ($performanceStats['slow_api_requests'] > 2) {
                $insights['recommendations'][] = [
                    'type' => 'api',
                    'priority' => 'high',
                    'title' => 'Optimize API Performance',
                    'description' => $performanceStats['slow_api_requests'] . ' slow API requests detected. Review database queries and consider API caching.'
                ];
            }
            
            return $insights;
            
        } catch (Exception $e) {
            error_log("Failed to get performance insights: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get PWA adoption metrics for company
     */
    public function getCompanyPWAAdoption($companyId, $timeframe = '30d') {
        try {
            $days = $this->parseTimeframe($timeframe);
            
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(DISTINCT user_id) as total_users,
                    COUNT(DISTINCT CASE WHEN event_type = 'pwa_installed' THEN user_id END) as installed_users,
                    COUNT(DISTINCT CASE WHEN event_type LIKE 'offline_%' THEN user_id END) as offline_users,
                    COUNT(DISTINCT CASE WHEN event_type = 'push_notification_received' THEN user_id END) as notification_users
                FROM pwa_analytics 
                WHERE company_id = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            
            $stmt->bind_param("ii", $companyId, $days);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();
            
            $totalUsers = (int)$stats['total_users'];
            $installedUsers = (int)$stats['installed_users'];
            
            $stats['adoption_rate'] = $totalUsers > 0 ? 
                round(($installedUsers / $totalUsers) * 100, 2) : 0;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Failed to get PWA adoption metrics: " . $e->getMessage());
            throw $e;
        }
    }
}
?>
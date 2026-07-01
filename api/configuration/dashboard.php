<?php
/**
 * Configuration Dashboard API Endpoint
 * GET /api/configuration/dashboard.php - Get dashboard statistics
 * 
 * Query Parameters (GET):
 * - recent_activities_limit: Number of recent activities to include (default: 10, max: 50)
 * - include_summary: Include configuration summary for date range (optional)
 * - date_from: Start date for summary (Y-m-d format, required if include_summary=1)
 * - date_to: End date for summary (Y-m-d format, required if include_summary=1)
 * 
 * Response includes:
 * - router_stats: Total, configured, unconfigured, in-progress router counts
 * - ip_stats: Total, available, locked, configured IP counts
 * - locked_ips: Currently locked IPs with remaining time
 * - recent_activities: Recent configuration activities
 * - ip_utilization: IP utilization percentage
 * 
 * **Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/ConfigurationDashboardService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access for dashboard viewing
    $user = $authMiddleware->requireAdvUser();
    
    $dashboardService = new ConfigurationDashboardService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // GET: Get dashboard statistics
        handleGetRequest($dashboardService, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET']);
    }
    
} catch (Exception $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - Get dashboard statistics
 * 
 * Requirements: 7.1 - Display total routers count with breakdown by configuration status
 * Requirements: 7.2 - Display total IP_Master count with breakdown by status
 * Requirements: 7.3 - Display count of currently locked IPs with remaining lock time
 * Requirements: 7.4 - Display recent configuration activities with timestamps and users
 * Requirements: 7.5 - Update dashboard statistics in real-time
 */
function handleGetRequest($dashboardService, $authMiddleware, $user) {
    // Get query parameters
    $recentActivitiesLimit = min(50, max(1, (int)($_GET['recent_activities_limit'] ?? 10)));
    $includeSummary = isset($_GET['include_summary']) && $_GET['include_summary'] == '1';
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
    
    // Validate date formats if summary is requested
    if ($includeSummary) {
        if (empty($dateFrom) || empty($dateTo)) {
            ApiResponse::validationError(
                ['date_range' => ['date_from and date_to are required when include_summary=1']],
                'Missing date range for summary'
            );
        }
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            ApiResponse::validationError(
                ['date_from' => ['Invalid date format. Expected: Y-m-d (e.g., 2024-12-30)']],
                'Invalid date format'
            );
        }
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            ApiResponse::validationError(
                ['date_to' => ['Invalid date format. Expected: Y-m-d (e.g., 2024-12-30)']],
                'Invalid date format'
            );
        }
    }
    
    // Get dashboard data
    $dashboardData = $dashboardService->getDashboardData($recentActivitiesLimit);
    
    // Get IP utilization
    $ipUtilization = $dashboardService->getIPUtilization();
    
    // Format response
    $response = [
        'router_stats' => formatRouterStats($dashboardData['router_stats']),
        'ip_stats' => formatIPStats($dashboardData['ip_stats']),
        'locked_ips' => formatLockedIPs($dashboardData['locked_ips']),
        'recent_activities' => formatRecentActivities($dashboardData['recent_activities']),
        'ip_utilization' => $ipUtilization,
        'generated_at' => $dashboardData['generated_at']
    ];
    
    // Include summary if requested
    if ($includeSummary) {
        $response['configuration_summary'] = $dashboardService->getConfigurationSummary($dateFrom, $dateTo);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/dashboard', 'GET', [
        'recent_activities_limit' => $recentActivitiesLimit,
        'include_summary' => $includeSummary
    ]);
    
    ApiResponse::success($response, 'Dashboard data retrieved successfully');
}

/**
 * Format router statistics for API response
 * 
 * @param array $stats Raw router statistics
 * @return array Formatted router statistics
 */
function formatRouterStats($stats) {
    $total = (int)$stats['total'];
    $configured = (int)$stats['configured'];
    $unconfigured = (int)$stats['unconfigured'];
    $inProgress = (int)$stats['in_progress'];
    
    return [
        'total' => $total,
        'configured' => $configured,
        'unconfigured' => $unconfigured,
        'in_progress' => $inProgress,
        'percentages' => [
            'configured' => $total > 0 ? round(($configured / $total) * 100, 2) : 0,
            'unconfigured' => $total > 0 ? round(($unconfigured / $total) * 100, 2) : 0,
            'in_progress' => $total > 0 ? round(($inProgress / $total) * 100, 2) : 0
        ]
    ];
}

/**
 * Format IP statistics for API response
 * 
 * @param array $stats Raw IP statistics
 * @return array Formatted IP statistics
 */
function formatIPStats($stats) {
    $total = (int)$stats['total'];
    $available = (int)$stats['available'];
    $locked = (int)$stats['locked'];
    $configured = (int)$stats['configured'];
    
    return [
        'total' => $total,
        'available' => $available,
        'locked' => $locked,
        'configured' => $configured,
        'percentages' => [
            'available' => $total > 0 ? round(($available / $total) * 100, 2) : 0,
            'locked' => $total > 0 ? round(($locked / $total) * 100, 2) : 0,
            'configured' => $total > 0 ? round(($configured / $total) * 100, 2) : 0
        ]
    ];
}

/**
 * Format locked IPs for API response
 * 
 * @param array $lockedIPs Raw locked IPs data
 * @return array Formatted locked IPs
 */
function formatLockedIPs($lockedIPs) {
    return array_map(function($lock) {
        return [
            'lock_id' => (int)$lock['lock_id'],
            'ip_master_id' => (int)$lock['ip_master_id'],
            'ip_details' => [
                'network_ip' => $lock['network_ip'],
                'router_ip' => $lock['router_ip'],
                'site_ip' => $lock['site_ip'],
                'subnet_mask' => $lock['subnet_mask']
            ],
            'router_serial_number' => $lock['router_serial_number'],
            'locked_by' => [
                'user_id' => (int)$lock['locked_by'],
                'username' => $lock['locked_by_username']
            ],
            'locked_at' => $lock['locked_at'],
            'expires_at' => $lock['expires_at'],
            'remaining_time' => [
                'seconds' => (int)$lock['remaining_seconds'],
                'minutes' => (int)$lock['remaining_minutes'],
                'formatted' => $lock['remaining_formatted']
            ]
        ];
    }, $lockedIPs);
}

/**
 * Format recent activities for API response
 * 
 * @param array $activities Raw activities data
 * @return array Formatted activities
 */
function formatRecentActivities($activities) {
    return array_map(function($activity) {
        return [
            'id' => (int)$activity['id'],
            'action_type' => $activity['action_type'],
            'action_label' => $activity['action_label'] ?? null,
            'user' => [
                'id' => isset($activity['user_id']) ? (int)$activity['user_id'] : null,
                'username' => $activity['username'] ?? null
            ],
            'router_serial_number' => $activity['router_serial_number'] ?? null,
            'ip_master_id' => isset($activity['ip_master_id']) ? (int)$activity['ip_master_id'] : null,
            'ip_details' => [
                'network_ip' => $activity['network_ip'] ?? null,
                'router_ip' => $activity['router_ip'] ?? null,
                'site_ip' => $activity['site_ip'] ?? null,
                'subnet_mask' => $activity['subnet_mask'] ?? null
            ],
            'details' => $activity['details_decoded'] ?? [],
            'created_at' => $activity['created_at'] ?? null
        ];
    }, $activities);
}

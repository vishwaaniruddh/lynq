<?php
/**
 * Performance Monitoring Dashboard
 * Monitor system performance metrics
 * 
 * Requirements: 4.5, 7.4 - Performance monitoring
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!isAdvUser() || !can('system.manage')) {
    $_SESSION['flash_error'] = 'You do not have permission to access performance monitoring';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Performance Monitoring';
$currentPage = 'admin';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'System Admin', 'url' => 'index.php'],
    ['label' => 'Performance']
];

$adminService = new SystemAdminService();
$metrics = $adminService->getPerformanceMetrics();
$health = $adminService->getSystemHealth();

// Get API performance data
$db = Database::getInstance()->getConnection();
$apiStats = [];
try {
    // API requests by endpoint
    $stmt = $db->query("
        SELECT endpoint, method, COUNT(*) as count, AVG(response_time) as avg_time
        FROM api_access_log
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY endpoint, method
        ORDER BY count DESC
        LIMIT 10
    ");
    $apiStats['by_endpoint'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // API requests by hour
    $stmt = $db->query("
        SELECT HOUR(created_at) as hour, COUNT(*) as count
        FROM api_access_log
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ");
    $apiStats['by_hour'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Response time distribution
    $stmt = $db->query("
        SELECT 
            CASE 
                WHEN response_time < 100 THEN '< 100ms'
                WHEN response_time < 500 THEN '100-500ms'
                WHEN response_time < 1000 THEN '500ms-1s'
                ELSE '> 1s'
            END as range_label,
            COUNT(*) as count
        FROM api_access_log
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY range_label
        ORDER BY MIN(response_time)
    ");
    $apiStats['response_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

ob_start();
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Performance Monitoring</h1>
            <p class="text-gray-500 mt-1">Monitor system and API performance metrics</p>
        </div>
        <div class="mt-4 md:mt-0">
            <button onclick="location.reload()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">DB Query Time</p>
                    <p class="text-2xl font-bold <?php echo ($metrics['db_query_time'] ?? 0) < 100 ? 'text-green-600' : 'text-yellow-600'; ?> mt-1">
                        <?php echo $metrics['db_query_time'] ?? 0; ?>ms
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-database text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Memory Usage</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $metrics['php_memory_usage_formatted']; ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                    <i class="fas fa-memory text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">API Requests (1h)</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo number_format($metrics['api_requests_1h']); ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                    <i class="fas fa-exchange-alt text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Avg Response Time</p>
                    <p class="text-2xl font-bold <?php echo ($metrics['api_avg_response_time'] ?? 0) < 200 ? 'text-green-600' : 'text-yellow-600'; ?> mt-1">
                        <?php echo $metrics['api_avg_response_time']; ?>ms
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center">
                    <i class="fas fa-tachometer-alt text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Database Performance -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-database mr-2 text-blue-500"></i>Database Performance
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-600">Active Connections</span>
                        <span class="font-medium text-gray-800"><?php echo $metrics['db_connections'] ?? 'N/A'; ?></span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-600">Slow Queries</span>
                        <span class="font-medium <?php echo ($metrics['slow_queries'] ?? 0) > 0 ? 'text-yellow-600' : 'text-green-600'; ?>">
                            <?php echo $metrics['slow_queries'] ?? 0; ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-600">Database Size</span>
                        <span class="font-medium text-gray-800"><?php echo $health['checks']['database']['database_size'] ?? 'N/A'; ?></span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-gray-600">Response Time</span>
                        <span class="font-medium <?php echo ($health['checks']['database']['response_time_ms'] ?? 0) < 100 ? 'text-green-600' : 'text-yellow-600'; ?>">
                            <?php echo $health['checks']['database']['response_time_ms'] ?? 'N/A'; ?>ms
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Memory Performance -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-memory mr-2 text-purple-500"></i>Memory Performance
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-600">Current Usage</span>
                        <span class="font-medium text-gray-800"><?php echo $metrics['php_memory_usage_formatted']; ?></span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-600">Peak Usage</span>
                        <span class="font-medium text-gray-800"><?php echo $metrics['php_memory_peak_formatted']; ?></span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-600">Memory Limit</span>
                        <span class="font-medium text-gray-800"><?php echo $health['checks']['memory']['limit'] ?? 'N/A'; ?></span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-gray-600">Usage Percentage</span>
                        <span class="font-medium <?php echo ($health['checks']['memory']['used_percent'] ?? 0) < 70 ? 'text-green-600' : 'text-yellow-600'; ?>">
                            <?php echo $health['checks']['memory']['used_percent'] ?? 0; ?>%
                        </span>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="h-3 rounded-full <?php echo ($health['checks']['memory']['used_percent'] ?? 0) < 70 ? 'bg-green-500' : 'bg-yellow-500'; ?>" 
                             style="width: <?php echo $health['checks']['memory']['used_percent'] ?? 0; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- API Performance -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Endpoints -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-route mr-2 text-green-500"></i>Top API Endpoints (24h)
                </h3>
            </div>
            <div class="p-6">
                <?php if (!empty($apiStats['by_endpoint'])): ?>
                <div class="space-y-3">
                    <?php foreach ($apiStats['by_endpoint'] as $endpoint): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <span class="px-2 py-1 rounded text-xs font-medium 
                                <?php echo $endpoint['method'] === 'GET' ? 'bg-blue-100 text-blue-700' : 
                                    ($endpoint['method'] === 'POST' ? 'bg-green-100 text-green-700' : 
                                    ($endpoint['method'] === 'PUT' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700')); ?>">
                                <?php echo $endpoint['method']; ?>
                            </span>
                            <span class="text-sm text-gray-800 truncate max-w-xs"><?php echo htmlspecialchars($endpoint['endpoint']); ?></span>
                        </div>
                        <div class="text-right">
                            <span class="font-medium text-gray-800"><?php echo number_format($endpoint['count']); ?></span>
                            <span class="text-xs text-gray-500 ml-2"><?php echo round($endpoint['avg_time']); ?>ms</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-gray-500 text-center py-4">No API data available</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Response Time Distribution -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-chart-pie mr-2 text-orange-500"></i>Response Time Distribution (24h)
                </h3>
            </div>
            <div class="p-6">
                <?php if (!empty($apiStats['response_distribution'])): ?>
                <div class="space-y-4">
                    <?php 
                    $totalRequests = array_sum(array_column($apiStats['response_distribution'], 'count'));
                    foreach ($apiStats['response_distribution'] as $dist): 
                        $percentage = $totalRequests > 0 ? ($dist['count'] / $totalRequests * 100) : 0;
                    ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600"><?php echo $dist['range_label']; ?></span>
                            <span class="font-medium"><?php echo number_format($dist['count']); ?> (<?php echo round($percentage, 1); ?>%)</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="h-2 rounded-full <?php echo strpos($dist['range_label'], '< 100') !== false ? 'bg-green-500' : 
                                (strpos($dist['range_label'], '100-500') !== false ? 'bg-blue-500' : 
                                (strpos($dist['range_label'], '500ms-1s') !== false ? 'bg-yellow-500' : 'bg-red-500')); ?>" 
                                 style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-gray-500 text-center py-4">No response time data available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

<?php
/**
 * System Administration Dashboard
 * Main entry point for system administration tools
 * 
 * Requirements: 4.5, 7.4 - Audit trail and system administration
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Only ADV users with system.manage permission can access
if (!isAdvUser() || !can('system.manage')) {
    $_SESSION['flash_error'] = 'You do not have permission to access system administration';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'System Administration';
$currentPage = 'admin';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'System Administration']
];

$adminService = new SystemAdminService();

// Get system health
$health = $adminService->getSystemHealth();
$entityCounts = $adminService->getEntityCounts();
$performanceMetrics = $adminService->getPerformanceMetrics();

ob_start();
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">System Administration</h1>
            <p class="text-gray-500 mt-1">Monitor system health, manage backups, and configure settings</p>
        </div>
        <div class="mt-4 md:mt-0 flex space-x-3">
            <a href="health.php" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
                <i class="fas fa-heartbeat mr-2"></i>Health Details
            </a>
            <a href="backup.php" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition">
                <i class="fas fa-database mr-2"></i>Backups
            </a>
        </div>
    </div>

    <!-- System Status Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">System Status</p>
                    <p class="text-2xl font-bold mt-1 <?php echo $health['status'] === 'healthy' ? 'text-green-600' : ($health['status'] === 'warning' ? 'text-yellow-600' : 'text-red-600'); ?>">
                        <?php echo ucfirst($health['status']); ?>
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl <?php echo $health['status'] === 'healthy' ? 'bg-green-100' : ($health['status'] === 'warning' ? 'bg-yellow-100' : 'bg-red-100'); ?> flex items-center justify-center">
                    <i class="fas <?php echo $health['status'] === 'healthy' ? 'fa-check-circle text-green-600' : ($health['status'] === 'warning' ? 'fa-exclamation-triangle text-yellow-600' : 'fa-times-circle text-red-600'); ?> text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Active Users</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $entityCounts['active_users'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Active Sessions</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $entityCounts['active_sessions'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                    <i class="fas fa-desktop text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">DB Response</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $performanceMetrics['db_query_time'] ?? 0; ?>ms</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center">
                    <i class="fas fa-tachometer-alt text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Health Checks -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Health Checks -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Health Checks</h3>
            </div>
            <div class="p-6 space-y-4">
                <?php foreach ($health['checks'] as $name => $check): ?>
                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg <?php echo $check['status'] === 'healthy' ? 'bg-green-100' : ($check['status'] === 'warning' ? 'bg-yellow-100' : 'bg-red-100'); ?> flex items-center justify-center">
                            <i class="fas <?php echo $check['status'] === 'healthy' ? 'fa-check text-green-600' : ($check['status'] === 'warning' ? 'fa-exclamation text-yellow-600' : 'fa-times text-red-600'); ?>"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $name)); ?></p>
                            <p class="text-sm text-gray-500"><?php echo $check['message'] ?? ''; ?></p>
                        </div>
                    </div>
                    <span class="px-2 py-1 rounded text-xs font-medium <?php echo $check['status'] === 'healthy' ? 'bg-green-100 text-green-700' : ($check['status'] === 'warning' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                        <?php echo ucfirst($check['status']); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Quick Actions</h3>
            </div>
            <div class="p-6 grid grid-cols-2 gap-4">
                <a href="backup.php?action=create" class="flex flex-col items-center p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition">
                    <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center mb-3">
                        <i class="fas fa-download text-green-600 text-xl"></i>
                    </div>
                    <span class="font-medium text-gray-800">Create Backup</span>
                    <span class="text-xs text-gray-500 mt-1">Export database</span>
                </a>
                
                <a href="maintenance.php" class="flex flex-col items-center p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition">
                    <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center mb-3">
                        <i class="fas fa-broom text-blue-600 text-xl"></i>
                    </div>
                    <span class="font-medium text-gray-800">Cleanup</span>
                    <span class="text-xs text-gray-500 mt-1">Remove old data</span>
                </a>
                
                <a href="activity.php" class="flex flex-col items-center p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition">
                    <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center mb-3">
                        <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                    </div>
                    <span class="font-medium text-gray-800">Activity Report</span>
                    <span class="text-xs text-gray-500 mt-1">User activity</span>
                </a>
                
                <a href="config.php" class="flex flex-col items-center p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition">
                    <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center mb-3">
                        <i class="fas fa-cog text-orange-600 text-xl"></i>
                    </div>
                    <span class="font-medium text-gray-800">Configuration</span>
                    <span class="text-xs text-gray-500 mt-1">System settings</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Performance & Database Info -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Performance Metrics -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Performance Metrics</h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Memory Usage</span>
                        <span class="font-medium"><?php echo $performanceMetrics['php_memory_usage_formatted']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Peak Memory</span>
                        <span class="font-medium"><?php echo $performanceMetrics['php_memory_peak_formatted']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">DB Connections</span>
                        <span class="font-medium"><?php echo $performanceMetrics['db_connections'] ?? 'N/A'; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Slow Queries</span>
                        <span class="font-medium"><?php echo $performanceMetrics['slow_queries'] ?? 'N/A'; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">API Requests (1h)</span>
                        <span class="font-medium"><?php echo $performanceMetrics['api_requests_1h']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Avg Response Time</span>
                        <span class="font-medium"><?php echo $performanceMetrics['api_avg_response_time']; ?>ms</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Database Overview</h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Database Size</span>
                        <span class="font-medium"><?php echo $health['checks']['database']['database_size'] ?? 'N/A'; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Table Count</span>
                        <span class="font-medium"><?php echo $health['checks']['database']['table_count'] ?? 'N/A'; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Response Time</span>
                        <span class="font-medium"><?php echo $health['checks']['database']['response_time_ms'] ?? 'N/A'; ?>ms</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Active Sessions</span>
                        <span class="font-medium"><?php echo $health['checks']['sessions']['active_sessions'] ?? 'N/A'; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Expired Sessions</span>
                        <span class="font-medium"><?php echo $health['checks']['sessions']['expired_sessions'] ?? 'N/A'; ?></span>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t">
                    <a href="database.php" class="text-primary hover:underline text-sm">
                        <i class="fas fa-table mr-1"></i>View Table Details
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

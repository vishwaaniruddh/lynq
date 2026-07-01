<?php
/**
 * System Health Monitoring Dashboard
 * Detailed health status and monitoring
 * 
 * Requirements: 4.5, 7.4 - System monitoring
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!isAdvUser() || !can('system.manage')) {
    $_SESSION['flash_error'] = 'You do not have permission to access system health monitoring';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'System Health';
$currentPage = 'admin';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'System Admin', 'url' => 'index.php'],
    ['label' => 'Health Monitoring']
];

$adminService = new SystemAdminService();
$health = $adminService->getSystemHealth();
$config = $adminService->getSystemConfiguration();

ob_start();
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">System Health Monitoring</h1>
            <p class="text-gray-500 mt-1">Real-time system health status and diagnostics</p>
        </div>
        <div class="mt-4 md:mt-0">
            <button onclick="location.reload()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Overall Status -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center space-x-4">
            <div class="w-16 h-16 rounded-2xl <?php echo $health['status'] === 'healthy' ? 'bg-green-100' : ($health['status'] === 'warning' ? 'bg-yellow-100' : 'bg-red-100'); ?> flex items-center justify-center">
                <i class="fas <?php echo $health['status'] === 'healthy' ? 'fa-check-circle text-green-600' : ($health['status'] === 'warning' ? 'fa-exclamation-triangle text-yellow-600' : 'fa-times-circle text-red-600'); ?> text-3xl"></i>
            </div>
            <div>
                <h2 class="text-2xl font-bold <?php echo $health['status'] === 'healthy' ? 'text-green-600' : ($health['status'] === 'warning' ? 'text-yellow-600' : 'text-red-600'); ?>">
                    System <?php echo ucfirst($health['status']); ?>
                </h2>
                <p class="text-gray-500">Last checked: <?php echo $health['timestamp']; ?></p>
            </div>
        </div>
    </div>

    <!-- Detailed Health Checks -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Database Health -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-database mr-2 text-blue-500"></i>Database
                </h3>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $health['checks']['database']['status'] === 'healthy' ? 'bg-green-100 text-green-700' : ($health['checks']['database']['status'] === 'warning' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                    <?php echo ucfirst($health['checks']['database']['status']); ?>
                </span>
            </div>
            <div class="p-6 space-y-3">
                <?php if (isset($health['checks']['database']['response_time_ms'])): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600">Response Time</span>
                    <span class="font-medium"><?php echo $health['checks']['database']['response_time_ms']; ?>ms</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Database Size</span>
                    <span class="font-medium"><?php echo $health['checks']['database']['database_size']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Table Count</span>
                    <span class="font-medium"><?php echo $health['checks']['database']['table_count']; ?></span>
                </div>
                <?php endif; ?>
                <div class="pt-3 border-t">
                    <p class="text-sm text-gray-500"><?php echo $health['checks']['database']['message']; ?></p>
                </div>
            </div>
        </div>

        <!-- Session Health -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-user-clock mr-2 text-purple-500"></i>Sessions
                </h3>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $health['checks']['sessions']['status'] === 'healthy' ? 'bg-green-100 text-green-700' : ($health['checks']['sessions']['status'] === 'warning' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                    <?php echo ucfirst($health['checks']['sessions']['status']); ?>
                </span>
            </div>
            <div class="p-6 space-y-3">
                <?php if (isset($health['checks']['sessions']['active_sessions'])): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600">Active Sessions</span>
                    <span class="font-medium"><?php echo $health['checks']['sessions']['active_sessions']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Expired Sessions</span>
                    <span class="font-medium"><?php echo $health['checks']['sessions']['expired_sessions']; ?></span>
                </div>
                <?php endif; ?>
                <div class="pt-3 border-t">
                    <p class="text-sm text-gray-500"><?php echo $health['checks']['sessions']['message']; ?></p>
                </div>
            </div>
        </div>

        <!-- Disk Space -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-hdd mr-2 text-green-500"></i>Disk Space
                </h3>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $health['checks']['disk_space']['status'] === 'healthy' ? 'bg-green-100 text-green-700' : ($health['checks']['disk_space']['status'] === 'warning' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                    <?php echo ucfirst($health['checks']['disk_space']['status']); ?>
                </span>
            </div>
            <div class="p-6 space-y-3">
                <?php if (isset($health['checks']['disk_space']['total'])): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Space</span>
                    <span class="font-medium"><?php echo $health['checks']['disk_space']['total']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Free Space</span>
                    <span class="font-medium"><?php echo $health['checks']['disk_space']['free']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Used</span>
                    <span class="font-medium"><?php echo $health['checks']['disk_space']['used_percent']; ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                    <div class="h-2 rounded-full <?php echo $health['checks']['disk_space']['used_percent'] < 80 ? 'bg-green-500' : ($health['checks']['disk_space']['used_percent'] < 90 ? 'bg-yellow-500' : 'bg-red-500'); ?>" 
                         style="width: <?php echo $health['checks']['disk_space']['used_percent']; ?>%"></div>
                </div>
                <?php endif; ?>
                <div class="pt-3 border-t">
                    <p class="text-sm text-gray-500"><?php echo $health['checks']['disk_space']['message']; ?></p>
                </div>
            </div>
        </div>

        <!-- Memory Usage -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-memory mr-2 text-orange-500"></i>Memory
                </h3>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $health['checks']['memory']['status'] === 'healthy' ? 'bg-green-100 text-green-700' : ($health['checks']['memory']['status'] === 'warning' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                    <?php echo ucfirst($health['checks']['memory']['status']); ?>
                </span>
            </div>
            <div class="p-6 space-y-3">
                <?php if (isset($health['checks']['memory']['current'])): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600">Current Usage</span>
                    <span class="font-medium"><?php echo $health['checks']['memory']['current']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Peak Usage</span>
                    <span class="font-medium"><?php echo $health['checks']['memory']['peak']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Limit</span>
                    <span class="font-medium"><?php echo $health['checks']['memory']['limit']; ?></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                    <div class="h-2 rounded-full <?php echo $health['checks']['memory']['used_percent'] < 70 ? 'bg-green-500' : ($health['checks']['memory']['used_percent'] < 85 ? 'bg-yellow-500' : 'bg-red-500'); ?>" 
                         style="width: <?php echo $health['checks']['memory']['used_percent']; ?>%"></div>
                </div>
                <?php endif; ?>
                <div class="pt-3 border-t">
                    <p class="text-sm text-gray-500"><?php echo $health['checks']['memory']['message']; ?></p>
                </div>
            </div>
        </div>

        <!-- Security Status -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 md:col-span-2">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-shield-alt mr-2 text-red-500"></i>Security Status
                </h3>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $health['checks']['security']['status'] === 'healthy' ? 'bg-green-100 text-green-700' : ($health['checks']['security']['status'] === 'warning' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                    <?php echo ucfirst($health['checks']['security']['status']); ?>
                </span>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center p-4 bg-gray-50 rounded-xl">
                        <p class="text-3xl font-bold <?php echo ($health['checks']['security']['critical_events_24h'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                            <?php echo $health['checks']['security']['critical_events_24h'] ?? 0; ?>
                        </p>
                        <p class="text-gray-500 text-sm mt-1">Critical Events (24h)</p>
                    </div>
                    <div class="text-center p-4 bg-gray-50 rounded-xl">
                        <p class="text-3xl font-bold <?php echo ($health['checks']['security']['failed_logins_1h'] ?? 0) > 20 ? 'text-yellow-600' : 'text-green-600'; ?>">
                            <?php echo $health['checks']['security']['failed_logins_1h'] ?? 0; ?>
                        </p>
                        <p class="text-gray-500 text-sm mt-1">Failed Logins (1h)</p>
                    </div>
                    <div class="text-center p-4 bg-gray-50 rounded-xl">
                        <p class="text-sm text-gray-600"><?php echo $health['checks']['security']['message'] ?? 'N/A'; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PHP Configuration -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fab fa-php mr-2 text-indigo-500"></i>PHP Configuration
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm text-gray-500">PHP Version</p>
                    <p class="font-semibold text-gray-800"><?php echo $config['php']['version']; ?></p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm text-gray-500">Memory Limit</p>
                    <p class="font-semibold text-gray-800"><?php echo $config['php']['memory_limit']; ?></p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm text-gray-500">Max Execution Time</p>
                    <p class="font-semibold text-gray-800"><?php echo $config['php']['max_execution_time']; ?>s</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm text-gray-500">Upload Max Size</p>
                    <p class="font-semibold text-gray-800"><?php echo $config['php']['upload_max_filesize']; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

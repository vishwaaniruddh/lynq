<?php
/**
 * System Admin Dashboard
 * Central hub for system administration and monitoring
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Only ADV users can access
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to access system administration';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'System Admin';
$currentPage = 'system_admin';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'System'],
    ['label' => 'Admin']
];

// Get database info
$db = Database::getInstance();
$pdo = $db->getConnection();

// System info
$phpVersion = phpversion();
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown';
$serverTime = date('Y-m-d H:i:s');
$timezone = date_default_timezone_get();

// Memory info
$memoryUsage = memory_get_usage(true);
$memoryPeak = memory_get_peak_usage(true);
$memoryLimit = ini_get('memory_limit');

// Database info
try {
    $stmt = $pdo->query("SELECT VERSION() as version");
    $dbVersion = $stmt->fetch()['version'] ?? 'Unknown';
    
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $dbName = $stmt->fetch()['db_name'] ?? 'Unknown';
    
    // Get table count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()");
    $tableCount = $stmt->fetch()['count'] ?? 0;
    
    // Get database size
    $stmt = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
                         FROM information_schema.tables WHERE table_schema = DATABASE()");
    $dbSize = $stmt->fetch()['size_mb'] ?? 0;
} catch (Exception $e) {
    $dbVersion = 'Error';
    $dbName = 'Error';
    $tableCount = 0;
    $dbSize = 0;
}

// Get entity counts
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $activeUsers = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM companies WHERE status = 'active'");
    $activeCompanies = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sites");
    $totalSites = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
    $totalProducts = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $activeUsers = 0;
    $activeCompanies = 0;
    $totalSites = 0;
    $totalProducts = 0;
}

// Format bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

ob_start();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">System Administration</h1>
            <p class="text-gray-500 mt-1">Monitor and manage system resources</p>
        </div>
        <div class="mt-4 md:mt-0 flex gap-3">
            <a href="backup.php" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-database mr-2"></i>Backup
            </a>
            <button onclick="location.reload()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-5 shadow-sm border">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Active Users</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo number_format($activeUsers); ?></p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-blue-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Companies</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo number_format($activeCompanies); ?></p>
                </div>
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-building text-purple-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Total Sites</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo number_format($totalSites); ?></p>
                </div>
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-map-marker-alt text-green-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Products</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo number_format($totalProducts); ?></p>
                </div>
                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-box text-orange-600"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Server Information -->
        <div class="bg-white rounded-xl shadow-sm border">
            <div class="p-5 border-b flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Server Information</h3>
                <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Online</span>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="text-gray-600 text-sm">PHP Version</span>
                    <span class="font-medium text-sm"><?php echo $phpVersion; ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="text-gray-600 text-sm">Server Software</span>
                    <span class="font-medium text-sm truncate max-w-[200px]" title="<?php echo htmlspecialchars($serverSoftware); ?>"><?php echo htmlspecialchars(substr($serverSoftware, 0, 30)); ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="text-gray-600 text-sm">Server Time</span>
                    <span class="font-medium text-sm"><?php echo $serverTime; ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="text-gray-600 text-sm">Timezone</span>
                    <span class="font-medium text-sm"><?php echo $timezone; ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="text-gray-600 text-sm">Memory Usage</span>
                    <span class="font-medium text-sm"><?php echo formatBytes($memoryUsage); ?></span>
                </div>
                <div class="flex justify-between py-2">
                    <span class="text-gray-600 text-sm">Memory Limit</span>
                    <span class="font-medium text-sm"><?php echo $memoryLimit; ?></span>
                </div>
            </div>
        </div>

        <!-- Database Information -->
        <div class="bg-white rounded-xl shadow-sm border">
            <div class="p-5 border-b flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Database Information</h3>
                <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">MySQL</span>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="text-gray-600 text-sm">MySQL Version</span>
                    <span class="font-medium text-sm"><?php echo $dbVersion; ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="text-gray-600 text-sm">Database Name</span>
                    <span class="font-medium text-sm"><?php echo htmlspecialchars($dbName); ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="text-gray-600 text-sm">Total Tables</span>
                    <span class="font-medium text-sm"><?php echo $tableCount; ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-100">
                    <span class="text-gray-600 text-sm">Database Size</span>
                    <span class="font-medium text-sm"><?php echo $dbSize; ?> MB</span>
                </div>
                <div class="flex justify-between py-2">
                    <span class="text-gray-600 text-sm">Connection</span>
                    <span class="font-medium text-sm text-green-600"><i class="fas fa-check-circle mr-1"></i>Connected</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-sm border">
        <div class="p-5 border-b">
            <h3 class="font-semibold text-gray-800">Quick Actions</h3>
        </div>
        <div class="p-5 grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="backup.php" class="flex flex-col items-center p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mb-3">
                    <i class="fas fa-download text-green-600 text-xl"></i>
                </div>
                <span class="font-medium text-gray-800 text-sm">Create Backup</span>
            </a>
            <a href="../admin/maintenance.php" class="flex flex-col items-center p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition">
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-3">
                    <i class="fas fa-broom text-blue-600 text-xl"></i>
                </div>
                <span class="font-medium text-gray-800 text-sm">Cleanup</span>
            </a>
            <a href="../admin/activity.php" class="flex flex-col items-center p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition">
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-3">
                    <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                </div>
                <span class="font-medium text-gray-800 text-sm">Activity Log</span>
            </a>
            <a href="../admin/health.php" class="flex flex-col items-center p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition">
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mb-3">
                    <i class="fas fa-heartbeat text-red-600 text-xl"></i>
                </div>
                <span class="font-medium text-gray-800 text-sm">Health Check</span>
            </a>
        </div>
    </div>

    <!-- PHP Extensions -->
    <div class="bg-white rounded-xl shadow-sm border">
        <div class="p-5 border-b">
            <h3 class="font-semibold text-gray-800">PHP Extensions</h3>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                <?php
                $requiredExtensions = ['pdo', 'pdo_mysql', 'mysqli', 'json', 'mbstring', 'curl', 'gd', 'zip', 'fileinfo', 'openssl'];
                foreach ($requiredExtensions as $ext):
                    $loaded = extension_loaded($ext);
                ?>
                <div class="flex items-center p-2 rounded-lg <?php echo $loaded ? 'bg-green-50' : 'bg-red-50'; ?>">
                    <i class="fas <?php echo $loaded ? 'fa-check-circle text-green-600' : 'fa-times-circle text-red-600'; ?> mr-2"></i>
                    <span class="text-sm <?php echo $loaded ? 'text-green-700' : 'text-red-700'; ?>"><?php echo $ext; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/main.php';
?>

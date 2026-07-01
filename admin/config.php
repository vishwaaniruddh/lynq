<?php
/**
 * System Configuration Management Interface
 * View and manage system configuration settings
 * 
 * Requirements: 4.5, 7.4 - System configuration
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!isAdvUser() || !can('system.manage')) {
    $_SESSION['flash_error'] = 'You do not have permission to access system configuration';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'System Configuration';
$currentPage = 'admin';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'System Admin', 'url' => 'index.php'],
    ['label' => 'Configuration']
];

$adminService = new SystemAdminService();
$config = $adminService->getSystemConfiguration();

// Get loaded extensions
$extensions = get_loaded_extensions();
sort($extensions);

ob_start();
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">System Configuration</h1>
            <p class="text-gray-500 mt-1">View system settings and PHP configuration</p>
        </div>
    </div>

    <!-- Configuration Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- PHP Configuration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fab fa-php mr-2 text-indigo-500"></i>PHP Configuration
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <?php foreach ($config['php'] as $key => $value): ?>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                        <span class="text-gray-600"><?php echo ucwords(str_replace('_', ' ', $key)); ?></span>
                        <span class="font-medium text-gray-800 bg-gray-100 px-3 py-1 rounded"><?php echo htmlspecialchars($value); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Database Configuration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-database mr-2 text-blue-500"></i>Database Configuration
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <?php foreach ($config['database'] as $key => $value): ?>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                        <span class="text-gray-600"><?php echo ucwords(str_replace('_', ' ', $key)); ?></span>
                        <span class="font-medium text-gray-800 bg-gray-100 px-3 py-1 rounded"><?php echo htmlspecialchars($value); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Security Configuration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-shield-alt mr-2 text-red-500"></i>Security Configuration
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-600">Session Timeout</span>
                        <span class="font-medium text-gray-800 bg-gray-100 px-3 py-1 rounded">
                            <?php echo $config['security']['session_timeout']; ?>s (<?php echo round($config['security']['session_timeout'] / 60); ?> min)
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-600">Max Login Attempts</span>
                        <span class="font-medium text-gray-800 bg-gray-100 px-3 py-1 rounded"><?php echo $config['security']['max_login_attempts']; ?></span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-gray-600">Lockout Duration</span>
                        <span class="font-medium text-gray-800 bg-gray-100 px-3 py-1 rounded">
                            <?php echo $config['security']['lockout_duration']; ?>s (<?php echo round($config['security']['lockout_duration'] / 60); ?> min)
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-gray-600">Min Password Length</span>
                        <span class="font-medium text-gray-800 bg-gray-100 px-3 py-1 rounded"><?php echo $config['security']['password_min_length']; ?> characters</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Application Configuration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-cog mr-2 text-green-500"></i>Application Configuration
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <?php foreach ($config['application'] as $key => $value): ?>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                        <span class="text-gray-600"><?php echo ucwords(str_replace('_', ' ', $key)); ?></span>
                        <span class="font-medium text-gray-800 bg-gray-100 px-3 py-1 rounded text-sm"><?php echo htmlspecialchars($value); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Server Information -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-server mr-2 text-purple-500"></i>Server Information
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm text-gray-500">Server Software</p>
                    <p class="font-semibold text-gray-800 mt-1"><?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A'); ?></p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm text-gray-500">Server Name</p>
                    <p class="font-semibold text-gray-800 mt-1"><?php echo htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'N/A'); ?></p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm text-gray-500">Document Root</p>
                    <p class="font-semibold text-gray-800 mt-1 text-sm break-all"><?php echo htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'N/A'); ?></p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm text-gray-500">PHP SAPI</p>
                    <p class="font-semibold text-gray-800 mt-1"><?php echo php_sapi_name(); ?></p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm text-gray-500">Operating System</p>
                    <p class="font-semibold text-gray-800 mt-1"><?php echo PHP_OS; ?></p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm text-gray-500">Server Protocol</p>
                    <p class="font-semibold text-gray-800 mt-1"><?php echo htmlspecialchars($_SERVER['SERVER_PROTOCOL'] ?? 'N/A'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- PHP Extensions -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-puzzle-piece mr-2 text-orange-500"></i>Loaded PHP Extensions
            </h3>
            <span class="text-sm text-gray-500"><?php echo count($extensions); ?> extensions</span>
        </div>
        <div class="p-6">
            <div class="flex flex-wrap gap-2">
                <?php foreach ($extensions as $ext): ?>
                <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm"><?php echo htmlspecialchars($ext); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Important Extensions Check -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-check-double mr-2 text-green-500"></i>Required Extensions Status
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php
                $requiredExtensions = ['pdo', 'pdo_mysql', 'mysqli', 'json', 'mbstring', 'openssl', 'session', 'curl'];
                foreach ($requiredExtensions as $ext):
                    $loaded = extension_loaded($ext);
                ?>
                <div class="flex items-center space-x-2 p-3 rounded-lg <?php echo $loaded ? 'bg-green-50' : 'bg-red-50'; ?>">
                    <i class="fas <?php echo $loaded ? 'fa-check-circle text-green-500' : 'fa-times-circle text-red-500'; ?>"></i>
                    <span class="<?php echo $loaded ? 'text-green-700' : 'text-red-700'; ?>"><?php echo $ext; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

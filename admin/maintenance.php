<?php
/**
 * Database Maintenance Tools
 * Cleanup old data and optimize database
 * 
 * Requirements: 4.5, 7.4 - Database maintenance
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!isAdvUser() || !can('system.manage')) {
    $_SESSION['flash_error'] = 'You do not have permission to access maintenance tools';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Database Maintenance';
$currentPage = 'admin';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'System Admin', 'url' => 'index.php'],
    ['label' => 'Maintenance']
];

$adminService = new SystemAdminService();
$message = null;
$messageType = null;
$results = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'cleanup':
            $daysToKeep = (int)($_POST['days_to_keep'] ?? 90);
            $results = $adminService->cleanupOldData($daysToKeep);
            if ($results['status'] === 'success') {
                $message = "Cleanup completed successfully";
                $messageType = 'success';
            } else {
                $message = "Cleanup failed: " . ($results['message'] ?? 'Unknown error');
                $messageType = 'error';
            }
            break;
            
        case 'optimize':
            $results = $adminService->optimizeTables();
            $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
            $message = "Optimized {$successCount} tables";
            $messageType = 'success';
            break;
    }
}

// Get current stats
$db = Database::getInstance()->getConnection();
$stats = [];

try {
    $stmt = $db->query("SELECT COUNT(*) FROM user_sessions WHERE expires_at <= NOW()");
    $stats['expired_sessions'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stats['old_login_attempts'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM security_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) AND severity != 'CRITICAL'");
    $stats['old_security_events'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM api_access_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stats['old_api_logs'] = $stmt->fetchColumn();
} catch (Exception $e) {}

ob_start();
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Database Maintenance</h1>
            <p class="text-gray-500 mt-1">Clean up old data and optimize database performance</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
        <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Cleanup Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Expired Sessions</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo number_format($stats['expired_sessions'] ?? 0); ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-yellow-100 flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Old Login Attempts</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo number_format($stats['old_login_attempts'] ?? 0); ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center">
                    <i class="fas fa-sign-in-alt text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Old Security Events</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo number_format($stats['old_security_events'] ?? 0); ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                    <i class="fas fa-shield-alt text-red-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Old API Logs</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo number_format($stats['old_api_logs'] ?? 0); ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-code text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Cleanup Tool -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-broom mr-2 text-green-500"></i>Data Cleanup
                </h3>
                <p class="text-sm text-gray-500 mt-1">Remove old sessions, logs, and temporary data</p>
            </div>
            <div class="p-6">
                <form method="POST">
                    <input type="hidden" name="action" value="cleanup">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Keep data from last</label>
                        <select name="days_to_keep" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="30">30 days</option>
                            <option value="60">60 days</option>
                            <option value="90" selected>90 days</option>
                            <option value="180">180 days</option>
                            <option value="365">1 year</option>
                        </select>
                    </div>
                    
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-yellow-500 mt-0.5 mr-3"></i>
                            <div class="text-sm text-yellow-700">
                                <p class="font-medium">This will permanently delete:</p>
                                <ul class="list-disc list-inside mt-1">
                                    <li>Expired user sessions</li>
                                    <li>Old login attempt records</li>
                                    <li>Non-critical security events</li>
                                    <li>Old API access logs</li>
                                    <li>Old company access logs</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition"
                            onclick="return confirm('Are you sure you want to clean up old data? This cannot be undone.');">
                        <i class="fas fa-broom mr-2"></i>Run Cleanup
                    </button>
                </form>
                
                <?php if ($results && isset($_POST['action']) && $_POST['action'] === 'cleanup'): ?>
                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                    <h4 class="font-medium text-gray-800 mb-2">Cleanup Results:</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php foreach ($results as $key => $value): ?>
                        <?php if (is_array($value) && isset($value['deleted'])): ?>
                        <li><i class="fas fa-check text-green-500 mr-2"></i><?php echo ucwords(str_replace('_', ' ', $key)); ?>: <?php echo $value['deleted']; ?> deleted</li>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Optimize Tool -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-bolt mr-2 text-blue-500"></i>Table Optimization
                </h3>
                <p class="text-sm text-gray-500 mt-1">Optimize database tables for better performance</p>
            </div>
            <div class="p-6">
                <form method="POST">
                    <input type="hidden" name="action" value="optimize">
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-3"></i>
                            <div class="text-sm text-blue-700">
                                <p class="font-medium">Optimization will:</p>
                                <ul class="list-disc list-inside mt-1">
                                    <li>Reclaim unused space</li>
                                    <li>Defragment data files</li>
                                    <li>Update table statistics</li>
                                    <li>Improve query performance</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-bolt mr-2"></i>Optimize Tables
                    </button>
                </form>
                
                <?php if ($results && isset($_POST['action']) && $_POST['action'] === 'optimize'): ?>
                <div class="mt-4 p-4 bg-gray-50 rounded-lg max-h-64 overflow-y-auto">
                    <h4 class="font-medium text-gray-800 mb-2">Optimization Results:</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php foreach ($results as $table => $result): ?>
                        <li>
                            <i class="fas <?php echo $result['status'] === 'success' ? 'fa-check text-green-500' : 'fa-times text-red-500'; ?> mr-2"></i>
                            <?php echo htmlspecialchars($table); ?>: <?php echo $result['message']; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scheduled Maintenance Info -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-calendar-alt mr-2 text-purple-500"></i>Maintenance Recommendations
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="p-4 bg-gray-50 rounded-xl">
                    <h4 class="font-medium text-gray-800 mb-2">Daily</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Monitor system health</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Review security events</li>
                    </ul>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <h4 class="font-medium text-gray-800 mb-2">Weekly</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Create database backup</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Review user activity</li>
                    </ul>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <h4 class="font-medium text-gray-800 mb-2">Monthly</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Run data cleanup</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Optimize tables</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i>Review old backups</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

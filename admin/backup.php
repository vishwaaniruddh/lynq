<?php
/**
 * Database Backup and Maintenance Tools
 * Create, manage, and restore database backups
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
    $_SESSION['flash_error'] = 'You do not have permission to access backup tools';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Database Backup';
$currentPage = 'admin';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'System Admin', 'url' => 'index.php'],
    ['label' => 'Backup & Maintenance']
];

$adminService = new SystemAdminService();
$message = null;
$messageType = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $result = $adminService->createBackup();
            if ($result['status'] === 'success') {
                $message = "Backup created successfully: {$result['filename']} ({$result['size']})";
                $messageType = 'success';
            } else {
                $message = "Backup failed: " . ($result['message'] ?? 'Unknown error');
                $messageType = 'error';
            }
            break;
            
        case 'delete':
            $filename = $_POST['filename'] ?? '';
            if ($adminService->deleteBackup($filename)) {
                $message = "Backup deleted: {$filename}";
                $messageType = 'success';
            } else {
                $message = "Failed to delete backup";
                $messageType = 'error';
            }
            break;
            
        case 'download':
            $filename = $_GET['file'] ?? '';
            $backupDir = __DIR__ . '/../backups';
            $filepath = $backupDir . '/' . basename($filename);
            
            if (file_exists($filepath) && strpos($filename, 'backup_') === 0) {
                header('Content-Type: application/sql');
                header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
                header('Content-Length: ' . filesize($filepath));
                readfile($filepath);
                exit;
            }
            $message = "Backup file not found";
            $messageType = 'error';
            break;
    }
}

// Get backups list
$backups = $adminService->getBackups();
$tablesInfo = $adminService->getDatabaseTablesInfo();

ob_start();
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Database Backup & Maintenance</h1>
            <p class="text-gray-500 mt-1">Create backups and manage database maintenance</p>
        </div>
        <div class="mt-4 md:mt-0">
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="create">
                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition">
                    <i class="fas fa-plus mr-2"></i>Create Backup
                </button>
            </form>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
        <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Backup List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-archive mr-2 text-blue-500"></i>Available Backups
            </h3>
            <span class="text-sm text-gray-500"><?php echo count($backups); ?> backup(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Filename</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Size</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Created</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($backups)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-database text-4xl text-gray-300 mb-3"></i>
                                <p>No backups available</p>
                                <p class="text-sm mt-1">Create your first backup to get started</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($backups as $backup): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-3">
                                <i class="fas fa-file-code text-blue-500"></i>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($backup['filename']); ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo $backup['size']; ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo $backup['created_at']; ?></td>
                        <td class="px-6 py-4 text-right">
                            <a href="?action=download&file=<?php echo urlencode($backup['filename']); ?>" 
                               class="text-blue-500 hover:text-blue-700 mr-3">
                                <i class="fas fa-download"></i>
                            </a>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this backup?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Database Tables Info -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-table mr-2 text-purple-500"></i>Database Tables
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Table Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Rows</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Data Size</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Index Size</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Total Size</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($tablesInfo as $table): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-800"><?php echo htmlspecialchars($table['table_name']); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo number_format($table['table_rows'] ?? 0); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo $table['data_length_formatted']; ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo $table['index_length_formatted']; ?></td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-800"><?php echo $table['total_size_formatted']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

<?php
/**
 * System Backup Management
 * Create and manage database backups
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Only ADV users can access
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to access backup management';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Backup Management';
$currentPage = 'system_backup';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'System'],
    ['label' => 'Backup']
];

// Get backup directory
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Handle backup creation
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_backup') {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            // Get database name
            $stmt = $pdo->query("SELECT DATABASE() as db_name");
            $dbName = $stmt->fetch()['db_name'];
            
            $filename = 'backup_' . $dbName . '_' . date('Y-m-d_His') . '.sql';
            $filepath = $backupDir . '/' . $filename;
            
            // Get all tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $sql = "-- ADV CRM Database Backup\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Database: " . $dbName . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                // Get create table statement
                $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
                $row = $stmt->fetch();
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $row['Create Table'] . ";\n\n";
                
                // Get table data
                $stmt = $pdo->query("SELECT * FROM `$table`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($rows) > 0) {
                    $columns = array_keys($rows[0]);
                    $columnList = '`' . implode('`, `', $columns) . '`';
                    
                    foreach ($rows as $row) {
                        $values = array_map(function($val) use ($pdo) {
                            if ($val === null) return 'NULL';
                            return $pdo->quote($val);
                        }, array_values($row));
                        $sql .= "INSERT INTO `$table` ($columnList) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sql .= "\n";
                }
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            file_put_contents($filepath, $sql);
            
            $message = "Backup created successfully: $filename";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "Backup failed: " . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'delete_backup' && isset($_POST['filename'])) {
        $filename = basename($_POST['filename']);
        $filepath = $backupDir . '/' . $filename;
        
        if (file_exists($filepath) && pathinfo($filepath, PATHINFO_EXTENSION) === 'sql') {
            unlink($filepath);
            $message = "Backup deleted: $filename";
            $messageType = 'success';
        } else {
            $message = "Invalid backup file";
            $messageType = 'error';
        }
    }
}

// Get existing backups
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.sql');
    foreach ($files as $file) {
        $backups[] = [
            'filename' => basename($file),
            'size' => filesize($file),
            'created' => filemtime($file)
        ];
    }
    // Sort by date descending
    usort($backups, function($a, $b) {
        return $b['created'] - $a['created'];
    });
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

ob_start();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Backup Management</h1>
            <p class="text-gray-500 mt-1">Create and manage database backups</p>
        </div>
        <div class="mt-4 md:mt-0">
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="create_backup">
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
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

    <!-- Backup Info -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl p-5 shadow-sm border">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Total Backups</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo count($backups); ?></p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-database text-blue-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Total Size</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo formatFileSize(array_sum(array_column($backups, 'size'))); ?></p>
                </div>
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-hdd text-purple-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Last Backup</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">
                        <?php echo count($backups) > 0 ? date('M d, Y H:i', $backups[0]['created']) : 'Never'; ?>
                    </p>
                </div>
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-green-600"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Backup List -->
    <div class="bg-white rounded-xl shadow-sm border">
        <div class="p-5 border-b">
            <h3 class="font-semibold text-gray-800">Available Backups</h3>
        </div>
        
        <?php if (empty($backups)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-database text-4xl mb-3 text-gray-300"></i>
            <p>No backups found</p>
            <p class="text-sm mt-1">Create your first backup using the button above</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50/80">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Filename</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Size</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($backups as $index => $backup): ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-2.5 text-xs text-gray-500">#<?php echo $index + 1; ?></td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center">
                                <div class="w-7 h-7 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg flex items-center justify-center mr-2.5">
                                    <i class="fas fa-file-archive text-blue-500 text-xs"></i>
                                </div>
                                <span class="font-medium text-xs text-gray-800"><?php echo htmlspecialchars($backup['filename']); ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-gray-600"><?php echo formatFileSize($backup['size']); ?></td>
                        <td class="px-4 py-2.5 text-xs text-gray-600"><?php echo date('M d, Y H:i:s', $backup['created']); ?></td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center space-x-1">
                                <a href="../backups/<?php echo urlencode($backup['filename']); ?>" download 
                                   class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Download">
                                    <i class="fas fa-download text-xs"></i>
                                </a>
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this backup?');">
                                    <input type="hidden" name="action" value="delete_backup">
                                    <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5">
        <div class="flex items-start">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-info-circle text-blue-600"></i>
            </div>
            <div>
                <h4 class="font-semibold text-blue-800">Backup Information</h4>
                <ul class="mt-2 text-sm text-blue-700 space-y-1">
                    <li>• Backups are stored in the <code class="bg-blue-100 px-1 rounded">/backups</code> directory</li>
                    <li>• Each backup contains the complete database structure and data</li>
                    <li>• Download backups regularly and store them in a secure location</li>
                    <li>• Old backups should be deleted periodically to save disk space</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/main.php';
?>

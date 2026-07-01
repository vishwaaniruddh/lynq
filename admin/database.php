<?php
/**
 * Database Tables Information
 * View detailed database table information
 * 
 * Requirements: 4.5, 7.4 - Database monitoring
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!isAdvUser() || !can('system.manage')) {
    $_SESSION['flash_error'] = 'You do not have permission to access database information';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Database Tables';
$currentPage = 'admin';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'System Admin', 'url' => 'index.php'],
    ['label' => 'Database Tables']
];

$adminService = new SystemAdminService();
$tablesInfo = $adminService->getDatabaseTablesInfo();

// Calculate totals
$totalRows = 0;
$totalSize = 0;
foreach ($tablesInfo as $table) {
    $totalRows += $table['table_rows'] ?? 0;
    $totalSize += $table['total_size'] ?? 0;
}

ob_start();
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Database Tables</h1>
            <p class="text-gray-500 mt-1">Detailed information about database tables</p>
        </div>
        <div class="mt-4 md:mt-0">
            <a href="maintenance.php" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
                <i class="fas fa-tools mr-2"></i>Maintenance Tools
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Tables</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo count($tablesInfo); ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-table text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Rows</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo number_format($totalRows); ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                    <i class="fas fa-list text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Size</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $adminService->getSystemHealth()['checks']['database']['database_size'] ?? 'N/A'; ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                    <i class="fas fa-hdd text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-database mr-2 text-blue-500"></i>Table Details
                </h3>
                <input type="text" id="table-search" placeholder="Search tables..." 
                    class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary w-64">
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full" id="tables-table">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Table Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Rows</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Data Size</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Index Size</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Total Size</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($tablesInfo as $table): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-3">
                                <i class="fas fa-table text-gray-400"></i>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($table['table_name']); ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo number_format($table['table_rows'] ?? 0); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo $table['data_length_formatted']; ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo $table['index_length_formatted']; ?></td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-800"><?php echo $table['total_size_formatted']; ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            <?php echo $table['create_time'] ? date('M d, Y', strtotime($table['create_time'])) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            <?php echo $table['update_time'] ? date('M d, Y H:i', strtotime($table['update_time'])) : '-'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('table-search').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    document.querySelectorAll('#tables-table tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

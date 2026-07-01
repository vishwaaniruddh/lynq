<?php
/**
 * User Activity Reporting System
 * Comprehensive user activity tracking and reporting
 * 
 * Requirements: 4.5, 7.4 - Audit trail and reporting
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!isAdvUser() || !can('system.manage')) {
    $_SESSION['flash_error'] = 'You do not have permission to access activity reports';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'User Activity Report';
$currentPage = 'admin';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'System Admin', 'url' => 'index.php'],
    ['label' => 'Activity Report']
];

$adminService = new SystemAdminService();
$db = Database::getInstance()->getConnection();

// Get filter parameters
$filters = [
    'user_id' => $_GET['user_id'] ?? null,
    'action' => $_GET['action'] ?? null,
    'from_date' => $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days')),
    'to_date' => $_GET['to_date'] ?? date('Y-m-d'),
    'company_id' => $_GET['company_id'] ?? null
];

// Get activity data
$activities = $adminService->getUserActivityReport($filters, 200);
$stats = $adminService->getActivityStatistics(30);

// Get users and companies for filters
$users = [];
$companies = [];
try {
    $stmt = $db->query("SELECT id, username FROM users ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->query("SELECT id, name FROM companies ORDER BY name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

ob_start();
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">User Activity Report</h1>
            <p class="text-gray-500 mt-1">Track and analyze user activities across the system</p>
        </div>
        <div class="mt-4 md:mt-0">
            <button onclick="exportReport()" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition">
                <i class="fas fa-download mr-2"></i>Export CSV
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Activities (30d)</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo number_format($stats['total_activities'] ?? 0); ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-chart-bar text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Active Users</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo count($stats['most_active_users'] ?? []); ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                    <i class="fas fa-users text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Action Types</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo count($stats['by_action'] ?? []); ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                    <i class="fas fa-tasks text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Avg Daily</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">
                        <?php 
                        $avgDaily = count($stats['by_day'] ?? []) > 0 
                            ? round(($stats['total_activities'] ?? 0) / count($stats['by_day'])) 
                            : 0;
                        echo number_format($avgDaily);
                        ?>
                    </p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center">
                    <i class="fas fa-calendar-day text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                <select name="user_id" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $filters['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['username']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
                <select name="company_id" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Companies</option>
                    <?php foreach ($companies as $company): ?>
                    <option value="<?php echo $company['id']; ?>" <?php echo $filters['company_id'] == $company['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($company['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="from_date" value="<?php echo $filters['from_date']; ?>" 
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="to_date" value="<?php echo $filters['to_date']; ?>" 
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="w-full px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Activity by Action Type -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Activity by Type</h3>
            </div>
            <div class="p-6">
                <?php if (!empty($stats['by_action'])): ?>
                <div class="space-y-3">
                    <?php foreach (array_slice($stats['by_action'], 0, 8) as $action): ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600"><?php echo htmlspecialchars($action['action']); ?></span>
                        <span class="px-2 py-1 bg-gray-100 rounded text-sm font-medium"><?php echo number_format($action['count']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-gray-500 text-center py-4">No activity data</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Most Active Users -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Most Active Users</h3>
            </div>
            <div class="p-6">
                <?php if (!empty($stats['most_active_users'])): ?>
                <div class="space-y-3">
                    <?php foreach ($stats['most_active_users'] as $index => $user): ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <span class="w-6 h-6 rounded-full bg-primary/10 text-primary text-xs flex items-center justify-center font-medium">
                                <?php echo $index + 1; ?>
                            </span>
                            <span class="text-sm text-gray-800"><?php echo htmlspecialchars($user['username']); ?></span>
                        </div>
                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-sm font-medium">
                            <?php echo number_format($user['activity_count']); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-gray-500 text-center py-4">No user activity</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Daily Activity -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Daily Activity (Last 7 Days)</h3>
            </div>
            <div class="p-6">
                <?php 
                $recentDays = array_slice($stats['by_day'] ?? [], -7);
                if (!empty($recentDays)): 
                    $maxCount = max(array_column($recentDays, 'count'));
                ?>
                <div class="space-y-3">
                    <?php foreach ($recentDays as $day): ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600"><?php echo date('M d', strtotime($day['date'])); ?></span>
                            <span class="font-medium"><?php echo number_format($day['count']); ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-primary h-2 rounded-full" style="width: <?php echo $maxCount > 0 ? ($day['count'] / $maxCount * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-gray-500 text-center py-4">No daily data</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Activity Log Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Activity Log</h3>
            <span class="text-sm text-gray-500"><?php echo count($activities); ?> records</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full" id="activity-table">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Timestamp</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">User</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Company</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Action</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Performed By</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">IP Address</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($activities)): ?>
                    <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No activity records found</td></tr>
                    <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo date('M d, Y H:i', strtotime($activity['timestamp'])); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-800"><?php echo htmlspecialchars($activity['username'] ?? '-'); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($activity['company_name'] ?? '-'); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 rounded text-xs font-medium
                                <?php 
                                $action = strtolower($activity['action']);
                                echo strpos($action, 'create') !== false ? 'bg-green-100 text-green-700' : 
                                    (strpos($action, 'delete') !== false ? 'bg-red-100 text-red-700' : 
                                    (strpos($action, 'update') !== false ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700')); 
                                ?>">
                                <?php echo htmlspecialchars($activity['action']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($activity['performed_by_username'] ?? '-'); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($activity['ip_address'] ?? '-'); ?></td>
                        <td class="px-6 py-4">
                            <?php if (!empty($activity['details'])): ?>
                            <button onclick="showDetails('<?php echo htmlspecialchars(addslashes($activity['details'])); ?>')" 
                                class="text-primary hover:underline text-sm">View</button>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function showDetails(details) {
    try {
        const parsed = JSON.parse(details);
        const formatted = JSON.stringify(parsed, null, 2);
        openModal('Activity Details', `<pre class="bg-gray-100 p-4 rounded text-sm overflow-auto max-h-96">${formatted}</pre>`);
    } catch (e) {
        openModal('Activity Details', `<p class="text-gray-600">${details}</p>`);
    }
}

function exportReport() {
    const table = document.getElementById('activity-table');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach((col, index) => {
            if (index < 6) { // Skip details column
                rowData.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
            }
        });
        csv.push(rowData.join(','));
    });
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'activity_report_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

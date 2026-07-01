<?php
require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!can('system.audit') || !isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to view audit logs';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Audit Trail';
$currentPage = 'audit';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Audit Trail']
];

$db = Database::getInstance()->getConnection();
$userLogs = [];
$permissionLogs = [];

try {
    // Get user audit logs
    $stmt = $db->query("
        SELECT ual.*, u.username as target_user, p.username as performed_by_user
        FROM user_audit_log ual
        LEFT JOIN users u ON ual.user_id = u.id
        LEFT JOIN users p ON ual.performed_by = p.id
        ORDER BY ual.timestamp DESC
        LIMIT 100
    ");
    $userLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get permission audit logs
    $stmt = $db->query("
        SELECT pal.*, c.name as company_name, p.name as permission_name, u.username as performed_by_user
        FROM permission_audit_log pal
        LEFT JOIN companies c ON pal.company_id = c.id
        LEFT JOIN permissions p ON pal.permission_id = p.id
        LEFT JOIN users u ON pal.performed_by = u.id
        ORDER BY pal.timestamp DESC
        LIMIT 100
    ");
    $permissionLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error loading audit logs: ' . $e->getMessage();
}

ob_start();
?>

<div class="space-y-6">
    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="border-b">
            <nav class="flex -mb-px">
                <button onclick="showTab('user')" id="tab-user" 
                    class="tab-btn px-6 py-4 text-sm font-medium border-b-2 border-primary text-primary">
                    <i class="fas fa-users mr-2"></i>User Activity
                </button>
                <button onclick="showTab('permission')" id="tab-permission" 
                    class="tab-btn px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    <i class="fas fa-key mr-2"></i>Permission Changes
                </button>
            </nav>
        </div>
        
        <!-- User Activity Tab -->
        <div id="panel-user" class="tab-panel">
            <div class="p-4 border-b bg-gray-50">
                <input type="text" id="user-search" placeholder="Search user activity..." 
                    class="w-full md:w-64 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>
            <div class="overflow-x-auto">
                <table class="w-full" id="user-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Target User</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Performed By</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php if (empty($userLogs)): ?>
                        <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No activity logs found</td></tr>
                        <?php else: ?>
                        <?php foreach ($userLogs as $log): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo date('M d, Y H:i', strtotime($log['timestamp'])); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?php echo strpos($log['action'], 'created') !== false ? 'bg-green-100 text-green-700' : 
                                        (strpos($log['action'], 'deleted') !== false ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'); ?>">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-800"><?php echo htmlspecialchars($log['target_user'] ?? '-'); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($log['performed_by_user'] ?? '-'); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                            <td class="px-6 py-4">
                                <?php if ($log['details']): ?>
                                <button onclick="showDetails('<?php echo htmlspecialchars(addslashes($log['details'])); ?>')" 
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
        
        <!-- Permission Changes Tab -->
        <div id="panel-permission" class="tab-panel hidden">
            <div class="p-4 border-b bg-gray-50">
                <input type="text" id="permission-search" placeholder="Search permission changes..." 
                    class="w-full md:w-64 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>
            <div class="overflow-x-auto">
                <table class="w-full" id="permission-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Company</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Permission</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Performed By</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php if (empty($permissionLogs)): ?>
                        <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No permission changes found</td></tr>
                        <?php else: ?>
                        <?php foreach ($permissionLogs as $log): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo date('M d, Y H:i', strtotime($log['timestamp'])); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?php echo $log['action'] === 'granted' ? 'bg-green-100 text-green-700' : 
                                        ($log['action'] === 'revoked' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'); ?>">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-800"><?php echo htmlspecialchars($log['company_name'] ?? '-'); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($log['permission_name'] ?? 'Multiple'); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($log['performed_by_user'] ?? '-'); ?></td>
                            <td class="px-6 py-4">
                                <?php if ($log['details']): ?>
                                <button onclick="showDetails('<?php echo htmlspecialchars(addslashes($log['details'])); ?>')" 
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
</div>

<script>
function showTab(tab) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('border-primary', 'text-primary');
        b.classList.add('border-transparent', 'text-gray-500');
    });
    
    document.getElementById('panel-' + tab).classList.remove('hidden');
    const btn = document.getElementById('tab-' + tab);
    btn.classList.add('border-primary', 'text-primary');
    btn.classList.remove('border-transparent', 'text-gray-500');
}

function showDetails(details) {
    try {
        const parsed = JSON.parse(details);
        const formatted = JSON.stringify(parsed, null, 2);
        openModal('Details', `<pre class="bg-gray-100 p-4 rounded text-sm overflow-auto max-h-96">${formatted}</pre>`);
    } catch (e) {
        openModal('Details', `<p class="text-gray-600">${details}</p>`);
    }
}

// Search functionality
['user', 'permission'].forEach(type => {
    document.getElementById(type + '-search').addEventListener('input', function(e) {
        const search = e.target.value.toLowerCase();
        document.querySelectorAll('#' + type + '-table tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
        });
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

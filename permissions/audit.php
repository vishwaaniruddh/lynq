<?php
/**
 * Permission Audit Trail Page
 * Displays detailed audit logs for permission delegation activities
 * 
 * **Validates: Requirements 4.5**
 */

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
$pageTitle = 'Permission Audit Trail';
$currentPage = 'audit';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Permission Delegation', 'url' => 'delegate.php'],
    ['label' => 'Audit Trail']
];

$db = Database::getInstance()->getConnection();
$companies = [];
$auditLogs = [];
$selectedCompanyId = $_GET['company_id'] ?? null;
$selectedCompanyName = '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$actionFilter = $_GET['action'] ?? '';

// Statistics
$stats = [
    'total' => 0,
    'granted' => 0,
    'revoked' => 0,
    'bulk_updates' => 0
];

try {
    // Get contractor companies
    $stmt = $db->query("SELECT id, name FROM companies WHERE type = 'CONTRACTOR' ORDER BY name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build query for audit logs
    $sql = "
        SELECT pal.*, 
               c.name as company_name,
               p.name as permission_name, 
               p.module, 
               p.action as perm_action,
               u.username as performed_by_username, 
               u.first_name, 
               u.last_name
        FROM permission_audit_log pal
        LEFT JOIN companies c ON pal.company_id = c.id
        LEFT JOIN permissions p ON pal.permission_id = p.id
        LEFT JOIN users u ON pal.performed_by = u.id
        WHERE DATE(pal.timestamp) BETWEEN ? AND ?
    ";
    $params = [$dateFrom, $dateTo];
    
    if ($selectedCompanyId) {
        $sql .= " AND pal.company_id = ?";
        $params[] = $selectedCompanyId;
        
        // Get company name
        $stmt = $db->prepare("SELECT name FROM companies WHERE id = ?");
        $stmt->execute([$selectedCompanyId]);
        $companyRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $selectedCompanyName = $companyRow ? $companyRow['name'] : '';
    }
    
    if ($actionFilter) {
        $sql .= " AND pal.action LIKE ?";
        $params[] = "%$actionFilter%";
    }
    
    $sql .= " ORDER BY pal.timestamp DESC LIMIT 500";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    foreach ($auditLogs as $log) {
        $stats['total']++;
        if (strpos($log['action'], 'grant') !== false) {
            $stats['granted']++;
        } elseif (strpos($log['action'], 'revoke') !== false) {
            $stats['revoked']++;
        }
        if (strpos($log['action'], 'bulk') !== false) {
            $stats['bulk_updates']++;
        }
    }
    
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error loading audit logs: ' . $e->getMessage();
}

ob_start();
?>

<div class="space-y-6">
    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
                <select name="company_id" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Companies</option>
                    <?php foreach ($companies as $company): ?>
                    <option value="<?php echo $company['id']; ?>" <?php echo $selectedCompanyId == $company['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($company['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="date_from" value="<?php echo $dateFrom; ?>" 
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="date_to" value="<?php echo $dateTo; ?>" 
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Action Type</label>
                <select name="action" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Actions</option>
                    <option value="grant" <?php echo $actionFilter === 'grant' ? 'selected' : ''; ?>>Granted</option>
                    <option value="revoke" <?php echo $actionFilter === 'revoke' ? 'selected' : ''; ?>>Revoked</option>
                    <option value="bulk" <?php echo $actionFilter === 'bulk' ? 'selected' : ''; ?>>Bulk Updates</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
            </div>
        </form>
    </div>
    
    <!-- Statistics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-list text-blue-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></p>
                    <p class="text-sm text-gray-500">Total Records</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-plus text-green-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['granted']; ?></p>
                    <p class="text-sm text-gray-500">Permissions Granted</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-minus text-red-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['revoked']; ?></p>
                    <p class="text-sm text-gray-500">Permissions Revoked</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-layer-group text-purple-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['bulk_updates']; ?></p>
                    <p class="text-sm text-gray-500">Bulk Updates</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Audit Log Table -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">
                <?php if ($selectedCompanyName): ?>
                Audit Trail for <?php echo htmlspecialchars($selectedCompanyName); ?>
                <?php else: ?>
                All Permission Changes
                <?php endif; ?>
            </h3>
            <button onclick="exportAuditLog()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                <i class="fas fa-download mr-2"></i>Export CSV
            </button>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full" id="audit-table">
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
                    <?php if (empty($auditLogs)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-history text-4xl mb-3 text-gray-300"></i>
                            <p>No audit records found for the selected criteria</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($auditLogs as $log): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-600">
                            <?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php
                            $actionClass = 'bg-blue-100 text-blue-700';
                            $actionIcon = 'fa-edit';
                            if (strpos($log['action'], 'grant') !== false) {
                                $actionClass = 'bg-green-100 text-green-700';
                                $actionIcon = 'fa-plus';
                            } elseif (strpos($log['action'], 'revoke') !== false) {
                                $actionClass = 'bg-red-100 text-red-700';
                                $actionIcon = 'fa-minus';
                            }
                            ?>
                            <span class="px-2 py-1 rounded text-xs font-medium <?php echo $actionClass; ?>">
                                <i class="fas <?php echo $actionIcon; ?> mr-1"></i>
                                <?php echo htmlspecialchars(str_replace('_', ' ', $log['action'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-800">
                            <?php echo htmlspecialchars($log['company_name'] ?? '-'); ?>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <?php if ($log['permission_name']): ?>
                            <span class="text-gray-800"><?php echo htmlspecialchars($log['permission_name']); ?></span>
                            <span class="text-xs text-gray-500 block"><?php echo htmlspecialchars($log['module'] ?? ''); ?></span>
                            <?php else: ?>
                            <span class="text-gray-400 italic">Multiple/Bulk</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            <?php echo htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')); ?>
                            <span class="text-xs text-gray-400 block"><?php echo htmlspecialchars($log['performed_by_username'] ?? ''); ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($log['details']): ?>
                            <button onclick="showDetails('<?php echo htmlspecialchars(addslashes($log['details'])); ?>')" 
                                class="text-primary hover:underline text-sm">
                                <i class="fas fa-eye mr-1"></i>View
                            </button>
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
        
        <?php if (count($auditLogs) >= 500): ?>
        <div class="p-4 border-t bg-gray-50 text-center text-sm text-gray-500">
            Showing first 500 records. Use filters to narrow down results.
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function showDetails(details) {
    try {
        const parsed = JSON.parse(details);
        const formatted = JSON.stringify(parsed, null, 2);
        openModal('Audit Details', `<pre class="bg-gray-100 p-4 rounded text-sm overflow-auto max-h-96">${formatted}</pre>`);
    } catch (e) {
        openModal('Audit Details', `<p class="text-gray-600">${details}</p>`);
    }
}

function exportAuditLog() {
    const table = document.getElementById('audit-table');
    const rows = table.querySelectorAll('tbody tr');
    
    if (rows.length === 0 || (rows.length === 1 && rows[0].querySelector('td[colspan]'))) {
        alert('No data to export');
        return;
    }
    
    let csv = 'Timestamp,Action,Company,Permission,Performed By\n';
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 5) {
            const timestamp = cells[0].textContent.trim();
            const action = cells[1].textContent.trim();
            const company = cells[2].textContent.trim();
            const permission = cells[3].textContent.trim().replace(/\n/g, ' ');
            const performedBy = cells[4].textContent.trim().replace(/\n/g, ' ');
            
            csv += `"${timestamp}","${action}","${company}","${permission}","${performedBy}"\n`;
        }
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'permission_audit_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

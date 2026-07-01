<?php
/**
 * Permission Delegation Interface
 * Allows ADV users to delegate permissions to contractor companies
 * 
 * Features:
 * - Permission delegation form for ADV users
 * - Contractor company permission viewing
 * - Permission revocation functionality
 * - Bulk permission management tools
 * 
 * **Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5**
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!can('permissions.delegate') || !isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to delegate permissions';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Permission Delegation';
$currentPage = 'delegate';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Permission Delegation']
];

$db = Database::getInstance()->getConnection();
$permissionEngine = new PermissionEngine();
$companies = [];
$permissions = [];
$companyPermissions = [];
$selectedCompanyId = $_GET['company_id'] ?? null;
$selectedCompanyName = '';
$auditLogs = [];

try {
    // Get contractor companies with permission counts
    $stmt = $db->query("
        SELECT c.id, c.name, c.status, 
               COUNT(cp.id) as permission_count
        FROM companies c
        LEFT JOIN company_permissions cp ON c.id = cp.company_id AND cp.is_active = 1
        WHERE c.type = 'CONTRACTOR' AND c.status = 'ACTIVE'
        GROUP BY c.id, c.name, c.status
        ORDER BY c.name
    ");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all permissions (excluding ADV-only for delegation)
    $stmt = $db->query("SELECT * FROM permissions WHERE is_adv_only = 0 ORDER BY module, action");
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get delegated permissions for selected company
    if ($selectedCompanyId) {
        $stmt = $db->prepare("SELECT permission_id FROM company_permissions WHERE company_id = ? AND is_active = 1");
        $stmt->execute([$selectedCompanyId]);
        $companyPermissions = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_id');
        
        // Get company name
        $stmt = $db->prepare("SELECT name FROM companies WHERE id = ?");
        $stmt->execute([$selectedCompanyId]);
        $companyRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $selectedCompanyName = $companyRow ? $companyRow['name'] : '';
        
        // Get recent audit logs for this company
        $stmt = $db->prepare("
            SELECT pal.*, p.name as permission_name, p.module, p.action as perm_action,
                   u.username as performed_by_username, u.first_name, u.last_name
            FROM permission_audit_log pal
            LEFT JOIN permissions p ON pal.permission_id = p.id
            LEFT JOIN users u ON pal.performed_by = u.id
            WHERE pal.company_id = ?
            ORDER BY pal.timestamp DESC
            LIMIT 10
        ");
        $stmt->execute([$selectedCompanyId]);
        $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error loading data: ' . $e->getMessage();
}

// Handle form submission - Individual permission delegation/revocation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';
    $companyId = $_POST['company_id'] ?? null;
    
    if ($companyId) {
        try {
            $db->beginTransaction();
            
            if ($action === 'delegate_single') {
                // Single permission delegation
                $permissionId = $_POST['permission_id'] ?? null;
                if ($permissionId) {
                    // Check if already delegated
                    $stmt = $db->prepare("SELECT id FROM company_permissions WHERE company_id = ? AND permission_id = ? AND is_active = 1");
                    $stmt->execute([$companyId, $permissionId]);
                    if (!$stmt->fetch()) {
                        $stmt = $db->prepare("
                            INSERT INTO company_permissions (company_id, permission_id, granted_by, granted_at, is_active)
                            VALUES (?, ?, ?, NOW(), 1)
                        ");
                        $stmt->execute([$companyId, $permissionId, $currentUser['id']]);
                        
                        // Log audit
                        $stmt = $db->prepare("
                            INSERT INTO permission_audit_log (company_id, permission_id, action, performed_by, details)
                            VALUES (?, ?, 'granted', ?, ?)
                        ");
                        $stmt->execute([$companyId, $permissionId, $currentUser['id'], json_encode(['type' => 'single_delegation'])]);
                    }
                    $_SESSION['flash_success'] = 'Permission delegated successfully';
                }
            } elseif ($action === 'revoke_single') {
                // Single permission revocation
                $permissionId = $_POST['permission_id'] ?? null;
                if ($permissionId) {
                    $stmt = $db->prepare("
                        UPDATE company_permissions 
                        SET is_active = 0, revoked_by = ?, revoked_at = NOW() 
                        WHERE company_id = ? AND permission_id = ? AND is_active = 1
                    ");
                    $stmt->execute([$currentUser['id'], $companyId, $permissionId]);
                    
                    // Log audit
                    $stmt = $db->prepare("
                        INSERT INTO permission_audit_log (company_id, permission_id, action, performed_by, details)
                        VALUES (?, ?, 'revoked', ?, ?)
                    ");
                    $stmt->execute([$companyId, $permissionId, $currentUser['id'], json_encode(['type' => 'single_revocation'])]);
                    
                    $_SESSION['flash_success'] = 'Permission revoked successfully';
                }
            } elseif ($action === 'bulk_delegate') {
                // Bulk permission delegation by module
                $module = $_POST['module'] ?? null;
                if ($module) {
                    $stmt = $db->prepare("SELECT id FROM permissions WHERE module = ? AND is_adv_only = 0");
                    $stmt->execute([$module]);
                    $modulePermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $delegatedCount = 0;
                    foreach ($modulePermissions as $permId) {
                        $stmt = $db->prepare("SELECT id FROM company_permissions WHERE company_id = ? AND permission_id = ? AND is_active = 1");
                        $stmt->execute([$companyId, $permId]);
                        if (!$stmt->fetch()) {
                            $stmt = $db->prepare("
                                INSERT INTO company_permissions (company_id, permission_id, granted_by, granted_at, is_active)
                                VALUES (?, ?, ?, NOW(), 1)
                            ");
                            $stmt->execute([$companyId, $permId, $currentUser['id']]);
                            $delegatedCount++;
                        }
                    }
                    
                    // Log bulk audit
                    $stmt = $db->prepare("
                        INSERT INTO permission_audit_log (company_id, permission_id, action, performed_by, details)
                        VALUES (?, NULL, 'bulk_granted', ?, ?)
                    ");
                    $stmt->execute([$companyId, $currentUser['id'], json_encode(['module' => $module, 'count' => $delegatedCount])]);
                    
                    $_SESSION['flash_success'] = "Delegated $delegatedCount permissions from module '$module'";
                }
            } elseif ($action === 'bulk_revoke') {
                // Bulk permission revocation by module
                $module = $_POST['module'] ?? null;
                if ($module) {
                    $stmt = $db->prepare("SELECT id FROM permissions WHERE module = ? AND is_adv_only = 0");
                    $stmt->execute([$module]);
                    $modulePermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $revokedCount = 0;
                    foreach ($modulePermissions as $permId) {
                        $stmt = $db->prepare("
                            UPDATE company_permissions 
                            SET is_active = 0, revoked_by = ?, revoked_at = NOW() 
                            WHERE company_id = ? AND permission_id = ? AND is_active = 1
                        ");
                        $stmt->execute([$currentUser['id'], $companyId, $permId]);
                        if ($stmt->rowCount() > 0) {
                            $revokedCount++;
                        }
                    }
                    
                    // Log bulk audit
                    $stmt = $db->prepare("
                        INSERT INTO permission_audit_log (company_id, permission_id, action, performed_by, details)
                        VALUES (?, NULL, 'bulk_revoked', ?, ?)
                    ");
                    $stmt->execute([$companyId, $currentUser['id'], json_encode(['module' => $module, 'count' => $revokedCount])]);
                    
                    $_SESSION['flash_success'] = "Revoked $revokedCount permissions from module '$module'";
                }
            } elseif ($action === 'revoke_all') {
                // Revoke all permissions from company
                $stmt = $db->prepare("
                    UPDATE company_permissions 
                    SET is_active = 0, revoked_by = ?, revoked_at = NOW() 
                    WHERE company_id = ? AND is_active = 1
                ");
                $stmt->execute([$currentUser['id'], $companyId]);
                $revokedCount = $stmt->rowCount();
                
                // Log audit
                $stmt = $db->prepare("
                    INSERT INTO permission_audit_log (company_id, permission_id, action, performed_by, details)
                    VALUES (?, NULL, 'all_revoked', ?, ?)
                ");
                $stmt->execute([$companyId, $currentUser['id'], json_encode(['count' => $revokedCount])]);
                
                $_SESSION['flash_success'] = "Revoked all $revokedCount permissions from company";
            } else {
                // Original bulk update logic
                $selectedPermissions = $_POST['permissions'] ?? [];
                
                // Get current active permissions
                $stmt = $db->prepare("SELECT permission_id FROM company_permissions WHERE company_id = ? AND is_active = 1");
                $stmt->execute([$companyId]);
                $currentPermissions = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_id');
                
                // Deactivate all current permissions
                $stmt = $db->prepare("UPDATE company_permissions SET is_active = 0, revoked_by = ?, revoked_at = NOW() WHERE company_id = ? AND is_active = 1");
                $stmt->execute([$currentUser['id'], $companyId]);
                
                // Add new permissions
                $stmt = $db->prepare("
                    INSERT INTO company_permissions (company_id, permission_id, granted_by, granted_at, is_active)
                    VALUES (?, ?, ?, NOW(), 1)
                ");
                
                foreach ($selectedPermissions as $permId) {
                    $stmt->execute([$companyId, $permId, $currentUser['id']]);
                }
                
                // Log audit
                $added = array_diff($selectedPermissions, $currentPermissions);
                $removed = array_diff($currentPermissions, $selectedPermissions);
                
                if (!empty($added) || !empty($removed)) {
                    $stmt = $db->prepare("
                        INSERT INTO permission_audit_log (company_id, permission_id, action, performed_by, details)
                        VALUES (?, NULL, 'bulk_update', ?, ?)
                    ");
                    $stmt->execute([
                        $companyId,
                        $currentUser['id'],
                        json_encode(['added' => array_values($added), 'removed' => array_values($removed)])
                    ]);
                }
                
                $_SESSION['flash_success'] = 'Permissions updated successfully';
            }
            
            $db->commit();
            header('Location: delegate.php?company_id=' . $companyId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = 'Error updating permissions: ' . $e->getMessage();
        }
    }
}

ob_start();
?>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Company Selection -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b">
                <h3 class="font-semibold text-gray-800">Select Company</h3>
                <p class="text-xs text-gray-500 mt-1">Choose a contractor to manage</p>
            </div>
            <div class="p-4">
                <?php if (empty($companies)): ?>
                <p class="text-gray-500 text-sm">No contractor companies found</p>
                <?php else: ?>
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    <?php foreach ($companies as $company): ?>
                    <a href="?company_id=<?php echo $company['id']; ?>" 
                       class="block px-4 py-3 rounded-lg transition <?php echo $selectedCompanyId == $company['id'] ? 'bg-primary text-white' : 'bg-gray-50 hover:bg-gray-100 text-gray-700'; ?>">
                        <div class="flex items-center justify-between">
                            <span><i class="fas fa-building mr-2"></i><?php echo htmlspecialchars($company['name']); ?></span>
                            <span class="text-xs px-2 py-1 rounded-full <?php echo $selectedCompanyId == $company['id'] ? 'bg-white/20' : 'bg-gray-200'; ?>">
                                <?php echo $company['permission_count']; ?>
                            </span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <?php if ($selectedCompanyId): ?>
        <div class="bg-white rounded-xl shadow-sm mt-4">
            <div class="p-4 border-b">
                <h3 class="font-semibold text-gray-800">Quick Actions</h3>
            </div>
            <div class="p-4 space-y-2">
                <a href="audit.php?company_id=<?php echo $selectedCompanyId; ?>" 
                   class="block w-full px-4 py-2 text-center bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700 transition">
                    <i class="fas fa-history mr-2"></i>View Full Audit Trail
                </a>
                <button onclick="confirmRevokeAll()" 
                    class="w-full px-4 py-2 bg-red-100 hover:bg-red-200 rounded-lg text-red-700 transition">
                    <i class="fas fa-ban mr-2"></i>Revoke All Permissions
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Permission Management -->
    <div class="lg:col-span-3">
        <?php if ($selectedCompanyId): ?>
        <!-- Company Header -->
        <div class="bg-white rounded-xl shadow-sm mb-4">
            <div class="p-6 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($selectedCompanyName); ?></h2>
                    <p class="text-sm text-gray-500">
                        <span class="text-primary font-medium"><?php echo count($companyPermissions); ?></span> permissions delegated
                    </p>
                </div>
                <div class="flex gap-2">
                    <button onclick="selectAll()" class="px-3 py-1 text-sm bg-green-100 text-green-700 rounded hover:bg-green-200 transition">
                        <i class="fas fa-check-double mr-1"></i>Select All
                    </button>
                    <button onclick="deselectAll()" class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition">
                        <i class="fas fa-times mr-1"></i>Clear All
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="border-b">
                <nav class="flex -mb-px">
                    <button onclick="showTab('permissions')" id="tab-permissions" 
                        class="tab-btn px-6 py-4 text-sm font-medium border-b-2 border-primary text-primary">
                        <i class="fas fa-key mr-2"></i>Permissions
                    </button>
                    <button onclick="showTab('bulk')" id="tab-bulk" 
                        class="tab-btn px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                        <i class="fas fa-layer-group mr-2"></i>Bulk Actions
                    </button>
                    <button onclick="showTab('audit')" id="tab-audit" 
                        class="tab-btn px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                        <i class="fas fa-history mr-2"></i>Recent Activity
                    </button>
                </nav>
            </div>
            
            <!-- Permissions Tab -->
            <div id="panel-permissions" class="tab-panel">
                <form method="POST" id="permissions-form">
                    <input type="hidden" name="company_id" value="<?php echo $selectedCompanyId; ?>">
                    <input type="hidden" name="action" value="update">
                    
                    <div class="p-4 border-b bg-gray-50 flex items-center justify-between">
                        <div>
                            <input type="text" id="permission-search" placeholder="Search permissions..." 
                                class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary w-64">
                        </div>
                        <span class="text-sm text-gray-500">
                            <span id="selected-count"><?php echo count($companyPermissions); ?></span> permissions selected
                        </span>
                    </div>
                    
                    <?php
                    $modules = [];
                    foreach ($permissions as $perm) {
                        $modules[$perm['module']][] = $perm;
                    }
                    ?>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($modules as $module => $perms): ?>
                            <div class="bg-gray-50 rounded-lg p-4 permission-module" data-module="<?php echo $module; ?>">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-semibold text-gray-700 capitalize flex items-center">
                                        <i class="fas fa-folder mr-2 text-gray-400"></i><?php echo $module; ?>
                                    </h4>
                                    <button type="button" onclick="toggleModule('<?php echo $module; ?>')" 
                                        class="text-xs text-primary hover:underline">Toggle All</button>
                                </div>
                                <div class="space-y-2">
                                    <?php foreach ($perms as $perm): ?>
                                    <label class="flex items-center cursor-pointer permission-item" data-name="<?php echo htmlspecialchars($perm['name']); ?>">
                                        <input type="checkbox" name="permissions[]" value="<?php echo $perm['id']; ?>"
                                            class="permission-checkbox module-<?php echo $module; ?> rounded border-gray-300 text-primary focus:ring-primary"
                                            <?php echo in_array($perm['id'], $companyPermissions) ? 'checked' : ''; ?>
                                            onchange="updateCount()">
                                        <span class="ml-2 text-sm text-gray-600"><?php echo htmlspecialchars($perm['action']); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="p-6 pt-0 border-t bg-gray-50 flex justify-end">
                        <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                            <i class="fas fa-save mr-2"></i>Save Permissions
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Bulk Actions Tab -->
            <div id="panel-bulk" class="tab-panel hidden">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Bulk Permission Management</h3>
                    <p class="text-sm text-gray-500 mb-6">Quickly delegate or revoke permissions by module</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach (array_keys($modules) as $module): ?>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-700 capitalize mb-3">
                                <i class="fas fa-folder mr-2 text-gray-400"></i><?php echo $module; ?>
                            </h4>
                            <p class="text-xs text-gray-500 mb-3"><?php echo count($modules[$module]); ?> permissions</p>
                            <div class="flex gap-2">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="company_id" value="<?php echo $selectedCompanyId; ?>">
                                    <input type="hidden" name="action" value="bulk_delegate">
                                    <input type="hidden" name="module" value="<?php echo $module; ?>">
                                    <button type="submit" class="px-3 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200 transition">
                                        <i class="fas fa-plus mr-1"></i>Delegate All
                                    </button>
                                </form>
                                <form method="POST" class="inline" onsubmit="return confirm('Revoke all <?php echo $module; ?> permissions?')">
                                    <input type="hidden" name="company_id" value="<?php echo $selectedCompanyId; ?>">
                                    <input type="hidden" name="action" value="bulk_revoke">
                                    <input type="hidden" name="module" value="<?php echo $module; ?>">
                                    <button type="submit" class="px-3 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200 transition">
                                        <i class="fas fa-minus mr-1"></i>Revoke All
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Audit Tab -->
            <div id="panel-audit" class="tab-panel hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Recent Permission Changes</h3>
                        <a href="audit.php?company_id=<?php echo $selectedCompanyId; ?>" class="text-sm text-primary hover:underline">
                            View Full History <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    
                    <?php if (empty($auditLogs)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-history text-4xl mb-3 text-gray-300"></i>
                        <p>No permission changes recorded yet</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($auditLogs as $log): ?>
                        <div class="flex items-start gap-4 p-3 bg-gray-50 rounded-lg">
                            <div class="flex-shrink-0">
                                <?php if (strpos($log['action'], 'grant') !== false): ?>
                                <span class="w-8 h-8 flex items-center justify-center bg-green-100 text-green-600 rounded-full">
                                    <i class="fas fa-plus text-sm"></i>
                                </span>
                                <?php elseif (strpos($log['action'], 'revoke') !== false): ?>
                                <span class="w-8 h-8 flex items-center justify-center bg-red-100 text-red-600 rounded-full">
                                    <i class="fas fa-minus text-sm"></i>
                                </span>
                                <?php else: ?>
                                <span class="w-8 h-8 flex items-center justify-center bg-blue-100 text-blue-600 rounded-full">
                                    <i class="fas fa-edit text-sm"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow">
                                <p class="text-sm text-gray-800">
                                    <span class="font-medium"><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></span>
                                    <?php echo htmlspecialchars($log['action']); ?>
                                    <?php if ($log['permission_name']): ?>
                                    <span class="font-medium text-primary"><?php echo htmlspecialchars($log['permission_name']); ?></span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php echo date('M d, Y H:i', strtotime($log['timestamp'])); ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- No Company Selected -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-12 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full mx-auto flex items-center justify-center mb-4">
                    <i class="fas fa-hand-pointer text-2xl text-gray-400"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Select a Company</h3>
                <p class="text-gray-500">Choose a contractor company from the left panel to manage their permissions</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Revoke All Confirmation Form (hidden) -->
<form id="revoke-all-form" method="POST" style="display: none;">
    <input type="hidden" name="company_id" value="<?php echo $selectedCompanyId; ?>">
    <input type="hidden" name="action" value="revoke_all">
</form>

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

function selectAll() {
    document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = true);
    updateCount();
}

function deselectAll() {
    document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = false);
    updateCount();
}

function toggleModule(module) {
    const checkboxes = document.querySelectorAll('.module-' + module);
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
    updateCount();
}

function updateCount() {
    const count = document.querySelectorAll('.permission-checkbox:checked').length;
    document.getElementById('selected-count').textContent = count;
}

function confirmRevokeAll() {
    if (confirm('Are you sure you want to revoke ALL permissions from this company? This action cannot be undone.')) {
        document.getElementById('revoke-all-form').submit();
    }
}

// Permission search
document.getElementById('permission-search')?.addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    document.querySelectorAll('.permission-item').forEach(item => {
        const name = item.dataset.name.toLowerCase();
        item.style.display = name.includes(search) ? '' : 'none';
    });
    
    // Hide empty modules
    document.querySelectorAll('.permission-module').forEach(module => {
        const visibleItems = module.querySelectorAll('.permission-item:not([style*="display: none"])');
        module.style.display = visibleItems.length > 0 ? '' : 'none';
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

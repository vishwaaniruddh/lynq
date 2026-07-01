<?php
/**
 * Permission Viewing Page
 * Allows users to view their company's delegated permissions
 * Contractors can see what permissions have been delegated to their company
 * 
 * **Validates: Requirements 4.2**
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'My Company Permissions';
$currentPage = 'permissions';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'My Permissions']
];

$db = Database::getInstance()->getConnection();
$permissionEngine = new PermissionEngine();
$companyPermissions = [];
$userPermissions = [];
$companyInfo = null;

try {
    // Get company info
    $stmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$currentUser['company_id']]);
    $companyInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user's direct permissions from role
    $stmt = $db->prepare("
        SELECT p.*, 'role' as source
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE rp.role_id = ?
        ORDER BY p.module, p.action
    ");
    $stmt->execute([$currentUser['role_id']]);
    $userPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For contractor users, get company delegated permissions
    if ($currentUser['company_type'] === 'CONTRACTOR') {
        $stmt = $db->prepare("
            SELECT p.*, cp.granted_at, cp.granted_by,
                   u.first_name as granted_by_first, u.last_name as granted_by_last
            FROM permissions p
            INNER JOIN company_permissions cp ON p.id = cp.permission_id
            LEFT JOIN users u ON cp.granted_by = u.id
            WHERE cp.company_id = ? AND cp.is_active = 1
            ORDER BY p.module, p.action
        ");
        $stmt->execute([$currentUser['company_id']]);
        $companyPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error loading permissions: ' . $e->getMessage();
}

// Group permissions by module
function groupByModule($permissions) {
    $grouped = [];
    foreach ($permissions as $perm) {
        $grouped[$perm['module']][] = $perm;
    }
    return $grouped;
}

$userPermissionsGrouped = groupByModule($userPermissions);
$companyPermissionsGrouped = groupByModule($companyPermissions);

ob_start();
?>

<div class="space-y-6">
    <!-- Company Info Card -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-16 h-16 bg-primary/10 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-building text-2xl text-primary"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($companyInfo['name'] ?? 'Unknown'); ?></h2>
                    <p class="text-sm text-gray-500">
                        <span class="px-2 py-1 rounded text-xs font-medium <?php echo $currentUser['company_type'] === 'ADV' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'; ?>">
                            <?php echo $currentUser['company_type']; ?>
                        </span>
                        <span class="ml-2">Role: <?php echo htmlspecialchars($currentUser['role_name']); ?></span>
                    </p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-3xl font-bold text-primary"><?php echo count($userPermissions) + count($companyPermissions); ?></p>
                <p class="text-sm text-gray-500">Total Permissions</p>
            </div>
        </div>
    </div>
    
    <!-- Permission Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-user-shield text-green-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo count($userPermissions); ?></p>
                    <p class="text-sm text-gray-500">Role Permissions</p>
                </div>
            </div>
        </div>
        <?php if ($currentUser['company_type'] === 'CONTRACTOR'): ?>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-share-alt text-blue-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo count($companyPermissions); ?></p>
                    <p class="text-sm text-gray-500">Delegated Permissions</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-folder text-purple-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?php echo count($companyPermissionsGrouped); ?></p>
                    <p class="text-sm text-gray-500">Modules Accessible</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="border-b">
            <nav class="flex -mb-px">
                <button onclick="showTab('role')" id="tab-role" 
                    class="tab-btn px-6 py-4 text-sm font-medium border-b-2 border-primary text-primary">
                    <i class="fas fa-user-shield mr-2"></i>Role Permissions (<?php echo count($userPermissions); ?>)
                </button>
                <?php if ($currentUser['company_type'] === 'CONTRACTOR'): ?>
                <button onclick="showTab('delegated')" id="tab-delegated" 
                    class="tab-btn px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    <i class="fas fa-share-alt mr-2"></i>Delegated Permissions (<?php echo count($companyPermissions); ?>)
                </button>
                <?php endif; ?>
            </nav>
        </div>
        
        <!-- Role Permissions Tab -->
        <div id="panel-role" class="tab-panel p-6">
            <p class="text-sm text-gray-500 mb-4">
                These permissions are granted through your role: <strong><?php echo htmlspecialchars($currentUser['role_name']); ?></strong>
            </p>
            
            <?php if (empty($userPermissionsGrouped)): ?>
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-key text-4xl mb-3 text-gray-300"></i>
                <p>No role permissions assigned</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($userPermissionsGrouped as $module => $perms): ?>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-semibold text-gray-700 capitalize mb-3 flex items-center">
                        <i class="fas fa-folder mr-2 text-gray-400"></i><?php echo $module; ?>
                        <span class="ml-auto text-xs bg-gray-200 px-2 py-1 rounded"><?php echo count($perms); ?></span>
                    </h4>
                    <div class="space-y-2">
                        <?php foreach ($perms as $perm): ?>
                        <div class="flex items-center text-sm">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span class="text-gray-600"><?php echo htmlspecialchars($perm['action']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Delegated Permissions Tab -->
        <?php if ($currentUser['company_type'] === 'CONTRACTOR'): ?>
        <div id="panel-delegated" class="tab-panel hidden p-6">
            <p class="text-sm text-gray-500 mb-4">
                These permissions have been delegated to your company by ADV administrators
            </p>
            
            <?php if (empty($companyPermissionsGrouped)): ?>
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-share-alt text-4xl mb-3 text-gray-300"></i>
                <p>No permissions have been delegated to your company yet</p>
                <p class="text-xs mt-2">Contact your ADV administrator to request access</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($companyPermissionsGrouped as $module => $perms): ?>
                <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
                    <h4 class="font-semibold text-gray-700 capitalize mb-3 flex items-center">
                        <i class="fas fa-folder mr-2 text-blue-400"></i><?php echo $module; ?>
                        <span class="ml-auto text-xs bg-blue-200 px-2 py-1 rounded"><?php echo count($perms); ?></span>
                    </h4>
                    <div class="space-y-2">
                        <?php foreach ($perms as $perm): ?>
                        <div class="text-sm">
                            <div class="flex items-center">
                                <i class="fas fa-share text-blue-500 mr-2"></i>
                                <span class="text-gray-600"><?php echo htmlspecialchars($perm['action']); ?></span>
                            </div>
                            <p class="text-xs text-gray-400 ml-5 mt-1">
                                Granted <?php echo date('M d, Y', strtotime($perm['granted_at'])); ?>
                                by <?php echo htmlspecialchars(($perm['granted_by_first'] ?? '') . ' ' . ($perm['granted_by_last'] ?? '')); ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

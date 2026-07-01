<?php
require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!can('users.read')) {
    $_SESSION['flash_error'] = 'You do not have permission to view users';
    header('Location: ../dashboard.php');
    exit;
}

$userId = $_GET['id'] ?? null;
if (!$userId) {
    header('Location: index.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'View User';
$currentPage = 'users';
$isLoggedIn = true;

$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->prepare("
        SELECT u.*, c.name as company_name, c.type as company_type, r.name as role_name 
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['flash_error'] = 'User not found';
        header('Location: index.php');
        exit;
    }
    
    // Check company access
    if (!isAdvUser() && $user['company_id'] != $currentUser['company_id']) {
        $_SESSION['flash_error'] = 'You do not have permission to view this user';
        header('Location: index.php');
        exit;
    }
    
    // Get user permissions
    $stmt = $db->prepare("
        SELECT p.name, p.module, p.action 
        FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE rp.role_id = ?
        ORDER BY p.module, p.action
    ");
    $stmt->execute([$user['role_id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent activity
    $stmt = $db->prepare("
        SELECT * FROM user_audit_log 
        WHERE target_user_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get managed LHOs for this user (Requirements: 3.1, 3.2, 3.3)
    $managedLhos = [];
    if ($user['company_type'] === 'ADV') {
        $locationService = new LocationService();
        $managedLhos = $locationService->getLhosByManager($userId);
    }
    
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error loading user: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Users', 'url' => 'index.php'],
    ['label' => $user['username']]
];

ob_start();
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- User Info Card -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 text-center border-b">
                <div class="w-24 h-24 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full mx-auto flex items-center justify-center mb-4">
                    <span class="text-4xl font-bold text-white"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                </div>
                <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($user['username']); ?></h3>
                <p class="text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                <div class="mt-3">
                    <?php if ($user['status'] == 1): ?>
                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm">Active</span>
                    <?php else: ?>
                    <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm">Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="p-6 space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-500">Company</span>
                    <span class="font-medium"><?php echo htmlspecialchars($user['company_name']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Company Type</span>
                    <span class="px-2 py-1 <?php echo $user['company_type'] === 'ADV' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'; ?> rounded text-xs">
                        <?php echo $user['company_type']; ?>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Role</span>
                    <span class="font-medium"><?php echo htmlspecialchars($user['role_name']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Created</span>
                    <span class="font-medium"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Last Updated</span>
                    <span class="font-medium"><?php echo date('M d, Y', strtotime($user['updated_at'])); ?></span>
                </div>
            </div>
            
            <?php if (can('users.update')): ?>
            <div class="p-6 border-t">
                <a href="edit.php?id=<?php echo $user['id']; ?>" 
                   class="block w-full text-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-edit mr-2"></i>Edit User
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Details -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Permissions -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-key mr-2 text-gray-400"></i>Permissions
                </h3>
            </div>
            <div class="p-6">
                <?php if (empty($permissions)): ?>
                <p class="text-gray-500 text-center py-4">No permissions assigned</p>
                <?php else: ?>
                <div class="flex flex-wrap gap-2">
                    <?php 
                    $modules = [];
                    foreach ($permissions as $perm) {
                        $modules[$perm['module']][] = $perm['action'];
                    }
                    foreach ($modules as $module => $actions): ?>
                    <div class="bg-gray-50 rounded-lg p-3 flex-1 min-w-[200px]">
                        <h4 class="font-medium text-gray-700 mb-2 capitalize"><?php echo $module; ?></h4>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($actions as $action): ?>
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs"><?php echo $action; ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($user['company_type'] === 'ADV'): ?>
        <!-- Managed LHOs - Requirements: 3.1, 3.2, 3.3 -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-building mr-2 text-gray-400"></i>Managed LHOs
                </h3>
            </div>
            <div class="p-6">
                <?php if (empty($managedLhos)): ?>
                <!-- Requirements: 3.3 - Display appropriate indicator when user manages no LHOs -->
                <p class="text-gray-500 text-center py-4">No LHOs assigned to this user</p>
                <?php else: ?>
                <!-- Requirements: 3.1, 3.2 - Display list of LHOs the user manages -->
                <div class="space-y-3">
                    <?php foreach ($managedLhos as $lho): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-building text-blue-600"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($lho['lho_name']); ?></p>
                                <p class="text-xs text-gray-500">
                                    <?php echo $lho['lho_status'] === 'active' ? 
                                        '<span class="text-green-600">Active</span>' : 
                                        '<span class="text-red-600">Inactive</span>'; ?>
                                </p>
                            </div>
                        </div>
                        <a href="../masters/lhos.php" class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-sm text-gray-500 mt-4">
                    Total: <?php echo count($managedLhos); ?> LHO<?php echo count($managedLhos) !== 1 ? 's' : ''; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Activity Log -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-history mr-2 text-gray-400"></i>Recent Activity
                </h3>
            </div>
            <div class="p-6">
                <?php if (empty($activity)): ?>
                <p class="text-gray-500 text-center py-4">No activity recorded</p>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($activity as $log): ?>
                    <div class="flex items-start space-x-3 pb-4 border-b last:border-0">
                        <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-clock text-gray-500 text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm text-gray-800"><?php echo htmlspecialchars($log['action']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo date('M d, Y H:i', strtotime($log['timestamp'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

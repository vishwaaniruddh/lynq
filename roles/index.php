<?php
require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!can('roles.read')) {
    $_SESSION['flash_error'] = 'You do not have permission to view roles';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Role Management';
$currentPage = 'roles';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Roles']
];

$db = Database::getInstance()->getConnection();
$roles = [];

try {
    $stmt = $db->query("
        SELECT r.*, 
               (SELECT COUNT(*) FROM users WHERE role_id = r.id) as user_count,
               (SELECT COUNT(*) FROM role_permissions WHERE role_id = r.id) as permission_count
        FROM roles r 
        ORDER BY r.company_type, r.level
    ");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error loading roles: ' . $e->getMessage();
}

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">All Roles</h3>
            <p class="text-sm text-gray-500">Manage system roles and their permissions</p>
        </div>
        <?php if (can('roles.create') && isAdvUser()): ?>
        <a href="create.php" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
            <i class="fas fa-plus mr-2"></i>Add Role
        </a>
        <?php endif; ?>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
        <!-- ADV Roles -->
        <div>
            <h4 class="text-sm font-semibold text-gray-500 uppercase mb-4">
                <i class="fas fa-crown mr-2 text-blue-500"></i>ADV Roles
            </h4>
            <div class="space-y-3">
                <?php foreach ($roles as $role): ?>
                <?php if ($role['company_type'] === 'ADV'): ?>
                <div class="bg-blue-50 border border-blue-100 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h5 class="font-semibold text-gray-800"><?php echo htmlspecialchars($role['name']); ?></h5>
                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">Level <?php echo $role['level']; ?></span>
                    </div>
                    <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($role['description'] ?? 'No description'); ?></p>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">
                            <i class="fas fa-users mr-1"></i><?php echo $role['user_count']; ?> users
                            <span class="mx-2">•</span>
                            <i class="fas fa-key mr-1"></i><?php echo $role['permission_count']; ?> permissions
                        </span>
                        <?php if (can('roles.update') && isAdvUser()): ?>
                        <a href="edit.php?id=<?php echo $role['id']; ?>" class="text-blue-600 hover:underline">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Contractor Roles -->
        <div>
            <h4 class="text-sm font-semibold text-gray-500 uppercase mb-4">
                <i class="fas fa-building mr-2 text-gray-500"></i>Contractor Roles
            </h4>
            <div class="space-y-3">
                <?php foreach ($roles as $role): ?>
                <?php if ($role['company_type'] === 'CONTRACTOR'): ?>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h5 class="font-semibold text-gray-800"><?php echo htmlspecialchars($role['name']); ?></h5>
                        <span class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs">Level <?php echo $role['level']; ?></span>
                    </div>
                    <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($role['description'] ?? 'No description'); ?></p>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">
                            <i class="fas fa-users mr-1"></i><?php echo $role['user_count']; ?> users
                            <span class="mx-2">•</span>
                            <i class="fas fa-key mr-1"></i><?php echo $role['permission_count']; ?> permissions
                        </span>
                        <?php if (can('roles.update') && isAdvUser()): ?>
                        <a href="edit.php?id=<?php echo $role['id']; ?>" class="text-blue-600 hover:underline">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

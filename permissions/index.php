<?php
/**
 * Permissions Index Page
 * Lists all permissions in the system
 */

require_once __DIR__ . '/../config/autoload.php';

// Initialize services
$sessionService = new SessionService();
$authService = new AuthenticationService();

// Check authentication
if (!$sessionService->isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

// Check permission
if (!can('permissions.read')) {
    header('Location: ../dashboard.php?error=access_denied');
    exit;
}

// Get current user
$currentUserId = $sessionService->getCurrentUserId();
$userModel = new User();
$currentUser = $userModel->findWithRelations($currentUserId);

if (!$currentUser) {
    header('Location: ../logout.php');
    exit;
}

// Get permissions grouped by module
$permissionModel = new Permission();
$permissionsGrouped = $permissionModel->findGroupedByModule();

// Page setup
$pageTitle = 'Permissions - ADV CRM';
$currentPage = 'permissions';
$baseUrl = '..';
$isLoggedIn = true;
$menuService = new MenuService();

ob_start();
?>

<div class="max-w-7xl mx-auto">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Permissions</h1>
            <p class="text-gray-600 mt-1">View all system permissions</p>
        </div>
        <?php if (isAdvUser()): ?>
        <div class="mt-4 md:mt-0">
            <a href="delegate.php" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                <i class="fas fa-share-alt mr-2"></i>
                Delegate Permissions
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Permissions by Module -->
    <?php foreach ($permissionsGrouped as $module => $permissions): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900 capitalize">
                <i class="fas fa-folder mr-2 text-primary"></i>
                <?php echo htmlspecialchars(str_replace('_', ' ', $module)); ?>
            </h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($permissions as $permission): ?>
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-medium text-gray-900">
                                <?php echo htmlspecialchars($permission['name']); ?>
                            </p>
                            <p class="text-sm text-gray-500 mt-1">
                                <?php echo htmlspecialchars($permission['description'] ?? 'No description'); ?>
                            </p>
                        </div>
                        <?php if ($permission['is_adv_only']): ?>
                        <span class="px-2 py-1 text-xs bg-primary/20 text-primary rounded-full">
                            ADV Only
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3 flex items-center text-xs text-gray-400">
                        <span class="mr-3">
                            <i class="fas fa-cube mr-1"></i>
                            Module: <?php echo htmlspecialchars($permission['module']); ?>
                        </span>
                        <span>
                            <i class="fas fa-bolt mr-1"></i>
                            Action: <?php echo htmlspecialchars($permission['action']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Permission Legend -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Permission Legend</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex items-center">
                <span class="px-2 py-1 text-xs bg-primary/20 text-primary rounded-full mr-3">ADV Only</span>
                <span class="text-sm text-gray-600">Permission is restricted to ADV users only</span>
            </div>
            <div class="flex items-center">
                <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full mr-3">Delegatable</span>
                <span class="text-sm text-gray-600">Permission can be delegated to contractor companies</span>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

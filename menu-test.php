<?php
/**
 * Menu Testing Interface
 * Allows testing and verification of menu visibility rules
 * 
 * **Feature: adv-crm-users-module, Property 10: Permission-Based Menu Visibility**
 * **Validates: Requirements 6.1, 6.3, 6.4, 6.5**
 */

require_once __DIR__ . '/config/autoload.php';

// Initialize services
$sessionService = new SessionService();
$authService = new AuthenticationService();

// Check authentication
if (!$sessionService->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Get current user
$currentUserId = $sessionService->getCurrentUserId();
$userModel = new User();
$currentUser = $userModel->findWithRelations($currentUserId);

if (!$currentUser) {
    header('Location: logout.php');
    exit;
}

// Only ADV users can access this testing interface
if ($currentUser['company_type'] !== 'ADV') {
    header('Location: dashboard.php');
    exit;
}

// Initialize MenuService
$menuService = new MenuService();

// Get all users for testing
$userRepository = new UserRepository();
$allUsers = $userRepository->findAll();

// Handle test user selection
$testUserId = isset($_GET['test_user']) ? (int)$_GET['test_user'] : $currentUserId;
$testUser = $userModel->findWithRelations($testUserId);

// Get visible menus for test user
$visibleMenus = $testUser ? $menuService->getVisibleMenus($testUserId) : [];
$allMenuItems = $menuService->getAllMenuItems();

// Page setup
$pageTitle = 'Menu Visibility Testing - ADV CRM';
$currentPage = 'menu_test';
$baseUrl = '';
$isLoggedIn = true;

ob_start();
?>

<div class="max-w-7xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Menu Visibility Testing</h1>
        <p class="text-gray-600 mt-1">Test and verify menu visibility rules for different user types</p>
    </div>
    
    <!-- User Selection -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Select Test User</h2>
        <form method="GET" class="flex items-end gap-4">
            <div class="flex-1">
                <label for="test_user" class="block text-sm font-medium text-gray-700 mb-1">User</label>
                <select name="test_user" id="test_user" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    <?php foreach ($allUsers as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $user['id'] == $testUserId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['username']); ?> 
                        (<?php echo htmlspecialchars($user['company_name'] ?? 'Unknown'); ?> - 
                        <?php echo htmlspecialchars($user['company_type'] ?? 'Unknown'); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                Test User
            </button>
        </form>
    </div>
    
    <?php if ($testUser): ?>
    <!-- Test User Info -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Test User Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="text-sm text-gray-500">Username</p>
                <p class="font-medium"><?php echo htmlspecialchars($testUser['username']); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Company</p>
                <p class="font-medium"><?php echo htmlspecialchars($testUser['company_name'] ?? 'Unknown'); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Company Type</p>
                <p class="font-medium">
                    <?php if ($testUser['company_type'] === 'ADV'): ?>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/20 text-primary">
                        <i class="fas fa-shield-alt mr-1"></i> ADV
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-500/20 text-green-600">
                        <i class="fas fa-building mr-1"></i> CONTRACTOR
                    </span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Role</p>
                <p class="font-medium"><?php echo htmlspecialchars($testUser['role_name'] ?? 'Unknown'); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Role Level</p>
                <p class="font-medium"><?php echo htmlspecialchars($testUser['role_level'] ?? 'Unknown'); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Status</p>
                <p class="font-medium">
                    <?php if ($testUser['status'] == 1): ?>
                    <span class="text-green-600">Active</span>
                    <?php else: ?>
                    <span class="text-red-600">Inactive</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Menu Visibility Results -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Visible Menus -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                Visible Menus
            </h2>
            
            <?php if (empty($visibleMenus)): ?>
            <p class="text-gray-500">No menus visible for this user.</p>
            <?php else: ?>
            
            <?php foreach ($visibleMenus as $section => $items): ?>
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-2">
                    <?php echo ucfirst(str_replace('_', ' ', $section)); ?>
                </h3>
                <div class="space-y-2">
                    <?php foreach ($items as $item): ?>
                    <div class="flex items-center p-2 bg-green-50 rounded-lg">
                        <i class="fas <?php echo htmlspecialchars($item['icon']); ?> w-5 text-green-600 mr-3"></i>
                        <div class="flex-1">
                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($item['label']); ?></p>
                            <p class="text-xs text-gray-500">
                                Permission: <?php echo htmlspecialchars($item['permission'] ?? 'None required'); ?>
                            </p>
                        </div>
                        <?php if ($item['adv_only']): ?>
                        <span class="px-2 py-1 text-xs bg-primary/20 text-primary rounded">ADV Only</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php endif; ?>
        </div>
        
        <!-- Hidden Menus -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-times-circle text-red-500 mr-2"></i>
                Hidden Menus
            </h2>
            
            <?php
            // Get all visible menu IDs
            $visibleIds = [];
            foreach ($visibleMenus as $items) {
                foreach ($items as $item) {
                    $visibleIds[] = $item['id'];
                }
            }
            
            // Find hidden menus
            $hiddenMenus = array_filter($allMenuItems, function($item) use ($visibleIds) {
                return !in_array($item['id'], $visibleIds);
            });
            
            // Group hidden menus by section
            $hiddenBySection = [];
            foreach ($hiddenMenus as $item) {
                $hiddenBySection[$item['section']][] = $item;
            }
            ?>
            
            <?php if (empty($hiddenMenus)): ?>
            <p class="text-gray-500">All menus are visible for this user.</p>
            <?php else: ?>
            
            <?php foreach ($hiddenBySection as $section => $items): ?>
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-2">
                    <?php echo ucfirst(str_replace('_', ' ', $section)); ?>
                </h3>
                <div class="space-y-2">
                    <?php foreach ($items as $item): ?>
                    <div class="flex items-center p-2 bg-red-50 rounded-lg">
                        <i class="fas <?php echo htmlspecialchars($item['icon']); ?> w-5 text-red-400 mr-3"></i>
                        <div class="flex-1">
                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($item['label']); ?></p>
                            <p class="text-xs text-gray-500">
                                Permission: <?php echo htmlspecialchars($item['permission'] ?? 'None required'); ?>
                            </p>
                            <?php 
                            $isAdvUser = $testUser['company_type'] === 'ADV';
                            $reason = '';
                            if ($item['adv_only'] && !$isAdvUser) {
                                $reason = 'ADV-only menu (user is contractor)';
                            } elseif (!empty($item['permission'])) {
                                $permissionEngine = new PermissionEngine();
                                if (!$permissionEngine->can($testUserId, $item['permission'])) {
                                    $reason = 'Missing permission: ' . $item['permission'];
                                }
                            }
                            ?>
                            <?php if ($reason): ?>
                            <p class="text-xs text-red-600 mt-1">
                                <i class="fas fa-info-circle mr-1"></i><?php echo htmlspecialchars($reason); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <?php if ($item['adv_only']): ?>
                        <span class="px-2 py-1 text-xs bg-red-200 text-red-700 rounded">ADV Only</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ADV-Only Module Verification -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-shield-alt text-primary mr-2"></i>
            ADV-Only Module Verification
        </h2>
        <p class="text-gray-600 mb-4">
            Contractor users should NEVER see these modules: Master Data, System, Admin
        </p>
        
        <?php
        $advOnlyModules = $menuService->getAdvOnlyModules();
        $isContractor = $testUser['company_type'] === 'CONTRACTOR';
        ?>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($advOnlyModules as $module): ?>
            <?php
            $moduleVisible = isset($visibleMenus[$module]) && !empty($visibleMenus[$module]);
            $isCorrect = $isContractor ? !$moduleVisible : true;
            ?>
            <div class="p-4 rounded-lg <?php echo $isCorrect ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
                <div class="flex items-center justify-between">
                    <span class="font-medium"><?php echo ucfirst(str_replace('_', ' ', $module)); ?></span>
                    <?php if ($isCorrect): ?>
                    <i class="fas fa-check-circle text-green-500"></i>
                    <?php else: ?>
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <?php endif; ?>
                </div>
                <p class="text-sm mt-1 <?php echo $isCorrect ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php if ($isContractor): ?>
                        <?php echo $moduleVisible ? 'ERROR: Visible to contractor!' : 'Correctly hidden'; ?>
                    <?php else: ?>
                        <?php echo $moduleVisible ? 'Visible (ADV user)' : 'Hidden (no permission)'; ?>
                    <?php endif; ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layouts/base.php';
?>

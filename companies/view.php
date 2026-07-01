<?php
require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!can('companies.read')) {
    $_SESSION['flash_error'] = 'You do not have permission to view companies';
    header('Location: ../dashboard.php');
    exit;
}

$companyId = $_GET['id'] ?? null;
if (!$companyId) {
    header('Location: index.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'View Company';
$currentPage = 'companies';
$isLoggedIn = true;

$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        $_SESSION['flash_error'] = 'Company not found';
        header('Location: index.php');
        exit;
    }
    
    // Check access
    if (!isAdvUser() && $company['id'] != $currentUser['company_id']) {
        $_SESSION['flash_error'] = 'You do not have permission to view this company';
        header('Location: index.php');
        exit;
    }
    
    // Get users
    $stmt = $db->prepare("
        SELECT u.*, r.name as role_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.company_id = ?
        ORDER BY u.username
    ");
    $stmt->execute([$companyId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get delegated permissions
    $stmt = $db->prepare("
        SELECT p.* FROM permissions p
        JOIN company_permissions cp ON p.id = cp.permission_id
        WHERE cp.company_id = ? AND cp.is_active = 1
        ORDER BY p.module, p.action
    ");
    $stmt->execute([$companyId]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error loading company: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Companies', 'url' => 'index.php'],
    ['label' => $company['name']]
];

ob_start();
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Company Info -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 text-center border-b">
                <div class="w-20 h-20 <?php echo $company['type'] === 'ADV' ? 'bg-blue-100' : 'bg-gray-100'; ?> rounded-xl mx-auto flex items-center justify-center mb-4">
                    <i class="fas fa-building text-3xl <?php echo $company['type'] === 'ADV' ? 'text-blue-500' : 'text-gray-500'; ?>"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($company['name']); ?></h3>
                <div class="mt-2">
                    <span class="px-3 py-1 <?php echo $company['type'] === 'ADV' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'; ?> rounded-full text-sm">
                        <?php echo $company['type']; ?>
                    </span>
                    <?php if ($company['status'] === 'ACTIVE'): ?>
                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm ml-2">Active</span>
                    <?php elseif ($company['status'] === 'SUSPENDED'): ?>
                    <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm ml-2">Suspended</span>
                    <?php else: ?>
                    <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm ml-2">Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="p-6 space-y-3">
                <?php if (!empty($company['contact_email'])): ?>
                <div class="flex items-center text-gray-600">
                    <i class="fas fa-envelope w-5 mr-3"></i>
                    <span><?php echo htmlspecialchars($company['contact_email']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($company['contact_phone'])): ?>
                <div class="flex items-center text-gray-600">
                    <i class="fas fa-phone w-5 mr-3"></i>
                    <span><?php echo htmlspecialchars($company['contact_phone']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($company['address'])): ?>
                <div class="flex items-start text-gray-600">
                    <i class="fas fa-map-marker-alt w-5 mr-3 mt-1"></i>
                    <span><?php echo nl2br(htmlspecialchars($company['address'])); ?></span>
                </div>
                <?php endif; ?>
                <div class="flex items-center text-gray-600">
                    <i class="fas fa-users w-5 mr-3"></i>
                    <span><?php echo count($users); ?> users</span>
                </div>
                <div class="flex items-center text-gray-600">
                    <i class="fas fa-calendar w-5 mr-3"></i>
                    <span>Created <?php echo date('M d, Y', strtotime($company['created_at'])); ?></span>
                </div>
            </div>
            
            <?php if (can('companies.update') && isAdvUser()): ?>
            <div class="p-6 border-t">
                <a href="edit.php?id=<?php echo $company['id']; ?>" 
                   class="block w-full text-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-edit mr-2"></i>Edit Company
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Details -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Users -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-users mr-2 text-gray-400"></i>Users
                </h3>
                <?php if (can('users.create')): ?>
                <a href="../users/create.php?company_id=<?php echo $company['id']; ?>" class="text-primary hover:underline text-sm">
                    <i class="fas fa-plus mr-1"></i>Add User
                </a>
                <?php endif; ?>
            </div>
            <div class="p-6">
                <?php if (empty($users)): ?>
                <p class="text-gray-500 text-center py-4">No users in this company</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($users as $user): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white mr-3">
                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user['username']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['role_name'] ?? 'No role'); ?></p>
                            </div>
                        </div>
                        <span class="px-2 py-1 <?php echo $user['status'] == 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?> rounded text-xs">
                            <?php echo $user['status'] == 1 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Delegated Permissions (for contractors) -->
        <?php if ($company['type'] === 'CONTRACTOR'): ?>
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-key mr-2 text-gray-400"></i>Delegated Permissions
                </h3>
                <?php if (can('permissions.delegate') && isAdvUser()): ?>
                <a href="../permissions/delegate.php?company_id=<?php echo $company['id']; ?>" class="text-primary hover:underline text-sm">
                    <i class="fas fa-edit mr-1"></i>Manage
                </a>
                <?php endif; ?>
            </div>
            <div class="p-6">
                <?php if (empty($permissions)): ?>
                <p class="text-gray-500 text-center py-4">No permissions delegated</p>
                <?php else: ?>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($permissions as $perm): ?>
                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm">
                        <?php echo htmlspecialchars($perm['name']); ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

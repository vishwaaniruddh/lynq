<?php
require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!can('roles.update') || !isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to edit roles';
    header('Location: ../dashboard.php');
    exit;
}

$roleId = $_GET['id'] ?? null;
if (!$roleId) {
    header('Location: index.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Edit Role';
$currentPage = 'roles';
$isLoggedIn = true;

$db = Database::getInstance()->getConnection();
$errors = [];
$permissions = [];
$rolePermissions = [];

try {
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        $_SESSION['flash_error'] = 'Role not found';
        header('Location: index.php');
        exit;
    }
    
    // Get all permissions
    $stmt = $db->query("SELECT * FROM permissions ORDER BY module, action");
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get role permissions
    $stmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$roleId]);
    $rolePermissions = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_id');
    
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error loading role';
    header('Location: index.php');
    exit;
}

$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Roles', 'url' => 'index.php'],
    ['label' => 'Edit: ' . $role['name']]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    $selectedPermissions = $_POST['permissions'] ?? [];
    
    try {
        $db->beginTransaction();
        
        // Update role description
        $stmt = $db->prepare("UPDATE roles SET description = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$description, $roleId]);
        
        // Update permissions
        $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);
        
        $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($selectedPermissions as $permId) {
            $stmt->execute([$roleId, $permId]);
        }
        
        $db->commit();
        $_SESSION['flash_success'] = 'Role updated successfully';
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = 'Error updating role: ' . $e->getMessage();
    }
}

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($role['name']); ?></h3>
                    <p class="text-sm text-gray-500">
                        <span class="px-2 py-1 <?php echo $role['company_type'] === 'ADV' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'; ?> rounded text-xs mr-2">
                            <?php echo $role['company_type']; ?>
                        </span>
                        Level <?php echo $role['level']; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <?php if (!empty($errors)): ?>
        <div class="p-4 bg-red-50 border-b">
            <?php foreach ($errors as $error): ?>
            <p class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="p-6">
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" rows="2"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="Role description"><?php echo htmlspecialchars($role['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="mb-4">
                <h4 class="font-semibold text-gray-800 mb-2">Permissions</h4>
                <div class="flex items-center space-x-4 mb-4">
                    <button type="button" onclick="selectAll()" class="text-sm text-blue-600 hover:underline">Select All</button>
                    <button type="button" onclick="deselectAll()" class="text-sm text-gray-600 hover:underline">Deselect All</button>
                    <span class="text-sm text-gray-500">
                        <span id="selected-count"><?php echo count($rolePermissions); ?></span> selected
                    </span>
                </div>
            </div>
            
            <?php
            $modules = [];
            foreach ($permissions as $perm) {
                $modules[$perm['module']][] = $perm;
            }
            ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <?php foreach ($modules as $module => $perms): ?>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h5 class="font-medium text-gray-700 mb-3 capitalize">
                        <i class="fas fa-folder mr-2 text-gray-400"></i><?php echo $module; ?>
                    </h5>
                    <div class="space-y-2">
                        <?php foreach ($perms as $perm): ?>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="<?php echo $perm['id']; ?>"
                                class="permission-checkbox rounded border-gray-300 text-primary focus:ring-primary"
                                <?php echo in_array($perm['id'], $rolePermissions) ? 'checked' : ''; ?>
                                onchange="updateCount()">
                            <span class="ml-2 text-sm text-gray-600"><?php echo htmlspecialchars($perm['action']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                <a href="index.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-save mr-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function selectAll() {
    document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = true);
    updateCount();
}

function deselectAll() {
    document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = false);
    updateCount();
}

function updateCount() {
    const count = document.querySelectorAll('.permission-checkbox:checked').length;
    document.getElementById('selected-count').textContent = count;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

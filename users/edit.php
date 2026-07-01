<?php
require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!can('users.update')) {
    $_SESSION['flash_error'] = 'You do not have permission to edit users';
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
$pageTitle = 'Edit User';
$currentPage = 'users';
$isLoggedIn = true;

$db = Database::getInstance()->getConnection();
$user = null;
$companies = [];
$roles = [];
$errors = [];

try {
    // Get user
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['flash_error'] = 'User not found';
        header('Location: index.php');
        exit;
    }
    
    // Check company access
    if (!isAdvUser() && $user['company_id'] != $currentUser['company_id']) {
        $_SESSION['flash_error'] = 'You do not have permission to edit this user';
        header('Location: index.php');
        exit;
    }
    
    // Get companies
    if (isAdvUser()) {
        $stmt = $db->query("SELECT id, name, type FROM companies WHERE status = 'active' ORDER BY name");
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("SELECT id, name, type FROM companies WHERE id = ?");
        $stmt->execute([$currentUser['company_id']]);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get roles based on company type - include BOTH type roles
    if (isAdvUser()) {
        $stmt = $db->query("SELECT id, name, company_type, level FROM roles WHERE company_type IN ('ADV', 'CONTRACTOR', 'BOTH') ORDER BY company_type, level");
    } else {
        $stmt = $db->query("SELECT id, name, company_type, level FROM roles WHERE company_type IN ('CONTRACTOR', 'BOTH') ORDER BY level");
    }
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errors[] = 'Error loading user: ' . $e->getMessage();
}

$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Users', 'url' => 'index.php'],
    ['label' => 'Edit: ' . ($user['username'] ?? '')]
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'company_id' => $_POST['company_id'] ?? '',
        'role_id' => $_POST['role_id'] ?? '',
        'status' => $_POST['status'] ?? '1'
    ];
    
    // Validation
    if (empty($formData['username'])) {
        $errors[] = 'Username is required';
    }
    
    if (empty($formData['email'])) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($formData['first_name'])) {
        $errors[] = 'First name is required';
    }
    
    if (empty($formData['last_name'])) {
        $errors[] = 'Last name is required';
    }
    
    if (!empty($formData['password']) && strlen($formData['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    
    // Check username uniqueness
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$formData['username'], $userId]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already exists';
        }
    }
    
    // Check email uniqueness
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$formData['email'], $userId]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already exists';
        }
    }
    
    // Update user
    if (empty($errors)) {
        try {
            if (!empty($formData['password'])) {
                $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, password_hash = ?, company_id = ?, role_id = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $formData['username'],
                    $formData['email'],
                    $formData['first_name'],
                    $formData['last_name'],
                    $passwordHash,
                    $formData['company_id'],
                    $formData['role_id'],
                    $formData['status'],
                    $userId
                ]);
            } else {
                $stmt = $db->prepare("
                    UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, company_id = ?, role_id = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $formData['username'],
                    $formData['email'],
                    $formData['first_name'],
                    $formData['last_name'],
                    $formData['company_id'],
                    $formData['role_id'],
                    $formData['status'],
                    $userId
                ]);
            }
            
            // Log audit
            $stmt = $db->prepare("
                INSERT INTO user_audit_log (user_id, target_user_id, action, details, performed_by, ip_address)
                VALUES (?, ?, 'user_updated', ?, ?, ?)
            ");
            $stmt->execute([
                $currentUser['id'],
                $userId,
                json_encode(['changes' => $formData]),
                $currentUser['id'],
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            $_SESSION['flash_success'] = 'User updated successfully';
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error updating user: ' . $e->getMessage();
        }
    }
    
    // Update user array with form data for display
    $user = array_merge($user, $formData);
}

ob_start();
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Edit User</h3>
            <p class="text-sm text-gray-500">Update user account details</p>
        </div>
        
        <?php if (!empty($errors)): ?>
        <div class="p-4 bg-red-50 border-b border-red-100">
            <div class="flex items-start">
                <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-2"></i>
                <div>
                    <?php foreach ($errors as $error): ?>
                    <p class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                    <input type="text" name="first_name" required
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>"
                        placeholder="Enter first name">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                    <input type="text" name="last_name" required
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>"
                        placeholder="Enter last name">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Username *</label>
                    <input type="text" name="username" required
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                    <input type="email" name="email" required
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                <input type="password" name="password" minlength="8"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="Leave blank to keep current password">
                <p class="text-xs text-gray-500 mt-1">Min 8 characters. Leave blank to keep current password.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Company *</label>
                    <select name="company_id" id="company_id" required
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        onchange="filterRoles()">
                        <option value="">Select Company</option>
                        <?php foreach ($companies as $company): ?>
                        <option value="<?php echo $company['id']; ?>" 
                            data-type="<?php echo $company['type']; ?>"
                            <?php echo ($user['company_id'] ?? '') == $company['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($company['name']); ?> (<?php echo $company['type']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                    <select name="role_id" id="role_id" required
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>" 
                            data-type="<?php echo $role['company_type']; ?>"
                            <?php echo ($user['role_id'] ?? '') == $role['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="1" <?php echo ($user['status'] ?? 1) == 1 ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo ($user['status'] ?? 1) == 0 ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                <a href="index.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-save mr-2"></i>Update User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function filterRoles() {
    const companySelect = document.getElementById('company_id');
    const roleSelect = document.getElementById('role_id');
    const selectedOption = companySelect.options[companySelect.selectedIndex];
    const companyType = selectedOption ? selectedOption.dataset.type : '';
    
    Array.from(roleSelect.options).forEach(option => {
        if (option.value === '') {
            option.style.display = '';
        } else {
            // Show role if it matches company type OR if role is 'BOTH'
            const roleType = option.dataset.type;
            const shouldShow = roleType === companyType || roleType === 'BOTH';
            option.style.display = shouldShow ? '' : 'none';
        }
    });
    
    // Reset role selection if current selection doesn't match
    if (roleSelect.value) {
        const selectedRole = roleSelect.options[roleSelect.selectedIndex];
        if (selectedRole && selectedRole.style.display === 'none') {
            roleSelect.value = '';
        }
    }
}

document.addEventListener('DOMContentLoaded', filterRoles);
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

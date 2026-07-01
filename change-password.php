<?php
require_once __DIR__ . '/config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: views/auth/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '';
$pageTitle = 'Change Password';
$currentPage = 'profile';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'Profile', 'url' => 'profile.php'],
    ['label' => 'Change Password']
];

$db = Database::getInstance()->getConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate
    if (empty($currentPassword)) {
        $errors[] = 'Current password is required';
    }
    
    if (empty($newPassword)) {
        $errors[] = 'New password is required';
    } elseif (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters';
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    // Verify current password
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$currentUser['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect';
        }
    }
    
    // Update password
    if (empty($errors)) {
        try {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newHash, $currentUser['id']]);
            
            // Log audit
            $stmt = $db->prepare("
                INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address, created_at)
                VALUES (?, 'password_changed', '{}', ?, ?, NOW())
            ");
            $stmt->execute([$currentUser['id'], $currentUser['id'], $_SERVER['REMOTE_ADDR'] ?? '']);
            
            $_SESSION['flash_success'] = 'Password changed successfully';
            header('Location: profile.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error changing password';
        }
    }
}

ob_start();
?>

<div class="max-w-md mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Change Password</h3>
            <p class="text-sm text-gray-500">Update your account password</p>
        </div>
        
        <?php if (!empty($errors)): ?>
        <div class="p-4 bg-red-50 border-b">
            <?php foreach ($errors as $error): ?>
            <p class="text-red-700 text-sm"><i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="p-6 space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Current Password *</label>
                <input type="password" name="current_password" required
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="Enter current password">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">New Password *</label>
                <input type="password" name="new_password" required minlength="8"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="Min 8 characters">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password *</label>
                <input type="password" name="confirm_password" required
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="Confirm new password">
            </div>
            
            <div class="pt-4 border-t flex justify-end space-x-3">
                <a href="profile.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-key mr-2"></i>Change Password
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layouts/base.php';
?>

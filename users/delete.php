<?php
require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!can('users.delete')) {
    $_SESSION['flash_error'] = 'You do not have permission to delete users';
    header('Location: index.php');
    exit;
}

$userId = $_GET['id'] ?? null;
$confirm = $_GET['confirm'] ?? null;

if (!$userId) {
    header('Location: index.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$db = Database::getInstance()->getConnection();

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
    
    // Cannot delete yourself
    if ($user['id'] == $currentUser['id']) {
        $_SESSION['flash_error'] = 'You cannot delete your own account';
        header('Location: index.php');
        exit;
    }
    
    // Check company access
    if (!isAdvUser() && $user['company_id'] != $currentUser['company_id']) {
        $_SESSION['flash_error'] = 'You do not have permission to delete this user';
        header('Location: index.php');
        exit;
    }
    
    if ($confirm === '1') {
        // Store user info for audit before deletion
        $deletedUserInfo = [
            'deleted_user_id' => $userId,
            'username' => $user['username'],
            'email' => $user['email']
        ];
        
        // Delete user first
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Log audit with NULL for user_id (since user is deleted)
        // Store the deleted user's info in details JSON
        $stmt = $db->prepare("
            INSERT INTO user_audit_log (user_id, target_user_id, action, details, performed_by, ip_address, timestamp)
            VALUES (?, NULL, 'user_deleted', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $currentUser['id'],
            json_encode($deletedUserInfo),
            $currentUser['id'],
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        
        $_SESSION['flash_success'] = 'User deleted successfully';
    }
    
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error deleting user: ' . $e->getMessage();
}

header('Location: index.php');
exit;

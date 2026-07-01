<?php
/**
 * Locked Users Management Page
 * Displays users who are currently locked or have failed login attempts,
 * and allows administrators with the 'users.update' permission to unlock them.
 */
require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/AccountLockoutService.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!can('users.read')) {
    $_SESSION['flash_error'] = 'You do not have permission to view locked users';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$canUpdate = can('users.update');

$db = DatabaseConfig::getInstance();
$lockoutService = new AccountLockoutService();

// Process unlock action
if (isset($_GET['action']) && $_GET['action'] === 'unlock' && isset($_GET['id'])) {
    if (!$canUpdate) {
        $_SESSION['flash_error'] = 'You do not have permission to unlock users';
        header('Location: locked.php');
        exit;
    }
    
    $userId = intval($_GET['id']);
    $success = $lockoutService->unlockAccount($userId, $currentUser['id']);
    
    if ($success) {
        $_SESSION['flash_success'] = 'User account has been successfully unlocked';
    } else {
        $_SESSION['flash_error'] = 'Failed to unlock user account';
    }
    
    header('Location: locked.php');
    exit;
}

// Fetch all locked users or users with failed attempts
$sql = "SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.status, u.locked_until, u.failed_login_attempts,
               c.name as company_name, r.name as role_name 
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.status = 2 OR u.failed_login_attempts >= 5
        ORDER BY u.locked_until DESC, u.failed_login_attempts DESC";

$lockedUsers = $db->getResults($sql, [], '');

$baseUrl = '..';
$pageTitle = 'Locked Users';
$currentPage = 'users';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Users', 'url' => 'index.php'],
    ['label' => 'Locked Users']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Locked Users & Login Failures</h3>
            <p class="text-sm text-gray-500">Monitor and unlock user accounts affected by multiple failed login attempts</p>
        </div>
        <div>
            <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Back to Users
            </a>
        </div>
    </div>

    <!-- Alert notifications -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="m-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-sm flex items-center">
            <i class="fas fa-check-circle mr-2 text-emerald-500"></i>
            <?php 
                echo $_SESSION['flash_success']; 
                unset($_SESSION['flash_success']);
            ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="m-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm flex items-center">
            <i class="fas fa-exclamation-circle mr-2 text-red-500"></i>
            <?php 
                echo $_SESSION['flash_error']; 
                unset($_SESSION['flash_error']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Company / Role</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Failed Attempts</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Lockout Status</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($lockedUsers)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-600 mb-3">
                                    <i class="fas fa-shield-alt text-xl"></i>
                                </div>
                                <h4 class="font-medium text-gray-800">No Locked Accounts</h4>
                                <p class="text-xs text-gray-400 mt-1">All user accounts are currently unlocked and active.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($lockedUsers as $u): 
                        $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                        if (empty($fullName)) $fullName = $u['username'];
                        $initial = strtoupper(substr($fullName, 0, 1));
                        
                        $isLocked = ($u['status'] == 2);
                        $isTimedOut = false;
                        if ($u['locked_until']) {
                            $isTimedOut = (strtotime($u['locked_until']) > time());
                        }
                    ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="w-9 h-9 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg flex items-center justify-center text-blue-600 text-sm font-medium mr-3 flex-shrink-0">
                                        <?php echo $initial; ?>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($fullName); ?></div>
                                        <div class="text-xs text-gray-500">@<?php echo htmlspecialchars($u['username']); ?> &bull; <?php echo htmlspecialchars($u['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-xs text-gray-700 font-medium"><?php echo htmlspecialchars($u['company_name'] ?? '-'); ?></div>
                                <div class="text-[11px] text-gray-500"><?php echo htmlspecialchars($u['role_name'] ?? '-'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <span class="px-2.5 py-1 <?php echo $u['failed_login_attempts'] >= 5 ? 'bg-red-50 text-red-700 font-semibold' : 'bg-amber-50 text-amber-700'; ?> rounded-lg text-xs">
                                        <?php echo $u['failed_login_attempts']; ?> attempts
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($isLocked && $isTimedOut): ?>
                                    <div class="text-xs">
                                        <span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-700 rounded-full text-[10px] font-medium mb-1">
                                            <span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1"></span>Locked Out
                                        </span>
                                        <div class="text-[10px] text-gray-500">
                                            <i class="far fa-clock mr-1"></i>Until: <?php echo date('M d, Y h:i A', strtotime($u['locked_until'])); ?>
                                        </div>
                                    </div>
                                <?php elseif ($u['failed_login_attempts'] > 0): ?>
                                    <div class="text-xs">
                                        <span class="inline-flex items-center px-2 py-0.5 bg-amber-50 text-amber-700 rounded-full text-[10px] font-medium">
                                            <span class="w-1.5 h-1.5 bg-amber-500 rounded-full mr-1"></span>Failed Attempts
                                        </span>
                                        <div class="text-[10px] text-gray-400 mt-0.5">Account is still active but monitoring failures</div>
                                    </div>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded-full text-[10px] font-medium">
                                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Active
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($canUpdate): ?>
                                    <button onclick="confirmUnlock(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>')" 
                                            class="inline-flex items-center px-3 py-1.5 bg-amber-500 text-white rounded-lg text-xs font-semibold hover:bg-amber-600 transition shadow-sm hover:shadow">
                                        <i class="fas fa-lock-open mr-1.5"></i>Unlock Account
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">No permissions</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function confirmUnlock(id, username) {
    openConfirmModal(
        'Unlock User Account',
        `Are you sure you want to unlock user account "${username}"? This will clear all login attempts and reset the lockout status.`,
        function() {
            window.location.href = `locked.php?action=unlock&id=${id}`;
        }
    );
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

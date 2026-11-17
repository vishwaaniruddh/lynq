<?php
/**
 * Locked Users & Login Security Management Page
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

// 1. Process Unlock User Account Action
if (isset($_GET['action']) && $_GET['action'] === 'unlock' && isset($_GET['id'])) {
    if (!$canUpdate) {
        $_SESSION['flash_error'] = 'You do not have permission to unlock users';
        header('Location: locked.php');
        exit;
    }
    
    $userId = intval($_GET['id']);
    $success = $lockoutService->unlockAccount($userId, $currentUser['id']);
    
    if ($success) {
        $_SESSION['flash_success'] = 'User account has been successfully unlocked and reset.';
    } else {
        $_SESSION['flash_error'] = 'Failed to unlock user account.';
    }
    
    header('Location: locked.php');
    exit;
}

// 2. Process Clear IP Failures Action
if (isset($_GET['action']) && $_GET['action'] === 'unlock_ip' && isset($_GET['ip'])) {
    if (!$canUpdate) {
        $_SESSION['flash_error'] = 'You do not have permission to clear IP lockouts';
        header('Location: locked.php');
        exit;
    }
    
    $ip = trim($_GET['ip']);
    $success = $lockoutService->unlockIp($ip, $currentUser['id']);
    
    if ($success) {
        $_SESSION['flash_success'] = 'IP address [' . htmlspecialchars($ip) . '] login failures cleared successfully.';
    } else {
        $_SESSION['flash_error'] = 'Failed to clear IP failures.';
    }
    
    header('Location: locked.php');
    exit;
}

// 3. Process Unlock All Action
if (isset($_GET['action']) && $_GET['action'] === 'unlock_all') {
    if (!$canUpdate) {
        $_SESSION['flash_error'] = 'You do not have permission to perform bulk unlock';
        header('Location: locked.php');
        exit;
    }
    
    $success = $lockoutService->unlockAll($currentUser['id']);
    if ($success) {
        $_SESSION['flash_success'] = 'All locked user accounts and IP failure records have been reset.';
    } else {
        $_SESSION['flash_error'] = 'Failed to perform bulk unlock.';
    }
    
    header('Location: locked.php');
    exit;
}

// Fetch all locked users or users with failed attempts (from users table or login_attempts table)
$sqlUsers = "SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.status, u.locked_until, u.failed_login_attempts,
               c.name as company_name, r.name as role_name,
               (SELECT COUNT(*) FROM login_attempts la 
                WHERE (la.identifier = u.username OR la.identifier = u.email OR la.identifier = SUBSTRING_INDEX(u.email, '@', 1))
                  AND la.success = 0 
                  AND la.created_at > DATE_SUB(NOW(), INTERVAL 900 SECOND)) as recent_failed_attempts,
               (SELECT COUNT(*) FROM login_attempts la 
                WHERE (la.identifier = u.username OR la.identifier = u.email OR la.identifier = SUBSTRING_INDEX(u.email, '@', 1))
                  AND la.success = 0 
                  AND la.created_at > DATE_SUB(NOW(), INTERVAL 86400 SECOND)) as day_failed_attempts,
               (SELECT MAX(la.created_at) FROM login_attempts la 
                WHERE (la.identifier = u.username OR la.identifier = u.email OR la.identifier = SUBSTRING_INDEX(u.email, '@', 1))
                  AND la.success = 0) as last_attempt_at,
               (SELECT la.ip_address FROM login_attempts la 
                WHERE (la.identifier = u.username OR la.identifier = u.email OR la.identifier = SUBSTRING_INDEX(u.email, '@', 1))
                  AND la.success = 0 ORDER BY la.id DESC LIMIT 1) as last_ip
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.status = 2 
           OR u.failed_login_attempts > 0 
           OR u.locked_until IS NOT NULL 
           OR EXISTS (
               SELECT 1 FROM login_attempts la 
               WHERE (la.identifier = u.username OR la.identifier = u.email OR la.identifier = SUBSTRING_INDEX(u.email, '@', 1))
                 AND la.success = 0 
                 AND la.created_at > DATE_SUB(NOW(), INTERVAL 86400 SECOND)
           )
        ORDER BY 
            CASE WHEN u.status = 2 OR (u.locked_until IS NOT NULL AND u.locked_until > NOW()) OR (
                SELECT COUNT(*) FROM login_attempts la 
                WHERE (la.identifier = u.username OR la.identifier = u.email OR la.identifier = SUBSTRING_INDEX(u.email, '@', 1))
                  AND la.success = 0 
                  AND la.created_at > DATE_SUB(NOW(), INTERVAL 900 SECOND)
            ) >= 5 THEN 1 ELSE 2 END,
            u.locked_until DESC, 
            u.failed_login_attempts DESC";

$lockedUsers = $db->getResults($sqlUsers, [], '');

// Fetch IP addresses with failed attempts in last 24 hours
$sqlIps = "SELECT ip_address, 
               COUNT(*) as failed_count, 
               GROUP_CONCAT(DISTINCT identifier SEPARATOR ', ') as identifiers,
               MAX(created_at) as last_failed_at,
               MIN(created_at) as first_failed_at
        FROM login_attempts 
        WHERE success = 0 
          AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY ip_address 
        ORDER BY failed_count DESC, last_failed_at DESC";

$flaggedIps = $db->getResults($sqlIps, [], '');

// Compute dashboard KPIs
$kpiLocked = 0;
$kpiMonitoring = 0;
foreach ($lockedUsers as $u) {
    $isUserLocked = ($u['status'] == 2) || 
                    ($u['locked_until'] && strtotime($u['locked_until']) > time()) || 
                    ($u['recent_failed_attempts'] >= 5);
    if ($isUserLocked) {
        $kpiLocked++;
    } else {
        $kpiMonitoring++;
    }
}
$kpiIps = count($flaggedIps);
$kpiTotalFailures = array_sum(array_column($flaggedIps, 'failed_count'));

$baseUrl = '..';
$pageTitle = 'Locked Users & Security';
$currentPage = 'users';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Users', 'url' => 'index.php'],
    ['label' => 'Locked Users & Security']
];

ob_start();
?>

<div class="space-y-6">
    <!-- Top Action Bar -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-shield-alt text-primary mr-2.5"></i>Locked Users & Security Management
            </h3>
            <p class="text-sm text-gray-500 mt-1">Monitor, inspect, and immediately unlock user accounts and network IPs affected by failed logins</p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($canUpdate && (!empty($lockedUsers) || !empty($flaggedIps))): ?>
                <button onclick="confirmUnlockAll()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition flex items-center text-sm font-semibold shadow-sm">
                    <i class="fas fa-unlock-alt mr-2"></i>Unlock All Accounts
                </button>
            <?php endif; ?>
            <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Back to Users
            </a>
        </div>
    </div>

    <!-- Alert notifications -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center shadow-sm">
            <i class="fas fa-check-circle mr-2.5 text-emerald-600 text-lg"></i>
            <div><?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></div>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl text-sm flex items-center shadow-sm">
            <i class="fas fa-exclamation-circle mr-2.5 text-red-600 text-lg"></i>
            <div><?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?></div>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Locked Accounts</p>
                <p class="text-2xl font-bold text-red-600 mt-1"><?php echo $kpiLocked; ?></p>
                <p class="text-[11px] text-gray-400 mt-0.5">Currently blocked from login</p>
            </div>
            <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center text-red-500">
                <i class="fas fa-user-lock text-xl"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Monitoring Failures</p>
                <p class="text-2xl font-bold text-amber-600 mt-1"><?php echo $kpiMonitoring; ?></p>
                <p class="text-[11px] text-gray-400 mt-0.5">Under threshold (1-4 attempts)</p>
            </div>
            <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center text-amber-500">
                <i class="fas fa-user-clock text-xl"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Flagged Network IPs</p>
                <p class="text-2xl font-bold text-indigo-600 mt-1"><?php echo $kpiIps; ?></p>
                <p class="text-[11px] text-gray-400 mt-0.5">IPs with recorded failures</p>
            </div>
            <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-500">
                <i class="fas fa-network-wired text-xl"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Failures (24h)</p>
                <p class="text-2xl font-bold text-gray-700 mt-1"><?php echo $kpiTotalFailures; ?></p>
                <p class="text-[11px] text-gray-400 mt-0.5">Across all accounts & IPs</p>
            </div>
            <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center text-gray-500">
                <i class="fas fa-history text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Section 1: User Accounts (Locked & Monitored) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b bg-gray-50/50 flex items-center justify-between">
            <div>
                <h4 class="font-bold text-gray-800 text-base">User Accounts (Locked & Monitored Failures)</h4>
                <p class="text-xs text-gray-500 mt-0.5">Accounts flagged due to repeated password failures or admin lockout</p>
            </div>
            <span class="px-2.5 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">
                <?php echo count($lockedUsers); ?> Users
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b">
                    <tr>
                        <th class="px-6 py-3.5 text-left">User Details</th>
                        <th class="px-6 py-3.5 text-left">Company / Role</th>
                        <th class="px-6 py-3.5 text-left">Failed Attempts</th>
                        <th class="px-6 py-3.5 text-left">Lockout Status</th>
                        <th class="px-6 py-3.5 text-left">Last Attempt / IP</th>
                        <th class="px-6 py-3.5 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($lockedUsers)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
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
                            
                            $totalFailed = max((int)$u['failed_login_attempts'], (int)$u['recent_failed_attempts'], (int)$u['day_failed_attempts']);
                            $isExplicitLocked = ($u['status'] == 2);
                            $isTimedOut = ($u['locked_until'] && strtotime($u['locked_until']) > time());
                            $isServiceLocked = ($u['recent_failed_attempts'] >= 5);
                            $isLocked = $isExplicitLocked || $isTimedOut || $isServiceLocked;
                        ?>
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <!-- User Info -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-br <?php echo $isLocked ? 'from-red-100 to-red-200 text-red-700' : 'from-amber-100 to-amber-200 text-amber-700'; ?> rounded-xl flex items-center justify-center text-sm font-bold mr-3.5 flex-shrink-0 shadow-sm">
                                            <?php echo $initial; ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($fullName); ?></div>
                                            <div class="text-xs text-gray-500 font-mono mt-0.5">
                                                <?php echo htmlspecialchars($u['email']); ?>
                                            </div>
                                            <?php if ($u['username'] !== $u['email']): ?>
                                                <div class="text-[11px] text-gray-400">Username: @<?php echo htmlspecialchars($u['username']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Company & Role -->
                                <td class="px-6 py-4">
                                    <div class="text-xs font-semibold text-gray-800"><?php echo htmlspecialchars($u['company_name'] ?? 'Advantage'); ?></div>
                                    <div class="text-[11px] text-gray-500 mt-0.5"><?php echo htmlspecialchars($u['role_name'] ?? 'User'); ?></div>
                                </td>

                                <!-- Failed Attempts -->
                                <td class="px-6 py-4">
                                    <div class="space-y-1">
                                        <span class="inline-flex items-center px-2.5 py-1 <?php echo $totalFailed >= 5 ? 'bg-red-50 text-red-700 border border-red-200 font-bold' : 'bg-amber-50 text-amber-700 border border-amber-200 font-semibold'; ?> rounded-lg text-xs">
                                            <i class="fas fa-exclamation-triangle mr-1.5 text-[10px]"></i>
                                            <?php echo $totalFailed; ?> failed <?php echo $totalFailed === 1 ? 'attempt' : 'attempts'; ?>
                                        </span>
                                        <?php if ((int)$u['recent_failed_attempts'] > 0): ?>
                                            <div class="text-[10px] text-gray-400">
                                                (<?php echo $u['recent_failed_attempts']; ?> within last 15 mins)
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Lockout Status -->
                                <td class="px-6 py-4">
                                    <?php if ($isLocked): ?>
                                        <div class="space-y-1">
                                            <span class="inline-flex items-center px-2.5 py-1 bg-red-100 text-red-800 rounded-full text-xs font-bold">
                                                <span class="w-2 h-2 bg-red-600 rounded-full animate-ping mr-1.5"></span>
                                                Locked Out
                                            </span>
                                            <?php if ($u['locked_until']): ?>
                                                <div class="text-[11px] text-red-600 font-medium">
                                                    <i class="far fa-clock mr-1"></i>Until: <?php echo date('h:i A', strtotime($u['locked_until'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-[11px] text-gray-500">Security threshold exceeded</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="space-y-1">
                                            <span class="inline-flex items-center px-2.5 py-1 bg-amber-100 text-amber-800 rounded-full text-xs font-semibold">
                                                <span class="w-2 h-2 bg-amber-500 rounded-full mr-1.5"></span>
                                                Monitoring Failures
                                            </span>
                                            <div class="text-[11px] text-gray-400">Account active, monitoring attempts</div>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Last Attempt / IP -->
                                <td class="px-6 py-4">
                                    <?php if ($u['last_attempt_at']): ?>
                                        <div class="text-xs text-gray-700 font-medium">
                                            <?php echo date('M d, Y h:i A', strtotime($u['last_attempt_at'])); ?>
                                        </div>
                                        <?php if ($u['last_ip']): ?>
                                            <div class="text-[11px] font-mono text-gray-400 mt-0.5">
                                                IP: <?php echo htmlspecialchars($u['last_ip']); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 text-center">
                                    <?php if ($canUpdate): ?>
                                        <button onclick="confirmUnlock(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>')" 
                                                class="inline-flex items-center px-3.5 py-1.5 <?php echo $isLocked ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-amber-500 hover:bg-amber-600 text-white'; ?> rounded-lg text-xs font-semibold transition shadow-sm hover:shadow">
                                            <i class="fas fa-lock-open mr-1.5"></i><?php echo $isLocked ? 'Unlock Account' : 'Reset Attempts'; ?>
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

    <!-- Section 2: Flagged Network IPs & Recent Activity -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b bg-gray-50/50 flex items-center justify-between">
            <div>
                <h4 class="font-bold text-gray-800 text-base">Network IP Failures & Flood Protection</h4>
                <p class="text-xs text-gray-500 mt-0.5">IP addresses with multiple failed login attempts in the past 24 hours</p>
            </div>
            <span class="px-2.5 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">
                <?php echo count($flaggedIps); ?> Networks
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b">
                    <tr>
                        <th class="px-6 py-3.5 text-left">IP Address</th>
                        <th class="px-6 py-3.5 text-left">Targeted Identifiers</th>
                        <th class="px-6 py-3.5 text-left">Total Failed Attempts</th>
                        <th class="px-6 py-3.5 text-left">Last Recorded Failure</th>
                        <th class="px-6 py-3.5 text-left">Status</th>
                        <th class="px-6 py-3.5 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($flaggedIps)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                                <i class="fas fa-check-circle text-emerald-500 text-xl mb-2"></i>
                                <p class="text-xs text-gray-400">No network IP failure records logged in the last 24 hours.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($flaggedIps as $ipRow): 
                            $isRateLimited = ($ipRow['failed_count'] >= 20);
                        ?>
                            <tr class="hover:bg-gray-50/60 transition-colors">
                                <td class="px-6 py-4 font-mono text-xs font-bold text-gray-800">
                                    <i class="fas fa-globe text-gray-400 mr-2"></i><?php echo htmlspecialchars($ipRow['ip_address']); ?>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-600 max-w-xs truncate" title="<?php echo htmlspecialchars($ipRow['identifiers']); ?>">
                                    <?php echo htmlspecialchars($ipRow['identifiers']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 <?php echo $ipRow['failed_count'] >= 5 ? 'bg-amber-100 text-amber-800 font-bold' : 'bg-gray-100 text-gray-700'; ?> rounded-lg text-xs">
                                        <?php echo $ipRow['failed_count']; ?> attempts
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-600">
                                    <?php echo date('M d, Y h:i A', strtotime($ipRow['last_failed_at'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($isRateLimited): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 bg-red-100 text-red-800 rounded-full text-[11px] font-bold">
                                            <span class="w-1.5 h-1.5 bg-red-600 rounded-full mr-1.5"></span>Rate-Limited
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full text-[11px] font-medium">
                                            <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-1.5"></span>Logged
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($canUpdate): ?>
                                        <button onclick="confirmUnlockIp('<?php echo htmlspecialchars($ipRow['ip_address'], ENT_QUOTES); ?>')" 
                                                class="inline-flex items-center px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium transition">
                                            <i class="fas fa-trash-alt mr-1.5 text-gray-400"></i>Clear IP Failures
                                        </button>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function confirmUnlock(id, username) {
    openConfirmModal(
        'Unlock User Account',
        `Are you sure you want to unlock user account "${username}"? This will clear all recorded login failures and reset the lockout status immediately.`,
        function() {
            window.location.href = `locked.php?action=unlock&id=${id}`;
        }
    );
}

function confirmUnlockIp(ip) {
    openConfirmModal(
        'Clear IP Failures',
        `Are you sure you want to clear all failed login attempts for IP address "${ip}"?`,
        function() {
            window.location.href = `locked.php?action=unlock_ip&ip=${encodeURIComponent(ip)}`;
        }
    );
}

function confirmUnlockAll() {
    openConfirmModal(
        'Unlock All Accounts & Networks',
        `Are you sure you want to unlock all user accounts and clear all recorded failed login attempts?`,
        function() {
            window.location.href = `locked.php?action=unlock_all`;
        }
    );
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

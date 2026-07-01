<?php
/**
 * Inventory Notifications Page
 * Displays all notifications for the current user
 * 
 * Requirements: 11.5
 * - Display notification type, related dispatch, and timestamp
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InventoryNotificationService.php';
require_once __DIR__ . '/../services/MenuService.php';

// Initialize services
$sessionService = new SessionService();
$menuService = new MenuService();

// Set base URL
$baseUrl = '';

// Check authentication
if (!$sessionService->isLoggedIn()) {
    header('Location: ' . $baseUrl . '/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$userId = $currentUser['id'];
$isLoggedIn = true;

// Initialize notification service
$notificationService = new InventoryNotificationService();

// Get notifications
$result = $notificationService->getNotifications($userId, null, 100);
$notifications = $result['success'] ? $result['data']['notifications'] : [];

// Get unread count
$unreadResult = $notificationService->getUnreadCount($userId);
$unreadCount = $unreadResult['success'] ? $unreadResult['data']['unread_count'] : 0;

// Page configuration
$pageTitle = 'Notifications';
$currentPage = 'notifications';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => $baseUrl . '/dashboard.php'],
    ['label' => 'Inventory', 'url' => $baseUrl . '/inventory/dashboard.php'],
    ['label' => 'Notifications']
];

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Notifications</h1>
            <p class="text-gray-500 text-sm mt-1">
                <?php echo $unreadCount; ?> unread notification<?php echo $unreadCount !== 1 ? 's' : ''; ?>
            </p>
        </div>
        
        <?php if ($unreadCount > 0): ?>
        <button onclick="markAllAsRead()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition text-sm">
            <i class="fas fa-check-double mr-2"></i>Mark All as Read
        </button>
        <?php endif; ?>
    </div>
    
    <!-- Notifications List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <?php if (empty($notifications)): ?>
        <div class="px-6 py-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                <i class="fas fa-bell-slash text-2xl text-gray-400"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-800 mb-1">No notifications</h3>
            <p class="text-gray-500 text-sm">You're all caught up!</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-gray-100" id="notificationsList">
            <?php foreach ($notifications as $notification): ?>
            <?php
                $isUnread = !$notification['is_read'];
                $bgClass = $isUnread ? 'bg-blue-50' : '';
                
                // Get icon based on type
                $iconConfig = [
                    'pending_receive' => ['icon' => 'fa-inbox', 'bg' => 'bg-blue-100', 'text' => 'text-blue-600'],
                    'accepted' => ['icon' => 'fa-check-circle', 'bg' => 'bg-green-100', 'text' => 'text-green-600'],
                    'rejected' => ['icon' => 'fa-times-circle', 'bg' => 'bg-red-100', 'text' => 'text-red-600'],
                    'overdue' => ['icon' => 'fa-clock', 'bg' => 'bg-orange-100', 'text' => 'text-orange-600'],
                    'discrepancy' => ['icon' => 'fa-exclamation-triangle', 'bg' => 'bg-yellow-100', 'text' => 'text-yellow-600']
                ];
                $icon = $iconConfig[$notification['notification_type']] ?? ['icon' => 'fa-bell', 'bg' => 'bg-gray-100', 'text' => 'text-gray-600'];
                
                // Format time
                $createdAt = new DateTime($notification['created_at']);
                $now = new DateTime();
                $diff = $now->diff($createdAt);
                
                if ($diff->days == 0) {
                    if ($diff->h == 0) {
                        $timeAgo = $diff->i == 0 ? 'Just now' : $diff->i . ' min ago';
                    } else {
                        $timeAgo = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                    }
                } elseif ($diff->days == 1) {
                    $timeAgo = 'Yesterday';
                } elseif ($diff->days < 7) {
                    $timeAgo = $diff->days . ' days ago';
                } else {
                    $timeAgo = $createdAt->format('M j, Y');
                }
            ?>
            <div class="notification-item px-6 py-4 hover:bg-gray-50 cursor-pointer <?php echo $bgClass; ?>" 
                 data-id="<?php echo $notification['id']; ?>"
                 data-dispatch-id="<?php echo $notification['dispatch_id'] ?? ''; ?>"
                 onclick="handleNotificationClick(<?php echo $notification['id']; ?>, <?php echo $notification['dispatch_id'] ?? 'null'; ?>)">
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full <?php echo $icon['bg']; ?> flex items-center justify-center">
                        <i class="fas <?php echo $icon['icon']; ?> <?php echo $icon['text']; ?>"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-800 <?php echo $isUnread ? 'font-semibold' : ''; ?>">
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </p>
                            <span class="text-xs text-gray-400 flex-shrink-0 ml-4"><?php echo $timeAgo; ?></span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($notification['message'] ?? ''); ?></p>
                        <?php if (!empty($notification['dispatch_number'])): ?>
                        <p class="text-xs text-primary mt-2">
                            <i class="fas fa-truck mr-1"></i>
                            Dispatch: <?php echo htmlspecialchars($notification['dispatch_number']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php if ($isUnread): ?>
                    <span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0 mt-2"></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const baseUrl = '<?php echo $baseUrl; ?>';

/**
 * Handle notification click
 */
async function handleNotificationClick(notificationId, dispatchId) {
    try {
        // Mark as read
        await fetch(baseUrl + '/api/inventory/notifications/mark-read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_id: notificationId })
        });
        
        // Navigate to dispatch if available
        if (dispatchId) {
            window.location.href = baseUrl + '/inventory/dispatch.php?highlight=' + dispatchId;
        } else {
            // Just refresh the page to update read status
            window.location.reload();
        }
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
    }
}

/**
 * Mark all notifications as read
 */
async function markAllAsRead() {
    try {
        const response = await fetch(baseUrl + '/api/inventory/notifications/mark-all-read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        
        if (response.ok) {
            window.location.reload();
        }
    } catch (error) {
        console.error('Failed to mark all as read:', error);
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

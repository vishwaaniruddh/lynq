<header class="glass sticky top-0 z-30 border-b border-gray-200 px-4 md:px-6 py-4">
    <div class="flex items-center justify-between">
        <div class="pl-12 lg:pl-0">
            <h2 class="text-xl font-semibold text-gray-800"><?php echo $pageTitle ?? 'Dashboard'; ?></h2>
            <?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
            <nav class="flex items-center text-sm text-gray-500 mt-1">
                <?php foreach ($breadcrumbs as $i => $crumb): ?>
                    <?php if ($i > 0): ?><i class="fas fa-chevron-right mx-2 text-xs text-gray-300"></i><?php endif; ?>
                    <?php if (isset($crumb['url'])): ?>
                        <a href="<?php echo $crumb['url']; ?>" class="hover:text-primary transition"><?php echo $crumb['label']; ?></a>
                    <?php else: ?>
                        <span class="text-gray-700"><?php echo $crumb['label']; ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
        </div>
        
        <div class="flex items-center space-x-3">
            <!-- Connection Status -->
            <div class="hidden md:flex items-center space-x-2">
                <span class="connection-status online" id="connection-status">Online</span>
            </div>
            
            <!-- PWA Install Button -->
            <button id="pwa-install-btn" onclick="installPWA()" 
                    class="hidden md:flex items-center space-x-2 px-3 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition text-sm"
                    title="Install as App">
                <i class="fas fa-download"></i>
                <span>Install App</span>
            </button>
            
            <!-- Search (Desktop) -->
            <div class="hidden md:block relative">
                <input type="text" placeholder="Search..." 
                    class="w-64 pl-10 pr-4 py-2 bg-gray-100 border-0 rounded-xl text-sm focus:ring-2 focus:ring-primary focus:bg-white transition">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>
            
            <?php if (isAdvUser()): ?>
            <!-- ADV Documentation -->
            <a href="<?php echo $baseUrl; ?>/docs/adv.php" 
               class="p-2 text-gray-500 hover:text-primary hover:bg-gray-100 rounded-xl transition"
               title="ADV Documentation">
                <i class="fas fa-book text-lg"></i>
            </a>
            <?php endif; ?>
            
            <!-- Notifications -->
            <div class="relative" id="notificationDropdown">
                <button onclick="toggleNotificationDropdown()" class="relative p-2 text-gray-500 hover:text-primary hover:bg-gray-100 rounded-xl transition">
                    <i class="fas fa-bell text-lg"></i>
                    <span id="notificationBadge" class="absolute top-1 right-1 min-w-[18px] h-[18px] bg-red-500 rounded-full text-white text-xs flex items-center justify-center hidden">0</span>
                </button>
                
                <div id="notificationMenu" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50 max-h-96 overflow-hidden">
                    <!-- Header -->
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-800">Notifications</h3>
                        <button onclick="markAllNotificationsRead()" class="text-xs text-primary hover:text-primary-dark transition">
                            Mark all as read
                        </button>
                    </div>
                    
                    <!-- Notification List -->
                    <div id="notificationList" class="overflow-y-auto max-h-72">
                        <div class="px-4 py-8 text-center text-gray-500 text-sm">
                            <i class="fas fa-bell-slash text-2xl mb-2 text-gray-300"></i>
                            <p>No notifications</p>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="px-4 py-2 border-t border-gray-100 bg-gray-50">
                        <a href="<?php echo $baseUrl; ?>/inventory/notifications.php" class="text-xs text-primary hover:text-primary-dark transition block text-center">
                            View all notifications
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- User Dropdown -->
            <div class="relative" id="userDropdown">
                <button onclick="toggleDropdown()" class="flex items-center space-x-2 p-2 hover:bg-gray-100 rounded-xl transition">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white text-sm font-semibold">
                        <?php echo strtoupper(substr($currentUser['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="hidden md:block text-left">
                        <p class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($currentUser['username'] ?? 'User'); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($currentUser['company_name'] ?? ''); ?></p>
                    </div>
                    <i class="fas fa-chevron-down text-xs text-gray-400 hidden md:block"></i>
                </button>
                
                <div id="dropdownMenu" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($currentUser['username'] ?? ''); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></p>
                        <span class="inline-block mt-2 px-2 py-1 text-xs rounded-lg <?php echo ($currentUser['company_type'] ?? '') === 'ADV' ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-600'; ?>">
                            <?php echo htmlspecialchars($currentUser['company_type'] ?? ''); ?> - <?php echo htmlspecialchars($currentUser['role_name'] ?? ''); ?>
                        </span>
                    </div>
                    <a href="<?php echo $baseUrl; ?>/profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                        <i class="fas fa-user-circle w-5 mr-3 text-gray-400"></i>My Profile
                    </a>
                    <a href="<?php echo $baseUrl; ?>/change-password.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                        <i class="fas fa-key w-5 mr-3 text-gray-400"></i>Change Password
                    </a>
                    <hr class="my-2 border-gray-100">
                    <a href="<?php echo $baseUrl; ?>/logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
                        <i class="fas fa-sign-out-alt w-5 mr-3"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
function toggleDropdown() {
    document.getElementById('dropdownMenu').classList.toggle('hidden');
}
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('userDropdown');
    if (dropdown && !dropdown.contains(e.target)) {
        document.getElementById('dropdownMenu').classList.add('hidden');
    }
});

/**
 * Notification System
 * Requirements: 11.5 - Display notification type, related dispatch, and timestamp
 */
const NotificationManager = {
    baseUrl: '<?php echo $baseUrl; ?>',
    refreshInterval: null,
    
    /**
     * Initialize notification system
     */
    init: function() {
        this.loadUnreadCount();
        this.loadNotifications();
        // Refresh every 60 seconds
        this.refreshInterval = setInterval(() => {
            this.loadUnreadCount();
        }, 60000);
    },
    
    /**
     * Load unread notification count
     */
    loadUnreadCount: async function() {
        try {
            const response = await fetch(this.baseUrl + '/api/inventory/notifications/unread-count.php');
            const data = await response.json();
            
            if (data.success) {
                this.updateBadge(data.data.unread_count);
            }
        } catch (error) {
            console.error('Failed to load notification count:', error);
        }
    },
    
    /**
     * Update notification badge
     */
    updateBadge: function(count) {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    },
    
    /**
     * Load notifications list
     */
    loadNotifications: async function() {
        try {
            const response = await fetch(this.baseUrl + '/api/inventory/notifications/list.php?limit=10');
            const data = await response.json();
            
            if (data.success) {
                this.renderNotifications(data.data.notifications);
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
        }
    },
    
    /**
     * Render notifications in dropdown
     */
    renderNotifications: function(notifications) {
        const container = document.getElementById('notificationList');
        if (!container) return;
        
        if (!notifications || notifications.length === 0) {
            container.innerHTML = `
                <div class="px-4 py-8 text-center text-gray-500 text-sm">
                    <i class="fas fa-bell-slash text-2xl mb-2 text-gray-300"></i>
                    <p>No notifications</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        notifications.forEach(notification => {
            const isUnread = !notification.is_read;
            const icon = this.getNotificationIcon(notification.notification_type);
            const timeAgo = this.formatTimeAgo(notification.created_at);
            const bgClass = isUnread ? 'bg-blue-50' : '';
            
            html += `
                <div class="notification-item px-4 py-3 border-b border-gray-100 hover:bg-gray-50 cursor-pointer ${bgClass}" 
                     data-id="${notification.id}" 
                     data-dispatch-id="${notification.dispatch_id || ''}"
                     onclick="NotificationManager.handleNotificationClick(${notification.id}, ${notification.dispatch_id || 'null'})">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full ${icon.bgColor} flex items-center justify-center">
                            <i class="fas ${icon.icon} ${icon.textColor} text-xs"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 ${isUnread ? 'font-semibold' : ''}">${this.escapeHtml(notification.title)}</p>
                            <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">${this.escapeHtml(notification.message || '')}</p>
                            <p class="text-xs text-gray-400 mt-1">${timeAgo}</p>
                        </div>
                        ${isUnread ? '<span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0"></span>' : ''}
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    },
    
    /**
     * Get icon for notification type
     */
    getNotificationIcon: function(type) {
        const icons = {
            'pending_receive': { icon: 'fa-inbox', bgColor: 'bg-blue-100', textColor: 'text-blue-600' },
            'accepted': { icon: 'fa-check-circle', bgColor: 'bg-green-100', textColor: 'text-green-600' },
            'rejected': { icon: 'fa-times-circle', bgColor: 'bg-red-100', textColor: 'text-red-600' },
            'overdue': { icon: 'fa-clock', bgColor: 'bg-orange-100', textColor: 'text-orange-600' },
            'discrepancy': { icon: 'fa-exclamation-triangle', bgColor: 'bg-yellow-100', textColor: 'text-yellow-600' }
        };
        return icons[type] || { icon: 'fa-bell', bgColor: 'bg-gray-100', textColor: 'text-gray-600' };
    },
    
    /**
     * Format time ago
     */
    formatTimeAgo: function(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' min ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
        
        return date.toLocaleDateString();
    },
    
    /**
     * Handle notification click
     */
    handleNotificationClick: async function(notificationId, dispatchId) {
        // Mark as read
        try {
            await fetch(this.baseUrl + '/api/inventory/notifications/mark-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notificationId })
            });
            
            // Update badge
            this.loadUnreadCount();
            
            // Navigate to dispatch if available
            if (dispatchId) {
                window.location.href = this.baseUrl + '/inventory/dispatch.php?highlight=' + dispatchId;
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    },
    
    /**
     * Mark all notifications as read
     */
    markAllAsRead: async function() {
        try {
            const response = await fetch(this.baseUrl + '/api/inventory/notifications/mark-all-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (response.ok) {
                this.loadUnreadCount();
                this.loadNotifications();
            }
        } catch (error) {
            console.error('Failed to mark all as read:', error);
        }
    },
    
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml: function(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

/**
 * Toggle notification dropdown
 */
function toggleNotificationDropdown() {
    const menu = document.getElementById('notificationMenu');
    const isHidden = menu.classList.contains('hidden');
    
    // Close user dropdown if open
    document.getElementById('dropdownMenu').classList.add('hidden');
    
    menu.classList.toggle('hidden');
    
    // Refresh notifications when opening
    if (isHidden) {
        NotificationManager.loadNotifications();
    }
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsRead() {
    NotificationManager.markAllAsRead();
}

// Close notification dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown && !dropdown.contains(e.target)) {
        document.getElementById('notificationMenu').classList.add('hidden');
    }
});

// Initialize notification system when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    NotificationManager.init();
});


</script>

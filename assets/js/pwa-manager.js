/**
 * ADV Clarity Management System - PWA Manager
 * Handles PWA lifecycle management, service worker registration,
 * install prompts, offline state detection, and update notifications.
 */

class PWAManager {
    constructor() {
        this.serviceWorker = null;
        this.deferredPrompt = null;
        this.isOnline = navigator.onLine;
        this.updateAvailable = false;
        this.offlineActionQueue = [];
        
        this.init();
    }

    /**
     * Initialize PWA Manager
     */
    async init() {
        console.log('[PWA] Initializing PWA Manager...');
        
        // Track PWA initialization
        this.trackAnalyticsEvent('pwa_manager_init', {
            userAgent: navigator.userAgent,
            standalone: window.matchMedia('(display-mode: standalone)').matches,
            serviceWorkerSupported: 'serviceWorker' in navigator
        });
        
        // Load offline queue from storage
        this.loadOfflineQueue();
        
        // Register service worker
        await this.registerServiceWorker();
        
        // Set up event listeners
        this.setupEventListeners();
        
        // Initialize offline state detection
        this.initOfflineDetection();
        
        // Set up install prompt handling
        this.setupInstallPrompt();
        
        // Check for updates periodically
        this.setupUpdateChecking();
        
        // Enhanced update lifecycle management
        await this.manageUpdateLifecycle();
        
        console.log('[PWA] PWA Manager initialized successfully');
    }

    /**
     * Register service worker
     */
    async registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            console.warn('[PWA] Service workers not supported');
            return false;
        }

        try {
            const registrations = await navigator.serviceWorker.getRegistrations();
            for (const registration of registrations) {
                await registration.unregister();
                console.log('[PWA] Unregistered service worker via PWAManager');
            }
            return true;
        } catch (error) {
            console.error('[PWA] Service worker unregistration failed:', error);
            return false;
        }
    }

    /**
     * Handle service worker updates
     */
    handleServiceWorkerUpdate(registration) {
        const newWorker = registration.installing;
        
        newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed') {
                if (navigator.serviceWorker.controller) {
                    // New update available
                    this.updateAvailable = true;
                    this.showUpdateNotification();
                } else {
                    // First time installation
                    console.log('[PWA] Service worker installed for the first time');
                }
            }
        });
    }

    /**
     * Show update notification to user
     */
    showUpdateNotification() {
        const updateBanner = this.createUpdateBanner();
        document.body.appendChild(updateBanner);
        
        // Auto-hide after 10 seconds if user doesn't interact
        setTimeout(() => {
            if (updateBanner.parentNode) {
                updateBanner.remove();
            }
        }, 10000);
    }

    /**
     * Create update notification banner
     */
    createUpdateBanner() {
        const banner = document.createElement('div');
        banner.id = 'pwa-update-banner';
        banner.className = 'fixed top-0 left-0 right-0 bg-blue-600 text-white p-4 z-50 shadow-lg';
        banner.innerHTML = `
            <div class="container mx-auto flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-download mr-2"></i>
                    <span>A new version of the app is available!</span>
                </div>
                <div class="flex space-x-2">
                    <button id="pwa-update-btn" class="bg-white text-blue-600 px-4 py-2 rounded hover:bg-gray-100">
                        Update Now
                    </button>
                    <button id="pwa-dismiss-btn" class="text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;

        // Add event listeners
        banner.querySelector('#pwa-update-btn').addEventListener('click', () => {
            this.applyUpdate();
            banner.remove();
        });

        banner.querySelector('#pwa-dismiss-btn').addEventListener('click', () => {
            banner.remove();
        });

        return banner;
    }

    /**
     * Apply service worker update
     */
    async applyUpdate() {
        if (!this.serviceWorker || !this.serviceWorker.waiting) {
            return;
        }

        // Tell the waiting service worker to skip waiting
        this.serviceWorker.waiting.postMessage({ type: 'SKIP_WAITING' });
        
        // Reload the page to use the new service worker
        window.location.reload();
    }

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Online/offline events
        window.addEventListener('online', () => this.handleOnlineStatusChange(true));
        window.addEventListener('offline', () => this.handleOnlineStatusChange(false));
        
        // Before install prompt
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            
            // Only show install button if user hasn't dismissed it too many times
            if (this.shouldShowInstallPrompt()) {
                console.log('[PWA] Install prompt available and eligible');
                this.showInstallButton();
            } else {
                console.log('[PWA] Install prompt available but not eligible to show');
            }
        });

        // App installed
        window.addEventListener('appinstalled', () => {
            console.log('[PWA] App installed successfully');
            this.hideInstallButton();
            this.deferredPrompt = null;
            
            // Track successful installation
            this.trackInstallationSuccess();
            
            // Show success message
            this.showUserMessage('Installation Complete', 
                'The app has been installed successfully! You can now access it from your device\'s home screen.', 
                'success');
        });

        // Service worker messages
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                this.handleServiceWorkerMessage(event);
            });
        }
    }

    /**
     * Handle online/offline status changes
     */
    handleOnlineStatusChange(isOnline) {
        this.isOnline = isOnline;
        
        if (isOnline) {
            console.log('[PWA] Connection restored');
            this.hideOfflineIndicator();
            this.syncOfflineActions();
            
            // Track analytics event
            this.trackAnalyticsEvent('connection_restored', {
                queueLength: this.offlineActionQueue.length,
                timestamp: Date.now()
            });
        } else {
            console.log('[PWA] Connection lost');
            this.showOfflineIndicator();
            
            // Track analytics event
            this.trackAnalyticsEvent('connection_lost', {
                timestamp: Date.now()
            });
        }

        // Dispatch custom event for other components to listen
        window.dispatchEvent(new CustomEvent('pwa-connection-change', {
            detail: { isOnline }
        }));
    }

    /**
     * Initialize offline state detection
     */
    initOfflineDetection() {
        // Show initial state
        if (!this.isOnline) {
            this.showOfflineIndicator();
        }

        // Monitor network requests to detect connectivity issues
        this.setupNetworkMonitoring();
    }

    /**
     * Set up network monitoring for more accurate offline detection
     */
    setupNetworkMonitoring() {
        // Override fetch to monitor network requests
        const originalFetch = window.fetch;
        
        window.fetch = async (...args) => {
            try {
                const response = await originalFetch(...args);
                
                // If we get a response, we're likely online
                if (!this.isOnline && response.ok) {
                    this.handleOnlineStatusChange(true);
                }
                
                return response;
            } catch (error) {
                // Network error might indicate offline status
                if (this.isOnline && error.name === 'TypeError') {
                    this.handleOnlineStatusChange(false);
                }
                throw error;
            }
        };
    }

    /**
     * Show offline indicator
     */
    showOfflineIndicator() {
        let indicator = document.getElementById('pwa-offline-indicator');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'pwa-offline-indicator';
            indicator.className = 'fixed bottom-4 right-4 bg-red-600 text-white px-4 py-2 rounded-lg shadow-lg z-40';
            indicator.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-wifi-slash mr-2"></i>
                    <span>You're offline</span>
                </div>
            `;
            document.body.appendChild(indicator);
        }
        
        indicator.style.display = 'block';
    }

    /**
     * Hide offline indicator
     */
    hideOfflineIndicator() {
        const indicator = document.getElementById('pwa-offline-indicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }

    /**
     * Set up install prompt handling
     */
    setupInstallPrompt() {
        // Check if app is already installed
        if (window.matchMedia('(display-mode: standalone)').matches) {
            console.log('[PWA] App is running in standalone mode');
            return;
        }

        // Create install button (initially hidden)
        this.createInstallButton();
    }

    /**
     * Create install button
     */
    createInstallButton() {
        // Check if install button already exists in header
        const existingBtn = document.getElementById('pwa-install-btn');
        if (existingBtn) {
            // Use existing button from header
            existingBtn.addEventListener('click', () => this.promptInstall());
            return;
        }
        
        // Create fallback install button if header button doesn't exist
        const installBtn = document.createElement('button');
        installBtn.id = 'pwa-install-btn-fallback';
        installBtn.className = 'fixed bottom-4 left-4 bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg z-40 hidden';
        installBtn.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-download mr-2"></i>
                <span>Install App</span>
            </div>
        `;
        
        installBtn.addEventListener('click', () => this.promptInstall());
        document.body.appendChild(installBtn);
    }

    /**
     * Show install button
     */
    showInstallButton() {
        // Try header button first
        const headerBtn = document.getElementById('pwa-install-btn');
        if (headerBtn) {
            headerBtn.style.display = 'flex';
            return;
        }
        
        // Fallback to floating button
        const fallbackBtn = document.getElementById('pwa-install-btn-fallback');
        if (fallbackBtn) {
            fallbackBtn.classList.remove('hidden');
        }
    }

    /**
     * Hide install button
     */
    hideInstallButton() {
        // Hide header button
        const headerBtn = document.getElementById('pwa-install-btn');
        if (headerBtn) {
            headerBtn.style.display = 'none';
        }
        
        // Hide fallback button
        const fallbackBtn = document.getElementById('pwa-install-btn-fallback');
        if (fallbackBtn) {
            fallbackBtn.classList.add('hidden');
        }
    }

    /**
     * Prompt user to install the app
     */
    async promptInstall() {
        if (!this.deferredPrompt) {
            console.log('[PWA] Install prompt not available');
            this.showInstallUnavailableMessage();
            return;
        }

        try {
            // Show the install prompt
            this.deferredPrompt.prompt();
            
            // Wait for user response
            const { outcome } = await this.deferredPrompt.userChoice;
            
            console.log('[PWA] Install prompt outcome:', outcome);
            
            if (outcome === 'accepted') {
                console.log('[PWA] User accepted the install prompt');
                this.trackInstallationSuccess();
                this.showInstallationPendingMessage();
            } else {
                console.log('[PWA] User dismissed the install prompt');
                this.trackInstallationDismissal();
            }
            
            // Clear the deferred prompt
            this.deferredPrompt = null;
            this.hideInstallButton();
        } catch (error) {
            console.error('[PWA] Install prompt failed:', error);
            this.showInstallErrorMessage(error.message);
        }
    }

    /**
     * Set up periodic update checking
     */
    setupUpdateChecking() {
        // Check for updates every 30 minutes
        setInterval(() => {
            this.checkForUpdates();
        }, 30 * 60 * 1000);
        
        // Also check when page becomes visible
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.checkForUpdates();
            }
        });
    }

    /**
     * Check for service worker updates
     */
    async checkForUpdates() {
        if (!this.serviceWorker) {
            return;
        }

        try {
            await this.serviceWorker.update();
            console.log('[PWA] Checked for updates');
        } catch (error) {
            console.error('[PWA] Update check failed:', error);
        }
    }

    /**
     * Queue action for offline sync
     */
    queueOfflineAction(action) {
        const queuedAction = {
            id: this.generateId(),
            timestamp: Date.now(),
            retryCount: 0,
            maxRetries: 3,
            ...action
        };
        
        this.offlineActionQueue.push(queuedAction);
        this.saveOfflineQueue();
        
        console.log('[PWA] Queued offline action:', queuedAction.id);
        
        // Track analytics event
        this.trackAnalyticsEvent('offline_action_queued', {
            actionType: action.method || 'unknown',
            endpoint: action.endpoint,
            queueLength: this.offlineActionQueue.length
        });
        
        // Register background sync if available
        this.registerBackgroundSync();
        
        // Try to sync immediately if online
        if (this.isOnline) {
            this.syncOfflineActions();
        }
    }

    /**
     * Register background sync for offline actions
     */
    async registerBackgroundSync() {
        if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
            try {
                const registration = await navigator.serviceWorker.ready;
                await registration.sync.register('offline-actions');
                console.log('[PWA] Background sync registered');
            } catch (error) {
                console.error('[PWA] Background sync registration failed:', error);
            }
        } else {
            console.log('[PWA] Background sync not supported');
        }
    }

    /**
     * Sync offline actions when connection is restored
     */
    async syncOfflineActions() {
        if (!this.isOnline || this.offlineActionQueue.length === 0) {
            return;
        }

        console.log('[PWA] Syncing offline actions...');
        
        const actionsToSync = [...this.offlineActionQueue];
        
        for (const action of actionsToSync) {
            try {
                await this.processOfflineAction(action);
                
                // Remove successful action from queue
                this.offlineActionQueue = this.offlineActionQueue.filter(a => a.id !== action.id);
                console.log('[PWA] Synced offline action:', action.id);
                
            } catch (error) {
                console.error('[PWA] Failed to sync action:', action.id, error);
                
                // Increment retry count
                action.retryCount++;
                
                // Remove if max retries exceeded
                if (action.retryCount >= action.maxRetries) {
                    this.offlineActionQueue = this.offlineActionQueue.filter(a => a.id !== action.id);
                    console.warn('[PWA] Removed failed action after max retries:', action.id);
                }
            }
        }
        
        this.saveOfflineQueue();
        
        if (this.offlineActionQueue.length === 0) {
            console.log('[PWA] All offline actions synced successfully');
        }
    }

    /**
     * Process a single offline action
     */
    async processOfflineAction(action) {
        const response = await fetch(action.endpoint, {
            method: action.method || 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CRM?.csrfToken || '',
                ...action.headers
            },
            body: action.data ? JSON.stringify(action.data) : undefined
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return response.json();
    }

    /**
     * Save offline queue to localStorage
     */
    saveOfflineQueue() {
        try {
            localStorage.setItem('pwa-offline-queue', JSON.stringify(this.offlineActionQueue));
        } catch (error) {
            console.error('[PWA] Failed to save offline queue:', error);
        }
    }

    /**
     * Load offline queue from localStorage
     */
    loadOfflineQueue() {
        try {
            const saved = localStorage.getItem('pwa-offline-queue');
            if (saved) {
                this.offlineActionQueue = JSON.parse(saved);
                console.log('[PWA] Loaded offline queue:', this.offlineActionQueue.length, 'actions');
            }
        } catch (error) {
            console.error('[PWA] Failed to load offline queue:', error);
            this.offlineActionQueue = [];
        }
    }

    /**
     * Handle messages from service worker
     */
    handleServiceWorkerMessage(event) {
        const { data } = event;
        
        if (data && data.type) {
            switch (data.type) {
                case 'CACHE_UPDATED':
                    console.log('[PWA] Cache updated:', data.cacheName);
                    break;
                    
                case 'OFFLINE_FALLBACK':
                    console.log('[PWA] Serving offline fallback for:', data.url);
                    break;
                    
                case 'QUEUE_UPDATED':
                    console.log('[PWA] Offline queue updated, length:', data.queueLength);
                    this.updateQueueIndicators(data.queueLength);
                    break;
                    
                case 'GET_STORAGE':
                    // Handle storage request from service worker
                    this.handleStorageRequest(data, event.ports[0]);
                    break;
                    
                case 'SET_STORAGE':
                    // Handle storage set request from service worker
                    this.handleStorageSetRequest(data, event.ports[0]);
                    break;
                    
                case 'NOTIFICATION_CLICK':
                    console.log('[PWA] Notification clicked:', data.data);
                    this.handleNotificationClick(data.data);
                    break;
                    
                case 'NAVIGATE_TO':
                    console.log('[PWA] Navigate to:', data.url);
                    window.location.href = data.url;
                    break;
                    
                case 'SEND_ANALYTICS':
                    // Handle analytics data from service worker
                    this.sendAnalyticsData(data.eventType, data.data);
                    break;
                    
                default:
                    console.log('[PWA] Unknown message from service worker:', data);
            }
        }
    }

    /**
     * Handle storage request from service worker
     */
    handleStorageRequest(data, port) {
        try {
            const value = localStorage.getItem(data.key);
            port.postMessage(value);
        } catch (error) {
            console.error('[PWA] Failed to get storage:', error);
            port.postMessage(null);
        }
    }

    /**
     * Handle storage set request from service worker
     */
    handleStorageSetRequest(data, port) {
        try {
            localStorage.setItem(data.key, data.value);
            port.postMessage(true);
        } catch (error) {
            console.error('[PWA] Failed to set storage:', error);
            port.postMessage(false);
        }
    }

    /**
     * Handle notification click from service worker
     */
    handleNotificationClick(data) {
        // Handle notification-specific actions
        if (data.dispatch_id) {
            // Navigate to dispatch page
            window.location.href = `${window.baseUrl || ''}/inventory/dispatch.php?highlight=${data.dispatch_id}`;
        } else if (data.url) {
            // Navigate to specified URL
            window.location.href = data.url;
        }
    }

    /**
     * Update queue indicators in UI
     */
    updateQueueIndicators(queueLength) {
        // Update sidebar queue indicator
        const queueIndicator = document.querySelector('.offline-queue-count');
        if (queueIndicator) {
            queueIndicator.textContent = queueLength;
            queueIndicator.style.display = queueLength > 0 ? 'inline' : 'none';
        }
        
        // Update any other queue indicators
        const queueElements = document.querySelectorAll('[data-queue-count]');
        queueElements.forEach(element => {
            element.textContent = queueLength;
            element.style.display = queueLength > 0 ? 'inline' : 'none';
        });
    }

    /**
     * Generate unique ID
     */
    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    /**
     * Get current online status
     */
    getOnlineStatus() {
        return this.isOnline;
    }

    /**
     * Get offline queue length
     */
    getOfflineQueueLength() {
        return this.offlineActionQueue.length;
    }

    /**
     * Clear offline queue (for testing/debugging)
     */
    clearOfflineQueue() {
        this.offlineActionQueue = [];
        this.saveOfflineQueue();
        console.log('[PWA] Offline queue cleared');
    }

    /**
     * Show notification settings UI
     */
    showNotificationSettings() {
        const modal = this.createNotificationSettingsModal();
        document.body.appendChild(modal);
        
        // Initialize current state
        this.updateNotificationSettingsUI(modal);
    }

    /**
     * Create notification settings modal
     */
    createNotificationSettingsModal() {
        const modal = document.createElement('div');
        modal.id = 'pwa-notification-settings';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Notification Settings</h3>
                    <button id="close-notification-settings" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="font-medium">Push Notifications</label>
                            <p class="text-sm text-gray-600">Receive notifications even when the app is closed</p>
                        </div>
                        <button id="toggle-push-notifications" class="bg-gray-300 rounded-full w-12 h-6 relative">
                            <div id="toggle-switch" class="bg-white w-5 h-5 rounded-full absolute top-0.5 left-0.5 transition-transform"></div>
                        </button>
                    </div>
                    
                    <div id="notification-status" class="text-sm text-gray-600"></div>
                    
                    <div class="flex space-x-2 pt-4">
                        <button id="test-notification" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">
                            Test Notification
                        </button>
                        <button id="close-settings" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded hover:bg-gray-400">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Add event listeners
        modal.querySelector('#close-notification-settings').addEventListener('click', () => {
            modal.remove();
        });

        modal.querySelector('#close-settings').addEventListener('click', () => {
            modal.remove();
        });

        modal.querySelector('#toggle-push-notifications').addEventListener('click', () => {
            this.togglePushNotifications(modal);
        });

        modal.querySelector('#test-notification').addEventListener('click', () => {
            this.sendTestNotification();
        });

        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });

        return modal;
    }

    /**
     * Update notification settings UI
     */
    async updateNotificationSettingsUI(modal) {
        const toggle = modal.querySelector('#toggle-push-notifications');
        const toggleSwitch = modal.querySelector('#toggle-switch');
        const status = modal.querySelector('#notification-status');

        const isSubscribed = await this.checkPushSubscription();
        const permission = Notification.permission;

        if (isSubscribed) {
            toggle.classList.add('bg-blue-600');
            toggle.classList.remove('bg-gray-300');
            toggleSwitch.style.transform = 'translateX(24px)';
            status.textContent = 'Push notifications are enabled';
            status.className = 'text-sm text-green-600';
        } else {
            toggle.classList.remove('bg-blue-600');
            toggle.classList.add('bg-gray-300');
            toggleSwitch.style.transform = 'translateX(0)';
            
            if (permission === 'denied') {
                status.textContent = 'Push notifications are blocked. Please enable them in your browser settings.';
                status.className = 'text-sm text-red-600';
            } else {
                status.textContent = 'Push notifications are disabled';
                status.className = 'text-sm text-gray-600';
            }
        }
    }

    /**
     * Toggle push notifications
     */
    async togglePushNotifications(modal) {
        const isSubscribed = await this.checkPushSubscription();

        if (isSubscribed) {
            await this.unsubscribeFromPush();
        } else {
            await this.requestNotificationPermission();
        }

        // Update UI
        this.updateNotificationSettingsUI(modal);
    }

    /**
     * Send test notification
     */
    sendTestNotification() {
        if (Notification.permission === 'granted') {
            this.showNotification('Test Notification', {
                body: 'This is a test notification from ADV Clarity System',
                icon: '/assets/icons/icon-192.png',
                tag: 'test-notification'
            });
        } else {
            alert('Please enable notifications first');
        }
    }

    /**
     * Send message to service worker
     */
    sendMessageToServiceWorker(message) {
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage(message);
        }
    }

    /**
     * Get push notification settings
     */
    async getPushSettings() {
        return {
            supported: 'Notification' in window && 'PushManager' in window,
            permission: Notification.permission,
            subscribed: await this.checkPushSubscription()
        };
    }

    /**
     * Request notification permission and set up push notifications
     */
    async requestNotificationPermission() {
        if (!('Notification' in window)) {
            console.warn('[PWA] Notifications not supported');
            return false;
        }

        if (Notification.permission === 'granted') {
            await this.setupPushNotifications();
            return true;
        }

        if (Notification.permission === 'denied') {
            console.warn('[PWA] Notification permission denied');
            return false;
        }

        try {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                await this.setupPushNotifications();
                return true;
            }
            return false;
        } catch (error) {
            console.error('[PWA] Failed to request notification permission:', error);
            return false;
        }
    }

    /**
     * Set up push notifications
     */
    async setupPushNotifications() {
        if (!this.serviceWorker || !('PushManager' in window)) {
            console.warn('[PWA] Push notifications not supported');
            return false;
        }

        try {
            // Get VAPID public key from server
            const vapidResponse = await fetch('/api/notifications/vapid-key');
            const { vapidKey } = await vapidResponse.json();

            // Subscribe to push notifications
            const subscription = await this.serviceWorker.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(vapidKey)
            });

            // Send subscription to server
            const response = await fetch('/api/notifications/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CRM?.csrfToken || ''
                },
                body: JSON.stringify({ subscription })
            });

            if (response.ok) {
                console.log('[PWA] Push notifications set up successfully');
                return true;
            } else {
                console.error('[PWA] Failed to save push subscription');
                return false;
            }
        } catch (error) {
            console.error('[PWA] Failed to set up push notifications:', error);
            return false;
        }
    }

    /**
     * Unsubscribe from push notifications
     */
    async unsubscribeFromPush() {
        if (!this.serviceWorker || !('PushManager' in window)) {
            return false;
        }

        try {
            const subscription = await this.serviceWorker.pushManager.getSubscription();
            
            if (subscription) {
                // Unsubscribe from browser
                await subscription.unsubscribe();

                // Remove from server
                await fetch('/api/notifications/unsubscribe', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.CRM?.csrfToken || ''
                    },
                    body: JSON.stringify({ endpoint: subscription.endpoint })
                });

                console.log('[PWA] Unsubscribed from push notifications');
                return true;
            }
        } catch (error) {
            console.error('[PWA] Failed to unsubscribe from push notifications:', error);
        }

        return false;
    }

    /**
     * Check push subscription status
     */
    async checkPushSubscription() {
        if (!this.serviceWorker || !('PushManager' in window)) {
            return false;
        }

        try {
            const subscription = await this.serviceWorker.pushManager.getSubscription();
            return !!subscription;
        } catch (error) {
            console.error('[PWA] Failed to check push subscription:', error);
            return false;
        }
    }

    /**
     * Convert VAPID key to Uint8Array
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    /**
     * Show local notification
     */
    showNotification(title, options = {}) {
        if (Notification.permission !== 'granted') {
            console.warn('[PWA] Notification permission not granted');
            return;
        }

        const notification = new Notification(title, {
            icon: '/assets/icons/icon-192.png',
            badge: '/assets/icons/icon-72.png',
            ...options
        });

        // Auto-close after 5 seconds if not interacted with
        setTimeout(() => {
            notification.close();
        }, 5000);

        return notification;
    }

    /**
     * Show install unavailable message
     */
    showInstallUnavailableMessage() {
        this.showUserMessage('App Installation', 
            'The app is already installed or installation is not available on this device.', 
            'info');
    }

    /**
     * Show installation pending message
     */
    showInstallationPendingMessage() {
        this.showUserMessage('App Installation', 
            'Installation started! The app will be available in your device\'s app drawer shortly.', 
            'success');
    }

    /**
     * Show install error message
     */
    showInstallErrorMessage(error) {
        this.showUserMessage('Installation Error', 
            `Failed to install the app: ${error}`, 
            'error');
    }

    /**
     * Show user message with toast notification
     */
    showUserMessage(title, message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 max-w-sm p-4 rounded-lg shadow-lg z-50 ${this.getToastClasses(type)}`;
        toast.innerHTML = `
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas ${this.getToastIcon(type)} text-lg"></i>
                </div>
                <div class="ml-3 flex-1">
                    <h4 class="text-sm font-semibold">${title}</h4>
                    <p class="text-sm mt-1">${message}</p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-sm opacity-70 hover:opacity-100">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 5000);
    }

    /**
     * Get toast CSS classes based on type
     */
    getToastClasses(type) {
        const classes = {
            'info': 'bg-blue-600 text-white',
            'success': 'bg-green-600 text-white',
            'error': 'bg-red-600 text-white',
            'warning': 'bg-yellow-600 text-white'
        };
        return classes[type] || classes.info;
    }

    /**
     * Get toast icon based on type
     */
    getToastIcon(type) {
        const icons = {
            'info': 'fa-info-circle',
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-circle',
            'warning': 'fa-exclamation-triangle'
        };
        return icons[type] || icons.info;
    }

    /**
     * Track installation success
     */
    trackInstallationSuccess() {
        try {
            localStorage.setItem('pwa-install-accepted', Date.now().toString());
            console.log('[PWA] Installation acceptance tracked');
            
            // Track analytics event
            this.trackAnalyticsEvent('pwa_install_accepted', {
                timestamp: Date.now(),
                userAgent: navigator.userAgent,
                standalone: window.matchMedia('(display-mode: standalone)').matches
            });
        } catch (error) {
            console.error('[PWA] Failed to track installation:', error);
        }
    }

    /**
     * Track installation dismissal
     */
    trackInstallationDismissal() {
        try {
            const dismissals = parseInt(localStorage.getItem('pwa-install-dismissals') || '0') + 1;
            localStorage.setItem('pwa-install-dismissals', dismissals.toString());
            localStorage.setItem('pwa-last-dismissal', Date.now().toString());
            console.log('[PWA] Installation dismissal tracked:', dismissals);
            
            // Track analytics event
            this.trackAnalyticsEvent('pwa_install_dismissed', {
                dismissalCount: dismissals,
                timestamp: Date.now()
            });
        } catch (error) {
            console.error('[PWA] Failed to track dismissal:', error);
        }
    }

    /**
     * Check if install prompt should be shown based on user history
     */
    shouldShowInstallPrompt() {
        try {
            // Don't show if already installed
            if (window.matchMedia('(display-mode: standalone)').matches) {
                return false;
            }

            // Don't show if user accepted installation recently
            const installAccepted = localStorage.getItem('pwa-install-accepted');
            if (installAccepted) {
                return false;
            }

            // Check dismissal history
            const dismissals = parseInt(localStorage.getItem('pwa-install-dismissals') || '0');
            const lastDismissal = parseInt(localStorage.getItem('pwa-last-dismissal') || '0');
            const now = Date.now();
            const daysSinceLastDismissal = (now - lastDismissal) / (1000 * 60 * 60 * 24);

            // Progressive backoff: wait longer after each dismissal
            const waitDays = Math.min(dismissals * 7, 30); // Max 30 days
            
            return daysSinceLastDismissal >= waitDays;
        } catch (error) {
            console.error('[PWA] Error checking install prompt eligibility:', error);
            return true; // Default to showing prompt
        }
    }

    /**
     * Enhanced update lifecycle management
     */
    async manageUpdateLifecycle() {
        if (!this.serviceWorker) {
            return;
        }

        try {
            // Check if there's a waiting service worker
            if (this.serviceWorker.waiting) {
                this.handleWaitingServiceWorker(this.serviceWorker.waiting);
            }

            // Listen for new service worker installations
            this.serviceWorker.addEventListener('updatefound', () => {
                const newWorker = this.serviceWorker.installing;
                this.handleNewServiceWorker(newWorker);
            });

            // Handle controller changes (when new SW takes over)
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                console.log('[PWA] New service worker took control');
                this.handleControllerChange();
            });

        } catch (error) {
            console.error('[PWA] Error managing update lifecycle:', error);
        }
    }

    /**
     * Handle waiting service worker
     */
    handleWaitingServiceWorker(worker) {
        console.log('[PWA] Service worker is waiting');
        this.updateAvailable = true;
        this.showUpdateNotification();
    }

    /**
     * Handle new service worker installation
     */
    handleNewServiceWorker(worker) {
        console.log('[PWA] New service worker installing');
        
        worker.addEventListener('statechange', () => {
            if (worker.state === 'installed') {
                if (navigator.serviceWorker.controller) {
                    // New update available
                    console.log('[PWA] New update available');
                    this.updateAvailable = true;
                    this.showUpdateNotification();
                } else {
                    // First time installation
                    console.log('[PWA] Service worker installed for the first time');
                    this.handleFirstTimeInstallation();
                }
            }
        });
    }

    /**
     * Handle controller change (new SW takes over)
     */
    handleControllerChange() {
        // Show success message
        this.showUserMessage('App Updated', 
            'The app has been updated to the latest version!', 
            'success');
        
        // Clear update flag
        this.updateAvailable = false;
        
        // Optionally refresh critical data
        this.refreshCriticalData();
    }

    /**
     * Handle first time service worker installation
     */
    handleFirstTimeInstallation() {
        console.log('[PWA] First time PWA setup complete');
        
        // Show welcome message
        this.showUserMessage('PWA Ready', 
            'The app is now ready for offline use!', 
            'success');
    }

    /**
     * Refresh critical data after update
     */
    async refreshCriticalData() {
        try {
            // Refresh current page data if offline data manager is available
            if (window.offlineDataManager) {
                await window.offlineDataManager.refreshCriticalData();
            }
            
            // Dispatch event for other components to refresh
            window.dispatchEvent(new CustomEvent('pwa-app-updated'));
            
        } catch (error) {
            console.error('[PWA] Error refreshing critical data:', error);
        }
    }

    /**
     * Track analytics event
     */
    async trackAnalyticsEvent(eventType, eventData = {}) {
        try {
            if (!window.CRM?.baseUrl) {
                return;
            }

            await fetch(`${window.CRM.baseUrl}/api/analytics/pwa-usage.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CRM?.csrfToken || ''
                },
                body: JSON.stringify({
                    event: eventType,
                    data: eventData
                })
            });

            console.log('[PWA] Analytics event tracked:', eventType);
        } catch (error) {
            console.error('[PWA] Failed to track analytics event:', error);
        }
    }

    /**
     * Send analytics data from service worker
     */
    async sendAnalyticsData(eventType, eventData) {
        try {
            await this.trackAnalyticsEvent(eventType, eventData);
        } catch (error) {
            console.error('[PWA] Failed to send analytics data:', error);
        }
    }

    /**
     * Initialize lazy loading for non-critical resources
     */
    initializeLazyLoading() {
        // Lazy load images
        this.setupImageLazyLoading();
        
        // Lazy load non-critical JavaScript modules
        this.setupModuleLazyLoading();
        
        // Lazy load non-critical CSS
        this.setupCSSLazyLoading();
    }

    /**
     * Set up image lazy loading with Intersection Observer
     */
    setupImageLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        const src = img.dataset.src;
                        
                        if (src) {
                            img.src = src;
                            img.removeAttribute('data-src');
                            observer.unobserve(img);
                            
                            // Track lazy load event
                            this.trackAnalyticsEvent('image_lazy_loaded', {
                                src: src,
                                timestamp: Date.now()
                            });
                        }
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });

            // Observe all images with data-src attribute
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });

            // Set up mutation observer for dynamically added images
            const mutationObserver = new MutationObserver(mutations => {
                mutations.forEach(mutation => {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) { // Element node
                            const lazyImages = node.querySelectorAll ? 
                                node.querySelectorAll('img[data-src]') : [];
                            lazyImages.forEach(img => imageObserver.observe(img));
                        }
                    });
                });
            });

            mutationObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }

    /**
     * Set up lazy loading for JavaScript modules
     */
    setupModuleLazyLoading() {
        // Define non-critical modules that can be loaded on demand
        const lazyModules = {
            'chart': () => import('/assets/js/chart.min.js'),
            'datepicker': () => import('/assets/js/datepicker.js'),
            'fileupload': () => import('/assets/js/fileupload.js')
        };

        // Create global function to load modules on demand
        window.loadModule = async (moduleName) => {
            if (lazyModules[moduleName]) {
                try {
                    const module = await lazyModules[moduleName]();
                    console.log('[PWA] Lazy loaded module:', moduleName);
                    
                    this.trackAnalyticsEvent('module_lazy_loaded', {
                        module: moduleName,
                        timestamp: Date.now()
                    });
                    
                    return module;
                } catch (error) {
                    console.error('[PWA] Failed to lazy load module:', moduleName, error);
                    throw error;
                }
            } else {
                throw new Error(`Unknown module: ${moduleName}`);
            }
        };
    }

    /**
     * Set up lazy loading for non-critical CSS
     */
    setupCSSLazyLoading() {
        // Load non-critical CSS after page load
        window.addEventListener('load', () => {
            const nonCriticalCSS = [
                '/assets/css/print.css',
                '/assets/css/animations.css'
            ];

            nonCriticalCSS.forEach(href => {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                link.media = 'print';
                link.onload = () => {
                    link.media = 'all';
                    this.trackAnalyticsEvent('css_lazy_loaded', {
                        href: href,
                        timestamp: Date.now()
                    });
                };
                document.head.appendChild(link);
            });
        });
    }

    /**
     * Get performance metrics
     */
    async getPerformanceMetrics() {
        try {
            // Get navigation timing
            const navigation = performance.getEntriesByType('navigation')[0];
            
            // Get resource timing
            const resources = performance.getEntriesByType('resource');
            
            // Calculate metrics
            const metrics = {
                // Page load metrics
                domContentLoaded: navigation ? navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart : 0,
                loadComplete: navigation ? navigation.loadEventEnd - navigation.loadEventStart : 0,
                firstPaint: this.getFirstPaint(),
                
                // Resource metrics
                totalResources: resources.length,
                cachedResources: resources.filter(r => r.transferSize === 0).length,
                
                // PWA specific metrics
                serviceWorkerActive: !!navigator.serviceWorker.controller,
                offlineQueueLength: this.getOfflineQueueLength(),
                
                // Cache metrics from service worker
                cacheMetrics: await this.getCacheMetrics(),
                
                timestamp: Date.now()
            };
            
            return metrics;
        } catch (error) {
            console.error('[PWA] Failed to get performance metrics:', error);
            return null;
        }
    }

    /**
     * Get First Paint timing
     */
    getFirstPaint() {
        try {
            const paintEntries = performance.getEntriesByType('paint');
            const firstPaint = paintEntries.find(entry => entry.name === 'first-paint');
            return firstPaint ? firstPaint.startTime : 0;
        } catch (error) {
            return 0;
        }
    }

    /**
     * Get cache metrics from service worker
     */
    async getCacheMetrics() {
        return new Promise((resolve) => {
            if (!navigator.serviceWorker.controller) {
                resolve({});
                return;
            }

            const messageChannel = new MessageChannel();
            messageChannel.port1.onmessage = (event) => {
                resolve(event.data || {});
            };

            navigator.serviceWorker.controller.postMessage({
                type: 'GET_CACHE_METRICS'
            }, [messageChannel.port2]);

            // Timeout after 5 seconds
            setTimeout(() => resolve({}), 5000);
        });
    }

    /**
     * Optimize PWA performance
     */
    async optimizePerformance() {
        try {
            console.log('[PWA] Starting performance optimization...');
            
            // Initialize lazy loading
            this.initializeLazyLoading();
            
            // Preload critical resources
            await this.preloadCriticalResources();
            
            // Optimize service worker cache
            this.optimizeServiceWorkerCache();
            
            // Set up performance monitoring
            this.setupPerformanceMonitoring();
            
            console.log('[PWA] Performance optimization complete');
            
            this.trackAnalyticsEvent('performance_optimization_complete', {
                timestamp: Date.now()
            });
            
        } catch (error) {
            console.error('[PWA] Performance optimization failed:', error);
        }
    }

    /**
     * Preload critical resources
     */
    async preloadCriticalResources() {
        const criticalResources = [
            '/assets/css/tailwind.css',
            '/assets/js/app.js',
            '/dashboard.php'
        ];

        const preloadPromises = criticalResources.map(url => {
            return new Promise((resolve) => {
                const link = document.createElement('link');
                link.rel = 'preload';
                link.as = this.getResourceType(url);
                link.href = url;
                link.onload = resolve;
                link.onerror = resolve; // Don't fail on preload errors
                document.head.appendChild(link);
            });
        });

        await Promise.all(preloadPromises);
        console.log('[PWA] Critical resources preloaded');
    }

    /**
     * Get resource type for preloading
     */
    getResourceType(url) {
        if (url.endsWith('.css')) return 'style';
        if (url.endsWith('.js')) return 'script';
        if (url.endsWith('.php') || url.endsWith('.html')) return 'document';
        return 'fetch';
    }

    /**
     * Optimize service worker cache
     */
    optimizeServiceWorkerCache() {
        if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'OPTIMIZE_CACHE'
            });
        }
    }

    /**
     * Set up performance monitoring
     */
    setupPerformanceMonitoring() {
        // Monitor page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.trackAnalyticsEvent('page_visible', {
                    timestamp: Date.now()
                });
            }
        });

        // Monitor network changes
        window.addEventListener('online', () => {
            this.trackAnalyticsEvent('network_online', {
                timestamp: Date.now()
            });
        });

        window.addEventListener('offline', () => {
            this.trackAnalyticsEvent('network_offline', {
                timestamp: Date.now()
            });
        });

        // Send performance metrics periodically
        setInterval(async () => {
            const metrics = await this.getPerformanceMetrics();
            if (metrics) {
                this.trackAnalyticsEvent('performance_metrics', metrics);
            }
        }, 5 * 60 * 1000); // Every 5 minutes
    }
}

// Initialize PWA Manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.pwaManager = new PWAManager();
    
    // Initialize performance optimizations
    window.pwaManager.optimizePerformance();
    
    // Integrate with existing CRM object if available
    if (window.CRM) {
        // Override CRM.ajax to queue offline actions
        const originalAjax = window.CRM.ajax.bind(window.CRM);
        
        window.CRM.ajax = async function(url, options = {}) {
            try {
                return await originalAjax(url, options);
            } catch (error) {
                // If offline and this is a modifying request, queue it
                if (!window.pwaManager.getOnlineStatus() && 
                    options.method && 
                    ['POST', 'PUT', 'DELETE'].includes(options.method.toUpperCase())) {
                    
                    window.pwaManager.queueOfflineAction({
                        endpoint: window.CRM.baseUrl + url,
                        method: options.method,
                        data: options.body,
                        headers: options.headers
                    });
                    
                    // Show user feedback
                    window.CRM.showAlert('Action queued for when you\'re back online', 'info');
                    
                    // Return a resolved promise to prevent UI errors
                    return { success: true, queued: true };
                }
                
                throw error;
            }
        };
    }
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PWAManager;
}
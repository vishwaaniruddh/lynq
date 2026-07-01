/**
 * ADV Clarity Management System - Offline Utilities
 * Utilities for handling offline functionality, form queuing, and data caching
 */

class OfflineUtils {
    constructor() {
        this.storageKeys = {
            offlineQueue: 'clarity-offline-queue',
            cachedData: 'clarity-cached-data',
            formData: 'clarity-form-data',
            lastSync: 'clarity-last-sync'
        };
        
        this.init();
    }

    /**
     * Initialize offline utilities
     */
    init() {
        this.setupFormHandlers();
        this.setupNetworkIndicators();
        this.loadCachedData();
        
        // Listen for PWA connection changes
        window.addEventListener('pwa-connection-change', (event) => {
            this.handleConnectionChange(event.detail.isOnline);
        });
    }

    /**
     * Set up form handlers for offline functionality
     */
    setupFormHandlers() {
        // Override form submissions to handle offline scenarios
        document.addEventListener('submit', (event) => {
            if (!navigator.onLine && this.isModifyingForm(event.target)) {
                event.preventDefault();
                this.handleOfflineFormSubmission(event.target);
            }
        });

        // Add offline indicators to forms
        this.addFormOfflineIndicators();
    }

    /**
     * Check if form is a modifying operation (POST, PUT, DELETE)
     */
    isModifyingForm(form) {
        const method = (form.method || 'GET').toUpperCase();
        return ['POST', 'PUT', 'DELETE'].includes(method);
    }

    /**
     * Handle form submission when offline
     */
    handleOfflineFormSubmission(form) {
        const formData = new FormData(form);
        const data = {};
        
        // Convert FormData to object
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }

        // Create offline action
        const action = {
            id: this.generateId(),
            type: 'form_submission',
            endpoint: form.action || window.location.pathname,
            method: form.method || 'POST',
            data: data,
            timestamp: Date.now(),
            formId: form.id || null,
            formName: form.name || null
        };

        // Queue the action
        this.queueOfflineAction(action);

        // Show user feedback
        this.showOfflineFormFeedback(form, action);

        // Save form data for restoration
        this.saveFormData(form, data);
    }

    /**
     * Queue an offline action
     */
    queueOfflineAction(action) {
        const queue = this.getOfflineQueue();
        queue.push(action);
        this.saveOfflineQueue(queue);
        
        console.log('[Offline] Queued action:', action.id);
        this.updateQueueIndicator();
    }

    /**
     * Get offline queue from storage
     */
    getOfflineQueue() {
        try {
            const queue = localStorage.getItem(this.storageKeys.offlineQueue);
            return queue ? JSON.parse(queue) : [];
        } catch (error) {
            console.error('[Offline] Failed to load queue:', error);
            return [];
        }
    }

    /**
     * Save offline queue to storage
     */
    saveOfflineQueue(queue) {
        try {
            localStorage.setItem(this.storageKeys.offlineQueue, JSON.stringify(queue));
        } catch (error) {
            console.error('[Offline] Failed to save queue:', error);
        }
    }

    /**
     * Show offline form feedback to user
     */
    showOfflineFormFeedback(form, action) {
        // Create feedback overlay
        const overlay = document.createElement('div');
        overlay.className = 'offline-form-overlay';
        overlay.innerHTML = `
            <div class="offline-overlay">
                <div class="icon">📡</div>
                <div class="message">
                    You're offline. This form has been saved and will be submitted when you're back online.
                </div>
                <button class="queue-btn" onclick="this.closest('.offline-form-overlay').remove()">
                    Got it
                </button>
            </div>
        `;

        // Add overlay to form
        form.style.position = 'relative';
        form.appendChild(overlay);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (overlay.parentNode) {
                overlay.remove();
            }
        }, 5000);
    }

    /**
     * Add offline indicators to forms
     */
    addFormOfflineIndicators() {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            if (this.isModifyingForm(form)) {
                this.addOfflineIndicatorToForm(form);
            }
        });
    }

    /**
     * Add offline indicator to a specific form
     */
    addOfflineIndicatorToForm(form) {
        if (!navigator.onLine) {
            form.classList.add('form-offline');
            
            // Add overlay if not already present
            if (!form.querySelector('.offline-overlay')) {
                const overlay = document.createElement('div');
                overlay.className = 'offline-overlay';
                overlay.innerHTML = `
                    <div class="icon">📡</div>
                    <div class="message">You're offline. Form submissions will be queued.</div>
                    <button class="queue-btn" onclick="this.closest('form').classList.remove('form-offline'); this.remove();">
                        Continue Anyway
                    </button>
                `;
                form.appendChild(overlay);
            }
        } else {
            form.classList.remove('form-offline');
            const overlay = form.querySelector('.offline-overlay');
            if (overlay) {
                overlay.remove();
            }
        }
    }

    /**
     * Save form data for restoration
     */
    saveFormData(form, data) {
        const formId = form.id || form.action || 'anonymous';
        const savedForms = this.getSavedFormData();
        
        savedForms[formId] = {
            data: data,
            timestamp: Date.now(),
            url: window.location.pathname
        };
        
        try {
            localStorage.setItem(this.storageKeys.formData, JSON.stringify(savedForms));
        } catch (error) {
            console.error('[Offline] Failed to save form data:', error);
        }
    }

    /**
     * Get saved form data
     */
    getSavedFormData() {
        try {
            const saved = localStorage.getItem(this.storageKeys.formData);
            return saved ? JSON.parse(saved) : {};
        } catch (error) {
            console.error('[Offline] Failed to load form data:', error);
            return {};
        }
    }

    /**
     * Restore form data
     */
    restoreFormData(formId) {
        const savedForms = this.getSavedFormData();
        const formData = savedForms[formId];
        
        if (formData) {
            const form = document.getElementById(formId);
            if (form) {
                Object.keys(formData.data).forEach(key => {
                    const field = form.querySelector(`[name="${key}"]`);
                    if (field) {
                        field.value = formData.data[key];
                    }
                });
                
                // Show restoration notice
                this.showFormRestorationNotice(form, formData.timestamp);
            }
        }
    }

    /**
     * Show form restoration notice
     */
    showFormRestorationNotice(form, timestamp) {
        const notice = document.createElement('div');
        notice.className = 'form-restoration-notice';
        notice.innerHTML = `
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4">
                <div class="flex items-center">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span>Form data restored from ${new Date(timestamp).toLocaleString()}</span>
                    <button onclick="this.closest('.form-restoration-notice').remove()" class="ml-auto text-blue-500 hover:text-blue-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        
        form.insertBefore(notice, form.firstChild);
    }

    /**
     * Set up network indicators
     */
    setupNetworkIndicators() {
        this.createNetworkStatusBar();
        this.createQueueIndicator();
        this.updateNetworkStatus();
    }

    /**
     * Create network status bar
     */
    createNetworkStatusBar() {
        if (document.getElementById('network-status-bar')) {
            return;
        }

        const statusBar = document.createElement('div');
        statusBar.id = 'network-status-bar';
        statusBar.className = 'network-status-bar';
        statusBar.innerHTML = `
            <span id="network-status-text">Checking connection...</span>
        `;
        
        document.body.appendChild(statusBar);
    }

    /**
     * Create queue indicator
     */
    createQueueIndicator() {
        if (document.getElementById('offline-queue-indicator')) {
            return;
        }

        const indicator = document.createElement('div');
        indicator.id = 'offline-queue-indicator';
        indicator.className = 'offline-queue-indicator';
        indicator.innerHTML = `
            <i class="fas fa-clock"></i>
            <span>Queued actions: <span class="count">0</span></span>
        `;
        
        indicator.addEventListener('click', () => {
            this.showQueueDetails();
        });
        
        document.body.appendChild(indicator);
    }

    /**
     * Update network status display
     */
    updateNetworkStatus() {
        const statusBar = document.getElementById('network-status-bar');
        const statusText = document.getElementById('network-status-text');
        
        if (!statusBar || !statusText) {
            return;
        }

        if (navigator.onLine) {
            statusBar.className = 'network-status-bar online';
            statusText.textContent = '🟢 Connected - All features available';
            
            // Hide after 3 seconds when online
            setTimeout(() => {
                statusBar.classList.remove('show');
            }, 3000);
        } else {
            statusBar.className = 'network-status-bar show';
            statusText.textContent = '🔴 Offline - Limited functionality available';
        }
    }

    /**
     * Handle connection changes
     */
    handleConnectionChange(isOnline) {
        this.updateNetworkStatus();
        this.updateQueueIndicator();
        
        // Update form indicators
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            if (this.isModifyingForm(form)) {
                this.addOfflineIndicatorToForm(form);
            }
        });

        if (isOnline) {
            this.syncOfflineActions();
        }
    }

    /**
     * Update queue indicator
     */
    updateQueueIndicator() {
        const indicator = document.getElementById('offline-queue-indicator');
        const countElement = indicator?.querySelector('.count');
        
        if (!indicator || !countElement) {
            return;
        }

        const queue = this.getOfflineQueue();
        const count = queue.length;
        
        countElement.textContent = count;
        
        if (count > 0) {
            indicator.classList.add('show');
        } else {
            indicator.classList.remove('show');
        }
    }

    /**
     * Show queue details modal
     */
    showQueueDetails() {
        const queue = this.getOfflineQueue();
        
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4 max-h-96 overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Queued Actions (${queue.length})</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-3">
                    ${queue.length === 0 ? 
                        '<p class="text-gray-500 text-center py-4">No queued actions</p>' :
                        queue.map(action => `
                            <div class="border rounded p-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">${action.type || 'Unknown'}</div>
                                        <div class="text-sm text-gray-600">${action.endpoint}</div>
                                        <div class="text-xs text-gray-500">${new Date(action.timestamp).toLocaleString()}</div>
                                    </div>
                                    <button onclick="offlineUtils.removeQueuedAction('${action.id}')" 
                                            class="text-red-500 hover:text-red-700">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        `).join('')
                    }
                </div>
                
                ${queue.length > 0 ? `
                    <div class="flex space-x-2 mt-4">
                        <button onclick="offlineUtils.syncOfflineActions()" 
                                class="flex-1 bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">
                            Sync Now
                        </button>
                        <button onclick="offlineUtils.clearOfflineQueue()" 
                                class="flex-1 bg-red-600 text-white py-2 px-4 rounded hover:bg-red-700">
                            Clear All
                        </button>
                    </div>
                ` : ''}
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    /**
     * Remove a queued action
     */
    removeQueuedAction(actionId) {
        const queue = this.getOfflineQueue();
        const filteredQueue = queue.filter(action => action.id !== actionId);
        this.saveOfflineQueue(filteredQueue);
        this.updateQueueIndicator();
        
        // Refresh modal if open
        const modal = document.querySelector('.fixed.inset-0');
        if (modal) {
            modal.remove();
            this.showQueueDetails();
        }
    }

    /**
     * Clear offline queue
     */
    clearOfflineQueue() {
        this.saveOfflineQueue([]);
        this.updateQueueIndicator();
        
        // Close modal
        const modal = document.querySelector('.fixed.inset-0');
        if (modal) {
            modal.remove();
        }
    }

    /**
     * Sync offline actions
     */
    async syncOfflineActions() {
        if (!navigator.onLine) {
            console.warn('[Offline] Cannot sync while offline');
            return;
        }

        const queue = this.getOfflineQueue();
        if (queue.length === 0) {
            return;
        }

        console.log('[Offline] Syncing', queue.length, 'actions...');
        
        const successfulActions = [];
        const failedActions = [];

        for (const action of queue) {
            try {
                await this.processOfflineAction(action);
                successfulActions.push(action);
                console.log('[Offline] Synced action:', action.id);
            } catch (error) {
                console.error('[Offline] Failed to sync action:', action.id, error);
                failedActions.push(action);
            }
        }

        // Update queue with only failed actions
        this.saveOfflineQueue(failedActions);
        this.updateQueueIndicator();

        // Show sync results
        this.showSyncResults(successfulActions.length, failedActions.length);
    }

    /**
     * Process a single offline action
     */
    async processOfflineAction(action) {
        const response = await fetch(action.endpoint, {
            method: action.method,
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

        return response;
    }

    /**
     * Show sync results to user
     */
    showSyncResults(successful, failed) {
        const message = successful > 0 ? 
            `✅ Synced ${successful} action${successful !== 1 ? 's' : ''}` : '';
        const errorMessage = failed > 0 ? 
            `❌ ${failed} action${failed !== 1 ? 's' : ''} failed` : '';
        
        const fullMessage = [message, errorMessage].filter(Boolean).join(', ');
        
        if (fullMessage && window.CRM?.showAlert) {
            window.CRM.showAlert(fullMessage, successful > 0 && failed === 0 ? 'success' : 'warning');
        }
    }

    /**
     * Cache data for offline access
     */
    cacheData(key, data, ttl = 24 * 60 * 60 * 1000) { // Default 24 hours
        const cacheEntry = {
            data: data,
            timestamp: Date.now(),
            ttl: ttl
        };
        
        try {
            const cached = this.getCachedData();
            cached[key] = cacheEntry;
            localStorage.setItem(this.storageKeys.cachedData, JSON.stringify(cached));
        } catch (error) {
            console.error('[Offline] Failed to cache data:', error);
        }
    }

    /**
     * Get cached data
     */
    getCachedData(key = null) {
        try {
            const cached = localStorage.getItem(this.storageKeys.cachedData);
            const data = cached ? JSON.parse(cached) : {};
            
            if (key) {
                const entry = data[key];
                if (entry && (Date.now() - entry.timestamp) < entry.ttl) {
                    return entry.data;
                }
                return null;
            }
            
            return data;
        } catch (error) {
            console.error('[Offline] Failed to load cached data:', error);
            return key ? null : {};
        }
    }

    /**
     * Load cached data for offline display
     */
    loadCachedData() {
        const cached = this.getCachedData();
        const validEntries = {};
        
        // Filter out expired entries
        Object.keys(cached).forEach(key => {
            const entry = cached[key];
            if (entry && (Date.now() - entry.timestamp) < entry.ttl) {
                validEntries[key] = entry;
            }
        });
        
        // Update storage with valid entries only
        try {
            localStorage.setItem(this.storageKeys.cachedData, JSON.stringify(validEntries));
        } catch (error) {
            console.error('[Offline] Failed to update cached data:', error);
        }
    }

    /**
     * Display cached data in offline scenarios
     */
    displayCachedData(containerId, dataKey, formatter = null) {
        const container = document.getElementById(containerId);
        if (!container) {
            return;
        }

        const data = this.getCachedData(dataKey);
        
        if (data) {
            const formattedData = formatter ? formatter(data) : JSON.stringify(data, null, 2);
            const timestamp = this.getCachedData()[dataKey]?.timestamp;
            
            container.innerHTML = `
                <div class="cached-data">
                    <div class="timestamp">Last updated: ${new Date(timestamp).toLocaleString()}</div>
                    <div class="content">${formattedData}</div>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="offline-data-container">
                    <div class="icon">📭</div>
                    <div class="title">No Cached Data</div>
                    <div class="description">No offline data available for this section.</div>
                </div>
            `;
        }
    }

    /**
     * Generate unique ID
     */
    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    /**
     * Get offline statistics
     */
    getOfflineStats() {
        const queue = this.getOfflineQueue();
        const cached = this.getCachedData();
        
        return {
            queuedActions: queue.length,
            cachedEntries: Object.keys(cached).length,
            lastSync: localStorage.getItem(this.storageKeys.lastSync),
            isOnline: navigator.onLine
        };
    }
}

// Initialize offline utilities when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.offlineUtils = new OfflineUtils();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OfflineUtils;
}
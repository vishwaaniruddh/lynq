/**
 * ADV Clarity Management System - Network Status Indicators
 * Enhanced network status monitoring and user feedback
 */

class NetworkStatus {
    constructor() {
        this.isOnline = navigator.onLine;
        this.connectionType = this.getConnectionType();
        this.lastOnlineTime = Date.now();
        this.offlineDuration = 0;
        this.indicators = new Map();
        
        this.init();
    }

    /**
     * Initialize network status monitoring
     */
    init() {
        this.createIndicators();
        this.setupEventListeners();
        this.startConnectionMonitoring();
        this.updateAllIndicators();
    }

    /**
     * Create network status indicators
     */
    createIndicators() {
        // Main status bar (top of page)
        this.createStatusBar();
        
        // Floating indicator (bottom right)
        this.createFloatingIndicator();
        
        // Connection quality indicator
        this.createConnectionQualityIndicator();
        
        // Sync status indicator
        this.createSyncStatusIndicator();
    }

    /**
     * Create main status bar
     */
    createStatusBar() {
        if (document.getElementById('network-status-bar')) {
            return;
        }

        const statusBar = document.createElement('div');
        statusBar.id = 'network-status-bar';
        statusBar.className = 'network-status-bar';
        statusBar.innerHTML = `
            <div class="flex items-center justify-between max-w-7xl mx-auto px-4">
                <div class="flex items-center space-x-2">
                    <span id="network-status-icon">🟢</span>
                    <span id="network-status-text">Connected</span>
                    <span id="connection-type" class="text-sm opacity-75"></span>
                </div>
                <div class="flex items-center space-x-4">
                    <span id="offline-duration" class="text-sm"></span>
                    <button id="network-status-close" class="text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        
        // Add close functionality
        statusBar.querySelector('#network-status-close').addEventListener('click', () => {
            statusBar.classList.remove('show');
        });
        
        document.body.appendChild(statusBar);
        this.indicators.set('statusBar', statusBar);
    }

    /**
     * Create floating indicator
     */
    createFloatingIndicator() {
        if (document.getElementById('network-floating-indicator')) {
            return;
        }

        const indicator = document.createElement('div');
        indicator.id = 'network-floating-indicator';
        indicator.className = 'fixed bottom-4 right-4 bg-white border border-gray-200 rounded-lg shadow-lg p-3 z-40 hidden';
        indicator.innerHTML = `
            <div class="flex items-center space-x-2">
                <div id="floating-status-dot" class="w-3 h-3 rounded-full bg-green-500"></div>
                <span id="floating-status-text" class="text-sm font-medium">Online</span>
                <button id="floating-indicator-close" class="text-gray-400 hover:text-gray-600 ml-2">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <div id="floating-details" class="text-xs text-gray-500 mt-1 hidden">
                <div id="floating-connection-info"></div>
                <div id="floating-last-sync"></div>
            </div>
        `;
        
        // Add click to expand/collapse
        indicator.addEventListener('click', (e) => {
            if (e.target.id !== 'floating-indicator-close') {
                const details = indicator.querySelector('#floating-details');
                details.classList.toggle('hidden');
            }
        });
        
        // Add close functionality
        indicator.querySelector('#floating-indicator-close').addEventListener('click', (e) => {
            e.stopPropagation();
            indicator.classList.add('hidden');
        });
        
        document.body.appendChild(indicator);
        this.indicators.set('floating', indicator);
    }

    /**
     * Create connection quality indicator
     */
    createConnectionQualityIndicator() {
        if (document.getElementById('connection-quality-indicator')) {
            return;
        }

        const indicator = document.createElement('div');
        indicator.id = 'connection-quality-indicator';
        indicator.className = 'fixed top-20 right-4 bg-white border border-gray-200 rounded-lg shadow-lg p-2 z-30 hidden';
        indicator.innerHTML = `
            <div class="flex items-center space-x-2">
                <div class="flex space-x-1">
                    <div class="connection-bar w-1 h-4 bg-gray-300 rounded"></div>
                    <div class="connection-bar w-1 h-4 bg-gray-300 rounded"></div>
                    <div class="connection-bar w-1 h-4 bg-gray-300 rounded"></div>
                    <div class="connection-bar w-1 h-4 bg-gray-300 rounded"></div>
                </div>
                <span id="connection-quality-text" class="text-xs font-medium">Checking...</span>
            </div>
        `;
        
        document.body.appendChild(indicator);
        this.indicators.set('quality', indicator);
    }

    /**
     * Create sync status indicator
     */
    createSyncStatusIndicator() {
        if (document.getElementById('sync-status-indicator')) {
            return;
        }

        const indicator = document.createElement('div');
        indicator.id = 'sync-status-indicator';
        indicator.className = 'sync-status hidden';
        indicator.innerHTML = `
            <div class="spinner hidden"></div>
            <span id="sync-status-text">Synced</span>
        `;
        
        // Add to header or appropriate location
        const header = document.querySelector('header, .header, nav');
        if (header) {
            header.appendChild(indicator);
        } else {
            document.body.appendChild(indicator);
        }
        
        this.indicators.set('sync', indicator);
    }

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Browser online/offline events
        window.addEventListener('online', () => this.handleOnlineEvent());
        window.addEventListener('offline', () => this.handleOfflineEvent());
        
        // Page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.checkConnectionStatus();
            }
        });
        
        // PWA connection changes
        window.addEventListener('pwa-connection-change', (event) => {
            this.handleConnectionChange(event.detail.isOnline);
        });
        
        // Sync events
        window.addEventListener('sync-start', () => this.showSyncStatus('syncing'));
        window.addEventListener('sync-complete', () => this.showSyncStatus('synced'));
        window.addEventListener('sync-error', () => this.showSyncStatus('error'));
    }

    /**
     * Start connection monitoring
     */
    startConnectionMonitoring() {
        // Check connection quality periodically
        setInterval(() => {
            this.checkConnectionQuality();
        }, 30000); // Every 30 seconds
        
        // Update offline duration
        setInterval(() => {
            this.updateOfflineDuration();
        }, 1000); // Every second
        
        // Initial checks
        this.checkConnectionStatus();
        this.checkConnectionQuality();
    }

    /**
     * Handle online event
     */
    handleOnlineEvent() {
        this.isOnline = true;
        this.lastOnlineTime = Date.now();
        this.offlineDuration = 0;
        this.connectionType = this.getConnectionType();
        
        console.log('[NetworkStatus] Connection restored');
        this.updateAllIndicators();
        this.hideOfflineIndicators();
        
        // Dispatch custom event
        window.dispatchEvent(new CustomEvent('network-online', {
            detail: { connectionType: this.connectionType }
        }));
    }

    /**
     * Handle offline event
     */
    handleOfflineEvent() {
        this.isOnline = false;
        this.connectionType = 'none';
        
        console.log('[NetworkStatus] Connection lost');
        this.updateAllIndicators();
        this.showOfflineIndicators();
        
        // Dispatch custom event
        window.dispatchEvent(new CustomEvent('network-offline'));
    }

    /**
     * Handle connection change from PWA manager
     */
    handleConnectionChange(isOnline) {
        if (this.isOnline !== isOnline) {
            if (isOnline) {
                this.handleOnlineEvent();
            } else {
                this.handleOfflineEvent();
            }
        }
    }

    /**
     * Check connection status with network request
     */
    async checkConnectionStatus() {
        try {
            const response = await fetch('/favicon.ico', {
                method: 'HEAD',
                cache: 'no-cache',
                signal: AbortSignal.timeout(5000)
            });
            
            if (response.ok && !this.isOnline) {
                this.handleOnlineEvent();
            }
        } catch (error) {
            if (this.isOnline) {
                this.handleOfflineEvent();
            }
        }
    }

    /**
     * Check connection quality
     */
    async checkConnectionQuality() {
        if (!this.isOnline) {
            this.updateConnectionQuality('offline', 0);
            return;
        }

        const startTime = Date.now();
        
        try {
            const response = await fetch('/favicon.ico', {
                method: 'HEAD',
                cache: 'no-cache',
                signal: AbortSignal.timeout(10000)
            });
            
            const endTime = Date.now();
            const latency = endTime - startTime;
            
            if (response.ok) {
                this.updateConnectionQuality('online', latency);
            } else {
                this.updateConnectionQuality('poor', latency);
            }
        } catch (error) {
            this.updateConnectionQuality('error', 0);
        }
    }

    /**
     * Update connection quality display
     */
    updateConnectionQuality(status, latency) {
        const qualityIndicator = this.indicators.get('quality');
        if (!qualityIndicator) return;

        const bars = qualityIndicator.querySelectorAll('.connection-bar');
        const text = qualityIndicator.querySelector('#connection-quality-text');
        
        // Reset bars
        bars.forEach(bar => {
            bar.className = 'connection-bar w-1 h-4 bg-gray-300 rounded';
        });
        
        let quality = 'Unknown';
        let barCount = 0;
        let color = 'gray';
        
        if (status === 'offline') {
            quality = 'Offline';
            barCount = 0;
            color = 'red';
        } else if (status === 'error') {
            quality = 'Error';
            barCount = 0;
            color = 'red';
        } else if (latency < 200) {
            quality = 'Excellent';
            barCount = 4;
            color = 'green';
        } else if (latency < 500) {
            quality = 'Good';
            barCount = 3;
            color = 'green';
        } else if (latency < 1000) {
            quality = 'Fair';
            barCount = 2;
            color = 'yellow';
        } else {
            quality = 'Poor';
            barCount = 1;
            color = 'red';
        }
        
        // Update bars
        for (let i = 0; i < barCount; i++) {
            bars[i].classList.remove('bg-gray-300');
            bars[i].classList.add(`bg-${color}-500`);
        }
        
        text.textContent = quality;
        
        // Show/hide indicator based on connection status
        if (status === 'offline' || status === 'error' || latency > 1000) {
            qualityIndicator.classList.remove('hidden');
        } else {
            qualityIndicator.classList.add('hidden');
        }
    }

    /**
     * Update all indicators
     */
    updateAllIndicators() {
        this.updateStatusBar();
        this.updateFloatingIndicator();
        this.updateConnectionInfo();
    }

    /**
     * Update main status bar
     */
    updateStatusBar() {
        const statusBar = this.indicators.get('statusBar');
        if (!statusBar) return;

        const icon = statusBar.querySelector('#network-status-icon');
        const text = statusBar.querySelector('#network-status-text');
        const type = statusBar.querySelector('#connection-type');
        
        if (this.isOnline) {
            statusBar.className = 'network-status-bar online';
            icon.textContent = '🟢';
            text.textContent = 'Connected';
            type.textContent = this.connectionType ? `(${this.connectionType})` : '';
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                statusBar.classList.remove('show');
            }, 3000);
        } else {
            statusBar.className = 'network-status-bar show';
            icon.textContent = '🔴';
            text.textContent = 'Offline';
            type.textContent = '';
        }
    }

    /**
     * Update floating indicator
     */
    updateFloatingIndicator() {
        const indicator = this.indicators.get('floating');
        if (!indicator) return;

        const dot = indicator.querySelector('#floating-status-dot');
        const text = indicator.querySelector('#floating-status-text');
        const info = indicator.querySelector('#floating-connection-info');
        const lastSync = indicator.querySelector('#floating-last-sync');
        
        if (this.isOnline) {
            dot.className = 'w-3 h-3 rounded-full bg-green-500';
            text.textContent = 'Online';
            info.textContent = this.connectionType ? `Connection: ${this.connectionType}` : '';
            indicator.classList.add('hidden'); // Hide when online
        } else {
            dot.className = 'w-3 h-3 rounded-full bg-red-500';
            text.textContent = 'Offline';
            info.textContent = `Offline for: ${this.formatDuration(this.offlineDuration)}`;
            indicator.classList.remove('hidden'); // Show when offline
        }
        
        // Update last sync time
        const lastSyncTime = localStorage.getItem('clarity-last-sync');
        if (lastSyncTime) {
            lastSync.textContent = `Last sync: ${new Date(parseInt(lastSyncTime)).toLocaleTimeString()}`;
        } else {
            lastSync.textContent = 'Last sync: Never';
        }
    }

    /**
     * Update connection info in other components
     */
    updateConnectionInfo() {
        // Update any elements with network status classes
        const networkElements = document.querySelectorAll('.network-status-dependent');
        
        networkElements.forEach(element => {
            if (this.isOnline) {
                element.classList.remove('offline');
                element.classList.add('online');
            } else {
                element.classList.remove('online');
                element.classList.add('offline');
            }
        });
    }

    /**
     * Show offline indicators
     */
    showOfflineIndicators() {
        const statusBar = this.indicators.get('statusBar');
        if (statusBar) {
            statusBar.classList.add('show');
        }
        
        const floating = this.indicators.get('floating');
        if (floating) {
            floating.classList.remove('hidden');
        }
    }

    /**
     * Hide offline indicators
     */
    hideOfflineIndicators() {
        // Status bar will auto-hide via updateStatusBar
        // Floating indicator will hide via updateFloatingIndicator
    }

    /**
     * Show sync status
     */
    showSyncStatus(status) {
        const syncIndicator = this.indicators.get('sync');
        if (!syncIndicator) return;

        const spinner = syncIndicator.querySelector('.spinner');
        const text = syncIndicator.querySelector('#sync-status-text');
        
        syncIndicator.classList.remove('hidden', 'syncing', 'synced', 'error');
        syncIndicator.classList.add(status);
        
        switch (status) {
            case 'syncing':
                spinner.classList.remove('hidden');
                text.textContent = 'Syncing...';
                break;
            case 'synced':
                spinner.classList.add('hidden');
                text.textContent = 'Synced';
                // Auto-hide after 2 seconds
                setTimeout(() => {
                    syncIndicator.classList.add('hidden');
                }, 2000);
                break;
            case 'error':
                spinner.classList.add('hidden');
                text.textContent = 'Sync Error';
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    syncIndicator.classList.add('hidden');
                }, 5000);
                break;
        }
    }

    /**
     * Update offline duration
     */
    updateOfflineDuration() {
        if (!this.isOnline) {
            this.offlineDuration = Date.now() - this.lastOnlineTime;
            
            const durationElement = document.getElementById('offline-duration');
            if (durationElement) {
                durationElement.textContent = `Offline: ${this.formatDuration(this.offlineDuration)}`;
            }
        }
    }

    /**
     * Get connection type
     */
    getConnectionType() {
        if ('connection' in navigator) {
            return navigator.connection.effectiveType || navigator.connection.type || 'unknown';
        }
        return null;
    }

    /**
     * Format duration in human-readable format
     */
    formatDuration(milliseconds) {
        const seconds = Math.floor(milliseconds / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        
        if (hours > 0) {
            return `${hours}h ${minutes % 60}m`;
        } else if (minutes > 0) {
            return `${minutes}m ${seconds % 60}s`;
        } else {
            return `${seconds}s`;
        }
    }

    /**
     * Get current network status
     */
    getStatus() {
        return {
            isOnline: this.isOnline,
            connectionType: this.connectionType,
            offlineDuration: this.offlineDuration,
            lastOnlineTime: this.lastOnlineTime
        };
    }

    /**
     * Manually trigger connection check
     */
    refresh() {
        this.checkConnectionStatus();
        this.checkConnectionQuality();
    }

    /**
     * Destroy network status monitoring
     */
    destroy() {
        // Remove event listeners
        window.removeEventListener('online', this.handleOnlineEvent);
        window.removeEventListener('offline', this.handleOfflineEvent);
        
        // Remove indicators
        this.indicators.forEach(indicator => {
            if (indicator.parentNode) {
                indicator.parentNode.removeChild(indicator);
            }
        });
        
        this.indicators.clear();
    }
}

// Initialize network status when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.networkStatus = new NetworkStatus();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NetworkStatus;
}
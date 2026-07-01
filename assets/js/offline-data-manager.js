/**
 * ADV Clarity Management System - Offline Data Manager
 * Manages caching and display of critical information for offline access
 */

class OfflineDataManager {
    constructor() {
        this.cacheKeys = {
            dashboard: 'dashboard-stats',
            inventory: 'inventory-summary',
            sites: 'sites-list',
            installations: 'installations-active',
            dispatches: 'dispatches-pending',
            userProfile: 'user-profile'
        };
        
        this.cacheTTL = {
            dashboard: 30 * 60 * 1000,      // 30 minutes
            inventory: 60 * 60 * 1000,      // 1 hour
            sites: 2 * 60 * 60 * 1000,      // 2 hours
            installations: 15 * 60 * 1000,  // 15 minutes
            dispatches: 15 * 60 * 1000,     // 15 minutes
            userProfile: 24 * 60 * 60 * 1000 // 24 hours
        };
        
        this.init();
    }

    /**
     * Initialize offline data manager
     */
    init() {
        this.setupAutoCaching();
        this.setupPageSpecificCaching();
        
        // Listen for page visibility changes to cache data
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && navigator.onLine) {
                this.cacheCurrentPageData();
            }
        });
    }

    /**
     * Set up automatic caching for API responses
     */
    setupAutoCaching() {
        // Override fetch to automatically cache important responses
        const originalFetch = window.fetch;
        
        window.fetch = async (...args) => {
            const response = await originalFetch(...args);
            
            // Cache important API responses
            if (response.ok && this.shouldCacheResponse(args[0])) {
                this.cacheApiResponse(args[0], response.clone());
            }
            
            return response;
        };
    }

    /**
     * Check if response should be cached
     */
    shouldCacheResponse(url) {
        const urlString = url.toString().toLowerCase();
        
        return urlString.includes('/api/dashboard') ||
               urlString.includes('/api/inventory') ||
               urlString.includes('/api/sites') ||
               urlString.includes('/api/installations') ||
               urlString.includes('/api/dispatches') ||
               urlString.includes('/api/user/profile');
    }

    /**
     * Cache API response
     */
    async cacheApiResponse(url, response) {
        try {
            const data = await response.json();
            const urlString = url.toString().toLowerCase();
            
            if (urlString.includes('/api/dashboard')) {
                this.cacheDashboardData(data);
            } else if (urlString.includes('/api/inventory')) {
                this.cacheInventoryData(data);
            } else if (urlString.includes('/api/sites')) {
                this.cacheSitesData(data);
            } else if (urlString.includes('/api/installations')) {
                this.cacheInstallationsData(data);
            } else if (urlString.includes('/api/dispatches')) {
                this.cacheDispatchesData(data);
            } else if (urlString.includes('/api/user/profile')) {
                this.cacheUserProfile(data);
            }
        } catch (error) {
            console.error('[OfflineData] Failed to cache API response:', error);
        }
    }

    /**
     * Set up page-specific caching
     */
    setupPageSpecificCaching() {
        // Cache data when leaving pages
        window.addEventListener('beforeunload', () => {
            this.cacheCurrentPageData();
        });
        
        // Cache data periodically while on page
        setInterval(() => {
            if (navigator.onLine) {
                this.cacheCurrentPageData();
            }
        }, 5 * 60 * 1000); // Every 5 minutes
    }

    /**
     * Cache current page data based on URL
     */
    cacheCurrentPageData() {
        const path = window.location.pathname.toLowerCase();
        
        if (path.includes('dashboard')) {
            this.cacheDashboardStats();
        } else if (path.includes('inventory')) {
            this.cacheInventorySummary();
        } else if (path.includes('sites')) {
            this.cacheSitesList();
        } else if (path.includes('installation')) {
            this.cacheActiveInstallations();
        }
    }

    /**
     * Cache dashboard statistics
     */
    async cacheDashboardStats() {
        try {
            // Extract stats from current page
            const stats = this.extractDashboardStats();
            if (stats) {
                this.cacheData(this.cacheKeys.dashboard, stats, this.cacheTTL.dashboard);
            }
        } catch (error) {
            console.error('[OfflineData] Failed to cache dashboard stats:', error);
        }
    }

    /**
     * Extract dashboard statistics from current page
     */
    extractDashboardStats() {
        const stats = {};
        
        // Extract from dashboard cards or elements
        const totalSitesElement = document.querySelector('[data-stat="total-sites"]');
        if (totalSitesElement) {
            stats.totalSites = parseInt(totalSitesElement.textContent) || 0;
        }
        
        const activeInstallationsElement = document.querySelector('[data-stat="active-installations"]');
        if (activeInstallationsElement) {
            stats.activeInstallations = parseInt(activeInstallationsElement.textContent) || 0;
        }
        
        const pendingDispatchesElement = document.querySelector('[data-stat="pending-dispatches"]');
        if (pendingDispatchesElement) {
            stats.pendingDispatches = parseInt(pendingDispatchesElement.textContent) || 0;
        }
        
        // Extract from chart data if available
        if (window.dashboardChartData) {
            stats.chartData = window.dashboardChartData;
        }
        
        return Object.keys(stats).length > 0 ? stats : null;
    }

    /**
     * Cache inventory summary
     */
    async cacheInventorySummary() {
        try {
            const summary = this.extractInventorySummary();
            if (summary) {
                this.cacheData(this.cacheKeys.inventory, { summary }, this.cacheTTL.inventory);
            }
        } catch (error) {
            console.error('[OfflineData] Failed to cache inventory summary:', error);
        }
    }

    /**
     * Extract inventory summary from current page
     */
    extractInventorySummary() {
        const summary = {};
        
        // Extract from inventory dashboard elements
        const totalProductsElement = document.querySelector('[data-stat="total-products"]');
        if (totalProductsElement) {
            summary.totalProducts = parseInt(totalProductsElement.textContent) || 0;
        }
        
        const lowStockElement = document.querySelector('[data-stat="low-stock"]');
        if (lowStockElement) {
            summary.lowStock = parseInt(lowStockElement.textContent) || 0;
        }
        
        const warehousesElement = document.querySelector('[data-stat="warehouses"]');
        if (warehousesElement) {
            summary.warehouses = parseInt(warehousesElement.textContent) || 0;
        }
        
        // Extract from tables if available
        const inventoryTable = document.querySelector('#inventory-table tbody');
        if (inventoryTable) {
            const rows = inventoryTable.querySelectorAll('tr');
            summary.recentItems = Array.from(rows).slice(0, 10).map(row => {
                const cells = row.querySelectorAll('td');
                return {
                    name: cells[0]?.textContent?.trim() || '',
                    quantity: cells[1]?.textContent?.trim() || '',
                    status: cells[2]?.textContent?.trim() || ''
                };
            });
        }
        
        return Object.keys(summary).length > 0 ? summary : null;
    }

    /**
     * Cache sites list
     */
    async cacheSitesList() {
        try {
            const sites = this.extractSitesList();
            if (sites) {
                this.cacheData(this.cacheKeys.sites, { sites }, this.cacheTTL.sites);
            }
        } catch (error) {
            console.error('[OfflineData] Failed to cache sites list:', error);
        }
    }

    /**
     * Extract sites list from current page
     */
    extractSitesList() {
        const sitesTable = document.querySelector('#sites-table tbody');
        if (!sitesTable) {
            return null;
        }
        
        const rows = sitesTable.querySelectorAll('tr');
        return Array.from(rows).slice(0, 20).map(row => {
            const cells = row.querySelectorAll('td');
            return {
                id: cells[0]?.textContent?.trim() || '',
                name: cells[1]?.textContent?.trim() || '',
                location: cells[2]?.textContent?.trim() || '',
                status: cells[3]?.textContent?.trim() || '',
                engineer: cells[4]?.textContent?.trim() || ''
            };
        });
    }

    /**
     * Cache active installations
     */
    async cacheActiveInstallations() {
        try {
            const installations = this.extractActiveInstallations();
            if (installations) {
                this.cacheData(this.cacheKeys.installations, { installations }, this.cacheTTL.installations);
            }
        } catch (error) {
            console.error('[OfflineData] Failed to cache installations:', error);
        }
    }

    /**
     * Extract active installations from current page
     */
    extractActiveInstallations() {
        const installationsTable = document.querySelector('#installations-table tbody');
        if (!installationsTable) {
            return null;
        }
        
        const rows = installationsTable.querySelectorAll('tr');
        return Array.from(rows).slice(0, 15).map(row => {
            const cells = row.querySelectorAll('td');
            return {
                id: cells[0]?.textContent?.trim() || '',
                site: cells[1]?.textContent?.trim() || '',
                engineer: cells[2]?.textContent?.trim() || '',
                status: cells[3]?.textContent?.trim() || '',
                progress: cells[4]?.textContent?.trim() || '',
                eta: cells[5]?.textContent?.trim() || ''
            };
        });
    }

    /**
     * Cache dashboard data from API response
     */
    cacheDashboardData(data) {
        this.cacheData(this.cacheKeys.dashboard, data, this.cacheTTL.dashboard);
    }

    /**
     * Cache inventory data from API response
     */
    cacheInventoryData(data) {
        this.cacheData(this.cacheKeys.inventory, data, this.cacheTTL.inventory);
    }

    /**
     * Cache sites data from API response
     */
    cacheSitesData(data) {
        this.cacheData(this.cacheKeys.sites, data, this.cacheTTL.sites);
    }

    /**
     * Cache installations data from API response
     */
    cacheInstallationsData(data) {
        this.cacheData(this.cacheKeys.installations, data, this.cacheTTL.installations);
    }

    /**
     * Cache dispatches data from API response
     */
    cacheDispatchesData(data) {
        this.cacheData(this.cacheKeys.dispatches, data, this.cacheTTL.dispatches);
    }

    /**
     * Cache user profile data from API response
     */
    cacheUserProfile(data) {
        this.cacheData(this.cacheKeys.userProfile, data, this.cacheTTL.userProfile);
    }

    /**
     * Generic cache data method
     */
    cacheData(key, data, ttl) {
        if (window.offlineUtils) {
            window.offlineUtils.cacheData(key, data, ttl);
        } else {
            // Fallback to direct localStorage
            try {
                const cacheEntry = {
                    data: data,
                    timestamp: Date.now(),
                    ttl: ttl
                };
                localStorage.setItem(`clarity-cache-${key}`, JSON.stringify(cacheEntry));
            } catch (error) {
                console.error('[OfflineData] Failed to cache data:', error);
            }
        }
    }

    /**
     * Get cached data
     */
    getCachedData(key) {
        if (window.offlineUtils) {
            return window.offlineUtils.getCachedData(key);
        } else {
            // Fallback to direct localStorage
            try {
                const cached = localStorage.getItem(`clarity-cache-${key}`);
                if (cached) {
                    const entry = JSON.parse(cached);
                    if ((Date.now() - entry.timestamp) < entry.ttl) {
                        return entry.data;
                    }
                }
            } catch (error) {
                console.error('[OfflineData] Failed to get cached data:', error);
            }
        }
        return null;
    }

    /**
     * Display cached data in a container
     */
    displayCachedData(containerId, dataKey, formatter = null) {
        const container = document.getElementById(containerId);
        if (!container) {
            return;
        }

        const data = this.getCachedData(dataKey);
        
        if (data) {
            if (formatter && typeof formatter === 'function') {
                container.innerHTML = formatter(data);
            } else {
                // Default formatter
                container.innerHTML = this.formatCachedData(data, dataKey);
            }
        } else {
            container.innerHTML = `
                <div class="offline-data-container">
                    <div class="icon">📭</div>
                    <div class="title">No Cached Data</div>
                    <div class="description">No offline data available for ${dataKey}.</div>
                </div>
            `;
        }
    }

    /**
     * Default formatter for cached data
     */
    formatCachedData(data, dataKey) {
        let html = '<div class="cached-data">';
        html += '<div class="timestamp">Cached data available</div>';
        
        switch (dataKey) {
            case this.cacheKeys.dashboard:
                if (data.totalSites !== undefined) {
                    html += `<div class="cached-data-item"><span class="label">Total Sites:</span><span class="value">${data.totalSites}</span></div>`;
                }
                if (data.activeInstallations !== undefined) {
                    html += `<div class="cached-data-item"><span class="label">Active Installations:</span><span class="value">${data.activeInstallations}</span></div>`;
                }
                if (data.pendingDispatches !== undefined) {
                    html += `<div class="cached-data-item"><span class="label">Pending Dispatches:</span><span class="value">${data.pendingDispatches}</span></div>`;
                }
                break;
                
            case this.cacheKeys.inventory:
                if (data.summary) {
                    const summary = data.summary;
                    if (summary.totalProducts !== undefined) {
                        html += `<div class="cached-data-item"><span class="label">Total Products:</span><span class="value">${summary.totalProducts}</span></div>`;
                    }
                    if (summary.lowStock !== undefined) {
                        html += `<div class="cached-data-item"><span class="label">Low Stock Items:</span><span class="value">${summary.lowStock}</span></div>`;
                    }
                    if (summary.warehouses !== undefined) {
                        html += `<div class="cached-data-item"><span class="label">Warehouses:</span><span class="value">${summary.warehouses}</span></div>`;
                    }
                }
                break;
                
            case this.cacheKeys.sites:
                if (data.sites && Array.isArray(data.sites)) {
                    html += `<div class="cached-data-item"><span class="label">Cached Sites:</span><span class="value">${data.sites.length}</span></div>`;
                    html += '<div class="cached-list">';
                    data.sites.slice(0, 5).forEach(site => {
                        html += `<div class="cached-list-item">${site.name} - ${site.status}</div>`;
                    });
                    if (data.sites.length > 5) {
                        html += `<div class="cached-list-item">... and ${data.sites.length - 5} more</div>`;
                    }
                    html += '</div>';
                }
                break;
                
            default:
                html += '<div class="cached-data-item">Cached data available</div>';
        }
        
        html += '</div>';
        return html;
    }

    /**
     * Clear all cached data
     */
    clearAllCache() {
        Object.values(this.cacheKeys).forEach(key => {
            try {
                localStorage.removeItem(`clarity-cache-${key}`);
            } catch (error) {
                console.error('[OfflineData] Failed to clear cache:', error);
            }
        });
    }

    /**
     * Get cache statistics
     */
    getCacheStats() {
        const stats = {
            totalEntries: 0,
            totalSize: 0,
            entries: {}
        };
        
        Object.entries(this.cacheKeys).forEach(([name, key]) => {
            try {
                const cached = localStorage.getItem(`clarity-cache-${key}`);
                if (cached) {
                    const entry = JSON.parse(cached);
                    const isExpired = (Date.now() - entry.timestamp) >= entry.ttl;
                    
                    stats.entries[name] = {
                        size: cached.length,
                        timestamp: entry.timestamp,
                        expired: isExpired
                    };
                    
                    if (!isExpired) {
                        stats.totalEntries++;
                        stats.totalSize += cached.length;
                    }
                }
            } catch (error) {
                console.error('[OfflineData] Failed to get cache stats for', key, error);
            }
        });
        
        return stats;
    }
}

// Initialize offline data manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.offlineDataManager = new OfflineDataManager();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OfflineDataManager;
}
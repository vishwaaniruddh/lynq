/**
 * ADV Clarity Management System - Cache Optimizer
 * Client-side cache optimization and management
 */

class CacheOptimizer {
    constructor() {
        this.config = {
            maxCacheSize: 100 * 1024 * 1024, // 100MB
            cleanupThreshold: 0.8, // Cleanup when 80% full
            optimizationInterval: 30 * 60 * 1000, // 30 minutes
            metricsInterval: 5 * 60 * 1000, // 5 minutes
            preloadBatchSize: 5,
            retryAttempts: 3
        };
        
        this.metrics = {
            cacheHits: 0,
            cacheMisses: 0,
            totalRequests: 0,
            optimizationRuns: 0,
            lastOptimization: null,
            performanceGains: []
        };
        
        this.isOptimizing = false;
        this.preloadQueue = [];
        this.optimizationHistory = [];
        
        this.init();
    }
    
    /**
     * Initialize cache optimizer
     */
    async init() {
        try {
            console.log('[Cache Optimizer] Initializing...');
            
            // Load stored metrics
            await this.loadStoredMetrics();
            
            // Start monitoring
            this.startPerformanceMonitoring();
            
            // Schedule periodic optimization
            this.scheduleOptimization();
            
            // Listen for service worker messages
            this.setupServiceWorkerCommunication();
            
            console.log('[Cache Optimizer] Initialized successfully');
        } catch (error) {
            console.error('[Cache Optimizer] Initialization failed:', error);
        }
    }
    
    /**
     * Start performance monitoring
     */
    startPerformanceMonitoring() {
        // Monitor cache performance
        setInterval(() => {
            this.collectCacheMetrics();
        }, this.config.metricsInterval);
        
        // Monitor network performance
        if ('connection' in navigator) {
            navigator.connection.addEventListener('change', () => {
                this.handleNetworkChange();
            });
        }
        
        // Monitor page visibility for optimization timing
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.optimizeInBackground();
            }
        });
    }
    
    /**
     * Schedule periodic cache optimization
     */
    scheduleOptimization() {
        setInterval(() => {
            if (!this.isOptimizing && !document.hidden) {
                this.runOptimization();
            }
        }, this.config.optimizationInterval);
    }
    
    /**
     * Setup communication with service worker
     */
    setupServiceWorkerCommunication() {
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                this.handleServiceWorkerMessage(event.data);
            });
        }
    }
    
    /**
     * Handle messages from service worker
     */
    handleServiceWorkerMessage(data) {
        switch (data.type) {
            case 'CACHE_METRICS':
                this.updateCacheMetrics(data.metrics);
                break;
                
            case 'OPTIMIZATION_NEEDED':
                this.runOptimization();
                break;
                
            case 'PRELOAD_COMPLETE':
                this.handlePreloadComplete(data);
                break;
        }
    }
    
    /**
     * Run comprehensive cache optimization
     */
    async runOptimization() {
        if (this.isOptimizing) {
            console.log('[Cache Optimizer] Optimization already in progress');
            return;
        }
        
        this.isOptimizing = true;
        const startTime = performance.now();
        
        try {
            console.log('[Cache Optimizer] Starting optimization...');
            
            const results = {
                sizeOptimization: await this.optimizeCacheSize(),
                strategyOptimization: await this.optimizeCacheStrategies(),
                preloadOptimization: await this.optimizePreloading(),
                cleanupResults: await this.performIntelligentCleanup()
            };
            
            const duration = performance.now() - startTime;
            
            // Record optimization results
            this.recordOptimizationResults(results, duration);
            
            // Send results to analytics
            await this.sendOptimizationMetrics(results, duration);
            
            console.log('[Cache Optimizer] Optimization completed in', duration.toFixed(2), 'ms');
            
        } catch (error) {
            console.error('[Cache Optimizer] Optimization failed:', error);
        } finally {
            this.isOptimizing = false;
        }
    }
    
    /**
     * Optimize cache size by removing least used items
     */
    async optimizeCacheSize() {
        try {
            const cacheStatus = await this.getCacheStatus();
            
            if (cacheStatus.usagePercentage < this.config.cleanupThreshold * 100) {
                return { action: 'no_cleanup_needed', currentUsage: cacheStatus.usagePercentage };
            }
            
            // Request cache cleanup from service worker
            const cleanupResults = await this.requestServiceWorkerAction('OPTIMIZE_CACHE', {
                type: 'size',
                targetUsage: 60 // Target 60% usage
            });
            
            return {
                action: 'size_optimized',
                spaceSaved: cleanupResults.spaceSaved || 0,
                entriesRemoved: cleanupResults.entriesRemoved || 0
            };
            
        } catch (error) {
            console.error('[Cache Optimizer] Size optimization failed:', error);
            return { action: 'size_optimization_failed', error: error.message };
        }
    }
    
    /**
     * Optimize caching strategies based on usage patterns
     */
    async optimizeCacheStrategies() {
        try {
            const usagePatterns = await this.analyzeUsagePatterns();
            const optimizations = [];
            
            // Analyze API endpoint patterns
            for (const [endpoint, stats] of Object.entries(usagePatterns.apiEndpoints || {})) {
                if (stats.hitRate < 0.3 && stats.requestCount > 10) {
                    // Switch to network-first for low hit rate APIs
                    optimizations.push({
                        pattern: endpoint,
                        oldStrategy: 'cache-first',
                        newStrategy: 'network-first',
                        reason: 'low_hit_rate'
                    });
                } else if (stats.hitRate > 0.8 && stats.requestCount > 50) {
                    // Switch to cache-first for high hit rate APIs
                    optimizations.push({
                        pattern: endpoint,
                        oldStrategy: 'network-first',
                        newStrategy: 'cache-first',
                        reason: 'high_hit_rate'
                    });
                }
            }
            
            // Apply optimizations
            if (optimizations.length > 0) {
                await this.requestServiceWorkerAction('UPDATE_STRATEGIES', {
                    optimizations: optimizations
                });
            }
            
            return {
                action: 'strategies_optimized',
                optimizationCount: optimizations.length,
                optimizations: optimizations
            };
            
        } catch (error) {
            console.error('[Cache Optimizer] Strategy optimization failed:', error);
            return { action: 'strategy_optimization_failed', error: error.message };
        }
    }
    
    /**
     * Optimize resource preloading
     */
    async optimizePreloading() {
        try {
            const criticalResources = await this.identifyCriticalResources();
            const currentPreloaded = await this.getCurrentPreloadedResources();
            
            // Find resources to add to preload
            const toPreload = criticalResources.filter(resource => 
                !currentPreloaded.includes(resource) && 
                this.shouldPreloadResource(resource)
            );
            
            // Find resources to remove from preload
            const toRemove = currentPreloaded.filter(resource => 
                !criticalResources.includes(resource) ||
                !this.shouldPreloadResource(resource)
            );
            
            // Apply preload changes
            if (toPreload.length > 0) {
                await this.preloadResources(toPreload);
            }
            
            if (toRemove.length > 0) {
                await this.removeFromPreload(toRemove);
            }
            
            return {
                action: 'preload_optimized',
                resourcesAdded: toPreload.length,
                resourcesRemoved: toRemove.length,
                addedResources: toPreload,
                removedResources: toRemove
            };
            
        } catch (error) {
            console.error('[Cache Optimizer] Preload optimization failed:', error);
            return { action: 'preload_optimization_failed', error: error.message };
        }
    }
    
    /**
     * Perform intelligent cache cleanup
     */
    async performIntelligentCleanup() {
        try {
            const cleanupResults = await this.requestServiceWorkerAction('INTELLIGENT_CLEANUP', {
                preserveCritical: true,
                maxAge: 24 * 60 * 60 * 1000, // 24 hours
                sizeLimit: this.config.maxCacheSize
            });
            
            return {
                action: 'intelligent_cleanup',
                entriesRemoved: cleanupResults.entriesRemoved || 0,
                spaceSaved: cleanupResults.spaceSaved || 0,
                cachesCleaned: cleanupResults.cachesCleaned || []
            };
            
        } catch (error) {
            console.error('[Cache Optimizer] Intelligent cleanup failed:', error);
            return { action: 'cleanup_failed', error: error.message };
        }
    }
    
    /**
     * Optimize cache in background when page is hidden
     */
    async optimizeInBackground() {
        if (this.isOptimizing) return;
        
        try {
            // Perform lightweight optimizations
            await this.performIntelligentCleanup();
            await this.preloadCriticalResources();
            
        } catch (error) {
            console.error('[Cache Optimizer] Background optimization failed:', error);
        }
    }
    
    /**
     * Preload critical resources
     */
    async preloadCriticalResources() {
        try {
            const criticalResources = await this.identifyCriticalResources();
            const batchSize = this.config.preloadBatchSize;
            
            // Process in batches to avoid overwhelming the network
            for (let i = 0; i < criticalResources.length; i += batchSize) {
                const batch = criticalResources.slice(i, i + batchSize);
                await this.preloadResourceBatch(batch);
                
                // Small delay between batches
                await new Promise(resolve => setTimeout(resolve, 100));
            }
            
        } catch (error) {
            console.error('[Cache Optimizer] Critical resource preloading failed:', error);
        }
    }
    
    /**
     * Preload a batch of resources
     */
    async preloadResourceBatch(resources) {
        const promises = resources.map(resource => this.preloadResource(resource));
        
        try {
            await Promise.allSettled(promises);
        } catch (error) {
            console.error('[Cache Optimizer] Batch preload failed:', error);
        }
    }
    
    /**
     * Preload individual resource
     */
    async preloadResource(resource) {
        try {
            const response = await fetch(resource, {
                method: 'GET',
                cache: 'force-cache'
            });
            
            if (response.ok) {
                console.log('[Cache Optimizer] Preloaded:', resource);
                return true;
            }
            
        } catch (error) {
            console.warn('[Cache Optimizer] Failed to preload:', resource, error);
        }
        
        return false;
    }
    
    /**
     * Identify critical resources based on usage patterns
     */
    async identifyCriticalResources() {
        try {
            // Get usage analytics
            const usageData = await this.getUsageAnalytics();
            
            // Default critical resources
            const criticalResources = [
                '/',
                '/dashboard.php',
                '/assets/css/tailwind.css',
                '/assets/css/app.css',
                '/assets/js/app.js',
                '/assets/js/pwa-manager.js',
                '/assets/icons/icon-192.png',
                '/offline.html'
            ];
            
            // Add frequently accessed resources
            if (usageData && usageData.frequentResources) {
                usageData.frequentResources.forEach(resource => {
                    if (!criticalResources.includes(resource) && 
                        this.isValidCriticalResource(resource)) {
                        criticalResources.push(resource);
                    }
                });
            }
            
            return criticalResources;
            
        } catch (error) {
            console.error('[Cache Optimizer] Failed to identify critical resources:', error);
            return []; // Return empty array on error
        }
    }
    
    /**
     * Check if resource should be preloaded
     */
    shouldPreloadResource(resource) {
        // Don't preload large files
        if (resource.match(/\.(mp4|avi|zip|pdf)$/i)) {
            return false;
        }
        
        // Don't preload external resources
        if (resource.startsWith('http') && !resource.includes(location.hostname)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if resource is valid for critical resource list
     */
    isValidCriticalResource(resource) {
        // Must be internal resource
        if (resource.startsWith('http') && !resource.includes(location.hostname)) {
            return false;
        }
        
        // Must be reasonable size (estimated)
        const largeFileExtensions = ['.mp4', '.avi', '.zip', '.pdf', '.exe'];
        if (largeFileExtensions.some(ext => resource.toLowerCase().includes(ext))) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get current cache status
     */
    async getCacheStatus() {
        try {
            const response = await fetch('/api/pwa/cache-management.php?action=status');
            if (response.ok) {
                const data = await response.json();
                return data.data;
            }
        } catch (error) {
            console.error('[Cache Optimizer] Failed to get cache status:', error);
        }
        
        return { usagePercentage: 0, totalSize: 0 };
    }
    
    /**
     * Analyze usage patterns
     */
    async analyzeUsagePatterns() {
        try {
            // Get stored usage data
            const storedData = localStorage.getItem('pwa-usage-patterns');
            if (storedData) {
                return JSON.parse(storedData);
            }
        } catch (error) {
            console.error('[Cache Optimizer] Failed to analyze usage patterns:', error);
        }
        
        return { apiEndpoints: {}, resources: {} };
    }
    
    /**
     * Get usage analytics
     */
    async getUsageAnalytics() {
        try {
            const response = await fetch('/api/analytics/pwa-usage.php?action=patterns');
            if (response.ok) {
                const data = await response.json();
                return data.data;
            }
        } catch (error) {
            console.error('[Cache Optimizer] Failed to get usage analytics:', error);
        }
        
        return null;
    }
    
    /**
     * Request action from service worker
     */
    async requestServiceWorkerAction(action, data = {}) {
        return new Promise((resolve, reject) => {
            if (!navigator.serviceWorker.controller) {
                reject(new Error('No service worker controller'));
                return;
            }
            
            const messageChannel = new MessageChannel();
            
            messageChannel.port1.onmessage = (event) => {
                if (event.data.error) {
                    reject(new Error(event.data.error));
                } else {
                    resolve(event.data);
                }
            };
            
            navigator.serviceWorker.controller.postMessage({
                type: action,
                data: data,
                port: messageChannel.port2
            }, [messageChannel.port2]);
            
            // Timeout after 30 seconds
            setTimeout(() => {
                reject(new Error('Service worker action timeout'));
            }, 30000);
        });
    }
    
    /**
     * Collect cache metrics
     */
    async collectCacheMetrics() {
        try {
            // Request metrics from service worker
            const metrics = await this.requestServiceWorkerAction('GET_CACHE_METRICS');
            this.updateCacheMetrics(metrics);
            
        } catch (error) {
            console.error('[Cache Optimizer] Failed to collect cache metrics:', error);
        }
    }
    
    /**
     * Update cache metrics
     */
    updateCacheMetrics(newMetrics) {
        Object.assign(this.metrics, newMetrics);
        
        // Store metrics
        this.storeMetrics();
        
        // Check if optimization is needed
        this.checkOptimizationNeeded();
    }
    
    /**
     * Check if optimization is needed
     */
    checkOptimizationNeeded() {
        const hitRate = this.metrics.cacheHits / (this.metrics.cacheHits + this.metrics.cacheMisses);
        
        // Trigger optimization if hit rate is low
        if (hitRate < 0.6 && this.metrics.totalRequests > 50) {
            console.log('[Cache Optimizer] Low hit rate detected, scheduling optimization');
            setTimeout(() => this.runOptimization(), 1000);
        }
    }
    
    /**
     * Handle network change
     */
    handleNetworkChange() {
        const connection = navigator.connection;
        
        if (connection) {
            console.log('[Cache Optimizer] Network changed:', {
                effectiveType: connection.effectiveType,
                downlink: connection.downlink,
                rtt: connection.rtt
            });
            
            // Adjust optimization strategy based on network
            if (connection.effectiveType === 'slow-2g' || connection.effectiveType === '2g') {
                // Aggressive caching for slow networks
                this.optimizeForSlowNetwork();
            } else if (connection.effectiveType === '4g') {
                // More selective caching for fast networks
                this.optimizeForFastNetwork();
            }
        }
    }
    
    /**
     * Optimize for slow network conditions
     */
    async optimizeForSlowNetwork() {
        try {
            // Preload more critical resources
            await this.preloadCriticalResources();
            
            // Switch to more aggressive caching
            await this.requestServiceWorkerAction('UPDATE_STRATEGIES', {
                networkCondition: 'slow',
                defaultStrategy: 'cache-first'
            });
            
        } catch (error) {
            console.error('[Cache Optimizer] Slow network optimization failed:', error);
        }
    }
    
    /**
     * Optimize for fast network conditions
     */
    async optimizeForFastNetwork() {
        try {
            // Use more network-first strategies for fresh content
            await this.requestServiceWorkerAction('UPDATE_STRATEGIES', {
                networkCondition: 'fast',
                defaultStrategy: 'network-first'
            });
            
        } catch (error) {
            console.error('[Cache Optimizer] Fast network optimization failed:', error);
        }
    }
    
    /**
     * Record optimization results
     */
    recordOptimizationResults(results, duration) {
        const record = {
            timestamp: Date.now(),
            duration: duration,
            results: results
        };
        
        this.optimizationHistory.push(record);
        
        // Keep only last 50 records
        if (this.optimizationHistory.length > 50) {
            this.optimizationHistory = this.optimizationHistory.slice(-50);
        }
        
        this.metrics.optimizationRuns++;
        this.metrics.lastOptimization = Date.now();
        
        // Store updated metrics
        this.storeMetrics();
    }
    
    /**
     * Send optimization metrics to analytics
     */
    async sendOptimizationMetrics(results, duration) {
        try {
            await fetch('/api/analytics/performance-insights.php?action=track', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    metric_type: 'cache_optimization',
                    value: duration,
                    context: {
                        results: results,
                        timestamp: Date.now()
                    }
                })
            });
            
        } catch (error) {
            console.error('[Cache Optimizer] Failed to send optimization metrics:', error);
        }
    }
    
    /**
     * Load stored metrics
     */
    async loadStoredMetrics() {
        try {
            const stored = localStorage.getItem('pwa-cache-optimizer-metrics');
            if (stored) {
                const parsedMetrics = JSON.parse(stored);
                Object.assign(this.metrics, parsedMetrics);
            }
        } catch (error) {
            console.error('[Cache Optimizer] Failed to load stored metrics:', error);
        }
    }
    
    /**
     * Store metrics to localStorage
     */
    storeMetrics() {
        try {
            localStorage.setItem('pwa-cache-optimizer-metrics', JSON.stringify(this.metrics));
        } catch (error) {
            console.error('[Cache Optimizer] Failed to store metrics:', error);
        }
    }
    
    /**
     * Get optimization statistics
     */
    getOptimizationStats() {
        return {
            metrics: { ...this.metrics },
            history: [...this.optimizationHistory],
            isOptimizing: this.isOptimizing,
            config: { ...this.config }
        };
    }
    
    /**
     * Manual optimization trigger
     */
    async triggerOptimization() {
        console.log('[Cache Optimizer] Manual optimization triggered');
        return await this.runOptimization();
    }
}

// Initialize cache optimizer when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.cacheOptimizer = new CacheOptimizer();
    });
} else {
    window.cacheOptimizer = new CacheOptimizer();
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CacheOptimizer;
}
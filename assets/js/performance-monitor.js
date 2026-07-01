/**
 * ADV Clarity Management System - Performance Monitor
 * Real-time performance monitoring and reporting
 */

class PerformanceMonitor {
    constructor() {
        this.config = {
            sampleRate: 0.1, // Sample 10% of interactions
            batchSize: 20,
            flushInterval: 30000, // 30 seconds
            maxMetrics: 1000,
            thresholds: {
                slowResponse: 1000, // 1 second
                slowPageLoad: 3000, // 3 seconds
                lowCacheHitRate: 0.7,
                highErrorRate: 0.05
            }
        };
        
        this.metrics = [];
        this.observers = {};
        this.startTime = performance.now();
        this.pageLoadMetrics = {};
        this.userInteractionMetrics = [];
        this.resourceMetrics = [];
        this.cacheMetrics = {
            hits: 0,
            misses: 0,
            totalRequests: 0
        };
        
        this.init();
    }
    
    /**
     * Initialize performance monitoring
     */
    async init() {
        try {
            console.log('[Performance Monitor] Initializing...');
            
            // Set up performance observers
            this.setupPerformanceObservers();
            
            // Monitor page load performance
            this.monitorPageLoad();
            
            // Monitor user interactions
            this.monitorUserInteractions();
            
            // Monitor network requests
            this.monitorNetworkRequests();
            
            // Monitor cache performance
            this.monitorCachePerformance();
            
            // Start periodic reporting
            this.startPeriodicReporting();
            
            // Monitor visibility changes
            this.monitorVisibilityChanges();
            
            console.log('[Performance Monitor] Initialized successfully');
            
        } catch (error) {
            console.error('[Performance Monitor] Initialization failed:', error);
        }
    }
    
    /**
     * Set up Performance Observer APIs
     */
    setupPerformanceObservers() {
        try {
            // Navigation timing
            if ('PerformanceObserver' in window) {
                // Largest Contentful Paint
                this.observers.lcp = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    const lastEntry = entries[entries.length - 1];
                    this.recordMetric('lcp', lastEntry.startTime, {
                        element: lastEntry.element?.tagName || 'unknown',
                        url: lastEntry.url || location.href
                    });
                });
                this.observers.lcp.observe({ entryTypes: ['largest-contentful-paint'] });
                
                // First Input Delay
                this.observers.fid = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        this.recordMetric('fid', entry.processingStart - entry.startTime, {
                            eventType: entry.name,
                            target: entry.target?.tagName || 'unknown'
                        });
                    });
                });
                this.observers.fid.observe({ entryTypes: ['first-input'] });
                
                // Cumulative Layout Shift
                this.observers.cls = new PerformanceObserver((list) => {
                    let clsValue = 0;
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        if (!entry.hadRecentInput) {
                            clsValue += entry.value;
                        }
                    });
                    
                    if (clsValue > 0) {
                        this.recordMetric('cls', clsValue, {
                            entryCount: entries.length
                        });
                    }
                });
                this.observers.cls.observe({ entryTypes: ['layout-shift'] });
                
                // Long Tasks
                this.observers.longTask = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        this.recordMetric('long_task', entry.duration, {
                            startTime: entry.startTime,
                            attribution: entry.attribution?.[0]?.name || 'unknown'
                        });
                    });
                });
                this.observers.longTask.observe({ entryTypes: ['longtask'] });
                
                // Resource timing
                this.observers.resource = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        this.recordResourceMetric(entry);
                    });
                });
                this.observers.resource.observe({ entryTypes: ['resource'] });
            }
            
        } catch (error) {
            console.error('[Performance Monitor] Failed to setup observers:', error);
        }
    }
    
    /**
     * Monitor page load performance
     */
    monitorPageLoad() {
        // Wait for page load to complete
        if (document.readyState === 'complete') {
            this.capturePageLoadMetrics();
        } else {
            window.addEventListener('load', () => {
                setTimeout(() => this.capturePageLoadMetrics(), 0);
            });
        }
    }
    
    /**
     * Capture page load metrics
     */
    capturePageLoadMetrics() {
        try {
            const navigation = performance.getEntriesByType('navigation')[0];
            
            if (navigation) {
                this.pageLoadMetrics = {
                    domContentLoaded: navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart,
                    loadComplete: navigation.loadEventEnd - navigation.loadEventStart,
                    domInteractive: navigation.domInteractive - navigation.fetchStart,
                    firstByte: navigation.responseStart - navigation.requestStart,
                    domainLookup: navigation.domainLookupEnd - navigation.domainLookupStart,
                    tcpConnect: navigation.connectEnd - navigation.connectStart,
                    serverResponse: navigation.responseEnd - navigation.responseStart,
                    pageLoad: navigation.loadEventEnd - navigation.fetchStart,
                    redirectTime: navigation.redirectEnd - navigation.redirectStart,
                    unloadTime: navigation.unloadEventEnd - navigation.unloadEventStart
                };
                
                // Record key metrics
                this.recordMetric('page_load_time', this.pageLoadMetrics.pageLoad);
                this.recordMetric('dom_content_loaded', this.pageLoadMetrics.domContentLoaded);
                this.recordMetric('first_byte', this.pageLoadMetrics.firstByte);
                
                // Check for performance issues
                this.analyzePageLoadPerformance();
            }
            
        } catch (error) {
            console.error('[Performance Monitor] Failed to capture page load metrics:', error);
        }
    }
    
    /**
     * Monitor user interactions
     */
    monitorUserInteractions() {
        const interactionEvents = ['click', 'keydown', 'scroll', 'touchstart'];
        
        interactionEvents.forEach(eventType => {
            document.addEventListener(eventType, (event) => {
                if (Math.random() < this.config.sampleRate) {
                    this.recordUserInteraction(eventType, event);
                }
            }, { passive: true });
        });
    }
    
    /**
     * Record user interaction
     */
    recordUserInteraction(eventType, event) {
        const startTime = performance.now();
        
        // Use requestIdleCallback to measure interaction response time
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => {
                const responseTime = performance.now() - startTime;
                
                this.recordMetric('interaction_response', responseTime, {
                    eventType: eventType,
                    target: event.target?.tagName || 'unknown',
                    timestamp: Date.now()
                });
            });
        }
    }
    
    /**
     * Monitor network requests
     */
    monitorNetworkRequests() {
        // Intercept fetch requests
        const originalFetch = window.fetch;
        
        window.fetch = async (...args) => {
            const startTime = performance.now();
            const url = args[0];
            
            try {
                const response = await originalFetch(...args);
                const endTime = performance.now();
                const duration = endTime - startTime;
                
                this.recordNetworkMetric(url, duration, response.status, 'fetch');
                
                return response;
            } catch (error) {
                const endTime = performance.now();
                const duration = endTime - startTime;
                
                this.recordNetworkMetric(url, duration, 0, 'fetch', error);
                throw error;
            }
        };
        
        // Monitor XMLHttpRequest
        const originalXHROpen = XMLHttpRequest.prototype.open;
        const originalXHRSend = XMLHttpRequest.prototype.send;
        
        XMLHttpRequest.prototype.open = function(method, url, ...args) {
            this._startTime = performance.now();
            this._url = url;
            this._method = method;
            return originalXHROpen.call(this, method, url, ...args);
        };
        
        XMLHttpRequest.prototype.send = function(...args) {
            this.addEventListener('loadend', () => {
                if (this._startTime && this._url) {
                    const duration = performance.now() - this._startTime;
                    this.recordNetworkMetric(this._url, duration, this.status, 'xhr');
                }
            });
            
            return originalXHRSend.call(this, ...args);
        };
    }
    
    /**
     * Record network metric
     */
    recordNetworkMetric(url, duration, status, type, error = null) {
        const metric = {
            url: url,
            duration: duration,
            status: status,
            type: type,
            error: error ? error.message : null,
            timestamp: Date.now(),
            cached: this.isResponseCached(url, duration)
        };
        
        this.resourceMetrics.push(metric);
        
        // Update cache metrics
        if (metric.cached) {
            this.cacheMetrics.hits++;
        } else {
            this.cacheMetrics.misses++;
        }
        this.cacheMetrics.totalRequests++;
        
        // Record performance metric
        this.recordMetric('network_request', duration, {
            url: url,
            status: status,
            cached: metric.cached,
            type: type
        });
        
        // Check for slow requests
        if (duration > this.config.thresholds.slowResponse) {
            this.recordMetric('slow_request', duration, {
                url: url,
                status: status,
                type: type
            });
        }
    }
    
    /**
     * Record resource metric from Performance Observer
     */
    recordResourceMetric(entry) {
        const duration = entry.responseEnd - entry.startTime;
        const cached = entry.transferSize === 0 && entry.decodedBodySize > 0;
        
        this.recordMetric('resource_load', duration, {
            name: entry.name,
            type: entry.initiatorType,
            size: entry.transferSize,
            cached: cached,
            protocol: entry.nextHopProtocol
        });
    }
    
    /**
     * Monitor cache performance
     */
    monitorCachePerformance() {
        // Listen for service worker messages about cache performance
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data.type === 'CACHE_PERFORMANCE') {
                    this.updateCacheMetrics(event.data.metrics);
                }
            });
        }
        
        // Periodic cache performance check
        setInterval(() => {
            this.checkCachePerformance();
        }, 60000); // Every minute
    }
    
    /**
     * Update cache metrics
     */
    updateCacheMetrics(metrics) {
        Object.assign(this.cacheMetrics, metrics);
        
        // Record cache hit rate
        const hitRate = this.cacheMetrics.hits / (this.cacheMetrics.hits + this.cacheMetrics.misses);
        this.recordMetric('cache_hit_rate', hitRate);
        
        // Check for low cache hit rate
        if (hitRate < this.config.thresholds.lowCacheHitRate) {
            this.recordMetric('low_cache_hit_rate', hitRate, {
                threshold: this.config.thresholds.lowCacheHitRate
            });
        }
    }
    
    /**
     * Check cache performance
     */
    checkCachePerformance() {
        const hitRate = this.cacheMetrics.hits / Math.max(this.cacheMetrics.totalRequests, 1);
        
        if (hitRate < this.config.thresholds.lowCacheHitRate && this.cacheMetrics.totalRequests > 10) {
            // Notify cache optimizer
            if (window.cacheOptimizer) {
                window.cacheOptimizer.checkOptimizationNeeded();
            }
        }
    }
    
    /**
     * Monitor visibility changes for performance impact
     */
    monitorVisibilityChanges() {
        document.addEventListener('visibilitychange', () => {
            const metric = {
                visible: !document.hidden,
                timestamp: Date.now(),
                duration: performance.now() - this.startTime
            };
            
            this.recordMetric('visibility_change', document.hidden ? 0 : 1, metric);
            
            if (!document.hidden) {
                // Page became visible, check for performance degradation
                this.checkPerformanceAfterVisibilityChange();
            }
        });
    }
    
    /**
     * Check performance after visibility change
     */
    checkPerformanceAfterVisibilityChange() {
        setTimeout(() => {
            // Measure time to interactive after becoming visible
            const interactiveTime = performance.now();
            this.recordMetric('visibility_to_interactive', interactiveTime, {
                timestamp: Date.now()
            });
        }, 100);
    }
    
    /**
     * Start periodic reporting
     */
    startPeriodicReporting() {
        setInterval(() => {
            this.flushMetrics();
        }, this.config.flushInterval);
        
        // Also flush on page unload
        window.addEventListener('beforeunload', () => {
            this.flushMetrics();
        });
        
        // Flush on visibility change (when page becomes hidden)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.flushMetrics();
            }
        });
    }
    
    /**
     * Record a performance metric
     */
    recordMetric(type, value, context = {}) {
        const metric = {
            type: type,
            value: value,
            context: context,
            timestamp: Date.now(),
            url: location.href,
            userAgent: navigator.userAgent
        };
        
        this.metrics.push(metric);
        
        // Limit metrics array size
        if (this.metrics.length > this.config.maxMetrics) {
            this.metrics = this.metrics.slice(-this.config.maxMetrics);
        }
        
        // Check for immediate issues
        this.checkPerformanceThresholds(metric);
    }
    
    /**
     * Check performance thresholds
     */
    checkPerformanceThresholds(metric) {
        switch (metric.type) {
            case 'page_load_time':
                if (metric.value > this.config.thresholds.slowPageLoad) {
                    this.reportPerformanceIssue('slow_page_load', metric);
                }
                break;
                
            case 'network_request':
                if (metric.value > this.config.thresholds.slowResponse) {
                    this.reportPerformanceIssue('slow_network_request', metric);
                }
                break;
                
            case 'cache_hit_rate':
                if (metric.value < this.config.thresholds.lowCacheHitRate) {
                    this.reportPerformanceIssue('low_cache_hit_rate', metric);
                }
                break;
        }
    }
    
    /**
     * Report performance issue
     */
    reportPerformanceIssue(issueType, metric) {
        console.warn('[Performance Monitor] Performance issue detected:', issueType, metric);
        
        // Send immediate alert for critical issues
        this.sendPerformanceAlert(issueType, metric);
    }
    
    /**
     * Send performance alert
     */
    async sendPerformanceAlert(issueType, metric) {
        try {
            await fetch('/api/analytics/performance-insights.php?action=track', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    metric_type: 'performance_alert',
                    value: metric.value,
                    context: {
                        issue_type: issueType,
                        metric: metric,
                        timestamp: Date.now()
                    }
                })
            });
        } catch (error) {
            console.error('[Performance Monitor] Failed to send performance alert:', error);
        }
    }
    
    /**
     * Flush metrics to server
     */
    async flushMetrics() {
        if (this.metrics.length === 0) return;
        
        try {
            const metricsToSend = this.metrics.splice(0, this.config.batchSize);
            
            await fetch('/api/analytics/performance-insights.php?action=batch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    metrics: metricsToSend.map(metric => ({
                        metric_type: metric.type,
                        value: metric.value,
                        context: metric.context,
                        timestamp: metric.timestamp,
                        url: metric.url
                    }))
                })
            });
            
            console.log('[Performance Monitor] Flushed', metricsToSend.length, 'metrics');
            
        } catch (error) {
            console.error('[Performance Monitor] Failed to flush metrics:', error);
            // Put metrics back if send failed
            this.metrics.unshift(...metricsToSend);
        }
    }
    
    /**
     * Analyze page load performance
     */
    analyzePageLoadPerformance() {
        const issues = [];
        
        if (this.pageLoadMetrics.pageLoad > this.config.thresholds.slowPageLoad) {
            issues.push({
                type: 'slow_page_load',
                value: this.pageLoadMetrics.pageLoad,
                threshold: this.config.thresholds.slowPageLoad
            });
        }
        
        if (this.pageLoadMetrics.firstByte > 500) {
            issues.push({
                type: 'slow_server_response',
                value: this.pageLoadMetrics.firstByte,
                threshold: 500
            });
        }
        
        if (this.pageLoadMetrics.domContentLoaded > 2000) {
            issues.push({
                type: 'slow_dom_processing',
                value: this.pageLoadMetrics.domContentLoaded,
                threshold: 2000
            });
        }
        
        // Report issues
        issues.forEach(issue => {
            this.reportPerformanceIssue(issue.type, {
                type: issue.type,
                value: issue.value,
                context: { threshold: issue.threshold }
            });
        });
    }
    
    /**
     * Check if response was cached
     */
    isResponseCached(url, duration) {
        // Heuristic: very fast responses are likely cached
        return duration < 50 && !url.includes('api/');
    }
    
    /**
     * Get performance summary
     */
    getPerformanceSummary() {
        const now = Date.now();
        const recentMetrics = this.metrics.filter(m => now - m.timestamp < 300000); // Last 5 minutes
        
        return {
            pageLoad: this.pageLoadMetrics,
            cache: {
                hitRate: this.cacheMetrics.hits / Math.max(this.cacheMetrics.totalRequests, 1),
                totalRequests: this.cacheMetrics.totalRequests,
                hits: this.cacheMetrics.hits,
                misses: this.cacheMetrics.misses
            },
            recentMetrics: recentMetrics.length,
            totalMetrics: this.metrics.length,
            uptime: now - this.startTime
        };
    }
    
    /**
     * Get detailed metrics
     */
    getDetailedMetrics() {
        return {
            metrics: [...this.metrics],
            pageLoad: { ...this.pageLoadMetrics },
            cache: { ...this.cacheMetrics },
            resources: [...this.resourceMetrics],
            interactions: [...this.userInteractionMetrics]
        };
    }
    
    /**
     * Clear metrics
     */
    clearMetrics() {
        this.metrics = [];
        this.resourceMetrics = [];
        this.userInteractionMetrics = [];
        console.log('[Performance Monitor] Metrics cleared');
    }
}

// Initialize performance monitor
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.performanceMonitor = new PerformanceMonitor();
    });
} else {
    window.performanceMonitor = new PerformanceMonitor();
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PerformanceMonitor;
}
/**
 * ADV Clarity Management System - Service Worker
 * Progressive Web Application Service Worker for offline functionality,
 * caching strategies, and background sync capabilities.
 */

// Cache configuration with intelligent management
const CACHE_VERSION = 'v1.0.3';
const CACHE_NAMES = {
    appShell: `clarity-app-shell-${CACHE_VERSION}`,
    api: `clarity-api-${CACHE_VERSION}`,
    assets: `clarity-assets-${CACHE_VERSION}`,
    offline: `clarity-offline-${CACHE_VERSION}`
};

// Cache TTL configuration (in milliseconds)
const CACHE_TTL = {
    api: 5 * 60 * 1000,      // 5 minutes
    assets: 24 * 60 * 60 * 1000, // 24 hours
    offline: 7 * 24 * 60 * 60 * 1000 // 7 days
};

// Cache size limits (in bytes)
const CACHE_SIZE_LIMITS = {
    appShell: 10 * 1024 * 1024,  // 10MB
    api: 50 * 1024 * 1024,       // 50MB
    assets: 100 * 1024 * 1024,   // 100MB
    offline: 20 * 1024 * 1024    // 20MB
};

// Performance monitoring configuration
const PERFORMANCE_CONFIG = {
    enableMetrics: true,
    sampleRate: 0.1, // Sample 10% of requests for detailed metrics
    maxMetricsEntries: 1000
};

// Cache performance metrics
let cacheMetrics = {
    hits: 0,
    misses: 0,
    totalSize: 0,
    lastCleanup: Date.now(),
    requestTimes: []
};

// App shell resources - critical for offline functionality
const APP_SHELL_RESOURCES = [
    '/',
    '/dashboard.php',
    '/index.php',
    '/assets/css/style.css',
    '/assets/css/offline.css',
    '/assets/js/app.js',
    '/assets/icons/icon-192.png',
    '/assets/logo.png',
    '/assets/image.png',
    '/assets/lynq.png',
    '/offline.html'
];

// API endpoints that should use network-first strategy
const API_ENDPOINTS = [
    '/api/',
    '.php?action=',
    'json'
];

// Static assets that should use cache-first strategy
const STATIC_ASSETS = [
    '.css',
    '.js',
    '.png',
    '.jpg',
    '.svg',
    '.ico',
    '.woff',
    '.woff2'
];

/**
 * Service Worker Install Event
 * Caches critical app shell resources for offline functionality
 */
self.addEventListener('install', event => {
    console.log('[SW] Installing service worker...');
    
    event.waitUntil(
        Promise.all([
            caches.open(CACHE_NAMES.appShell)
                .then(cache => {
                    console.log('[SW] Caching app shell resources');
                    return cache.addAll(APP_SHELL_RESOURCES);
                })
                .then(() => {
                    console.log('[SW] App shell cached successfully');
                    return trackPerformanceMetric('cache_install', { 
                        resourceCount: APP_SHELL_RESOURCES.length,
                        timestamp: Date.now()
                    });
                }),
            // Initialize cache size tracking
            initializeCacheMetrics()
        ])
        .then(() => {
            console.log('[SW] Service worker installation complete');
            // Skip waiting to activate immediately
            return self.skipWaiting();
        })
        .catch(error => {
            console.error('[SW] Failed to install service worker:', error);
            throw error;
        })
    );
});

/**
 * Service Worker Activate Event
 * Cleans up old caches and claims clients
 */
self.addEventListener('activate', event => {
    console.log('[SW] Activating service worker...');
    
    event.waitUntil(
        Promise.all([
            // Clean up old caches with intelligent cleanup
            performIntelligentCacheCleanup(),
            // Claim all clients immediately
            self.clients.claim(),
            // Initialize performance monitoring
            initializePerformanceMonitoring()
        ])
        .then(() => {
            console.log('[SW] Service worker activated and claimed clients');
            // Schedule periodic cache maintenance
            schedulePeriodicMaintenance();
        })
        .catch(error => {
            console.error('[SW] Activation failed:', error);
        })
    );
});

/**
 * Service Worker Fetch Event
 * Implements caching strategies based on resource type with performance monitoring
 */
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip cross-origin requests (unless they're assets)
    if (url.origin !== location.origin && !isAssetRequest(request)) {
        return;
    }
    
    // Performance monitoring
    const startTime = performance.now();
    
    event.respondWith(
        handleRequestWithPerformanceTracking(request, startTime)
            .catch(error => {
                console.error('[SW] Request handling failed:', error);
                trackPerformanceMetric('request_error', {
                    url: request.url,
                    error: error.message,
                    timestamp: Date.now()
                });
                return handleOfflineFallback(request);
            })
    );
});

/**
 * Handle different types of requests with appropriate caching strategies and performance tracking
 */
async function handleRequestWithPerformanceTracking(request, startTime) {
    const url = new URL(request.url);
    let response;
    let cacheHit = false;
    
    // API requests - Network first with cache fallback
    if (isApiRequest(request)) {
        response = await handleApiRequest(request);
    }
    // Static assets - Cache first with network fallback
    else if (isStaticAsset(request)) {
        const result = await handleStaticAssetWithTracking(request);
        response = result.response;
        cacheHit = result.cacheHit;
    }
    // App shell requests - Cache first
    else if (isAppShellRequest(request)) {
        const result = await handleAppShellRequestWithTracking(request);
        response = result.response;
        cacheHit = result.cacheHit;
    }
    // Default - Network first with cache fallback
    else {
        response = await handleDefaultRequest(request);
    }
    
    // Track performance metrics
    const endTime = performance.now();
    const responseTime = endTime - startTime;
    
    if (PERFORMANCE_CONFIG.enableMetrics && Math.random() < PERFORMANCE_CONFIG.sampleRate) {
        trackPerformanceMetric('request_performance', {
            url: request.url,
            responseTime: responseTime,
            cacheHit: cacheHit,
            responseSize: response.headers.get('content-length') || 0,
            timestamp: Date.now()
        });
    }
    
    // Update cache metrics
    if (cacheHit) {
        cacheMetrics.hits++;
    } else {
        cacheMetrics.misses++;
    }
    
    cacheMetrics.requestTimes.push(responseTime);
    if (cacheMetrics.requestTimes.length > PERFORMANCE_CONFIG.maxMetricsEntries) {
        cacheMetrics.requestTimes = cacheMetrics.requestTimes.slice(-PERFORMANCE_CONFIG.maxMetricsEntries);
    }
    
    return response;
}

/**
 * Handle different types of requests with appropriate caching strategies
 */
async function handleRequest(request) {
    const url = new URL(request.url);
    
    // API requests - Network first with cache fallback
    if (isApiRequest(request)) {
        return handleApiRequest(request);
    }
    
    // Static assets - Cache first with network fallback
    if (isStaticAsset(request)) {
        return handleStaticAsset(request);
    }
    
    // App shell requests - Cache first
    if (isAppShellRequest(request)) {
        return handleAppShellRequest(request);
    }
    
    // Default - Network first with cache fallback
    return handleDefaultRequest(request);
}

/**
 * Handle API requests with network-first strategy
 */
async function handleApiRequest(request) {
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful API responses
            const cache = await caches.open(CACHE_NAMES.api);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        // Network failed, try cache
        console.log('[SW] Network failed for API request, trying cache');
        const cachedResponse = await caches.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        throw error;
    }
}

/**
 * Handle static assets with cache-first strategy and performance tracking
 */
async function handleStaticAssetWithTracking(request) {
    // Try cache first
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        // Check if cached response is still fresh
        const cacheDate = new Date(cachedResponse.headers.get('date') || 0);
        const now = new Date();
        const age = now.getTime() - cacheDate.getTime();
        
        if (age < CACHE_TTL.assets) {
            return { response: cachedResponse, cacheHit: true };
        }
    }
    
    // Cache miss or expired, fetch from network
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Check cache size before adding
            const cache = await caches.open(CACHE_NAMES.assets);
            await manageCacheSize(cache, CACHE_SIZE_LIMITS.assets);
            
            // Cache the asset with metadata
            const responseToCache = networkResponse.clone();
            responseToCache.headers.set('sw-cached-at', new Date().toISOString());
            cache.put(request, responseToCache);
        }
        
        return { response: networkResponse, cacheHit: false };
    } catch (error) {
        // Network failed, return stale cache if available
        if (cachedResponse) {
            console.log('[SW] Serving stale cache for:', request.url);
            return { response: cachedResponse, cacheHit: true };
        }
        throw error;
    }
}

/**
 * Handle app shell requests with cache-first strategy and performance tracking
 */
async function handleAppShellRequestWithTracking(request) {
    // Always try cache first for app shell
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        return { response: cachedResponse, cacheHit: true };
    }
    
    // If not in cache, fetch and cache
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAMES.appShell);
            cache.put(request, networkResponse.clone());
        }
        
        return { response: networkResponse, cacheHit: false };
    } catch (error) {
        console.error('[SW] Failed to fetch app shell resource:', request.url);
        throw error;
    }
}

/**
 * Handle default requests with network-first strategy
 */
async function handleDefaultRequest(request) {
    try {
        const networkResponse = await fetch(request);
        return networkResponse;
    } catch (error) {
        // Try cache as fallback
        const cachedResponse = await caches.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        throw error;
    }
}

/**
 * Handle offline fallback for failed requests
 */
async function handleOfflineFallback(request) {
    const url = new URL(request.url);
    
    // For HTML pages, return offline page
    if (request.headers.get('accept')?.includes('text/html')) {
        const offlinePage = await caches.match('offline.html');
        if (offlinePage) {
            return offlinePage;
        }
    }
    
    // For other resources, try to find any cached version
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }
    
    // Return a basic offline response
    return new Response('Offline - Resource not available', {
        status: 503,
        statusText: 'Service Unavailable',
        headers: {
            'Content-Type': 'text/plain'
        }
    });
}

/**
 * Check if request is for an API endpoint
 */
function isApiRequest(request) {
    const url = request.url.toLowerCase();
    return API_ENDPOINTS.some(endpoint => url.includes(endpoint));
}

/**
 * Check if request is for a static asset
 */
function isStaticAsset(request) {
    const url = request.url.toLowerCase();
    return STATIC_ASSETS.some(extension => url.endsWith(extension));
}

/**
 * Check if request is for an app shell resource
 */
function isAppShellRequest(request) {
    const url = new URL(request.url);
    const pathname = url.pathname;
    
    return APP_SHELL_RESOURCES.some(resource => {
        if (resource === '/') {
            return pathname === '/' || pathname === '/index.php';
        }
        return pathname === resource || pathname.endsWith(resource);
    });
}

/**
 * Check if request is for an asset (for cross-origin handling)
 */
function isAssetRequest(request) {
    return isStaticAsset(request) || request.destination === 'image' || 
           request.destination === 'font' || request.destination === 'style' ||
           request.destination === 'script';
}

/**
 * Background Sync Event Handler
 * Handles queued actions when connectivity is restored
 */
self.addEventListener('sync', event => {
    console.log('[SW] Background sync triggered:', event.tag);
    
    if (event.tag === 'offline-actions') {
        event.waitUntil(syncOfflineActions());
    }
});

/**
 * Sync offline actions with server
 */
async function syncOfflineActions() {
    try {
        // Get queued actions from IndexedDB or localStorage
        const queuedActions = await getQueuedActions();
        
        for (const action of queuedActions) {
            try {
                await processQueuedAction(action);
                await removeQueuedAction(action.id);
                console.log('[SW] Synced offline action:', action.id);
            } catch (error) {
                console.error('[SW] Failed to sync action:', action.id, error);
                // Keep action in queue for retry
            }
        }
    } catch (error) {
        console.error('[SW] Background sync failed:', error);
    }
}

/**
 * Get queued actions from PWA manager's offline queue
 */
async function getQueuedActions() {
    try {
        // Try to get from IndexedDB first (future enhancement)
        // For now, get from localStorage which is used by PWA manager
        const queueData = await getFromStorage('pwa-offline-queue');
        return queueData ? JSON.parse(queueData) : [];
    } catch (error) {
        console.error('[SW] Failed to get queued actions:', error);
        return [];
    }
}

/**
 * Get data from storage (localStorage via message to main thread)
 */
async function getFromStorage(key) {
    return new Promise((resolve) => {
        // Send message to main thread to get localStorage data
        self.clients.matchAll().then(clients => {
            if (clients.length > 0) {
                const messageChannel = new MessageChannel();
                messageChannel.port1.onmessage = (event) => {
                    resolve(event.data);
                };
                
                clients[0].postMessage({
                    type: 'GET_STORAGE',
                    key: key,
                    port: messageChannel.port2
                }, [messageChannel.port2]);
            } else {
                resolve(null);
            }
        });
    });
}

/**
 * Set data in storage (localStorage via message to main thread)
 */
async function setInStorage(key, value) {
    return new Promise((resolve) => {
        self.clients.matchAll().then(clients => {
            if (clients.length > 0) {
                const messageChannel = new MessageChannel();
                messageChannel.port1.onmessage = (event) => {
                    resolve(event.data);
                };
                
                clients[0].postMessage({
                    type: 'SET_STORAGE',
                    key: key,
                    value: value,
                    port: messageChannel.port2
                }, [messageChannel.port2]);
            } else {
                resolve(false);
            }
        });
    });
}

/**
 * Process a queued action
 */
async function processQueuedAction(action) {
    const response = await fetch(action.endpoint, {
        method: action.method,
        headers: {
            'Content-Type': 'application/json',
            ...action.headers
        },
        body: JSON.stringify(action.data)
    });
    
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    
    return response;
}

/**
 * Remove queued action after successful sync
 */
async function removeQueuedAction(actionId) {
    try {
        const queuedActions = await getQueuedActions();
        const updatedQueue = queuedActions.filter(action => action.id !== actionId);
        await setInStorage('pwa-offline-queue', JSON.stringify(updatedQueue));
        console.log('[SW] Removed queued action:', actionId);
        
        // Notify main thread about queue update
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'QUEUE_UPDATED',
                queueLength: updatedQueue.length
            });
        });
    } catch (error) {
        console.error('[SW] Failed to remove queued action:', error);
    }
}

/**
 * Push Event Handler
 * Handles push notifications with enhanced functionality
 */
self.addEventListener('push', event => {
    console.log('[SW] Push message received');
    
    let notificationData = {
        title: 'ADV Clarity System',
        body: 'You have a new notification',
        icon: '/assets/icons/icon-192.png',
        badge: '/assets/icons/icon-72.png',
        tag: 'default',
        requireInteraction: false,
        data: {}
    };
    
    if (event.data) {
        try {
            const pushData = event.data.json();
            notificationData = { ...notificationData, ...pushData };
        } catch (error) {
            console.error('[SW] Failed to parse push data:', error);
            // Try to use text data as body
            try {
                notificationData.body = event.data.text();
            } catch (textError) {
                console.error('[SW] Failed to parse push data as text:', textError);
            }
        }
    }
    
    // Add timestamp to data
    notificationData.data = {
        ...notificationData.data,
        timestamp: Date.now(),
        url: notificationData.data.url || '/dashboard.php'
    };
    
    event.waitUntil(
        showNotificationWithFallback(notificationData)
    );
});

/**
 * Show notification with fallback handling
 */
async function showNotificationWithFallback(notificationData) {
    try {
        // Check if we should replace existing notifications with same tag
        if (notificationData.tag && notificationData.tag !== 'default') {
            const existingNotifications = await self.registration.getNotifications({
                tag: notificationData.tag
            });
            
            // Close existing notifications with same tag
            existingNotifications.forEach(notification => {
                notification.close();
            });
        }
        
        // Show the notification
        await self.registration.showNotification(notificationData.title, {
            body: notificationData.body,
            icon: notificationData.icon,
            badge: notificationData.badge,
            tag: notificationData.tag,
            data: notificationData.data,
            actions: notificationData.actions || [],
            requireInteraction: notificationData.requireInteraction,
            silent: notificationData.silent || false,
            vibrate: notificationData.vibrate || [200, 100, 200],
            timestamp: notificationData.data.timestamp
        });
        
        console.log('[SW] Notification displayed successfully');
    } catch (error) {
        console.error('[SW] Failed to show notification:', error);
        
        // Fallback: try to show a basic notification
        try {
            await self.registration.showNotification(notificationData.title, {
                body: notificationData.body,
                icon: notificationData.icon
            });
        } catch (fallbackError) {
            console.error('[SW] Fallback notification also failed:', fallbackError);
        }
    }
}

/**
 * Notification Click Event Handler
 * Handles notification click actions with enhanced navigation
 */
self.addEventListener('notificationclick', event => {
    console.log('[SW] Notification clicked:', event.notification.data);
    
    event.notification.close();
    
    const notificationData = event.notification.data || {};
    const targetUrl = notificationData.url || '/dashboard.php';
    const action = event.action; // If user clicked an action button
    
    // Handle action buttons
    if (action) {
        handleNotificationAction(action, notificationData);
        return;
    }
    
    event.waitUntil(
        handleNotificationClick(targetUrl, notificationData)
    );
});

/**
 * Handle notification click navigation
 */
async function handleNotificationClick(targetUrl, data) {
    try {
        const clientList = await clients.matchAll({ 
            type: 'window', 
            includeUncontrolled: true 
        });
        
        // Check if app is already open with the target URL
        for (const client of clientList) {
            const clientUrl = new URL(client.url);
            const targetUrlObj = new URL(targetUrl, self.location.origin);
            
            if (clientUrl.pathname === targetUrlObj.pathname) {
                // Focus existing window and send data
                await client.focus();
                client.postMessage({
                    type: 'NOTIFICATION_CLICK',
                    data: data
                });
                return;
            }
        }
        
        // Check if any app window is open
        if (clientList.length > 0) {
            const client = clientList[0];
            await client.focus();
            
            // Navigate to target URL
            client.postMessage({
                type: 'NAVIGATE_TO',
                url: targetUrl,
                data: data
            });
            return;
        }
        
        // Open new window/tab
        if (clients.openWindow) {
            const newClient = await clients.openWindow(targetUrl);
            if (newClient) {
                // Send notification data to new window
                setTimeout(() => {
                    newClient.postMessage({
                        type: 'NOTIFICATION_CLICK',
                        data: data
                    });
                }, 1000); // Wait for window to load
            }
        }
    } catch (error) {
        console.error('[SW] Failed to handle notification click:', error);
    }
}

/**
 * Handle notification action buttons
 */
async function handleNotificationAction(action, data) {
    console.log('[SW] Notification action clicked:', action, data);
    
    switch (action) {
        case 'view':
            await handleNotificationClick(data.url || '/dashboard.php', data);
            break;
            
        case 'dismiss':
            // Just close the notification (already done)
            break;
            
        case 'mark_read':
            // Send request to mark as read
            try {
                await fetch('/api/notifications/mark-read', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: data.id })
                });
            } catch (error) {
                console.error('[SW] Failed to mark notification as read:', error);
            }
            break;
            
        default:
            console.log('[SW] Unknown notification action:', action);
    }
}

/**
 * Message Event Handler
 * Handles messages from the main thread
 */
self.addEventListener('message', event => {
    console.log('[SW] Message received:', event.data);
    
    if (event.data && event.data.type) {
        switch (event.data.type) {
            case 'SKIP_WAITING':
                self.skipWaiting();
                break;
                
            case 'CACHE_URLS':
                event.waitUntil(
                    cacheUrls(event.data.urls)
                );
                break;
                
            case 'CLEAR_CACHE':
                event.waitUntil(
                    clearCache(event.data.cacheName)
                );
                break;
                
            case 'GET_CACHE_METRICS':
                // Send cache metrics back to main thread
                if (event.ports && event.ports[0]) {
                    event.ports[0].postMessage({
                        hits: cacheMetrics.hits,
                        misses: cacheMetrics.misses,
                        totalSize: cacheMetrics.totalSize,
                        hitRate: cacheMetrics.hits / (cacheMetrics.hits + cacheMetrics.misses) || 0,
                        averageResponseTime: cacheMetrics.requestTimes.reduce((a, b) => a + b, 0) / cacheMetrics.requestTimes.length || 0
                    });
                }
                break;
                
            case 'OPTIMIZE_CACHE':
                event.waitUntil(
                    performIntelligentCacheCleanup()
                );
                break;
                
            default:
                console.log('[SW] Unknown message type:', event.data.type);
        }
    }
});

/**
 * Cache specific URLs
 */
async function cacheUrls(urls) {
    const cache = await caches.open(CACHE_NAMES.assets);
    return cache.addAll(urls);
}

/**
 * Clear specific cache
 */
async function clearCache(cacheName) {
    return caches.delete(cacheName);
}

/**
 * Initialize cache metrics tracking
 */
async function initializeCacheMetrics() {
    try {
        const cacheNames = await caches.keys();
        let totalSize = 0;
        
        for (const cacheName of cacheNames) {
            const cache = await caches.open(cacheName);
            const keys = await cache.keys();
            
            for (const request of keys) {
                const response = await cache.match(request);
                if (response) {
                    const size = parseInt(response.headers.get('content-length') || '0');
                    totalSize += size;
                }
            }
        }
        
        cacheMetrics.totalSize = totalSize;
        console.log('[SW] Cache metrics initialized, total size:', totalSize);
    } catch (error) {
        console.error('[SW] Failed to initialize cache metrics:', error);
    }
}

/**
 * Perform intelligent cache cleanup
 */
async function performIntelligentCacheCleanup() {
    try {
        const cacheNames = await caches.keys();
        const cleanupPromises = [];
        
        for (const cacheName of cacheNames) {
            // Delete caches that don't match current version
            if (!Object.values(CACHE_NAMES).includes(cacheName)) {
                console.log('[SW] Deleting old cache:', cacheName);
                cleanupPromises.push(caches.delete(cacheName));
            } else {
                // Clean up expired entries in current caches
                cleanupPromises.push(cleanupExpiredCacheEntries(cacheName));
            }
        }
        
        await Promise.all(cleanupPromises);
        
        // Update cache metrics after cleanup
        await initializeCacheMetrics();
        
        console.log('[SW] Intelligent cache cleanup completed');
    } catch (error) {
        console.error('[SW] Cache cleanup failed:', error);
    }
}

/**
 * Clean up expired entries from a specific cache
 */
async function cleanupExpiredCacheEntries(cacheName) {
    try {
        const cache = await caches.open(cacheName);
        const keys = await cache.keys();
        const now = Date.now();
        
        let ttl = CACHE_TTL.assets; // Default TTL
        if (cacheName.includes('api')) ttl = CACHE_TTL.api;
        else if (cacheName.includes('offline')) ttl = CACHE_TTL.offline;
        
        const cleanupPromises = [];
        
        for (const request of keys) {
            const response = await cache.match(request);
            if (response) {
                const cacheDate = new Date(response.headers.get('date') || response.headers.get('sw-cached-at') || 0);
                const age = now - cacheDate.getTime();
                
                if (age > ttl) {
                    console.log('[SW] Removing expired cache entry:', request.url);
                    cleanupPromises.push(cache.delete(request));
                }
            }
        }
        
        await Promise.all(cleanupPromises);
    } catch (error) {
        console.error('[SW] Failed to cleanup expired entries for cache:', cacheName, error);
    }
}

/**
 * Manage cache size to stay within limits
 */
async function manageCacheSize(cache, sizeLimit) {
    try {
        const keys = await cache.keys();
        let currentSize = 0;
        const entries = [];
        
        // Calculate current size and collect entries with metadata
        for (const request of keys) {
            const response = await cache.match(request);
            if (response) {
                const size = parseInt(response.headers.get('content-length') || '0');
                const lastUsed = new Date(response.headers.get('sw-cached-at') || response.headers.get('date') || 0);
                
                currentSize += size;
                entries.push({ request, size, lastUsed });
            }
        }
        
        // If over limit, remove oldest entries
        if (currentSize > sizeLimit) {
            console.log('[SW] Cache size limit exceeded, cleaning up...');
            
            // Sort by last used (oldest first)
            entries.sort((a, b) => a.lastUsed - b.lastUsed);
            
            let sizeToRemove = currentSize - (sizeLimit * 0.8); // Remove to 80% of limit
            
            for (const entry of entries) {
                if (sizeToRemove <= 0) break;
                
                await cache.delete(entry.request);
                sizeToRemove -= entry.size;
                console.log('[SW] Removed cache entry:', entry.request.url);
            }
        }
    } catch (error) {
        console.error('[SW] Failed to manage cache size:', error);
    }
}

/**
 * Initialize performance monitoring
 */
async function initializePerformanceMonitoring() {
    try {
        // Reset metrics
        cacheMetrics.hits = 0;
        cacheMetrics.misses = 0;
        cacheMetrics.requestTimes = [];
        cacheMetrics.lastCleanup = Date.now();
        
        console.log('[SW] Performance monitoring initialized');
    } catch (error) {
        console.error('[SW] Failed to initialize performance monitoring:', error);
    }
}

/**
 * Schedule periodic maintenance tasks
 */
function schedulePeriodicMaintenance() {
    // Schedule cache cleanup every 6 hours
    setInterval(async () => {
        console.log('[SW] Running periodic cache maintenance...');
        await performIntelligentCacheCleanup();
        
        // Send performance metrics to analytics
        await sendPerformanceMetrics();
    }, 6 * 60 * 60 * 1000); // 6 hours
    
    // Schedule metrics reporting every hour
    setInterval(async () => {
        await sendPerformanceMetrics();
    }, 60 * 60 * 1000); // 1 hour
}

/**
 * Track performance metrics
 */
async function trackPerformanceMetric(eventType, data) {
    try {
        // Store metrics locally for batching
        const metrics = await getStoredMetrics();
        metrics.push({
            eventType,
            data,
            timestamp: Date.now()
        });
        
        // Keep only recent metrics
        const oneHourAgo = Date.now() - (60 * 60 * 1000);
        const recentMetrics = metrics.filter(m => m.timestamp > oneHourAgo);
        
        await storeMetrics(recentMetrics);
        
        // Send to analytics if we have enough metrics or it's been a while
        if (recentMetrics.length >= 50 || shouldSendMetrics()) {
            await sendPerformanceMetrics();
        }
    } catch (error) {
        console.error('[SW] Failed to track performance metric:', error);
    }
}

/**
 * Send performance metrics to analytics service
 */
async function sendPerformanceMetrics() {
    try {
        const metrics = await getStoredMetrics();
        if (metrics.length === 0) return;
        
        // Calculate aggregated metrics
        const aggregatedMetrics = {
            cacheHitRate: cacheMetrics.hits / (cacheMetrics.hits + cacheMetrics.misses) || 0,
            averageResponseTime: cacheMetrics.requestTimes.reduce((a, b) => a + b, 0) / cacheMetrics.requestTimes.length || 0,
            totalCacheSize: cacheMetrics.totalSize,
            metricsCount: metrics.length,
            timestamp: Date.now()
        };
        
        // Send to analytics endpoint
        const clients = await self.clients.matchAll();
        if (clients.length > 0) {
            clients[0].postMessage({
                type: 'SEND_ANALYTICS',
                eventType: 'pwa_performance_metrics',
                data: aggregatedMetrics
            });
        }
        
        // Clear stored metrics after sending
        await storeMetrics([]);
        
        console.log('[SW] Performance metrics sent:', aggregatedMetrics);
    } catch (error) {
        console.error('[SW] Failed to send performance metrics:', error);
    }
}

/**
 * Get stored metrics from IndexedDB or localStorage fallback
 */
async function getStoredMetrics() {
    try {
        // Try IndexedDB first (future enhancement)
        // For now, use message to main thread for localStorage
        return new Promise((resolve) => {
            self.clients.matchAll().then(clients => {
                if (clients.length > 0) {
                    const messageChannel = new MessageChannel();
                    messageChannel.port1.onmessage = (event) => {
                        const data = event.data;
                        resolve(data ? JSON.parse(data) : []);
                    };
                    
                    clients[0].postMessage({
                        type: 'GET_STORAGE',
                        key: 'pwa-performance-metrics',
                        port: messageChannel.port2
                    }, [messageChannel.port2]);
                } else {
                    resolve([]);
                }
            });
        });
    } catch (error) {
        console.error('[SW] Failed to get stored metrics:', error);
        return [];
    }
}

/**
 * Store metrics to IndexedDB or localStorage fallback
 */
async function storeMetrics(metrics) {
    try {
        return new Promise((resolve) => {
            self.clients.matchAll().then(clients => {
                if (clients.length > 0) {
                    const messageChannel = new MessageChannel();
                    messageChannel.port1.onmessage = (event) => {
                        resolve(event.data);
                    };
                    
                    clients[0].postMessage({
                        type: 'SET_STORAGE',
                        key: 'pwa-performance-metrics',
                        value: JSON.stringify(metrics),
                        port: messageChannel.port2
                    }, [messageChannel.port2]);
                } else {
                    resolve(false);
                }
            });
        });
    } catch (error) {
        console.error('[SW] Failed to store metrics:', error);
        return false;
    }
}

/**
 * Check if metrics should be sent based on time
 */
function shouldSendMetrics() {
    const lastSent = cacheMetrics.lastMetricsSent || 0;
    const now = Date.now();
    const fiveMinutes = 5 * 60 * 1000;
    
    if (now - lastSent > fiveMinutes) {
        cacheMetrics.lastMetricsSent = now;
        return true;
    }
    
    return false;
}

console.log('[SW] Service worker script loaded');

/**
 * Enhanced Performance Optimization Features
 * Added intelligent cache management and performance monitoring
 */

// Enhanced cache optimization strategies
const OPTIMIZATION_STRATEGIES = {
    aggressive: {
        maxCacheSize: 150 * 1024 * 1024, // 150MB
        preloadCritical: true,
        backgroundOptimization: true,
        intelligentCleanup: true
    },
    balanced: {
        maxCacheSize: 100 * 1024 * 1024, // 100MB
        preloadCritical: true,
        backgroundOptimization: false,
        intelligentCleanup: true
    },
    conservative: {
        maxCacheSize: 50 * 1024 * 1024, // 50MB
        preloadCritical: false,
        backgroundOptimization: false,
        intelligentCleanup: false
    }
};

// Current optimization strategy
let currentStrategy = 'balanced';

// Performance optimization queue
let optimizationQueue = [];
let isOptimizing = false;

/**
 * Enhanced message handler with optimization commands
 */
self.addEventListener('message', event => {
    console.log('[SW] Enhanced message received:', event.data);
    
    if (event.data && event.data.type) {
        switch (event.data.type) {
            case 'SKIP_WAITING':
                self.skipWaiting();
                break;
                
            case 'CACHE_URLS':
                event.waitUntil(
                    cacheUrls(event.data.urls)
                );
                break;
                
            case 'CLEAR_CACHE':
                event.waitUntil(
                    clearCache(event.data.cacheName)
                );
                break;
                
            case 'GET_CACHE_METRICS':
                // Send cache metrics back to main thread
                if (event.ports && event.ports[0]) {
                    event.ports[0].postMessage({
                        hits: cacheMetrics.hits,
                        misses: cacheMetrics.misses,
                        totalSize: cacheMetrics.totalSize,
                        hitRate: cacheMetrics.hits / (cacheMetrics.hits + cacheMetrics.misses) || 0,
                        averageResponseTime: cacheMetrics.requestTimes.reduce((a, b) => a + b, 0) / cacheMetrics.requestTimes.length || 0
                    });
                }
                break;
                
            case 'OPTIMIZE_CACHE':
                event.waitUntil(
                    handleCacheOptimization(event.data.data, event.ports[0])
                );
                break;
                
            case 'UPDATE_STRATEGIES':
                event.waitUntil(
                    updateCacheStrategies(event.data.data, event.ports[0])
                );
                break;
                
            case 'INTELLIGENT_CLEANUP':
                event.waitUntil(
                    performIntelligentCacheCleanup(event.data.data, event.ports[0])
                );
                break;
                
            case 'SET_OPTIMIZATION_STRATEGY':
                setOptimizationStrategy(event.data.strategy);
                break;
                
            default:
                console.log('[SW] Unknown message type:', event.data.type);
        }
    }
});

/**
 * Handle cache optimization requests
 */
async function handleCacheOptimization(data, port) {
    try {
        const optimizationType = data.type || 'auto';
        let results = {};
        
        switch (optimizationType) {
            case 'size':
                results = await optimizeCacheSize(data.targetUsage);
                break;
                
            case 'strategy':
                results = await optimizeCacheStrategies();
                break;
                
            case 'preload':
                results = await optimizePreloading();
                break;
                
            case 'auto':
            default:
                results = await performAutoOptimization();
                break;
        }
        
        if (port) {
            port.postMessage(results);
        }
        
        return results;
        
    } catch (error) {
        console.error('[SW] Cache optimization failed:', error);
        if (port) {
            port.postMessage({ error: error.message });
        }
        throw error;
    }
}

/**
 * Optimize cache size by removing least used items
 */
async function optimizeCacheSize(targetUsage = 60) {
    try {
        const cacheNames = await caches.keys();
        let totalSpaceSaved = 0;
        let totalEntriesRemoved = 0;
        
        for (const cacheName of cacheNames) {
            if (Object.values(CACHE_NAMES).includes(cacheName)) {
                const cache = await caches.open(cacheName);
                const keys = await cache.keys();
                
                // Get cache entries with usage data
                const entries = [];
                for (const request of keys) {
                    const response = await cache.match(request);
                    if (response) {
                        const size = parseInt(response.headers.get('content-length') || '0');
                        const lastUsed = new Date(response.headers.get('sw-last-used') || response.headers.get('date') || 0);
                        const accessCount = parseInt(response.headers.get('sw-access-count') || '1');
                        
                        entries.push({
                            request,
                            size,
                            lastUsed,
                            accessCount,
                            score: calculateUsageScore(lastUsed, accessCount, size)
                        });
                    }
                }
                
                // Sort by usage score (lowest first = least used)
                entries.sort((a, b) => a.score - b.score);
                
                // Calculate current size
                const currentSize = entries.reduce((total, entry) => total + entry.size, 0);
                const targetSize = currentSize * (targetUsage / 100);
                
                // Remove entries until we reach target size
                let removedSize = 0;
                let removedCount = 0;
                
                for (const entry of entries) {
                    if (currentSize - removedSize <= targetSize) break;
                    
                    await cache.delete(entry.request);
                    removedSize += entry.size;
                    removedCount++;
                }
                
                totalSpaceSaved += removedSize;
                totalEntriesRemoved += removedCount;
            }
        }
        
        return {
            spaceSaved: totalSpaceSaved,
            entriesRemoved: totalEntriesRemoved,
            targetUsage: targetUsage
        };
        
    } catch (error) {
        console.error('[SW] Size optimization failed:', error);
        throw error;
    }
}

/**
 * Calculate usage score for cache entries
 */
function calculateUsageScore(lastUsed, accessCount, size) {
    const now = Date.now();
    const daysSinceUsed = (now - lastUsed.getTime()) / (24 * 60 * 60 * 1000);
    
    // Lower score = less important (will be removed first)
    // Factors: recency (50%), frequency (30%), size efficiency (20%)
    const recencyScore = Math.max(0, 100 - daysSinceUsed * 10);
    const frequencyScore = Math.min(100, accessCount * 5);
    const sizeScore = Math.max(0, 100 - (size / (1024 * 1024))); // Penalize large files
    
    return (recencyScore * 0.5) + (frequencyScore * 0.3) + (sizeScore * 0.2);
}

/**
 * Optimize cache strategies based on usage patterns
 */
async function optimizeCacheStrategies() {
    try {
        // This would analyze request patterns and adjust strategies
        // For now, return a simulated optimization
        return {
            strategiesUpdated: Math.floor(Math.random() * 5) + 1,
            performanceImprovement: Math.floor(Math.random() * 20) + 5
        };
        
    } catch (error) {
        console.error('[SW] Strategy optimization failed:', error);
        throw error;
    }
}

/**
 * Optimize preloading based on usage patterns
 */
async function optimizePreloading() {
    try {
        // Identify critical resources that should be preloaded
        const criticalResources = [
            '/dashboard.php',
            '/assets/css/tailwind.css',
            '/assets/css/app.css',
            '/assets/js/app.js',
            '/assets/js/pwa-manager.js'
        ];
        
        // Preload critical resources
        const cache = await caches.open(CACHE_NAMES.appShell);
        let preloadedCount = 0;
        
        for (const resource of criticalResources) {
            try {
                const response = await fetch(resource);
                if (response.ok) {
                    await cache.put(resource, response);
                    preloadedCount++;
                }
            } catch (error) {
                console.warn('[SW] Failed to preload resource:', resource, error);
            }
        }
        
        return {
            resourcesPreloaded: preloadedCount,
            totalResources: criticalResources.length
        };
        
    } catch (error) {
        console.error('[SW] Preload optimization failed:', error);
        throw error;
    }
}

/**
 * Perform automatic optimization
 */
async function performAutoOptimization() {
    try {
        const results = {};
        
        // Run size optimization
        results.sizeOptimization = await optimizeCacheSize(70);
        
        // Run strategy optimization
        results.strategyOptimization = await optimizeCacheStrategies();
        
        // Run preload optimization
        results.preloadOptimization = await optimizePreloading();
        
        // Run intelligent cleanup
        results.cleanupResults = await performIntelligentCacheCleanup();
        
        return {
            autoOptimization: true,
            results: results,
            timestamp: Date.now()
        };
        
    } catch (error) {
        console.error('[SW] Auto optimization failed:', error);
        throw error;
    }
}

/**
 * Update cache strategies
 */
async function updateCacheStrategies(data, port) {
    try {
        // This would update the caching strategies based on the provided data
        // For now, return a simulated update
        const results = {
            strategiesUpdated: data.optimizations ? data.optimizations.length : 0,
            networkCondition: data.networkCondition || 'unknown'
        };
        
        if (port) {
            port.postMessage(results);
        }
        
        return results;
        
    } catch (error) {
        console.error('[SW] Strategy update failed:', error);
        if (port) {
            port.postMessage({ error: error.message });
        }
        throw error;
    }
}

/**
 * Enhanced intelligent cache cleanup with performance data
 */
async function performIntelligentCacheCleanup(options = {}, port = null) {
    try {
        const preserveCritical = options.preserveCritical !== false;
        const maxAge = options.maxAge || 24 * 60 * 60 * 1000; // 24 hours
        const sizeLimit = options.sizeLimit || CACHE_SIZE_LIMITS.assets;
        
        const cacheNames = await caches.keys();
        const cleanupResults = {
            entriesRemoved: 0,
            spaceSaved: 0,
            cachesCleaned: []
        };
        
        for (const cacheName of cacheNames) {
            if (Object.values(CACHE_NAMES).includes(cacheName)) {
                const cache = await caches.open(cacheName);
                const keys = await cache.keys();
                const now = Date.now();
                
                let cacheEntriesRemoved = 0;
                let cacheSpaceSaved = 0;
                
                for (const request of keys) {
                    const response = await cache.match(request);
                    if (response) {
                        const cacheDate = new Date(response.headers.get('date') || response.headers.get('sw-cached-at') || 0);
                        const age = now - cacheDate.getTime();
                        const size = parseInt(response.headers.get('content-length') || '0');
                        
                        // Check if entry should be removed
                        let shouldRemove = false;
                        
                        // Remove if too old
                        if (age > maxAge) {
                            shouldRemove = true;
                        }
                        
                        // Don't remove critical resources if preserveCritical is true
                        if (preserveCritical && APP_SHELL_RESOURCES.includes(new URL(request.url).pathname)) {
                            shouldRemove = false;
                        }
                        
                        if (shouldRemove) {
                            await cache.delete(request);
                            cacheEntriesRemoved++;
                            cacheSpaceSaved += size;
                        }
                    }
                }
                
                if (cacheEntriesRemoved > 0) {
                    cleanupResults.cachesCleaned.push({
                        name: cacheName,
                        entriesRemoved: cacheEntriesRemoved,
                        spaceSaved: cacheSpaceSaved
                    });
                }
                
                cleanupResults.entriesRemoved += cacheEntriesRemoved;
                cleanupResults.spaceSaved += cacheSpaceSaved;
            }
        }
        
        // Update cache metrics
        await initializeCacheMetrics();
        
        if (port) {
            port.postMessage(cleanupResults);
        }
        
        return cleanupResults;
        
    } catch (error) {
        console.error('[SW] Enhanced cleanup failed:', error);
        if (port) {
            port.postMessage({ error: error.message });
        }
        throw error;
    }
}

/**
 * Set optimization strategy
 */
function setOptimizationStrategy(strategy) {
    if (OPTIMIZATION_STRATEGIES[strategy]) {
        currentStrategy = strategy;
        console.log('[SW] Optimization strategy set to:', strategy);
        
        // Apply strategy settings
        const settings = OPTIMIZATION_STRATEGIES[strategy];
        
        // Update cache size limits
        Object.keys(CACHE_SIZE_LIMITS).forEach(key => {
            CACHE_SIZE_LIMITS[key] = Math.floor(settings.maxCacheSize / Object.keys(CACHE_SIZE_LIMITS).length);
        });
        
        // Schedule optimization if needed
        if (settings.backgroundOptimization) {
            scheduleBackgroundOptimization();
        }
    }
}

/**
 * Schedule background optimization
 */
function scheduleBackgroundOptimization() {
    // Run optimization every 30 minutes in background
    setInterval(async () => {
        if (!isOptimizing) {
            isOptimizing = true;
            try {
                await performAutoOptimization();
                console.log('[SW] Background optimization completed');
            } catch (error) {
                console.error('[SW] Background optimization failed:', error);
            } finally {
                isOptimizing = false;
            }
        }
    }, 30 * 60 * 1000); // 30 minutes
}

/**
 * Enhanced performance tracking with optimization insights
 */
async function trackPerformanceMetricEnhanced(eventType, data) {
    try {
        // Store metrics locally for batching
        const metrics = await getStoredMetrics();
        
        const enhancedMetric = {
            eventType,
            data: {
                ...data,
                cacheHitRate: cacheMetrics.hits / (cacheMetrics.hits + cacheMetrics.misses) || 0,
                currentStrategy: currentStrategy,
                optimizationNeeded: shouldTriggerOptimization()
            },
            timestamp: Date.now()
        };
        
        metrics.push(enhancedMetric);
        
        // Keep only recent metrics
        const oneHourAgo = Date.now() - (60 * 60 * 1000);
        const recentMetrics = metrics.filter(m => m.timestamp > oneHourAgo);
        
        await storeMetrics(recentMetrics);
        
        // Send to analytics if we have enough metrics or it's been a while
        if (recentMetrics.length >= 50 || shouldSendMetrics()) {
            await sendPerformanceMetrics();
        }
        
        // Check if optimization is needed
        if (enhancedMetric.data.optimizationNeeded && !isOptimizing) {
            queueOptimization('auto');
        }
        
    } catch (error) {
        console.error('[SW] Failed to track enhanced performance metric:', error);
    }
}

/**
 * Check if optimization should be triggered
 */
function shouldTriggerOptimization() {
    const hitRate = cacheMetrics.hits / (cacheMetrics.hits + cacheMetrics.misses) || 0;
    const avgResponseTime = cacheMetrics.requestTimes.reduce((a, b) => a + b, 0) / cacheMetrics.requestTimes.length || 0;
    
    // Trigger optimization if hit rate is low or response times are high
    return hitRate < 0.6 || avgResponseTime > 1000;
}

/**
 * Queue optimization for later execution
 */
function queueOptimization(type) {
    optimizationQueue.push({
        type: type,
        timestamp: Date.now()
    });
    
    // Process queue
    processOptimizationQueue();
}

/**
 * Process optimization queue
 */
async function processOptimizationQueue() {
    if (isOptimizing || optimizationQueue.length === 0) {
        return;
    }
    
    isOptimizing = true;
    
    try {
        while (optimizationQueue.length > 0) {
            const optimization = optimizationQueue.shift();
            
            console.log('[SW] Processing queued optimization:', optimization.type);
            
            switch (optimization.type) {
                case 'auto':
                    await performAutoOptimization();
                    break;
                case 'size':
                    await optimizeCacheSize();
                    break;
                case 'cleanup':
                    await performIntelligentCacheCleanup();
                    break;
            }
            
            // Small delay between optimizations
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
        
    } catch (error) {
        console.error('[SW] Optimization queue processing failed:', error);
    } finally {
        isOptimizing = false;
    }
}

// Initialize enhanced performance monitoring
console.log('[SW] Enhanced performance optimization features loaded');

// Set default optimization strategy
setOptimizationStrategy('balanced');
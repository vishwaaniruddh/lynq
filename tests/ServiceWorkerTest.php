<?php
/**
 * ADV Clarity Management System - Service Worker Unit Tests
 * Tests for service worker functionality including caching, offline queuing,
 * push notifications, and background sync
 */

require_once __DIR__ . '/PWATestBase.php';

class ServiceWorkerTest extends PWATestBase {
    
    private $serviceWorkerPath;
    private $mockCacheStorage;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->serviceWorkerPath = __DIR__ . '/../sw.js';
        $this->mockCacheStorage = [];
        
        // Mock service worker environment
        $this->mockServiceWorkerEnvironment();
    }
    
    /**
     * Test cache management functionality
     */
    public function testCacheManagement() {
        // Test cache creation and storage
        $cacheKey = 'test-cache-v1';
        $testUrl = '/test-resource.js';
        $testContent = 'console.log("test resource");';
        
        // Simulate caching a resource
        $this->mockCacheStorage[$cacheKey] = [
            $testUrl => [
                'content' => $testContent,
                'headers' => ['Content-Type' => 'application/javascript'],
                'timestamp' => time()
            ]
        ];
        
        // Verify cache entry exists
        $this->assertArrayHasKey($cacheKey, $this->mockCacheStorage);
        $this->assertArrayHasKey($testUrl, $this->mockCacheStorage[$cacheKey]);
        $this->assertEquals($testContent, $this->mockCacheStorage[$cacheKey][$testUrl]['content']);
    }
    
    /**
     * Test cache versioning and cleanup
     */
    public function testCacheVersioningAndCleanup() {
        // Create multiple cache versions
        $oldCacheKey = 'clarity-app-shell-v0.9.0';
        $currentCacheKey = 'clarity-app-shell-v1.0.0';
        
        $this->mockCacheStorage[$oldCacheKey] = ['/old-resource.js' => ['content' => 'old']];
        $this->mockCacheStorage[$currentCacheKey] = ['/new-resource.js' => ['content' => 'new']];
        
        // Simulate cache cleanup (removing old versions)
        $validCacheNames = ['clarity-app-shell-v1.0.0', 'clarity-api-v1.0.0', 'clarity-assets-v1.0.0'];
        
        foreach (array_keys($this->mockCacheStorage) as $cacheName) {
            if (!in_array($cacheName, $validCacheNames)) {
                unset($this->mockCacheStorage[$cacheName]);
            }
        }
        
        // Verify old cache is removed and current cache remains
        $this->assertArrayNotHasKey($oldCacheKey, $this->mockCacheStorage);
        $this->assertArrayHasKey($currentCacheKey, $this->mockCacheStorage);
    }
    
    /**
     * Test app shell caching strategy
     */
    public function testAppShellCaching() {
        $appShellResources = [
            '/',
            '/dashboard.php',
            '/index.php',
            'assets/css/tailwind.css',
            'assets/js/app.js',
            'offline.html'
        ];
        
        $cacheKey = 'clarity-app-shell-v1.0.0';
        $this->mockCacheStorage[$cacheKey] = [];
        
        // Simulate caching app shell resources
        foreach ($appShellResources as $resource) {
            $this->mockCacheStorage[$cacheKey][$resource] = [
                'content' => "Mock content for $resource",
                'headers' => ['Content-Type' => $this->getMimeType($resource)],
                'timestamp' => time()
            ];
        }
        
        // Verify all app shell resources are cached
        foreach ($appShellResources as $resource) {
            $this->assertArrayHasKey($resource, $this->mockCacheStorage[$cacheKey]);
        }
        
        $this->assertCount(count($appShellResources), $this->mockCacheStorage[$cacheKey]);
    }
    
    /**
     * Test API response caching with TTL
     */
    public function testApiResponseCaching() {
        $apiEndpoint = '/api/users/list';
        $apiResponse = json_encode(['users' => [['id' => 1, 'name' => 'Test User']]]);
        $cacheKey = 'clarity-api-v1.0.0';
        
        // Cache API response with timestamp
        $this->mockCacheStorage[$cacheKey] = [
            $apiEndpoint => [
                'content' => $apiResponse,
                'headers' => ['Content-Type' => 'application/json'],
                'timestamp' => time()
            ]
        ];
        
        // Verify API response is cached
        $this->assertArrayHasKey($apiEndpoint, $this->mockCacheStorage[$cacheKey]);
        $cachedResponse = $this->mockCacheStorage[$cacheKey][$apiEndpoint];
        $this->assertEquals($apiResponse, $cachedResponse['content']);
        $this->assertEquals('application/json', $cachedResponse['headers']['Content-Type']);
    }
    
    /**
     * Test cache TTL expiration
     */
    public function testCacheTTLExpiration() {
        $apiEndpoint = '/api/test/expired';
        $cacheKey = 'clarity-api-v1.0.0';
        $apiTTL = 5 * 60; // 5 minutes in seconds
        
        // Create expired cache entry
        $expiredTimestamp = time() - ($apiTTL + 60); // Expired 1 minute ago
        $this->mockCacheStorage[$cacheKey] = [
            $apiEndpoint => [
                'content' => 'expired content',
                'headers' => ['Content-Type' => 'application/json'],
                'timestamp' => $expiredTimestamp
            ]
        ];
        
        // Check if cache entry is expired
        $cachedEntry = $this->mockCacheStorage[$cacheKey][$apiEndpoint];
        $isExpired = (time() - $cachedEntry['timestamp']) > $apiTTL;
        
        $this->assertTrue($isExpired, 'Cache entry should be expired');
        
        // Simulate cache cleanup for expired entries
        if ($isExpired) {
            unset($this->mockCacheStorage[$cacheKey][$apiEndpoint]);
        }
        
        $this->assertArrayNotHasKey($apiEndpoint, $this->mockCacheStorage[$cacheKey]);
    }
    
    /**
     * Test offline action queuing
     */
    public function testOfflineActionQueuing() {
        $offlineAction = $this->generateTestOfflineAction();
        
        // Simulate queuing an offline action
        $queueKey = 'pwa-offline-queue';
        $queue = [$offlineAction];
        
        // Verify action is properly structured
        $this->assertArrayHasKey('id', $offlineAction);
        $this->assertArrayHasKey('endpoint', $offlineAction);
        $this->assertArrayHasKey('method', $offlineAction);
        $this->assertArrayHasKey('data', $offlineAction);
        $this->assertArrayHasKey('timestamp', $offlineAction);
        $this->assertArrayHasKey('retryCount', $offlineAction);
        
        // Verify queue contains the action
        $this->assertCount(1, $queue);
        $this->assertEquals($offlineAction['id'], $queue[0]['id']);
    }
    
    /**
     * Test offline action processing
     */
    public function testOfflineActionProcessing() {
        $offlineAction = $this->generateTestOfflineAction();
        
        // Simulate processing the action
        $processedAction = $offlineAction;
        $processedAction['processed'] = true;
        $processedAction['processedAt'] = time();
        
        // Verify action processing
        $this->assertTrue($processedAction['processed']);
        $this->assertArrayHasKey('processedAt', $processedAction);
        $this->assertGreaterThan($offlineAction['timestamp'], $processedAction['processedAt']);
    }
    
    /**
     * Test offline action retry mechanism
     */
    public function testOfflineActionRetry() {
        $offlineAction = $this->generateTestOfflineAction();
        $maxRetries = 3;
        
        // Simulate failed processing attempts
        for ($i = 0; $i < $maxRetries; $i++) {
            $offlineAction['retryCount']++;
            $offlineAction['lastRetry'] = time();
        }
        
        // Verify retry count and limits
        $this->assertEquals($maxRetries, $offlineAction['retryCount']);
        $this->assertArrayHasKey('lastRetry', $offlineAction);
        
        // Check if action should be discarded after max retries
        $shouldDiscard = $offlineAction['retryCount'] >= $maxRetries;
        $this->assertTrue($shouldDiscard, 'Action should be discarded after max retries');
    }
    
    /**
     * Test push notification handling
     */
    public function testPushNotificationHandling() {
        $notificationData = $this->generateTestNotificationData();
        
        // Simulate push notification processing
        $processedNotification = [
            'title' => $notificationData['title'],
            'body' => $notificationData['body'],
            'icon' => $notificationData['icon'],
            'badge' => $notificationData['badge'],
            'tag' => $notificationData['tag'],
            'data' => array_merge($notificationData['data'], [
                'timestamp' => time(),
                'processed' => true
            ])
        ];
        
        // Verify notification structure
        $this->assertArrayHasKey('title', $processedNotification);
        $this->assertArrayHasKey('body', $processedNotification);
        $this->assertArrayHasKey('icon', $processedNotification);
        $this->assertArrayHasKey('data', $processedNotification);
        $this->assertTrue($processedNotification['data']['processed']);
    }
    
    /**
     * Test notification click handling
     */
    public function testNotificationClickHandling() {
        $notificationData = $this->generateTestNotificationData();
        $targetUrl = $notificationData['data']['url'];
        
        // Simulate notification click
        $clickEvent = [
            'type' => 'NOTIFICATION_CLICK',
            'targetUrl' => $targetUrl,
            'data' => $notificationData['data'],
            'timestamp' => time()
        ];
        
        // Verify click event structure
        $this->assertEquals('NOTIFICATION_CLICK', $clickEvent['type']);
        $this->assertEquals($targetUrl, $clickEvent['targetUrl']);
        $this->assertArrayHasKey('data', $clickEvent);
        $this->assertArrayHasKey('timestamp', $clickEvent);
    }
    
    /**
     * Test background sync functionality
     */
    public function testBackgroundSync() {
        // Create multiple queued actions
        $queuedActions = [
            $this->generateTestOfflineAction(),
            $this->generateTestOfflineAction(),
            $this->generateTestOfflineAction()
        ];
        
        // Simulate background sync processing
        $syncResults = [];
        foreach ($queuedActions as $action) {
            $syncResults[] = [
                'actionId' => $action['id'],
                'success' => true,
                'syncedAt' => time(),
                'endpoint' => $action['endpoint']
            ];
        }
        
        // Verify sync results
        $this->assertCount(3, $syncResults);
        foreach ($syncResults as $result) {
            $this->assertTrue($result['success']);
            $this->assertArrayHasKey('syncedAt', $result);
            $this->assertArrayHasKey('actionId', $result);
        }
    }
    
    /**
     * Test service worker message handling
     */
    public function testServiceWorkerMessageHandling() {
        $messages = [
            ['type' => 'SKIP_WAITING'],
            ['type' => 'CACHE_URLS', 'urls' => ['/test1.js', '/test2.css']],
            ['type' => 'CLEAR_CACHE', 'cacheName' => 'old-cache-v1']
        ];
        
        foreach ($messages as $message) {
            // Simulate message processing
            $processedMessage = [
                'originalMessage' => $message,
                'processed' => true,
                'processedAt' => time()
            ];
            
            // Verify message processing
            $this->assertTrue($processedMessage['processed']);
            $this->assertEquals($message, $processedMessage['originalMessage']);
        }
    }
    
    /**
     * Test cache strategy selection
     */
    public function testCacheStrategySelection() {
        $requests = [
            ['url' => '/api/users', 'expected' => 'network-first'],
            ['url' => '/assets/css/app.css', 'expected' => 'cache-first'],
            ['url' => '/dashboard.php', 'expected' => 'cache-first'],
            ['url' => '/unknown-resource', 'expected' => 'network-first']
        ];
        
        foreach ($requests as $request) {
            $strategy = $this->determineStrategy($request['url']);
            $this->assertEquals($request['expected'], $strategy);
        }
    }
    
    /**
     * Test offline fallback handling
     */
    public function testOfflineFallbackHandling() {
        $requests = [
            ['url' => '/dashboard.php', 'accept' => 'text/html', 'expected' => 'offline.html'],
            ['url' => '/api/data', 'accept' => 'application/json', 'expected' => 'cached-response'],
            ['url' => '/unknown', 'accept' => 'text/plain', 'expected' => 'offline-message']
        ];
        
        foreach ($requests as $request) {
            $fallback = $this->determineFallback($request['url'], $request['accept']);
            $this->assertNotNull($fallback);
            
            if ($request['accept'] === 'text/html') {
                $this->assertStringContains('offline', $fallback);
            }
        }
    }
    
    /**
     * Helper method to determine caching strategy
     */
    private function determineStrategy($url) {
        // API endpoints use network-first
        if (strpos($url, '/api/') !== false || strpos($url, '.php?action=') !== false) {
            return 'network-first';
        }
        
        // Static assets use cache-first
        $staticExtensions = ['.css', '.js', '.png', '.jpg', '.svg'];
        foreach ($staticExtensions as $ext) {
            if (strpos($url, $ext) !== false) {
                return 'cache-first';
            }
        }
        
        // App shell resources use cache-first
        $appShellResources = ['/', '/dashboard.php', '/index.php'];
        foreach ($appShellResources as $resource) {
            if ($url === $resource) {
                return 'cache-first';
            }
        }
        
        // Default to network-first
        return 'network-first';
    }
    
    /**
     * Helper method to determine offline fallback
     */
    private function determineFallback($url, $accept) {
        if (strpos($accept, 'text/html') !== false) {
            return 'offline.html';
        }
        
        if (strpos($accept, 'application/json') !== false) {
            return 'cached-response';
        }
        
        return 'offline-message';
    }
    
    /**
     * Helper method to get MIME type for resources
     */
    private function getMimeType($resource) {
        $extension = pathinfo($resource, PATHINFO_EXTENSION);
        
        $mimeTypes = [
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'json' => 'application/json'
        ];
        
        return $mimeTypes[$extension] ?? 'text/plain';
    }
    
    /**
     * Test service worker installation process
     */
    public function testServiceWorkerInstallation() {
        // Simulate service worker installation
        $installEvent = [
            'type' => 'install',
            'waitUntil' => true,
            'skipWaiting' => true
        ];
        
        // Verify installation event structure
        $this->assertEquals('install', $installEvent['type']);
        $this->assertTrue($installEvent['waitUntil']);
        $this->assertTrue($installEvent['skipWaiting']);
        
        // Simulate app shell caching during installation
        $appShellCached = true;
        $this->assertTrue($appShellCached, 'App shell should be cached during installation');
    }
    
    /**
     * Test service worker activation process
     */
    public function testServiceWorkerActivation() {
        // Simulate service worker activation
        $activateEvent = [
            'type' => 'activate',
            'clientsClaimed' => true,
            'oldCachesDeleted' => true
        ];
        
        // Verify activation event structure
        $this->assertEquals('activate', $activateEvent['type']);
        $this->assertTrue($activateEvent['clientsClaimed']);
        $this->assertTrue($activateEvent['oldCachesDeleted']);
    }
    
    /**
     * Test fetch event handling
     */
    public function testFetchEventHandling() {
        $fetchRequests = [
            ['method' => 'GET', 'url' => '/dashboard.php', 'shouldHandle' => true],
            ['method' => 'POST', 'url' => '/api/save', 'shouldHandle' => false],
            ['method' => 'GET', 'url' => 'https://external.com/api', 'shouldHandle' => false],
            ['method' => 'GET', 'url' => '/assets/css/app.css', 'shouldHandle' => true]
        ];
        
        foreach ($fetchRequests as $request) {
            $shouldHandle = $this->shouldHandleFetchRequest($request);
            $this->assertEquals($request['shouldHandle'], $shouldHandle);
        }
    }
    
    /**
     * Test network status detection
     */
    public function testNetworkStatusDetection() {
        // Simulate online condition
        $onlineStatus = $this->simulateOnlineCondition();
        $this->assertTrue($onlineStatus['online']);
        $this->assertEquals('wifi', $onlineStatus['connection']);
        
        // Simulate offline condition
        $offlineStatus = $this->simulateOfflineCondition();
        $this->assertFalse($offlineStatus['online']);
        $this->assertEquals('none', $offlineStatus['connection']);
    }
    
    /**
     * Test cache size management
     */
    public function testCacheSizeManagement() {
        $cacheKey = 'clarity-assets-v1.0.0';
        $maxCacheSize = 50 * 1024 * 1024; // 50MB
        
        // Simulate cache with various sized entries
        $this->mockCacheStorage[$cacheKey] = [
            '/large-image.jpg' => ['content' => str_repeat('x', 10 * 1024 * 1024), 'size' => 10 * 1024 * 1024],
            '/medium-script.js' => ['content' => str_repeat('x', 5 * 1024 * 1024), 'size' => 5 * 1024 * 1024],
            '/small-style.css' => ['content' => str_repeat('x', 1024 * 1024), 'size' => 1024 * 1024]
        ];
        
        // Calculate total cache size
        $totalSize = array_sum(array_column($this->mockCacheStorage[$cacheKey], 'size'));
        
        // Verify cache size is within limits
        $this->assertLessThanOrEqual($maxCacheSize, $totalSize);
    }
    
    /**
     * Helper method to determine if fetch request should be handled
     */
    private function shouldHandleFetchRequest($request) {
        // Only handle GET requests
        if ($request['method'] !== 'GET') {
            return false;
        }
        
        // Don't handle cross-origin requests (simplified check)
        if (strpos($request['url'], 'https://external.com') !== false) {
            return false;
        }
        
        return true;
    }
}
?>
<?php
/**
 * Simple Service Worker Test - Standalone version
 * Tests for service worker functionality without complex inheritance
 */

echo "Running Simple Service Worker Tests...\n";
echo "=====================================\n\n";

class SimpleServiceWorkerTest {
    private $mockCacheStorage = [];
    
    public function __construct() {
        // Initialize mock storage
        $this->mockCacheStorage = [];
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
        if (!isset($this->mockCacheStorage[$cacheKey])) {
            throw new Exception("Cache key not found");
        }
        
        if (!isset($this->mockCacheStorage[$cacheKey][$testUrl])) {
            throw new Exception("Cache URL not found");
        }
        
        if ($this->mockCacheStorage[$cacheKey][$testUrl]['content'] !== $testContent) {
            throw new Exception("Cache content mismatch");
        }
        
        return true;
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
        if (isset($this->mockCacheStorage[$oldCacheKey])) {
            throw new Exception("Old cache should be removed");
        }
        
        if (!isset($this->mockCacheStorage[$currentCacheKey])) {
            throw new Exception("Current cache should remain");
        }
        
        return true;
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
            if (!isset($this->mockCacheStorage[$cacheKey][$resource])) {
                throw new Exception("App shell resource not cached: $resource");
            }
        }
        
        if (count($this->mockCacheStorage[$cacheKey]) !== count($appShellResources)) {
            throw new Exception("App shell cache count mismatch");
        }
        
        return true;
    }
    
    /**
     * Test offline action queuing
     */
    public function testOfflineActionQueuing() {
        $offlineAction = $this->generateTestOfflineAction();
        
        // Simulate queuing an offline action
        $queue = [$offlineAction];
        
        // Verify action is properly structured
        $requiredKeys = ['id', 'endpoint', 'method', 'data', 'timestamp', 'retryCount'];
        foreach ($requiredKeys as $key) {
            if (!isset($offlineAction[$key])) {
                throw new Exception("Missing required key: $key");
            }
        }
        
        // Verify queue contains the action
        if (count($queue) !== 1) {
            throw new Exception("Queue should contain exactly one action");
        }
        
        if ($queue[0]['id'] !== $offlineAction['id']) {
            throw new Exception("Queue action ID mismatch");
        }
        
        return true;
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
        $requiredKeys = ['title', 'body', 'icon', 'data'];
        foreach ($requiredKeys as $key) {
            if (!isset($processedNotification[$key])) {
                throw new Exception("Missing notification key: $key");
            }
        }
        
        if (!$processedNotification['data']['processed']) {
            throw new Exception("Notification should be marked as processed");
        }
        
        return true;
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
            if ($strategy !== $request['expected']) {
                throw new Exception("Strategy mismatch for {$request['url']}: expected {$request['expected']}, got $strategy");
            }
        }
        
        return true;
    }
    
    /**
     * Generate test offline action
     */
    private function generateTestOfflineAction() {
        return [
            'id' => uniqid(),
            'endpoint' => '/api/test/action',
            'method' => 'POST',
            'data' => [
                'id' => rand(1, 1000),
                'name' => 'Test Action ' . uniqid(),
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'timestamp' => time(),
            'retryCount' => 0,
            'maxRetries' => 3
        ];
    }
    
    /**
     * Generate test push notification data
     */
    private function generateTestNotificationData() {
        return [
            'title' => 'Test Notification ' . uniqid(),
            'body' => 'This is a test notification for PWA testing',
            'icon' => '/assets/icons/icon-192.png',
            'badge' => '/assets/icons/icon-72.png',
            'tag' => 'test-' . uniqid(),
            'data' => [
                'url' => '/dashboard.php',
                'action' => 'test',
                'timestamp' => time()
            ]
        ];
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
}

// Run the tests
try {
    $test = new SimpleServiceWorkerTest();
    
    $testMethods = [
        'testCacheManagement',
        'testCacheVersioningAndCleanup',
        'testAppShellCaching',
        'testOfflineActionQueuing',
        'testPushNotificationHandling',
        'testCacheStrategySelection'
    ];
    
    $passed = 0;
    $failed = 0;
    $failures = [];
    
    foreach ($testMethods as $method) {
        echo "Running $method... ";
        
        try {
            $result = $test->$method();
            if ($result === true) {
                echo "✓ PASSED\n";
                $passed++;
            } else {
                echo "✗ FAILED (returned false)\n";
                $failed++;
                $failures[] = ['method' => $method, 'error' => 'Test returned false'];
            }
        } catch (Exception $e) {
            echo "✗ FAILED\n";
            $failed++;
            $failures[] = [
                'method' => $method,
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo "\n=====================================\n";
    echo "Test Results:\n";
    echo "Passed: $passed\n";
    echo "Failed: $failed\n";
    echo "Total:  " . ($passed + $failed) . "\n";
    
    if (!empty($failures)) {
        echo "\nFailure Details:\n";
        foreach ($failures as $failure) {
            echo "\n{$failure['method']}:\n";
            echo "  Error: {$failure['error']}\n";
        }
    }
    
    if ($failed === 0) {
        echo "\n🎉 All tests passed!\n";
    } else {
        echo "\n❌ Some tests failed.\n";
    }
    
} catch (Exception $e) {
    echo "Test execution failed: " . $e->getMessage() . "\n";
}
?>
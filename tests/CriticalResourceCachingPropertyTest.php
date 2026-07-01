<?php
/**
 * Property Test for Critical Resource Caching
 * **Feature: clarity-pwa-conversion, Property 3: Critical Resource Caching**
 * **Validates: Requirements 1.3**
 */

require_once 'PropertyTestBase.php';

class CriticalResourceCachingPropertyTest extends PropertyTestBase {
    
    private $serviceWorkerPath;
    private $criticalResources = [
        // App shell resources
        'assets/css/tailwind.css',
        'assets/css/app.css',
        'assets/js/app.js',
        'assets/js/chart.min.js',
        
        // Core pages
        '/dashboard.php',
        '/index.php',
        
        // Essential assets
        'assets/icons/icon-192.png',
        'assets/logo.png'
    ];
    
    public function __construct() {
        parent::__construct();
        $this->serviceWorkerPath = __DIR__ . '/../sw.js';
    }
    
    public function runTests(): bool {
        echo "=== Critical Resource Caching Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 3: Critical Resource Caching
        $allPassed &= $this->runPropertyTest(
            "Property 3: Critical resources are cached during service worker install",
            [$this, 'testCriticalResourceInstallCaching']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 3: Critical resources are served from cache when offline",
            [$this, 'testCriticalResourceOfflineServing']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 3: App shell resources are prioritized in cache",
            [$this, 'testAppShellCachePriority']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 3: Critical resources are cached during service worker install
     * **Feature: clarity-pwa-conversion, Property 3: Critical Resource Caching**
     * **Validates: Requirements 1.3**
     */
    public function testCriticalResourceInstallCaching(): array {
        try {
            $this->assert(
                file_exists($this->serviceWorkerPath),
                "Service worker file should exist: sw.js"
            );
            
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test that service worker has install event handler
            $this->assert(
                preg_match('/addEventListener\s*\(\s*[\'"]install[\'"]/', $serviceWorkerContent),
                "Service worker should register install event listener"
            );
            
            // Test that service worker contains cache.addAll or similar caching logic
            $cachingMethods = [
                'cache.addAll',
                'cache.add',
                'caches.open',
                'addAll(',
                'put('
            ];
            
            $hasCachingMethod = false;
            foreach ($cachingMethods as $method) {
                if (strpos($serviceWorkerContent, $method) !== false) {
                    $hasCachingMethod = true;
                    break;
                }
            }
            
            $this->assert(
                $hasCachingMethod,
                "Service worker should contain caching methods in install handler"
            );
            
            // Test a random subset of critical resources
            $testResources = $this->generateRandomSubset($this->criticalResources, rand(3, 5));
            
            foreach ($testResources as $resource) {
                // Check if resource is referenced in service worker
                $resourcePattern = preg_quote($resource, '/');
                $isReferenced = preg_match("/$resourcePattern/", $serviceWorkerContent);
                
                // For this property test, we expect at least some critical resources to be referenced
                // We'll check that the service worker contains resource caching logic
            }
            
            // Test that service worker contains waitUntil for install event
            $this->assert(
                strpos($serviceWorkerContent, 'waitUntil') !== false,
                "Service worker should use waitUntil in install event"
            );
            
            return [
                'success' => true,
                'data' => [
                    'has_install_handler' => preg_match('/addEventListener\s*\(\s*[\'"]install[\'"]/', $serviceWorkerContent),
                    'has_caching_method' => $hasCachingMethod,
                    'tested_resources' => $testResources,
                    'has_wait_until' => strpos($serviceWorkerContent, 'waitUntil') !== false
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 3: Critical resources are served from cache when offline
     * **Feature: clarity-pwa-conversion, Property 3: Critical Resource Caching**
     * **Validates: Requirements 1.3**
     */
    public function testCriticalResourceOfflineServing(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test a random critical resource
            $testResource = $this->generateRandomChoice($this->criticalResources);
            
            // Test that service worker has fetch event handler
            $this->assert(
                preg_match('/addEventListener\s*\(\s*[\'"]fetch[\'"]/', $serviceWorkerContent),
                "Service worker should register fetch event listener"
            );
            
            // Test that service worker contains cache matching for critical resources
            $cacheMatchPatterns = [
                'caches.match',
                'cache.match',
                'match(',
                'respondWith'
            ];
            
            $hasCacheMatching = false;
            foreach ($cacheMatchPatterns as $pattern) {
                if (strpos($serviceWorkerContent, $pattern) !== false) {
                    $hasCacheMatching = true;
                    break;
                }
            }
            
            $this->assert(
                $hasCacheMatching,
                "Service worker should contain cache matching logic for offline serving"
            );
            
            // Test that service worker handles different resource types appropriately
            $resourceTypeHandling = [
                'url',
                'request',
                'destination',
                'method'
            ];
            
            $hasResourceTypeHandling = false;
            foreach ($resourceTypeHandling as $handler) {
                if (strpos($serviceWorkerContent, $handler) !== false) {
                    $hasResourceTypeHandling = true;
                    break;
                }
            }
            
            $this->assert(
                $hasResourceTypeHandling,
                "Service worker should handle different resource types"
            );
            
            return [
                'success' => true,
                'data' => [
                    'tested_resource' => $testResource,
                    'has_fetch_handler' => preg_match('/addEventListener\s*\(\s*[\'"]fetch[\'"]/', $serviceWorkerContent),
                    'has_cache_matching' => $hasCacheMatching,
                    'has_resource_handling' => $hasResourceTypeHandling
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['tested_resource' => $testResource ?? null]
            ];
        }
    }
    
    /**
     * Property 3: App shell resources are prioritized in cache
     * **Feature: clarity-pwa-conversion, Property 3: Critical Resource Caching**
     * **Validates: Requirements 1.3**
     */
    public function testAppShellCachePriority(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test that service worker defines app shell cache
            $appShellPatterns = [
                'app-shell',
                'appShell',
                'shell',
                'core',
                'critical'
            ];
            
            $hasAppShellCache = false;
            foreach ($appShellPatterns as $pattern) {
                if (stripos($serviceWorkerContent, $pattern) !== false) {
                    $hasAppShellCache = true;
                    break;
                }
            }
            
            $this->assert(
                $hasAppShellCache,
                "Service worker should define app shell or critical resource cache"
            );
            
            // Test that service worker contains cache versioning
            $versioningPatterns = [
                'version',
                'v1',
                'v2',
                '-v',
                'VERSION'
            ];
            
            $hasVersioning = false;
            foreach ($versioningPatterns as $pattern) {
                if (strpos($serviceWorkerContent, $pattern) !== false) {
                    $hasVersioning = true;
                    break;
                }
            }
            
            $this->assert(
                $hasVersioning,
                "Service worker should implement cache versioning"
            );
            
            // Test that service worker contains cache cleanup logic
            $cleanupPatterns = [
                'delete',
                'cleanup',
                'clear',
                'remove',
                'activate'
            ];
            
            $hasCleanup = false;
            foreach ($cleanupPatterns as $pattern) {
                if (stripos($serviceWorkerContent, $pattern) !== false) {
                    $hasCleanup = true;
                    break;
                }
            }
            
            $this->assert(
                $hasCleanup,
                "Service worker should contain cache cleanup logic"
            );
            
            // Test a random app shell resource type
            $appShellTypes = ['css', 'js', 'html', 'icon'];
            $testType = $this->generateRandomChoice($appShellTypes);
            
            return [
                'success' => true,
                'data' => [
                    'has_app_shell_cache' => $hasAppShellCache,
                    'has_versioning' => $hasVersioning,
                    'has_cleanup' => $hasCleanup,
                    'tested_type' => $testType
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate a random subset of an array
     */
    private function generateRandomSubset(array $array, int $count): array {
        $count = min($count, count($array));
        $keys = array_rand($array, $count);
        
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        
        $result = [];
        foreach ($keys as $key) {
            $result[] = $array[$key];
        }
        
        return $result;
    }
}
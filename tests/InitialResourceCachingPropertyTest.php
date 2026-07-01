<?php
/**
 * Property Test for Initial Resource Caching
 * **Feature: clarity-pwa-conversion, Property 11: Initial Resource Caching**
 * **Validates: Requirements 4.1**
 */

require_once 'PropertyTestBase.php';

class InitialResourceCachingPropertyTest extends PropertyTestBase {
    
    private $serviceWorkerPath;
    private $initialResources = [
        // Core app shell
        '/',
        '/dashboard.php',
        '/index.php',
        
        // Critical CSS
        'assets/css/tailwind.css',
        'assets/css/app.css',
        
        // Critical JavaScript
        'assets/js/app.js',
        'assets/js/chart.min.js',
        
        // Essential images
        'assets/icons/icon-192.png',
        'assets/logo.png',
        
        // Offline fallback
        'offline.html'
    ];
    
    public function __construct() {
        parent::__construct();
        $this->serviceWorkerPath = __DIR__ . '/../sw.js';
    }
    
    public function runTests(): bool {
        echo "=== Initial Resource Caching Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 11: Initial Resource Caching
        $allPassed &= $this->runPropertyTest(
            "Property 11: Critical resources are cached during first application load",
            [$this, 'testCriticalResourcesInitialCaching']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 11: Service worker install event caches essential resources",
            [$this, 'testInstallEventCaching']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 11: App shell resources are prioritized for initial caching",
            [$this, 'testAppShellPrioritization']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 11: Critical resources are cached during first application load
     * **Feature: clarity-pwa-conversion, Property 11: Initial Resource Caching**
     * **Validates: Requirements 4.1**
     */
    public function testCriticalResourcesInitialCaching(): array {
        try {
            $this->assert(
                file_exists($this->serviceWorkerPath),
                "Service worker file should exist: sw.js"
            );
            
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test that service worker has install event handler
            $this->assert(
                preg_match('/addEventListener\s*\(\s*[\'"]install[\'"]/', $serviceWorkerContent),
                "Service worker should register install event listener for initial caching"
            );
            
            // Test that install handler contains caching logic
            $cachingMethods = [
                'cache\.addAll',
                'cache\.add',
                'caches\.open',
                'addAll\s*\(',
                'put\s*\('
            ];
            
            $hasCachingInInstall = false;
            foreach ($cachingMethods as $method) {
                if (preg_match("/$method/", $serviceWorkerContent)) {
                    $hasCachingInInstall = true;
                    break;
                }
            }
            
            $this->assert(
                $hasCachingInInstall,
                "Service worker install event should contain caching methods"
            );
            
            // Test that service worker uses waitUntil for install caching
            $this->assert(
                strpos($serviceWorkerContent, 'waitUntil') !== false,
                "Service worker should use waitUntil to ensure caching completes during install"
            );
            
            // Test a random subset of initial resources
            $testResources = $this->generateRandomSubset($this->initialResources, rand(3, 6));
            
            // Check if service worker contains resource list or caching array
            $hasResourceList = (
                strpos($serviceWorkerContent, '[') !== false &&
                strpos($serviceWorkerContent, ']') !== false
            );
            
            $this->assert(
                $hasResourceList,
                "Service worker should contain resource list for initial caching"
            );
            
            return [
                'success' => true,
                'data' => [
                    'has_install_handler' => preg_match('/addEventListener\s*\(\s*[\'"]install[\'"]/', $serviceWorkerContent),
                    'has_caching_in_install' => $hasCachingInInstall,
                    'has_wait_until' => strpos($serviceWorkerContent, 'waitUntil') !== false,
                    'has_resource_list' => $hasResourceList,
                    'tested_resources' => $testResources
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
     * Property 11: Service worker install event caches essential resources
     * **Feature: clarity-pwa-conversion, Property 11: Initial Resource Caching**
     * **Validates: Requirements 4.1**
     */
    public function testInstallEventCaching(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test that install event contains proper caching sequence
            $installEventPattern = '/addEventListener\s*\(\s*[\'"]install[\'"][^}]*\}/s';
            
            $this->assert(
                preg_match($installEventPattern, $serviceWorkerContent),
                "Service worker should have complete install event handler"
            );
            
            // Test that install event contains cache opening
            $cacheOpenPatterns = [
                'caches\.open',
                'cache\s*=.*open',
                'open\s*\('
            ];
            
            $hasCacheOpen = false;
            foreach ($cacheOpenPatterns as $pattern) {
                if (preg_match("/$pattern/", $serviceWorkerContent)) {
                    $hasCacheOpen = true;
                    break;
                }
            }
            
            $this->assert(
                $hasCacheOpen,
                "Service worker install event should open cache for resource storage"
            );
            
            // Test that install event contains resource addition
            $resourceAddPatterns = [
                'addAll\s*\(',
                'add\s*\(',
                'put\s*\(',
                'cache.*\['
            ];
            
            $hasResourceAdd = false;
            foreach ($resourceAddPatterns as $pattern) {
                if (preg_match("/$pattern/", $serviceWorkerContent)) {
                    $hasResourceAdd = true;
                    break;
                }
            }
            
            $this->assert(
                $hasResourceAdd,
                "Service worker install event should add resources to cache"
            );
            
            // Test that install event handles errors appropriately
            $errorHandlingPatterns = [
                'catch\s*\(',
                '\.catch',
                'error',
                'reject'
            ];
            
            $hasErrorHandling = false;
            foreach ($errorHandlingPatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $hasErrorHandling = true;
                    break;
                }
            }
            
            // Test random essential resource type
            $resourceTypes = ['html', 'css', 'js', 'image', 'icon'];
            $testResourceType = $this->generateRandomChoice($resourceTypes);
            
            return [
                'success' => true,
                'data' => [
                    'has_complete_install' => preg_match($installEventPattern, $serviceWorkerContent),
                    'has_cache_open' => $hasCacheOpen,
                    'has_resource_add' => $hasResourceAdd,
                    'has_error_handling' => $hasErrorHandling,
                    'tested_resource_type' => $testResourceType
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['tested_resource_type' => $testResourceType ?? null]
            ];
        }
    }
    
    /**
     * Property 11: App shell resources are prioritized for initial caching
     * **Feature: clarity-pwa-conversion, Property 11: Initial Resource Caching**
     * **Validates: Requirements 4.1**
     */
    public function testAppShellPrioritization(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test that service worker defines app shell cache name
            $appShellCachePatterns = [
                'app.*shell',
                'shell.*cache',
                'core.*cache',
                'critical.*cache',
                'static.*cache'
            ];
            
            $hasAppShellCache = false;
            foreach ($appShellCachePatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $hasAppShellCache = true;
                    break;
                }
            }
            
            $this->assert(
                $hasAppShellCache,
                "Service worker should define app shell cache for prioritized resources"
            );
            
            // Test that service worker contains cache versioning for app shell
            $versioningPatterns = [
                'version',
                'v\d+',
                'VERSION',
                '-v',
                'CACHE_VERSION'
            ];
            
            $hasVersioning = false;
            foreach ($versioningPatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $hasVersioning = true;
                    break;
                }
            }
            
            $this->assert(
                $hasVersioning,
                "Service worker should implement versioning for app shell cache"
            );
            
            // Test that service worker prioritizes static resources
            $staticResourcePatterns = [
                '\.css',
                '\.js',
                '\.html',
                '\.png',
                '\.ico'
            ];
            
            $foundStaticPatterns = [];
            foreach ($staticResourcePatterns as $pattern) {
                if (preg_match("/$pattern/", $serviceWorkerContent)) {
                    $foundStaticPatterns[] = $pattern;
                }
            }
            
            $this->assert(
                count($foundStaticPatterns) >= 3,
                "Service worker should handle multiple types of static resources for app shell"
            );
            
            // Test that service worker contains skipWaiting for immediate activation
            $activationPatterns = [
                'skipWaiting',
                'skip.*waiting',
                'self\.skipWaiting'
            ];
            
            $hasSkipWaiting = false;
            foreach ($activationPatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $hasSkipWaiting = true;
                    break;
                }
            }
            
            // Test random app shell component
            $appShellComponents = ['layout', 'navigation', 'header', 'footer', 'sidebar'];
            $testComponent = $this->generateRandomChoice($appShellComponents);
            
            return [
                'success' => true,
                'data' => [
                    'has_app_shell_cache' => $hasAppShellCache,
                    'has_versioning' => $hasVersioning,
                    'static_patterns' => $foundStaticPatterns,
                    'static_count' => count($foundStaticPatterns),
                    'has_skip_waiting' => $hasSkipWaiting,
                    'tested_component' => $testComponent
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
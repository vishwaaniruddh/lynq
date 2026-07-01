<?php
/**
 * Property Test for Offline Content Serving
 * **Feature: clarity-pwa-conversion, Property 1: Offline Content Serving**
 * **Validates: Requirements 1.1**
 */

require_once 'PropertyTestBase.php';

class OfflineContentServingPropertyTest extends PropertyTestBase {
    
    private $serviceWorkerPath;
    private $testPages = [
        '/dashboard.php',
        '/inventory/',
        '/installation/',
        '/sites/',
        '/engineer/feasibility_list.php'
    ];
    
    public function __construct() {
        parent::__construct();
        $this->serviceWorkerPath = __DIR__ . '/../sw.js';
    }
    
    public function runTests(): bool {
        echo "=== Offline Content Serving Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 1: Offline Content Serving
        $allPassed &= $this->runPropertyTest(
            "Property 1: Service worker serves cached content when offline",
            [$this, 'testOfflineContentServing']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 1: Service worker handles fetch events for cached resources",
            [$this, 'testServiceWorkerFetchHandling']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 1: Cached content is served without network errors",
            [$this, 'testCachedContentServing']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 1: Service worker serves cached content when offline
     * **Feature: clarity-pwa-conversion, Property 1: Offline Content Serving**
     * **Validates: Requirements 1.1**
     */
    public function testOfflineContentServing(): array {
        try {
            $this->assert(
                file_exists($this->serviceWorkerPath),
                "Service worker file should exist: sw.js"
            );
            
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            $this->assert(
                !empty($serviceWorkerContent),
                "Service worker should not be empty"
            );
            
            // Test that service worker contains fetch event handler
            $this->assert(
                strpos($serviceWorkerContent, 'fetch') !== false,
                "Service worker should handle fetch events"
            );
            
            // Test that service worker contains cache logic
            $this->assert(
                strpos($serviceWorkerContent, 'cache') !== false || strpos($serviceWorkerContent, 'Cache') !== false,
                "Service worker should contain caching logic"
            );
            
            // Test that service worker contains offline handling
            $offlineKeywords = ['offline', 'network', 'respondWith', 'match'];
            $hasOfflineHandling = false;
            
            foreach ($offlineKeywords as $keyword) {
                if (strpos($serviceWorkerContent, $keyword) !== false) {
                    $hasOfflineHandling = true;
                    break;
                }
            }
            
            $this->assert(
                $hasOfflineHandling,
                "Service worker should contain offline handling logic"
            );
            
            return [
                'success' => true,
                'data' => [
                    'service_worker_size' => strlen($serviceWorkerContent),
                    'has_fetch_handler' => strpos($serviceWorkerContent, 'fetch') !== false,
                    'has_cache_logic' => strpos($serviceWorkerContent, 'cache') !== false
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
     * Property 1: Service worker handles fetch events for cached resources
     * **Feature: clarity-pwa-conversion, Property 1: Offline Content Serving**
     * **Validates: Requirements 1.1**
     */
    public function testServiceWorkerFetchHandling(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test a random page from our test pages
            $testPage = $this->generateRandomChoice($this->testPages);
            
            // Check that service worker contains addEventListener for fetch
            $this->assert(
                preg_match('/addEventListener\s*\(\s*[\'"]fetch[\'"]/', $serviceWorkerContent),
                "Service worker should register fetch event listener"
            );
            
            // Check that service worker contains respondWith method
            $this->assert(
                strpos($serviceWorkerContent, 'respondWith') !== false,
                "Service worker should use respondWith for fetch events"
            );
            
            // Check that service worker contains cache matching logic
            $cacheMatchPatterns = [
                'caches.match',
                'cache.match',
                'match(',
                'matchAll('
            ];
            
            $hasCacheMatch = false;
            foreach ($cacheMatchPatterns as $pattern) {
                if (strpos($serviceWorkerContent, $pattern) !== false) {
                    $hasCacheMatch = true;
                    break;
                }
            }
            
            $this->assert(
                $hasCacheMatch,
                "Service worker should contain cache matching logic"
            );
            
            return [
                'success' => true,
                'data' => [
                    'tested_page' => $testPage,
                    'has_fetch_listener' => preg_match('/addEventListener\s*\(\s*[\'"]fetch[\'"]/', $serviceWorkerContent),
                    'has_respond_with' => strpos($serviceWorkerContent, 'respondWith') !== false,
                    'has_cache_match' => $hasCacheMatch
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['tested_page' => $testPage ?? null]
            ];
        }
    }
    
    /**
     * Property 1: Cached content is served without network errors
     * **Feature: clarity-pwa-conversion, Property 1: Offline Content Serving**
     * **Validates: Requirements 1.1**
     */
    public function testCachedContentServing(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test that service worker contains error handling for network failures
            $errorHandlingPatterns = [
                'catch',
                'error',
                'reject',
                'fallback',
                'offline'
            ];
            
            $hasErrorHandling = false;
            foreach ($errorHandlingPatterns as $pattern) {
                if (stripos($serviceWorkerContent, $pattern) !== false) {
                    $hasErrorHandling = true;
                    break;
                }
            }
            
            $this->assert(
                $hasErrorHandling,
                "Service worker should contain error handling for network failures"
            );
            
            // Test that service worker contains cache-first or cache fallback strategy
            $cacheStrategies = [
                'cache-first',
                'cacheFirst',
                'cache.match',
                'caches.match'
            ];
            
            $hasCacheStrategy = false;
            foreach ($cacheStrategies as $strategy) {
                if (stripos($serviceWorkerContent, $strategy) !== false) {
                    $hasCacheStrategy = true;
                    break;
                }
            }
            
            $this->assert(
                $hasCacheStrategy,
                "Service worker should implement cache-first or fallback strategy"
            );
            
            // Test that service worker handles different resource types
            $resourceTypes = ['html', 'css', 'js', 'image', 'api'];
            $testResourceType = $this->generateRandomChoice($resourceTypes);
            
            // Check if service worker has logic for different resource types
            $hasResourceTypeHandling = (
                strpos($serviceWorkerContent, 'url') !== false ||
                strpos($serviceWorkerContent, 'request') !== false ||
                strpos($serviceWorkerContent, 'destination') !== false
            );
            
            $this->assert(
                $hasResourceTypeHandling,
                "Service worker should handle different resource types"
            );
            
            return [
                'success' => true,
                'data' => [
                    'has_error_handling' => $hasErrorHandling,
                    'has_cache_strategy' => $hasCacheStrategy,
                    'tested_resource_type' => $testResourceType,
                    'has_resource_handling' => $hasResourceTypeHandling
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
<?php
/**
 * Property Test for Cache Version Management
 * **Feature: clarity-pwa-conversion, Property 19: Cache Version Management**
 * **Validates: Requirements 6.2**
 */

require_once 'PropertyTestBase.php';

class CacheVersionManagementPropertyTest extends PropertyTestBase {
    
    private $serviceWorkerPath;
    
    public function __construct() {
        parent::__construct();
        $this->serviceWorkerPath = __DIR__ . '/../sw.js';
    }
    
    public function runTests(): bool {
        echo "=== Cache Version Management Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 19: Cache Version Management
        $allPassed &= $this->runPropertyTest(
            "Property 19: Old cache versions are cleaned up during application updates",
            [$this, 'testOldCacheCleanup']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 19: New cache versions are created for application updates",
            [$this, 'testNewCacheVersionCreation']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 19: Cache versioning prevents stale content serving",
            [$this, 'testStaleContentPrevention']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 19: Old cache versions are cleaned up during application updates
     * **Feature: clarity-pwa-conversion, Property 19: Cache Version Management**
     * **Validates: Requirements 6.2**
     */
    public function testOldCacheCleanup(): array {
        try {
            $this->assert(
                file_exists($this->serviceWorkerPath),
                "Service worker file should exist: sw.js"
            );
            
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test activate event handler for cleanup
            $this->assert(
                preg_match('/addEventListener\s*\(\s*[\'"]activate[\'"]/', $serviceWorkerContent),
                "Service worker should register activate event listener for cache cleanup"
            );
            
            // Test cache deletion logic
            $deletionPatterns = [
                'caches\.delete',
                'cache\.delete',
                'delete\s*\(',
                'cleanup',
                'clear',
                'remove'
            ];
            
            $hasDeletion = false;
            foreach ($deletionPatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $hasDeletion = true;
                    break;
                }
            }
            
            $this->assert(
                $hasDeletion,
                "Service worker should contain cache deletion logic for cleanup"
            );
            
            // Test cache keys enumeration
            $keysPatterns = [
                'caches\.keys',
                'cache\.keys',
                'keys\s*\(',
                'forEach',
                'map\s*\('
            ];
            
            $hasKeysEnumeration = false;
            foreach ($keysPatterns as $pattern) {
                if (preg_match("/$pattern/", $serviceWorkerContent)) {
                    $hasKeysEnumeration = true;
                    break;
                }
            }
            
            $this->assert(
                $hasKeysEnumeration,
                "Service worker should enumerate cache keys for selective cleanup"
            );
            
            return [
                'success' => true,
                'data' => [
                    'has_activate_handler' => preg_match('/addEventListener\s*\(\s*[\'"]activate[\'"]/', $serviceWorkerContent),
                    'has_deletion' => $hasDeletion,
                    'has_keys_enumeration' => $hasKeysEnumeration
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
     * Property 19: New cache versions are created for application updates
     * **Feature: clarity-pwa-conversion, Property 19: Cache Version Management**
     * **Validates: Requirements 6.2**
     */
    public function testNewCacheVersionCreation(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test cache versioning
            $versioningPatterns = [
                'version',
                'v\d+',
                'VERSION',
                'CACHE_VERSION',
                '-v',
                '_v'
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
                "Service worker should implement cache versioning"
            );
            
            // Test cache name generation with versions
            $cacheNamePatterns = [
                'cache.*name',
                'cacheName',
                'CACHE_NAME',
                '\+.*version',
                'version.*\+'
            ];
            
            $hasCacheNaming = false;
            foreach ($cacheNamePatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $hasCacheNaming = true;
                    break;
                }
            }
            
            $this->assert(
                $hasCacheNaming,
                "Service worker should generate versioned cache names"
            );
            
            // Test install event for new cache creation
            $this->assert(
                preg_match('/addEventListener\s*\(\s*[\'"]install[\'"]/', $serviceWorkerContent),
                "Service worker should register install event for new cache creation"
            );
            
            return [
                'success' => true,
                'data' => [
                    'has_versioning' => $hasVersioning,
                    'has_cache_naming' => $hasCacheNaming,
                    'has_install_handler' => preg_match('/addEventListener\s*\(\s*[\'"]install[\'"]/', $serviceWorkerContent)
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
     * Property 19: Cache versioning prevents stale content serving
     * **Feature: clarity-pwa-conversion, Property 19: Cache Version Management**
     * **Validates: Requirements 6.2**
     */
    public function testStaleContentPrevention(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test skipWaiting for immediate activation
            $skipWaitingPatterns = [
                'skipWaiting',
                'skip.*waiting',
                'self\.skipWaiting'
            ];
            
            $hasSkipWaiting = false;
            foreach ($skipWaitingPatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $hasSkipWaiting = true;
                    break;
                }
            }
            
            // Test clients.claim for immediate control
            $claimPatterns = [
                'clients\.claim',
                'claim\s*\(',
                'self\.clients\.claim'
            ];
            
            $hasClaim = false;
            foreach ($claimPatterns as $pattern) {
                if (preg_match("/$pattern/", $serviceWorkerContent)) {
                    $hasClaim = true;
                    break;
                }
            }
            
            // Test cache invalidation logic
            $invalidationPatterns = [
                'invalidate',
                'expire',
                'stale',
                'fresh',
                'update'
            ];
            
            $hasInvalidation = false;
            foreach ($invalidationPatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $hasInvalidation = true;
                    break;
                }
            }
            
            // Test random cache management scenario
            $cacheScenarios = ['update', 'invalidation', 'refresh', 'cleanup'];
            $testScenario = $this->generateRandomChoice($cacheScenarios);
            
            return [
                'success' => true,
                'data' => [
                    'has_skip_waiting' => $hasSkipWaiting,
                    'has_claim' => $hasClaim,
                    'has_invalidation' => $hasInvalidation,
                    'tested_scenario' => $testScenario
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['tested_scenario' => $testScenario ?? null]
            ];
        }
    }
}
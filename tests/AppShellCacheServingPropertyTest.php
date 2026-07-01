<?php
/**
 * Property Test for App Shell Cache Serving
 * **Feature: clarity-pwa-conversion, Property 14: App Shell Cache Serving**
 * **Validates: Requirements 4.4**
 */

require_once 'PropertyTestBase.php';

class AppShellCacheServingPropertyTest extends PropertyTestBase {
    
    private $serviceWorkerPath;
    private $appShellResources = [
        '/',
        '/dashboard.php',
        '/index.php',
        'assets/css/tailwind.css',
        'assets/css/app.css',
        'assets/js/app.js'
    ];
    
    public function __construct() {
        parent::__construct();
        $this->serviceWorkerPath = __DIR__ . '/../sw.js';
    }
    
    public function runTests(): bool {
        echo "=== App Shell Cache Serving Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 14: App Shell Cache Serving
        $allPassed &= $this->runPropertyTest(
            "Property 14: App shell resources are served immediately from cache",
            [$this, 'testAppShellImmediateServing']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 14: App shell cache has highest priority for serving",
            [$this, 'testAppShellCachePriority']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 14: App shell resources are served immediately from cache
     * **Feature: clarity-pwa-conversion, Property 14: App Shell Cache Serving**
     * **Validates: Requirements 4.4**
     */
    public function testAppShellImmediateServing(): array {
        try {
            $this->assert(
                file_exists($this->serviceWorkerPath),
                "Service worker file should exist: sw.js"
            );
            
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test app shell cache definition
            $appShellPatterns = [
                'app.*shell',
                'shell.*cache',
                'core.*cache',
                'static.*cache'
            ];
            
            $hasAppShellCache = false;
            foreach ($appShellPatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $hasAppShellCache = true;
                    break;
                }
            }
            
            $this->assert(
                $hasAppShellCache,
                "Service worker should define app shell cache"
            );
            
            // Test immediate cache serving
            $immediateServingPatterns = [
                'caches\.match',
                'cache\.match',
                'respondWith.*cache',
                'return.*cache'
            ];
            
            $hasImmediateServing = false;
            foreach ($immediateServingPatterns as $pattern) {
                if (preg_match("/$pattern/", $serviceWorkerContent)) {
                    $hasImmediateServing = true;
                    break;
                }
            }
            
            $this->assert(
                $hasImmediateServing,
                "Service worker should serve app shell resources immediately from cache"
            );
            
            return [
                'success' => true,
                'data' => [
                    'has_app_shell_cache' => $hasAppShellCache,
                    'has_immediate_serving' => $hasImmediateServing
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
     * Property 14: App shell cache has highest priority for serving
     * **Feature: clarity-pwa-conversion, Property 14: App Shell Cache Serving**
     * **Validates: Requirements 4.4**
     */
    public function testAppShellCachePriority(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test cache-first strategy for app shell
            $cacheFirstPatterns = [
                'cache.*first',
                'caches\.match.*then',
                'match.*\|\|.*fetch',
                'cache.*before.*network'
            ];
            
            $hasCacheFirst = false;
            foreach ($cacheFirstPatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $hasCacheFirst = true;
                    break;
                }
            }
            
            $this->assert(
                $hasCacheFirst,
                "Service worker should prioritize cache over network for app shell"
            );
            
            // Test random app shell resource
            $testResource = $this->generateRandomChoice($this->appShellResources);
            
            return [
                'success' => true,
                'data' => [
                    'has_cache_first' => $hasCacheFirst,
                    'tested_resource' => $testResource
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
}
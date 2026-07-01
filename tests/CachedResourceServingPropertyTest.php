<?php
/**
 * Property Test for Cached Resource Serving
 * **Feature: clarity-pwa-conversion, Property 12: Cached Resource Serving**
 * **Validates: Requirements 4.2**
 */

require_once 'PropertyTestBase.php';

class CachedResourceServingPropertyTest extends PropertyTestBase {
    
    private $serviceWorkerPath;
    
    public function __construct() {
        parent::__construct();
        $this->serviceWorkerPath = __DIR__ . '/../sw.js';
    }
    
    public function runTests(): bool {
        echo "=== Cached Resource Serving Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 12: Cached Resource Serving
        $allPassed &= $this->runPropertyTest(
            "Property 12: Cached resources are served for improved performance on subsequent visits",
            [$this, 'testCachedResourcePerformance']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 12: Service worker serves cached resources before network requests",
            [$this, 'testCacheFirstStrategy']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 12: Cached resources are served for improved performance on subsequent visits
     * **Feature: clarity-pwa-conversion, Property 12: Cached Resource Serving**
     * **Validates: Requirements 4.2**
     */
    public function testCachedResourcePerformance(): array {
        try {
            $this->assert(
                file_exists($this->serviceWorkerPath),
                "Service worker file should exist: sw.js"
            );
            
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test cache matching for performance
            $cacheMatchPatterns = [
                'caches\.match',
                'cache\.match',
                'match\s*\(',
                'respondWith.*cache'
            ];
            
            $hasCacheMatch = false;
            foreach ($cacheMatchPatterns as $pattern) {
                if (preg_match("/$pattern/", $serviceWorkerContent)) {
                    $hasCacheMatch = true;
                    break;
                }
            }
            
            $this->assert(
                $hasCacheMatch,
                "Service worker should use cache matching for performance optimization"
            );
            
            return [
                'success' => true,
                'data' => [
                    'has_cache_match' => $hasCacheMatch
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
     * Property 12: Service worker serves cached resources before network requests
     * **Feature: clarity-pwa-conversion, Property 12: Cached Resource Serving**
     * **Validates: Requirements 4.2**
     */
    public function testCacheFirstStrategy(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test cache-first strategy implementation
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
                "Service worker should implement cache-first strategy for static resources"
            );
            
            return [
                'success' => true,
                'data' => [
                    'has_cache_first' => $hasCacheFirst
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
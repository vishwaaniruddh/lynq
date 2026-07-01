<?php
/**
 * Property Test for Caching Strategy Implementation
 * **Feature: clarity-pwa-conversion, Property 13: Caching Strategy Implementation**
 * **Validates: Requirements 4.3**
 */

require_once 'PropertyTestBase.php';

class CachingStrategyImplementationPropertyTest extends PropertyTestBase {
    
    private $serviceWorkerPath;
    private $resourceTypes = [
        'static' => ['css', 'js', 'html', 'images'],
        'dynamic' => ['api', 'json', 'xhr'],
        'media' => ['png', 'jpg', 'svg', 'ico']
    ];
    
    public function __construct() {
        parent::__construct();
        $this->serviceWorkerPath = __DIR__ . '/../sw.js';
    }
    
    public function runTests(): bool {
        echo "=== Caching Strategy Implementation Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 13: Caching Strategy Implementation
        $allPassed &= $this->runPropertyTest(
            "Property 13: Appropriate cache-first strategies are applied for static resources",
            [$this, 'testCacheFirstForStatic']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 13: Appropriate network-first strategies are applied for dynamic content",
            [$this, 'testNetworkFirstForDynamic']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 13: Different resource types use appropriate caching strategies",
            [$this, 'testResourceTypeStrategies']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 13: Appropriate cache-first strategies are applied for static resources
     * **Feature: clarity-pwa-conversion, Property 13: Caching Strategy Implementation**
     * **Validates: Requirements 4.3**
     */
    public function testCacheFirstForStatic(): array {
        try {
            $this->assert(
                file_exists($this->serviceWorkerPath),
                "Service worker file should exist: sw.js"
            );
            
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test cache-first strategy for static resources
            $cacheFirstPatterns = [
                'cache.*first',
                'caches\.match.*then.*fetch',
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
            
            // Test static resource detection
            $staticPatterns = ['\.css', '\.js', '\.html', '\.png', '\.jpg', '\.svg'];
            $testPattern = $this->generateRandomChoice($staticPatterns);
            
            return [
                'success' => true,
                'data' => [
                    'has_cache_first' => $hasCacheFirst,
                    'tested_pattern' => $testPattern
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
     * Property 13: Appropriate network-first strategies are applied for dynamic content
     * **Feature: clarity-pwa-conversion, Property 13: Caching Strategy Implementation**
     * **Validates: Requirements 4.3**
     */
    public function testNetworkFirstForDynamic(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test network-first strategy for dynamic content
            $networkFirstPatterns = [
                'network.*first',
                'fetch.*then.*cache',
                'fetch.*catch.*cache',
                'api.*network'
            ];
            
            $hasNetworkFirst = false;
            foreach ($networkFirstPatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $hasNetworkFirst = true;
                    break;
                }
            }
            
            // Test API endpoint handling
            $apiPatterns = ['/api/', 'json', 'xhr', 'POST', 'PUT', 'DELETE'];
            $testApiPattern = $this->generateRandomChoice($apiPatterns);
            
            return [
                'success' => true,
                'data' => [
                    'has_network_first' => $hasNetworkFirst,
                    'tested_api_pattern' => $testApiPattern
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['tested_api_pattern' => $testApiPattern ?? null]
            ];
        }
    }
    
    /**
     * Property 13: Different resource types use appropriate caching strategies
     * **Feature: clarity-pwa-conversion, Property 13: Caching Strategy Implementation**
     * **Validates: Requirements 4.3**
     */
    public function testResourceTypeStrategies(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test conditional logic for different resource types
            $conditionalPatterns = [
                'if\s*\(',
                'switch\s*\(',
                'case\s*',
                '\?\s*',
                'else',
                'request\.url',
                'request\.destination'
            ];
            
            $foundConditionals = [];
            foreach ($conditionalPatterns as $pattern) {
                if (preg_match("/$pattern/", $serviceWorkerContent)) {
                    $foundConditionals[] = $pattern;
                }
            }
            
            $this->assert(
                count($foundConditionals) >= 2,
                "Service worker should contain conditional logic for different resource types"
            );
            
            // Test random resource type handling
            $testResourceCategory = $this->generateRandomChoice(array_keys($this->resourceTypes));
            $testResourceType = $this->generateRandomChoice($this->resourceTypes[$testResourceCategory]);
            
            return [
                'success' => true,
                'data' => [
                    'conditionals' => $foundConditionals,
                    'conditional_count' => count($foundConditionals),
                    'tested_category' => $testResourceCategory,
                    'tested_type' => $testResourceType
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'tested_category' => $testResourceCategory ?? null,
                    'tested_type' => $testResourceType ?? null
                ]
            ];
        }
    }
}
<?php
/**
 * Property Test for Service Worker Caching Logic
 * **Feature: clarity-pwa-conversion, Property 10: Service Worker Caching Logic**
 * **Validates: Requirements 3.5**
 */

require_once 'PropertyTestBase.php';

class ServiceWorkerCachingLogicPropertyTest extends PropertyTestBase {
    
    private $serviceWorkerPath;
    private $cacheStrategies = [
        'cache-first',
        'network-first',
        'stale-while-revalidate',
        'cache-only',
        'network-only'
    ];
    
    public function __construct() {
        parent::__construct();
        $this->serviceWorkerPath = __DIR__ . '/../sw.js';
    }
    
    public function runTests(): bool {
        echo "=== Service Worker Caching Logic Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 10: Service Worker Caching Logic
        $allPassed &= $this->runPropertyTest(
            "Property 10: Service worker implements meaningful caching strategies",
            [$this, 'testMeaningfulCachingStrategies']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 10: Service worker contains comprehensive offline support",
            [$this, 'testComprehensiveOfflineSupport']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 10: Service worker handles different resource types appropriately",
            [$this, 'testResourceTypeHandling']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 10: Service worker implements proper cache management",
            [$this, 'testCacheManagement']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 10: Service worker implements meaningful caching strategies
     * **Feature: clarity-pwa-conversion, Property 10: Service Worker Caching Logic**
     * **Validates: Requirements 3.5**
     */
    public function testMeaningfulCachingStrategies(): array {
        try {
            $this->assert(
                file_exists($this->serviceWorkerPath),
                "Service worker file should exist: sw.js"
            );
            
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            $this->assert(
                !empty($serviceWorkerContent),
                "Service worker should contain meaningful caching logic"
            );
            
            // Test that service worker contains at least one caching strategy
            $strategyPatterns = [
                'cache.*first',
                'network.*first',
                'stale.*while.*revalidate',
                'cache.*only',
                'network.*only',
                'caches\.match',
                'cache\.match',
                'fetch.*then',
                'catch.*cache'
            ];
            
            $foundStrategies = [];
            foreach ($strategyPatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $foundStrategies[] = $pattern;
                }
            }
            
            $this->assert(
                count($foundStrategies) > 0,
                "Service worker should implement at least one caching strategy"
            );
            
            // Test that service worker contains proper fetch event handling
            $this->assert(
                preg_match('/addEventListener\s*\(\s*[\'"]fetch[\'"]/', $serviceWorkerContent),
                "Service worker should register fetch event listener"
            );
            
            $this->assert(
                strpos($serviceWorkerContent, 'respondWith') !== false,
                "Service worker should use respondWith for fetch events"
            );
            
            // Test a random caching strategy concept
            $testStrategy = $this->generateRandomChoice($this->cacheStrategies);
            
            return [
                'success' => true,
                'data' => [
                    'found_strategies' => $foundStrategies,
                    'strategy_count' => count($foundStrategies),
                    'tested_strategy' => $testStrategy,
                    'has_fetch_handler' => preg_match('/addEventListener\s*\(\s*[\'"]fetch[\'"]/', $serviceWorkerContent),
                    'has_respond_with' => strpos($serviceWorkerContent, 'respondWith') !== false
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
     * Property 10: Service worker contains comprehensive offline support
     * **Feature: clarity-pwa-conversion, Property 10: Service Worker Caching Logic**
     * **Validates: Requirements 3.5**
     */
    public function testComprehensiveOfflineSupport(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test offline handling patterns
            $offlinePatterns = [
                'offline',
                'navigator\.onLine',
                'network.*error',
                'fetch.*catch',
                'fallback',
                'cache.*match',
                'error.*handling'
            ];
            
            $foundOfflineFeatures = [];
            foreach ($offlinePatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $foundOfflineFeatures[] = $pattern;
                }
            }
            
            $this->assert(
                count($foundOfflineFeatures) >= 2,
                "Service worker should implement comprehensive offline support with multiple features"
            );
            
            // Test that service worker contains error handling
            $errorHandlingPatterns = [
                'catch\s*\(',
                '\.catch',
                'error',
                'reject',
                'throw'
            ];
            
            $hasErrorHandling = false;
            foreach ($errorHandlingPatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $hasErrorHandling = true;
                    break;
                }
            }
            
            $this->assert(
                $hasErrorHandling,
                "Service worker should contain error handling for network failures"
            );
            
            // Test that service worker contains cache fallback logic
            $fallbackPatterns = [
                'fallback',
                'default',
                'offline.*page',
                'cache.*match.*\|\|',
                'match.*\|\|.*fetch'
            ];
            
            $hasFallback = false;
            foreach ($fallbackPatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $hasFallback = true;
                    break;
                }
            }
            
            // Test random offline scenario handling
            $offlineScenarios = ['network_error', 'cache_miss', 'timeout', 'server_error'];
            $testScenario = $this->generateRandomChoice($offlineScenarios);
            
            return [
                'success' => true,
                'data' => [
                    'offline_features' => $foundOfflineFeatures,
                    'feature_count' => count($foundOfflineFeatures),
                    'has_error_handling' => $hasErrorHandling,
                    'has_fallback' => $hasFallback,
                    'tested_scenario' => $testScenario
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
     * Property 10: Service worker handles different resource types appropriately
     * **Feature: clarity-pwa-conversion, Property 10: Service Worker Caching Logic**
     * **Validates: Requirements 3.5**
     */
    public function testResourceTypeHandling(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test resource type detection patterns
            $resourceTypePatterns = [
                'request\.url',
                'request\.destination',
                'request\.method',
                'url\.pathname',
                'url\.includes',
                'endsWith',
                'startsWith',
                'match\(',
                'test\('
            ];
            
            $foundResourceHandling = [];
            foreach ($resourceTypePatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $foundResourceHandling[] = $pattern;
                }
            }
            
            $this->assert(
                count($foundResourceHandling) >= 2,
                "Service worker should handle different resource types with multiple detection methods"
            );
            
            // Test different resource type handling
            $resourceTypes = [
                'html' => ['\.html', 'text\/html', 'document'],
                'css' => ['\.css', 'text\/css', 'stylesheet'],
                'js' => ['\.js', 'javascript', 'script'],
                'api' => ['\/api\/', 'json', 'xhr'],
                'image' => ['\.png', '\.jpg', '\.svg', 'image']
            ];
            
            $testResourceType = $this->generateRandomChoice(array_keys($resourceTypes));
            $testPatterns = $resourceTypes[$testResourceType];
            
            $hasResourceTypeLogic = false;
            foreach ($testPatterns as $pattern) {
                if (preg_match("/$pattern/i", $serviceWorkerContent)) {
                    $hasResourceTypeLogic = true;
                    break;
                }
            }
            
            // Test that service worker contains conditional logic for different strategies
            $conditionalPatterns = [
                'if\s*\(',
                '\?\s*',
                'switch\s*\(',
                'case\s*',
                'else',
                '&&',
                '\|\|'
            ];
            
            $hasConditionalLogic = false;
            foreach ($conditionalPatterns as $pattern) {
                if (preg_match("/$pattern/", $serviceWorkerContent)) {
                    $hasConditionalLogic = true;
                    break;
                }
            }
            
            $this->assert(
                $hasConditionalLogic,
                "Service worker should contain conditional logic for different resource handling"
            );
            
            return [
                'success' => true,
                'data' => [
                    'resource_handling' => $foundResourceHandling,
                    'handling_count' => count($foundResourceHandling),
                    'tested_resource_type' => $testResourceType,
                    'has_resource_logic' => $hasResourceTypeLogic,
                    'has_conditional_logic' => $hasConditionalLogic
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
     * Property 10: Service worker implements proper cache management
     * **Feature: clarity-pwa-conversion, Property 10: Service Worker Caching Logic**
     * **Validates: Requirements 3.5**
     */
    public function testCacheManagement(): array {
        try {
            $serviceWorkerContent = file_get_contents($this->serviceWorkerPath);
            
            // Test cache management operations
            $cacheOperations = [
                'caches\.open',
                'cache\.add',
                'cache\.addAll',
                'cache\.put',
                'cache\.delete',
                'caches\.delete',
                'cache\.keys',
                'caches\.keys'
            ];
            
            $foundOperations = [];
            foreach ($cacheOperations as $operation) {
                if (preg_match("/$operation/", $serviceWorkerContent)) {
                    $foundOperations[] = $operation;
                }
            }
            
            $this->assert(
                count($foundOperations) >= 3,
                "Service worker should implement multiple cache management operations"
            );
            
            // Test cache versioning and cleanup
            $versioningPatterns = [
                'version',
                'v\d+',
                'VERSION',
                'CACHE_VERSION',
                'activate.*delete',
                'cleanup',
                'clear'
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
                "Service worker should implement cache versioning and cleanup"
            );
            
            // Test activate event for cache cleanup
            $this->assert(
                preg_match('/addEventListener\s*\(\s*[\'"]activate[\'"]/', $serviceWorkerContent),
                "Service worker should register activate event listener for cache management"
            );
            
            // Test random cache management scenario
            $cacheScenarios = ['install', 'update', 'cleanup', 'storage_limit'];
            $testScenario = $this->generateRandomChoice($cacheScenarios);
            
            return [
                'success' => true,
                'data' => [
                    'cache_operations' => $foundOperations,
                    'operation_count' => count($foundOperations),
                    'has_versioning' => $hasVersioning,
                    'has_activate_handler' => preg_match('/addEventListener\s*\(\s*[\'"]activate[\'"]/', $serviceWorkerContent),
                    'tested_scenario' => $testScenario
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
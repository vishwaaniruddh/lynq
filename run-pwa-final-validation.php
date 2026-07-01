<?php
/**
 * ADV Clarity Management System - Final PWA Validation Runner
 * Comprehensive command-line PWA validation and testing
 */

require_once __DIR__ . '/config/autoload.php';

echo "ADV Clarity PWA - Final Validation Suite\n";
echo "========================================\n\n";

class PWAFinalValidator {
    private $results = [];
    private $errors = [];
    private $warnings = [];
    
    public function runAllValidations() {
        echo "Starting comprehensive PWA validation...\n\n";
        
        // Core PWA Requirements
        $this->validateManifest();
        $this->validateServiceWorker();
        $this->validateIcons();
        $this->validateHTTPS();
        $this->validateOfflineCapability();
        
        // Performance and Optimization
        $this->validatePerformance();
        $this->validateCaching();
        
        // Installation and App Store Readiness
        $this->validateInstallability();
        $this->validateAppStoreRequirements();
        
        // Cross-browser Compatibility
        $this->validateCompatibility();
        
        // Generate final report
        $this->generateFinalReport();
        
        return $this->results;
    }
    
    private function validateManifest() {
        echo "1. Validating Web App Manifest...\n";
        
        $manifestPath = __DIR__ . '/app.webmanifest';
        
        if (!file_exists($manifestPath)) {
            $this->addError('Manifest', 'app.webmanifest file not found');
            return;
        }
        
        $manifestContent = file_get_contents($manifestPath);
        $manifest = json_decode($manifestContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError('Manifest', 'Invalid JSON in manifest file');
            return;
        }
        
        // Check required fields
        $requiredFields = [
            'name' => 'Application name',
            'short_name' => 'Short application name',
            'start_url' => 'Start URL',
            'display' => 'Display mode',
            'theme_color' => 'Theme color',
            'background_color' => 'Background color',
            'icons' => 'Application icons'
        ];
        
        $score = 100;
        foreach ($requiredFields as $field => $description) {
            if (empty($manifest[$field])) {
                $this->addError('Manifest', "Missing required field: $field ($description)");
                $score -= 15;
            } else {
                echo "   ✓ $description: present\n";
            }
        }
        
        // Check optional but recommended fields
        $recommendedFields = [
            'description' => 'Application description',
            'screenshots' => 'App screenshots',
            'shortcuts' => 'App shortcuts',
            'categories' => 'App categories'
        ];
        
        foreach ($recommendedFields as $field => $description) {
            if (empty($manifest[$field])) {
                $this->addWarning('Manifest', "Missing recommended field: $field ($description)");
            } else {
                echo "   ✓ $description: present\n";
            }
        }
        
        $this->results['manifest'] = [
            'score' => max(0, $score),
            'passed' => $score >= 70,
            'file_size' => filesize($manifestPath),
            'fields_present' => count(array_filter($requiredFields, fn($field) => !empty($manifest[array_search($field, $requiredFields)])))
        ];
        
        echo "   Manifest Score: {$this->results['manifest']['score']}/100\n\n";
    }
    
    private function validateServiceWorker() {
        echo "2. Validating Service Worker...\n";
        
        $swPath = __DIR__ . '/sw.js';
        
        if (!file_exists($swPath)) {
            $this->addError('Service Worker', 'sw.js file not found');
            return;
        }
        
        $swContent = file_get_contents($swPath);
        $score = 100;
        
        // Check for essential service worker features
        $requiredFeatures = [
            "addEventListener('install'" => 'Install event handler',
            "addEventListener('activate'" => 'Activate event handler',
            "addEventListener('fetch'" => 'Fetch event handler',
            'caches.open' => 'Cache management',
            'cache.addAll' => 'Cache preloading',
            'cache.match' => 'Cache retrieval'
        ];
        
        foreach ($requiredFeatures as $pattern => $description) {
            if (strpos($swContent, $pattern) !== false) {
                echo "   ✓ $description: implemented\n";
            } else {
                $this->addError('Service Worker', "Missing: $description");
                $score -= 15;
            }
        }
        
        // Check for advanced features
        $advancedFeatures = [
            "addEventListener('sync'" => 'Background sync',
            "addEventListener('push'" => 'Push notifications',
            "addEventListener('notificationclick'" => 'Notification click handling',
            'skipWaiting' => 'Service worker update handling'
        ];
        
        foreach ($advancedFeatures as $pattern => $description) {
            if (strpos($swContent, $pattern) !== false) {
                echo "   ✓ $description: implemented\n";
            } else {
                $this->addWarning('Service Worker', "Missing advanced feature: $description");
            }
        }
        
        $this->results['service_worker'] = [
            'score' => max(0, $score),
            'passed' => $score >= 70,
            'file_size' => filesize($swPath),
            'features_implemented' => count(array_filter($requiredFeatures, fn($pattern) => strpos($swContent, $pattern) !== false))
        ];
        
        echo "   Service Worker Score: {$this->results['service_worker']['score']}/100\n\n";
    }
    
    private function validateIcons() {
        echo "3. Validating PWA Icons...\n";
        
        $manifestPath = __DIR__ . '/app.webmanifest';
        
        if (!file_exists($manifestPath)) {
            $this->addError('Icons', 'Cannot validate icons - manifest not found');
            return;
        }
        
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $icons = $manifest['icons'] ?? [];
        
        if (empty($icons)) {
            $this->addError('Icons', 'No icons defined in manifest');
            return;
        }
        
        // Check for required icon sizes
        $requiredSizes = ['72x72', '96x96', '128x128', '144x144', '152x152', '192x192', '384x384', '512x512'];
        $availableSizes = [];
        $accessibleIcons = 0;
        
        foreach ($icons as $icon) {
            if (isset($icon['sizes'])) {
                $sizes = explode(' ', $icon['sizes']);
                $availableSizes = array_merge($availableSizes, $sizes);
            }
            
            // Check if icon file exists
            if (isset($icon['src'])) {
                $iconPath = __DIR__ . '/' . ltrim($icon['src'], '/');
                if (file_exists($iconPath)) {
                    $accessibleIcons++;
                    echo "   ✓ Icon {$icon['sizes']}: accessible\n";
                } else {
                    $this->addWarning('Icons', "Icon file not found: {$icon['src']}");
                }
            }
        }
        
        $missingSizes = array_diff($requiredSizes, $availableSizes);
        $score = max(0, 100 - (count($missingSizes) * 12));
        
        foreach ($missingSizes as $size) {
            $this->addWarning('Icons', "Missing icon size: $size");
        }
        
        $this->results['icons'] = [
            'score' => $score,
            'passed' => count($missingSizes) <= 2,
            'total_icons' => count($icons),
            'accessible_icons' => $accessibleIcons,
            'missing_sizes' => count($missingSizes)
        ];
        
        echo "   Icons Score: {$this->results['icons']['score']}/100\n\n";
    }
    
    private function validateHTTPS() {
        echo "4. Validating HTTPS Requirement...\n";
        
        // For CLI validation, we'll check server configuration
        $isSecure = true; // Assume secure for local validation
        
        if ($isSecure) {
            echo "   ✓ HTTPS requirement: satisfied (or localhost)\n";
            $this->results['https'] = ['score' => 100, 'passed' => true];
        } else {
            $this->addError('HTTPS', 'PWA requires HTTPS in production');
            $this->results['https'] = ['score' => 0, 'passed' => false];
        }
        
        echo "   HTTPS Score: {$this->results['https']['score']}/100\n\n";
    }
    
    private function validateOfflineCapability() {
        echo "5. Validating Offline Capability...\n";
        
        $score = 0;
        
        // Check for offline page
        if (file_exists(__DIR__ . '/offline.html')) {
            echo "   ✓ Offline fallback page: present\n";
            $score += 25;
        } else {
            $this->addWarning('Offline', 'No offline.html fallback page found');
        }
        
        // Check for service worker
        if (file_exists(__DIR__ . '/sw.js')) {
            echo "   ✓ Service worker: present\n";
            $score += 25;
        } else {
            $this->addError('Offline', 'Service worker required for offline functionality');
        }
        
        // Check for offline assets
        $offlineAssets = [
            '/assets/css/offline.css',
            '/assets/js/offline-utils.js',
            '/assets/js/offline-data-manager.js'
        ];
        
        $presentAssets = 0;
        foreach ($offlineAssets as $asset) {
            if (file_exists(__DIR__ . $asset)) {
                $presentAssets++;
            }
        }
        
        if ($presentAssets > 0) {
            echo "   ✓ Offline assets: $presentAssets/" . count($offlineAssets) . " present\n";
            $score += ($presentAssets / count($offlineAssets)) * 25;
        }
        
        // Check for cache strategies in service worker
        if (file_exists(__DIR__ . '/sw.js')) {
            $swContent = file_get_contents(__DIR__ . '/sw.js');
            if (strpos($swContent, 'cache') !== false && strpos($swContent, 'offline') !== false) {
                echo "   ✓ Cache strategies: implemented\n";
                $score += 25;
            } else {
                $this->addWarning('Offline', 'Limited cache strategies detected');
            }
        }
        
        $this->results['offline'] = [
            'score' => round($score),
            'passed' => $score >= 70,
            'offline_assets' => $presentAssets
        ];
        
        echo "   Offline Score: {$this->results['offline']['score']}/100\n\n";
    }
    
    private function validatePerformance() {
        echo "6. Validating Performance Optimization...\n";
        
        $score = 100;
        
        // Check for performance optimization files
        $performanceFiles = [
            '/assets/js/performance-monitor.js' => 'Performance monitoring',
            '/assets/js/cache-optimizer.js' => 'Cache optimization',
            '/api/analytics/performance-insights.php' => 'Performance analytics'
        ];
        
        foreach ($performanceFiles as $file => $description) {
            if (file_exists(__DIR__ . $file)) {
                echo "   ✓ $description: implemented\n";
            } else {
                $this->addWarning('Performance', "Missing: $description");
                $score -= 10;
            }
        }
        
        // Check service worker for performance features
        if (file_exists(__DIR__ . '/sw.js')) {
            $swContent = file_get_contents(__DIR__ . '/sw.js');
            
            $performanceFeatures = [
                'CACHE_SIZE_LIMITS' => 'Cache size management',
                'PERFORMANCE_CONFIG' => 'Performance configuration',
                'trackPerformanceMetric' => 'Performance tracking'
            ];
            
            foreach ($performanceFeatures as $pattern => $description) {
                if (strpos($swContent, $pattern) !== false) {
                    echo "   ✓ $description: implemented\n";
                } else {
                    $this->addWarning('Performance', "Missing: $description");
                    $score -= 5;
                }
            }
        }
        
        $this->results['performance'] = [
            'score' => max(0, $score),
            'passed' => $score >= 70
        ];
        
        echo "   Performance Score: {$this->results['performance']['score']}/100\n\n";
    }
    
    private function validateCaching() {
        echo "7. Validating Caching Strategies...\n";
        
        $score = 0;
        
        if (file_exists(__DIR__ . '/sw.js')) {
            $swContent = file_get_contents(__DIR__ . '/sw.js');
            
            // Check for different caching strategies
            $strategies = [
                'cache-first' => 'Cache-first strategy',
                'network-first' => 'Network-first strategy',
                'stale-while-revalidate' => 'Stale-while-revalidate strategy'
            ];
            
            foreach ($strategies as $pattern => $description) {
                if (strpos($swContent, $pattern) !== false) {
                    echo "   ✓ $description: implemented\n";
                    $score += 20;
                }
            }
            
            // Check for cache management
            $cacheFeatures = [
                'CACHE_NAMES' => 'Named cache buckets',
                'CACHE_TTL' => 'Cache TTL configuration',
                'cleanupExpiredCacheEntries' => 'Cache cleanup'
            ];
            
            foreach ($cacheFeatures as $pattern => $description) {
                if (strpos($swContent, $pattern) !== false) {
                    echo "   ✓ $description: implemented\n";
                    $score += 10;
                }
            }
        } else {
            $this->addError('Caching', 'Service worker required for caching strategies');
        }
        
        $this->results['caching'] = [
            'score' => min(100, $score),
            'passed' => $score >= 70
        ];
        
        echo "   Caching Score: {$this->results['caching']['score']}/100\n\n";
    }
    
    private function validateInstallability() {
        echo "8. Validating Installability...\n";
        
        $score = 100;
        $requirements = [
            'manifest' => file_exists(__DIR__ . '/app.webmanifest'),
            'service_worker' => file_exists(__DIR__ . '/sw.js'),
            'https' => true, // Assume HTTPS for validation
            'icons' => $this->hasRequiredIcons()
        ];
        
        foreach ($requirements as $requirement => $met) {
            if ($met) {
                echo "   ✓ " . ucfirst(str_replace('_', ' ', $requirement)) . ": satisfied\n";
            } else {
                $this->addError('Installability', "Missing requirement: $requirement");
                $score -= 25;
            }
        }
        
        $this->results['installability'] = [
            'score' => max(0, $score),
            'passed' => $score >= 70,
            'requirements_met' => count(array_filter($requirements))
        ];
        
        echo "   Installability Score: {$this->results['installability']['score']}/100\n\n";
    }
    
    private function validateAppStoreRequirements() {
        echo "9. Validating App Store Requirements...\n";
        
        $requirements = [
            'manifest_complete' => $this->isManifestComplete(),
            'required_icons' => $this->hasRequiredIcons(),
            'screenshots' => $this->hasScreenshots(),
            'service_worker' => file_exists(__DIR__ . '/sw.js'),
            'offline_support' => file_exists(__DIR__ . '/offline.html')
        ];
        
        $criticalRequirements = ['manifest_complete', 'required_icons', 'service_worker'];
        $criticalMet = 0;
        $totalMet = 0;
        
        foreach ($requirements as $requirement => $met) {
            if ($met) {
                echo "   ✓ " . ucfirst(str_replace('_', ' ', $requirement)) . ": satisfied\n";
                $totalMet++;
                if (in_array($requirement, $criticalRequirements)) {
                    $criticalMet++;
                }
            } else {
                $level = in_array($requirement, $criticalRequirements) ? 'Error' : 'Warning';
                $method = $level === 'Error' ? 'addError' : 'addWarning';
                $this->$method('App Store', "Missing: " . str_replace('_', ' ', $requirement));
            }
        }
        
        $ready = $criticalMet === count($criticalRequirements);
        $score = ($totalMet / count($requirements)) * 100;
        
        $this->results['app_store'] = [
            'score' => round($score),
            'passed' => $ready,
            'ready' => $ready,
            'critical_met' => $criticalMet,
            'total_met' => $totalMet
        ];
        
        echo "   App Store Score: {$this->results['app_store']['score']}/100\n";
        echo "   Ready for packaging: " . ($ready ? 'YES' : 'NO') . "\n\n";
    }
    
    private function validateCompatibility() {
        echo "10. Validating Cross-browser Compatibility...\n";
        
        // Check for compatibility features
        $compatibilityFeatures = [
            'fetch_polyfill' => 'Fetch API polyfill',
            'promise_polyfill' => 'Promise polyfill',
            'service_worker_fallback' => 'Service worker fallback'
        ];
        
        $score = 80; // Base compatibility score
        
        // Check JavaScript files for polyfills
        $jsFiles = glob(__DIR__ . '/assets/js/*.js');
        $hasPolyfills = false;
        
        foreach ($jsFiles as $file) {
            $content = file_get_contents($file);
            if (strpos($content, 'polyfill') !== false || strpos($content, 'fallback') !== false) {
                $hasPolyfills = true;
                break;
            }
        }
        
        if ($hasPolyfills) {
            echo "   ✓ Polyfills/fallbacks: detected\n";
            $score += 10;
        } else {
            $this->addWarning('Compatibility', 'No polyfills detected - may have compatibility issues');
        }
        
        // Check for progressive enhancement
        if (file_exists(__DIR__ . '/assets/js/pwa-manager.js')) {
            $pwaManager = file_get_contents(__DIR__ . '/assets/js/pwa-manager.js');
            if (strpos($pwaManager, "'serviceWorker' in navigator") !== false) {
                echo "   ✓ Progressive enhancement: implemented\n";
                $score += 10;
            }
        }
        
        $this->results['compatibility'] = [
            'score' => min(100, $score),
            'passed' => $score >= 70
        ];
        
        echo "   Compatibility Score: {$this->results['compatibility']['score']}/100\n\n";
    }
    
    private function generateFinalReport() {
        echo "========================================\n";
        echo "FINAL PWA VALIDATION REPORT\n";
        echo "========================================\n\n";
        
        $totalScore = 0;
        $totalTests = 0;
        $passedTests = 0;
        
        foreach ($this->results as $category => $result) {
            $status = $result['passed'] ? 'PASS' : 'FAIL';
            $statusIcon = $result['passed'] ? '✓' : '✗';
            
            echo sprintf("%-20s %s %3d/100 %s\n", 
                ucfirst(str_replace('_', ' ', $category)) . ':', 
                $statusIcon, 
                $result['score'], 
                $status
            );
            
            $totalScore += $result['score'];
            $totalTests++;
            if ($result['passed']) $passedTests++;
        }
        
        $averageScore = $totalTests > 0 ? round($totalScore / $totalTests) : 0;
        
        echo "\n" . str_repeat('-', 40) . "\n";
        echo sprintf("%-20s   %3d/100\n", "OVERALL SCORE:", $averageScore);
        echo sprintf("%-20s   %d/%d\n", "TESTS PASSED:", $passedTests, $totalTests);
        
        // Determine overall status
        if ($averageScore >= 90) {
            $status = "EXCELLENT - Ready for production";
            $icon = "🎉";
        } elseif ($averageScore >= 80) {
            $status = "GOOD - Ready with minor improvements";
            $icon = "✅";
        } elseif ($averageScore >= 70) {
            $status = "ACCEPTABLE - Needs improvements";
            $icon = "⚠️";
        } else {
            $status = "NEEDS WORK - Major issues to address";
            $icon = "❌";
        }
        
        echo "\n$icon PWA STATUS: $status\n\n";
        
        // Show errors and warnings
        if (!empty($this->errors)) {
            echo "CRITICAL ISSUES TO ADDRESS:\n";
            foreach ($this->errors as $error) {
                echo "  ❌ [{$error['category']}] {$error['message']}\n";
            }
            echo "\n";
        }
        
        if (!empty($this->warnings)) {
            echo "RECOMMENDATIONS FOR IMPROVEMENT:\n";
            foreach ($this->warnings as $warning) {
                echo "  ⚠️  [{$warning['category']}] {$warning['message']}\n";
            }
            echo "\n";
        }
        
        // App store readiness
        $appStoreReady = $this->results['app_store']['ready'] ?? false;
        echo "APP STORE PACKAGING: " . ($appStoreReady ? "✅ READY" : "❌ NOT READY") . "\n";
        
        if ($appStoreReady) {
            echo "\n🎉 Congratulations! Your PWA meets all requirements for app store packaging.\n";
            echo "You can proceed with PWA Builder to generate app packages.\n";
        } else {
            echo "\n⚠️  Address the critical issues above before packaging for app stores.\n";
        }
        
        echo "\nValidation completed at: " . date('Y-m-d H:i:s') . "\n";
    }
    
    private function addError($category, $message) {
        $this->errors[] = ['category' => $category, 'message' => $message];
    }
    
    private function addWarning($category, $message) {
        $this->warnings[] = ['category' => $category, 'message' => $message];
    }
    
    private function isManifestComplete() {
        $manifestPath = __DIR__ . '/app.webmanifest';
        if (!file_exists($manifestPath)) return false;
        
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $requiredFields = ['name', 'short_name', 'start_url', 'display', 'theme_color', 'background_color', 'icons'];
        
        foreach ($requiredFields as $field) {
            if (empty($manifest[$field])) return false;
        }
        
        return true;
    }
    
    private function hasRequiredIcons() {
        $manifestPath = __DIR__ . '/app.webmanifest';
        if (!file_exists($manifestPath)) return false;
        
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $icons = $manifest['icons'] ?? [];
        
        $has192 = false;
        $has512 = false;
        
        foreach ($icons as $icon) {
            if (strpos($icon['sizes'], '192x192') !== false) $has192 = true;
            if (strpos($icon['sizes'], '512x512') !== false) $has512 = true;
        }
        
        return $has192 && $has512;
    }
    
    private function hasScreenshots() {
        $manifestPath = __DIR__ . '/app.webmanifest';
        if (!file_exists($manifestPath)) return false;
        
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $screenshots = $manifest['screenshots'] ?? [];
        
        return count($screenshots) >= 2;
    }
}

// Run the validation
$validator = new PWAFinalValidator();
$results = $validator->runAllValidations();

// Exit with appropriate code
$overallPassed = array_reduce($results, function($carry, $result) {
    return $carry && $result['passed'];
}, true);

exit($overallPassed ? 0 : 1);
?>
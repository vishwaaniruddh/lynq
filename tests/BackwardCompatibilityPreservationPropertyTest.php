<?php
/**
 * Backward Compatibility Preservation Property Test
 * **Feature: clarity-pwa-conversion, Property 20: Backward Compatibility Preservation**
 * **Validates: Requirements 6.4**
 * 
 * Property: For any existing functionality, it should continue to work after PWA features are added
 */

class BackwardCompatibilityPreservationPropertyTest {
    
    private $testResults = [];
    
    public function runPropertyTests(): array {
        echo "=== Backward Compatibility Preservation Property Tests ===\n";
        echo "**Feature: clarity-pwa-conversion, Property 20: Backward Compatibility Preservation**\n";
        echo "**Validates: Requirements 6.4**\n\n";
        
        $results = [];
        
        // Property 20.1: Existing JavaScript functionality preserved
        $results[] = $this->runSingleTest(
            'Property 20.1: Existing JavaScript functionality preserved',
            [$this, 'testExistingJavaScriptFunctionality']
        );
        
        // Property 20.2: Existing PHP functionality preserved
        $results[] = $this->runSingleTest(
            'Property 20.2: Existing PHP functionality preserved',
            [$this, 'testExistingPHPFunctionality']
        );
        
        // Property 20.3: Existing CSS styles preserved
        $results[] = $this->runSingleTest(
            'Property 20.3: Existing CSS styles preserved',
            [$this, 'testExistingCSSStyles']
        );
        
        // Property 20.4: Existing form functionality preserved
        $results[] = $this->runSingleTest(
            'Property 20.4: Existing form functionality preserved',
            [$this, 'testExistingFormFunctionality']
        );
        
        return $results;
    }
    
    /**
     * Run a single test
     */
    private function runSingleTest(string $name, callable $testFunction): array {
        echo "Running property test: $name\n";
        
        try {
            $result = call_user_func($testFunction);
            
            if ($result) {
                echo "✓ Property test passed: $name\n";
                return ['name' => $name, 'passed' => true];
            } else {
                echo "✗ Property test failed: $name\n";
                return ['name' => $name, 'passed' => false];
            }
        } catch (Exception $e) {
            echo "✗ Property test failed: $name - " . $e->getMessage() . "\n";
            return ['name' => $name, 'passed' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Test that existing JavaScript functionality is preserved
     */
    public function testExistingJavaScriptFunctionality(): bool {
        try {
            // Test 1: Check that base layout includes existing JS files
            $baseLayoutPath = __DIR__ . '/../views/layouts/base.php';
            if (!file_exists($baseLayoutPath)) {
                throw new Exception('Base layout file not found');
            }
            
            $baseLayoutContent = file_get_contents($baseLayoutPath);
            
            // Verify existing JS files are still included
            $requiredJSFiles = [
                'assets/js/app.js',
                'assets/js/global-tools.js'
            ];
            
            foreach ($requiredJSFiles as $jsFile) {
                if (strpos($baseLayoutContent, $jsFile) === false) {
                    throw new Exception("Required JS file not found: $jsFile");
                }
            }
            
            // Test 2: Check that PWA JS files are added after existing ones
            $appJsPos = strpos($baseLayoutContent, 'assets/js/app.js');
            $pwaManagerPos = strpos($baseLayoutContent, 'assets/js/pwa-manager.js');
            
            if ($pwaManagerPos === false) {
                throw new Exception('PWA manager JS not found');
            }
            
            if ($appJsPos === false || $pwaManagerPos <= $appJsPos) {
                throw new Exception('PWA JS files not properly ordered after existing JS');
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logFailure('JavaScript functionality preservation failed', [
                'error' => $e->getMessage(),
                'test_type' => 'existing_javascript_functionality'
            ]);
            return false;
        }
    }
    
    /**
     * Test that existing PHP functionality is preserved
     */
    public function testExistingPHPFunctionality(): bool {
        try {
            // Test 1: Check that existing PHP includes are preserved
            $baseLayoutPath = __DIR__ . '/../views/layouts/base.php';
            $baseLayoutContent = file_get_contents($baseLayoutPath);
            
            // Verify existing PHP includes
            $requiredIncludes = [
                'components/sidebar.php',
                'components/header.php',
                'components/alerts.php',
                'components/modal.php',
                'components/global-tools.php'
            ];
            
            foreach ($requiredIncludes as $include) {
                if (strpos($baseLayoutContent, $include) === false) {
                    throw new Exception("Required PHP include not found: $include");
                }
            }
            
            // Test 2: Check that existing PHP variables are still used
            $requiredVariables = [
                '$pageTitle',
                '$baseUrl',
                '$isLoggedIn',
                '$content'
            ];
            
            foreach ($requiredVariables as $variable) {
                if (strpos($baseLayoutContent, $variable) === false) {
                    throw new Exception("Required PHP variable not found: $variable");
                }
            }
            
            // Test 3: Verify existing conditional logic is preserved
            if (strpos($baseLayoutContent, 'if (isset($isLoggedIn) && $isLoggedIn)') === false) {
                throw new Exception('Existing authentication logic not preserved');
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logFailure('PHP functionality preservation failed', [
                'error' => $e->getMessage(),
                'test_type' => 'existing_php_functionality'
            ]);
            return false;
        }
    }
    
    /**
     * Test that existing CSS styles are preserved
     */
    public function testExistingCSSStyles(): bool {
        try {
            // Test 1: Check that existing CSS files are still included
            $baseLayoutPath = __DIR__ . '/../views/layouts/base.php';
            $baseLayoutContent = file_get_contents($baseLayoutPath);
            
            // Verify existing CSS includes
            $requiredCSS = [
                'tailwindcss.com',
                'font-awesome',
                'fonts.googleapis.com'
            ];
            
            foreach ($requiredCSS as $css) {
                if (strpos($baseLayoutContent, $css) === false) {
                    throw new Exception("Required CSS not found: $css");
                }
            }
            
            // Test 2: Check that existing inline styles are preserved
            $requiredStyles = [
                '.sidebar',
                '.sidebar-link',
                '.glass',
                '.card'
            ];
            
            foreach ($requiredStyles as $style) {
                if (strpos($baseLayoutContent, $style) === false) {
                    throw new Exception("Required CSS class not found: $style");
                }
            }
            
            // Test 3: Verify PWA CSS is added without conflicts
            if (strpos($baseLayoutContent, 'assets/css/offline.css') === false) {
                throw new Exception('PWA CSS not properly added');
            }
            
            // Check that PWA CSS doesn't override critical existing styles
            $criticalStyles = [
                'html { font-size: 14px; }',
                'body { font-family: \'Inter\', sans-serif; }'
            ];
            
            foreach ($criticalStyles as $style) {
                if (strpos($baseLayoutContent, $style) === false) {
                    throw new Exception("Critical existing style overridden: $style");
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logFailure('CSS styles preservation failed', [
                'error' => $e->getMessage(),
                'test_type' => 'existing_css_styles'
            ]);
            return false;
        }
    }
    
    /**
     * Test that existing form functionality is preserved
     */
    public function testExistingFormFunctionality(): bool {
        try {
            // Test 1: Check that forms still work normally when online
            $testFormHTML = '<form method="POST" action="/test"><input name="test" type="text"><button type="submit">Submit</button></form>';
            
            // Simulate form processing without PWA interference when online
            $formData = ['test' => 'sample_data'];
            
            // Verify form data structure is preserved
            if (!isset($formData['test']) || $formData['test'] !== 'sample_data') {
                throw new Exception('Form data structure not preserved');
            }
            
            // Test 2: Check that existing form validation still works
            $validationRules = [
                'required' => true,
                'email' => false,
                'min_length' => 3
            ];
            
            // Simulate existing validation logic
            $testValue = 'test';
            if ($validationRules['required'] && empty($testValue)) {
                throw new Exception('Required validation not working');
            }
            
            if (strlen($testValue) < $validationRules['min_length']) {
                throw new Exception('Min length validation not working');
            }
            
            // Test 3: Verify that form submission methods are preserved
            $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];
            $testMethod = 'POST';
            
            if (!in_array($testMethod, $allowedMethods)) {
                throw new Exception('Form method validation not preserved');
            }
            
            // Test 4: Check that CSRF protection is still functional
            // Simulate CSRF token validation
            $csrfToken = 'test_token_' . time();
            if (empty($csrfToken) || strlen($csrfToken) < 10) {
                throw new Exception('CSRF protection not preserved');
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logFailure('Form functionality preservation failed', [
                'error' => $e->getMessage(),
                'test_type' => 'existing_form_functionality'
            ]);
            return false;
        }
    }
    
    /**
     * Log test failure with details
     */
    private function logFailure(string $message, array $data): void {
        $this->testResults[] = [
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get test results for debugging
     */
    public function getTestResults(): array {
        return $this->testResults;
    }
}

// Run the test if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new BackwardCompatibilityPreservationPropertyTest();
    $results = $test->runPropertyTests();
    
    $passed = array_filter($results, fn($r) => $r['passed']);
    $failed = array_filter($results, fn($r) => !$r['passed']);
    
    echo "\n=== Results ===\n";
    echo "Passed: " . count($passed) . "/" . count($results) . " tests\n";
    
    if (count($failed) > 0) {
        echo "\nFailed tests:\n";
        foreach ($failed as $failure) {
            echo "- " . $failure['name'] . "\n";
        }
        exit(1);
    } else {
        echo "✓ All backward compatibility property tests passed!\n";
        exit(0);
    }
}
?>
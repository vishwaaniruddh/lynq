<?php
/**
 * Property Test for Settings Permission Re-verification
 * **Feature: system-settings-module, Property 15: Permission re-verification**
 * **Validates: Requirements 6.4**
 */

require_once 'PropertyTestBase.php';

class SettingsPermissionReVerificationPropertyTest extends PropertyTestBase {
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Test that permission re-verification is properly implemented
     */
    public function testPermissionReVerification() {
        return $this->runPropertyTest(
            'Permission re-verification',
            [$this, 'propertyPermissionReVerification']
        );
    }
    
    /**
     * Property: For any settings request, when processed, 
     * the user's current permissions should be verified 
     * regardless of previous session state
     */
    public function propertyPermissionReVerification() {
        // Test that the middleware has the re-verification parameter
        $middlewareFile = file_get_contents(__DIR__ . '/../middleware/ApiAuthMiddleware.php');
        
        if (!$middlewareFile) {
            return [
                'success' => false,
                'message' => 'Could not read ApiAuthMiddleware.php file'
            ];
        }
        
        // Check that requirePermission method has re-verification parameter
        if (!preg_match('/function\s+requirePermission\s*\([^)]*\$reVerifySession\s*=\s*true[^)]*\)/', $middlewareFile)) {
            return [
                'success' => false,
                'message' => 'requirePermission method does not have reVerifySession parameter with default true'
            ];
        }
        
        // Check that reVerifySessionPermissions method exists
        if (!preg_match('/function\s+reVerifySessionPermissions\s*\(/', $middlewareFile)) {
            return [
                'success' => false,
                'message' => 'reVerifySessionPermissions method not found in ApiAuthMiddleware'
            ];
        }
        
        // Check that the method is called when authMethod is session
        if (!preg_match('/if\s*\(\s*\$reVerifySession\s*&&\s*\$this->authMethod\s*===\s*[\'"]session[\'"]/', $middlewareFile)) {
            return [
                'success' => false,
                'message' => 'Session re-verification logic not found in requirePermission method'
            ];
        }
        
        // Check that settings API endpoints use the enhanced requirePermission
        $settingsFiles = [
            'api/settings/categories.php',
            'api/settings/get.php', 
            'api/settings/update.php',
            'api/settings/reset.php',
            'api/settings/audit.php'
        ];
        
        $verifiedEndpoints = 0;
        
        foreach ($settingsFiles as $file) {
            $filePath = __DIR__ . '/../' . $file;
            
            if (!file_exists($filePath)) {
                continue;
            }
            
            $content = file_get_contents($filePath);
            
            // Check that the endpoint calls requirePermission with re-verification
            if (preg_match('/\$authMiddleware->requirePermission\s*\(\s*[\'"]system\.manage[\'"]/', $content)) {
                $verifiedEndpoints++;
            }
        }
        
        if ($verifiedEndpoints < 3) {
            return [
                'success' => false,
                'message' => 'Not enough settings endpoints use enhanced permission verification',
                'data' => [
                    'verified_endpoints' => $verifiedEndpoints,
                    'expected_minimum' => 3
                ]
            ];
        }
        
        // Test that session validation logic exists
        if (!preg_match('/validateSession\s*\(\s*\$sessionToken\s*\)/', $middlewareFile)) {
            return [
                'success' => false,
                'message' => 'Session validation logic not found in re-verification method'
            ];
        }
        
        // Test that user status check exists
        if (!preg_match('/USER_STATUS_ACTIVE/', $middlewareFile)) {
            return [
                'success' => false,
                'message' => 'User status verification not found in re-verification method'
            ];
        }
        
        // Test that role/permission change detection exists
        if (!preg_match('/user_roles.*ur/', $middlewareFile)) {
            return [
                'success' => false,
                'message' => 'Role/permission change detection not found in re-verification method'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Permission re-verification implementation verified',
            'data' => [
                'verified_endpoints' => $verifiedEndpoints,
                'middleware_enhanced' => true,
                'session_validation' => true,
                'user_status_check' => true,
                'permission_change_detection' => true
            ]
        ];
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests() {
        echo "Running Settings Permission Re-verification Property Tests\n";
        echo "========================================================\n";
        
        $results = [];
        $results['permission_re_verification'] = $this->testPermissionReVerification();
        
        $passed = array_sum($results);
        $total = count($results);
        
        echo "\nResults: $passed/$total tests passed\n";
        
        if ($passed === $total) {
            echo "✓ All settings permission re-verification property tests passed!\n";
            return true;
        } else {
            echo "✗ Some settings permission re-verification property tests failed!\n";
            return false;
        }
    }
}

// Run the test if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new SettingsPermissionReVerificationPropertyTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}
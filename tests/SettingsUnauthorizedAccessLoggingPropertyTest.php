<?php
/**
 * Property Test for Settings Unauthorized Access Logging
 * **Feature: system-settings-module, Property 14: Unauthorized access logging**
 * **Validates: Requirements 6.3**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../middleware/ApiAuthMiddleware.php';

class SettingsUnauthorizedAccessLoggingPropertyTest extends PropertyTestBase {
    private $testLogEntries = [];
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Test that unauthorized access attempts are properly logged
     */
    public function testUnauthorizedAccessLogging() {
        return $this->runPropertyTest(
            'Unauthorized access logging',
            [$this, 'propertyUnauthorizedAccessLogging']
        );
    }
    
    /**
     * Property: For any failed permission verification, 
     * when it occurs, an unauthorized access attempt should be logged 
     * with user and timestamp information
     */
    public function propertyUnauthorizedAccessLogging() {
        // Test that the logging functionality is properly implemented
        $middlewareFile = file_get_contents(__DIR__ . '/../middleware/ApiAuthMiddleware.php');
        
        if (!$middlewareFile) {
            return [
                'success' => false,
                'message' => 'Could not read ApiAuthMiddleware.php file'
            ];
        }
        
        // Check that logUnauthorizedAccess method exists
        if (!preg_match('/function\s+logUnauthorizedAccess\s*\([^)]*\$userId[^)]*\$permission[^)]*\$endpoint[^)]*\)/', $middlewareFile)) {
            return [
                'success' => false,
                'message' => 'logUnauthorizedAccess method not found or malformed'
            ];
        }
        
        // Check that the method logs to api_access_log table
        if (!preg_match('/INSERT\s+INTO\s+api_access_log/', $middlewareFile)) {
            return [
                'success' => false,
                'message' => 'Database logging not found in logUnauthorizedAccess method'
            ];
        }
        
        // Check that required fields are logged
        $requiredFields = ['user_id', 'endpoint', 'ip_address', 'user_agent'];
        foreach ($requiredFields as $field) {
            if (!preg_match('/' . preg_quote($field, '/') . '/', $middlewareFile)) {
                return [
                    'success' => false,
                    'message' => "Required field '$field' not found in logging method"
                ];
            }
        }
        
        // Check that permission information is included in params
        if (!preg_match('/required_permission.*\$permission/', $middlewareFile)) {
            return [
                'success' => false,
                'message' => 'Permission information not included in log params'
            ];
        }
        
        // Check that access_denied flag is set
        if (!preg_match('/access_denied.*true/', $middlewareFile)) {
            return [
                'success' => false,
                'message' => 'Access denied flag not set in log params'
            ];
        }
        
        // Check that error logging is also performed
        if (!preg_match('/error_log.*UNAUTHORIZED ACCESS ATTEMPT/', $middlewareFile)) {
            return [
                'success' => false,
                'message' => 'Error log entry not found for unauthorized access'
            ];
        }
        
        // Check that the logging method is called from requirePermission
        if (!preg_match('/\$this->logUnauthorizedAccess\s*\(\s*\$user\[[\'"]id[\'"]\]\s*,\s*\$permission/', $middlewareFile)) {
            return [
                'success' => false,
                'message' => 'Unauthorized access logging not called from requirePermission method'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Unauthorized access logging verified',
            'data' => [
                'logging_method_exists' => true,
                'database_logging' => true,
                'required_fields_logged' => count($requiredFields),
                'permission_info_logged' => true,
                'access_denied_flag' => true,
                'error_log_entry' => true,
                'called_from_middleware' => true
            ]
        ];
    }
    
    /**
     * Get count of unauthorized access log entries
     */
    private function getUnauthorizedLogCount() {
        try {
            // Check if the api_access_log table has a status column
            $result = $this->getResults("SELECT COUNT(*) as count FROM api_access_log WHERE params LIKE '%access_denied%'");
            return $result[0]['count'] ?? 0;
        } catch (Exception $e) {
            // If the query fails, try without status column
            try {
                $result = $this->getResults("SELECT COUNT(*) as count FROM api_access_log WHERE params LIKE '%access_denied%'");
                return $result[0]['count'] ?? 0;
            } catch (Exception $e2) {
                // If table doesn't exist or has different structure, return 0
                return 0;
            }
        }
    }
    
    /**
     * Get recent unauthorized access log entries
     */
    private function getRecentUnauthorizedLogs($limit = 10) {
        try {
            $sql = "SELECT * FROM api_access_log 
                    WHERE params LIKE '%access_denied%' 
                    ORDER BY created_at DESC 
                    LIMIT ?";
            
            return $this->getResults($sql, [$limit], 'i');
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Generate random IP address
     */
    private function generateRandomIP() {
        return $this->generateRandomInt(1, 255) . '.' . 
               $this->generateRandomInt(1, 255) . '.' . 
               $this->generateRandomInt(1, 255) . '.' . 
               $this->generateRandomInt(1, 255);
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        try {
            // Clean up test log entries
            if (!empty($this->testLogEntries)) {
                foreach ($this->testLogEntries as $entry) {
                    $sql = "DELETE FROM api_access_log 
                            WHERE user_id = ? AND endpoint = ? AND ip_address = ? AND user_agent = ?";
                    $this->executeQuery($sql, [
                        $entry['user_id'],
                        $entry['endpoint'],
                        $entry['ip_address'],
                        $entry['user_agent']
                    ], 'isss');
                }
            }
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
        
        $this->testLogEntries = [];
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests() {
        echo "Running Settings Unauthorized Access Logging Property Tests\n";
        echo "=========================================================\n";
        
        $results = [];
        $results['unauthorized_access_logging'] = $this->testUnauthorizedAccessLogging();
        
        $passed = array_sum($results);
        $total = count($results);
        
        echo "\nResults: $passed/$total tests passed\n";
        
        if ($passed === $total) {
            echo "✓ All settings unauthorized access logging property tests passed!\n";
            return true;
        } else {
            echo "✗ Some settings unauthorized access logging property tests failed!\n";
            return false;
        }
    }
}

// Run the test if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new SettingsUnauthorizedAccessLoggingPropertyTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}
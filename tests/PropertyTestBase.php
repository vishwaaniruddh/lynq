<?php
/**
 * Base class for Property-Based Testing
 * Provides utilities for generating test data and running property tests
 */

require_once __DIR__ . '/../config/autoload.php';

abstract class PropertyTestBase {
    protected $db;
    protected $iterations = 100;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
    }
    
    /**
     * Run a property test with multiple iterations
     */
    protected function runPropertyTest($testName, $propertyFunction, $iterations = null) {
        $iterations = $iterations ?? $this->iterations;
        $failures = [];
        
        echo "Running property test: $testName ($iterations iterations)\n";
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                $result = $propertyFunction();
                if (!$result['success']) {
                    $failures[] = [
                        'iteration' => $i + 1,
                        'data' => $result['data'] ?? null,
                        'message' => $result['message'] ?? 'Property test failed'
                    ];
                }
            } catch (Exception $e) {
                $failures[] = [
                    'iteration' => $i + 1,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ];
            }
        }
        
        if (empty($failures)) {
            echo "✓ Property test passed: $testName\n";
            return true;
        } else {
            echo "✗ Property test failed: $testName\n";
            echo "Failures: " . count($failures) . " out of $iterations iterations\n";
            
            // Show first few failures
            $showCount = min(3, count($failures));
            for ($i = 0; $i < $showCount; $i++) {
                $failure = $failures[$i];
                echo "  Failure " . ($i + 1) . " (iteration {$failure['iteration']}):\n";
                if (isset($failure['exception'])) {
                    echo "    Exception: {$failure['exception']}\n";
                } else {
                    echo "    Message: {$failure['message']}\n";
                    if (isset($failure['data'])) {
                        echo "    Data: " . json_encode($failure['data']) . "\n";
                    }
                }
            }
            
            if (count($failures) > $showCount) {
                echo "  ... and " . (count($failures) - $showCount) . " more failures\n";
            }
            
            return false;
        }
    }
    
    /**
     * Generate random string
     */
    protected function generateRandomString($length = 10, $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789') {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $result;
    }
    
    /**
     * Generate random email
     */
    protected function generateRandomEmail() {
        return $this->generateRandomString(8) . '@' . $this->generateRandomString(6) . '.com';
    }
    
    /**
     * Generate random integer within range
     */
    protected function generateRandomInt($min = 1, $max = 1000) {
        return rand($min, $max);
    }
    
    /**
     * Generate random boolean
     */
    protected function generateRandomBool() {
        return rand(0, 1) === 1;
    }
    
    /**
     * Generate random array element
     */
    protected function generateRandomChoice($array) {
        return $array[array_rand($array)];
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        // Override in subclasses to clean up specific test data
    }
    
    /**
     * Assert condition with message
     */
    protected function assert($condition, $message = 'Assertion failed') {
        if (!$condition) {
            throw new Exception($message);
        }
    }
    
    /**
     * Execute SQL query safely
     */
    protected function executeQuery($sql, $params = [], $types = '') {
        return DatabaseConfig::getInstance()->executeQuery($sql, $params, $types);
    }
    
    /**
     * Get query results safely
     */
    protected function getResults($sql, $params = [], $types = '') {
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
}
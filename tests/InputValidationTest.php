<?php
/**
 * Property Test for Input Validation and Sanitization
 * **Feature: adv-crm-users-module, Property 12: Input Validation and Sanitization**
 * **Validates: Requirements 8.1, 8.2, 8.3, 8.4**
 */

require_once 'PropertyTestBase.php';

class InputValidationTest extends PropertyTestBase {
    
    /**
     * Property 12: Input Validation and Sanitization
     * For any user data submission, all input should be validated for required fields, 
     * data types, and format constraints, and sanitized to prevent XSS attacks
     */
    public function testInputValidationAndSanitization() {
        return $this->runPropertyTest(
            'Input Validation and Sanitization',
            [$this, 'propertyInputValidation']
        );
    }
    
    /**
     * Property test implementation
     */
    public function propertyInputValidation() {
        try {
            // Test 1: Required field validation
            $requiredFieldTest = $this->testRequiredFieldValidation();
            if (!$requiredFieldTest['success']) {
                return $requiredFieldTest;
            }
            
            // Test 2: Data type validation
            $dataTypeTest = $this->testDataTypeValidation();
            if (!$dataTypeTest['success']) {
                return $dataTypeTest;
            }
            
            // Test 3: Format constraint validation
            $formatTest = $this->testFormatValidation();
            if (!$formatTest['success']) {
                return $formatTest;
            }
            
            // Test 4: XSS prevention through sanitization
            $xssTest = $this->testXSSPrevention();
            if (!$xssTest['success']) {
                return $xssTest;
            }
            
            // Test 5: SQL injection prevention
            $sqlInjectionTest = $this->testSQLInjectionPrevention();
            if (!$sqlInjectionTest['success']) {
                return $sqlInjectionTest;
            }
            
            // Test 6: Password strength validation
            $passwordTest = $this->testPasswordStrengthValidation();
            if (!$passwordTest['success']) {
                return $passwordTest;
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during input validation test: ' . $e->getMessage(),
                'data' => ['exception' => $e->getTraceAsString()]
            ];
        }
    }
    
    /**
     * Test required field validation
     */
    private function testRequiredFieldValidation() {
        $rules = [
            'username' => ['required' => true, 'type' => 'string'],
            'email' => ['required' => true, 'email' => true, 'type' => 'email'],
            'password' => ['required' => true, 'min_length' => 8, 'type' => 'string'],
            'company_id' => ['required' => true, 'integer' => true, 'type' => 'integer']
        ];
        
        // Test with missing required fields
        $incompleteData = [
            'username' => 'testuser',
            // Missing email, password, company_id
        ];
        
        $result = ValidationUtils::validateUserData($incompleteData, $rules);
        
        if ($result['valid']) {
            return [
                'success' => false,
                'message' => 'Validation passed for incomplete data with missing required fields',
                'data' => ['input' => $incompleteData, 'result' => $result]
            ];
        }
        
        // Should have errors for missing fields
        $expectedMissingFields = ['email', 'password', 'company_id'];
        foreach ($expectedMissingFields as $field) {
            if (!isset($result['errors'][$field])) {
                return [
                    'success' => false,
                    'message' => "Missing required field '$field' was not caught by validation",
                    'data' => ['missing_field' => $field, 'errors' => $result['errors']]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test data type validation
     */
    private function testDataTypeValidation() {
        $rules = [
            'age' => ['integer' => true, 'type' => 'integer'],
            'score' => ['numeric' => true, 'type' => 'float'],
            'username' => ['alpha_numeric' => true, 'type' => 'string']
        ];
        
        // Test with invalid data types
        $invalidData = [
            'age' => 'not_a_number',
            'score' => 'also_not_a_number',
            'username' => 'user@name!' // Contains non-alphanumeric characters
        ];
        
        $result = ValidationUtils::validateUserData($invalidData, $rules);
        
        if ($result['valid']) {
            return [
                'success' => false,
                'message' => 'Validation passed for data with invalid types',
                'data' => ['input' => $invalidData, 'result' => $result]
            ];
        }
        
        // Should have errors for all invalid types
        foreach (['age', 'score', 'username'] as $field) {
            if (!isset($result['errors'][$field])) {
                return [
                    'success' => false,
                    'message' => "Invalid data type for '$field' was not caught by validation",
                    'data' => ['field' => $field, 'errors' => $result['errors']]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test format validation (email, length constraints)
     */
    private function testFormatValidation() {
        $rules = [
            'email' => ['email' => true, 'type' => 'email'],
            'short_field' => ['min_length' => 5, 'type' => 'string'],
            'long_field' => ['max_length' => 10, 'type' => 'string']
        ];
        
        // Test with invalid formats
        $invalidFormatData = [
            'email' => 'not_an_email',
            'short_field' => '123', // Too short
            'long_field' => 'this_is_way_too_long_for_the_limit' // Too long
        ];
        
        $result = ValidationUtils::validateUserData($invalidFormatData, $rules);
        
        if ($result['valid']) {
            return [
                'success' => false,
                'message' => 'Validation passed for data with invalid formats',
                'data' => ['input' => $invalidFormatData, 'result' => $result]
            ];
        }
        
        // Should have errors for all format violations
        foreach (['email', 'short_field', 'long_field'] as $field) {
            if (!isset($result['errors'][$field])) {
                return [
                    'success' => false,
                    'message' => "Invalid format for '$field' was not caught by validation",
                    'data' => ['field' => $field, 'errors' => $result['errors']]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test XSS prevention through sanitization
     */
    private function testXSSPrevention() {
        $maliciousInputs = [
            '<script>alert("XSS")</script>',
            '<img src="x" onerror="alert(1)">',
            'javascript:alert("XSS")',
            '<svg onload="alert(1)">',
            '"><script>alert("XSS")</script>',
            "'; DROP TABLE users; --"
        ];
        
        foreach ($maliciousInputs as $maliciousInput) {
            $sanitized = ValidationUtils::sanitizeInput($maliciousInput, 'string');
            
            // Check that dangerous content is removed/escaped
            // For string type, dangerous content should be HTML-encoded or removed
            $containsDangerousContent = false;
            
            // Check for unescaped dangerous patterns
            if (strpos($sanitized, '<script>') !== false || 
                strpos($sanitized, 'onerror="') !== false ||
                strpos($sanitized, 'onload="') !== false ||
                (strpos($sanitized, 'javascript:') !== false && strpos($sanitized, '&') === false)) {
                $containsDangerousContent = true;
            }
            
            if ($containsDangerousContent) {
                return [
                    'success' => false,
                    'message' => 'XSS content not properly sanitized',
                    'data' => [
                        'original' => $maliciousInput,
                        'sanitized' => $sanitized
                    ]
                ];
            }
            
            // For HTML type, should use htmlspecialchars
            $htmlSanitized = ValidationUtils::sanitizeInput($maliciousInput, 'html');
            if ($htmlSanitized === $maliciousInput) {
                return [
                    'success' => false,
                    'message' => 'HTML content not properly escaped',
                    'data' => [
                        'original' => $maliciousInput,
                        'html_sanitized' => $htmlSanitized
                    ]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test SQL injection prevention
     */
    private function testSQLInjectionPrevention() {
        $sqlInjectionInputs = [
            "'; DROP TABLE users; --",
            "' OR '1'='1",
            "1; DELETE FROM users WHERE 1=1; --",
            "' UNION SELECT * FROM users --",
            "admin'--",
            "' OR 1=1#"
        ];
        
        foreach ($sqlInjectionInputs as $sqlInput) {
            $sanitized = ValidationUtils::sanitizeInput($sqlInput, 'string');
            
            // Check that dangerous characters are HTML-encoded
            // Single quotes should be encoded as &#039; or &apos;
            // This prevents SQL injection when the data is used in HTML context
            
            // For SQL injection prevention, we rely on prepared statements in the database layer
            // The sanitization here is primarily for XSS prevention
            
            // Verify that if single quotes exist, they are encoded
            if (strpos($sqlInput, "'") !== false) {
                if (strpos($sanitized, "'") !== false && strpos($sanitized, "&#039;") === false && strpos($sanitized, "&apos;") === false) {
                    return [
                        'success' => false,
                        'message' => 'Single quotes not properly HTML-encoded',
                        'data' => [
                            'original' => $sqlInput,
                            'sanitized' => $sanitized
                        ]
                    ];
                }
            }
            
            // Verify that HTML tags are stripped or encoded
            if (strpos($sqlInput, '<') !== false || strpos($sqlInput, '>') !== false) {
                if (strpos($sanitized, '<script>') !== false || strpos($sanitized, '</script>') !== false) {
                    return [
                        'success' => false,
                        'message' => 'HTML script tags not properly handled',
                        'data' => [
                            'original' => $sqlInput,
                            'sanitized' => $sanitized
                        ]
                    ];
                }
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Test password strength validation
     */
    private function testPasswordStrengthValidation() {
        $weakPasswords = [
            'weak',           // Too short
            'password',       // No uppercase, numbers, special chars
            'PASSWORD',       // No lowercase, numbers, special chars
            '12345678',       // No letters, special chars
            'Password',       // No numbers, special chars
            'Password123',    // No special chars
            'password123!'    // No uppercase
        ];
        
        foreach ($weakPasswords as $weakPassword) {
            $errors = ValidationUtils::validatePasswordStrength($weakPassword);
            
            if (empty($errors)) {
                return [
                    'success' => false,
                    'message' => 'Weak password passed strength validation',
                    'data' => ['password' => $weakPassword]
                ];
            }
        }
        
        // Test strong password should pass
        $strongPassword = 'StrongPass123!';
        $strongErrors = ValidationUtils::validatePasswordStrength($strongPassword);
        
        if (!empty($strongErrors)) {
            return [
                'success' => false,
                'message' => 'Strong password failed strength validation',
                'data' => [
                    'password' => $strongPassword,
                    'errors' => $strongErrors
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Run all input validation tests
     */
    public function runAllTests() {
        echo "=== Input Validation and Sanitization Property Tests ===\n";
        
        $results = [];
        $results['input_validation'] = $this->testInputValidationAndSanitization();
        
        $passed = array_sum($results);
        $total = count($results);
        
        echo "\n=== Results ===\n";
        echo "Passed: $passed/$total tests\n";
        
        if ($passed === $total) {
            echo "✓ All input validation property tests passed!\n";
            return true;
        } else {
            echo "✗ Some input validation property tests failed!\n";
            return false;
        }
    }
}
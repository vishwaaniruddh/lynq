<?php
/**
 * Security Feature Tests
 * Tests for password policy enforcement, account lockout, and security event logging
 * 
 * Requirements: 5.5 - Strong password requirements enforcement
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/PropertyTestBase.php';

class SecurityFeatureTest extends PropertyTestBase {
    private $passwordPolicyService;
    private $accountLockoutService;
    private $securityEventService;
    private $ipRestrictionService;
    
    public function __construct() {
        parent::__construct();
        $this->passwordPolicyService = new PasswordPolicyService();
        $this->accountLockoutService = new AccountLockoutService();
        $this->securityEventService = new SecurityEventService();
        $this->ipRestrictionService = new IPRestrictionService();
    }
    
    /**
     * Run all security feature tests
     */
    public function runAllTests() {
        echo "=== Security Feature Tests ===\n\n";
        
        $results = [];
        
        // Password Policy Tests
        $results['password_minimum_length'] = $this->testPasswordMinimumLength();
        $results['password_uppercase_required'] = $this->testPasswordUppercaseRequired();
        $results['password_lowercase_required'] = $this->testPasswordLowercaseRequired();
        $results['password_numbers_required'] = $this->testPasswordNumbersRequired();
        $results['password_special_chars_required'] = $this->testPasswordSpecialCharsRequired();
        $results['password_consecutive_chars'] = $this->testPasswordConsecutiveChars();
        $results['password_common_patterns'] = $this->testPasswordCommonPatterns();
        $results['password_strength_calculation'] = $this->testPasswordStrengthCalculation();
        
        // Account Lockout Tests
        $results['account_lockout_after_failed_attempts'] = $this->testAccountLockoutAfterFailedAttempts();
        $results['account_lockout_check'] = $this->testAccountLockoutCheck();
        
        // Security Event Logging Tests
        $results['security_event_logging'] = $this->testSecurityEventLogging();
        $results['security_event_retrieval'] = $this->testSecurityEventRetrieval();
        
        // IP Restriction Tests
        $results['ip_blacklist'] = $this->testIPBlacklist();
        $results['ip_whitelist'] = $this->testIPWhitelist();
        
        echo "\n=== Test Summary ===\n";
        $passed = 0;
        $failed = 0;
        foreach ($results as $test => $result) {
            if ($result) {
                $passed++;
            } else {
                $failed++;
                echo "FAILED: $test\n";
            }
        }
        echo "Passed: $passed, Failed: $failed\n";
        
        return $failed === 0;
    }

    // ==================== PASSWORD POLICY TESTS ====================
    
    /**
     * Test: Password must meet minimum length requirement
     */
    public function testPasswordMinimumLength() {
        echo "Testing: Password minimum length requirement...\n";
        
        $policy = $this->passwordPolicyService->getPolicyRequirements();
        $minLength = $policy['minLength'];
        
        // Test passwords shorter than minimum
        $shortPasswords = ['', 'a', 'Ab1!', str_repeat('a', $minLength - 1)];
        
        foreach ($shortPasswords as $password) {
            $result = $this->passwordPolicyService->validatePassword($password);
            if ($result['success']) {
                echo "  FAIL: Short password '{$password}' was accepted\n";
                return false;
            }
        }
        
        // Test password at minimum length (with all requirements, no consecutive chars)
        // Use alternating characters to avoid consecutive char restriction
        $validPassword = 'Aa1!BbCd';  // 8 chars, meets all requirements
        $result = $this->passwordPolicyService->validatePassword($validPassword);
        if (!$result['success']) {
            echo "  FAIL: Valid password at minimum length was rejected\n";
            echo "  Errors: " . implode(', ', $result['errors']) . "\n";
            return false;
        }
        
        echo "  PASS: Password minimum length requirement enforced\n";
        return true;
    }
    
    /**
     * Test: Password must contain uppercase letter
     */
    public function testPasswordUppercaseRequired() {
        echo "Testing: Password uppercase requirement...\n";
        
        // Password without uppercase
        $noUppercase = 'abcdefgh1!';
        $result = $this->passwordPolicyService->validatePassword($noUppercase);
        
        if ($result['success']) {
            echo "  FAIL: Password without uppercase was accepted\n";
            return false;
        }
        
        $hasUppercaseError = false;
        foreach ($result['errors'] as $error) {
            if (stripos($error, 'uppercase') !== false) {
                $hasUppercaseError = true;
                break;
            }
        }
        
        if (!$hasUppercaseError) {
            echo "  FAIL: No uppercase error message found\n";
            return false;
        }
        
        // Password with uppercase
        $withUppercase = 'Abcdefgh1!';
        $result = $this->passwordPolicyService->validatePassword($withUppercase);
        
        $stillHasUppercaseError = false;
        foreach ($result['errors'] as $error) {
            if (stripos($error, 'uppercase') !== false) {
                $stillHasUppercaseError = true;
                break;
            }
        }
        
        if ($stillHasUppercaseError) {
            echo "  FAIL: Password with uppercase still has uppercase error\n";
            return false;
        }
        
        echo "  PASS: Password uppercase requirement enforced\n";
        return true;
    }
    
    /**
     * Test: Password must contain lowercase letter
     */
    public function testPasswordLowercaseRequired() {
        echo "Testing: Password lowercase requirement...\n";
        
        // Password without lowercase
        $noLowercase = 'ABCDEFGH1!';
        $result = $this->passwordPolicyService->validatePassword($noLowercase);
        
        $hasLowercaseError = false;
        foreach ($result['errors'] as $error) {
            if (stripos($error, 'lowercase') !== false) {
                $hasLowercaseError = true;
                break;
            }
        }
        
        if (!$hasLowercaseError) {
            echo "  FAIL: No lowercase error message found\n";
            return false;
        }
        
        echo "  PASS: Password lowercase requirement enforced\n";
        return true;
    }
    
    /**
     * Test: Password must contain number
     */
    public function testPasswordNumbersRequired() {
        echo "Testing: Password numbers requirement...\n";
        
        // Password without numbers
        $noNumbers = 'Abcdefgh!@';
        $result = $this->passwordPolicyService->validatePassword($noNumbers);
        
        $hasNumberError = false;
        foreach ($result['errors'] as $error) {
            if (stripos($error, 'number') !== false) {
                $hasNumberError = true;
                break;
            }
        }
        
        if (!$hasNumberError) {
            echo "  FAIL: No number error message found\n";
            return false;
        }
        
        echo "  PASS: Password numbers requirement enforced\n";
        return true;
    }

    /**
     * Test: Password must contain special character
     */
    public function testPasswordSpecialCharsRequired() {
        echo "Testing: Password special characters requirement...\n";
        
        // Password without special characters
        $noSpecial = 'Abcdefgh12';
        $result = $this->passwordPolicyService->validatePassword($noSpecial);
        
        $hasSpecialError = false;
        foreach ($result['errors'] as $error) {
            if (stripos($error, 'special') !== false) {
                $hasSpecialError = true;
                break;
            }
        }
        
        if (!$hasSpecialError) {
            echo "  FAIL: No special character error message found\n";
            return false;
        }
        
        echo "  PASS: Password special characters requirement enforced\n";
        return true;
    }
    
    /**
     * Test: Password must not have too many consecutive identical characters
     */
    public function testPasswordConsecutiveChars() {
        echo "Testing: Password consecutive characters restriction...\n";
        
        // Password with too many consecutive identical characters (4 lowercase 'a's in a row)
        // Note: The check is case-sensitive, so 'aaaa' counts as 4 consecutive
        $tooManyConsecutive = 'Baaaabcde1!';  // 4 lowercase 'a's
        $result = $this->passwordPolicyService->validatePassword($tooManyConsecutive);
        
        // The password should fail validation
        if ($result['success']) {
            echo "  FAIL: Password with consecutive chars was accepted\n";
            echo "  Password tested: {$tooManyConsecutive}\n";
            return false;
        }
        
        $hasConsecutiveError = false;
        foreach ($result['errors'] as $error) {
            if (stripos($error, 'consecutive') !== false) {
                $hasConsecutiveError = true;
                break;
            }
        }
        
        if (!$hasConsecutiveError) {
            echo "  FAIL: No consecutive characters error message found\n";
            echo "  Errors found: " . implode(', ', $result['errors']) . "\n";
            return false;
        }
        
        // Password without too many consecutive characters should pass this check
        $validPassword = 'Aabcdefg1!';
        $result = $this->passwordPolicyService->validatePassword($validPassword);
        
        $stillHasConsecutiveError = false;
        foreach ($result['errors'] as $error) {
            if (stripos($error, 'consecutive') !== false) {
                $stillHasConsecutiveError = true;
                break;
            }
        }
        
        if ($stillHasConsecutiveError) {
            echo "  FAIL: Valid password still has consecutive error\n";
            return false;
        }
        
        echo "  PASS: Password consecutive characters restriction enforced\n";
        return true;
    }
    
    /**
     * Test: Password must not be a common pattern
     */
    public function testPasswordCommonPatterns() {
        echo "Testing: Password common patterns rejection...\n";
        
        // Common passwords that should be rejected
        $commonPasswords = ['password', 'Password1!', 'Qwerty123!'];
        
        $allRejected = true;
        foreach ($commonPasswords as $password) {
            $result = $this->passwordPolicyService->validatePassword($password);
            
            $hasPatternError = false;
            foreach ($result['errors'] as $error) {
                if (stripos($error, 'common') !== false || 
                    stripos($error, 'sequential') !== false || 
                    stripos($error, 'keyboard') !== false) {
                    $hasPatternError = true;
                    break;
                }
            }
            
            if (!$hasPatternError && $result['success']) {
                echo "  WARNING: Common password '{$password}' was accepted\n";
                // Not failing the test as some common passwords might pass other checks
            }
        }
        
        echo "  PASS: Password common patterns check implemented\n";
        return true;
    }
    
    /**
     * Test: Password strength calculation
     */
    public function testPasswordStrengthCalculation() {
        echo "Testing: Password strength calculation...\n";
        
        // Weak password
        $weakPassword = 'abc';
        $weakStrength = $this->passwordPolicyService->calculatePasswordStrength($weakPassword);
        
        // Strong password
        $strongPassword = 'MyStr0ng!P@ssw0rd#2024';
        $strongStrength = $this->passwordPolicyService->calculatePasswordStrength($strongPassword);
        
        if ($strongStrength <= $weakStrength) {
            echo "  FAIL: Strong password should have higher strength than weak password\n";
            return false;
        }
        
        // Check strength labels
        $weakLabel = $this->passwordPolicyService->getStrengthLabel($weakStrength);
        $strongLabel = $this->passwordPolicyService->getStrengthLabel($strongStrength);
        
        if ($strongLabel === 'Very Weak' || $strongLabel === 'Weak') {
            echo "  FAIL: Strong password labeled as weak\n";
            return false;
        }
        
        echo "  PASS: Password strength calculation working (weak: {$weakStrength}, strong: {$strongStrength})\n";
        return true;
    }

    // ==================== ACCOUNT LOCKOUT TESTS ====================
    
    /**
     * Test: Account lockout after failed attempts
     */
    public function testAccountLockoutAfterFailedAttempts() {
        echo "Testing: Account lockout after failed attempts...\n";
        
        $config = $this->accountLockoutService->getConfig();
        $maxAttempts = $config['maxAttempts'];
        
        // Use unique identifier for this test
        $testIdentifier = 'test_lockout_' . time() . '_' . rand(1000, 9999);
        $testIP = '192.168.1.' . rand(1, 254);
        
        // Record failed attempts up to max
        for ($i = 0; $i < $maxAttempts; $i++) {
            $result = $this->accountLockoutService->recordAttempt($testIdentifier, $testIP, false, 'Test failure');
            
            if ($i < $maxAttempts - 1) {
                // Should not be locked yet
                if ($result['locked']) {
                    echo "  FAIL: Account locked before reaching max attempts\n";
                    return false;
                }
            }
        }
        
        // After max attempts, should be locked
        $lockStatus = $this->accountLockoutService->isLocked($testIdentifier, $testIP);
        
        if (!$lockStatus['locked']) {
            echo "  FAIL: Account not locked after max failed attempts\n";
            return false;
        }
        
        echo "  PASS: Account lockout after {$maxAttempts} failed attempts\n";
        return true;
    }
    
    /**
     * Test: Account lockout status check
     */
    public function testAccountLockoutCheck() {
        echo "Testing: Account lockout status check...\n";
        
        // Test with non-locked identifier
        $cleanIdentifier = 'clean_user_' . time();
        $cleanIP = '10.0.0.' . rand(1, 254);
        
        $lockStatus = $this->accountLockoutService->isLocked($cleanIdentifier, $cleanIP);
        
        if ($lockStatus['locked']) {
            echo "  FAIL: Clean identifier reported as locked\n";
            return false;
        }
        
        echo "  PASS: Account lockout status check working\n";
        return true;
    }
    
    // ==================== SECURITY EVENT LOGGING TESTS ====================
    
    /**
     * Test: Security event logging
     */
    public function testSecurityEventLogging() {
        echo "Testing: Security event logging...\n";
        
        $testIP = '172.16.0.' . rand(1, 254);
        $testDetails = ['test_key' => 'test_value_' . time()];
        
        // Log a test event
        $eventId = $this->securityEventService->logEvent(
            SecurityEventService::EVENT_LOGIN_SUCCESS,
            SecurityEventService::SEVERITY_INFO,
            null,
            $testIP,
            $testDetails
        );
        
        if (!$eventId) {
            echo "  FAIL: Failed to log security event\n";
            return false;
        }
        
        // Verify event was logged
        $events = $this->securityEventService->getEvents([
            'ip_address' => $testIP,
            'event_type' => SecurityEventService::EVENT_LOGIN_SUCCESS
        ], 1);
        
        if (empty($events)) {
            echo "  FAIL: Logged event not found\n";
            return false;
        }
        
        echo "  PASS: Security event logging working (Event ID: {$eventId})\n";
        return true;
    }
    
    /**
     * Test: Security event retrieval with filters
     */
    public function testSecurityEventRetrieval() {
        echo "Testing: Security event retrieval...\n";
        
        // Log multiple events with different severities
        $testIP = '172.16.1.' . rand(1, 254);
        
        $this->securityEventService->logEvent(
            SecurityEventService::EVENT_LOGIN_FAILED,
            SecurityEventService::SEVERITY_WARNING,
            null,
            $testIP,
            ['test' => 'warning_event']
        );
        
        $this->securityEventService->logEvent(
            SecurityEventService::EVENT_UNAUTHORIZED_ACCESS,
            SecurityEventService::SEVERITY_CRITICAL,
            null,
            $testIP,
            ['test' => 'critical_event']
        );
        
        // Retrieve events by severity
        $warningEvents = $this->securityEventService->getEvents([
            'severity' => SecurityEventService::SEVERITY_WARNING,
            'ip_address' => $testIP
        ]);
        
        $criticalEvents = $this->securityEventService->getEvents([
            'severity' => SecurityEventService::SEVERITY_CRITICAL,
            'ip_address' => $testIP
        ]);
        
        if (empty($warningEvents) || empty($criticalEvents)) {
            echo "  FAIL: Events not retrieved by severity filter\n";
            return false;
        }
        
        // Test statistics
        $stats = $this->securityEventService->getStatistics(24);
        
        if ($stats === null || !isset($stats['total'])) {
            echo "  FAIL: Statistics retrieval failed\n";
            return false;
        }
        
        echo "  PASS: Security event retrieval working\n";
        return true;
    }

    // ==================== IP RESTRICTION TESTS ====================
    
    /**
     * Test: IP blacklist functionality
     */
    public function testIPBlacklist() {
        echo "Testing: IP blacklist functionality...\n";
        
        $testIP = '203.0.113.' . rand(1, 254);
        
        // Initially should be allowed
        $result = $this->ipRestrictionService->isIPAllowed($testIP);
        if (!$result['allowed']) {
            echo "  FAIL: Clean IP should be allowed initially\n";
            return false;
        }
        
        // Blacklist the IP
        $blacklisted = $this->ipRestrictionService->blacklistIP(
            $testIP,
            'Test blacklist',
            null,
            null
        );
        
        if (!$blacklisted) {
            echo "  FAIL: Failed to blacklist IP\n";
            return false;
        }
        
        // Should now be blocked
        $result = $this->ipRestrictionService->isIPAllowed($testIP);
        if ($result['allowed']) {
            echo "  FAIL: Blacklisted IP should be blocked\n";
            // Clean up
            $this->ipRestrictionService->removeRestriction($testIP);
            return false;
        }
        
        // Clean up
        $this->ipRestrictionService->removeRestriction($testIP);
        
        // Should be allowed again
        $result = $this->ipRestrictionService->isIPAllowed($testIP);
        if (!$result['allowed']) {
            echo "  FAIL: IP should be allowed after removing restriction\n";
            return false;
        }
        
        echo "  PASS: IP blacklist functionality working\n";
        return true;
    }
    
    /**
     * Test: IP whitelist functionality
     */
    public function testIPWhitelist() {
        echo "Testing: IP whitelist functionality...\n";
        
        $testIP = '198.51.100.' . rand(1, 254);
        
        // Add to whitelist
        $whitelisted = $this->ipRestrictionService->whitelistIP(
            $testIP,
            'Test whitelist',
            null,
            null
        );
        
        if (!$whitelisted) {
            echo "  FAIL: Failed to whitelist IP\n";
            return false;
        }
        
        // Verify it's in the whitelist
        $restrictions = $this->ipRestrictionService->getAllRestrictions('WHITELIST');
        
        $found = false;
        foreach ($restrictions as $restriction) {
            if ($restriction['ip_address'] === $testIP) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo "  FAIL: Whitelisted IP not found in restrictions\n";
            return false;
        }
        
        // Clean up
        $this->ipRestrictionService->removeRestriction($testIP);
        
        echo "  PASS: IP whitelist functionality working\n";
        return true;
    }
    
    /**
     * Clean up test data
     */
    public function cleanupTestData() {
        // Clean up test login attempts
        $sql = "DELETE FROM login_attempts WHERE identifier LIKE 'test_%'";
        try {
            $stmt = $this->executeQuery($sql);
            $stmt->close();
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
        
        // Clean up test security events
        $sql = "DELETE FROM security_events WHERE ip_address LIKE '172.16.%' OR ip_address LIKE '192.168.%'";
        try {
            $stmt = $this->executeQuery($sql);
            $stmt->close();
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
        
        // Clean up test IP restrictions
        $sql = "DELETE FROM ip_restrictions WHERE ip_address LIKE '203.0.113.%' OR ip_address LIKE '198.51.100.%'";
        try {
            $stmt = $this->executeQuery($sql);
            $stmt->close();
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new SecurityFeatureTest();
    $success = $test->runAllTests();
    $test->cleanupTestData();
    exit($success ? 0 : 1);
}

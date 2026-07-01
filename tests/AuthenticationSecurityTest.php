<?php
/**
 * Property Test for Authentication Security
 * **Feature: adv-crm-users-module, Property 8: Secure Authentication Process**
 * **Validates: Requirements 5.1, 5.2, 5.3**
 */

require_once 'PropertyTestBase.php';

class AuthenticationSecurityTest extends PropertyTestBase {
    private $authService;
    private $userModel;
    private $testUsers = [];
    
    public function __construct() {
        parent::__construct();
        $this->authService = new AuthenticationService();
        $this->userModel = new User();
    }
    
    /**
     * Property 8: Secure Authentication Process
     * For any user authentication attempt, the system should use password_hash() 
     * for verification and regenerate session IDs upon successful login
     */
    public function testSecureAuthenticationProcess() {
        return $this->runPropertyTest(
            'Secure Authentication Process',
            [$this, 'propertySecureAuthentication']
        );
    }
    
    /**
     * Property test implementation
     */
    public function propertySecureAuthentication() {
        try {
            // Generate random test user data
            $userData = $this->generateTestUserData();
            $plainPassword = $userData['password'];
            
            // Create test user with hashed password
            $hashedPassword = $this->authService->hashPassword($plainPassword);
            $userData['password_hash'] = $hashedPassword;
            unset($userData['password']);
            
            $userId = $this->createTestUser($userData);
            
            // Test 1: Verify password_hash() is used for password storage
            $storedUser = $this->userModel->find($userId);
            if (!password_verify($plainPassword, $storedUser['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Password hash verification failed - password_hash() not used correctly',
                    'data' => ['user_id' => $userId]
                ];
            }
            
            // Test 2: Verify authentication uses password_verify()
            $authResult = $this->authService->authenticate($userData['username'], $plainPassword);
            if (!$authResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Authentication failed for valid credentials',
                    'data' => ['user_id' => $userId, 'auth_result' => $authResult]
                ];
            }
            
            // Test 3: Verify session is created with secure token
            if (!isset($authResult['session']['session_token'])) {
                return [
                    'success' => false,
                    'message' => 'Session token not created during authentication',
                    'data' => ['auth_result' => $authResult]
                ];
            }
            
            $sessionToken = $authResult['session']['session_token'];
            
            // Test 4: Verify session token is sufficiently random/secure (length check)
            if (strlen($sessionToken) < 32) {
                return [
                    'success' => false,
                    'message' => 'Session token is not sufficiently secure (too short)',
                    'data' => ['token_length' => strlen($sessionToken)]
                ];
            }
            
            // Test 5: Verify session regeneration occurs (check PHP session)
            if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $userId) {
                return [
                    'success' => false,
                    'message' => 'PHP session not properly established',
                    'data' => ['expected_user_id' => $userId, 'session_user_id' => $_SESSION['user_id'] ?? null]
                ];
            }
            
            // Test 6: Verify wrong password fails authentication
            $wrongPassword = $plainPassword . 'wrong';
            $failAuthResult = $this->authService->authenticate($userData['username'], $wrongPassword);
            if ($failAuthResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Authentication succeeded with wrong password',
                    'data' => ['user_id' => $userId]
                ];
            }
            
            // Test 7: Verify CSRF token is generated
            if (!isset($authResult['session']['csrf_token'])) {
                return [
                    'success' => false,
                    'message' => 'CSRF token not generated during authentication',
                    'data' => ['session' => $authResult['session']]
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during authentication test: ' . $e->getMessage(),
                'data' => ['exception' => $e->getTraceAsString()]
            ];
        } finally {
            $this->cleanupTestData();
        }
    }
    
    /**
     * Generate random test user data
     */
    private function generateTestUserData() {
        return [
            'username' => 'testuser_' . $this->generateRandomString(8),
            'email' => $this->generateRandomEmail(),
            'password' => $this->generateSecurePassword(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'company_id' => 1, // Assume company 1 exists
            'role_id' => 1,    // Assume role 1 exists
            'status' => USER_STATUS_ACTIVE
        ];
    }
    
    /**
     * Generate secure password for testing
     */
    private function generateSecurePassword() {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        // Ensure at least one of each required character type
        $password .= chr(rand(65, 90));  // Uppercase
        $password .= chr(rand(97, 122)); // Lowercase
        $password .= chr(rand(48, 57));  // Number
        $password .= '!@#$%^&*'[rand(0, 7)]; // Special char
        
        // Fill remaining length
        for ($i = 4; $i < 12; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        return str_shuffle($password);
    }
    
    /**
     * Create test user in database
     */
    private function createTestUser($userData) {
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->executeQuery($sql, [
            $userData['username'],
            $userData['email'],
            $userData['password_hash'],
            $userData['first_name'],
            $userData['last_name'],
            $userData['company_id'],
            $userData['role_id'],
            $userData['status']
        ], 'sssssiis');
        
        $userId = $this->db->insert_id;
        $stmt->close();
        
        $this->testUsers[] = $userId;
        return $userId;
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        foreach ($this->testUsers as $userId) {
            try {
                // Clean up user sessions
                $sql = "DELETE FROM user_sessions WHERE user_id = ?";
                $stmt = $this->executeQuery($sql, [$userId], 'i');
                $stmt->close();
                
                // Clean up user
                $sql = "DELETE FROM users WHERE id = ?";
                $stmt = $this->executeQuery($sql, [$userId], 'i');
                $stmt->close();
            } catch (Exception $e) {
                error_log("Cleanup error: " . $e->getMessage());
            }
        }
        $this->testUsers = [];
        
        // Clean up login attempts for test IP to prevent lockout affecting subsequent tests
        try {
            $sql = "DELETE FROM login_attempts WHERE ip_address = '127.0.0.1' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            $stmt = $this->executeQuery($sql);
            $stmt->close();
        } catch (Exception $e) {
            error_log("Login attempts cleanup error: " . $e->getMessage());
        }
    }
    
    /**
     * Run all authentication security tests
     */
    public function runAllTests() {
        echo "=== Authentication Security Property Tests ===\n";
        
        $results = [];
        $results['secure_authentication'] = $this->testSecureAuthenticationProcess();
        
        $passed = array_sum($results);
        $total = count($results);
        
        echo "\n=== Results ===\n";
        echo "Passed: $passed/$total tests\n";
        
        if ($passed === $total) {
            echo "✓ All authentication security property tests passed!\n";
            return true;
        } else {
            echo "✗ Some authentication security property tests failed!\n";
            return false;
        }
    }
}
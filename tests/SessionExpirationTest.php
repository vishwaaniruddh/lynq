<?php
/**
 * Property Test for Session Expiration
 * **Feature: adv-crm-users-module, Property 9: Session Expiration Enforcement**
 * **Validates: Requirements 5.4**
 */

require_once 'PropertyTestBase.php';

class SessionExpirationTest extends PropertyTestBase {
    private $sessionService;
    private $userModel;
    private $testUsers = [];
    private $testSessions = [];
    
    public function __construct() {
        parent::__construct();
        $this->sessionService = new SessionService();
        $this->userModel = new User();
    }
    
    /**
     * Property 9: Session Expiration Enforcement
     * For any expired user session, the system should require re-authentication 
     * before allowing access to protected resources
     */
    public function testSessionExpirationEnforcement() {
        return $this->runPropertyTest(
            'Session Expiration Enforcement',
            [$this, 'propertySessionExpiration']
        );
    }
    
    /**
     * Property test implementation
     */
    public function propertySessionExpiration() {
        try {
            // Generate random test user
            $userData = $this->generateTestUserData();
            $userId = $this->createTestUser($userData);
            
            // Test 1: Create valid session using SessionService
            $session = $this->sessionService->createSession($userId);
            $sessionToken = $session['session_token'];
            $this->testSessions[] = $sessionToken;
            
            // Test 2: Verify session exists in database
            $sql = "SELECT * FROM user_sessions WHERE session_token = ?";
            $dbSession = $this->getResults($sql, [$sessionToken], 's');
            if (empty($dbSession)) {
                return [
                    'success' => false,
                    'message' => 'Session not found in database after creation',
                    'data' => ['session_token' => $sessionToken]
                ];
            }
            
            // Test 3: Verify session is not expired
            $sessionData = $dbSession[0];
            if (strtotime($sessionData['expires_at']) <= time()) {
                return [
                    'success' => false,
                    'message' => 'Session is already expired upon creation',
                    'data' => ['expires_at' => $sessionData['expires_at'], 'current_time' => date('Y-m-d H:i:s')]
                ];
            }
            
            // Test 4: Manually expire the session in database
            $this->expireSessionInDatabase($sessionToken);
            
            // Test 5: Verify expired session is rejected by direct database check
            $sql = "SELECT * FROM user_sessions WHERE session_token = ? AND expires_at > NOW()";
            $validSessions = $this->getResults($sql, [$sessionToken], 's');
            if (!empty($validSessions)) {
                return [
                    'success' => false,
                    'message' => 'Expired session still appears valid in database query',
                    'data' => ['session_token' => $sessionToken]
                ];
            }
            
            // Test 6: Create an already-expired session for testing
            $expiredSession = $this->createExpiredSession($userId);
            $expiredSessionToken = $expiredSession['session_token'];
            $this->testSessions[] = $expiredSessionToken;
            
            // Test 7: Verify expired session is not found in valid session query
            $sql = "SELECT * FROM user_sessions WHERE session_token = ? AND expires_at > NOW()";
            $expiredCheck = $this->getResults($sql, [$expiredSessionToken], 's');
            if (!empty($expiredCheck)) {
                return [
                    'success' => false,
                    'message' => 'Pre-expired session appears valid in database',
                    'data' => ['session_token' => $expiredSessionToken]
                ];
            }
            
            // Test 8: Verify session cleanup removes expired sessions
            $this->sessionService->cleanupExpiredSessions();
            
            // Check if expired sessions are removed from database
            $expiredSessionsCount = $this->countExpiredSessionsInDatabase();
            if ($expiredSessionsCount > 0) {
                return [
                    'success' => false,
                    'message' => 'Expired sessions not cleaned up from database',
                    'data' => ['expired_sessions_count' => $expiredSessionsCount]
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during session expiration test: ' . $e->getMessage(),
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
            'username' => 'sessiontest_' . $this->generateRandomString(8),
            'email' => $this->generateRandomEmail(),
            'password_hash' => password_hash('testpass123', PASSWORD_DEFAULT),
            'first_name' => 'Session',
            'last_name' => 'Test',
            'company_id' => 1,
            'role_id' => 1,
            'status' => USER_STATUS_ACTIVE
        ];
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
     * Manually expire session in database for testing
     */
    private function expireSessionInDatabase($sessionToken) {
        $sql = "UPDATE user_sessions SET expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE session_token = ?";
        $stmt = $this->executeQuery($sql, [$sessionToken], 's');
        $stmt->close();
    }
    
    /**
     * Create session with custom expiration time
     */
    private function createSessionWithCustomExpiration($userId, $expirationSeconds) {
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $expirationSeconds);
        $ipAddress = 'test'; // Use 'test' for testing environment
        
        $sql = "INSERT INTO user_sessions (user_id, session_token, expires_at, ip_address, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $this->executeQuery($sql, [$userId, $sessionToken, $expiresAt, $ipAddress], 'isss');
        $stmt->close();
        
        return [
            'session_token' => $sessionToken,
            'expires_at' => $expiresAt
        ];
    }
    
    /**
     * Create an already expired session for testing
     */
    private function createExpiredSession($userId) {
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() - 3600); // Expired 1 hour ago
        $ipAddress = 'test';
        
        $sql = "INSERT INTO user_sessions (user_id, session_token, expires_at, ip_address, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $this->executeQuery($sql, [$userId, $sessionToken, $expiresAt, $ipAddress], 'isss');
        $stmt->close();
        
        return [
            'session_token' => $sessionToken,
            'expires_at' => $expiresAt
        ];
    }
    
    /**
     * Count expired sessions in database
     */
    private function countExpiredSessionsInDatabase() {
        $sql = "SELECT COUNT(*) as count FROM user_sessions WHERE expires_at < NOW()";
        $result = $this->getResults($sql);
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Get session expiration time
     */
    private function getSessionExpiration($sessionToken) {
        $sql = "SELECT expires_at FROM user_sessions WHERE session_token = ?";
        $result = $this->getResults($sql, [$sessionToken], 's');
        return $result[0]['expires_at'] ?? null;
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        // Clean up test sessions
        foreach ($this->testSessions as $sessionToken) {
            try {
                $sql = "DELETE FROM user_sessions WHERE session_token = ?";
                $stmt = $this->executeQuery($sql, [$sessionToken], 's');
                $stmt->close();
            } catch (Exception $e) {
                error_log("Session cleanup error: " . $e->getMessage());
            }
        }
        $this->testSessions = [];
        
        // Clean up test users
        foreach ($this->testUsers as $userId) {
            try {
                $sql = "DELETE FROM user_sessions WHERE user_id = ?";
                $stmt = $this->executeQuery($sql, [$userId], 'i');
                $stmt->close();
                
                $sql = "DELETE FROM users WHERE id = ?";
                $stmt = $this->executeQuery($sql, [$userId], 'i');
                $stmt->close();
            } catch (Exception $e) {
                error_log("User cleanup error: " . $e->getMessage());
            }
        }
        $this->testUsers = [];
    }
    
    /**
     * Run all session expiration tests
     */
    public function runAllTests() {
        echo "=== Session Expiration Property Tests ===\n";
        
        $results = [];
        $results['session_expiration'] = $this->testSessionExpirationEnforcement();
        
        $passed = array_sum($results);
        $total = count($results);
        
        echo "\n=== Results ===\n";
        echo "Passed: $passed/$total tests\n";
        
        if ($passed === $total) {
            echo "✓ All session expiration property tests passed!\n";
            return true;
        } else {
            echo "✗ Some session expiration property tests failed!\n";
            return false;
        }
    }
}
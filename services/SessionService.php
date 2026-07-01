<?php
/**
 * Session Service
 * Handles secure session management with regeneration and expiration
 */

require_once __DIR__ . '/../config/autoload.php';

class SessionService {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->initializeSession();
    }
    
    /**
     * Initialize secure session configuration
     */
    private function initializeSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configure secure session settings
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_strict_mode', 1);
            // Use 'Lax' instead of 'Strict' to allow navigation
            ini_set('session.cookie_samesite', 'Lax');
            
            session_start();
        }
    }
    
    /**
     * Create new session for user
     * 
     * @param int $userId User ID
     * @return array Session data
     */
    public function createSession($userId) {
        try {
            // Generate secure session token
            $sessionToken = $this->generateSecureToken();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'test';
            
            // Store session in database - use MySQL's NOW() + INTERVAL for consistent timezone
            $sql = "INSERT INTO user_sessions (user_id, session_token, expires_at, ip_address, created_at) 
                    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?, NOW())";
            
            $stmt = $this->db->executeQuery($sql, [$userId, $sessionToken, SESSION_TIMEOUT, $ipAddress], 'isis');
            $stmt->close();
            
            // Get the actual expires_at from database
            $expiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
            
            // Regenerate PHP session ID for security (only if headers not sent)
            if (!headers_sent()) {
                session_regenerate_id(true);
            }
            
            // Store session data in PHP session
            $_SESSION['user_id'] = $userId;
            $_SESSION['session_token'] = $sessionToken;
            $_SESSION['last_regeneration'] = time();
            $_SESSION['csrf_token'] = $this->generateCSRFToken();
            
            return [
                'session_token' => $sessionToken,
                'expires_at' => $expiresAt,
                'csrf_token' => $_SESSION['csrf_token']
            ];
            
        } catch (Exception $e) {
            error_log("Session creation error: " . $e->getMessage());
            throw new Exception("Failed to create session");
        }
    }
    
    /**
     * Validate session token
     * 
     * @param string $sessionToken Session token
     * @return array|null Session data if valid, null if invalid
     */
    public function validateSession($sessionToken) {
        try {
            // Check if session needs regeneration
            $this->checkSessionRegeneration();
            
            // Get session from database
            $sql = "SELECT * FROM user_sessions WHERE session_token = ? AND expires_at > NOW()";
            $result = $this->db->getResults($sql, [$sessionToken], 's');
            
            if (empty($result)) {
                return null;
            }
            
            $session = $result[0];
            
            // Validate IP address (optional - can be disabled for mobile users)
            $currentIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            // Skip IP validation in testing environment
            if ($session['ip_address'] !== $currentIp && $session['ip_address'] !== 'unknown' && $currentIp !== 'test') {
                // Log suspicious activity
                error_log("Session IP mismatch: Expected {$session['ip_address']}, got {$currentIp}");
                // Optionally destroy session for security
                // $this->destroySession($sessionToken);
                // return null;
            }
            
            // Update last activity
            $this->updateSessionActivity($sessionToken);
            
            return $session;
            
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Destroy session
     * 
     * @param string $sessionToken Session token
     * @return bool Success status
     */
    public function destroySession($sessionToken) {
        try {
            // Remove from database
            $sql = "DELETE FROM user_sessions WHERE session_token = ?";
            $stmt = $this->db->executeQuery($sql, [$sessionToken], 's');
            $stmt->close();
            
            // Clear PHP session
            if (isset($_SESSION['session_token']) && $_SESSION['session_token'] === $sessionToken) {
                session_unset();
                session_destroy();
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Session destruction error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions() {
        try {
            $sql = "DELETE FROM user_sessions WHERE expires_at < NOW()";
            $stmt = $this->db->executeQuery($sql);
            $stmt->close();
        } catch (Exception $e) {
            error_log("Session cleanup error: " . $e->getMessage());
        }
    }
    
    /**
     * Generate CSRF token
     * 
     * @return string CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token Token to validate
     * @return bool True if valid
     */
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Check if session needs regeneration
     */
    private function checkSessionRegeneration() {
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        }
        
        // Regenerate session ID every 5 minutes (only if headers not sent)
        if (time() - $_SESSION['last_regeneration'] > SESSION_REGENERATE_INTERVAL) {
            if (!headers_sent()) {
                session_regenerate_id(true);
            }
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    /**
     * Update session last activity
     * 
     * @param string $sessionToken Session token
     */
    private function updateSessionActivity($sessionToken) {
        try {
            $sql = "UPDATE user_sessions SET last_activity = NOW() WHERE session_token = ?";
            $stmt = $this->db->executeQuery($sql, [$sessionToken], 's');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Session activity update error: " . $e->getMessage());
        }
    }
    
    /**
     * Generate secure random token
     * 
     * @return string Secure token
     */
    private function generateSecureToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Check if user is logged in
     * 
     * @return bool True if logged in
     */
    public function isLoggedIn() {
        // Just check if session variables exist
        // Database validation is done on sensitive operations only
        return isset($_SESSION['user_id']) && isset($_SESSION['session_token']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current user ID
     * 
     * @return int|null User ID or null if not logged in
     */
    public function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current user data with relations
     * 
     * @return array|null User data or null if not logged in
     */
    public function getCurrentUser() {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return null;
        }
        
        try {
            $sql = "SELECT u.*, c.name as company_name, c.type as company_type, r.name as role_name 
                    FROM users u 
                    LEFT JOIN companies c ON u.company_id = c.id 
                    LEFT JOIN roles r ON u.role_id = r.id 
                    WHERE u.id = ?";
            $result = $this->db->getResults($sql, [$userId], 'i');
            return !empty($result) ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Get current user error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Logout current user
     */
    public function logout() {
        if (isset($_SESSION['session_token'])) {
            $this->destroySession($_SESSION['session_token']);
        }
        
        session_unset();
        session_destroy();
    }
}
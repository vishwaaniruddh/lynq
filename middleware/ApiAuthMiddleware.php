<?php
/**
 * API Authentication and Authorization Middleware
 * Handles API-specific authentication, authorization, and rate limiting
 * 
 * Requirements: 6.1, 6.2, 6.5
 * Supports dual authentication: JWT (preferred) and session-based (legacy)
 * 
 * **Feature: jwt-authentication**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../api/ApiResponse.php';
require_once __DIR__ . '/../services/JWTService.php';

class ApiAuthMiddleware {
    private $sessionService;
    private $permissionEngine;
    private $db;
    
    /** @var JWTService|null JWT service instance */
    private $jwtService;
    
    /** @var array JWT configuration */
    private $jwtConfig;
    
    /** @var array|null Cached authenticated user */
    private $authenticatedUser = null;
    
    /** @var string|null Authentication method used ('jwt' or 'session') */
    private $authMethod = null;
    
    // Rate limiting configuration
    private $rateLimitRequests = 1000;  // Max requests per window
    private $rateLimitWindow = 60;      // Time window in seconds (1 minute)
    
    public function __construct() {
        $this->sessionService = new SessionService();
        $this->permissionEngine = new PermissionEngine();
        $this->db = DatabaseConfig::getInstance();
        
        // Load JWT configuration
        $this->jwtConfig = require __DIR__ . '/../config/jwt.php';
    }
    
    /**
     * Authenticate API request
     * Supports both JWT and session-based authentication
     * Requirements: 6.1, 6.2
     * 
     * Priority:
     * 1. JWT Bearer token in Authorization header
     * 2. JWT token in cookie
     * 3. Session-based authentication (if legacy_session_enabled)
     * 
     * @return array|null User data if authenticated, null otherwise
     */
    public function authenticate() {
        // Return cached user if already authenticated
        if ($this->authenticatedUser !== null) {
            return $this->authenticatedUser;
        }
        
        // Try JWT authentication first (Requirements: 6.2 - prefer JWT when both present)
        $jwtUser = $this->authenticateWithJWT();
        if ($jwtUser !== null) {
            $this->authenticatedUser = $jwtUser;
            $this->authMethod = 'jwt';
            return $jwtUser;
        }
        
        // Fall back to session if JWT not present and legacy enabled (Requirements: 6.1, 6.5)
        $legacyEnabled = $this->jwtConfig['legacy_session_enabled'] ?? true;
        if ($legacyEnabled) {
            $sessionUser = $this->authenticateWithSession();
            if ($sessionUser !== null) {
                $this->authenticatedUser = $sessionUser;
                $this->authMethod = 'session';
                return $sessionUser;
            }
        }
        
        return null;
    }
    
    /**
     * Authenticate using JWT token
     * Checks Authorization header first, then cookie
     * Requirements: 6.1, 6.2
     * 
     * @return array|null User data if authenticated, null otherwise
     */
    private function authenticateWithJWT(): ?array {
        // Extract JWT token
        $token = $this->extractJWTToken();
        
        if ($token === null) {
            return null;
        }
        
        // Validate token using JWTService
        $jwtService = $this->getJWTService();
        $result = $jwtService->validateToken($token);
        
        if (!$result['valid']) {
            return null;
        }
        
        // Build user data from claims
        $claims = $result['claims'];
        
        // Verify user still exists and is active
        $userModel = new User();
        $user = $userModel->findWithRelations($claims['user_id']);
        
        if (!$user || $user['status'] != USER_STATUS_ACTIVE) {
            return null;
        }
        
        // Add JWT-specific data to user array
        $user['auth_method'] = 'jwt';
        $user['jti'] = $claims['jti'] ?? null;
        $user['token_exp'] = $claims['exp'] ?? null;
        
        return $user;
    }
    
    /**
     * Extract JWT token from request
     * Checks Authorization header first, then cookie
     * 
     * @return string|null Token string or null if not found
     */
    private function extractJWTToken(): ?string {
        // Try Authorization header first (Bearer token)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        // Try cookie
        $cookieName = $this->jwtConfig['cookie_name'] ?? 'adv_access_token';
        if (isset($_COOKIE[$cookieName]) && !empty($_COOKIE[$cookieName])) {
            return $_COOKIE[$cookieName];
        }

        // Try query parameters
        if (!empty($_GET['access_token'])) {
            return $_GET['access_token'];
        }
        if (!empty($_GET['token'])) {
            return $_GET['token'];
        }
        
        return null;
    }
    
    /**
     * Authenticate using session
     * Requirements: 6.1, 6.5
     * 
     * @return array|null User data if authenticated, null otherwise
     */
    private function authenticateWithSession(): ?array {
        $userId = $this->sessionService->getCurrentUserId();
        
        if (!$userId) {
            return null;
        }
        
        $userModel = new User();
        $user = $userModel->findWithRelations($userId);
        
        if ($user && $user['status'] == USER_STATUS_ACTIVE) {
            $user['auth_method'] = 'session';
            return $user;
        }
        
        return null;
    }
    
    /**
     * Get the authentication method used
     * 
     * @return string|null 'jwt', 'session', or null if not authenticated
     */
    public function getAuthMethod(): ?string {
        if ($this->authMethod === null) {
            $this->authenticate();
        }
        return $this->authMethod;
    }
    
    /**
     * Check if authenticated via JWT
     * 
     * @return bool True if authenticated via JWT
     */
    public function isJWTAuthenticated(): bool {
        return $this->getAuthMethod() === 'jwt';
    }
    
    /**
     * Check if authenticated via session
     * 
     * @return bool True if authenticated via session
     */
    public function isSessionAuthenticated(): bool {
        return $this->getAuthMethod() === 'session';
    }
    
    /**
     * Get JWTService instance (lazy loading)
     * 
     * @return JWTService
     */
    private function getJWTService(): JWTService {
        if ($this->jwtService === null) {
            $this->jwtService = new JWTService();
        }
        return $this->jwtService;
    }
    
    /**
     * Set JWTService instance (for testing/dependency injection)
     * 
     * @param JWTService $jwtService
     */
    public function setJWTService(JWTService $jwtService): void {
        $this->jwtService = $jwtService;
    }
    
    /**
     * Set JWT configuration (for testing)
     * 
     * @param array $config
     */
    public function setJWTConfig(array $config): void {
        $this->jwtConfig = $config;
    }
    
    /**
     * Reset cached authentication state (for testing)
     */
    public function resetAuthState(): void {
        $this->authenticatedUser = null;
        $this->authMethod = null;
    }
    
    /**
     * Require authentication for API endpoint
     * 
     * @return array User data
     */
    public function requireAuth() {
        $user = $this->authenticate();
        
        if (!$user) {
            ApiResponse::unauthorized('Authentication required');
        }
        
        return $user;
    }
    
    /**
     * Require specific permission for API endpoint
     * 
     * @param string $permission Permission name
     * @param bool $reVerifySession Whether to re-verify session permissions
     * @return array User data
     */
    public function requirePermission($permission, $reVerifySession = true) {
        $user = $this->requireAuth();
        
        // Re-verify session permissions for security (Requirements: 6.4)
        if ($reVerifySession && $this->authMethod === 'session') {
            $this->reVerifySessionPermissions($user['id']);
        }
        
        if (!$this->permissionEngine->can($user['id'], $permission)) {
            // Log unauthorized access attempt (Requirements: 6.3)
            $this->logUnauthorizedAccess($user['id'], $permission, $_SERVER['REQUEST_URI'] ?? 'unknown');
            
            ApiResponse::forbidden(
                "You do not have permission to perform this action",
                [
                    'required_permission' => $permission,
                    'user_permissions' => array_keys($this->permissionEngine->getUserPermissions($user['id']))
                ]
            );
        }
        
        return $user;
    }
    
    /**
     * Require any of the specified permissions
     * 
     * @param array $permissions List of permissions (user needs at least one)
     * @return array User data
     */
    public function requireAnyPermission($permissions) {
        $user = $this->requireAuth();
        
        foreach ($permissions as $permission) {
            if ($this->permissionEngine->can($user['id'], $permission)) {
                return $user;
            }
        }
        
        ApiResponse::forbidden(
            "You do not have permission to perform this action",
            [
                'required_permissions' => $permissions,
                'user_permissions' => array_keys($this->permissionEngine->getUserPermissions($user['id']))
            ]
        );
    }
    
    /**
     * Require ADV user access
     * 
     * @return array User data
     */
    public function requireAdvUser() {
        $user = $this->requireAuth();
        
        if (strtoupper($user['company_type'] ?? '') !== 'ADV') {
            ApiResponse::forbidden('This action requires ADV user access');
        }
        
        return $user;
    }
    
    /**
     * Require company access
     * 
     * @param int $companyId Target company ID
     * @return array User data
     */
    public function requireCompanyAccess($companyId) {
        $user = $this->requireAuth();
        
        // ADV users can access any company
        if (strtoupper($user['company_type'] ?? '') === 'ADV') {
            return $user;
        }
        
        // Contractor users can only access their own company
        if ((int)$user['company_id'] !== (int)$companyId) {
            ApiResponse::forbidden('Access to this company is not allowed');
        }
        
        return $user;
    }
    
    /**
     * Check rate limiting
     * 
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @return bool True if within limits
     */
    public function checkRateLimit($identifier = null) {
        // Skip rate limiting for authenticated ADV users
        $userId = $this->sessionService->getCurrentUserId();
        if ($userId && isAdvUser($userId)) {
            return true;
        }
        
        if ($identifier === null) {
            $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        
        $key = 'api_rate_' . md5($identifier);
        $now = time();
        
        // Get current rate limit data from session or database
        $rateLimitData = $this->getRateLimitData($key);
        
        if (!$rateLimitData) {
            // First request
            $this->setRateLimitData($key, [
                'count' => 1,
                'window_start' => $now
            ]);
            return true;
        }
        
        // Check if window has expired
        if ($now - $rateLimitData['window_start'] > $this->rateLimitWindow) {
            // Reset window
            $this->setRateLimitData($key, [
                'count' => 1,
                'window_start' => $now
            ]);
            return true;
        }
        
        // Check if limit exceeded
        if ($rateLimitData['count'] >= $this->rateLimitRequests) {
            $retryAfter = $this->rateLimitWindow - ($now - $rateLimitData['window_start']);
            ApiResponse::rateLimitExceeded($retryAfter);
        }
        
        // Increment count
        $rateLimitData['count']++;
        $this->setRateLimitData($key, $rateLimitData);
        
        // Set rate limit headers
        $remaining = $this->rateLimitRequests - $rateLimitData['count'];
        header('X-RateLimit-Limit: ' . $this->rateLimitRequests);
        header('X-RateLimit-Remaining: ' . max(0, $remaining));
        header('X-RateLimit-Reset: ' . ($rateLimitData['window_start'] + $this->rateLimitWindow));
        
        return true;
    }
    
    /**
     * Validate Bearer token (legacy session token support)
     * This method is kept for backward compatibility with session tokens
     * passed as Bearer tokens
     * 
     * @param string $token Bearer token
     * @return array|null User data if valid
     * @deprecated Use JWT tokens instead
     */
    private function validateBearerToken($token) {
        // Check if legacy session is enabled
        $legacyEnabled = $this->jwtConfig['legacy_session_enabled'] ?? true;
        if (!$legacyEnabled) {
            return null;
        }
        
        // Validate session token
        $session = $this->sessionService->validateSession($token);
        
        if (!$session) {
            return null;
        }
        
        $userModel = new User();
        $user = $userModel->findWithRelations($session['user_id']);
        
        if (!$user || $user['status'] != USER_STATUS_ACTIVE) {
            return null;
        }
        
        $user['auth_method'] = 'session';
        return $user;
    }
    
    /**
     * Get rate limit data
     * 
     * @param string $key Rate limit key
     * @return array|null Rate limit data
     */
    private function getRateLimitData($key) {
        // Using session storage for simplicity
        // In production, use Redis or database
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION[$key] ?? null;
    }
    
    /**
     * Set rate limit data
     * 
     * @param string $key Rate limit key
     * @param array $data Rate limit data
     */
    private function setRateLimitData($key, $data) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION[$key] = $data;
    }
    
    /**
     * Get current authenticated user ID
     * 
     * @return int|null User ID or null
     */
    public function getCurrentUserId() {
        $user = $this->authenticate();
        return $user ? $user['id'] : null;
    }
    
    /**
     * Log API access for audit
     * 
     * @param int $userId User ID
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $params Request parameters
     */
    public function logApiAccess($userId, $endpoint, $method, $params = []) {
        try {
            $sql = "INSERT INTO api_access_log (user_id, endpoint, method, params, ip_address, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->executeQuery($sql, [
                $userId,
                $endpoint,
                $method,
                json_encode($params),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ], 'isssss');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log API access: " . $e->getMessage());
        }
    }
    
    /**
     * Log unauthorized access attempt
     * Requirements: 6.3
     * 
     * @param int $userId User ID attempting access
     * @param string $permission Required permission
     * @param string $endpoint Endpoint being accessed
     */
    public function logUnauthorizedAccess($userId, $permission, $endpoint) {
        try {
            $sql = "INSERT INTO api_access_log (user_id, endpoint, method, params, ip_address, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                'required_permission' => $permission,
                'access_denied' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $stmt = $this->db->executeQuery($sql, [
                $userId,
                $endpoint,
                $_SERVER['REQUEST_METHOD'] ?? 'GET',
                json_encode($params),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ], 'isssss');
            $stmt->close();
            
            // Also log to error log for immediate attention
            error_log("UNAUTHORIZED ACCESS ATTEMPT: User ID $userId attempted to access $endpoint requiring permission '$permission' from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            
        } catch (Exception $e) {
            error_log("Failed to log unauthorized access: " . $e->getMessage());
        }
    }
    
    /**
     * Re-verify session permissions for security
     * Requirements: 6.4
     * 
     * @param int $userId User ID to re-verify
     * @throws Exception If session is invalid or permissions changed
     */
    private function reVerifySessionPermissions($userId) {
        try {
            // Check if session is still valid by validating the current session token
            $sessionToken = $_SESSION['session_token'] ?? null;
            if (!$sessionToken || !$this->sessionService->validateSession($sessionToken)) {
                throw new Exception('Session expired or invalid');
            }
            
            // Verify user is still active
            $userModel = new User();
            $user = $userModel->findWithRelations($userId);
            
            if (!$user || $user['status'] != USER_STATUS_ACTIVE) {
                throw new Exception('User account is no longer active');
            }
            
            // Check if user's role or permissions have changed since session start
            $sessionToken = $_SESSION['session_token'] ?? null;
            if ($sessionToken) {
                // Get session creation time from database
                $sql = "SELECT created_at FROM user_sessions WHERE session_token = ?";
                $sessionResult = $this->db->getResults($sql, [$sessionToken], 's');
                
                if (!empty($sessionResult)) {
                    $sessionStartTime = $sessionResult[0]['created_at'];
                    
                    // Check if user's role was modified after session start
                    $sql = "SELECT COUNT(*) as changes FROM user_roles ur 
                            JOIN roles r ON ur.role_id = r.id 
                            WHERE ur.user_id = ? AND (ur.updated_at > ? OR r.updated_at > ?)";
                    
                    $result = $this->db->getResults($sql, [$userId, $sessionStartTime, $sessionStartTime], 'iss');
                    
                    if (!empty($result) && $result[0]['changes'] > 0) {
                        // Permissions may have changed, force re-authentication
                        $this->sessionService->destroySession($sessionToken);
                        throw new Exception('User permissions have changed, please re-authenticate');
                    }
                }
            }
            
        } catch (Exception $e) {
            // Log the re-verification failure
            error_log("Session re-verification failed for user $userId: " . $e->getMessage());
            
            // Clear cached authentication
            $this->authenticatedUser = null;
            $this->authMethod = null;
            
            // Destroy session if it exists
            $sessionToken = $_SESSION['session_token'] ?? null;
            if ($sessionToken) {
                $this->sessionService->destroySession($sessionToken);
            }
            
            ApiResponse::unauthorized('Session verification failed: ' . $e->getMessage());
        }
    }
}

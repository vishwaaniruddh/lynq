<?php
/**
 * JWT Authentication Middleware
 * Handles JWT-based authentication for API requests
 * 
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5
 * 
 * **Feature: jwt-authentication**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/JWTService.php';
require_once __DIR__ . '/../api/ApiResponse.php';

class JWTAuthMiddleware {
    /** @var JWTService */
    private $jwtService;
    
    /** @var PermissionEngine */
    private $permissionEngine;
    
    /** @var array|null Cached user data from token */
    private $currentUser = null;
    
    /** @var array|null Cached token claims */
    private $tokenClaims = null;
    
    /** @var array JWT configuration */
    private $config;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->jwtService = new JWTService();
        $this->permissionEngine = new PermissionEngine();
        $this->config = require __DIR__ . '/../config/jwt.php';
    }
    
    /**
     * Authenticate request and return user data
     * Extracts token from Authorization header (Bearer) or cookie
     * Requirements: 4.1, 4.2
     * 
     * @return array|null User data if authenticated, null otherwise
     */
    public function authenticate(): ?array {
        // Return cached user if already authenticated
        if ($this->currentUser !== null) {
            return $this->currentUser;
        }
        
        // Try to extract token
        $token = $this->extractToken();
        
        if ($token === null) {
            return null;
        }
        
        // Validate token
        $result = $this->jwtService->validateToken($token);
        
        if (!$result['valid']) {
            return null;
        }
        
        // Cache claims
        $this->tokenClaims = $result['claims'];
        
        // Populate user context from claims
        // Requirements: 4.2
        $this->currentUser = $this->buildUserFromClaims($result['claims']);
        
        return $this->currentUser;
    }

    /**
     * Extract JWT token from request
     * Checks Authorization header first, then cookie
     * Requirements: 4.1
     * 
     * @return string|null Token string or null if not found
     */
    private function extractToken(): ?string {
        // Try Authorization header first (Bearer token)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        // Try cookie
        $cookieName = $this->config['cookie_name'] ?? 'adv_access_token';
        if (isset($_COOKIE[$cookieName]) && !empty($_COOKIE[$cookieName])) {
            return $_COOKIE[$cookieName];
        }
        
        return null;
    }
    
    /**
     * Build user data array from token claims
     * Requirements: 4.2
     * 
     * @param array $claims Token claims
     * @return array User data
     */
    private function buildUserFromClaims(array $claims): array {
        return [
            'id' => $claims['user_id'] ?? null,
            'user_id' => $claims['user_id'] ?? null,
            'company_id' => $claims['company_id'] ?? null,
            'company_type' => $claims['company_type'] ?? '',
            'role_id' => $claims['role_id'] ?? null,
            'username' => $claims['username'] ?? '',
            'jti' => $claims['jti'] ?? null,
            'iat' => $claims['iat'] ?? null,
            'exp' => $claims['exp'] ?? null,
            'auth_method' => 'jwt'
        ];
    }
    
    /**
     * Require authentication, exit with 401 if not authenticated
     * Requirements: 4.3, 4.4
     * 
     * @return array User data
     */
    public function requireAuth(): array {
        // Try to extract token first to determine error type
        $token = $this->extractToken();
        
        if ($token === null) {
            // No token provided
            ApiResponse::error(
                JWTService::ERROR_TOKEN_MISSING,
                'Authentication required. Please provide a valid JWT token.',
                401
            );
        }
        
        // Validate token
        $result = $this->jwtService->validateToken($token);
        
        if (!$result['valid']) {
            // Return specific error based on validation result
            $errorCode = $result['error'] ?? JWTService::ERROR_TOKEN_INVALID;
            $message = $this->getErrorMessage($errorCode);
            
            ApiResponse::error($errorCode, $message, 401);
        }
        
        // Cache claims and user
        $this->tokenClaims = $result['claims'];
        $this->currentUser = $this->buildUserFromClaims($result['claims']);
        
        return $this->currentUser;
    }
    
    /**
     * Get human-readable error message for error code
     * 
     * @param string $errorCode Error code
     * @return string Error message
     */
    private function getErrorMessage(string $errorCode): string {
        $messages = [
            JWTService::ERROR_TOKEN_EXPIRED => 'Access token has expired. Please refresh your token.',
            JWTService::ERROR_TOKEN_INVALID => 'Invalid token. Please provide a valid JWT token.',
            JWTService::ERROR_TOKEN_REVOKED => 'Token has been revoked. Please login again.',
            JWTService::ERROR_TOKEN_MISSING => 'Authentication required. Please provide a valid JWT token.',
            JWTService::ERROR_REFRESH_EXPIRED => 'Refresh token has expired. Please login again.',
            JWTService::ERROR_REFRESH_REVOKED => 'Refresh token has been revoked. Please login again.',
        ];
        
        return $messages[$errorCode] ?? 'Authentication failed.';
    }

    /**
     * Require specific permission
     * Loads permissions from database using user_id from token
     * Requirements: 4.5
     * 
     * @param string $permission Permission name (e.g., 'users.create')
     * @return array User data
     */
    public function requirePermission(string $permission): array {
        // First ensure user is authenticated
        $user = $this->requireAuth();
        
        // Check permission using PermissionEngine
        // This loads permissions from database using user_id from token
        if (!$this->permissionEngine->can($user['id'], $permission)) {
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
    public function requireAnyPermission(array $permissions): array {
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
        
        // This line is never reached as ApiResponse::forbidden() calls exit
        // Added for static analysis tools
        return $user;
    }
    
    /**
     * Require ADV user access
     * 
     * @return array User data
     */
    public function requireAdvUser(): array {
        $user = $this->requireAuth();
        
        if (strtoupper($user['company_type'] ?? '') !== 'ADV') {
            ApiResponse::forbidden('This action requires ADV user access');
        }
        
        return $user;
    }
    
    /**
     * Require company access - enforces company isolation
     * Requirements: 4.5
     * 
     * @param int $companyId Target company ID
     * @return array User data
     */
    public function requireCompanyAccess(int $companyId): array {
        $user = $this->requireAuth();
        
        // ADV users can access any company
        if (strtoupper($user['company_type'] ?? '') === 'ADV') {
            return $user;
        }
        
        // Contractor users can only access their own company
        // This enforces company isolation based on company_id from token
        if ((int)$user['company_id'] !== $companyId) {
            ApiResponse::forbidden('Access to this company is not allowed');
        }
        
        return $user;
    }
    
    /**
     * Get current user ID from token
     * 
     * @return int|null User ID or null
     */
    public function getCurrentUserId(): ?int {
        $user = $this->authenticate();
        return $user ? (int)$user['id'] : null;
    }
    
    /**
     * Get current company ID from token
     * Requirements: 4.5
     * 
     * @return int|null Company ID or null
     */
    public function getCurrentCompanyId(): ?int {
        $user = $this->authenticate();
        return $user ? (int)$user['company_id'] : null;
    }
    
    /**
     * Get current company type from token
     * 
     * @return string|null Company type or null
     */
    public function getCurrentCompanyType(): ?string {
        $user = $this->authenticate();
        return $user ? $user['company_type'] : null;
    }
    
    /**
     * Check if current user is ADV user
     * 
     * @return bool True if ADV user
     */
    public function isAdvUser(): bool {
        $user = $this->authenticate();
        return $user && strtoupper($user['company_type'] ?? '') === 'ADV';
    }
    
    /**
     * Get token claims
     * 
     * @return array|null Token claims or null
     */
    public function getTokenClaims(): ?array {
        if ($this->tokenClaims === null) {
            $this->authenticate();
        }
        return $this->tokenClaims;
    }
    
    /**
     * Get the current access token
     * 
     * @return string|null Token string or null
     */
    public function getCurrentToken(): ?string {
        return $this->extractToken();
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
     * Set PermissionEngine instance (for testing/dependency injection)
     * 
     * @param PermissionEngine $permissionEngine
     */
    public function setPermissionEngine(PermissionEngine $permissionEngine): void {
        $this->permissionEngine = $permissionEngine;
    }
}

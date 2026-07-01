<?php
/**
 * Authentication Helper
 * Provides unified authentication functions that work with both JWT and session
 * 
 * Requirements: 6.3
 * - Provide requireAuth() function that works with both JWT and session
 * - Provide getCurrentUser() that extracts from JWT or session
 * 
 * **Feature: jwt-authentication**
 * 
 * Note: This file is loaded by autoload.php, do not require autoload here
 */

/**
 * Require authentication - works with both JWT and session
 * Returns user data if authenticated, exits with 401 if not
 * Requirements: 6.3
 * 
 * @param bool $exitOnFailure If true, exit with 401 on failure; if false, return null
 * @return array|null User data array or null if not authenticated (when exitOnFailure is false)
 */
function requireAuth($exitOnFailure = true) {
    static $jwtMiddleware = null;
    static $sessionService = null;
    static $config = null;
    
    // Load JWT config
    if ($config === null) {
        $configPath = __DIR__ . '/../config/jwt.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
        } else {
            $config = ['legacy_session_enabled' => true];
        }
    }
    
    // Try JWT authentication first
    $user = tryJWTAuth();
    
    if ($user !== null) {
        return $user;
    }
    
    // Fall back to session authentication if legacy is enabled
    $legacyEnabled = $config['legacy_session_enabled'] ?? true;
    
    if ($legacyEnabled) {
        $user = trySessionAuth();
        
        if ($user !== null) {
            return $user;
        }
    }
    
    // No valid authentication found
    if ($exitOnFailure) {
        // Check if this is an API request
        if (isApiRequest()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'TOKEN_MISSING',
                    'message' => 'Authentication required. Please provide a valid JWT token or session.'
                ]
            ]);
            exit;
        } else {
            // Redirect to login for web requests
            header('Location: /index.php');
            exit;
        }
    }
    
    return null;
}

/**
 * Try JWT authentication
 * 
 * @return array|null User data or null if not authenticated via JWT
 */
function tryJWTAuth() {
    // Check for Bearer token in Authorization header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = null;
    
    if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    }
    
    // Try cookie if no header token
    if ($token === null) {
        $configPath = __DIR__ . '/../config/jwt.php';
        $cookieName = 'adv_access_token';
        
        if (file_exists($configPath)) {
            $config = require $configPath;
            $cookieName = $config['cookie_name'] ?? 'adv_access_token';
        }
        
        if (isset($_COOKIE[$cookieName]) && !empty($_COOKIE[$cookieName])) {
            $token = $_COOKIE[$cookieName];
        }
    }
    
    if ($token === null) {
        return null;
    }
    
    // Validate token using JWTService
    try {
        $jwtService = new JWTService();
        $result = $jwtService->validateToken($token);
        
        if (!$result['valid']) {
            return null;
        }
        
        $claims = $result['claims'];
        
        // Build user data from claims
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
    } catch (Exception $e) {
        error_log("JWT authentication error: " . $e->getMessage());
        return null;
    }
}

/**
 * Try session authentication
 * 
 * @return array|null User data or null if not authenticated via session
 */
function trySessionAuth() {
    try {
        $sessionService = new SessionService();
        
        if (!$sessionService->isLoggedIn()) {
            return null;
        }
        
        $user = $sessionService->getCurrentUser();
        
        if ($user === null) {
            return null;
        }
        
        // Add auth_method to indicate session authentication
        $user['auth_method'] = 'session';
        
        return $user;
    } catch (Exception $e) {
        error_log("Session authentication error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get current user - works with both JWT and session
 * Does not exit on failure, just returns null
 * Requirements: 6.3
 * 
 * @return array|null User data array or null if not authenticated
 */
function getCurrentUser() {
    return requireAuth(false);
}

/**
 * Get current user ID - works with both JWT and session
 * 
 * @return int|null User ID or null if not authenticated
 */
function getCurrentUserId() {
    $user = getCurrentUser();
    return $user ? (int)$user['id'] : null;
}

/**
 * Get current company ID - works with both JWT and session
 * 
 * @return int|null Company ID or null if not authenticated
 */
function getCurrentCompanyId() {
    $user = getCurrentUser();
    return $user ? (int)$user['company_id'] : null;
}

/**
 * Get current company type - works with both JWT and session
 * 
 * @return string|null Company type or null if not authenticated
 */
function getCurrentCompanyType() {
    $user = getCurrentUser();
    return $user ? $user['company_type'] : null;
}

/**
 * Check if current user is authenticated
 * 
 * @return bool True if authenticated
 */
function isAuthenticated() {
    return getCurrentUser() !== null;
}

/**
 * Get authentication method used for current request
 * 
 * @return string|null 'jwt', 'session', or null if not authenticated
 */
function getAuthMethod() {
    $user = getCurrentUser();
    return $user ? ($user['auth_method'] ?? null) : null;
}

/**
 * Check if this is an API request
 * 
 * @return bool True if API request
 */
function isApiRequest() {
    // Check if request path starts with /api/
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($requestUri, '/api/') === 0) {
        return true;
    }
    
    // Check Accept header for JSON
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($acceptHeader, 'application/json') !== false) {
        return true;
    }
    
    // Check Content-Type header for JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        return true;
    }
    
    // Check X-Requested-With header for AJAX
    $xRequestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (strtolower($xRequestedWith) === 'xmlhttprequest') {
        return true;
    }
    
    return false;
}

/**
 * Require specific permission - works with both JWT and session
 * 
 * @param string $permission Permission name (e.g., 'users.create')
 * @param bool $exitOnFailure If true, exit with 403 on failure
 * @return array|null User data if authorized, null if not (when exitOnFailure is false)
 */
function requirePermission($permission, $exitOnFailure = true) {
    $user = requireAuth($exitOnFailure);
    
    if ($user === null) {
        return null;
    }
    
    // Check permission using the global can() function
    if (!can($permission, $user['id'])) {
        if ($exitOnFailure) {
            if (isApiRequest()) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'You do not have permission to perform this action',
                        'required_permission' => $permission
                    ]
                ]);
                exit;
            } else {
                // Redirect to dashboard with error for web requests
                header('Location: /dashboard.php?error=permission_denied');
                exit;
            }
        }
        return null;
    }
    
    return $user;
}

/**
 * Require ADV user access
 * 
 * @param bool $exitOnFailure If true, exit with 403 on failure
 * @return array|null User data if ADV user, null if not (when exitOnFailure is false)
 */
function requireAdvUser($exitOnFailure = true) {
    $user = requireAuth($exitOnFailure);
    
    if ($user === null) {
        return null;
    }
    
    if (strtoupper($user['company_type'] ?? '') !== 'ADV') {
        if ($exitOnFailure) {
            if (isApiRequest()) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'This action requires ADV user access'
                    ]
                ]);
                exit;
            } else {
                header('Location: /dashboard.php?error=adv_required');
                exit;
            }
        }
        return null;
    }
    
    return $user;
}

/**
 * Require company access - enforces company isolation
 * 
 * @param int $companyId Target company ID
 * @param bool $exitOnFailure If true, exit with 403 on failure
 * @return array|null User data if access allowed, null if not (when exitOnFailure is false)
 */
function requireCompanyAccess($companyId, $exitOnFailure = true) {
    $user = requireAuth($exitOnFailure);
    
    if ($user === null) {
        return null;
    }
    
    // ADV users can access any company
    if (strtoupper($user['company_type'] ?? '') === 'ADV') {
        return $user;
    }
    
    // Contractor users can only access their own company
    if ((int)$user['company_id'] !== (int)$companyId) {
        if ($exitOnFailure) {
            if (isApiRequest()) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Access to this company is not allowed'
                    ]
                ]);
                exit;
            } else {
                header('Location: /dashboard.php?error=company_access_denied');
                exit;
            }
        }
        return null;
    }
    
    return $user;
}

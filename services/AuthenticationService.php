<?php
/**
 * Authentication Service
 * Handles secure user authentication, session management, and security features
 * 
 * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5 - Secure authentication and session management
 * Requirements: 2.1, 2.5, 3.1, 3.2 - JWT token generation and management
 * 
 * **Feature: jwt-authentication**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/JWTService.php';
require_once __DIR__ . '/../repositories/RefreshTokenRepository.php';

class AuthenticationService {
    private $userModel;
    private $sessionService;
    private $accountLockoutService;
    private $securityEventService;
    private $ipRestrictionService;
    private $passwordPolicyService;
    private $jwtService;
    private $refreshTokenRepository;
    
    public function __construct() {
        $this->userModel = new User();
        $this->sessionService = new SessionService();
        $this->accountLockoutService = new AccountLockoutService();
        $this->securityEventService = new SecurityEventService();
        $this->ipRestrictionService = new IPRestrictionService();
        $this->passwordPolicyService = new PasswordPolicyService();
        $this->jwtService = new JWTService();
        $this->refreshTokenRepository = new RefreshTokenRepository();
    }
    
    /**
     * Authenticate user with username/email and password
     * Integrates with security services for comprehensive protection
     * 
     * @param string $identifier Username or email
     * @param string $password Plain text password
     * @return array Authentication result
     */
    public function authenticate($identifier, $password) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        try {
            // Check IP restrictions first (skip for localhost/CLI)
            if ($ipAddress !== '127.0.0.1' && $ipAddress !== 'unknown' && $ipAddress !== '::1') {
                $ipCheck = $this->ipRestrictionService->isIPAllowed($ipAddress);
                if (!$ipCheck['allowed']) {
                    $this->securityEventService->logLoginFailed($identifier, $ipAddress, 'IP_BLOCKED');
                    return [
                        'success' => false,
                        'error' => 'IP_BLOCKED',
                        'message' => 'Access denied from this IP address'
                    ];
                }
            }
            
            // Check if account/IP is locked via AccountLockoutService
            $lockStatus = $this->accountLockoutService->isLocked($identifier, $ipAddress);
            if ($lockStatus['locked']) {
                $this->securityEventService->logLoginFailed($identifier, $ipAddress, 'ACCOUNT_LOCKED');
                return [
                    'success' => false,
                    'error' => 'ACCOUNT_LOCKED',
                    'message' => 'Account is temporarily locked. Please try again later.',
                    'lockout_until' => $lockStatus['lockout_until'] ?? null
                ];
            }
            
            // Find user by username or email
            $user = $this->findUserByIdentifier($identifier);
            
            if (!$user) {
                // Record failed attempt
                $this->accountLockoutService->recordAttempt($identifier, $ipAddress, false, 'USER_NOT_FOUND');
                $this->securityEventService->logLoginFailed($identifier, $ipAddress, 'USER_NOT_FOUND');
                
                return [
                    'success' => false,
                    'error' => 'INVALID_CREDENTIALS',
                    'message' => 'Invalid username/email or password'
                ];
            }
            
            // Check if account is locked in database
            if ($this->userModel->isAccountLocked($user['id'])) {
                $this->securityEventService->logLoginFailed($identifier, $ipAddress, 'ACCOUNT_LOCKED_DB');
                return [
                    'success' => false,
                    'error' => 'ACCOUNT_LOCKED',
                    'message' => 'Account is temporarily locked due to multiple failed login attempts'
                ];
            }
            
            // Verify password using password_verify()
            if (!password_verify($password, $user['password_hash'])) {
                // Record failed attempt via AccountLockoutService
                $lockResult = $this->accountLockoutService->recordAttempt($identifier, $ipAddress, false, 'INVALID_PASSWORD');
                
                // Also increment in user model for backward compatibility
                $this->userModel->incrementFailedAttempts($user['id']);
                
                // Check if we should lock the account
                $failedAttempts = ($user['failed_login_attempts'] ?? 0) + 1;
                if ($failedAttempts >= MAX_LOGIN_ATTEMPTS) {
                    $this->userModel->lockAccount($user['id'], LOCKOUT_DURATION);
                }
                
                $this->securityEventService->logLoginFailed($identifier, $ipAddress, 'INVALID_PASSWORD');
                
                return [
                    'success' => false,
                    'error' => 'INVALID_CREDENTIALS',
                    'message' => 'Invalid username/email or password',
                    'attempts_remaining' => $lockResult['attempts_remaining'] ?? null
                ];
            }
            
            // Check if user is active
            if ($user['status'] != USER_STATUS_ACTIVE) {
                $this->securityEventService->logLoginFailed($identifier, $ipAddress, 'ACCOUNT_INACTIVE');
                return [
                    'success' => false,
                    'error' => 'ACCOUNT_INACTIVE',
                    'message' => 'Account is not active'
                ];
            }
            
            // Authentication successful - record success and create session
            $this->accountLockoutService->recordAttempt($identifier, $ipAddress, true);
            $session = $this->sessionService->createSession($user['id']);
            
            // Update last login
            $this->userModel->updateLastLogin($user['id']);
            
            // Generate JWT tokens
            // Requirements: 2.1, 2.5
            $accessToken = $this->jwtService->createAccessToken($user);
            $refreshTokenData = $this->jwtService->createRefreshToken($user['id']);
            
            // Store refresh token in database
            // Requirements: 2.5
            $tokenHash = hash('sha256', $refreshTokenData['token']);
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $this->refreshTokenRepository->store(
                $user['id'],
                $refreshTokenData['token_id'],
                $tokenHash,
                $refreshTokenData['expires_at'],
                $ipAddress,
                $userAgent
            );
            
            // Log successful login
            $this->securityEventService->logLoginSuccess($user['id'], $ipAddress, [
                'username' => $user['username'],
                'company_id' => $user['company_id']
            ]);
            
            return [
                'success' => true,
                'user' => $this->sanitizeUserData($user),
                'session' => $session,
                'access_token' => $accessToken,
                'refresh_token' => $refreshTokenData['token'],
                'token_expires_at' => date('Y-m-d H:i:s', time() + $this->jwtService->getAccessTokenTTL()),
                'refresh_expires_at' => $refreshTokenData['expires_at']
            ];
            
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            $this->securityEventService->logEvent(
                'AUTH_ERROR',
                SecurityEventService::SEVERITY_CRITICAL,
                null,
                $ipAddress,
                ['error' => $e->getMessage(), 'identifier' => $identifier]
            );
            return [
                'success' => false,
                'error' => 'SYSTEM_ERROR',
                'message' => 'An error occurred during authentication'
            ];
        }
    }
    
    /**
     * Logout user and destroy session
     * 
     * @param string $sessionToken Session token
     * @return bool Success status
     */
    public function logout($sessionToken) {
        try {
            $session = $this->sessionService->validateSession($sessionToken);
            $userId = $session ? $session['user_id'] : null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            $result = $this->sessionService->destroySession($sessionToken);
            
            if ($result && $userId) {
                $this->securityEventService->logEvent(
                    SecurityEventService::EVENT_LOGOUT,
                    SecurityEventService::SEVERITY_INFO,
                    $userId,
                    $ipAddress,
                    []
                );
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Logout user with JWT token blacklisting
     * Requirements: 3.1
     * 
     * @param string|null $sessionToken Session token (optional)
     * @param string|null $accessToken JWT access token to blacklist
     * @param string|null $refreshTokenId Refresh token ID to revoke
     * @return array Result with success status
     */
    public function logoutWithJWT($sessionToken = null, $accessToken = null, $refreshTokenId = null) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userId = null;
        
        try {
            // Destroy session if provided
            if ($sessionToken) {
                $session = $this->sessionService->validateSession($sessionToken);
                $userId = $session ? $session['user_id'] : null;
                $this->sessionService->destroySession($sessionToken);
            }
            
            // Blacklist access token if provided
            // Requirements: 3.1
            if ($accessToken) {
                $claims = $this->jwtService->parseToken($accessToken);
                if ($claims && isset($claims['user_id'])) {
                    $userId = $userId ?? $claims['user_id'];
                }
                $this->jwtService->blacklistToken($accessToken);
            }
            
            // Revoke refresh token if provided
            if ($refreshTokenId) {
                $this->refreshTokenRepository->revokeByTokenId($refreshTokenId);
            }
            
            // Log logout event
            if ($userId) {
                $this->securityEventService->logEvent(
                    SecurityEventService::EVENT_LOGOUT,
                    SecurityEventService::SEVERITY_INFO,
                    $userId,
                    $ipAddress,
                    ['jwt_logout' => true]
                );
            }
            
            return ['success' => true, 'message' => 'Logged out successfully'];
            
        } catch (Exception $e) {
            error_log("JWT Logout error: " . $e->getMessage());
            return ['success' => false, 'error' => 'An error occurred during logout'];
        }
    }
    
    /**
     * Refresh access token using a valid refresh token
     * Requirements: 2.3, 2.4
     * 
     * @param string $refreshToken Refresh token
     * @return array Result with new access token or error
     */
    public function refreshToken($refreshToken) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        try {
            // Validate refresh token
            $validationResult = $this->jwtService->validateToken($refreshToken);
            
            if (!$validationResult['valid']) {
                $errorCode = $validationResult['error'];
                
                // Map JWT errors to appropriate response
                if ($errorCode === JWTService::ERROR_TOKEN_EXPIRED) {
                    return [
                        'success' => false,
                        'error' => 'REFRESH_EXPIRED',
                        'message' => 'Refresh token has expired. Please login again.'
                    ];
                }
                
                return [
                    'success' => false,
                    'error' => 'REFRESH_INVALID',
                    'message' => 'Invalid refresh token'
                ];
            }
            
            $claims = $validationResult['claims'];
            
            // Verify this is a refresh token
            if (!isset($claims['type']) || $claims['type'] !== 'refresh') {
                return [
                    'success' => false,
                    'error' => 'REFRESH_INVALID',
                    'message' => 'Token is not a refresh token'
                ];
            }
            
            // Check if token is revoked in database
            $tokenId = $claims['jti'];
            if (!$this->refreshTokenRepository->isValid($tokenId)) {
                return [
                    'success' => false,
                    'error' => 'REFRESH_REVOKED',
                    'message' => 'Refresh token has been revoked'
                ];
            }
            
            // Verify token hash matches
            $tokenHash = hash('sha256', $refreshToken);
            if (!$this->refreshTokenRepository->verifyTokenHash($tokenId, $tokenHash)) {
                return [
                    'success' => false,
                    'error' => 'REFRESH_INVALID',
                    'message' => 'Refresh token verification failed'
                ];
            }
            
            // Get user data
            $userId = $claims['user_id'];
            $user = $this->userModel->findWithRelations($userId);
            
            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'USER_NOT_FOUND',
                    'message' => 'User not found'
                ];
            }
            
            // Check if user is still active
            if ($user['status'] != USER_STATUS_ACTIVE) {
                // Revoke the refresh token since user is no longer active
                $this->refreshTokenRepository->revokeByTokenId($tokenId);
                return [
                    'success' => false,
                    'error' => 'ACCOUNT_INACTIVE',
                    'message' => 'Account is not active'
                ];
            }
            
            // Generate new access token
            $newAccessToken = $this->jwtService->createAccessToken($user);
            
            // Log token refresh
            $this->securityEventService->logEvent(
                'TOKEN_REFRESH',
                SecurityEventService::SEVERITY_INFO,
                $userId,
                $ipAddress,
                ['refresh_token_id' => $tokenId]
            );
            
            return [
                'success' => true,
                'access_token' => $newAccessToken,
                'token_expires_at' => date('Y-m-d H:i:s', time() + $this->jwtService->getAccessTokenTTL()),
                'user' => $this->sanitizeUserData($user)
            ];
            
        } catch (Exception $e) {
            error_log("Token refresh error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'SYSTEM_ERROR',
                'message' => 'An error occurred during token refresh'
            ];
        }
    }
    
    /**
     * Change user password with policy validation
     * Requirements: 3.2 - Revoke all refresh tokens on password change
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return array Result with success status
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        try {
            // Get user
            $user = $this->userModel->find($userId);
            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password_hash'])) {
                $this->securityEventService->logEvent(
                    'PASSWORD_CHANGE_FAILED',
                    SecurityEventService::SEVERITY_WARNING,
                    $userId,
                    $ipAddress,
                    ['reason' => 'INVALID_CURRENT_PASSWORD']
                );
                return ['success' => false, 'error' => 'Current password is incorrect'];
            }
            
            // Validate new password against policy
            $policyResult = $this->passwordPolicyService->validatePassword($newPassword, $userId);
            if (!$policyResult['success']) {
                return [
                    'success' => false,
                    'error' => 'Password does not meet requirements',
                    'errors' => $policyResult['errors']
                ];
            }
            
            // Hash and update password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->userModel->update($userId, ['password_hash' => $newHash]);
            
            // Add to password history
            $this->passwordPolicyService->addToHistory($userId, $newHash);
            
            // Revoke all refresh tokens for this user
            // Requirements: 3.2
            $revokedCount = $this->refreshTokenRepository->revokeByUserId($userId);
            
            // Log event
            $this->securityEventService->logEvent(
                SecurityEventService::EVENT_PASSWORD_CHANGED,
                SecurityEventService::SEVERITY_INFO,
                $userId,
                $ipAddress,
                ['refresh_tokens_revoked' => $revokedCount]
            );
            
            return [
                'success' => true, 
                'message' => 'Password changed successfully',
                'refresh_tokens_revoked' => $revokedCount
            ];
            
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return ['success' => false, 'error' => 'An error occurred'];
        }
    }
    
    /**
     * Validate session token
     * 
     * @param string $sessionToken Session token
     * @return array|null User data if valid, null if invalid
     */
    public function validateSession($sessionToken) {
        try {
            $session = $this->sessionService->validateSession($sessionToken);
            
            if (!$session) {
                return null;
            }
            
            // Get user data
            $user = $this->userModel->findWithRelations($session['user_id']);
            
            if (!$user || $user['status'] != USER_STATUS_ACTIVE) {
                $this->sessionService->destroySession($sessionToken);
                return null;
            }
            
            return $this->sanitizeUserData($user);
            
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Hash password securely using password_hash()
     * 
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password against hash
     * 
     * @param string $password Plain text password
     * @param string $hash Stored password hash
     * @return bool True if password matches
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Find user by username or email
     * 
     * @param string $identifier Username or email
     * @return array|null User data or null
     */
    private function findUserByIdentifier($identifier) {
        // Try email first
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $this->userModel->findByEmail($identifier);
        }
        
        // Try username
        return $this->userModel->findByUsername($identifier);
    }
    
    /**
     * Remove sensitive data from user array
     * 
     * @param array $user User data
     * @return array Sanitized user data
     */
    private function sanitizeUserData($user) {
        unset($user['password_hash']);
        unset($user['failed_login_attempts']);
        unset($user['locked_until']);
        
        return $user;
    }
    
    /**
     * Get password policy requirements
     * 
     * @return array Policy requirements
     */
    public function getPasswordPolicy() {
        return $this->passwordPolicyService->getPolicyRequirements();
    }
    
    /**
     * Validate password against policy
     * 
     * @param string $password Password to validate
     * @param int|null $userId User ID for history check
     * @return array Validation result
     */
    public function validatePassword($password, $userId = null) {
        return $this->passwordPolicyService->validatePassword($password, $userId);
    }
    
    /**
     * Get password strength
     * 
     * @param string $password Password to check
     * @return array Strength info
     */
    public function getPasswordStrength($password) {
        $score = $this->passwordPolicyService->calculatePasswordStrength($password);
        return [
            'score' => $score,
            'label' => $this->passwordPolicyService->getStrengthLabel($score)
        ];
    }
}
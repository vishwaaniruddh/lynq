<?php
/**
 * JWT Cookie Service
 * Handles secure cookie-based storage for JWT tokens in web applications
 * 
 * Requirements: 5.1, 5.2, 5.4, 5.5
 * - 5.1: Set access token in HTTP-only, Secure, SameSite=Strict cookie
 * - 5.2: Set refresh token in separate HTTP-only, Secure, SameSite=Strict cookie
 * - 5.4: Clear both token cookies on logout
 * - 5.5: Refuse to set cookies on non-HTTPS in production
 * 
 * **Feature: jwt-authentication**
 */

require_once __DIR__ . '/../config/autoload.php';

class JWTCookieService {
    /** @var array Configuration settings */
    private $config;
    
    /** @var string Access token cookie name */
    private $accessCookieName;
    
    /** @var string Refresh token cookie name */
    private $refreshCookieName;
    
    /** @var bool Require HTTPS for cookies */
    private $requireSecure;
    
    /** @var string SameSite attribute */
    private $sameSite;
    
    /** @var string Cookie path */
    private $cookiePath;
    
    /** @var string Cookie domain */
    private $cookieDomain;
    
    /** @var bool Development mode flag */
    private $developmentMode;
    
    /**
     * Constructor - loads configuration
     */
    public function __construct() {
        $this->loadConfiguration();
    }
    
    /**
     * Load configuration from config/jwt.php
     */
    private function loadConfiguration(): void {
        $configPath = __DIR__ . '/../config/jwt.php';
        
        if (!file_exists($configPath)) {
            throw new Exception('JWT configuration file not found: ' . $configPath);
        }
        
        $this->config = require $configPath;
        
        $this->accessCookieName = $this->config['cookie_name'] ?? 'adv_access_token';
        $this->refreshCookieName = $this->config['refresh_cookie_name'] ?? 'adv_refresh_token';
        $this->requireSecure = $this->config['cookie_secure'] ?? true;
        $this->sameSite = $this->config['cookie_samesite'] ?? 'Strict';
        $this->cookiePath = $this->config['cookie_path'] ?? '/';
        $this->cookieDomain = $this->config['cookie_domain'] ?? '';
        $this->developmentMode = $this->config['development_mode'] ?? false;
    }
    
    /**
     * Check if the current connection is HTTPS
     * Requirements: 5.5
     * 
     * @return bool True if HTTPS
     */
    public function isSecureConnection(): bool {
        // Check HTTPS server variable
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        
        // Check X-Forwarded-Proto header (for reverse proxies)
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 
            strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        
        // Check port 443
        if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if cookies can be set (HTTPS enforcement)
     * Requirements: 5.5
     * 
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    public function canSetCookies(): array {
        // In development mode, allow non-HTTPS
        if ($this->developmentMode) {
            return ['allowed' => true, 'reason' => null];
        }
        
        // In production, require HTTPS if cookie_secure is true
        if ($this->requireSecure && !$this->isSecureConnection()) {
            return [
                'allowed' => false,
                'reason' => 'HTTPS is required for secure cookies in production'
            ];
        }
        
        return ['allowed' => true, 'reason' => null];
    }
    
    /**
     * Set both access and refresh token cookies
     * Requirements: 5.1, 5.2, 5.5
     * 
     * @param string $accessToken JWT access token
     * @param string $refreshToken JWT refresh token
     * @param int $accessTokenTTL Access token TTL in seconds
     * @param int $refreshTokenTTL Refresh token TTL in seconds
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function setTokenCookies(
        string $accessToken, 
        string $refreshToken, 
        int $accessTokenTTL, 
        int $refreshTokenTTL
    ): array {
        // Check if we can set cookies (HTTPS enforcement)
        $canSet = $this->canSetCookies();
        if (!$canSet['allowed']) {
            return [
                'success' => false,
                'error' => $canSet['reason']
            ];
        }
        
        // Determine if Secure flag should be set
        $secure = $this->requireSecure && $this->isSecureConnection();
        
        // In development mode, don't require secure even if configured
        if ($this->developmentMode && !$this->isSecureConnection()) {
            $secure = false;
        }
        
        // Set access token cookie
        $accessResult = $this->setCookie(
            $this->accessCookieName,
            $accessToken,
            time() + $accessTokenTTL,
            $secure
        );
        
        if (!$accessResult) {
            return [
                'success' => false,
                'error' => 'Failed to set access token cookie'
            ];
        }
        
        // Set refresh token cookie
        $refreshResult = $this->setCookie(
            $this->refreshCookieName,
            $refreshToken,
            time() + $refreshTokenTTL,
            $secure
        );
        
        if (!$refreshResult) {
            return [
                'success' => false,
                'error' => 'Failed to set refresh token cookie'
            ];
        }
        
        return ['success' => true, 'error' => null];
    }
    
    /**
     * Set a single cookie with security attributes
     * Requirements: 5.1, 5.2
     * 
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param int $expires Expiration timestamp
     * @param bool $secure Whether to set Secure flag
     * @return bool Success status
     */
    private function setCookie(string $name, string $value, int $expires, bool $secure): bool {
        // Use setcookie with options array (PHP 7.3+)
        $options = [
            'expires' => $expires,
            'path' => $this->cookiePath,
            'domain' => $this->cookieDomain,
            'secure' => $secure,
            'httponly' => true,  // Requirements: 5.1, 5.2 - HTTP-only
            'samesite' => $this->sameSite  // Requirements: 5.1, 5.2 - SameSite=Strict
        ];
        
        return setcookie($name, $value, $options);
    }
    
    /**
     * Clear both token cookies
     * Requirements: 5.4
     * 
     * @return bool Success status
     */
    public function clearTokenCookies(): bool {
        $accessCleared = $this->clearCookie($this->accessCookieName);
        $refreshCleared = $this->clearCookie($this->refreshCookieName);
        
        return $accessCleared && $refreshCleared;
    }
    
    /**
     * Clear a single cookie
     * 
     * @param string $name Cookie name
     * @return bool Success status
     */
    private function clearCookie(string $name): bool {
        // Set cookie with past expiration to delete it
        $options = [
            'expires' => time() - 3600,
            'path' => $this->cookiePath,
            'domain' => $this->cookieDomain,
            'secure' => $this->requireSecure,
            'httponly' => true,
            'samesite' => $this->sameSite
        ];
        
        // Also unset from $_COOKIE superglobal
        unset($_COOKIE[$name]);
        
        return setcookie($name, '', $options);
    }
    
    /**
     * Get access token from cookie
     * 
     * @return string|null Token or null if not present
     */
    public function getAccessTokenFromCookie(): ?string {
        return $_COOKIE[$this->accessCookieName] ?? null;
    }
    
    /**
     * Get refresh token from cookie
     * 
     * @return string|null Token or null if not present
     */
    public function getRefreshTokenFromCookie(): ?string {
        return $_COOKIE[$this->refreshCookieName] ?? null;
    }
    
    /**
     * Get token from cookie (generic method)
     * 
     * @param string $type 'access' or 'refresh'
     * @return string|null Token or null if not present
     */
    public function getTokenFromCookie(string $type = 'access'): ?string {
        if ($type === 'refresh') {
            return $this->getRefreshTokenFromCookie();
        }
        return $this->getAccessTokenFromCookie();
    }
    
    /**
     * Check if access token cookie exists
     * 
     * @return bool True if cookie exists
     */
    public function hasAccessTokenCookie(): bool {
        return isset($_COOKIE[$this->accessCookieName]) && 
               !empty($_COOKIE[$this->accessCookieName]);
    }
    
    /**
     * Check if refresh token cookie exists
     * 
     * @return bool True if cookie exists
     */
    public function hasRefreshTokenCookie(): bool {
        return isset($_COOKIE[$this->refreshCookieName]) && 
               !empty($_COOKIE[$this->refreshCookieName]);
    }
    
    /**
     * Get cookie configuration for testing/inspection
     * 
     * @return array Cookie configuration
     */
    public function getCookieConfig(): array {
        return [
            'access_cookie_name' => $this->accessCookieName,
            'refresh_cookie_name' => $this->refreshCookieName,
            'secure' => $this->requireSecure,
            'httponly' => true,  // Always true
            'samesite' => $this->sameSite,
            'path' => $this->cookiePath,
            'domain' => $this->cookieDomain,
            'development_mode' => $this->developmentMode
        ];
    }
    
    /**
     * Get cookie attributes that would be set for a token
     * Useful for testing Property 10: Cookie Security Attributes
     * 
     * @param string $type 'access' or 'refresh'
     * @param int $ttl TTL in seconds
     * @return array Cookie attributes
     */
    public function getCookieAttributes(string $type, int $ttl): array {
        $secure = $this->requireSecure;
        
        // In development mode without HTTPS, secure would be false
        if ($this->developmentMode && !$this->isSecureConnection()) {
            $secure = false;
        }
        
        return [
            'name' => $type === 'refresh' ? $this->refreshCookieName : $this->accessCookieName,
            'expires' => time() + $ttl,
            'path' => $this->cookiePath,
            'domain' => $this->cookieDomain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $this->sameSite
        ];
    }
    
    /**
     * Validate cookie security attributes meet requirements
     * Requirements: 5.1, 5.2
     * 
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateSecurityAttributes(): array {
        $errors = [];
        
        // Check HttpOnly is always true (hardcoded)
        // This is always true in our implementation
        
        // Check SameSite is Strict
        if ($this->sameSite !== 'Strict') {
            $errors[] = 'SameSite should be Strict for maximum security';
        }
        
        // In production, check Secure is true
        if (!$this->developmentMode && !$this->requireSecure) {
            $errors[] = 'Secure flag should be true in production';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Set configuration for testing
     * 
     * @param array $config Configuration overrides
     */
    public function setConfig(array $config): void {
        if (isset($config['cookie_name'])) {
            $this->accessCookieName = $config['cookie_name'];
        }
        if (isset($config['refresh_cookie_name'])) {
            $this->refreshCookieName = $config['refresh_cookie_name'];
        }
        if (isset($config['cookie_secure'])) {
            $this->requireSecure = $config['cookie_secure'];
        }
        if (isset($config['cookie_samesite'])) {
            $this->sameSite = $config['cookie_samesite'];
        }
        if (isset($config['cookie_path'])) {
            $this->cookiePath = $config['cookie_path'];
        }
        if (isset($config['cookie_domain'])) {
            $this->cookieDomain = $config['cookie_domain'];
        }
        if (isset($config['development_mode'])) {
            $this->developmentMode = $config['development_mode'];
        }
        
        $this->config = array_merge($this->config, $config);
    }
}

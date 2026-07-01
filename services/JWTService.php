<?php
/**
 * JWT Service
 * Handles JWT token creation, validation, and management
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 2.1, 2.2, 7.1, 7.3, 8.1, 8.2, 8.3, 8.4
 * 
 * **Feature: jwt-authentication**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/TokenBlacklistRepository.php';

class JWTService {
    /** @var array Configuration settings */
    private $config;
    
    /** @var string Secret key for signing */
    private $secret;
    
    /** @var string Algorithm (HS256) */
    private $algorithm;
    
    /** @var string Token issuer */
    private $issuer;
    
    /** @var int Access token TTL in seconds */
    private $accessTokenTTL;
    
    /** @var int Refresh token TTL in seconds */
    private $refreshTokenTTL;
    
    /** @var TokenBlacklistRepository|null Blacklist repository */
    private $blacklistRepository;
    
    /** Error codes */
    const ERROR_TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    const ERROR_TOKEN_INVALID = 'TOKEN_INVALID';
    const ERROR_TOKEN_REVOKED = 'TOKEN_REVOKED';
    const ERROR_TOKEN_MISSING = 'TOKEN_MISSING';
    const ERROR_REFRESH_EXPIRED = 'REFRESH_EXPIRED';
    const ERROR_REFRESH_REVOKED = 'REFRESH_REVOKED';
    const ERROR_CONFIG_ERROR = 'CONFIG_ERROR';
    
    /**
     * Constructor - loads configuration and validates secret
     * 
     * @throws Exception If configuration is invalid
     */
    public function __construct() {
        $this->loadConfiguration();
        $this->validateConfiguration();
    }
    
    /**
     * Load configuration from config/jwt.php
     * Requirements: 7.1
     */
    private function loadConfiguration(): void {
        $configPath = __DIR__ . '/../config/jwt.php';
        
        if (!file_exists($configPath)) {
            throw new Exception('JWT configuration file not found: ' . $configPath);
        }
        
        $this->config = require $configPath;
        
        $this->secret = $this->config['secret'] ?? '';
        $this->algorithm = $this->config['algorithm'] ?? 'HS256';
        $this->issuer = $this->config['issuer'] ?? 'adv-crm';
        $this->accessTokenTTL = $this->config['access_token_ttl'] ?? 1800;
        $this->refreshTokenTTL = $this->config['refresh_token_ttl'] ?? 1209600;
    }
    
    /**
     * Validate configuration settings
     * Requirements: 7.3
     * 
     * @throws Exception If secret key is invalid
     */
    private function validateConfiguration(): void {
        $minSecretLength = 32;
        $isDevelopmentMode = $this->config['development_mode'] ?? false;
        $isDefaultSecret = ($this->secret === 'CHANGE_THIS_IN_PRODUCTION_MIN_32_CHARS');
        
        // Check secret key length
        if (strlen($this->secret) < $minSecretLength) {
            throw new Exception(
                "JWT secret key must be at least {$minSecretLength} characters (256 bits). " .
                "Current length: " . strlen($this->secret)
            );
        }
        
        // Warn about default secret in development mode
        if ($isDefaultSecret && $isDevelopmentMode) {
            error_log('WARNING: Using default JWT secret in development mode. Change this in production!');
        } elseif ($isDefaultSecret && !$isDevelopmentMode) {
            throw new Exception(
                'Default JWT secret detected in non-development mode. ' .
                'Set JWT_SECRET environment variable or update config/jwt.php'
            );
        }
    }
    
    /**
     * Base64URL encode (URL-safe base64)
     * Requirements: 8.2
     * 
     * @param string $data Data to encode
     * @return string Base64URL encoded string
     */
    public function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64URL decode (URL-safe base64)
     * Requirements: 8.3
     * 
     * @param string $data Base64URL encoded string
     * @return string|false Decoded data or false on failure
     */
    public function base64url_decode(string $data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key Configuration key
     * @return mixed Configuration value or null
     */
    public function getConfig(string $key) {
        return $this->config[$key] ?? null;
    }
    
    /**
     * Get access token TTL
     * 
     * @return int TTL in seconds
     */
    public function getAccessTokenTTL(): int {
        return $this->accessTokenTTL;
    }
    
    /**
     * Get refresh token TTL
     * 
     * @return int TTL in seconds
     */
    public function getRefreshTokenTTL(): int {
        return $this->refreshTokenTTL;
    }
    
    /**
     * Create an access token for a user
     * Requirements: 1.1, 1.2, 1.6
     * 
     * @param array $user User data with id, company_id, company_type, role_id, username
     * @return string JWT access token
     */
    public function createAccessToken(array $user): string {
        $now = time();
        
        // Generate unique jti using random_bytes
        $jti = bin2hex(random_bytes(16));
        
        // Build claims
        $claims = [
            'iss' => $this->issuer,
            'sub' => (string)$user['id'],
            'iat' => $now,
            'exp' => $now + $this->accessTokenTTL,
            'jti' => $jti,
            'user_id' => (int)$user['id'],
            'company_id' => (int)($user['company_id'] ?? 0),
            'company_type' => $user['company_type'] ?? '',
            'role_id' => (int)($user['role_id'] ?? 0),
            'username' => $user['username'] ?? ''
        ];
        
        return $this->createToken($claims);
    }
    
    /**
     * Create a JWT token from claims
     * Requirements: 1.6, 8.2
     * 
     * @param array $claims Token claims
     * @return string JWT token string
     */
    private function createToken(array $claims): string {
        // Create header
        $header = [
            'alg' => $this->algorithm,
            'typ' => 'JWT'
        ];
        
        // Encode header and payload
        $headerEncoded = $this->base64url_encode(json_encode($header));
        $payloadEncoded = $this->base64url_encode(json_encode($claims));
        
        // Create signature
        $signatureInput = $headerEncoded . '.' . $payloadEncoded;
        $signature = $this->sign($signatureInput);
        $signatureEncoded = $this->base64url_encode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
    
    /**
     * Sign data using HS256
     * Requirements: 1.6
     * 
     * @param string $data Data to sign
     * @return string Signature
     */
    private function sign(string $data): string {
        return hash_hmac('sha256', $data, $this->secret, true);
    }
    
    /**
     * Create a refresh token for a user
     * Requirements: 2.1, 2.2
     * 
     * @param int $userId User ID
     * @return array ['token' => string, 'token_id' => string, 'expires_at' => string]
     */
    public function createRefreshToken(int $userId): array {
        $now = time();
        
        // Generate unique token_id
        $tokenId = bin2hex(random_bytes(16));
        
        // Build claims
        $claims = [
            'iss' => $this->issuer,
            'sub' => (string)$userId,
            'iat' => $now,
            'exp' => $now + $this->refreshTokenTTL,
            'jti' => $tokenId,
            'type' => 'refresh',
            'user_id' => $userId
        ];
        
        $token = $this->createToken($claims);
        $expiresAt = date('Y-m-d H:i:s', $now + $this->refreshTokenTTL);
        
        return [
            'token' => $token,
            'token_id' => $tokenId,
            'expires_at' => $expiresAt
        ];
    }
    
    /**
     * Validate and decode a token
     * Requirements: 1.3, 1.4, 1.5, 3.3, 3.4
     * 
     * @param string $token JWT token string
     * @return array ['valid' => bool, 'claims' => array|null, 'error' => string|null]
     */
    public function validateToken(string $token): array {
        // Parse the token first
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return [
                'valid' => false,
                'claims' => null,
                'error' => self::ERROR_TOKEN_INVALID
            ];
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        // Verify signature
        $signatureInput = $headerEncoded . '.' . $payloadEncoded;
        $expectedSignature = $this->sign($signatureInput);
        $actualSignature = $this->base64url_decode($signatureEncoded);
        
        if ($actualSignature === false || !hash_equals($expectedSignature, $actualSignature)) {
            return [
                'valid' => false,
                'claims' => null,
                'error' => self::ERROR_TOKEN_INVALID
            ];
        }
        
        // Decode payload
        $payloadJson = $this->base64url_decode($payloadEncoded);
        if ($payloadJson === false) {
            return [
                'valid' => false,
                'claims' => null,
                'error' => self::ERROR_TOKEN_INVALID
            ];
        }
        
        $claims = json_decode($payloadJson, true);
        if ($claims === null) {
            return [
                'valid' => false,
                'claims' => null,
                'error' => self::ERROR_TOKEN_INVALID
            ];
        }
        
        // Check expiration
        if (!isset($claims['exp']) || $claims['exp'] < time()) {
            return [
                'valid' => false,
                'claims' => $claims,
                'error' => self::ERROR_TOKEN_EXPIRED
            ];
        }
        
        // Verify required claims exist
        $requiredClaims = ['iss', 'sub', 'iat', 'exp', 'jti'];
        foreach ($requiredClaims as $claim) {
            if (!isset($claims[$claim])) {
                return [
                    'valid' => false,
                    'claims' => $claims,
                    'error' => self::ERROR_TOKEN_INVALID
                ];
            }
        }
        
        // Verify issuer
        if ($claims['iss'] !== $this->issuer) {
            return [
                'valid' => false,
                'claims' => $claims,
                'error' => self::ERROR_TOKEN_INVALID
            ];
        }
        
        // Check blacklist before returning valid result
        // Requirements: 3.3, 3.4
        if ($this->isBlacklisted($claims['jti'])) {
            return [
                'valid' => false,
                'claims' => $claims,
                'error' => self::ERROR_TOKEN_REVOKED
            ];
        }
        
        return [
            'valid' => true,
            'claims' => $claims,
            'error' => null
        ];
    }
    
    /**
     * Parse token without validation (for blacklist lookup)
     * Requirements: 8.1, 8.3
     * 
     * @param string $token JWT token string
     * @return array|null Decoded claims or null if malformed
     */
    public function parseToken(string $token): ?array {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        // Decode payload
        $payloadJson = $this->base64url_decode($payloadEncoded);
        if ($payloadJson === false) {
            return null;
        }
        
        $claims = json_decode($payloadJson, true);
        if ($claims === null) {
            return null;
        }
        
        return $claims;
    }
    
    /**
     * Parse token header
     * 
     * @param string $token JWT token string
     * @return array|null Decoded header or null if malformed
     */
    public function parseHeader(string $token): ?array {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        $headerJson = $this->base64url_decode($parts[0]);
        if ($headerJson === false) {
            return null;
        }
        
        $header = json_decode($headerJson, true);
        if ($header === null) {
            return null;
        }
        
        return $header;
    }
    
    /**
     * Add token to blacklist
     * Requirements: 3.1
     * 
     * @param string $token JWT token to blacklist
     * @return bool Success status
     */
    public function blacklistToken(string $token): bool {
        // Parse token to get jti and exp
        $claims = $this->parseToken($token);
        
        if ($claims === null) {
            return false;
        }
        
        // Ensure jti and exp exist
        if (!isset($claims['jti']) || !isset($claims['exp'])) {
            return false;
        }
        
        $jti = $claims['jti'];
        $expiresAt = date('Y-m-d H:i:s', $claims['exp']);
        
        // Add to blacklist repository
        return $this->getBlacklistRepository()->add($jti, $expiresAt);
    }
    
    /**
     * Check if token is blacklisted
     * Requirements: 3.3
     * 
     * @param string $jti Token ID (jti claim)
     * @return bool True if blacklisted
     */
    public function isBlacklisted(string $jti): bool {
        return $this->getBlacklistRepository()->isBlacklisted($jti);
    }
    
    /**
     * Get the blacklist repository instance (lazy loading)
     * 
     * @return TokenBlacklistRepository
     */
    private function getBlacklistRepository(): TokenBlacklistRepository {
        if ($this->blacklistRepository === null) {
            $this->blacklistRepository = new TokenBlacklistRepository();
        }
        return $this->blacklistRepository;
    }
    
    /**
     * Set the blacklist repository (for testing/dependency injection)
     * 
     * @param TokenBlacklistRepository $repository
     */
    public function setBlacklistRepository(TokenBlacklistRepository $repository): void {
        $this->blacklistRepository = $repository;
    }
}

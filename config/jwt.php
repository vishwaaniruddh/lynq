<?php
/**
 * JWT Configuration for ADV CRM
 * 
 * This file contains all JWT-related configuration settings.
 * Environment variables can be used to override default values.
 * 
 * Requirements: 7.1, 7.2
 */

return [
    /**
     * Secret key for signing JWT tokens
     * MUST be at least 32 characters (256 bits) for HS256
     * Override with JWT_SECRET environment variable in production
     */
    'secret' => getenv('JWT_SECRET') ?: 'CHANGE_THIS_IN_PRODUCTION_MIN_32_CHARS',
    
    /**
     * Signing algorithm
     * HS256 (HMAC with SHA-256) is used for symmetric signing
     */
    'algorithm' => 'HS256',
    
    /**
     * Token issuer claim (iss)
     */
    'issuer' => getenv('JWT_ISSUER') ?: 'adv-crm',
    
    /**
     * Access token time-to-live in seconds
     * Default: 1800 (30 minutes)
     * Valid range: 900 (15 min) to 3600 (60 min)
     */
    'access_token_ttl' => (int)(getenv('JWT_ACCESS_TOKEN_TTL') ?: 1800),
    
    /**
     * Refresh token time-to-live in seconds
     * Default: 1209600 (14 days)
     * Valid range: 604800 (7 days) to 2592000 (30 days)
     */
    'refresh_token_ttl' => (int)(getenv('JWT_REFRESH_TOKEN_TTL') ?: 1209600),
    
    /**
     * Cookie name for access token (web clients)
     */
    'cookie_name' => getenv('JWT_COOKIE_NAME') ?: 'adv_access_token',
    
    /**
     * Cookie name for refresh token (web clients)
     */
    'refresh_cookie_name' => getenv('JWT_REFRESH_COOKIE_NAME') ?: 'adv_refresh_token',
    
    /**
     * Require HTTPS for cookies (should be true in production)
     */
    'cookie_secure' => filter_var(getenv('JWT_COOKIE_SECURE') ?: true, FILTER_VALIDATE_BOOLEAN),
    
    /**
     * SameSite cookie attribute
     * Options: 'Strict', 'Lax', 'None'
     */
    'cookie_samesite' => getenv('JWT_COOKIE_SAMESITE') ?: 'Strict',
    
    /**
     * Cookie path
     */
    'cookie_path' => getenv('JWT_COOKIE_PATH') ?: '/',
    
    /**
     * Cookie domain (empty string means current domain)
     */
    'cookie_domain' => getenv('JWT_COOKIE_DOMAIN') ?: '',
    
    /**
     * Enable legacy session-based authentication during migration
     * Set to false to disable session auth and use JWT only
     */
    'legacy_session_enabled' => filter_var(getenv('JWT_LEGACY_SESSION_ENABLED') ?: true, FILTER_VALIDATE_BOOLEAN),
    
    /**
     * Development mode flag
     * When true, allows default secret with warning
     * Should be false in production
     */
    'development_mode' => filter_var(getenv('JWT_DEVELOPMENT_MODE') ?: true, FILTER_VALIDATE_BOOLEAN),
];

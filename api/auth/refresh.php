<?php
/**
 * Token Refresh API Endpoint
 * POST /api/auth/refresh.php
 * 
 * Accepts refresh token from cookie or request body and returns a new access token.
 * For web clients, also sets the new access token cookie.
 * 
 * Request: { "refresh_token": "string" } (optional if cookie is present)
 * Response: { "success": bool, "data": { "access_token": "string", ... } }
 * 
 * Requirements: 2.3 - Refresh token produces valid access token
 * 
 * **Feature: jwt-authentication**
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../../services/JWTCookieService.php';
require_once __DIR__ . '/../../services/JWTService.php';
require_once __DIR__ . '/../../services/AuthenticationService.php';
require_once __DIR__ . '/../../api/ApiResponse.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed(['POST']);
}

try {
    // Get refresh token from multiple sources
    $refreshToken = null;
    
    // 1. Try to get from cookie first
    $jwtCookieService = new JWTCookieService();
    $refreshToken = $jwtCookieService->getRefreshTokenFromCookie();
    
    // 2. If not in cookie, try request body
    if (empty($refreshToken)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $refreshToken = $input['refresh_token'] ?? null;
    }
    
    // 3. Also support form data
    if (empty($refreshToken)) {
        $refreshToken = $_POST['refresh_token'] ?? null;
    }
    
    // Validate that we have a refresh token
    if (empty($refreshToken)) {
        ApiResponse::error(
            'TOKEN_MISSING',
            'Refresh token is required. Provide it in the request body or as a cookie.',
            400
        );
    }
    
    // Use AuthenticationService to refresh the token
    $authService = new AuthenticationService();
    $result = $authService->refreshToken($refreshToken);
    
    if ($result['success']) {
        // Get JWT service for TTL values
        $jwtService = new JWTService();
        $accessTokenTTL = $jwtService->getAccessTokenTTL();
        
        // Set new access token cookie for web clients
        // Requirements: 2.3 - Set new access token cookie for web clients
        $cookieResult = $jwtCookieService->setTokenCookies(
            $result['access_token'],
            $refreshToken,  // Keep the same refresh token
            $accessTokenTTL,
            $jwtService->getRefreshTokenTTL()
        );
        
        // Log if cookie setting failed (but don't fail the refresh)
        if (!$cookieResult['success']) {
            error_log("JWT Cookie Warning on refresh: " . ($cookieResult['error'] ?? 'Unknown error'));
        }
        
        ApiResponse::success([
            'access_token' => $result['access_token'],
            'token_expires_at' => $result['token_expires_at'],
            'user' => $result['user'] ?? null
        ], 'Token refreshed successfully');
    } else {
        // Map error codes to appropriate HTTP status
        $errorCode = $result['error'] ?? 'REFRESH_FAILED';
        $message = $result['message'] ?? 'Token refresh failed';
        
        // Determine HTTP status based on error type
        $httpStatus = 401; // Default to unauthorized
        
        if ($errorCode === 'USER_NOT_FOUND' || $errorCode === 'ACCOUNT_INACTIVE') {
            $httpStatus = 403; // Forbidden - user exists but can't access
        } elseif ($errorCode === 'SYSTEM_ERROR') {
            $httpStatus = 500;
        }
        
        ApiResponse::error($errorCode, $message, $httpStatus);
    }
    
} catch (Exception $e) {
    error_log("Token Refresh API Error: " . $e->getMessage());
    ApiResponse::serverError('An unexpected error occurred during token refresh.');
}

<?php
/**
 * Logout Handler
 * Clears session, JWT cookies, and blacklists access token
 * 
 * Requirements: 5.4, 3.1
 * - 5.4: Clear both token cookies on logout
 * - 3.1: Add current access token to blacklist
 * 
 * **Feature: jwt-authentication**
 */

require_once __DIR__ . '/config/autoload.php';
require_once __DIR__ . '/services/JWTCookieService.php';
require_once __DIR__ . '/services/JWTService.php';
require_once __DIR__ . '/services/AuthenticationService.php';

// Initialize services
$sessionService = new SessionService();
$jwtCookieService = new JWTCookieService();
$authService = new AuthenticationService();

// Get access token from cookie before clearing
// Requirements: 3.1 - Blacklist the access token
$accessToken = $jwtCookieService->getAccessTokenFromCookie();
$refreshToken = $jwtCookieService->getRefreshTokenFromCookie();

// Get refresh token ID for revocation
$refreshTokenId = null;
if ($refreshToken) {
    $jwtService = new JWTService();
    $claims = $jwtService->parseToken($refreshToken);
    if ($claims && isset($claims['jti'])) {
        $refreshTokenId = $claims['jti'];
    }
}

// Get session token for logout
$sessionToken = $_SESSION['session_token'] ?? null;

// Perform JWT-aware logout
// Requirements: 3.1, 5.4
$authService->logoutWithJWT($sessionToken, $accessToken, $refreshTokenId);

// Clear JWT cookies
// Requirements: 5.4
$jwtCookieService->clearTokenCookies();

// Also perform traditional session logout for backward compatibility
$sessionService->logout();

// Redirect to login page
header('Location: views/auth/login.php');
exit;

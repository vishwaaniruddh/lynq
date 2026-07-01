<?php
/**
 * Login API Endpoint
 * POST /api/auth/login.php
 * 
 * Request: { "username": "string", "password": "string" }
 * Response: { "success": bool, "message": "string", "data": {...} }
 * 
 * Requirements: 5.1, 5.2 - Set JWT cookies on successful authentication
 * 
 * **Feature: jwt-authentication**
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../../services/JWTCookieService.php';
require_once __DIR__ . '/../../services/JWTService.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed',
        'error' => 'Only POST requests are accepted'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Also support form data
if (empty($input)) {
    $input = [
        'username' => $_POST['username'] ?? '',
        'password' => $_POST['password'] ?? ''
    ];
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

// Validate input
if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed',
        'error' => 'Username and password are required',
        'errors' => [
            'username' => empty($username) ? 'Username is required' : null,
            'password' => empty($password) ? 'Password is required' : null
        ]
    ]);
    exit;
}

try {
    $authService = new AuthenticationService();
    $result = $authService->authenticate($username, $password);
    
    if ($result['success']) {
        // Get user info for response
        $sessionService = new SessionService();
        $user = $sessionService->getCurrentUser();
        
        // Set JWT cookies for web clients
        // Requirements: 5.1, 5.2
        $jwtCookieService = new JWTCookieService();
        $jwtService = new JWTService();
        
        // Get TTL values from JWT service
        $accessTokenTTL = $jwtService->getAccessTokenTTL();
        $refreshTokenTTL = $jwtService->getRefreshTokenTTL();
        
        // Set cookies with access and refresh tokens
        $cookieResult = $jwtCookieService->setTokenCookies(
            $result['access_token'],
            $result['refresh_token'],
            $accessTokenTTL,
            $refreshTokenTTL
        );
        
        // Log if cookie setting failed (but don't fail the login)
        if (!$cookieResult['success']) {
            error_log("JWT Cookie Warning: " . ($cookieResult['error'] ?? 'Unknown error'));
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user['id'] ?? null,
                    'username' => $user['username'] ?? $username,
                    'email' => $user['email'] ?? null,
                    'role' => $user['role_name'] ?? null,
                    'company' => $user['company_name'] ?? null,
                    'company_type' => $user['company_type'] ?? null
                ],
                'redirect' => '../../dashboard.php',
                // Include tokens in response for API clients that don't use cookies
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'token_expires_at' => $result['token_expires_at'],
                'refresh_expires_at' => $result['refresh_expires_at']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentication failed',
            'error' => $result['error'] ?? 'Invalid username or password'
        ]);
    }
} catch (Exception $e) {
    error_log("Login API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'error' => 'An unexpected error occurred. Please try again.'
    ]);
}

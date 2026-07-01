<?php
/**
 * CSRF Protection Middleware
 * Provides CSRF token generation and validation for forms
 */

require_once __DIR__ . '/../config/autoload.php';

class CSRFMiddleware {
    
    /**
     * Generate CSRF token for forms
     * 
     * @return string CSRF token
     */
    public static function generateToken() {
        $sessionService = new SessionService();
        return $sessionService->generateCSRFToken();
    }
    
    /**
     * Validate CSRF token from request
     * Enhanced for API requests with better error handling
     * 
     * @param string $token Token from request (optional, will extract from headers/body if not provided)
     * @return bool True if valid
     * @throws Exception If token validation fails with detailed error
     */
    public function validateToken($token = null) {
        // Extract token if not provided
        if ($token === null) {
            $token = $this->extractTokenFromRequest();
        }
        
        if (empty($token)) {
            throw new Exception('CSRF token is required for this operation');
        }
        
        $sessionService = new SessionService();
        $isValid = $sessionService->validateCSRFToken($token);
        
        if (!$isValid) {
            throw new Exception('CSRF token validation failed - token may be expired or invalid');
        }
        
        return true;
    }
    
    /**
     * Extract CSRF token from various request sources
     * 
     * @return string|null Token or null if not found
     */
    private function extractTokenFromRequest() {
        // Try X-CSRF-Token header first (for AJAX requests)
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        
        // Try request body for JSON requests
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            
            if (strpos($contentType, 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
                if (isset($input['csrf_token'])) {
                    return $input['csrf_token'];
                }
            }
        }
        
        // Try POST data
        if (isset($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }
        
        return null;
    }
    
    /**
     * Middleware function to check CSRF token on POST requests
     * 
     * @param array $requestData Request data (usually $_POST)
     * @return array Result with success status and message
     */
    public static function checkCSRF($requestData = null) {
        // Skip CSRF check for GET requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => true];
        }
        
        $requestData = $requestData ?? $_POST;
        $token = $requestData['csrf_token'] ?? '';
        
        if (!self::validateToken($token)) {
            return [
                'success' => false,
                'error' => 'CSRF_TOKEN_INVALID',
                'message' => 'Invalid or missing CSRF token'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Generate hidden CSRF input field for forms
     * 
     * @return string HTML input field
     */
    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * Get CSRF token for AJAX requests
     * 
     * @return array Token data for JSON response
     */
    public static function getTokenForAjax() {
        return [
            'csrf_token' => self::generateToken()
        ];
    }
}
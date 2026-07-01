<?php
/**
 * API Response Helper
 * Provides consistent JSON response formatting with error handling
 */

class ApiResponse {
    
    /**
     * Send success response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $statusCode HTTP status code
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        self::send($statusCode, [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Send error response
     * 
     * @param string $code Error code
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array $details Additional error details
     */
    public static function error($code, $message, $statusCode = 400, $details = null) {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ],
            'timestamp' => date('c')
        ];
        
        if ($details !== null) {
            $response['error']['details'] = $details;
        }
        
        self::send($statusCode, $response);
    }
    
    /**
     * Send validation error response
     * 
     * @param array $errors Validation errors
     * @param string $message Error message
     */
    public static function validationError($errors, $message = 'Validation failed') {
        self::send(400, [
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $message,
                'details' => $errors
            ],
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Send unauthorized response
     * 
     * @param string $message Error message
     */
    public static function unauthorized($message = 'Authentication required') {
        self::error('UNAUTHORIZED', $message, 401);
    }
    
    /**
     * Send forbidden response
     * 
     * @param string $message Error message
     * @param array $details Additional details
     */
    public static function forbidden($message = 'Access denied', $details = null) {
        self::error('PERMISSION_DENIED', $message, 403, $details);
    }
    
    /**
     * Send not found response
     * 
     * @param string $message Error message
     */
    public static function notFound($message = 'Resource not found') {
        self::error('NOT_FOUND', $message, 404);
    }
    
    /**
     * Send method not allowed response
     * 
     * @param array $allowedMethods Allowed HTTP methods
     */
    public static function methodNotAllowed($allowedMethods = []) {
        if (!empty($allowedMethods)) {
            header('Allow: ' . implode(', ', $allowedMethods));
        }
        self::error('METHOD_NOT_ALLOWED', 'Method not allowed', 405);
    }
    
    /**
     * Send rate limit exceeded response
     * 
     * @param int $retryAfter Seconds until retry is allowed
     */
    public static function rateLimitExceeded($retryAfter = 60) {
        header('Retry-After: ' . $retryAfter);
        self::error('RATE_LIMIT_EXCEEDED', 'Too many requests. Please try again later.', 429);
    }
    
    /**
     * Send server error response
     * 
     * @param string $message Error message
     */
    public static function serverError($message = 'An unexpected error occurred') {
        self::error('SERVER_ERROR', $message, 500);
    }
    
    /**
     * Send JSON response with headers
     * 
     * @param int $statusCode HTTP status code
     * @param array $data Response data
     */
    private static function send($statusCode, $data) {
        // Set security headers
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Set CORS headers for API responses
     * 
     * @param array $allowedOrigins Allowed origins
     * @param array $allowedMethods Allowed HTTP methods
     */
    public static function setCorsHeaders($allowedOrigins = ['*'], $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        
        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        
        header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
        header('Access-Control-Max-Age: 86400');
    }
    
    /**
     * Handle preflight OPTIONS request
     */
    public static function handlePreflight() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            self::setCorsHeaders();
            http_response_code(200);
            exit;
        }
    }
}

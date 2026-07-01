<?php
/**
 * Security Utilities
 * Additional security functions and helpers
 */

class SecurityUtils {
    
    /**
     * Generate secure random string
     * 
     * @param int $length Length of string
     * @return string Random string
     */
    public static function generateSecureRandom($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Hash sensitive data (not passwords)
     * 
     * @param string $data Data to hash
     * @param string $salt Optional salt
     * @return string Hashed data
     */
    public static function hashData($data, $salt = '') {
        return hash('sha256', $data . $salt);
    }
    
    /**
     * Secure string comparison to prevent timing attacks
     * 
     * @param string $known Known string
     * @param string $user User provided string
     * @return bool True if strings match
     */
    public static function secureCompare($known, $user) {
        return hash_equals($known, $user);
    }
    
    /**
     * Rate limiting check
     * 
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if within limits
     */
    public static function checkRateLimit($identifier, $maxAttempts = 10, $timeWindow = 3600) {
        $key = 'rate_limit_' . md5($identifier);
        
        // In a real implementation, you'd use Redis or database
        // For now, using session storage as a simple example
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 1,
                'first_attempt' => time()
            ];
            return true;
        }
        
        $data = $_SESSION[$key];
        
        // Reset if time window has passed
        if (time() - $data['first_attempt'] > $timeWindow) {
            $_SESSION[$key] = [
                'attempts' => 1,
                'first_attempt' => time()
            ];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['attempts'] >= $maxAttempts) {
            return false;
        }
        
        // Increment attempts
        $_SESSION[$key]['attempts']++;
        return true;
    }
    
    /**
     * Log security event
     * 
     * @param string $event Event type
     * @param string $details Event details
     * @param int $userId User ID (optional)
     * @param string $ipAddress IP address (optional)
     */
    public static function logSecurityEvent($event, $details, $userId = null, $ipAddress = null) {
        $ipAddress = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $timestamp = date('Y-m-d H:i:s');
        
        $logEntry = [
            'timestamp' => $timestamp,
            'event' => $event,
            'details' => $details,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        // Log to file (in production, consider using a proper logging system)
        $logFile = __DIR__ . '/../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
        
        // Also log to PHP error log for critical events
        if (in_array($event, ['FAILED_LOGIN', 'ACCOUNT_LOCKED', 'SUSPICIOUS_ACTIVITY'])) {
            error_log("Security Event: {$event} - {$details} - IP: {$ipAddress}");
        }
    }
    
    /**
     * Validate IP address format
     * 
     * @param string $ip IP address
     * @return bool True if valid
     */
    public static function isValidIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * Check if IP is in allowed range (basic implementation)
     * 
     * @param string $ip IP address to check
     * @param array $allowedRanges Array of allowed IP ranges/addresses
     * @return bool True if allowed
     */
    public static function isIPAllowed($ip, $allowedRanges = []) {
        if (empty($allowedRanges)) {
            return true; // No restrictions
        }
        
        foreach ($allowedRanges as $range) {
            if ($ip === $range) {
                return true;
            }
            
            // Basic CIDR check (simplified)
            if (strpos($range, '/') !== false) {
                list($subnet, $mask) = explode('/', $range);
                if ((ip2long($ip) & ~((1 << (32 - $mask)) - 1)) === ip2long($subnet)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Sanitize filename for safe file operations
     * 
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    public static function sanitizeFilename($filename) {
        // Remove directory traversal attempts
        $filename = basename($filename);
        
        // Remove or replace dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }
    
    /**
     * Generate secure headers for HTTP responses
     * 
     * @return array Security headers
     */
    public static function getSecurityHeaders() {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';",
            'Referrer-Policy' => 'strict-origin-when-cross-origin'
        ];
    }
    
    /**
     * Apply security headers to response
     */
    public static function applySecurityHeaders() {
        $headers = self::getSecurityHeaders();
        
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }
}
<?php
/**
 * Password Policy Service
 * Enforces strong password requirements and tracks password history
 * 
 * Requirements: 5.5 - THE User Management System SHALL enforce strong password requirements
 */

require_once __DIR__ . '/../config/autoload.php';

class PasswordPolicyService {
    private $db;
    
    // Password policy configuration
    private $minLength = 8;
    private $maxLength = 128;
    private $requireUppercase = true;
    private $requireLowercase = true;
    private $requireNumbers = true;
    private $requireSpecialChars = true;
    private $passwordHistoryCount = 5; // Number of previous passwords to check
    private $maxConsecutiveChars = 3; // Max consecutive identical characters
    
    // Special characters allowed
    private $specialChars = '!@#$%^&*()_+-=[]{}|;:,.<>?';
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Validate password against policy
     * 
     * @param string $password Password to validate
     * @param int|null $userId User ID for history check (optional)
     * @return array Validation result with success status and errors
     */
    public function validatePassword($password, $userId = null) {
        $errors = [];
        
        // Check minimum length
        if (strlen($password) < $this->minLength) {
            $errors[] = "Password must be at least {$this->minLength} characters long";
        }
        
        // Check maximum length
        if (strlen($password) > $this->maxLength) {
            $errors[] = "Password must not exceed {$this->maxLength} characters";
        }
        
        // Check for uppercase letters
        if ($this->requireUppercase && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        // Check for lowercase letters
        if ($this->requireLowercase && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        // Check for numbers
        if ($this->requireNumbers && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        // Check for special characters
        if ($this->requireSpecialChars && !preg_match('/[' . preg_quote($this->specialChars, '/') . ']/', $password)) {
            $errors[] = "Password must contain at least one special character ({$this->specialChars})";
        }
        
        // Check for consecutive identical characters
        if ($this->hasConsecutiveChars($password, $this->maxConsecutiveChars)) {
            $errors[] = "Password must not contain more than {$this->maxConsecutiveChars} consecutive identical characters";
        }
        
        // Check for common patterns
        $commonPatterns = $this->checkCommonPatterns($password);
        if (!empty($commonPatterns)) {
            $errors = array_merge($errors, $commonPatterns);
        }
        
        // Check password history if user ID provided
        if ($userId !== null && $this->isPasswordInHistory($password, $userId)) {
            $errors[] = "Password has been used recently. Please choose a different password";
        }
        
        return [
            'success' => empty($errors),
            'errors' => $errors,
            'strength' => $this->calculatePasswordStrength($password)
        ];
    }
    
    /**
     * Check if password contains consecutive identical characters
     */
    private function hasConsecutiveChars($password, $maxConsecutive) {
        $length = strlen($password);
        $count = 1;
        
        for ($i = 1; $i < $length; $i++) {
            if ($password[$i] === $password[$i - 1]) {
                $count++;
                if ($count > $maxConsecutive) {
                    return true;
                }
            } else {
                $count = 1;
            }
        }
        
        return false;
    }
    
    /**
     * Check for common weak patterns
     */
    private function checkCommonPatterns($password) {
        $errors = [];
        $lowerPassword = strtolower($password);
        
        // Common weak passwords
        $commonPasswords = [
            'password', '123456', '12345678', 'qwerty', 'abc123',
            'monkey', 'master', 'dragon', 'letmein', 'login',
            'admin', 'welcome', 'password1', 'p@ssw0rd'
        ];
        
        if (in_array($lowerPassword, $commonPasswords)) {
            $errors[] = "Password is too common. Please choose a more unique password";
        }
        
        // Sequential patterns
        $sequentialPatterns = [
            '123456', '234567', '345678', '456789', '567890',
            'abcdef', 'bcdefg', 'cdefgh', 'qwerty', 'asdfgh'
        ];
        
        foreach ($sequentialPatterns as $pattern) {
            if (stripos($password, $pattern) !== false) {
                $errors[] = "Password contains sequential characters. Please avoid patterns like '123456' or 'abcdef'";
                break;
            }
        }
        
        // Keyboard patterns
        $keyboardPatterns = ['qwerty', 'asdfgh', 'zxcvbn', 'qazwsx'];
        foreach ($keyboardPatterns as $pattern) {
            if (stripos($password, $pattern) !== false) {
                $errors[] = "Password contains keyboard patterns. Please avoid patterns like 'qwerty'";
                break;
            }
        }
        
        return $errors;
    }
    
    /**
     * Calculate password strength score (0-100)
     */
    public function calculatePasswordStrength($password) {
        $score = 0;
        $length = strlen($password);
        
        // Length score (up to 30 points)
        $score += min(30, $length * 2);
        
        // Character variety (up to 40 points)
        if (preg_match('/[a-z]/', $password)) $score += 10;
        if (preg_match('/[A-Z]/', $password)) $score += 10;
        if (preg_match('/[0-9]/', $password)) $score += 10;
        if (preg_match('/[' . preg_quote($this->specialChars, '/') . ']/', $password)) $score += 10;
        
        // Bonus for mixing character types (up to 20 points)
        $types = 0;
        if (preg_match('/[a-z]/', $password)) $types++;
        if (preg_match('/[A-Z]/', $password)) $types++;
        if (preg_match('/[0-9]/', $password)) $types++;
        if (preg_match('/[' . preg_quote($this->specialChars, '/') . ']/', $password)) $types++;
        
        if ($types >= 3) $score += 10;
        if ($types >= 4) $score += 10;
        
        // Penalty for consecutive characters
        if ($this->hasConsecutiveChars($password, 2)) $score -= 10;
        
        return max(0, min(100, $score));
    }
    
    /**
     * Get password strength label
     */
    public function getStrengthLabel($score) {
        if ($score >= 80) return 'Very Strong';
        if ($score >= 60) return 'Strong';
        if ($score >= 40) return 'Medium';
        if ($score >= 20) return 'Weak';
        return 'Very Weak';
    }
    
    /**
     * Check if password exists in user's history
     */
    public function isPasswordInHistory($password, $userId) {
        try {
            $sql = "SELECT password_hash FROM password_history 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT ?";
            
            $results = $this->db->getResults($sql, [$userId, $this->passwordHistoryCount], 'ii');
            
            foreach ($results as $row) {
                if (password_verify($password, $row['password_hash'])) {
                    return true;
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Password history check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add password to history
     */
    public function addToHistory($userId, $passwordHash) {
        try {
            // Add new password to history
            $sql = "INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)";
            $stmt = $this->db->executeQuery($sql, [$userId, $passwordHash], 'is');
            $stmt->close();
            
            // Clean up old entries beyond history count
            $sql = "DELETE FROM password_history 
                    WHERE user_id = ? 
                    AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM password_history 
                            WHERE user_id = ? 
                            ORDER BY created_at DESC 
                            LIMIT ?
                        ) AS recent
                    )";
            $stmt = $this->db->executeQuery($sql, [$userId, $userId, $this->passwordHistoryCount], 'iii');
            $stmt->close();
            
            return true;
        } catch (Exception $e) {
            error_log("Add to password history error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get policy requirements as array (for UI display)
     */
    public function getPolicyRequirements() {
        return [
            'minLength' => $this->minLength,
            'maxLength' => $this->maxLength,
            'requireUppercase' => $this->requireUppercase,
            'requireLowercase' => $this->requireLowercase,
            'requireNumbers' => $this->requireNumbers,
            'requireSpecialChars' => $this->requireSpecialChars,
            'specialChars' => $this->specialChars,
            'maxConsecutiveChars' => $this->maxConsecutiveChars,
            'passwordHistoryCount' => $this->passwordHistoryCount
        ];
    }
}

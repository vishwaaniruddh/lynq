<?php
/**
 * Two-Factor Authentication Service (Preparation)
 * Prepares the system for 2FA implementation
 * 
 * Note: This is a preparation service. Full 2FA implementation
 * would require additional libraries (e.g., TOTP library)
 */

require_once __DIR__ . '/../config/autoload.php';

class TwoFactorAuthService {
    private $db;
    private $securityEventService;
    
    // 2FA configuration
    private $backupCodeCount = 10;
    private $backupCodeLength = 8;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->securityEventService = new SecurityEventService();
    }
    
    /**
     * Check if 2FA is enabled for user
     * 
     * @param int $userId User ID
     * @return bool True if 2FA is enabled
     */
    public function isEnabled($userId) {
        try {
            $sql = "SELECT is_enabled FROM user_2fa WHERE user_id = ?";
            $results = $this->db->getResults($sql, [$userId], 'i');
            
            return !empty($results) && $results[0]['is_enabled'] == 1;
            
        } catch (Exception $e) {
            error_log("Check 2FA enabled error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Initialize 2FA for user (generate secret)
     * 
     * @param int $userId User ID
     * @return array|false Setup data or false on failure
     */
    public function initialize($userId) {
        try {
            // Generate a secret key (in production, use a proper TOTP library)
            $secretKey = $this->generateSecretKey();
            $backupCodes = $this->generateBackupCodes();

            // Store in database (not enabled yet)
            $sql = "INSERT INTO user_2fa (user_id, secret_key, backup_codes, is_enabled) 
                    VALUES (?, ?, ?, 0)
                    ON DUPLICATE KEY UPDATE 
                    secret_key = VALUES(secret_key),
                    backup_codes = VALUES(backup_codes),
                    is_enabled = 0";
            
            $stmt = $this->db->executeQuery($sql, [
                $userId,
                $secretKey,
                json_encode($backupCodes)
            ], 'iss');
            $stmt->close();
            
            return [
                'secret_key' => $secretKey,
                'backup_codes' => $backupCodes,
                'qr_code_url' => $this->generateQRCodeUrl($userId, $secretKey)
            ];
            
        } catch (Exception $e) {
            error_log("Initialize 2FA error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enable 2FA after verification
     * 
     * @param int $userId User ID
     * @param string $code Verification code
     * @return bool Success status
     */
    public function enable($userId, $code) {
        try {
            // Get secret key
            $sql = "SELECT secret_key FROM user_2fa WHERE user_id = ?";
            $results = $this->db->getResults($sql, [$userId], 'i');
            
            if (empty($results)) {
                return false;
            }
            
            // Verify code (simplified - in production use proper TOTP verification)
            if (!$this->verifyCode($results[0]['secret_key'], $code)) {
                return false;
            }
            
            // Enable 2FA
            $sql = "UPDATE user_2fa SET is_enabled = 1 WHERE user_id = ?";
            $stmt = $this->db->executeQuery($sql, [$userId], 'i');
            $stmt->close();
            
            // Log event
            $this->securityEventService->logEvent(
                SecurityEventService::EVENT_2FA_ENABLED,
                SecurityEventService::SEVERITY_INFO,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                []
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("Enable 2FA error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Disable 2FA
     * 
     * @param int $userId User ID
     * @param string $password User's password for verification
     * @return bool Success status
     */
    public function disable($userId, $password) {
        try {
            // Verify password first
            $sql = "SELECT password_hash FROM users WHERE id = ?";
            $results = $this->db->getResults($sql, [$userId], 'i');
            
            if (empty($results) || !password_verify($password, $results[0]['password_hash'])) {
                return false;
            }
            
            // Disable 2FA
            $sql = "UPDATE user_2fa SET is_enabled = 0, secret_key = NULL, backup_codes = NULL WHERE user_id = ?";
            $stmt = $this->db->executeQuery($sql, [$userId], 'i');
            $stmt->close();
            
            // Log event
            $this->securityEventService->logEvent(
                SecurityEventService::EVENT_2FA_DISABLED,
                SecurityEventService::SEVERITY_INFO,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                []
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("Disable 2FA error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify 2FA code
     * 
     * @param int $userId User ID
     * @param string $code Code to verify
     * @return bool True if valid
     */
    public function verify($userId, $code) {
        try {
            $sql = "SELECT secret_key, backup_codes FROM user_2fa WHERE user_id = ? AND is_enabled = 1";
            $results = $this->db->getResults($sql, [$userId], 'i');
            
            if (empty($results)) {
                return false;
            }
            
            $data = $results[0];
            
            // Try TOTP code first
            if ($this->verifyCode($data['secret_key'], $code)) {
                $this->updateLastUsed($userId);
                return true;
            }
            
            // Try backup code
            if ($this->verifyBackupCode($userId, $code, $data['backup_codes'])) {
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Verify 2FA error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify TOTP code (simplified implementation)
     * In production, use a proper TOTP library like PHPGangsta/GoogleAuthenticator
     */
    private function verifyCode($secretKey, $code) {
        // Simplified verification - in production use proper TOTP algorithm
        // This is a placeholder that accepts any 6-digit code for testing
        return preg_match('/^\d{6}$/', $code);
    }
    
    /**
     * Verify and consume backup code
     */
    private function verifyBackupCode($userId, $code, $backupCodesJson) {
        $backupCodes = json_decode($backupCodesJson, true) ?? [];
        
        $index = array_search($code, $backupCodes);
        if ($index === false) {
            return false;
        }
        
        // Remove used backup code
        unset($backupCodes[$index]);
        $backupCodes = array_values($backupCodes);
        
        // Update backup codes in database
        $sql = "UPDATE user_2fa SET backup_codes = ? WHERE user_id = ?";
        $stmt = $this->db->executeQuery($sql, [json_encode($backupCodes), $userId], 'si');
        $stmt->close();
        
        return true;
    }
    
    /**
     * Generate new backup codes
     */
    public function regenerateBackupCodes($userId) {
        try {
            $backupCodes = $this->generateBackupCodes();
            
            $sql = "UPDATE user_2fa SET backup_codes = ? WHERE user_id = ?";
            $stmt = $this->db->executeQuery($sql, [json_encode($backupCodes), $userId], 'si');
            $stmt->close();
            
            return $backupCodes;
            
        } catch (Exception $e) {
            error_log("Regenerate backup codes error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate secret key
     */
    private function generateSecretKey() {
        // Generate a base32-encoded secret (16 characters)
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }
    
    /**
     * Generate backup codes
     */
    private function generateBackupCodes() {
        $codes = [];
        for ($i = 0; $i < $this->backupCodeCount; $i++) {
            $codes[] = bin2hex(random_bytes($this->backupCodeLength / 2));
        }
        return $codes;
    }
    
    /**
     * Generate QR code URL (for Google Authenticator)
     */
    private function generateQRCodeUrl($userId, $secretKey) {
        // Get user email
        $sql = "SELECT email FROM users WHERE id = ?";
        $results = $this->db->getResults($sql, [$userId], 'i');
        $email = $results[0]['email'] ?? 'user';
        
        $issuer = 'ADV_CRM';
        $otpauth = "otpauth://totp/{$issuer}:{$email}?secret={$secretKey}&issuer={$issuer}";
        
        // Return URL for QR code generation (use a QR code library or service)
        return "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode($otpauth);
    }
    
    /**
     * Update last used timestamp
     */
    private function updateLastUsed($userId) {
        $sql = "UPDATE user_2fa SET last_used_at = NOW() WHERE user_id = ?";
        $stmt = $this->db->executeQuery($sql, [$userId], 'i');
        $stmt->close();
    }
    
    /**
     * Get 2FA status for user
     */
    public function getStatus($userId) {
        try {
            $sql = "SELECT is_enabled, last_used_at, created_at FROM user_2fa WHERE user_id = ?";
            $results = $this->db->getResults($sql, [$userId], 'i');
            
            if (empty($results)) {
                return ['enabled' => false, 'configured' => false];
            }
            
            return [
                'enabled' => $results[0]['is_enabled'] == 1,
                'configured' => true,
                'last_used' => $results[0]['last_used_at'],
                'created_at' => $results[0]['created_at']
            ];
            
        } catch (Exception $e) {
            error_log("Get 2FA status error: " . $e->getMessage());
            return ['enabled' => false, 'configured' => false];
        }
    }
}

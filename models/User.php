<?php
/**
 * User Model
 */

require_once 'BaseModel.php';

class User extends BaseModel {
    protected $table = 'users';
    protected $fillable = [
        'username', 'email', 'password_hash', 'first_name', 'last_name',
        'company_id', 'role_id', 'status'
    ];
    protected $hidden = ['password_hash'];
    
    /**
     * Find user by email
     */
    public function findByEmail($email) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `email` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$email], 's');
        
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find user by username
     */
    public function findByUsername($username) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `username` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$username], 's');
        
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find users by company
     */
    public function findByCompany($companyId) {
        return $this->findAll(['company_id' => $companyId], 'first_name, last_name');
    }
    
    /**
     * Get user with company and role information
     */
    public function findWithRelations($id) {
        $sql = "SELECT u.*, c.name as company_name, c.type as company_type, 
                       r.name as role_name, r.level as role_level
                FROM `{$this->table}` u
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Update last login timestamp
     */
    public function updateLastLogin($id) {
        $sql = "UPDATE `{$this->table}` SET `last_login` = NOW(), `failed_login_attempts` = 0 WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$id], 'i');
        $stmt->close();
    }
    
    /**
     * Increment failed login attempts
     */
    public function incrementFailedAttempts($id) {
        $sql = "UPDATE `{$this->table}` SET `failed_login_attempts` = `failed_login_attempts` + 1 WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$id], 'i');
        $stmt->close();
    }
    
    /**
     * Lock user account
     */
    public function lockAccount($id, $lockDuration = 900) {
        $lockUntil = date('Y-m-d H:i:s', time() + $lockDuration);
        $sql = "UPDATE `{$this->table}` SET `status` = 2, `locked_until` = ? WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$lockUntil, $id], 'si');
        $stmt->close();
    }
    
    /**
     * Check if account is locked
     */
    public function isAccountLocked($id) {
        $sql = "SELECT `status`, `locked_until` FROM `{$this->table}` WHERE `id` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        
        if (empty($result)) {
            return false;
        }
        
        $user = $result[0];
        
        if ($user['status'] == 2) {
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                return true;
            } else {
                // Unlock account if lock period has expired
                $this->unlockAccount($id);
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Unlock user account
     */
    public function unlockAccount($id) {
        $sql = "UPDATE `{$this->table}` SET `status` = 1, `locked_until` = NULL, `failed_login_attempts` = 0 WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$id], 'i');
        $stmt->close();
    }
}
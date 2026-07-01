<?php
/**
 * LhoManager Model
 * Handles LHO-Manager assignment relationships (many-to-many junction)
 * 
 * Requirements: 1.3 - Persist manager relationships to database
 */

require_once __DIR__ . '/BaseModel.php';

class LhoManager extends BaseModel {
    protected $table = 'lho_managers';
    protected $fillable = [
        'lho_id',
        'user_id',
        'created_by'
    ];
    
    /**
     * Find all managers for a specific LHO
     * 
     * @param int $lhoId LHO ID
     * @return array Manager records with user details
     */
    public function findByLhoId(int $lhoId): array {
        $sql = "SELECT lm.*, 
                       u.first_name, u.last_name, u.email,
                       CONCAT(u.first_name, ' ', u.last_name) as manager_name
                FROM `{$this->table}` lm
                JOIN `users` u ON lm.user_id = u.id
                WHERE lm.lho_id = ?
                ORDER BY u.first_name, u.last_name";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$lhoId], 'i');
    }
    
    /**
     * Find all LHOs managed by a specific user
     * 
     * @param int $userId User ID
     * @return array LHO records
     */
    public function findByUserId(int $userId): array {
        $sql = "SELECT lm.*, 
                       l.lho_name, l.status as lho_status
                FROM `{$this->table}` lm
                JOIN `lhos` l ON lm.lho_id = l.id
                WHERE lm.user_id = ?
                ORDER BY l.lho_name";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$userId], 'i');
    }
    
    /**
     * Check if a user is a manager of a specific LHO
     * 
     * @param int $lhoId LHO ID
     * @param int $userId User ID
     * @return bool True if user is manager of LHO
     */
    public function isManager(int $lhoId, int $userId): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `lho_id` = ? AND `user_id` = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$lhoId, $userId], 'ii');
        return $result[0]['count'] > 0;
    }
    
    /**
     * Remove all manager assignments for an LHO
     * 
     * @param int $lhoId LHO ID
     * @return int Number of deleted records
     */
    public function deleteByLhoId(int $lhoId): int {
        $sql = "DELETE FROM `{$this->table}` WHERE `lho_id` = ?";
        
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$lhoId], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
    }
    
    /**
     * Remove all LHO assignments for a user
     * 
     * @param int $userId User ID
     * @return int Number of deleted records
     */
    public function deleteByUserId(int $userId): int {
        $sql = "DELETE FROM `{$this->table}` WHERE `user_id` = ?";
        
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$userId], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
    }
    
    /**
     * Get manager IDs for an LHO
     * 
     * @param int $lhoId LHO ID
     * @return array Array of user IDs
     */
    public function getManagerIds(int $lhoId): array {
        $sql = "SELECT `user_id` FROM `{$this->table}` WHERE `lho_id` = ?";
        
        $results = DatabaseConfig::getInstance()->getResults($sql, [$lhoId], 'i');
        return array_column($results, 'user_id');
    }
}

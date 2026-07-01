<?php
/**
 * LhoManager Repository
 * Provides data access operations for LHO-Manager assignment relationships
 * 
 * Requirements: 1.3, 1.4, 4.1, 4.2
 * - 1.3: Persist manager relationships to database
 * - 1.4: Replace existing assignments with new selection
 * - 4.1: Remove assignments when user is deleted/deactivated
 * - 4.2: Remove assignments when LHO is deleted
 */

require_once __DIR__ . '/BaseRepository.php';

class LhoManagerRepository extends BaseRepository {
    protected $table = 'lho_managers';
    protected $primaryKey = 'id';
    
    // LHO manager data is global master data, not company-specific
    protected $applyCompanyFilter = false;
    protected $companyIdColumn = null;
    
    /**
     * Get all managers for a specific LHO
     * 
     * @param int $lhoId LHO ID
     * @return array Array of manager records with user details
     * 
     * Requirements: 2.1, 2.2
     */
    public function getManagersByLhoId(int $lhoId): array {
        $sql = "SELECT lm.*, 
                       u.id as user_id,
                       u.first_name, 
                       u.last_name, 
                       u.email,
                       u.status as user_status,
                       CONCAT(u.first_name, ' ', u.last_name) as manager_name
                FROM `{$this->table}` lm
                JOIN `users` u ON lm.user_id = u.id
                WHERE lm.lho_id = ?
                ORDER BY u.first_name, u.last_name";
        
        return $this->db->getResults($sql, [$lhoId], 'i');
    }
    
    /**
     * Get manager IDs for a specific LHO
     * 
     * @param int $lhoId LHO ID
     * @return array Array of user IDs
     */
    public function getManagerIdsByLhoId(int $lhoId): array {
        $sql = "SELECT `user_id` FROM `{$this->table}` WHERE `lho_id` = ?";
        $results = $this->db->getResults($sql, [$lhoId], 'i');
        return array_column($results, 'user_id');
    }
    
    /**
     * Get all LHOs managed by a specific user
     * 
     * @param int $userId User ID
     * @return array Array of LHO records
     * 
     * Requirements: 3.1, 3.2
     */
    public function getLhosByUserId(int $userId): array {
        $sql = "SELECT lm.*, 
                       l.id as lho_id,
                       l.lho_name, 
                       l.status as lho_status
                FROM `{$this->table}` lm
                JOIN `lhos` l ON lm.lho_id = l.id
                WHERE lm.user_id = ?
                ORDER BY l.lho_name";
        
        return $this->db->getResults($sql, [$userId], 'i');
    }

    
    /**
     * Sync managers for an LHO (delete all existing + insert new)
     * This implements the "replace all" behavior for manager assignments
     * 
     * @param int $lhoId LHO ID
     * @param array $userIds Array of user IDs to assign as managers
     * @param int $createdBy ID of user performing the action
     * @return bool True if sync was successful
     * @throws Exception If sync fails
     * 
     * Requirements: 1.3, 1.4
     */
    public function syncManagers(int $lhoId, array $userIds, int $createdBy): bool {
        // Start transaction
        $conn = $this->db->getConnection();
        $conn->begin_transaction();
        
        try {
            // Delete all existing managers for this LHO
            $deleteSql = "DELETE FROM `{$this->table}` WHERE `lho_id` = ?";
            $deleteStmt = $this->db->executeQuery($deleteSql, [$lhoId], 'i');
            $deleteStmt->close();
            
            // Insert new managers
            if (!empty($userIds)) {
                $insertSql = "INSERT INTO `{$this->table}` (`lho_id`, `user_id`, `created_by`) VALUES (?, ?, ?)";
                
                foreach ($userIds as $userId) {
                    $userId = (int)$userId;
                    if ($userId > 0) {
                        $insertStmt = $this->db->executeQuery($insertSql, [$lhoId, $userId, $createdBy], 'iii');
                        $insertStmt->close();
                    }
                }
            }
            
            $conn->commit();
            return true;
            
        } catch (Exception $e) {
            $conn->rollback();
            throw new Exception("Failed to sync managers: " . $e->getMessage());
        }
    }
    
    /**
     * Remove all manager assignments for an LHO
     * 
     * @param int $lhoId LHO ID
     * @return int Number of deleted records
     * 
     * Requirements: 4.2
     */
    public function removeAllByLhoId(int $lhoId): int {
        $sql = "DELETE FROM `{$this->table}` WHERE `lho_id` = ?";
        
        $stmt = $this->db->executeQuery($sql, [$lhoId], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
    }
    
    /**
     * Remove all LHO assignments for a user
     * 
     * @param int $userId User ID
     * @return int Number of deleted records
     * 
     * Requirements: 4.1
     */
    public function removeAllByUserId(int $userId): int {
        $sql = "DELETE FROM `{$this->table}` WHERE `user_id` = ?";
        
        $stmt = $this->db->executeQuery($sql, [$userId], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
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
        
        $result = $this->db->getResults($sql, [$lhoId, $userId], 'ii');
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Add a single manager to an LHO
     * 
     * @param int $lhoId LHO ID
     * @param int $userId User ID
     * @param int $createdBy ID of user performing the action
     * @return int The ID of the newly created record
     * @throws Exception If assignment already exists or creation fails
     */
    public function addManager(int $lhoId, int $userId, int $createdBy): int {
        // Check if assignment already exists
        if ($this->isManager($lhoId, $userId)) {
            throw new Exception("User is already a manager of this LHO");
        }
        
        $sql = "INSERT INTO `{$this->table}` (`lho_id`, `user_id`, `created_by`) VALUES (?, ?, ?)";
        
        $stmt = $this->db->executeQuery($sql, [$lhoId, $userId, $createdBy], 'iii');
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to add manager assignment");
        }
        
        return $insertId;
    }
    
    /**
     * Remove a single manager from an LHO
     * 
     * @param int $lhoId LHO ID
     * @param int $userId User ID
     * @return bool True if removal was successful
     */
    public function removeManager(int $lhoId, int $userId): bool {
        $sql = "DELETE FROM `{$this->table}` WHERE `lho_id` = ? AND `user_id` = ?";
        
        $stmt = $this->db->executeQuery($sql, [$lhoId, $userId], 'ii');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Count managers for an LHO
     * 
     * @param int $lhoId LHO ID
     * @return int Number of managers
     */
    public function countManagersByLhoId(int $lhoId): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `lho_id` = ?";
        $result = $this->db->getResults($sql, [$lhoId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Count LHOs managed by a user
     * 
     * @param int $userId User ID
     * @return int Number of LHOs
     */
    public function countLhosByUserId(int $userId): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `user_id` = ?";
        $result = $this->db->getResults($sql, [$userId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
}

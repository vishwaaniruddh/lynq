<?php
/**
 * ProfileRevisionRepository
 * Provides data access for user profile revision history
 * 
 * Requirements: 8.1, 8.3
 * - 8.1: Create revision records when profile is updated
 * - 8.3: Return revisions sorted by created_at DESC
 */

require_once __DIR__ . '/BaseRepository.php';

class ProfileRevisionRepository extends BaseRepository {
    protected $table = 'profile_revisions';
    protected $primaryKey = 'id';
    protected $companyIdColumn = null; // Revisions use user_id, not company_id
    protected $applyCompanyFilter = false; // Disable company filtering
    
    /**
     * Find all revisions for a specific user sorted by created_at DESC
     * Requirement 8.3
     * 
     * @param int $userId User ID
     * @return array Revisions sorted by created_at DESC
     */
    public function findByUserId(int $userId): array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `user_id` = ? 
                ORDER BY `created_at` DESC";
        
        $results = $this->db->getResults($sql, [$userId], 'i');
        
        // Decode JSON fields
        foreach ($results as &$row) {
            $row['changed_fields'] = json_decode($row['changed_fields'], true) ?? [];
            $row['old_values'] = json_decode($row['old_values'], true) ?? [];
            $row['new_values'] = json_decode($row['new_values'], true) ?? [];
        }
        
        return $results;
    }
    
    /**
     * Create a new profile revision record
     * Requirement 8.1
     * 
     * @param array $data Revision data (user_id, changed_fields, old_values, new_values)
     * @return int The ID of the newly created revision
     * @throws Exception If creation fails
     */
    public function createRevision(array $data): int {
        if (empty($data['user_id'])) {
            throw new Exception("User ID is required");
        }
        
        if (empty($data['changed_fields'])) {
            throw new Exception("Changed fields are required");
        }
        
        $sql = "INSERT INTO `{$this->table}` 
                (`user_id`, `changed_fields`, `old_values`, `new_values`, `created_at`) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $params = [
            $data['user_id'],
            json_encode($data['changed_fields']),
            json_encode($data['old_values'] ?? []),
            json_encode($data['new_values'] ?? [])
        ];
        
        $stmt = $this->db->executeQuery($sql, $params, 'isss');
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create profile revision");
        }
        
        return $insertId;
    }
    
    /**
     * Find a revision by ID and user ID for secure retrieval
     * 
     * @param int $id Revision ID
     * @param int $userId User ID
     * @return array|null Revision data or null if not found/unauthorized
     */
    public function findByIdAndUserId(int $id, int $userId): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `id` = ? AND `user_id` = ?";
        
        $result = $this->db->getResults($sql, [$id, $userId], 'ii');
        
        if (empty($result)) {
            return null;
        }
        
        $row = $result[0];
        $row['changed_fields'] = json_decode($row['changed_fields'], true) ?? [];
        $row['old_values'] = json_decode($row['old_values'], true) ?? [];
        $row['new_values'] = json_decode($row['new_values'], true) ?? [];
        
        return $row;
    }
    
    /**
     * Count revisions for a user
     * 
     * @param int $userId User ID
     * @return int Number of revisions
     */
    public function countByUserId(int $userId): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `user_id` = ?";
        $result = $this->db->getResults($sql, [$userId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Delete all revisions for a user (used for cleanup)
     * 
     * @param int $userId User ID
     * @return bool True if deletion was successful
     */
    public function deleteByUserId(int $userId): bool {
        $sql = "DELETE FROM `{$this->table}` WHERE `user_id` = ?";
        $stmt = $this->db->executeQuery($sql, [$userId], 'i');
        $stmt->close();
        return true;
    }
}

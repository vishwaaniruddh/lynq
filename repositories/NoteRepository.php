<?php
/**
 * NoteRepository
 * Provides data access for personal user notes
 * 
 * Requirements: 5.1, 8.1, 9.1
 * - 5.1: Notes list sorted by updated_at DESC
 * - 8.1: User isolation - return only notes belonging to user
 * - 9.1: Search notes by title or content
 */

require_once __DIR__ . '/BaseRepository.php';

class NoteRepository extends BaseRepository {
    protected $table = 'notes';
    protected $primaryKey = 'id';
    protected $companyIdColumn = null; // Notes use user_id, not company_id
    protected $applyCompanyFilter = false; // Disable company filtering
    
    /**
     * Find all notes for a specific user sorted by updated_at DESC
     * Requirement 5.1, 8.1
     * 
     * @param int $userId User ID
     * @return array Notes sorted by updated_at DESC
     */
    public function findByUserId(int $userId): array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `user_id` = ? 
                ORDER BY `updated_at` DESC";
        
        return $this->db->getResults($sql, [$userId], 'i');
    }
    
    /**
     * Find a note by ID and user ID for secure single note retrieval
     * Requirement 8.1, 8.2
     * 
     * @param int $id Note ID
     * @param int $userId User ID
     * @return array|null Note data or null if not found/unauthorized
     */
    public function findByIdAndUserId(int $id, int $userId): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `id` = ? AND `user_id` = ?";
        
        $result = $this->db->getResults($sql, [$id, $userId], 'ii');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Search notes by title or content (case-insensitive)
     * Requirement 9.1
     * 
     * @param int $userId User ID
     * @param string $term Search term
     * @return array Matching notes sorted by updated_at DESC
     */
    public function search(int $userId, string $term): array {
        $searchTerm = '%' . $term . '%';
        
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `user_id` = ? 
                AND (`title` LIKE ? OR `content` LIKE ?)
                ORDER BY `updated_at` DESC";
        
        return $this->db->getResults($sql, [$userId, $searchTerm, $searchTerm], 'iss');
    }
    
    /**
     * Create a new note
     * Requirement 3.3, 8.3
     * 
     * @param array $data Note data (user_id, title, content)
     * @return int The ID of the newly created note
     * @throws Exception If creation fails
     */
    public function createNote(array $data): int {
        if (empty($data['user_id'])) {
            throw new Exception("User ID is required");
        }
        
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO `{$this->table}` 
                (`user_id`, `title`, `content`, `created_at`, `updated_at`) 
                VALUES (?, ?, ?, ?, ?)";
        
        $params = [
            $data['user_id'],
            $data['title'] ?? '',
            $data['content'] ?? '',
            $now,
            $now
        ];
        
        $stmt = $this->db->executeQuery($sql, $params, 'issss');
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create note");
        }
        
        return $insertId;
    }
    
    /**
     * Update an existing note
     * Requirement 6.3
     * 
     * @param int $id Note ID
     * @param int $userId User ID (for authorization)
     * @param array $data Note data (title, content)
     * @return bool True if update was successful
     * @throws Exception If note not found or unauthorized
     */
    public function updateNote(int $id, int $userId, array $data): bool {
        // Verify note exists and belongs to user
        $existing = $this->findByIdAndUserId($id, $userId);
        if (!$existing) {
            throw new Exception("Note not found or access denied");
        }
        
        $now = date('Y-m-d H:i:s');
        
        $sql = "UPDATE `{$this->table}` 
                SET `title` = ?, `content` = ?, `updated_at` = ? 
                WHERE `id` = ? AND `user_id` = ?";
        
        $params = [
            $data['title'] ?? $existing['title'],
            $data['content'] ?? $existing['content'],
            $now,
            $id,
            $userId
        ];
        
        $stmt = $this->db->executeQuery($sql, $params, 'sssii');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows >= 0; // 0 is valid if no changes made
    }
    
    /**
     * Delete a note
     * Requirement 7.2
     * 
     * @param int $id Note ID
     * @param int $userId User ID (for authorization)
     * @return bool True if deletion was successful
     * @throws Exception If note not found or unauthorized
     */
    public function deleteNote(int $id, int $userId): bool {
        // Verify note exists and belongs to user
        $existing = $this->findByIdAndUserId($id, $userId);
        if (!$existing) {
            throw new Exception("Note not found or access denied");
        }
        
        $sql = "DELETE FROM `{$this->table}` WHERE `id` = ? AND `user_id` = ?";
        
        $stmt = $this->db->executeQuery($sql, [$id, $userId], 'ii');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Count notes for a user
     * 
     * @param int $userId User ID
     * @return int Number of notes
     */
    public function countByUserId(int $userId): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `user_id` = ?";
        $result = $this->db->getResults($sql, [$userId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
}

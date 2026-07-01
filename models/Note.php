<?php
/**
 * Note Model
 * Handles personal user notes for the Notes Module
 * 
 * Requirements: 3.3 - Note persistence with user association
 */

require_once __DIR__ . '/BaseModel.php';

class Note extends BaseModel {
    protected $table = 'notes';
    protected $fillable = [
        'user_id',
        'title',
        'content'
    ];
    
    /**
     * Find all notes for a specific user
     * 
     * @param int $userId User ID
     * @return array Notes sorted by updated_at DESC
     */
    public function findByUserId(int $userId): array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `user_id` = ? 
                ORDER BY `updated_at` DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$userId], 'i');
    }
    
    /**
     * Find a note by ID and user ID (for secure access)
     * 
     * @param int $id Note ID
     * @param int $userId User ID
     * @return array|null Note data or null if not found/unauthorized
     */
    public function findByIdAndUserId(int $id, int $userId): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `id` = ? AND `user_id` = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id, $userId], 'ii');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Search notes by title or content
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
        
        return DatabaseConfig::getInstance()->getResults($sql, [$userId, $searchTerm, $searchTerm], 'iss');
    }
}

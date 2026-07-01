<?php
/**
 * Task Model
 * Handles personal user tasks for the Task Checklist System
 * 
 * Requirements: 1.1, 2.1 - Task creation and user-specific retrieval
 */

require_once __DIR__ . '/BaseModel.php';

class Task extends BaseModel {
    protected $table = 'tasks';
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'is_completed',
        'completed_at'
    ];
    
    /**
     * Find all tasks for a specific user
     * 
     * @param int $userId User ID
     * @return array Tasks sorted by created_at DESC (newest first)
     */
    public function findByUserId(int $userId): array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `user_id` = ? 
                ORDER BY `created_at` DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$userId], 'i');
    }
    
    /**
     * Find a task by ID and user ID (for secure access)
     * 
     * @param int $id Task ID
     * @param int $userId User ID
     * @return array|null Task data or null if not found/unauthorized
     */
    public function findByIdAndUserId(int $id, int $userId): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `id` = ? AND `user_id` = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id, $userId], 'ii');
        return !empty($result) ? $result[0] : null;
    }
}

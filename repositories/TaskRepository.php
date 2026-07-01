<?php
/**
 * TaskRepository
 * Provides data access for personal user tasks
 * 
 * Requirements: 1.1, 2.1, 2.4, 3.1, 3.2, 4.1, 5.1
 * - 1.1: Create new tasks associated with user
 * - 2.1: Return only tasks belonging to user
 * - 2.4: Tasks sorted by created_at DESC (newest first)
 * - 3.1, 3.2: Toggle completion status with timestamp
 * - 4.1: Permanently delete tasks
 * - 5.1: Update task title/description
 */

require_once __DIR__ . '/BaseRepository.php';

class TaskRepository extends BaseRepository {
    protected $table = 'tasks';
    protected $primaryKey = 'id';
    protected $companyIdColumn = null; // Tasks use user_id, not company_id
    protected $applyCompanyFilter = false; // Disable company filtering
    
    /**
     * Find all tasks for a specific user sorted by created_at DESC
     * Requirement 2.1, 2.4
     * 
     * @param int $userId User ID
     * @return array Tasks sorted by created_at DESC (newest first)
     */
    public function findByUserId(int $userId): array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `user_id` = ? 
                ORDER BY `created_at` DESC";
        
        return $this->db->getResults($sql, [$userId], 'i');
    }
    
    /**
     * Find a task by ID and user ID for secure single task retrieval
     * Requirement 2.1
     * 
     * @param int $id Task ID
     * @param int $userId User ID
     * @return array|null Task data or null if not found/unauthorized
     */
    public function findByIdAndUserId(int $id, int $userId): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `id` = ? AND `user_id` = ?";
        
        $result = $this->db->getResults($sql, [$id, $userId], 'ii');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Create a new task
     * Requirement 1.1
     * 
     * @param array $data Task data (user_id, title, description)
     * @return int The ID of the newly created task
     * @throws Exception If creation fails
     */
    public function createTask(array $data): int {
        if (empty($data['user_id'])) {
            throw new Exception("User ID is required");
        }
        
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO `{$this->table}` 
                (`user_id`, `title`, `description`, `is_completed`, `completed_at`, `created_at`, `updated_at`) 
                VALUES (?, ?, ?, 0, NULL, ?, ?)";
        
        $params = [
            $data['user_id'],
            $data['title'] ?? '',
            $data['description'] ?? null,
            $now,
            $now
        ];
        
        $stmt = $this->db->executeQuery($sql, $params, 'issss');
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create task");
        }
        
        return $insertId;
    }
    
    /**
     * Update an existing task
     * Requirement 5.1
     * 
     * @param int $id Task ID
     * @param int $userId User ID (for authorization)
     * @param array $data Task data (title, description)
     * @return bool True if update was successful
     * @throws Exception If task not found or unauthorized
     */
    public function updateTask(int $id, int $userId, array $data): bool {
        // Verify task exists and belongs to user
        $existing = $this->findByIdAndUserId($id, $userId);
        if (!$existing) {
            throw new Exception("Task not found or access denied");
        }
        
        $now = date('Y-m-d H:i:s');
        
        // Use array_key_exists to allow explicit null values for description
        $title = array_key_exists('title', $data) ? $data['title'] : $existing['title'];
        $description = array_key_exists('description', $data) ? $data['description'] : $existing['description'];
        
        $sql = "UPDATE `{$this->table}` 
                SET `title` = ?, `description` = ?, `updated_at` = ? 
                WHERE `id` = ? AND `user_id` = ?";
        
        $params = [
            $title,
            $description,
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
     * Delete a task
     * Requirement 4.1
     * 
     * @param int $id Task ID
     * @param int $userId User ID (for authorization)
     * @return bool True if deletion was successful
     * @throws Exception If task not found or unauthorized
     */
    public function deleteTask(int $id, int $userId): bool {
        // Verify task exists and belongs to user
        $existing = $this->findByIdAndUserId($id, $userId);
        if (!$existing) {
            throw new Exception("Task not found or access denied");
        }
        
        $sql = "DELETE FROM `{$this->table}` WHERE `id` = ? AND `user_id` = ?";
        
        $stmt = $this->db->executeQuery($sql, [$id, $userId], 'ii');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Toggle task completion status
     * Requirement 3.1, 3.2
     * 
     * @param int $id Task ID
     * @param int $userId User ID (for authorization)
     * @return bool True if toggle was successful
     * @throws Exception If task not found or unauthorized
     */
    public function toggleCompletion(int $id, int $userId): bool {
        // Verify task exists and belongs to user
        $existing = $this->findByIdAndUserId($id, $userId);
        if (!$existing) {
            throw new Exception("Task not found or access denied");
        }
        
        $now = date('Y-m-d H:i:s');
        $currentStatus = (int)$existing['is_completed'];
        $newStatus = $currentStatus === 1 ? 0 : 1;
        $completedAt = $newStatus === 1 ? $now : null;
        
        $sql = "UPDATE `{$this->table}` 
                SET `is_completed` = ?, `completed_at` = ?, `updated_at` = ? 
                WHERE `id` = ? AND `user_id` = ?";
        
        $params = [
            $newStatus,
            $completedAt,
            $now,
            $id,
            $userId
        ];
        
        $stmt = $this->db->executeQuery($sql, $params, 'issii');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows >= 0;
    }
    
    /**
     * Count tasks for a user
     * 
     * @param int $userId User ID
     * @return int Number of tasks
     */
    public function countByUserId(int $userId): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `user_id` = ?";
        $result = $this->db->getResults($sql, [$userId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
}

<?php
/**
 * Feasibility ETA Repository
 * Handles database operations for feasibility ETA (Estimated Time of Arrival) records
 * 
 * Requirements: 2.2, 2.5
 */

require_once __DIR__ . '/BaseRepository.php';

class FeasibilityETARepository extends BaseRepository {
    protected $table = 'feasibility_eta';
    protected $primaryKey = 'id';
    protected $companyIdColumn = null; // No direct company relation
    
    /**
     * Create a new ETA record
     * Before creating, marks all previous ETAs for this assignment as not current
     * 
     * @param array $data ETA data (assignment_id, eta_datetime, submitted_by)
     * @return array Created ETA record
     * @throws Exception If required fields are missing or creation fails
     * 
     * Requirements: 2.2
     */
    public function create($data): array {
        $requiredFields = ['assignment_id', 'eta_datetime', 'submitted_by'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new Exception("The {$field} field is required");
            }
        }
        
        // Mark previous ETAs as not current before creating new one
        $this->markPreviousAsNotCurrent((int)$data['assignment_id']);
        
        $sql = "INSERT INTO `{$this->table}` 
                (`assignment_id`, `eta_datetime`, `submitted_by`, `is_current`, `submitted_at`) 
                VALUES (?, ?, ?, TRUE, NOW())";
        
        $stmt = $this->db->executeQuery($sql, [
            (int)$data['assignment_id'],
            $data['eta_datetime'],
            (int)$data['submitted_by']
        ], 'isi');
        
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create ETA record");
        }
        
        return $this->findById($insertId);
    }
    
    /**
     * Find ETA record by ID
     * 
     * @param int $id ETA record ID
     * @return array|null ETA record or null if not found
     */
    public function findById(int $id): ?array {
        $sql = "SELECT e.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as submitted_by_name,
                       u.email as submitted_by_email
                FROM `{$this->table}` e
                LEFT JOIN `users` u ON e.submitted_by = u.id
                WHERE e.id = ?";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find all ETA records for an assignment
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return array All ETA records for the assignment
     * 
     * Requirements: 2.5
     */
    public function findByAssignment(int $assignmentId): array {
        $sql = "SELECT e.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as submitted_by_name,
                       u.email as submitted_by_email
                FROM `{$this->table}` e
                LEFT JOIN `users` u ON e.submitted_by = u.id
                WHERE e.assignment_id = ?
                ORDER BY e.submitted_at DESC";
        
        return $this->db->getResults($sql, [$assignmentId], 'i');
    }
    
    /**
     * Find the current (most recent active) ETA for an assignment
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return array|null Current ETA record or null if none exists
     * 
     * Requirements: 2.2
     */
    public function findCurrentByAssignment(int $assignmentId): ?array {
        $sql = "SELECT e.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as submitted_by_name,
                       u.email as submitted_by_email
                FROM `{$this->table}` e
                LEFT JOIN `users` u ON e.submitted_by = u.id
                WHERE e.assignment_id = ? AND e.is_current = TRUE
                ORDER BY e.submitted_at DESC
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$assignmentId], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get ETA history for an assignment (all ETAs including non-current)
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return array ETA history ordered by submission time (newest first)
     * 
     * Requirements: 2.5
     */
    public function getHistory(int $assignmentId): array {
        $sql = "SELECT e.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as submitted_by_name,
                       u.email as submitted_by_email,
                       CASE WHEN e.is_current = TRUE THEN 'Current' ELSE 'Historical' END as eta_status
                FROM `{$this->table}` e
                LEFT JOIN `users` u ON e.submitted_by = u.id
                WHERE e.assignment_id = ?
                ORDER BY e.submitted_at DESC";
        
        return $this->db->getResults($sql, [$assignmentId], 'i');
    }
    
    /**
     * Mark all previous ETAs for an assignment as not current
     * Called before creating a new ETA to maintain history
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return bool True if update was successful
     * 
     * Requirements: 2.5
     */
    public function markPreviousAsNotCurrent(int $assignmentId): bool {
        $sql = "UPDATE `{$this->table}` 
                SET `is_current` = FALSE, `updated_at` = NOW() 
                WHERE `assignment_id` = ? AND `is_current` = TRUE";
        
        $stmt = $this->db->executeQuery($sql, [$assignmentId], 'i');
        $stmt->close();
        
        return true;
    }
    
    /**
     * Check if an assignment has any ETA submitted
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return bool True if at least one ETA exists
     */
    public function hasETA(int $assignmentId): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `assignment_id` = ?";
        $result = $this->db->getResults($sql, [$assignmentId], 'i');
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Count ETA submissions for an assignment
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return int Number of ETA submissions
     */
    public function countByAssignment(int $assignmentId): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `assignment_id` = ?";
        $result = $this->db->getResults($sql, [$assignmentId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
}

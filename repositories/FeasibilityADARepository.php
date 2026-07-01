<?php
/**
 * Feasibility ADA Repository
 * Handles database operations for feasibility ADA (Actual Date of Arrival) records
 * 
 * Requirements: 3.4
 */

require_once __DIR__ . '/BaseRepository.php';

class FeasibilityADARepository extends BaseRepository {
    protected $table = 'feasibility_ada';
    protected $primaryKey = 'id';
    protected $companyIdColumn = null; // No direct company relation
    
    /**
     * Create a new ADA record
     * Note: Only one ADA per assignment is allowed (unique constraint)
     * 
     * @param array $data ADA data (assignment_id, ada_datetime, latitude, longitude, submitted_by)
     * @return array Created ADA record
     * @throws Exception If required fields are missing, creation fails, or ADA already exists
     * 
     * Requirements: 3.4
     */
    public function create($data): array {
        $requiredFields = ['assignment_id', 'latitude', 'longitude', 'submitted_by'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new Exception("The {$field} field is required");
            }
        }
        
        // Check if ADA already exists for this assignment
        $existing = $this->findByAssignment((int)$data['assignment_id']);
        if ($existing) {
            throw new Exception("ADA already exists for this assignment");
        }
        
        // Use current datetime if not provided
        $adaDatetime = $data['ada_datetime'] ?? date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO `{$this->table}` 
                (`assignment_id`, `ada_datetime`, `latitude`, `longitude`, `submitted_by`, `submitted_at`) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->executeQuery($sql, [
            (int)$data['assignment_id'],
            $adaDatetime,
            (float)$data['latitude'],
            (float)$data['longitude'],
            (int)$data['submitted_by']
        ], 'isddi');
        
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create ADA record");
        }
        
        return $this->findById($insertId);
    }
    
    /**
     * Find ADA record by ID
     * 
     * @param int $id ADA record ID
     * @return array|null ADA record or null if not found
     */
    public function findById(int $id): ?array {
        $sql = "SELECT a.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as submitted_by_name,
                       u.email as submitted_by_email
                FROM `{$this->table}` a
                LEFT JOIN `users` u ON a.submitted_by = u.id
                WHERE a.id = ?";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find ADA record for an assignment
     * Note: Only one ADA per assignment exists due to unique constraint
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return array|null ADA record or null if not found
     * 
     * Requirements: 3.4
     */
    public function findByAssignment(int $assignmentId): ?array {
        $sql = "SELECT a.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as submitted_by_name,
                       u.email as submitted_by_email
                FROM `{$this->table}` a
                LEFT JOIN `users` u ON a.submitted_by = u.id
                WHERE a.assignment_id = ?";
        
        $result = $this->db->getResults($sql, [$assignmentId], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Check if an assignment has ADA submitted
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return bool True if ADA exists
     */
    public function hasADA(int $assignmentId): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `assignment_id` = ?";
        $result = $this->db->getResults($sql, [$assignmentId], 'i');
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Get ADA with assignment and site details
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return array|null ADA record with related data or null
     */
    public function findByAssignmentWithDetails(int $assignmentId): ?array {
        $sql = "SELECT a.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as submitted_by_name,
                       u.email as submitted_by_email,
                       ea.site_id, ea.engineer_id, ea.status as assignment_status,
                       s.site_name, s.lho, s.address, s.city, s.state
                FROM `{$this->table}` a
                LEFT JOIN `users` u ON a.submitted_by = u.id
                LEFT JOIN `engineer_assignments` ea ON a.assignment_id = ea.id
                LEFT JOIN `sites` s ON ea.site_id = s.id
                WHERE a.assignment_id = ?";
        
        $result = $this->db->getResults($sql, [$assignmentId], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find all ADAs submitted by a specific engineer
     * 
     * @param int $engineerId Engineer user ID
     * @return array List of ADA records
     */
    public function findByEngineer(int $engineerId): array {
        $sql = "SELECT a.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as submitted_by_name,
                       ea.site_id,
                       s.site_name, s.lho, s.city
                FROM `{$this->table}` a
                LEFT JOIN `users` u ON a.submitted_by = u.id
                LEFT JOIN `engineer_assignments` ea ON a.assignment_id = ea.id
                LEFT JOIN `sites` s ON ea.site_id = s.id
                WHERE a.submitted_by = ?
                ORDER BY a.submitted_at DESC";
        
        return $this->db->getResults($sql, [$engineerId], 'i');
    }
}

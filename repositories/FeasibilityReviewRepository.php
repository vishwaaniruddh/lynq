<?php
/**
 * Feasibility Review Repository
 * Handles database operations for feasibility review records in the approval workflow
 * 
 * Requirements: 10.2, 12.5
 */

require_once __DIR__ . '/BaseRepository.php';

class FeasibilityReviewRepository extends BaseRepository {
    protected $table = 'feasibility_reviews';
    protected $primaryKey = 'id';
    protected $companyIdColumn = null; // No direct company relation
    
    /**
     * Create a new review record
     * 
     * @param array $data Review data (feasibility_id, reviewer_id, reviewer_role, review_type, etc.)
     * @return array Created review record
     * @throws Exception If required fields are missing or creation fails
     * 
     * Requirements: 10.2
     */
    public function create($data): array {
        $requiredFields = ['feasibility_id', 'reviewer_id', 'reviewer_role', 'review_type'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new Exception("The {$field} field is required");
            }
        }
        
        // Validate reviewer_role
        $validRoles = ['contractor_admin', 'contractor_manager', 'adv'];
        if (!in_array($data['reviewer_role'], $validRoles)) {
            throw new Exception("Invalid reviewer_role. Must be one of: " . implode(', ', $validRoles));
        }
        
        // Validate review_type
        $validTypes = ['approval', 'rejection'];
        if (!in_array($data['review_type'], $validTypes)) {
            throw new Exception("Invalid review_type. Must be one of: " . implode(', ', $validTypes));
        }
        
        // For rejections, validate rejection_type
        if ($data['review_type'] === 'rejection') {
            if (empty($data['rejection_type'])) {
                throw new Exception("rejection_type is required for rejections");
            }
            $validRejectionTypes = ['overall', 'section_specific'];
            if (!in_array($data['rejection_type'], $validRejectionTypes)) {
                throw new Exception("Invalid rejection_type. Must be one of: " . implode(', ', $validRejectionTypes));
            }
        }
        
        // Build dynamic insert query
        $fields = [];
        $placeholders = [];
        $values = [];
        $types = '';
        
        // Define all possible fields with their types
        $fieldTypes = [
            'feasibility_id' => 'i',
            'reviewer_id' => 'i',
            'reviewer_role' => 's',
            'review_type' => 's',
            'rejection_type' => 's',
            'rejected_sections' => 's',
            'reason' => 's',
            'comments' => 's'
        ];
        
        foreach ($fieldTypes as $field => $type) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $fields[] = "`{$field}`";
                $placeholders[] = '?';
                
                // Handle JSON encoding for rejected_sections
                if ($field === 'rejected_sections' && is_array($data[$field])) {
                    $values[] = json_encode($data[$field]);
                } else {
                    $values[] = $type === 'i' ? (int)$data[$field] : $data[$field];
                }
                $types .= $type;
            }
        }
        
        $sql = "INSERT INTO `{$this->table}` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create review record");
        }
        
        return $this->findById($insertId);
    }

    
    /**
     * Find review record by ID
     * 
     * @param int $id Review record ID
     * @return array|null Review record or null if not found
     */
    public function findById(int $id): ?array {
        $sql = "SELECT r.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as reviewer_name,
                       u.email as reviewer_email,
                       fc.assignment_id, fc.site_id
                FROM `{$this->table}` r
                LEFT JOIN `users` u ON r.reviewer_id = u.id
                LEFT JOIN `feasibility_checks` fc ON r.feasibility_id = fc.id
                WHERE r.id = ?";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        
        if (!empty($result)) {
            $record = $result[0];
            // Decode JSON rejected_sections
            if (!empty($record['rejected_sections'])) {
                $record['rejected_sections'] = json_decode($record['rejected_sections'], true);
            }
            return $record;
        }
        
        return null;
    }
    
    /**
     * Find all reviews for a feasibility check
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array All review records for the feasibility check
     * 
     * Requirements: 10.2, 12.5
     */
    public function findByFeasibility(int $feasibilityId): array {
        $sql = "SELECT r.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as reviewer_name,
                       u.email as reviewer_email
                FROM `{$this->table}` r
                LEFT JOIN `users` u ON r.reviewer_id = u.id
                WHERE r.feasibility_id = ?
                ORDER BY r.reviewed_at DESC";
        
        $results = $this->db->getResults($sql, [$feasibilityId], 'i');
        
        // Decode JSON rejected_sections for each record
        foreach ($results as &$record) {
            if (!empty($record['rejected_sections'])) {
                $record['rejected_sections'] = json_decode($record['rejected_sections'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Find the latest (most recent) review for a feasibility check
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array|null Latest review record or null if none exists
     * 
     * Requirements: 10.2
     */
    public function findLatest(int $feasibilityId): ?array {
        $sql = "SELECT r.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as reviewer_name,
                       u.email as reviewer_email
                FROM `{$this->table}` r
                LEFT JOIN `users` u ON r.reviewer_id = u.id
                WHERE r.feasibility_id = ?
                ORDER BY r.reviewed_at DESC
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$feasibilityId], 'i');
        
        if (!empty($result)) {
            $record = $result[0];
            // Decode JSON rejected_sections
            if (!empty($record['rejected_sections'])) {
                $record['rejected_sections'] = json_decode($record['rejected_sections'], true);
            }
            return $record;
        }
        
        return null;
    }
    
    /**
     * Get complete review history for a feasibility check
     * Returns all reviews with timestamps, reviewers, and reasons
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array Review history ordered by review time (newest first)
     * 
     * Requirements: 12.5
     */
    public function getHistory(int $feasibilityId): array {
        $sql = "SELECT r.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as reviewer_name,
                       u.email as reviewer_email,
                       ro.name as reviewer_user_role,
                       CASE 
                           WHEN r.review_type = 'approval' THEN 'Approved'
                           WHEN r.review_type = 'rejection' THEN 'Rejected'
                           ELSE r.review_type
                       END as review_status,
                       CASE 
                           WHEN r.reviewer_role = 'contractor_admin' THEN 'Contractor Admin'
                           WHEN r.reviewer_role = 'contractor_manager' THEN 'Contractor Manager'
                           WHEN r.reviewer_role = 'adv' THEN 'ADV'
                           ELSE r.reviewer_role
                       END as reviewer_role_display
                FROM `{$this->table}` r
                LEFT JOIN `users` u ON r.reviewer_id = u.id
                LEFT JOIN `roles` ro ON u.role_id = ro.id
                WHERE r.feasibility_id = ?
                ORDER BY r.reviewed_at DESC";
        
        $results = $this->db->getResults($sql, [$feasibilityId], 'i');
        
        // Decode JSON rejected_sections for each record
        foreach ($results as &$record) {
            if (!empty($record['rejected_sections'])) {
                $record['rejected_sections'] = json_decode($record['rejected_sections'], true);
            }
        }
        
        return $results;
    }

    
    /**
     * Find reviews by reviewer ID
     * 
     * @param int $reviewerId Reviewer user ID
     * @return array List of review records
     */
    public function findByReviewer(int $reviewerId): array {
        $sql = "SELECT r.*, 
                       fc.assignment_id, fc.site_id,
                       s.site_name, s.lho, s.city
                FROM `{$this->table}` r
                LEFT JOIN `feasibility_checks` fc ON r.feasibility_id = fc.id
                LEFT JOIN `sites` s ON fc.site_id = s.id
                WHERE r.reviewer_id = ?
                ORDER BY r.reviewed_at DESC";
        
        $results = $this->db->getResults($sql, [$reviewerId], 'i');
        
        // Decode JSON rejected_sections for each record
        foreach ($results as &$record) {
            if (!empty($record['rejected_sections'])) {
                $record['rejected_sections'] = json_decode($record['rejected_sections'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Find reviews by reviewer role
     * 
     * @param string $reviewerRole Reviewer role (contractor_admin, contractor_manager, adv)
     * @return array List of review records
     */
    public function findByReviewerRole(string $reviewerRole): array {
        $sql = "SELECT r.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as reviewer_name,
                       u.email as reviewer_email,
                       fc.assignment_id, fc.site_id,
                       s.site_name, s.lho, s.city
                FROM `{$this->table}` r
                LEFT JOIN `users` u ON r.reviewer_id = u.id
                LEFT JOIN `feasibility_checks` fc ON r.feasibility_id = fc.id
                LEFT JOIN `sites` s ON fc.site_id = s.id
                WHERE r.reviewer_role = ?
                ORDER BY r.reviewed_at DESC";
        
        $results = $this->db->getResults($sql, [$reviewerRole], 's');
        
        // Decode JSON rejected_sections for each record
        foreach ($results as &$record) {
            if (!empty($record['rejected_sections'])) {
                $record['rejected_sections'] = json_decode($record['rejected_sections'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Find the latest contractor review for a feasibility check
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array|null Latest contractor review or null
     */
    public function findLatestContractorReview(int $feasibilityId): ?array {
        $sql = "SELECT r.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as reviewer_name,
                       u.email as reviewer_email
                FROM `{$this->table}` r
                LEFT JOIN `users` u ON r.reviewer_id = u.id
                WHERE r.feasibility_id = ? 
                  AND r.reviewer_role IN ('contractor_admin', 'contractor_manager')
                ORDER BY r.reviewed_at DESC
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$feasibilityId], 'i');
        
        if (!empty($result)) {
            $record = $result[0];
            if (!empty($record['rejected_sections'])) {
                $record['rejected_sections'] = json_decode($record['rejected_sections'], true);
            }
            return $record;
        }
        
        return null;
    }
    
    /**
     * Find the latest ADV review for a feasibility check
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array|null Latest ADV review or null
     */
    public function findLatestADVReview(int $feasibilityId): ?array {
        $sql = "SELECT r.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as reviewer_name,
                       u.email as reviewer_email
                FROM `{$this->table}` r
                LEFT JOIN `users` u ON r.reviewer_id = u.id
                WHERE r.feasibility_id = ? 
                  AND r.reviewer_role = 'adv'
                ORDER BY r.reviewed_at DESC
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$feasibilityId], 'i');
        
        if (!empty($result)) {
            $record = $result[0];
            if (!empty($record['rejected_sections'])) {
                $record['rejected_sections'] = json_decode($record['rejected_sections'], true);
            }
            return $record;
        }
        
        return null;
    }
    
    /**
     * Check if a feasibility check has any reviews
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return bool True if at least one review exists
     */
    public function hasReviews(int $feasibilityId): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `feasibility_id` = ?";
        $result = $this->db->getResults($sql, [$feasibilityId], 'i');
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Count reviews for a feasibility check
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return int Number of reviews
     */
    public function countByFeasibility(int $feasibilityId): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `feasibility_id` = ?";
        $result = $this->db->getResults($sql, [$feasibilityId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Count reviews by type for a feasibility check
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array Counts by review type
     */
    public function countByType(int $feasibilityId): array {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN review_type = 'approval' THEN 1 ELSE 0 END) as approvals,
                    SUM(CASE WHEN review_type = 'rejection' THEN 1 ELSE 0 END) as rejections
                FROM `{$this->table}`
                WHERE `feasibility_id` = ?";
        
        $result = $this->db->getResults($sql, [$feasibilityId], 'i');
        
        return [
            'total' => (int)($result[0]['total'] ?? 0),
            'approvals' => (int)($result[0]['approvals'] ?? 0),
            'rejections' => (int)($result[0]['rejections'] ?? 0)
        ];
    }
    
    /**
     * Get all rejections for a feasibility check
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array List of rejection records
     */
    public function getRejections(int $feasibilityId): array {
        $sql = "SELECT r.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as reviewer_name,
                       u.email as reviewer_email
                FROM `{$this->table}` r
                LEFT JOIN `users` u ON r.reviewer_id = u.id
                WHERE r.feasibility_id = ? AND r.review_type = 'rejection'
                ORDER BY r.reviewed_at DESC";
        
        $results = $this->db->getResults($sql, [$feasibilityId], 'i');
        
        // Decode JSON rejected_sections for each record
        foreach ($results as &$record) {
            if (!empty($record['rejected_sections'])) {
                $record['rejected_sections'] = json_decode($record['rejected_sections'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Get the latest rejection for a feasibility check
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array|null Latest rejection record or null
     */
    public function getLatestRejection(int $feasibilityId): ?array {
        $sql = "SELECT r.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as reviewer_name,
                       u.email as reviewer_email
                FROM `{$this->table}` r
                LEFT JOIN `users` u ON r.reviewer_id = u.id
                WHERE r.feasibility_id = ? AND r.review_type = 'rejection'
                ORDER BY r.reviewed_at DESC
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$feasibilityId], 'i');
        
        if (!empty($result)) {
            $record = $result[0];
            if (!empty($record['rejected_sections'])) {
                $record['rejected_sections'] = json_decode($record['rejected_sections'], true);
            }
            return $record;
        }
        
        return null;
    }
}

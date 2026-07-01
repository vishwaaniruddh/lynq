<?php
/**
 * InstallationSectionRemark Repository
 * Provides data access operations for installation section remark records
 * 
 * Requirements: 12.2, 12.3, 13.2
 * - 12.2: Record approval with reviewer ID, timestamp, and optional remarks
 * - 12.3: Require rejection reason (minimum 10 characters)
 * - 13.2: Display previous contractor review comments and approval status
 */

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/InstallationSectionRemark.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class InstallationSectionRemarkRepository extends BaseRepository {
    protected $table = 'installation_section_remarks';
    protected $primaryKey = 'id';
    
    // Remarks don't have direct company_id
    // Company isolation is handled through installation -> site relationship
    protected $companyIdColumn = null;
    protected $applyCompanyFilter = false;
    
    /**
     * Create a new section remark record
     * 
     * @param array $data Remark data
     * @return array|null Created remark record or null on failure
     * @throws Exception If validation fails or creation fails
     * 
     * Requirements: 12.2, 12.3
     */
    public function create($data): ?array {
        // Validate section if provided
        if (isset($data['section']) && !InstallationSections::isValid($data['section'])) {
            throw new Exception("Invalid section: " . $data['section']);
        }
        
        // Validate reviewer level if provided
        if (isset($data['reviewer_level']) && !InstallationSectionRemark::isValidReviewerLevel($data['reviewer_level'])) {
            throw new Exception("Invalid reviewer level: " . $data['reviewer_level']);
        }
        
        // Validate review type if provided
        if (isset($data['review_type']) && !InstallationSectionRemark::isValidReviewType($data['review_type'])) {
            throw new Exception("Invalid review type: " . $data['review_type']);
        }
        
        // Validate rejection reason length (Requirements 12.3)
        if (isset($data['review_type']) && $data['review_type'] === InstallationSectionRemark::TYPE_REJECTION) {
            if (!isset($data['remark']) || strlen(trim($data['remark'])) < InstallationSectionRemark::MIN_REJECTION_REASON_LENGTH) {
                throw new Exception("Rejection reason must be at least " . InstallationSectionRemark::MIN_REJECTION_REASON_LENGTH . " characters");
            }
        }
        
        $fields = ['installation_id', 'section', 'reviewer_id', 'reviewer_level', 'review_type'];
        $values = [
            $data['installation_id'],
            $data['section'],
            $data['reviewer_id'],
            $data['reviewer_level'],
            $data['review_type']
        ];
        $types = 'isiss';
        
        // Add remark if provided
        if (isset($data['remark']) && $data['remark'] !== '') {
            $fields[] = 'remark';
            $values[] = $data['remark'];
            $types .= 's';
        }
        
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $fields) . "`) VALUES ($placeholders)";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId > 0) {
            return $this->find($insertId);
        }
        
        return null;
    }
    
    /**
     * Find remark by ID with reviewer information
     * 
     * @param int $id Remark ID
     * @return array|null Remark record or null if not found
     */
    public function find($id): ?array {
        $sql = "SELECT isr.*, CONCAT(u.first_name, ' ', u.last_name) as reviewer_name
                FROM `{$this->table}` isr
                LEFT JOIN users u ON isr.reviewer_id = u.id
                WHERE isr.id = ?";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find all remarks for an installation
     * 
     * @param int $installationId Installation ID
     * @return array Array of remark records
     * 
     * Requirements: 13.2
     */
    public function findByInstallationId(int $installationId): array {
        $sql = "SELECT isr.*, CONCAT(u.first_name, ' ', u.last_name) as reviewer_name
                FROM `{$this->table}` isr
                LEFT JOIN users u ON isr.reviewer_id = u.id
                WHERE isr.installation_id = ?
                ORDER BY isr.created_at DESC";
        
        return $this->db->getResults($sql, [$installationId], 'i');
    }
    
    /**
     * Find remarks for a specific section of an installation
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @return array Array of remark records
     * 
     * Requirements: 12.2, 13.2
     */
    public function findBySectionAndInstallation(int $installationId, string $section): array {
        // Validate section
        if (!InstallationSections::isValid($section)) {
            throw new Exception("Invalid section: $section");
        }
        
        $sql = "SELECT isr.*, CONCAT(u.first_name, ' ', u.last_name) as reviewer_name
                FROM `{$this->table}` isr
                LEFT JOIN users u ON isr.reviewer_id = u.id
                WHERE isr.installation_id = ? AND isr.section = ?
                ORDER BY isr.created_at DESC";
        
        return $this->db->getResults($sql, [$installationId, $section], 'is');
    }
    
    /**
     * Find remarks by reviewer level for an installation
     * 
     * @param int $installationId Installation ID
     * @param string $level Reviewer level ('contractor' or 'adv')
     * @return array Array of remark records
     * 
     * Requirements: 13.2
     */
    public function findByReviewerLevel(int $installationId, string $level): array {
        // Validate level
        if (!InstallationSectionRemark::isValidReviewerLevel($level)) {
            throw new Exception("Invalid reviewer level: $level");
        }
        
        $sql = "SELECT isr.*, CONCAT(u.first_name, ' ', u.last_name) as reviewer_name
                FROM `{$this->table}` isr
                LEFT JOIN users u ON isr.reviewer_id = u.id
                WHERE isr.installation_id = ? AND isr.reviewer_level = ?
                ORDER BY isr.created_at DESC";
        
        return $this->db->getResults($sql, [$installationId, $level], 'is');
    }
    
    /**
     * Find remarks by review type for an installation
     * 
     * @param int $installationId Installation ID
     * @param string $type Review type ('approval' or 'rejection')
     * @return array Array of remark records
     */
    public function findByReviewType(int $installationId, string $type): array {
        // Validate type
        if (!InstallationSectionRemark::isValidReviewType($type)) {
            throw new Exception("Invalid review type: $type");
        }
        
        $sql = "SELECT isr.*, CONCAT(u.first_name, ' ', u.last_name) as reviewer_name
                FROM `{$this->table}` isr
                LEFT JOIN users u ON isr.reviewer_id = u.id
                WHERE isr.installation_id = ? AND isr.review_type = ?
                ORDER BY isr.created_at DESC";
        
        return $this->db->getResults($sql, [$installationId, $type], 'is');
    }
    
    /**
     * Get the latest remark for a section at a specific reviewer level
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @param string $level Reviewer level ('contractor' or 'adv')
     * @return array|null Latest remark record or null if not found
     */
    public function getLatestSectionRemark(int $installationId, string $section, string $level): ?array {
        // Validate section
        if (!InstallationSections::isValid($section)) {
            throw new Exception("Invalid section: $section");
        }
        
        // Validate level
        if (!InstallationSectionRemark::isValidReviewerLevel($level)) {
            throw new Exception("Invalid reviewer level: $level");
        }
        
        $sql = "SELECT isr.*, CONCAT(u.first_name, ' ', u.last_name) as reviewer_name
                FROM `{$this->table}` isr
                LEFT JOIN users u ON isr.reviewer_id = u.id
                WHERE isr.installation_id = ? AND isr.section = ? AND isr.reviewer_level = ?
                ORDER BY isr.created_at DESC
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$installationId, $section, $level], 'iss');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get rejection remarks for an installation
     * 
     * @param int $installationId Installation ID
     * @return array Array of rejection remark records
     * 
     * Requirements: 14.1
     */
    public function getRejectionRemarks(int $installationId): array {
        $sql = "SELECT isr.*, CONCAT(u.first_name, ' ', u.last_name) as reviewer_name
                FROM `{$this->table}` isr
                LEFT JOIN users u ON isr.reviewer_id = u.id
                WHERE isr.installation_id = ? AND isr.review_type = ?
                ORDER BY isr.created_at DESC";
        
        return $this->db->getResults($sql, [
            $installationId, 
            InstallationSectionRemark::TYPE_REJECTION
        ], 'is');
    }
    
    /**
     * Get approval remarks for an installation
     * 
     * @param int $installationId Installation ID
     * @return array Array of approval remark records
     */
    public function getApprovalRemarks(int $installationId): array {
        $sql = "SELECT isr.*, CONCAT(u.first_name, ' ', u.last_name) as reviewer_name
                FROM `{$this->table}` isr
                LEFT JOIN users u ON isr.reviewer_id = u.id
                WHERE isr.installation_id = ? AND isr.review_type = ?
                ORDER BY isr.created_at DESC";
        
        return $this->db->getResults($sql, [
            $installationId, 
            InstallationSectionRemark::TYPE_APPROVAL
        ], 'is');
    }
    
    /**
     * Get review history grouped by section
     * 
     * @param int $installationId Installation ID
     * @return array Array of remarks grouped by section
     * 
     * Requirements: 13.2
     */
    public function getReviewHistoryBySection(int $installationId): array {
        $remarks = $this->findByInstallationId($installationId);
        
        $grouped = [];
        foreach ($remarks as $remark) {
            $section = $remark['section'];
            if (!isset($grouped[$section])) {
                $grouped[$section] = [];
            }
            $grouped[$section][] = $remark;
        }
        
        return $grouped;
    }
    
    /**
     * Count remarks by type for an installation
     * 
     * @param int $installationId Installation ID
     * @return array Array with counts by review type
     */
    public function countByReviewType(int $installationId): array {
        $sql = "SELECT review_type, COUNT(*) as count 
                FROM `{$this->table}` 
                WHERE installation_id = ? 
                GROUP BY review_type";
        
        $results = $this->db->getResults($sql, [$installationId], 'i');
        
        $counts = [
            InstallationSectionRemark::TYPE_APPROVAL => 0,
            InstallationSectionRemark::TYPE_REJECTION => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $counts[$row['review_type']] = (int)$row['count'];
            $counts['total'] += (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Delete all remarks for an installation
     * 
     * @param int $installationId Installation ID
     * @return bool True if deletion was successful
     */
    public function deleteByInstallationId(int $installationId): bool {
        $sql = "DELETE FROM `{$this->table}` WHERE installation_id = ?";
        $stmt = $this->db->executeQuery($sql, [$installationId], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows >= 0;
    }
    
    /**
     * Delete remarks for a specific section of an installation
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @return bool True if deletion was successful
     */
    public function deleteBySectionAndInstallation(int $installationId, string $section): bool {
        // Validate section
        if (!InstallationSections::isValid($section)) {
            throw new Exception("Invalid section: $section");
        }
        
        $sql = "DELETE FROM `{$this->table}` WHERE installation_id = ? AND section = ?";
        $stmt = $this->db->executeQuery($sql, [$installationId, $section], 'is');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows >= 0;
    }
    
    /**
     * Check if a section has any rejection remarks
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @return bool True if section has rejection remarks
     */
    public function hasSectionRejections(int $installationId, string $section): bool {
        // Validate section
        if (!InstallationSections::isValid($section)) {
            throw new Exception("Invalid section: $section");
        }
        
        $sql = "SELECT COUNT(*) as count 
                FROM `{$this->table}` 
                WHERE installation_id = ? AND section = ? AND review_type = ?";
        
        $result = $this->db->getResults($sql, [
            $installationId, 
            $section, 
            InstallationSectionRemark::TYPE_REJECTION
        ], 'iss');
        
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Get contractor review comments for ADV display
     * 
     * @param int $installationId Installation ID
     * @return array Array of contractor remarks indexed by section
     * 
     * Requirements: 13.2
     */
    public function getContractorReviewsForAdv(int $installationId): array {
        $sql = "SELECT isr.*, CONCAT(u.first_name, ' ', u.last_name) as reviewer_name
                FROM `{$this->table}` isr
                LEFT JOIN users u ON isr.reviewer_id = u.id
                WHERE isr.installation_id = ? AND isr.reviewer_level = ?
                ORDER BY isr.section, isr.created_at DESC";
        
        $results = $this->db->getResults($sql, [
            $installationId, 
            InstallationSectionRemark::LEVEL_CONTRACTOR
        ], 'is');
        
        // Group by section, keeping only the latest remark per section
        $grouped = [];
        foreach ($results as $remark) {
            $section = $remark['section'];
            if (!isset($grouped[$section])) {
                $grouped[$section] = $remark;
            }
        }
        
        return $grouped;
    }
}

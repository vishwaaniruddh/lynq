<?php
/**
 * InstallationCheckpoint Repository
 * Provides data access operations for installation checkpoint records
 * 
 * Requirements: 12.2, 12.4, 12.5, 13.3, 13.4
 * - 12.2: Record approval with reviewer ID, timestamp, and optional remarks
 * - 12.4: Update section status to "rejected" and notify engineer
 * - 12.5: Update installation status to "contractor_approved" when all sections approved
 * - 13.3: Update installation status to "adv_approved" when ADV approves all sections
 * - 13.4: Require rejection reason and update status to "adv_rejected"
 */

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/InstallationCheckpoint.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class InstallationCheckpointRepository extends BaseRepository {
    protected $table = 'installation_checkpoints';
    protected $primaryKey = 'id';
    
    // Checkpoints don't have direct company_id
    // Company isolation is handled through installation -> site relationship
    protected $companyIdColumn = null;
    protected $applyCompanyFilter = false;
    
    /**
     * Create checkpoint records for all sections of an installation
     * 
     * @param int $installationId Installation ID
     * @return array Array of created checkpoint records
     * @throws Exception If creation fails
     * 
     * Requirements: 12.1
     */
    public function createForInstallation(int $installationId): array {
        $sections = InstallationSections::getAll();
        $createdCheckpoints = [];
        
        foreach ($sections as $section) {
            // Check if checkpoint already exists
            $existing = $this->findByInstallationAndSection($installationId, $section);
            if ($existing) {
                $createdCheckpoints[] = $existing;
                continue;
            }
            
            $data = [
                'installation_id' => $installationId,
                'section' => $section,
                'contractor_status' => InstallationCheckpoint::STATUS_PENDING,
                'adv_status' => InstallationCheckpoint::STATUS_PENDING
            ];
            
            $sql = "INSERT INTO `{$this->table}` 
                    (`installation_id`, `section`, `contractor_status`, `adv_status`) 
                    VALUES (?, ?, ?, ?)";
            
            $stmt = $this->db->executeQuery($sql, [
                $installationId,
                $section,
                InstallationCheckpoint::STATUS_PENDING,
                InstallationCheckpoint::STATUS_PENDING
            ], 'isss');
            
            $insertId = $this->db->getConnection()->insert_id;
            $stmt->close();
            
            if ($insertId > 0) {
                $createdCheckpoints[] = $this->find($insertId);
            }
        }
        
        return $createdCheckpoints;
    }
    
    /**
     * Update section status for contractor or ADV level
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @param string $level Reviewer level ('contractor' or 'adv')
     * @param string $status New status ('pending', 'approved', 'rejected')
     * @param int $reviewerId Reviewer user ID
     * @return array|null Updated checkpoint record or null if not found
     * @throws Exception If update fails or invalid parameters
     * 
     * Requirements: 12.2, 12.4, 13.3, 13.4
     */
    public function updateSectionStatus(
        int $installationId, 
        string $section, 
        string $level, 
        string $status, 
        int $reviewerId
    ): ?array {
        // Validate section
        if (!InstallationSections::isValid($section)) {
            throw new Exception("Invalid section: $section");
        }
        
        // Validate level
        if (!InstallationCheckpoint::isValidReviewerLevel($level)) {
            throw new Exception("Invalid reviewer level: $level");
        }
        
        // Validate status
        if (!InstallationCheckpoint::isValidStatus($status)) {
            throw new Exception("Invalid status: $status");
        }
        
        // Find existing checkpoint
        $checkpoint = $this->findByInstallationAndSection($installationId, $section);
        if (!$checkpoint) {
            // Create checkpoint if it doesn't exist
            $this->createForInstallation($installationId);
            $checkpoint = $this->findByInstallationAndSection($installationId, $section);
            if (!$checkpoint) {
                throw new Exception("Failed to create checkpoint for section: $section");
            }
        }
        
        // Build update query based on level
        $statusField = $level === InstallationCheckpoint::LEVEL_CONTRACTOR 
            ? 'contractor_status' 
            : 'adv_status';
        $reviewerField = $level === InstallationCheckpoint::LEVEL_CONTRACTOR 
            ? 'contractor_reviewer_id' 
            : 'adv_reviewer_id';
        $reviewedAtField = $level === InstallationCheckpoint::LEVEL_CONTRACTOR 
            ? 'contractor_reviewed_at' 
            : 'adv_reviewed_at';
        
        $sql = "UPDATE `{$this->table}` 
                SET `$statusField` = ?, 
                    `$reviewerField` = ?, 
                    `$reviewedAtField` = NOW() 
                WHERE `installation_id` = ? AND `section` = ?";
        
        $stmt = $this->db->executeQuery($sql, [$status, $reviewerId, $installationId, $section], 'siis');
        $stmt->close();
        
        return $this->findByInstallationAndSection($installationId, $section);
    }
    
    /**
     * Get section status for a specific installation and section
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @return array|null Checkpoint record or null if not found
     * 
     * Requirements: 12.2
     */
    public function getSectionStatus(int $installationId, string $section): ?array {
        return $this->findByInstallationAndSection($installationId, $section);
    }
    
    /**
     * Get all section statuses for an installation
     * 
     * @param int $installationId Installation ID
     * @return array Array of checkpoint records indexed by section
     * 
     * Requirements: 12.5, 13.3
     */
    public function getAllSectionStatuses(int $installationId): array {
        $sql = "SELECT ic.*, 
                       CONCAT(cu.first_name, ' ', cu.last_name) as contractor_reviewer_name,
                       CONCAT(au.first_name, ' ', au.last_name) as adv_reviewer_name
                FROM `{$this->table}` ic
                LEFT JOIN users cu ON ic.contractor_reviewer_id = cu.id
                LEFT JOIN users au ON ic.adv_reviewer_id = au.id
                WHERE ic.installation_id = ?
                ORDER BY ic.section";
        
        $results = $this->db->getResults($sql, [$installationId], 'i');
        
        // Index by section for easy access
        $indexed = [];
        foreach ($results as $row) {
            $indexed[$row['section']] = $row;
        }
        
        return $indexed;
    }
    
    /**
     * Find checkpoint by installation ID and section
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @return array|null Checkpoint record or null if not found
     */
    public function findByInstallationAndSection(int $installationId, string $section): ?array {
        $sql = "SELECT ic.*, 
                       CONCAT(cu.first_name, ' ', cu.last_name) as contractor_reviewer_name,
                       CONCAT(au.first_name, ' ', au.last_name) as adv_reviewer_name
                FROM `{$this->table}` ic
                LEFT JOIN users cu ON ic.contractor_reviewer_id = cu.id
                LEFT JOIN users au ON ic.adv_reviewer_id = au.id
                WHERE ic.installation_id = ? AND ic.section = ?";
        
        $result = $this->db->getResults($sql, [$installationId, $section], 'is');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Check if all sections are approved at contractor level
     * 
     * @param int $installationId Installation ID
     * @return bool True if all sections are approved
     * 
     * Requirements: 12.5
     */
    public function areAllSectionsContractorApproved(int $installationId): bool {
        $allSections = InstallationSections::getAll();
        $totalSections = count($allSections);
        
        $sql = "SELECT COUNT(*) as approved_count 
                FROM `{$this->table}` 
                WHERE installation_id = ? AND contractor_status = ?";
        
        $result = $this->db->getResults($sql, [
            $installationId, 
            InstallationCheckpoint::STATUS_APPROVED
        ], 'is');
        
        $approvedCount = (int)($result[0]['approved_count'] ?? 0);
        
        return $approvedCount >= $totalSections;
    }
    
    /**
     * Check if all sections are approved at ADV level
     * 
     * @param int $installationId Installation ID
     * @return bool True if all sections are approved
     * 
     * Requirements: 13.3
     */
    public function areAllSectionsAdvApproved(int $installationId): bool {
        $allSections = InstallationSections::getAll();
        $totalSections = count($allSections);
        
        $sql = "SELECT COUNT(*) as approved_count 
                FROM `{$this->table}` 
                WHERE installation_id = ? AND adv_status = ?";
        
        $result = $this->db->getResults($sql, [
            $installationId, 
            InstallationCheckpoint::STATUS_APPROVED
        ], 'is');
        
        $approvedCount = (int)($result[0]['approved_count'] ?? 0);
        
        return $approvedCount >= $totalSections;
    }
    
    /**
     * Check if any section is rejected at contractor level
     * 
     * @param int $installationId Installation ID
     * @return bool True if any section is rejected
     * 
     * Requirements: 12.4
     */
    public function hasContractorRejectedSections(int $installationId): bool {
        $sql = "SELECT COUNT(*) as rejected_count 
                FROM `{$this->table}` 
                WHERE installation_id = ? AND contractor_status = ?";
        
        $result = $this->db->getResults($sql, [
            $installationId, 
            InstallationCheckpoint::STATUS_REJECTED
        ], 'is');
        
        return (int)($result[0]['rejected_count'] ?? 0) > 0;
    }
    
    /**
     * Check if any section is rejected at ADV level
     * 
     * @param int $installationId Installation ID
     * @return bool True if any section is rejected
     * 
     * Requirements: 13.4
     */
    public function hasAdvRejectedSections(int $installationId): bool {
        $sql = "SELECT COUNT(*) as rejected_count 
                FROM `{$this->table}` 
                WHERE installation_id = ? AND adv_status = ?";
        
        $result = $this->db->getResults($sql, [
            $installationId, 
            InstallationCheckpoint::STATUS_REJECTED
        ], 'is');
        
        return (int)($result[0]['rejected_count'] ?? 0) > 0;
    }
    
    /**
     * Get rejected sections at contractor level
     * 
     * @param int $installationId Installation ID
     * @return array Array of rejected checkpoint records
     * 
     * Requirements: 12.4, 14.1
     */
    public function getContractorRejectedSections(int $installationId): array {
        $sql = "SELECT ic.*, 
                       CONCAT(cu.first_name, ' ', cu.last_name) as contractor_reviewer_name
                FROM `{$this->table}` ic
                LEFT JOIN users cu ON ic.contractor_reviewer_id = cu.id
                WHERE ic.installation_id = ? AND ic.contractor_status = ?
                ORDER BY ic.section";
        
        return $this->db->getResults($sql, [
            $installationId, 
            InstallationCheckpoint::STATUS_REJECTED
        ], 'is');
    }
    
    /**
     * Get rejected sections at ADV level
     * 
     * @param int $installationId Installation ID
     * @return array Array of rejected checkpoint records
     * 
     * Requirements: 13.4, 14.1
     */
    public function getAdvRejectedSections(int $installationId): array {
        $sql = "SELECT ic.*, 
                       CONCAT(au.first_name, ' ', au.last_name) as adv_reviewer_name
                FROM `{$this->table}` ic
                LEFT JOIN users au ON ic.adv_reviewer_id = au.id
                WHERE ic.installation_id = ? AND ic.adv_status = ?
                ORDER BY ic.section";
        
        return $this->db->getResults($sql, [
            $installationId, 
            InstallationCheckpoint::STATUS_REJECTED
        ], 'is');
    }
    
    /**
     * Reset section status to pending (for resubmission)
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @param string $level Reviewer level ('contractor' or 'adv')
     * @return array|null Updated checkpoint record or null if not found
     * 
     * Requirements: 14.4
     */
    public function resetSectionStatus(int $installationId, string $section, string $level): ?array {
        // Validate section
        if (!InstallationSections::isValid($section)) {
            throw new Exception("Invalid section: $section");
        }
        
        // Validate level
        if (!InstallationCheckpoint::isValidReviewerLevel($level)) {
            throw new Exception("Invalid reviewer level: $level");
        }
        
        $statusField = $level === InstallationCheckpoint::LEVEL_CONTRACTOR 
            ? 'contractor_status' 
            : 'adv_status';
        $reviewerField = $level === InstallationCheckpoint::LEVEL_CONTRACTOR 
            ? 'contractor_reviewer_id' 
            : 'adv_reviewer_id';
        $reviewedAtField = $level === InstallationCheckpoint::LEVEL_CONTRACTOR 
            ? 'contractor_reviewed_at' 
            : 'adv_reviewed_at';
        
        $sql = "UPDATE `{$this->table}` 
                SET `$statusField` = ?, 
                    `$reviewerField` = NULL, 
                    `$reviewedAtField` = NULL 
                WHERE `installation_id` = ? AND `section` = ?";
        
        $stmt = $this->db->executeQuery($sql, [
            InstallationCheckpoint::STATUS_PENDING, 
            $installationId, 
            $section
        ], 'sis');
        $stmt->close();
        
        return $this->findByInstallationAndSection($installationId, $section);
    }
    
    /**
     * Reset all section statuses to pending for an installation
     * 
     * @param int $installationId Installation ID
     * @param string $level Reviewer level ('contractor' or 'adv')
     * @return bool True if reset was successful
     * 
     * Requirements: 14.5
     */
    public function resetAllSectionStatuses(int $installationId, string $level): bool {
        // Validate level
        if (!InstallationCheckpoint::isValidReviewerLevel($level)) {
            throw new Exception("Invalid reviewer level: $level");
        }
        
        $statusField = $level === InstallationCheckpoint::LEVEL_CONTRACTOR 
            ? 'contractor_status' 
            : 'adv_status';
        $reviewerField = $level === InstallationCheckpoint::LEVEL_CONTRACTOR 
            ? 'contractor_reviewer_id' 
            : 'adv_reviewer_id';
        $reviewedAtField = $level === InstallationCheckpoint::LEVEL_CONTRACTOR 
            ? 'contractor_reviewed_at' 
            : 'adv_reviewed_at';
        
        $sql = "UPDATE `{$this->table}` 
                SET `$statusField` = ?, 
                    `$reviewerField` = NULL, 
                    `$reviewedAtField` = NULL 
                WHERE `installation_id` = ?";
        
        $stmt = $this->db->executeQuery($sql, [
            InstallationCheckpoint::STATUS_PENDING, 
            $installationId
        ], 'si');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Delete all checkpoints for an installation
     * 
     * @param int $installationId Installation ID
     * @return bool True if deletion was successful
     */
    public function deleteByInstallationId(int $installationId): bool {
        $sql = "DELETE FROM `{$this->table}` WHERE installation_id = ?";
        $stmt = $this->db->executeQuery($sql, [$installationId], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Get count of sections by status for an installation
     * 
     * @param int $installationId Installation ID
     * @param string $level Reviewer level ('contractor' or 'adv')
     * @return array Array with status counts
     */
    public function countSectionsByStatus(int $installationId, string $level): array {
        $statusField = $level === InstallationCheckpoint::LEVEL_CONTRACTOR 
            ? 'contractor_status' 
            : 'adv_status';
        
        $sql = "SELECT `$statusField` as status, COUNT(*) as count 
                FROM `{$this->table}` 
                WHERE installation_id = ? 
                GROUP BY `$statusField`";
        
        $results = $this->db->getResults($sql, [$installationId], 'i');
        
        // Initialize counts
        $counts = [
            InstallationCheckpoint::STATUS_PENDING => 0,
            InstallationCheckpoint::STATUS_APPROVED => 0,
            InstallationCheckpoint::STATUS_REJECTED => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $counts[$row['status']] = (int)$row['count'];
            $counts['total'] += (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Approve all sections at once for a given level
     * 
     * @param int $installationId Installation ID
     * @param string $level Reviewer level ('contractor' or 'adv')
     * @param int $reviewerId Reviewer user ID
     * @return bool True if all sections were approved
     * 
     * Requirements: 12.5, 13.3
     */
    public function approveAllSections(int $installationId, string $level, int $reviewerId): bool {
        // Validate level
        if (!InstallationCheckpoint::isValidReviewerLevel($level)) {
            throw new Exception("Invalid reviewer level: $level");
        }
        
        // Ensure checkpoints exist
        $this->createForInstallation($installationId);
        
        $statusField = $level === InstallationCheckpoint::LEVEL_CONTRACTOR 
            ? 'contractor_status' 
            : 'adv_status';
        $reviewerField = $level === InstallationCheckpoint::LEVEL_CONTRACTOR 
            ? 'contractor_reviewer_id' 
            : 'adv_reviewer_id';
        $reviewedAtField = $level === InstallationCheckpoint::LEVEL_CONTRACTOR 
            ? 'contractor_reviewed_at' 
            : 'adv_reviewed_at';
        
        $sql = "UPDATE `{$this->table}` 
                SET `$statusField` = ?, 
                    `$reviewerField` = ?, 
                    `$reviewedAtField` = NOW() 
                WHERE `installation_id` = ?";
        
        $stmt = $this->db->executeQuery($sql, [
            InstallationCheckpoint::STATUS_APPROVED, 
            $reviewerId, 
            $installationId
        ], 'sii');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Get editable sections (rejected sections that can be modified)
     * 
     * @param int $installationId Installation ID
     * @return array Array of section identifiers that are editable
     * 
     * Requirements: 14.3
     */
    public function getEditableSections(int $installationId): array {
        // Get sections rejected at contractor level
        $contractorRejected = $this->getContractorRejectedSections($installationId);
        
        // Get sections rejected at ADV level
        $advRejected = $this->getAdvRejectedSections($installationId);
        
        $editableSections = [];
        
        foreach ($contractorRejected as $checkpoint) {
            $editableSections[] = $checkpoint['section'];
        }
        
        foreach ($advRejected as $checkpoint) {
            if (!in_array($checkpoint['section'], $editableSections)) {
                $editableSections[] = $checkpoint['section'];
            }
        }
        
        return $editableSections;
    }
}

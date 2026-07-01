<?php
/**
 * SiteDelegation Model
 * Handles site delegation data between ADV and contractors
 * 
 * Requirements: 2.1, 4.2, 4.3
 */

require_once __DIR__ . '/BaseModel.php';

class SiteDelegation extends BaseModel {
    protected $table = 'site_delegations';
    protected $fillable = [
        'site_id', 'contractor_id', 'delegated_by', 'delegated_at',
        'status', 'rejection_notes', 'responded_by', 'responded_at'
    ];
    
    // Delegation status constants
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    
    /**
     * Find delegations by contractor
     * Requirement 4.1
     * 
     * @param int $contractorId Contractor company ID
     * @param array $filters Optional filters
     * @return array Delegations
     */
    public function findByContractor(int $contractorId, array $filters = []): array {
        $sql = "SELECT d.*, s.site_name, s.lho, s.city, s.state, s.country, s.address,
                       u.name as delegated_by_name
                FROM `{$this->table}` d
                JOIN `sites` s ON d.site_id = s.id
                LEFT JOIN `users` u ON d.delegated_by = u.id
                WHERE d.contractor_id = ?";
        $params = [$contractorId];
        $types = 'i';
        
        if (isset($filters['status'])) {
            $sql .= " AND d.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        $sql .= " ORDER BY d.delegated_at DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Find delegations by site
     * 
     * @param int $siteId Site ID
     * @return array Delegations
     */
    public function findBySite(int $siteId): array {
        $sql = "SELECT d.*, c.name as contractor_name, u.name as delegated_by_name
                FROM `{$this->table}` d
                JOIN `companies` c ON d.contractor_id = c.id
                LEFT JOIN `users` u ON d.delegated_by = u.id
                WHERE d.site_id = ?
                ORDER BY d.delegated_at DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$siteId], 'i');
    }
    
    /**
     * Find pending delegations for contractor
     * Requirement 4.1
     * 
     * @param int $contractorId Contractor company ID
     * @return array Pending delegations
     */
    public function findPendingByContractor(int $contractorId): array {
        return $this->findByContractor($contractorId, ['status' => self::STATUS_PENDING]);
    }
    
    /**
     * Find accepted delegations for contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array Accepted delegations
     */
    public function findAcceptedByContractor(int $contractorId): array {
        return $this->findByContractor($contractorId, ['status' => self::STATUS_ACCEPTED]);
    }
    
    /**
     * Check if active delegation exists for site and contractor
     * Requirement 2.4
     * 
     * @param int $siteId Site ID
     * @param int $contractorId Contractor company ID
     * @param int|null $excludeId Delegation ID to exclude (for updates)
     * @return bool True if active delegation exists
     */
    public function hasActiveDelegation(int $siteId, int $contractorId, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `site_id` = ? AND `contractor_id` = ? 
                AND `status` IN ('pending', 'accepted')";
        $params = [$siteId, $contractorId];
        $types = 'ii';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = DatabaseConfig::getInstance()->getResults($sql, $params, $types);
        return $result[0]['count'] > 0;
    }
    
    /**
     * Accept delegation
     * Requirement 4.2
     * 
     * @param int $delegationId Delegation ID
     * @param int $respondedBy User ID who responded
     * @return bool Success
     */
    public function accept(int $delegationId, int $respondedBy): bool {
        $sql = "UPDATE `{$this->table}` 
                SET `status` = 'accepted', `responded_by` = ?, `responded_at` = NOW() 
                WHERE `id` = ? AND `status` = 'pending'";
        
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$respondedBy, $delegationId], 'ii');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Reject delegation with notes
     * Requirement 4.3
     * 
     * @param int $delegationId Delegation ID
     * @param string $notes Rejection notes (required)
     * @param int $respondedBy User ID who responded
     * @return array Result with success status
     */
    public function reject(int $delegationId, string $notes, int $respondedBy): array {
        // Validate rejection notes are provided (Requirement 4.3)
        if (trim($notes) === '') {
            return [
                'success' => false,
                'message' => 'Rejection notes are required',
                'code' => 'REJECTION_NOTES_REQUIRED'
            ];
        }
        
        $sql = "UPDATE `{$this->table}` 
                SET `status` = 'rejected', `rejection_notes` = ?, `responded_by` = ?, `responded_at` = NOW() 
                WHERE `id` = ? AND `status` = 'pending'";
        
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$notes, $respondedBy, $delegationId], 'sii');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        if ($affectedRows > 0) {
            return ['success' => true];
        }
        
        return [
            'success' => false,
            'message' => 'Delegation not found or already responded',
            'code' => 'DELEGATION_NOT_PENDING'
        ];
    }
    
    /**
     * Get delegation with full details
     * 
     * @param int $id Delegation ID
     * @return array|null Delegation with details
     */
    public function findWithDetails(int $id): ?array {
        $sql = "SELECT d.*, 
                       s.site_name, s.lho, s.city, s.state, s.country, s.address, s.latitude, s.longitude,
                       c.name as contractor_name,
                       u1.name as delegated_by_name,
                       u2.name as responded_by_name
                FROM `{$this->table}` d
                JOIN `sites` s ON d.site_id = s.id
                JOIN `companies` c ON d.contractor_id = c.id
                LEFT JOIN `users` u1 ON d.delegated_by = u1.id
                LEFT JOIN `users` u2 ON d.responded_by = u2.id
                WHERE d.id = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find delegations by ADV company (through sites)
     * Requirement 3.1
     * 
     * @param int $advCompanyId ADV company ID
     * @param array $filters Optional filters
     * @return array Delegations
     */
    public function findByADV(int $advCompanyId, array $filters = []): array {
        $sql = "SELECT d.*, 
                       s.site_name, s.lho, s.city, s.state,
                       c.name as contractor_name,
                       u1.name as delegated_by_name,
                       u2.name as responded_by_name
                FROM `{$this->table}` d
                JOIN `sites` s ON d.site_id = s.id
                JOIN `companies` c ON d.contractor_id = c.id
                LEFT JOIN `users` u1 ON d.delegated_by = u1.id
                LEFT JOIN `users` u2 ON d.responded_by = u2.id
                WHERE s.company_id = ?";
        $params = [$advCompanyId];
        $types = 'i';
        
        if (isset($filters['status'])) {
            $sql .= " AND d.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (isset($filters['contractor_id'])) {
            $sql .= " AND d.contractor_id = ?";
            $params[] = $filters['contractor_id'];
            $types .= 'i';
        }
        
        if (isset($filters['date_from'])) {
            $sql .= " AND d.delegated_at >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (isset($filters['date_to'])) {
            $sql .= " AND d.delegated_at <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        $sql .= " ORDER BY d.delegated_at DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
}

<?php
/**
 * Delegation Repository
 * Provides data access operations for site delegation records
 * 
 * Requirements: 2.1, 2.4, 3.1, 3.2
 * - 2.1: Create delegation records linking sites to contractors
 * - 2.4: Prevent duplicate active delegations to same contractor
 * - 3.1: Display all delegations with status, contractor, dates
 * - 3.2: Filter delegations by status, contractor, date range
 */

require_once __DIR__ . '/BaseRepository.php';

class DelegationRepository extends BaseRepository {
    protected $table = 'site_delegations';
    protected $primaryKey = 'id';
    protected $companyIdColumn = 'contractor_id';
    
    // Delegation status constants
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    
    /**
     * Find delegation by ID with full details
     * 
     * @param int $id Delegation ID
     * @return array|null Delegation record or null if not found
     */
    public function findById(int $id): ?array {
        $sql = "SELECT d.*, 
                       s.site_name, s.lho, s.city, s.state, s.country, s.address, 
                       s.latitude, s.longitude, s.company_id as adv_company_id,
                       c.name as contractor_name,
                       CONCAT(u1.first_name, ' ', u1.last_name) as delegated_by_name,
                       CONCAT(u2.first_name, ' ', u2.last_name) as responded_by_name
                FROM `{$this->table}` d
                JOIN `sites` s ON d.site_id = s.id
                JOIN `companies` c ON d.contractor_id = c.id
                LEFT JOIN `users` u1 ON d.delegated_by = u1.id
                LEFT JOIN `users` u2 ON d.responded_by = u2.id
                WHERE d.id = ?";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find delegations by contractor company ID with optional filters
     * 
     * @param int $contractorId Contractor company ID
     * @param array $filters Optional filters: status, date_from, date_to, page, limit
     * @return array Array with 'data', 'total', 'page', 'limit', 'totalPages'
     * 
     * Requirements: 3.1, 3.2
     */
    public function findByContractor(int $contractorId, array $filters = []): array {
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        
        $whereClause = ["d.contractor_id = ?"];
        $params = [$contractorId];
        $types = 'i';
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "d.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // LHO filter
        if (!empty($filters['lho'])) {
            $whereClause[] = "s.lho = ?";
            $params[] = $filters['lho'];
            $types .= 's';
        }
        
        // Date range filters
        if (!empty($filters['date_from'])) {
            $whereClause[] = "d.delegated_at >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause[] = "d.delegated_at <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        // Search filter (searches in site_name, lho, city)
        if (!empty($filters['search'])) {
            $whereClause[] = "(s.site_name LIKE ? OR s.lho LIKE ? OR s.city LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total 
                     FROM `{$this->table}` d
                     JOIN `sites` s ON d.site_id = s.id" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data with details and engineer assignment status
        // Note: engineer_assignments uses status values: 'assigned', 'in_progress', 'completed'
        $dataSQL = "SELECT d.*, 
                           s.site_name, s.lho, s.city, s.state, s.country, s.address, s.bank_name,
                           s.company_id as adv_company_id,
                           CONCAT(u1.first_name, ' ', u1.last_name) as delegated_by_name,
                           CONCAT(u2.first_name, ' ', u2.last_name) as responded_by_name,
                           ea.id as engineer_assignment_id,
                           ea.engineer_id,
                           ea.feasibility_status,
                           CONCAT(u3.first_name, ' ', u3.last_name) as engineer_name,
                           (SELECT COUNT(*) FROM dispatches disp WHERE disp.site_id = s.id AND disp.to_company_id = d.contractor_id) as dispatch_count,
                           (SELECT disp2.status FROM dispatches disp2 WHERE disp2.site_id = s.id AND disp2.to_company_id = d.contractor_id ORDER BY disp2.created_at DESC LIMIT 1) as latest_dispatch_status,
                           (SELECT pr.status FROM pending_receives pr 
                            INNER JOIN dispatches disp3 ON pr.dispatch_id = disp3.id 
                            WHERE disp3.site_id = s.id AND disp3.to_company_id = d.contractor_id 
                            ORDER BY pr.created_at DESC LIMIT 1) as material_receive_status
                    FROM `{$this->table}` d
                    JOIN `sites` s ON d.site_id = s.id
                    LEFT JOIN `users` u1 ON d.delegated_by = u1.id
                    LEFT JOIN `users` u2 ON d.responded_by = u2.id
                    LEFT JOIN `engineer_assignments` ea ON d.id = ea.delegation_id AND ea.status IN ('assigned', 'in_progress')
                    LEFT JOIN `users` u3 ON ea.engineer_id = u3.id" . 
                    $whereSQL . " ORDER BY d.delegated_at DESC LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $data = $this->db->getResults($dataSQL, $dataParams, $dataTypes);
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $total > 0 ? ceil($total / $limit) : 0
        ];
    }

    
    /**
     * Find delegations by ADV company ID (through sites) with optional filters
     * 
     * @param int $advCompanyId ADV company ID
     * @param array $filters Optional filters: status, contractor_id, date_from, date_to, page, limit
     * @return array Array with 'data', 'total', 'page', 'limit', 'totalPages'
     * 
     * Requirements: 3.1, 3.2
     */
    public function findByADV(int $advCompanyId, array $filters = []): array {
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        
        $whereClause = ["s.company_id = ?"];
        $params = [$advCompanyId];
        $types = 'i';
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "d.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Contractor filter
        if (isset($filters['contractor_id']) && $filters['contractor_id'] !== '') {
            $whereClause[] = "d.contractor_id = ?";
            $params[] = (int)$filters['contractor_id'];
            $types .= 'i';
        }
        
        // Date range filters
        if (!empty($filters['date_from'])) {
            $whereClause[] = "d.delegated_at >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause[] = "d.delegated_at <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause[] = "(s.site_name LIKE ? OR s.lho LIKE ? OR c.name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total 
                     FROM `{$this->table}` d
                     JOIN `sites` s ON d.site_id = s.id
                     JOIN `companies` c ON d.contractor_id = c.id" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data with details
        $dataSQL = "SELECT d.*, 
                           s.site_name, s.lho, s.city, s.state, s.country, s.address,
                           c.name as contractor_name,
                           CONCAT(u1.first_name, ' ', u1.last_name) as delegated_by_name,
                           CONCAT(u2.first_name, ' ', u2.last_name) as responded_by_name
                    FROM `{$this->table}` d
                    JOIN `sites` s ON d.site_id = s.id
                    JOIN `companies` c ON d.contractor_id = c.id
                    LEFT JOIN `users` u1 ON d.delegated_by = u1.id
                    LEFT JOIN `users` u2 ON d.responded_by = u2.id" . 
                    $whereSQL . " ORDER BY d.delegated_at DESC LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $data = $this->db->getResults($dataSQL, $dataParams, $dataTypes);
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $total > 0 ? ceil($total / $limit) : 0
        ];
    }
    
    /**
     * Check if an active delegation exists for site and contractor
     * Active means status is 'pending' or 'accepted'
     * 
     * @param int $siteId Site ID
     * @param int $contractorId Contractor company ID
     * @param int|null $excludeId Delegation ID to exclude (for updates)
     * @return bool True if duplicate active delegation exists
     * 
     * Requirements: 2.4
     */
    public function checkDuplicateDelegation(int $siteId, int $contractorId, ?int $excludeId = null): bool {
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
        
        $result = $this->db->getResults($sql, $params, $types);
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Create a new delegation record
     * 
     * @param array $data Delegation data: site_id, contractor_id, delegated_by
     * @return int The ID of the newly created delegation
     * @throws Exception If creation fails or duplicate exists
     * 
     * Requirements: 2.1, 2.4
     */
    public function create($data): int {
        // Validate required fields
        $requiredFields = ['site_id', 'contractor_id', 'delegated_by'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new Exception("The {$field} field is required");
            }
        }
        
        // Check for duplicate active delegation (Requirement 2.4)
        if ($this->checkDuplicateDelegation($data['site_id'], $data['contractor_id'])) {
            throw new Exception("An active delegation already exists for this site and contractor");
        }
        
        // Build insert query
        $fields = ['site_id', 'contractor_id', 'delegated_by', 'status'];
        $placeholders = ['?', '?', '?', '?'];
        $values = [
            (int)$data['site_id'],
            (int)$data['contractor_id'],
            (int)$data['delegated_by'],
            self::STATUS_PENDING
        ];
        $types = 'iiis';
        
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create delegation record");
        }
        
        return $insertId;
    }

    
    /**
     * Update delegation status
     * 
     * @param int $id Delegation ID
     * @param string $status New status
     * @return bool True if update was successful
     * @throws Exception If delegation not found
     */
    public function updateStatus(int $id, string $status): bool {
        // Verify delegation exists
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Delegation record not found");
        }
        
        $sql = "UPDATE `{$this->table}` SET `status` = ? WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$status, $id], 'si');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows >= 0;
    }
    
    /**
     * Update delegation status to accepted
     * 
     * @param int $id Delegation ID
     * @param int $respondedBy User ID who responded
     * @return bool True if update was successful
     * @throws Exception If delegation not found or not pending
     * 
     * Requirements: 4.2
     */
    public function updateStatusAccepted(int $id, int $respondedBy): bool {
        // Verify delegation exists and is pending
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Delegation record not found");;
        }
        
        if ($existing['status'] !== self::STATUS_PENDING) {
            throw new Exception("Delegation is not in pending status");
        }
        
        $sql = "UPDATE `{$this->table}` 
                SET `status` = ?, `responded_by` = ?, `responded_at` = NOW() 
                WHERE `id` = ?";
        
        $stmt = $this->db->executeQuery($sql, [self::STATUS_ACCEPTED, $respondedBy, $id], 'sii');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Update delegation status to rejected with notes
     * 
     * @param int $id Delegation ID
     * @param string $notes Rejection notes (required)
     * @param int $respondedBy User ID who responded
     * @return bool True if update was successful
     * @throws Exception If delegation not found, not pending, or notes empty
     * 
     * Requirements: 4.3
     */
    public function updateStatusRejected(int $id, string $notes, int $respondedBy): bool {
        // Validate rejection notes are provided (Requirement 4.3)
        if (trim($notes) === '') {
            throw new Exception("Rejection notes are required");
        }
        
        // Verify delegation exists and is pending
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Delegation record not found");
        }
        
        if ($existing['status'] !== self::STATUS_PENDING) {
            throw new Exception("Delegation is not in pending status");
        }
        
        $sql = "UPDATE `{$this->table}` 
                SET `status` = ?, `rejection_notes` = ?, `responded_by` = ?, `responded_at` = NOW() 
                WHERE `id` = ?";
        
        $stmt = $this->db->executeQuery($sql, [self::STATUS_REJECTED, $notes, $respondedBy, $id], 'ssii');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Update delegation record
     * 
     * @param int $id Delegation ID
     * @param array $data Data to update
     * @return bool True if update was successful
     * @throws Exception If update fails
     */
    public function update($id, $data): bool {
        // Verify record exists
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Delegation record not found");
        }
        
        // Build update query
        $setClauses = [];
        $values = [];
        $types = '';
        
        // Define allowed fields and their types
        $allowedFields = [
            'status' => 's',
            'rejection_notes' => 's',
            'responded_by' => 'i'
        ];
        
        foreach ($allowedFields as $field => $type) {
            if (array_key_exists($field, $data)) {
                $setClauses[] = "`$field` = ?";
                $values[] = $data[$field];
                $types .= $type;
            }
        }
        
        if (empty($setClauses)) {
            return true; // Nothing to update
        }
        
        // Add ID to params
        $values[] = $id;
        $types .= 'i';
        
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setClauses) . " WHERE `id` = ?";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows >= 0;
    }
    
    /**
     * Delete a delegation record (hard delete)
     * 
     * @param int $id Delegation ID
     * @return bool True if deletion was successful
     * @throws Exception If deletion fails
     */
    public function delete($id): bool {
        // Verify record exists
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Delegation record not found");
        }
        
        $sql = "DELETE FROM `{$this->table}` WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$id], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Find pending delegations for a contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array Array of pending delegation records
     */
    public function findPendingByContractor(int $contractorId): array {
        return $this->findByContractor($contractorId, ['status' => self::STATUS_PENDING])['data'];
    }
    
    /**
     * Find accepted delegations for a contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array Array of accepted delegation records
     */
    public function findAcceptedByContractor(int $contractorId): array {
        return $this->findByContractor($contractorId, ['status' => self::STATUS_ACCEPTED])['data'];
    }
    
    /**
     * Find delegations by site ID
     * 
     * @param int $siteId Site ID
     * @return array Array of delegation records
     */
    public function findBySite(int $siteId): array {
        $sql = "SELECT d.*, 
                       c.name as contractor_name,
                       CONCAT(u1.first_name, ' ', u1.last_name) as delegated_by_name,
                       CONCAT(u2.first_name, ' ', u2.last_name) as responded_by_name
                FROM `{$this->table}` d
                JOIN `companies` c ON d.contractor_id = c.id
                LEFT JOIN `users` u1 ON d.delegated_by = u1.id
                LEFT JOIN `users` u2 ON d.responded_by = u2.id
                WHERE d.site_id = ?
                ORDER BY d.delegated_at DESC";
        
        return $this->db->getResults($sql, [$siteId], 'i');
    }
    
    /**
     * Get all delegations for export (no pagination)
     * 
     * @param int $advCompanyId ADV company ID
     * @param array $filters Optional filters: status, contractor_id, date_from, date_to
     * @return array Array of delegation records
     * 
     * Requirements: 3.4
     */
    public function findAllForExport(int $advCompanyId, array $filters = []): array {
        $whereClause = ["s.company_id = ?"];
        $params = [$advCompanyId];
        $types = 'i';
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "d.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Contractor filter
        if (isset($filters['contractor_id']) && $filters['contractor_id'] !== '') {
            $whereClause[] = "d.contractor_id = ?";
            $params[] = (int)$filters['contractor_id'];
            $types .= 'i';
        }
        
        // Date range filters
        if (!empty($filters['date_from'])) {
            $whereClause[] = "d.delegated_at >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause[] = "d.delegated_at <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        
        $sql = "SELECT d.*, 
                       s.site_name, s.lho, s.city, s.state, s.country, s.address,
                       s.latitude, s.longitude,
                       c.name as contractor_name,
                       CONCAT(u1.first_name, ' ', u1.last_name) as delegated_by_name,
                       CONCAT(u2.first_name, ' ', u2.last_name) as responded_by_name
                FROM `{$this->table}` d
                JOIN `sites` s ON d.site_id = s.id
                JOIN `companies` c ON d.contractor_id = c.id
                LEFT JOIN `users` u1 ON d.delegated_by = u1.id
                LEFT JOIN `users` u2 ON d.responded_by = u2.id" . 
                $whereSQL . " ORDER BY d.delegated_at DESC";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Count delegations by status for an ADV company
     * 
     * @param int $advCompanyId ADV company ID
     * @return array Array with status counts
     */
    public function countByStatus(int $advCompanyId): array {
        $sql = "SELECT d.status, COUNT(*) as count 
                FROM `{$this->table}` d
                JOIN `sites` s ON d.site_id = s.id
                WHERE s.company_id = ?
                GROUP BY d.status";
        $result = $this->db->getResults($sql, [$advCompanyId], 'i');
        
        $counts = [
            'pending' => 0, 
            'accepted' => 0, 
            'rejected' => 0, 
            'total' => 0
        ];
        
        foreach ($result as $row) {
            $counts[$row['status']] = (int)$row['count'];
            $counts['total'] += (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Get distinct contractors for an ADV company's delegations
     * 
     * @param int $advCompanyId ADV company ID
     * @return array Array of contractor records with id and name
     */
    public function getDistinctContractors(int $advCompanyId): array {
        $sql = "SELECT DISTINCT c.id, c.name 
                FROM `{$this->table}` d
                JOIN `sites` s ON d.site_id = s.id
                JOIN `companies` c ON d.contractor_id = c.id
                WHERE s.company_id = ?
                ORDER BY c.name ASC";
        
        return $this->db->getResults($sql, [$advCompanyId], 'i');
    }
    
    /**
     * Check if a site has any active delegation (pending or accepted)
     * 
     * @param int $siteId Site ID
     * @return bool True if site has active delegation
     */
    public function hasAnyActiveDelegation(int $siteId): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `site_id` = ? AND `status` IN ('pending', 'accepted')";
        
        $result = $this->db->getResults($sql, [$siteId], 'i');
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Get accepted delegation for a site (for engineer assignment)
     * 
     * @param int $siteId Site ID
     * @param int $contractorId Contractor company ID
     * @return array|null Accepted delegation or null
     */
    public function getAcceptedDelegation(int $siteId, int $contractorId): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `site_id` = ? AND `contractor_id` = ? AND `status` = 'accepted'
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$siteId, $contractorId], 'ii');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get delegation history by delegation ID
     * Returns all history entries for a specific delegation
     * 
     * @param int $delegationId Delegation ID
     * @return array Array of history records with user details
     * 
     * Requirements: 3.3
     */
    public function getHistoryByDelegationId(int $delegationId): array {
        $sql = "SELECT dh.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as performed_by_name,
                       u.email as performed_by_email
                FROM `delegation_history` dh
                LEFT JOIN `users` u ON dh.performed_by = u.id
                WHERE dh.delegation_id = ?
                ORDER BY dh.performed_at ASC";
        
        return $this->db->getResults($sql, [$delegationId], 'i');
    }
    
    /**
     * Count delegations by status for a contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array Array with status counts
     */
    public function countByStatusForContractor(int $contractorId): array {
        $sql = "SELECT status, COUNT(*) as count 
                FROM `{$this->table}`
                WHERE contractor_id = ?
                GROUP BY status";
        $result = $this->db->getResults($sql, [$contractorId], 'i');
        
        $counts = [
            'pending' => 0, 
            'accepted' => 0, 
            'rejected' => 0, 
            'total' => 0
        ];
        
        foreach ($result as $row) {
            $counts[$row['status']] = (int)$row['count'];
            $counts['total'] += (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Get distinct LHOs for a contractor's delegated sites
     * 
     * @param int $contractorId Contractor company ID
     * @return array Array of distinct LHO values
     */
    public function getDistinctLHOsForContractor(int $contractorId): array {
        $sql = "SELECT DISTINCT s.lho 
                FROM `{$this->table}` d
                JOIN `sites` s ON d.site_id = s.id
                WHERE d.contractor_id = ? AND s.lho IS NOT NULL AND s.lho != ''
                ORDER BY s.lho ASC";
        
        $result = $this->db->getResults($sql, [$contractorId], 'i');
        return array_column($result, 'lho');
    }
}

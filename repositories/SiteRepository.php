<?php
/**
 * Site Repository
 * Provides data access operations for site records
 * 
 * Requirements: 1.1, 1.5
 * - 1.1: Create site records with all required fields
 * - 1.5: Prevent duplicate site names within the same LHO
 */

require_once __DIR__ . '/BaseRepository.php';

class SiteRepository extends BaseRepository {
    protected $table = 'sites';
    protected $primaryKey = 'id';
    protected $companyIdColumn = 'company_id';
    
    // Sites are company-specific, apply company filtering
    protected $applyCompanyFilter = true;
    
    /**
     * Find site by ID
     * 
     * @param int $id Site ID
     * @return array|null Site record or null if not found
     * 
     * Requirements: 1.1
     */
    public function findById(int $id): ?array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `id` = ? AND `status` != 'deleted'";
        $params = [$id];
        $types = 'i';
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                $this->companyIdColumn
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find all sites by company ID with optional filters
     * 
     * @param int $companyId Company ID
     * @param array $filters Optional filters: status, lho, search, delegation, material, installation, page, limit, orderBy, orderDir
     * @return array Array with 'data', 'total', 'page', 'limit', 'totalPages'
     * 
     * Requirements: 1.1
     */
    public function findByCompany($companyId, array $filters = []) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        $orderBy = $filters['orderBy'] ?? 'site_name';
        $orderDir = strtoupper($filters['orderDir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        
        $whereClause = ["s.`company_id` = ?", "s.`status` != 'deleted'"];
        $params = [$companyId];
        $types = 'i';
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "s.`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // LHO filter
        if (!empty($filters['lho'])) {
            $whereClause[] = "s.`lho` = ?";
            $params[] = $filters['lho'];
            $types .= 's';
        }
        
        // Search filter (searches in site_name, address, city)
        if (!empty($filters['search'])) {
            $whereClause[] = "(s.`site_name` LIKE ? OR s.`address` LIKE ? OR s.`city` LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        // Delegation filter
        if (!empty($filters['delegation'])) {
            if ($filters['delegation'] === 'delegated') {
                $whereClause[] = "sd.id IS NOT NULL";
            } elseif ($filters['delegation'] === 'not_delegated') {
                $whereClause[] = "sd.id IS NULL";
            }
        }
        
        // Material filter (based on material_requests table)
        if (!empty($filters['material'])) {
            if ($filters['material'] === 'generated') {
                $whereClause[] = "mr.id IS NOT NULL";
            } elseif ($filters['material'] === 'not_generated') {
                $whereClause[] = "mr.id IS NULL";
            }
        }
        
        // Installation filter
        if (!empty($filters['installation'])) {
            if ($filters['installation'] === 'done') {
                $whereClause[] = "inst.id IS NOT NULL AND inst.status = 'completed'";
            } elseif ($filters['installation'] === 'not_done') {
                $whereClause[] = "(inst.id IS NULL OR inst.status != 'completed')";
            }
        }
        
        // Build WHERE clause
        $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        
        // Whitelist allowed order columns
        $allowedOrderColumns = ['id', 'site_name', 'lho', 'city', 'state', 'status', 'created_at', 'updated_at'];
        if (!in_array($orderBy, $allowedOrderColumns)) {
            $orderBy = 'site_name';
        }
        
        // Base JOIN clause for all queries
        $joinSQL = " LEFT JOIN `site_delegations` sd ON s.id = sd.site_id AND sd.status IN ('pending', 'accepted')
                    LEFT JOIN `companies` c ON sd.contractor_id = c.id
                    LEFT JOIN `feasibility_checks` fc ON s.id = fc.site_id
                    LEFT JOIN `installations` inst ON s.id = inst.site_id
                    LEFT JOIN `material_requests` mr ON s.id = mr.site_id
                    LEFT JOIN `engineer_assignments` ea ON sd.id = ea.delegation_id";
        
        // Get total count
        $countSQL = "SELECT COUNT(DISTINCT s.id) as total FROM `{$this->table}` s" . $joinSQL . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data with delegation status and feasibility status
        $dataSQL = "SELECT s.*, 
                    sd.id as delegation_id,
                    sd.status as delegation_status,
                    sd.contractor_id,
                    c.name as contractor_name,
                    sd.delegated_at,
                    fc.id as feasibility_check_id,
                    fc.approval_status as feasibility_approval_status,
                    ea.feasibility_status,
                    inst.id as installation_id,
                    inst.status as installation_status
                    FROM `{$this->table}` s" .
                   $joinSQL .
                   $whereSQL .
                   " GROUP BY s.id ORDER BY s.`$orderBy` $orderDir LIMIT ? OFFSET ?";
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
     * Find all sites by LHO
     * 
     * @param string $lho LHO name
     * @param array $filters Optional filters: status, companyId
     * @return array Array of site records
     * 
     * Requirements: 1.1
     */
    public function findByLHO(string $lho, array $filters = []): array {
        $whereClause = ["`lho` = ?", "`status` != 'deleted'"];
        $params = [$lho];
        $types = 's';
        
        // Company filter
        if (isset($filters['companyId'])) {
            $whereClause[] = "`company_id` = ?";
            $params[] = (int)$filters['companyId'];
            $types .= 'i';
        }
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                $this->companyIdColumn
            );
            $whereClause[] = $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        $sql = "SELECT * FROM `{$this->table}`" . $whereSQL . " ORDER BY `site_name` ASC";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Check if a site name already exists within the same LHO and company
     * 
     * @param string $siteName Site name to check
     * @param string $lho LHO name
     * @param int $companyId Company ID
     * @param int|null $excludeId Optional site ID to exclude (for updates)
     * @return bool True if duplicate exists, false otherwise
     * 
     * Requirements: 1.5
     */
    public function checkDuplicateName(string $siteName, string $lho, int $companyId, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `site_name` = ? AND `lho` = ? AND `company_id` = ? AND `status` != 'deleted'";
        $params = [$siteName, $lho, $companyId];
        $types = 'ssi';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Create a new site record
     * 
     * @param array $data Site data
     * @return int The ID of the newly created site
     * @throws Exception If creation fails or duplicate exists
     * 
     * Requirements: 1.1, 1.5
     */
    public function create($data) {
        // Validate required fields
        $requiredFields = ['site_name', 'lho', 'city', 'state', 'country', 'company_id'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                throw new Exception("The {$field} field is required");
            }
        }
        
        // Check for duplicate site name within LHO and company (Requirement 1.5)
        if ($this->checkDuplicateName($data['site_name'], $data['lho'], $data['company_id'])) {
            throw new Exception("A site with this name already exists in the same LHO");
        }
        
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $data['company_id']);
        }
        
        // Build insert query
        $fields = [];
        $placeholders = [];
        $values = [];
        $types = '';
        
        // Define allowed fields and their types
        $allowedFields = [
            'site_name' => 's',
            'lho' => 's',
            'bank_name' => 's',
            'customer_name' => 's',
            'city' => 's',
            'state' => 's',
            'country' => 's',
            'zone' => 's',
            'address' => 's',
            'latitude' => 'd',
            'longitude' => 'd',
            'company_id' => 'i',
            'status' => 's',
            'created_by' => 'i'
        ];
        
        foreach ($allowedFields as $field => $type) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $fields[] = $field;
                $placeholders[] = '?';
                $values[] = $data[$field];
                $types .= $type;
            }
        }
        
        // Set default status if not provided
        if (!in_array('status', $fields)) {
            $fields[] = 'status';
            $placeholders[] = '?';
            $values[] = 'active';
            $types .= 's';
        }
        
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create site record");
        }
        
        return $insertId;
    }

    
    /**
     * Update an existing site record
     * 
     * @param int $id Site ID
     * @param array $data Data to update
     * @return bool True if update was successful
     * @throws Exception If update fails or duplicate exists
     * 
     * Requirements: 1.1, 1.5
     */
    public function update($id, $data) {
        // Verify record exists and user has access
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Site record not found or access denied");
        }
        
        // Check for duplicate if site_name or lho is being changed (Requirement 1.5)
        $siteName = $data['site_name'] ?? $existing['site_name'];
        $lho = $data['lho'] ?? $existing['lho'];
        $companyId = $data['company_id'] ?? $existing['company_id'];
        
        if ($this->checkDuplicateName($siteName, $lho, $companyId, $id)) {
            throw new Exception("A site with this name already exists in the same LHO");
        }
        
        // Validate company access if changing company
        if ($this->currentUserId && isset($data['company_id'])) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $data['company_id']);
        }
        
        // Build update query
        $setClauses = [];
        $values = [];
        $types = '';
        
        // Define allowed fields and their types
        $allowedFields = [
            'site_name' => 's',
            'lho' => 's',
            'bank_name' => 's',
            'customer_name' => 's',
            'city' => 's',
            'state' => 's',
            'country' => 's',
            'zone' => 's',
            'address' => 's',
            'latitude' => 'd',
            'longitude' => 'd',
            'company_id' => 'i',
            'status' => 's',
            'updated_by' => 'i'
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
        
        return $affectedRows >= 0; // 0 is valid if no changes were made
    }
    
    /**
     * Soft delete a site record (set status to 'deleted')
     * 
     * @param int $id Site ID
     * @param int|null $deletedBy User ID who performed the deletion
     * @return bool True if soft delete was successful
     * @throws Exception If deletion fails
     * 
     * Requirements: 1.1
     */
    public function delete($id, $deletedBy = null) {
        // Verify record exists and user has access
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Site record not found or access denied");
        }
        
        $data = ['status' => 'deleted'];
        if ($deletedBy !== null) {
            $data['updated_by'] = $deletedBy;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Get all active sites for a company (for dropdowns)
     * 
     * @param int $companyId Company ID
     * @return array Array of active site records
     */
    public function findAllActive(int $companyId): array {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        $sql = "SELECT * FROM `{$this->table}` WHERE `company_id` = ? AND `status` = 'active' ORDER BY `site_name` ASC";
        return $this->db->getResults($sql, [$companyId], 'i');
    }
    
    /**
     * Get distinct LHO values for a company
     * 
     * @param int $companyId Company ID
     * @return array Array of distinct LHO values
     */
    public function getDistinctLHOs(int $companyId): array {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        $sql = "SELECT DISTINCT `lho` FROM `{$this->table}` WHERE `company_id` = ? AND `status` != 'deleted' ORDER BY `lho` ASC";
        $result = $this->db->getResults($sql, [$companyId], 'i');
        
        return array_column($result, 'lho');
    }
    
    /**
     * Get sites for export (all matching filters, no pagination)
     * 
     * @param int $companyId Company ID
     * @param array $filters Optional filters: status, lho, search
     * @return array Array of site records
     */
    public function findAllForExport(int $companyId, array $filters = []): array {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        $whereClause = ["`company_id` = ?", "`status` != 'deleted'"];
        $params = [$companyId];
        $types = 'i';
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // LHO filter
        if (!empty($filters['lho'])) {
            $whereClause[] = "`lho` = ?";
            $params[] = $filters['lho'];
            $types .= 's';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause[] = "(`site_name` LIKE ? OR `address` LIKE ? OR `city` LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        $sql = "SELECT * FROM `{$this->table}`" . $whereSQL . " ORDER BY `site_name` ASC";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Count sites by status for a company
     * 
     * @param int $companyId Company ID
     * @return array Array with status counts
     */
    public function countByStatus(int $companyId): array {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        $sql = "SELECT `status`, COUNT(*) as count FROM `{$this->table}` 
                WHERE `company_id` = ? AND `status` != 'deleted' 
                GROUP BY `status`";
        $result = $this->db->getResults($sql, [$companyId], 'i');
        
        $counts = ['active' => 0, 'inactive' => 0, 'total' => 0, 'delegated' => 0];
        foreach ($result as $row) {
            $counts[$row['status']] = (int)$row['count'];
            $counts['total'] += (int)$row['count'];
        }
        
        // Get delegated count
        $delegatedSql = "SELECT COUNT(DISTINCT s.id) as count FROM `{$this->table}` s
                         INNER JOIN `site_delegations` sd ON s.id = sd.site_id AND sd.status IN ('pending', 'accepted')
                         WHERE s.company_id = ? AND s.status != 'deleted'";
        $delegatedResult = $this->db->getResults($delegatedSql, [$companyId], 'i');
        $counts['delegated'] = (int)($delegatedResult[0]['count'] ?? 0);
        
        return $counts;
    }
    
    /**
     * Find sites not yet delegated to any contractor
     * 
     * @param int $companyId Company ID
     * @return array Array of undelegated site records
     */
    public function findUndelegated(int $companyId): array {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        $sql = "SELECT s.* FROM `{$this->table}` s 
                LEFT JOIN `site_delegations` sd ON s.id = sd.site_id AND sd.status IN ('pending', 'accepted')
                WHERE s.company_id = ? AND s.status = 'active' AND sd.id IS NULL
                ORDER BY s.site_name ASC";
        
        return $this->db->getResults($sql, [$companyId], 'i');
    }
}

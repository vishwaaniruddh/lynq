<?php
/**
 * IP Master Repository
 * Provides data access operations for IP_Master records
 * 
 * Requirements: 1.1, 1.2, 1.3
 * - 1.1: Store unique IP combinations with validation
 * - 1.2: Prevent duplicate IP combinations
 * - 1.3: Display IP combinations with status
 */

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/IPMaster.php';

class IPMasterRepository extends BaseRepository {
    protected $table = 'ip_master';
    protected $primaryKey = 'id';
    
    // IP_Master is global configuration data, not company-specific
    protected $applyCompanyFilter = false;
    protected $companyIdColumn = null;
    
    /**
     * Find all IP_Master records with optional filters
     * Supports pagination, search, and status filtering
     * 
     * @param array $filters Optional filters: search, status, page, limit, orderBy, orderDir
     * @return array Array with 'data', 'total', 'page', 'limit', 'totalPages'
     * 
     * Requirements: 1.3
     */
    public function findAllWithFilters(array $filters = []): array {
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        $orderBy = $filters['orderBy'] ?? 'id';
        $orderDir = strtoupper($filters['orderDir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        
        $whereClause = [];
        $params = [];
        $types = '';
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Search filter (searches in all IP fields)
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereClause[] = "(`network_ip` LIKE ? OR `router_ip` LIKE ? OR `site_ip` LIKE ? OR `subnet_mask` LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ssss';
        }
        
        // Build WHERE clause
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        // Whitelist allowed order columns
        $allowedOrderColumns = ['id', 'network_ip', 'router_ip', 'site_ip', 'subnet_mask', 'status', 'created_at'];
        if (!in_array($orderBy, $allowedOrderColumns)) {
            $orderBy = 'id';
        }
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total FROM `{$this->table}`" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data
        $dataSQL = "SELECT * FROM `{$this->table}`" . $whereSQL . 
                   " ORDER BY `$orderBy` $orderDir LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $data = $this->db->getResults($dataSQL, $params, $types);
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ];
    }

    /**
     * Find IP_Master by ID
     * 
     * @param int $id IP_Master ID
     * @return array|null IP_Master record or null if not found
     * 
     * Requirements: 1.3
     */
    public function findById(int $id): ?array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `id` = ?";
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all available IP_Master records (status = 'available')
     * 
     * @return array Array of available IP_Master records
     * 
     * Requirements: 3.1
     */
    public function getAvailable(): array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `status` = ? ORDER BY `id` ASC";
        return $this->db->getResults($sql, [IPMaster::STATUS_AVAILABLE], 's');
    }
    
    /**
     * Get the next available IP_Master for configuration
     * 
     * @return array|null Next available IP_Master or null if none available
     * 
     * Requirements: 3.1
     */
    public function getNextAvailable(): ?array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `status` = ? ORDER BY `id` ASC LIMIT 1";
        $result = $this->db->getResults($sql, [IPMaster::STATUS_AVAILABLE], 's');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Check if IP combination already exists (for uniqueness validation)
     * 
     * @param string $networkIp Network IP
     * @param string $routerIp Router IP
     * @param string $siteIp Site IP
     * @param string $subnetMask Subnet Mask
     * @param int|null $excludeId Optional ID to exclude (for updates)
     * @return bool True if duplicate exists
     * 
     * Requirements: 1.2
     */
    public function checkDuplicate(string $networkIp, string $routerIp, string $siteIp, string $subnetMask, ?int $excludeId = null): bool {
        $sql = "SELECT id FROM `{$this->table}` 
                WHERE `network_ip` = ? AND `router_ip` = ? AND `site_ip` = ? AND `subnet_mask` = ?";
        $params = [$networkIp, $routerIp, $siteIp, $subnetMask];
        $types = 'ssss';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result);
    }
    
    /**
     * Check if IP combination exists using array data
     * 
     * @param array $data IP data with network_ip, router_ip, site_ip, subnet_mask
     * @param int|null $excludeId Optional ID to exclude (for updates)
     * @return bool True if duplicate exists
     * 
     * Requirements: 1.2
     */
    public function checkDuplicateFromArray(array $data, ?int $excludeId = null): bool {
        return $this->checkDuplicate(
            $data['network_ip'] ?? '',
            $data['router_ip'] ?? '',
            $data['site_ip'] ?? '',
            $data['subnet_mask'] ?? '',
            $excludeId
        );
    }
    
    /**
     * Create a new IP_Master record
     * 
     * @param array $data IP_Master data: network_ip, router_ip, site_ip, subnet_mask, created_by
     * @return int The ID of the newly created IP_Master
     * @throws Exception If creation fails
     * 
     * Requirements: 1.1
     */
    public function createIPMaster(array $data): int {
        $fields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask', 'status'];
        $placeholders = ['?', '?', '?', '?', '?'];
        $values = [
            $data['network_ip'],
            $data['router_ip'],
            $data['site_ip'],
            $data['subnet_mask'],
            $data['status'] ?? IPMaster::STATUS_AVAILABLE
        ];
        $types = 'sssss';
        
        // Optional field: created_by
        if (isset($data['created_by'])) {
            $fields[] = 'created_by';
            $placeholders[] = '?';
            $values[] = (int)$data['created_by'];
            $types .= 'i';
        }
        
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create IP_Master record");
        }
        
        return $insertId;
    }
    
    /**
     * Update an existing IP_Master record
     * 
     * @param int $id IP_Master ID
     * @param array $data Data to update
     * @return bool True if update was successful
     * @throws Exception If update fails
     * 
     * Requirements: 1.4
     */
    public function updateIPMaster(int $id, array $data): bool {
        // Verify record exists
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("IP_Master record not found");
        }
        
        $setClauses = [];
        $values = [];
        $types = '';
        
        // Allowed update fields
        $allowedFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $setClauses[] = "`$field` = ?";
                $values[] = $data[$field];
                $types .= 's';
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
        $stmt->close();
        
        return true;
    }
    
    /**
     * Update IP_Master status
     * 
     * @param int $id IP_Master ID
     * @param string $status New status
     * @return bool True if update was successful
     */
    public function updateStatus(int $id, string $status): bool {
        if (!in_array($status, [IPMaster::STATUS_AVAILABLE, IPMaster::STATUS_LOCKED, IPMaster::STATUS_CONFIGURED])) {
            throw new Exception("Invalid status: $status");
        }
        
        return $this->updateIPMaster($id, ['status' => $status]);
    }
    
    /**
     * Delete an IP_Master record
     * 
     * @param int $id IP_Master ID
     * @return bool True if deletion was successful
     * @throws Exception If deletion fails
     * 
     * Requirements: 1.5
     */
    public function deleteIPMaster(int $id): bool {
        // Verify record exists
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("IP_Master record not found");
        }
        
        $sql = "DELETE FROM `{$this->table}` WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$id], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Get count by status
     * 
     * @return array Counts by status
     * 
     * Requirements: 7.2
     */
    public function getCountByStatus(): array {
        $sql = "SELECT status, COUNT(*) as count FROM `{$this->table}` GROUP BY status";
        $results = $this->db->getResults($sql, [], '');
        
        $counts = [
            IPMaster::STATUS_AVAILABLE => 0,
            IPMaster::STATUS_LOCKED => 0,
            IPMaster::STATUS_CONFIGURED => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $counts[$row['status']] = (int)$row['count'];
            $counts['total'] += (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Get all IP_Master records for export (no pagination)
     * 
     * @param array $filters Optional filters: search, status
     * @return array Array of IP_Master records
     */
    public function findAllForExport(array $filters = []): array {
        $whereClause = [];
        $params = [];
        $types = '';
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $whereClause[] = "(`network_ip` LIKE ? OR `router_ip` LIKE ? OR `site_ip` LIKE ? OR `subnet_mask` LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ssss';
        }
        
        $whereSQL = '';
        if (!empty($whereClause)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        $sql = "SELECT * FROM `{$this->table}`" . $whereSQL . " ORDER BY `id` ASC";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Find IP_Master by exact IP combination
     * 
     * @param string $networkIp Network IP
     * @param string $routerIp Router IP
     * @param string $siteIp Site IP
     * @param string $subnetMask Subnet Mask
     * @return array|null IP_Master record or null if not found
     */
    public function findByIPCombination(string $networkIp, string $routerIp, string $siteIp, string $subnetMask): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `network_ip` = ? AND `router_ip` = ? AND `site_ip` = ? AND `subnet_mask` = ?";
        $result = $this->db->getResults($sql, [$networkIp, $routerIp, $siteIp, $subnetMask], 'ssss');
        return !empty($result) ? $result[0] : null;
    }
}

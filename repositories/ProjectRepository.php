<?php
/**
 * ProjectRepository
 * Data access repository for Project Master with Soft Delete
 */

require_once __DIR__ . '/BaseRepository.php';

class ProjectRepository extends BaseRepository {
    protected $table = 'projects';
    protected $primaryKey = 'id';
    protected $applyCompanyFilter = false;
    
    /**
     * Find all projects with filters and pagination
     */
    public function findAllWithFilters(array $filters = []): array {
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        $orderBy = $filters['orderBy'] ?? 'id';
        $orderDir = strtoupper($filters['orderDir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        
        $whereClause = ["p.`deleted_at` IS NULL"];
        $params = [];
        $types = '';
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $whereClause[] = "p.`status` = ?";
            $params[] = (int)$filters['status'];
            $types .= 'i';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause[] = "(p.`name` LIKE ? OR p.`code` LIKE ? OR p.`description` LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        
        // Allowed sort columns
        $allowedOrderColumns = ['id', 'name', 'code', 'status', 'created_at', 'updated_at'];
        if (!in_array($orderBy, $allowedOrderColumns)) {
            $orderBy = 'id';
        }
        
        $countSQL = "SELECT COUNT(*) as total FROM `{$this->table}` p" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        $dataSQL = "SELECT p.*, 
                           CONCAT(COALESCE(u.`first_name`, 'System'), ' ', COALESCE(u.`last_name`, '')) as created_by_name
                    FROM `{$this->table}` p
                    LEFT JOIN `users` u ON p.`created_by` = u.`id`" .
                    $whereSQL .
                    " ORDER BY p.`$orderBy` $orderDir LIMIT ? OFFSET ?";
        
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
     * Find project by ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT p.*, 
                       CONCAT(COALESCE(u.`first_name`, 'System'), ' ', COALESCE(u.`last_name`, '')) as created_by_name
                FROM `{$this->table}` p
                LEFT JOIN `users` u ON p.`created_by` = u.`id`
                WHERE p.`id` = ? AND p.`deleted_at` IS NULL";
        
        $results = $this->db->getResults($sql, [$id], 'i');
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Create a new project
     */
    public function createProject(array $data): int {
        if (empty($data['name'])) {
            throw new Exception("Project name is required");
        }
        
        if ($this->nameExists(trim($data['name']))) {
            throw new Exception("A project with this name already exists");
        }
        
        $now = date('Y-m-d H:i:s');
        $code = !empty($data['code']) ? trim($data['code']) : null;
        $desc = $data['description'] ?? null;
        $status = isset($data['status']) ? (int)$data['status'] : 1;
        $createdBy = $data['created_by'] ?? null;
        
        $sql = "INSERT INTO `{$this->table}` 
                (`name`, `code`, `description`, `status`, `created_by`, `created_at`, `updated_at`) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [$data['name'], $code, $desc, $status, $createdBy, $now, $now];
        $types = 'sssiiss';
        
        $stmt = $this->db->executeQuery($sql, $params, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create project");
        }
        
        return $insertId;
    }
    
    /**
     * Update project
     */
    public function updateProject(int $id, array $data): bool {
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Project not found");
        }
        
        if (isset($data['name']) && trim($data['name']) !== $existing['name']) {
            if ($this->nameExists(trim($data['name']), $id)) {
                throw new Exception("A project with this name already exists");
            }
        }
        
        $setClauses = [];
        $params = [];
        $types = '';
        
        if (isset($data['name'])) {
            $setClauses[] = "`name` = ?";
            $params[] = trim($data['name']);
            $types .= 's';
        }
        
        if (array_key_exists('code', $data)) {
            $setClauses[] = "`code` = ?";
            $params[] = !empty($data['code']) ? trim($data['code']) : null;
            $types .= 's';
        }
        
        if (array_key_exists('description', $data)) {
            $setClauses[] = "`description` = ?";
            $params[] = $data['description'];
            $types .= 's';
        }
        
        if (isset($data['status'])) {
            $setClauses[] = "`status` = ?";
            $params[] = (int)$data['status'];
            $types .= 'i';
        }
        
        if (isset($data['updated_by'])) {
            $setClauses[] = "`updated_by` = ?";
            $params[] = (int)$data['updated_by'];
            $types .= 'i';
        }
        
        $setClauses[] = "`updated_at` = ?";
        $params[] = date('Y-m-d H:i:s');
        $types .= 's';
        
        $params[] = $id;
        $types .= 'i';
        
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setClauses) . " WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, $params, $types);
        $stmt->close();
        
        return true;
    }
    
    /**
     * Soft delete project (only sets deleted_at)
     */
    public function softDelete(int $id, ?int $userId = null): bool {
        $existing = $this->findById($id);
        if (!$existing) {
            throw new Exception("Project not found");
        }
        
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE `{$this->table}` SET `deleted_at` = ?, `updated_at` = ?, `updated_by` = ? WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$now, $now, $userId, $id], 'ssii');
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }
    
    /**
     * Check if project name exists
     */
    public function nameExists(string $name, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `name` = ? AND `deleted_at` IS NULL";
        $params = [$name];
        $types = 's';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Export all filtered projects
     */
    public function exportAll(array $filters = []): array {
        $whereClause = ["p.`deleted_at` IS NULL"];
        $params = [];
        $types = '';
        
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $whereClause[] = "p.`status` = ?";
            $params[] = (int)$filters['status'];
            $types .= 'i';
        }
        
        if (!empty($filters['search'])) {
            $whereClause[] = "(p.`name` LIKE ? OR p.`code` LIKE ? OR p.`description` LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        $sql = "SELECT p.*, 
                       CONCAT(COALESCE(u.`first_name`, 'System'), ' ', COALESCE(u.`last_name`, '')) as created_by_name
                FROM `{$this->table}` p
                LEFT JOIN `users` u ON p.`created_by` = u.`id`" .
                $whereSQL .
                " ORDER BY p.`name` ASC";
        
        return $this->db->getResults($sql, $params, $types);
    }
}

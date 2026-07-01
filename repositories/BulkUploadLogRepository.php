<?php
/**
 * Bulk Upload Log Repository
 * Handles database operations for bulk upload logs
 */

require_once __DIR__ . '/../config/autoload.php';

class BulkUploadLogRepository {
    private $db;
    private $table = 'bulk_upload_logs';
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Create a new upload log entry
     */
    public function create(array $data): array {
        $sql = "INSERT INTO `{$this->table}` 
                (`upload_type`, `original_filename`, `total_rows`, `success_count`, `error_count`, 
                 `success_file`, `error_file`, `success_data`, `error_data`, `uploaded_by`, `company_id`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->executeQuery($sql, [
            $data['upload_type'],
            $data['original_filename'],
            $data['total_rows'],
            $data['success_count'],
            $data['error_count'],
            $data['success_file'] ?? null,
            $data['error_file'] ?? null,
            $data['success_data'] ?? null,
            $data['error_data'] ?? null,
            $data['uploaded_by'],
            $data['company_id'] ?? null
        ], 'ssiiissssii');
        
        $id = $this->db->getLastInsertId();
        return $this->findById($id);
    }
    
    /**
     * Find log by ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT l.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
                FROM `{$this->table}` l
                LEFT JOIN `users` u ON l.uploaded_by = u.id
                WHERE l.id = ?";
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get logs with pagination and filters
     */
    public function findAll(array $filters = []): array {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        $where = "1=1";
        $params = [];
        $types = '';
        
        if (!empty($filters['upload_type'])) {
            $where .= " AND l.upload_type = ?";
            $params[] = $filters['upload_type'];
            $types .= 's';
        }
        
        if (!empty($filters['uploaded_by'])) {
            $where .= " AND l.uploaded_by = ?";
            $params[] = (int)$filters['uploaded_by'];
            $types .= 'i';
        }
        
        if (!empty($filters['company_id'])) {
            $where .= " AND l.company_id = ?";
            $params[] = (int)$filters['company_id'];
            $types .= 'i';
        }
        
        // Count total
        $countSql = "SELECT COUNT(*) as total FROM `{$this->table}` l WHERE {$where}";
        $countResult = $this->db->getResults($countSql, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get data
        $sql = "SELECT l.id, l.upload_type, l.original_filename, l.total_rows, 
                       l.success_count, l.error_count, l.success_file, l.error_file,
                       l.uploaded_by, l.company_id, l.created_at,
                       CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
                FROM `{$this->table}` l
                LEFT JOIN `users` u ON l.uploaded_by = u.id
                WHERE {$where}
                ORDER BY l.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $data = $this->db->getResults($sql, $params, $types);
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $total > 0 ? ceil($total / $limit) : 0
        ];
    }
    
    /**
     * Update file paths
     */
    public function updateFiles(int $id, ?string $successFile, ?string $errorFile): bool {
        $sql = "UPDATE `{$this->table}` SET success_file = ?, error_file = ? WHERE id = ?";
        $this->db->executeQuery($sql, [$successFile, $errorFile, $id], 'ssi');
        return true;
    }
    
    /**
     * Delete old logs (cleanup)
     */
    public function deleteOlderThan(int $days): int {
        $sql = "DELETE FROM `{$this->table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $this->db->executeQuery($sql, [$days], 'i');
        return $this->db->getAffectedRows();
    }
}

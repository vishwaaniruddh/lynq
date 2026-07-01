<?php
/**
 * MaterialReceipt Repository
 * Provides data access operations for material receipt records
 * 
 * Requirements: 2.2, 2.3
 * - 2.2: Record confirmation with timestamp and engineer ID
 * - 2.3: Update installation status to "materials_received"
 */

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/MaterialReceipt.php';

class MaterialReceiptRepository extends BaseRepository {
    protected $table = 'installation_material_receipts';
    protected $primaryKey = 'id';
    
    // Material receipts don't have direct company_id
    // Company isolation is handled through installation -> site relationship
    protected $companyIdColumn = null;
    protected $applyCompanyFilter = false;
    
    /**
     * Create a new material receipt record
     * 
     * @param array $data Material receipt data (installation_id, confirmed_by)
     * @return array Created material receipt record
     * @throws Exception If creation fails or receipt already exists
     * 
     * Requirements: 2.2
     */
    public function create($data): array {
        // Validate required fields
        $requiredFields = ['installation_id', 'confirmed_by'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                throw new Exception("The {$field} field is required");
            }
        }
        
        // Check if receipt already exists for this installation
        $existing = $this->findByInstallationId((int)$data['installation_id']);
        if ($existing) {
            throw new Exception("Material receipt already exists for this installation");
        }
        
        // Build insert query
        $fields = ['installation_id', 'confirmed_by'];
        $placeholders = ['?', '?'];
        $values = [(int)$data['installation_id'], (int)$data['confirmed_by']];
        $types = 'ii';
        
        // Add confirmed_at if provided, otherwise use current timestamp
        if (isset($data['confirmed_at']) && $data['confirmed_at'] !== '') {
            $fields[] = 'confirmed_at';
            $placeholders[] = '?';
            $values[] = $data['confirmed_at'];
            $types .= 's';
        }
        
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->executeQuery($sql, $values, $types);
        $insertId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if ($insertId <= 0) {
            throw new Exception("Failed to create material receipt record");
        }
        
        return $this->find($insertId);
    }
    
    /**
     * Find material receipt by installation ID
     * 
     * @param int $installationId Installation ID
     * @return array|null Material receipt record or null if not found
     * 
     * Requirements: 2.2
     */
    public function findByInstallationId(int $installationId): ?array {
        $sql = "SELECT mr.*, CONCAT(u.first_name, ' ', u.last_name) as confirmed_by_name
                FROM `{$this->table}` mr
                LEFT JOIN users u ON mr.confirmed_by = u.id
                WHERE mr.installation_id = ?";
        
        $result = $this->db->getResults($sql, [$installationId], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Check if materials have been received for an installation
     * 
     * @param int $installationId Installation ID
     * @return bool True if materials have been received
     * 
     * Requirements: 2.3
     */
    public function hasMaterialsReceived(int $installationId): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE installation_id = ?";
        $result = $this->db->getResults($sql, [$installationId], 'i');
        return (int)($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Find material receipt by ID with user details
     * 
     * @param int $id Material receipt ID
     * @return array|null Material receipt record or null if not found
     */
    public function findWithDetails(int $id): ?array {
        $sql = "SELECT mr.*, 
                       u.name as confirmed_by_name,
                       i.site_id, i.atm_id, i.status as installation_status
                FROM `{$this->table}` mr
                LEFT JOIN users u ON mr.confirmed_by = u.id
                LEFT JOIN installations i ON mr.installation_id = i.id
                WHERE mr.id = ?";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Delete material receipt by installation ID
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
     * Get all material receipts with pagination
     * 
     * @param array $filters Optional filters: page, limit
     * @return array Array with 'data', 'total', 'page', 'limit', 'totalPages'
     */
    public function findAllWithFilters(array $filters = []): array {
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total FROM `{$this->table}`";
        $countResult = $this->db->getResults($countSQL, [], '');
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data
        $dataSQL = "SELECT mr.*, 
                           u.name as confirmed_by_name,
                           i.atm_id, i.status as installation_status
                    FROM `{$this->table}` mr
                    LEFT JOIN users u ON mr.confirmed_by = u.id
                    LEFT JOIN installations i ON mr.installation_id = i.id
                    ORDER BY mr.confirmed_at DESC
                    LIMIT ? OFFSET ?";
        
        $data = $this->db->getResults($dataSQL, [$limit, $offset], 'ii');
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $total > 0 ? ceil($total / $limit) : 0
        ];
    }
}

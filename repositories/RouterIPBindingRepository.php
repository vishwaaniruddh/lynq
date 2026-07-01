<?php
/**
 * Router IP Binding Repository
 * Provides data access operations for Router IP Binding records
 * 
 * Requirements: 5.1, 5.3, 5.4, 6.2
 * - 5.1: Create permanent bindings between routers and IP_Master records
 * - 5.3: Query router to get bound IP_Master details
 * - 5.4: Query IP_Master to get bound router serial number
 * - 6.2: Unbind IP from router with reason tracking
 */

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/RouterIPBinding.php';
require_once __DIR__ . '/../models/IPMaster.php';

class RouterIPBindingRepository extends BaseRepository {
    protected $table = 'router_ip_bindings';
    protected $primaryKey = 'id';
    
    // Router IP Bindings are global configuration data, not company-specific
    protected $applyCompanyFilter = false;
    protected $companyIdColumn = null;
    
    /**
     * Create a permanent binding between a router and an IP_Master
     * 
     * @param string $routerSerialNumber Router serial number
     * @param int $ipMasterId IP_Master ID
     * @param int $configuredBy User ID who configured
     * @param string|null $notes Optional configuration notes
     * @return array Result with success status and binding data
     * 
     * Requirements: 5.1
     */
    public function createBinding(string $routerSerialNumber, int $ipMasterId, int $configuredBy, ?string $notes = null): array {
        $conn = $this->db->getConnection();
        
        try {
            $conn->begin_transaction();
            
            // Check if router already has an active binding
            $sql = "SELECT id FROM `{$this->table}` 
                    WHERE `router_serial_number` = ? AND `status` = ? 
                    FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $activeStatus = RouterIPBinding::STATUS_ACTIVE;
            $stmt->bind_param('ss', $routerSerialNumber, $activeStatus);
            $stmt->execute();
            $result = $stmt->get_result();
            $existingBinding = $result->fetch_assoc();
            $stmt->close();
            
            if ($existingBinding) {
                $conn->rollback();
                return [
                    'success' => false,
                    'message' => 'Router already has an active binding',
                    'code' => 'ROUTER_ALREADY_BOUND',
                    'data' => ['existing_binding_id' => $existingBinding['id']]
                ];
            }
            
            // Check if IP_Master already has an active binding
            $sql = "SELECT id FROM `{$this->table}` 
                    WHERE `ip_master_id` = ? AND `status` = ? 
                    FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('is', $ipMasterId, $activeStatus);
            $stmt->execute();
            $result = $stmt->get_result();
            $existingIPBinding = $result->fetch_assoc();
            $stmt->close();
            
            if ($existingIPBinding) {
                $conn->rollback();
                return [
                    'success' => false,
                    'message' => 'IP_Master already has an active binding',
                    'code' => 'IP_ALREADY_BOUND',
                    'data' => ['existing_binding_id' => $existingIPBinding['id']]
                ];
            }
            
            // Create the binding
            $sql = "INSERT INTO `{$this->table}` 
                    (`router_serial_number`, `ip_master_id`, `configured_by`, `configured_at`, `notes`, `status`) 
                    VALUES (?, ?, ?, NOW(), ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('siiss', $routerSerialNumber, $ipMasterId, $configuredBy, $notes, $activeStatus);
            $stmt->execute();
            $bindingId = $conn->insert_id;
            $stmt->close();
            
            $conn->commit();
            
            // Fetch the created binding
            $binding = $this->findById($bindingId);
            
            return [
                'success' => true,
                'message' => 'Binding created successfully',
                'data' => $binding
            ];
            
        } catch (Exception $e) {
            $conn->rollback();
            return [
                'success' => false,
                'message' => 'Failed to create binding: ' . $e->getMessage(),
                'code' => 'BINDING_ERROR'
            ];
        }
    }
    
    /**
     * Find binding by ID
     * 
     * @param int $id Binding ID
     * @return array|null Binding record or null
     */
    public function findById(int $id): ?array {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask, m.status as ip_status,
                       u1.username as configured_by_username,
                       u2.username as unbound_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u1 ON b.configured_by = u1.id
                LEFT JOIN users u2 ON b.unbound_by = u2.id
                WHERE b.id = ?";
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find active binding by router serial number
     * 
     * @param string $routerSerialNumber Router serial number
     * @return array|null Active binding or null
     * 
     * Requirements: 5.3
     */
    public function getByRouter(string $routerSerialNumber): ?array {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask, m.status as ip_status,
                       u.username as configured_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u ON b.configured_by = u.id
                WHERE b.router_serial_number = ? AND b.status = ?
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$routerSerialNumber, RouterIPBinding::STATUS_ACTIVE], 'ss');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find active binding by IP_Master ID
     * 
     * @param int $ipMasterId IP_Master ID
     * @return array|null Active binding or null
     * 
     * Requirements: 5.4
     */
    public function getByIPMaster(int $ipMasterId): ?array {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask,
                       u.username as configured_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u ON b.configured_by = u.id
                WHERE b.ip_master_id = ? AND b.status = ?
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$ipMasterId, RouterIPBinding::STATUS_ACTIVE], 'is');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Unbind IP from router with reason tracking
     * Updates binding status and resets IP_Master status to available
     * 
     * @param int $bindingId Binding ID
     * @param int $unboundBy User ID performing unbind
     * @param string $reason Reason for unbinding
     * @return array Result with success status
     * 
     * Requirements: 6.2
     */
    public function unbind(int $bindingId, int $unboundBy, string $reason): array {
        $conn = $this->db->getConnection();
        
        try {
            $conn->begin_transaction();
            
            // Get the binding
            $sql = "SELECT * FROM `{$this->table}` WHERE `id` = ? FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $bindingId);
            $stmt->execute();
            $result = $stmt->get_result();
            $binding = $result->fetch_assoc();
            $stmt->close();
            
            if (!$binding) {
                $conn->rollback();
                return [
                    'success' => false,
                    'message' => 'Binding not found',
                    'code' => 'NOT_FOUND'
                ];
            }
            
            if ($binding['status'] !== RouterIPBinding::STATUS_ACTIVE) {
                $conn->rollback();
                return [
                    'success' => false,
                    'message' => 'Binding is not active (status: ' . $binding['status'] . ')',
                    'code' => 'NOT_ACTIVE'
                ];
            }
            
            // Update binding status to unbound
            $sql = "UPDATE `{$this->table}` 
                    SET `status` = ?, `unbound_by` = ?, `unbound_at` = NOW(), `unbind_reason` = ? 
                    WHERE `id` = ?";
            $stmt = $conn->prepare($sql);
            $unboundStatus = RouterIPBinding::STATUS_UNBOUND;
            $stmt->bind_param('sisi', $unboundStatus, $unboundBy, $reason, $bindingId);
            $stmt->execute();
            $stmt->close();
            
            // Reset IP_Master status to available
            $sql = "UPDATE `ip_master` SET `status` = ? WHERE `id` = ?";
            $stmt = $conn->prepare($sql);
            $availableStatus = IPMaster::STATUS_AVAILABLE;
            $stmt->bind_param('si', $availableStatus, $binding['ip_master_id']);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            return [
                'success' => true,
                'message' => 'Binding unbound successfully',
                'data' => [
                    'binding_id' => $bindingId,
                    'router_serial_number' => $binding['router_serial_number'],
                    'ip_master_id' => $binding['ip_master_id'],
                    'unbound_by' => $unboundBy,
                    'unbind_reason' => $reason
                ]
            ];
            
        } catch (Exception $e) {
            $conn->rollback();
            return [
                'success' => false,
                'message' => 'Failed to unbind: ' . $e->getMessage(),
                'code' => 'UNBIND_ERROR'
            ];
        }
    }
    
    /**
     * Get all active bindings with details
     * 
     * @return array Active bindings with IP and user details
     */
    public function getActiveBindings(): array {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask,
                       u.username as configured_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u ON b.configured_by = u.id
                WHERE b.status = ?
                ORDER BY b.configured_at DESC";
        
        return $this->db->getResults($sql, [RouterIPBinding::STATUS_ACTIVE], 's');
    }
    
    /**
     * Get binding history for a router
     * 
     * @param string $routerSerialNumber Router serial number
     * @return array All bindings (active and unbound) for the router
     */
    public function getRouterHistory(string $routerSerialNumber): array {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask,
                       u1.username as configured_by_username,
                       u2.username as unbound_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u1 ON b.configured_by = u1.id
                LEFT JOIN users u2 ON b.unbound_by = u2.id
                WHERE b.router_serial_number = ?
                ORDER BY b.configured_at DESC";
        
        return $this->db->getResults($sql, [$routerSerialNumber], 's');
    }
    
    /**
     * Get binding history for an IP_Master
     * 
     * @param int $ipMasterId IP_Master ID
     * @return array All bindings (active and unbound) for the IP
     */
    public function getIPHistory(int $ipMasterId): array {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask,
                       u1.username as configured_by_username,
                       u2.username as unbound_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u1 ON b.configured_by = u1.id
                LEFT JOIN users u2 ON b.unbound_by = u2.id
                WHERE b.ip_master_id = ?
                ORDER BY b.configured_at DESC";
        
        return $this->db->getResults($sql, [$ipMasterId], 'i');
    }
    
    /**
     * Count active bindings
     * 
     * @return int Number of active bindings
     */
    public function countActive(): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE status = ?";
        $result = $this->db->getResults($sql, [RouterIPBinding::STATUS_ACTIVE], 's');
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Search bindings with filters
     * 
     * @param array $filters Search filters
     * @return array Matching bindings
     */
    public function search(array $filters = []): array {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask,
                       u1.username as configured_by_username,
                       u2.username as unbound_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u1 ON b.configured_by = u1.id
                LEFT JOIN users u2 ON b.unbound_by = u2.id
                WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($filters['status'])) {
            $sql .= " AND b.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['router_serial_number'])) {
            $sql .= " AND b.router_serial_number LIKE ?";
            $params[] = '%' . $filters['router_serial_number'] . '%';
            $types .= 's';
        }
        
        if (!empty($filters['configured_by'])) {
            $sql .= " AND b.configured_by = ?";
            $params[] = $filters['configured_by'];
            $types .= 'i';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND b.configured_at >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND b.configured_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (b.router_serial_number LIKE ? OR m.network_ip LIKE ? OR m.router_ip LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        $sql .= " ORDER BY b.configured_at DESC";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get recent configurations
     * 
     * @param int $limit Number of records to return
     * @return array Recent configurations
     */
    public function getRecentConfigurations(int $limit = 10): array {
        $sql = "SELECT b.*, 
                       m.network_ip, m.router_ip, m.site_ip, m.subnet_mask,
                       u.username as configured_by_username
                FROM `{$this->table}` b
                JOIN ip_master m ON b.ip_master_id = m.id
                LEFT JOIN users u ON b.configured_by = u.id
                WHERE b.status = ?
                ORDER BY b.configured_at DESC
                LIMIT ?";
        
        return $this->db->getResults($sql, [RouterIPBinding::STATUS_ACTIVE, $limit], 'si');
    }
    
    /**
     * Check if router is configured
     * 
     * @param string $routerSerialNumber Router serial number
     * @return bool True if router has active binding
     */
    public function isRouterConfigured(string $routerSerialNumber): bool {
        return $this->getByRouter($routerSerialNumber) !== null;
    }
    
    /**
     * Check if IP_Master is bound
     * 
     * @param int $ipMasterId IP_Master ID
     * @return bool True if IP has active binding
     */
    public function isIPBound(int $ipMasterId): bool {
        return $this->getByIPMaster($ipMasterId) !== null;
    }
}

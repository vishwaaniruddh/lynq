<?php
/**
 * IP Master Service
 * Handles business logic for IP_Master management operations
 * 
 * Requirements: 1.1, 1.2, 1.4, 1.5
 * - 1.1: Create IP_Master with validation
 * - 1.2: Prevent duplicate IP combinations
 * - 1.4: Prevent editing configured IPs
 * - 1.5: Prevent deletion of configured/locked IPs
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../models/IPMaster.php';

class IPMasterService {
    private $db;
    private $repository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->repository = new IPMasterRepository();
    }
    
    /**
     * Get all IP_Master records with filters
     * 
     * @param array $filters Optional filters: search, status, page, limit, orderBy, orderDir
     * @return array Paginated result with data, total, page, limit, totalPages
     * 
     * Requirements: 1.3
     */
    public function getAll(array $filters = []): array {
        return $this->repository->findAllWithFilters($filters);
    }
    
    /**
     * Get IP_Master by ID
     * 
     * @param int $id IP_Master ID
     * @return array|null IP_Master record or null if not found
     */
    public function getById(int $id): ?array {
        return $this->repository->findById($id);
    }
    
    /**
     * Get all available IP_Master records
     * 
     * @return array Array of available IP_Master records
     * 
     * Requirements: 3.1
     */
    public function getAvailable(): array {
        return $this->repository->getAvailable();
    }
    
    /**
     * Get the next available IP_Master for configuration
     * 
     * @return array|null Next available IP_Master or null if none available
     * 
     * Requirements: 3.1
     */
    public function getNextAvailable(): ?array {
        return $this->repository->getNextAvailable();
    }

    /**
     * Create a new IP_Master record
     * 
     * @param array $data IP_Master data: network_ip, router_ip, site_ip, subnet_mask
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 1.1, 1.2
     */
    public function create(array $data, ?int $userId = null): array {
        // Validate required fields and IP format
        $validation = $this->validate($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Check for duplicate IP combination
        if ($this->repository->checkDuplicateFromArray($data)) {
            return [
                'success' => false,
                'message' => 'An IP_Master with this combination already exists',
                'errors' => [
                    'ip_combination' => ['This IP combination (Network IP, Router IP, Site IP, Subnet Mask) already exists']
                ],
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            // Prepare data
            $ipMasterData = [
                'network_ip' => trim($data['network_ip']),
                'router_ip' => trim($data['router_ip']),
                'site_ip' => trim($data['site_ip']),
                'subnet_mask' => trim($data['subnet_mask']),
                'status' => IPMaster::STATUS_AVAILABLE
            ];
            
            if ($userId !== null) {
                $ipMasterData['created_by'] = $userId;
            }
            
            // Create IP_Master
            $ipMasterId = $this->repository->createIPMaster($ipMasterData);
            
            // Log audit
            $this->logAction($userId, $ipMasterId, 'ip_created', [
                'network_ip' => $ipMasterData['network_ip'],
                'router_ip' => $ipMasterData['router_ip'],
                'site_ip' => $ipMasterData['site_ip'],
                'subnet_mask' => $ipMasterData['subnet_mask']
            ]);
            
            // Return created IP_Master
            $ipMaster = $this->repository->findById($ipMasterId);
            
            return [
                'success' => true,
                'message' => 'IP_Master created successfully',
                'data' => $ipMaster
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create IP_Master: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing IP_Master record
     * 
     * @param int $id IP_Master ID
     * @param array $data Data to update: network_ip, router_ip, site_ip, subnet_mask
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 1.4
     */
    public function update(int $id, array $data, ?int $userId = null): array {
        // Check if IP_Master exists
        $existing = $this->repository->findById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'IP_Master not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if IP is configured - prevent editing
        // Requirement 1.4: Prevent editing configured IPs
        if ($existing['status'] === IPMaster::STATUS_CONFIGURED) {
            return [
                'success' => false,
                'message' => 'Cannot edit IP_Master that is currently configured. Unbind it first.',
                'code' => 'CONFIGURED_ERROR'
            ];
        }
        
        // Validate IP format if any IP fields are being updated
        $ipFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask'];
        $hasIPUpdate = false;
        foreach ($ipFields as $field) {
            if (isset($data[$field])) {
                $hasIPUpdate = true;
                break;
            }
        }
        
        if ($hasIPUpdate) {
            // Merge with existing data for validation
            $mergedData = array_merge([
                'network_ip' => $existing['network_ip'],
                'router_ip' => $existing['router_ip'],
                'site_ip' => $existing['site_ip'],
                'subnet_mask' => $existing['subnet_mask']
            ], $data);
            
            $validation = $this->validate($mergedData, $id);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors'],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
            
            // Check for duplicate IP combination (excluding current record)
            if ($this->repository->checkDuplicateFromArray($mergedData, $id)) {
                return [
                    'success' => false,
                    'message' => 'An IP_Master with this combination already exists',
                    'errors' => [
                        'ip_combination' => ['This IP combination already exists']
                    ],
                    'code' => 'DUPLICATE_ERROR'
                ];
            }
        }
        
        try {
            // Prepare update data
            $updateData = [];
            foreach ($ipFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = trim($data[$field]);
                }
            }
            
            if (empty($updateData)) {
                return [
                    'success' => true,
                    'message' => 'No changes to update',
                    'data' => $existing
                ];
            }
            
            // Update IP_Master
            $this->repository->updateIPMaster($id, $updateData);
            
            // Log audit
            $this->logAction($userId, $id, 'ip_updated', [
                'changes' => array_keys($updateData),
                'old_values' => array_intersect_key($existing, $updateData),
                'new_values' => $updateData
            ]);
            
            // Return updated IP_Master
            $ipMaster = $this->repository->findById($id);
            
            return [
                'success' => true,
                'message' => 'IP_Master updated successfully',
                'data' => $ipMaster
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update IP_Master: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Delete an IP_Master record
     * 
     * @param int $id IP_Master ID
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status
     * 
     * Requirements: 1.5
     */
    public function delete(int $id, ?int $userId = null): array {
        // Check if IP_Master exists
        $existing = $this->repository->findById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'IP_Master not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check status - only allow deletion if available
        // Requirement 1.5: Only allow deletion if not configured or locked
        if ($existing['status'] === IPMaster::STATUS_CONFIGURED) {
            return [
                'success' => false,
                'message' => 'Cannot delete IP_Master that is currently configured. Unbind it first.',
                'code' => 'CONFIGURED_ERROR'
            ];
        }
        
        if ($existing['status'] === IPMaster::STATUS_LOCKED) {
            return [
                'success' => false,
                'message' => 'Cannot delete IP_Master that is currently locked. Wait for the lock to expire or be released.',
                'code' => 'LOCKED_ERROR'
            ];
        }
        
        try {
            // Delete IP_Master
            $this->repository->deleteIPMaster($id);
            
            // Log audit
            $this->logAction($userId, $id, 'ip_deleted', [
                'network_ip' => $existing['network_ip'],
                'router_ip' => $existing['router_ip'],
                'site_ip' => $existing['site_ip'],
                'subnet_mask' => $existing['subnet_mask']
            ]);
            
            return [
                'success' => true,
                'message' => 'IP_Master deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete IP_Master: ' . $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Check if IP_Master can be edited
     * 
     * @param int $id IP_Master ID
     * @return bool True if can be edited
     * 
     * Requirements: 1.4
     */
    public function canEdit(int $id): bool {
        $existing = $this->repository->findById($id);
        if (!$existing) {
            return false;
        }
        return $existing['status'] !== IPMaster::STATUS_CONFIGURED;
    }
    
    /**
     * Check if IP_Master can be deleted
     * 
     * @param int $id IP_Master ID
     * @return bool True if can be deleted
     * 
     * Requirements: 1.5
     */
    public function canDelete(int $id): bool {
        $existing = $this->repository->findById($id);
        if (!$existing) {
            return false;
        }
        return $existing['status'] === IPMaster::STATUS_AVAILABLE;
    }
    
    /**
     * Get IP statistics for dashboard
     * 
     * @return array Counts by status
     * 
     * Requirements: 7.2
     */
    public function getStats(): array {
        return $this->repository->getCountByStatus();
    }
    
    /**
     * Export IP_Master records
     * 
     * @param array $filters Optional filters: search, status
     * @return array Array of IP_Master records for export
     */
    public function export(array $filters = []): array {
        return $this->repository->findAllForExport($filters);
    }
    
    /**
     * Check if IP combination exists
     * 
     * @param array $data IP data
     * @param int|null $excludeId ID to exclude (for updates)
     * @return bool True if combination exists
     */
    public function combinationExists(array $data, ?int $excludeId = null): bool {
        return $this->repository->checkDuplicateFromArray($data, $excludeId);
    }
    
    /**
     * Validate IP_Master data
     * 
     * @param array $data Data to validate
     * @param int|null $id IP_Master ID (for updates, to exclude from uniqueness check)
     * @return array Validation result with 'valid' and 'errors'
     */
    private function validate(array $data, ?int $id = null): array {
        $errors = [];
        
        // Required fields
        $requiredFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $errors[$field] = [ucfirst(str_replace('_', ' ', $field)) . ' is required'];
            }
        }
        
        // If required fields are missing, return early
        if (!empty($errors)) {
            return [
                'valid' => false,
                'errors' => $errors
            ];
        }
        
        // Validate IP format for all fields
        $ipErrors = IPMaster::validateAllIPs($data);
        foreach ($ipErrors as $field => $error) {
            $errors[$field] = [$error];
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Log action for audit trail
     * 
     * @param int|null $userId User performing the action
     * @param int $ipMasterId IP_Master ID
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logAction(?int $userId, int $ipMasterId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO configuration_audit_log (action_type, user_id, ip_master_id, details, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->executeQuery($sql, [
                $action,
                $userId ?? 0,
                $ipMasterId,
                json_encode($details)
            ], 'siis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log IP_Master action: " . $e->getMessage());
        }
    }
}

<?php
/**
 * Courier Service
 * Handles business logic for courier master module operations
 * 
 * Requirements: 2.2, 2.3, 2.4, 2.6
 * - 2.2: Create new courier records with validation
 * - 2.3: Update existing courier records
 * - 2.4: Soft delete courier records
 * - 2.6: Export courier data
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/CourierRepository.php';

class CourierService {
    private $db;
    private $courierRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->courierRepository = new CourierRepository();
    }
    
    /**
     * Get all couriers with filters
     * 
     * @param array $filters Optional filters: search, status, page, limit, orderBy, orderDir
     * @return array Paginated result with data, total, page, limit, totalPages
     * 
     * Requirements: 2.1
     */
    public function getAll(array $filters = []): array {
        return $this->courierRepository->findAllWithFilters($filters);
    }
    
    /**
     * Get courier by ID
     * 
     * @param int $id Courier ID
     * @return array|null Courier record or null if not found
     */
    public function getById(int $id): ?array {
        return $this->courierRepository->findById($id);
    }
    
    /**
     * Create a new courier record
     * 
     * @param array $data Courier data: name, status (optional)
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 2.2
     */
    public function create(array $data, ?int $userId = null): array {
        // Validate required fields
        $validation = $this->validate($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Check uniqueness
        if ($this->courierRepository->nameExists(trim($data['name']))) {
            return [
                'success' => false,
                'message' => 'A courier with this name already exists',
                'errors' => ['name' => ['Courier name must be unique']],
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            // Prepare data
            $courierData = [
                'name' => trim($data['name']),
                'status' => isset($data['status']) ? (int)$data['status'] : 1
            ];
            
            if ($userId !== null) {
                $courierData['created_by'] = $userId;
            }
            
            // Create courier
            $courierId = $this->courierRepository->createCourier($courierData);
            
            // Log audit
            $this->logAction($userId, $courierId, 'courier_created', [
                'name' => $courierData['name'],
                'status' => $courierData['status']
            ]);
            
            // Return created courier
            $courier = $this->courierRepository->findById($courierId);
            
            return [
                'success' => true,
                'message' => 'Courier created successfully',
                'data' => $courier
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create courier: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing courier record
     * 
     * @param int $id Courier ID
     * @param array $data Data to update: name, status
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 2.3
     */
    public function update(int $id, array $data, ?int $userId = null): array {
        // Check if courier exists
        $existing = $this->courierRepository->findById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Courier not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Validate if name is being updated
        if (isset($data['name'])) {
            $validation = $this->validate($data, $id);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors'],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
            
            // Check uniqueness (excluding current record)
            if ($this->courierRepository->nameExists(trim($data['name']), $id)) {
                return [
                    'success' => false,
                    'message' => 'A courier with this name already exists',
                    'errors' => ['name' => ['Courier name must be unique']],
                    'code' => 'DUPLICATE_ERROR'
                ];
            }
        }
        
        try {
            // Prepare update data
            $updateData = [];
            
            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            
            if (isset($data['status'])) {
                $updateData['status'] = (int)$data['status'];
            }
            
            if ($userId !== null) {
                $updateData['updated_by'] = $userId;
            }
            
            // Update courier
            $this->courierRepository->updateCourier($id, $updateData);
            
            // Log audit
            $this->logAction($userId, $id, 'courier_updated', [
                'changes' => array_keys($updateData),
                'old_name' => $existing['name'],
                'new_name' => $updateData['name'] ?? $existing['name']
            ]);
            
            // Return updated courier
            $courier = $this->courierRepository->findById($id);
            
            return [
                'success' => true,
                'message' => 'Courier updated successfully',
                'data' => $courier
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update courier: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Soft delete a courier record (set status to inactive)
     * 
     * @param int $id Courier ID
     * @param int|null $userId User ID performing the action (for audit)
     * @return array Result with success status
     * 
     * Requirements: 2.4
     */
    public function delete(int $id, ?int $userId = null): array {
        // Check if courier exists
        $existing = $this->courierRepository->findById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Courier not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        try {
            // Soft delete
            $this->courierRepository->softDelete($id, $userId);
            
            // Log audit
            $this->logAction($userId, $id, 'courier_deleted', [
                'name' => $existing['name']
            ]);
            
            return [
                'success' => true,
                'message' => 'Courier deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete courier: ' . $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Export couriers with current filters
     * 
     * @param array $filters Optional filters: search, status
     * @return array Array of courier records for export
     * 
     * Requirements: 2.6
     */
    public function export(array $filters = []): array {
        return $this->courierRepository->findAllForExport($filters);
    }
    
    /**
     * Get all active couriers (for dropdowns)
     * 
     * @return array Array of active courier records
     */
    public function getActiveList(): array {
        return $this->courierRepository->findAllActive();
    }
    
    /**
     * Check if courier name exists
     * 
     * @param string $name Courier name
     * @param int|null $excludeId ID to exclude (for updates)
     * @return bool True if name exists
     */
    public function nameExists(string $name, ?int $excludeId = null): bool {
        return $this->courierRepository->nameExists($name, $excludeId);
    }
    
    /**
     * Validate courier data
     * 
     * @param array $data Data to validate
     * @param int|null $id Courier ID (for updates, to exclude from uniqueness check)
     * @return array Validation result with 'valid' and 'errors'
     */
    private function validate(array $data, ?int $id = null): array {
        $errors = [];
        
        // Required field: name
        if (!isset($data['name']) || trim($data['name']) === '') {
            $errors['name'] = ['Courier name is required'];
        } elseif (strlen(trim($data['name'])) > 255) {
            $errors['name'] = ['Courier name must not exceed 255 characters'];
        }
        
        // Status validation (if provided)
        if (isset($data['status']) && !in_array((int)$data['status'], [0, 1], true)) {
            $errors['status'] = ['Status must be 0 (inactive) or 1 (active)'];
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
     * @param int $courierId Courier ID
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logAction(?int $userId, int $courierId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details['courier_id'] = $courierId;
            $details['entity_type'] = 'courier';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId ?? 0,
                $action,
                json_encode($details),
                $userId ?? 0,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log courier action: " . $e->getMessage());
        }
    }
}

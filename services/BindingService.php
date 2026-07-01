<?php
/**
 * Binding Service
 * Handles business logic for Router-IP bindings
 * 
 * Requirements: 5.1, 5.2, 6.2, 6.3
 * - 5.1: Create permanent bindings between routers and IP_Master records
 * - 5.2: Record configuration timestamp, user, and notes
 * - 6.2: Unbind IP from router with status reset
 * - 6.3: Record unbind action with timestamp, user, and reason in audit log
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/RouterIPBindingRepository.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../models/RouterIPBinding.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../models/ConfigurationAuditLog.php';

class BindingService {
    private $db;
    private $bindingRepository;
    private $ipMasterRepository;
    private $auditLog;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->bindingRepository = new RouterIPBindingRepository();
        $this->ipMasterRepository = new IPMasterRepository();
        $this->auditLog = new ConfigurationAuditLog();
    }
    
    /**
     * Create a permanent binding between a router and an IP_Master
     * 
     * @param string $routerSerialNumber Router serial number
     * @param int $ipMasterId IP_Master ID
     * @param int $userId User ID creating the binding
     * @param string|null $notes Optional configuration notes
     * @return array Result with success status and binding data
     * 
     * Requirements: 5.1, 5.2
     */
    public function createBinding(string $routerSerialNumber, int $ipMasterId, int $userId, ?string $notes = null): array {
        // Validate inputs
        if (empty(trim($routerSerialNumber))) {
            return [
                'success' => false,
                'message' => 'Router serial number is required',
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        $routerSerialNumber = trim($routerSerialNumber);
        
        // Check if IP_Master exists
        $ipMaster = $this->ipMasterRepository->findById($ipMasterId);
        if (!$ipMaster) {
            return [
                'success' => false,
                'message' => 'IP_Master not found',
                'code' => 'IP_NOT_FOUND'
            ];
        }
        
        // Create the binding
        $result = $this->bindingRepository->createBinding(
            $routerSerialNumber,
            $ipMasterId,
            $userId,
            $notes
        );
        
        if ($result['success']) {
            // Update IP_Master status to configured
            $this->ipMasterRepository->updateStatus($ipMasterId, IPMaster::STATUS_CONFIGURED);
            
            // Log the binding creation (Requirement 5.2)
            $this->auditLog->logConfigured(
                $userId,
                $routerSerialNumber,
                $ipMasterId,
                [
                    'binding_id' => $result['data']['id'],
                    'notes' => $notes,
                    'network_ip' => $ipMaster['network_ip'],
                    'router_ip' => $ipMaster['router_ip'],
                    'site_ip' => $ipMaster['site_ip'],
                    'subnet_mask' => $ipMaster['subnet_mask']
                ]
            );
        }
        
        return $result;
    }
    
    /**
     * Unbind IP from router with status reset and audit logging
     * 
     * @param int $bindingId Binding ID
     * @param int $userId User ID performing unbind
     * @param string $reason Reason for unbinding
     * @return array Result with success status
     * 
     * Requirements: 6.2, 6.3
     */
    public function unbind(int $bindingId, int $userId, string $reason): array {
        // Validate reason
        if (empty(trim($reason))) {
            return [
                'success' => false,
                'message' => 'Unbind reason is required',
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        $reason = trim($reason);
        
        // Get the binding first for audit logging
        $binding = $this->bindingRepository->findById($bindingId);
        if (!$binding) {
            return [
                'success' => false,
                'message' => 'Binding not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Perform the unbind operation (this also resets IP_Master status)
        $result = $this->bindingRepository->unbind($bindingId, $userId, $reason);
        
        if ($result['success']) {
            // Log the unbind action (Requirement 6.3)
            $this->auditLog->logUnbound(
                $userId,
                $binding['router_serial_number'],
                $binding['ip_master_id'],
                $reason,
                [
                    'binding_id' => $bindingId,
                    'previous_configured_by' => $binding['configured_by'],
                    'previous_configured_at' => $binding['configured_at'],
                    'network_ip' => $binding['network_ip'],
                    'router_ip' => $binding['router_ip'],
                    'site_ip' => $binding['site_ip'],
                    'subnet_mask' => $binding['subnet_mask']
                ]
            );
        }
        
        return $result;
    }
    
    /**
     * Get binding by router serial number
     * 
     * @param string $routerSerialNumber Router serial number
     * @return array|null Active binding or null
     * 
     * Requirements: 5.3
     */
    public function getByRouter(string $routerSerialNumber): ?array {
        return $this->bindingRepository->getByRouter($routerSerialNumber);
    }
    
    /**
     * Get binding by IP_Master ID
     * 
     * @param int $ipMasterId IP_Master ID
     * @return array|null Active binding or null
     * 
     * Requirements: 5.4
     */
    public function getByIPMaster(int $ipMasterId): ?array {
        return $this->bindingRepository->getByIPMaster($ipMasterId);
    }
    
    /**
     * Get all active bindings
     * 
     * @return array Active bindings with details
     */
    public function getActiveBindings(): array {
        return $this->bindingRepository->getActiveBindings();
    }
    
    /**
     * Get binding by ID
     * 
     * @param int $bindingId Binding ID
     * @return array|null Binding or null
     */
    public function getById(int $bindingId): ?array {
        return $this->bindingRepository->findById($bindingId);
    }
    
    /**
     * Get binding history for a router
     * 
     * @param string $routerSerialNumber Router serial number
     * @return array All bindings for the router
     */
    public function getRouterHistory(string $routerSerialNumber): array {
        return $this->bindingRepository->getRouterHistory($routerSerialNumber);
    }
    
    /**
     * Get binding history for an IP_Master
     * 
     * @param int $ipMasterId IP_Master ID
     * @return array All bindings for the IP
     */
    public function getIPHistory(int $ipMasterId): array {
        return $this->bindingRepository->getIPHistory($ipMasterId);
    }
    
    /**
     * Check if router is configured
     * 
     * @param string $routerSerialNumber Router serial number
     * @return bool True if router has active binding
     */
    public function isRouterConfigured(string $routerSerialNumber): bool {
        return $this->bindingRepository->isRouterConfigured($routerSerialNumber);
    }
    
    /**
     * Check if IP_Master is bound
     * 
     * @param int $ipMasterId IP_Master ID
     * @return bool True if IP has active binding
     */
    public function isIPBound(int $ipMasterId): bool {
        return $this->bindingRepository->isIPBound($ipMasterId);
    }
    
    /**
     * Search bindings with filters
     * 
     * @param array $filters Search filters
     * @return array Matching bindings
     */
    public function search(array $filters = []): array {
        return $this->bindingRepository->search($filters);
    }
    
    /**
     * Get recent configurations
     * 
     * @param int $limit Number of records to return
     * @return array Recent configurations
     */
    public function getRecentConfigurations(int $limit = 10): array {
        return $this->bindingRepository->getRecentConfigurations($limit);
    }
    
    /**
     * Count active bindings
     * 
     * @return int Number of active bindings
     */
    public function countActive(): int {
        return $this->bindingRepository->countActive();
    }
    
    /**
     * Validate unbind operation
     * Checks if the binding can be unbound
     * 
     * @param int $bindingId Binding ID
     * @return array Validation result
     */
    public function validateUnbind(int $bindingId): array {
        $binding = $this->bindingRepository->findById($bindingId);
        
        if (!$binding) {
            return [
                'valid' => false,
                'message' => 'Binding not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        if ($binding['status'] !== RouterIPBinding::STATUS_ACTIVE) {
            return [
                'valid' => false,
                'message' => 'Binding is not active',
                'code' => 'NOT_ACTIVE',
                'data' => ['status' => $binding['status']]
            ];
        }
        
        return [
            'valid' => true,
            'binding' => $binding
        ];
    }
}

<?php
/**
 * Installation Assignment Service
 * Handles assignment of installation sites to engineers by contractors
 * 
 * Requirements: 2.3, 2.4, 2.6
 * - 2.3: Display "Assign Engineer" option for pending_assignment sites
 * - 2.4: Update installation status to "pending_eta" when engineer is assigned
 * - 2.6: Display assigned engineer name and allow reassignment
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/InstallationNotificationService.php';

class InstallationAssignmentService {
    private $db;
    private $installationRepository;
    private $notificationService;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->installationRepository = new InstallationRepository();
        $this->notificationService = new InstallationNotificationService();
    }
    
    /**
     * Assign an engineer to an installation
     * Updates status to "pending_eta" and records assignment details
     * 
     * @param int $installationId Installation ID
     * @param int $engineerId Engineer user ID
     * @param int $assignedBy User ID performing the assignment
     * @return array Result with success status and data/errors
     * 
     * Requirements: 2.3, 2.4
     */
    public function assignEngineer(int $installationId, int $engineerId, int $assignedBy): array {
        // Get installation
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if assignment is allowed
        $canAssign = $this->canAssign($installationId, $installation['contractor_id'] ?? 0);
        if (!$canAssign['canAssign']) {
            return [
                'success' => false,
                'message' => $canAssign['reason'],
                'code' => $canAssign['code']
            ];
        }
        
        // Validate engineer
        $validation = $this->validateAssignment($installationId, $engineerId);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'code' => $validation['code']
            ];
        }
        
        try {
            // Update installation with assignment data (Requirement 2.4)
            $updateData = [
                'assigned_engineer_id' => $engineerId,
                'assigned_by' => $assignedBy,
                'assigned_at' => date('Y-m-d H:i:s'),
                'status' => Installation::STATUS_PENDING_ETA
            ];
            
            $updatedInstallation = $this->installationRepository->update($installationId, $updateData);
            
            // Log audit
            $this->logAction($assignedBy, $installationId, 'engineer_assigned', [
                'engineer_id' => $engineerId,
                'contractor_id' => $installation['contractor_id']
            ]);
            
            // Send notification to engineer (Requirement 2.5)
            $this->notificationService->notifyEngineerAssigned($updatedInstallation, $engineerId);
            
            return [
                'success' => true,
                'message' => 'Engineer assigned successfully',
                'data' => $updatedInstallation
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to assign engineer: ' . $e->getMessage(),
                'code' => 'ASSIGNMENT_ERROR'
            ];
        }
    }

    
    /**
     * Get assignment details for an installation
     * 
     * @param int $installationId Installation ID
     * @return array|null Assignment details or null if not found
     */
    public function getAssignment(int $installationId): ?array {
        $installation = $this->installationRepository->findWithDetails($installationId);
        
        if (!$installation) {
            return null;
        }
        
        return [
            'installation_id' => $installation['id'],
            'site_id' => $installation['site_id'],
            'site_name' => $installation['site_name'] ?? $installation['atm_id'],
            'contractor_id' => $installation['contractor_id'],
            'contractor_name' => $installation['contractor_name'] ?? null,
            'assigned_engineer_id' => $installation['assigned_engineer_id'],
            'assigned_engineer_name' => $installation['assigned_engineer_name'] ?? null,
            'assigned_by' => $installation['assigned_by'],
            'assigned_by_name' => $installation['assigned_by_name'] ?? null,
            'assigned_at' => $installation['assigned_at'],
            'status' => $installation['status']
        ];
    }
    
    /**
     * Get all assignments for an engineer
     * 
     * @param int $engineerId Engineer user ID
     * @return array List of installations assigned to the engineer
     * 
     * Requirements: 3.1
     */
    public function getAssignmentsByEngineer(int $engineerId): array {
        return $this->installationRepository->findByEngineer($engineerId);
    }
    
    /**
     * Get all assignments for a contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array List of installations delegated to the contractor
     * 
     * Requirements: 2.1
     */
    public function getAssignmentsByContractor(int $contractorId): array {
        return $this->installationRepository->findByContractor($contractorId);
    }
    
    /**
     * Reassign an installation to a different engineer
     * 
     * @param int $installationId Installation ID
     * @param int $newEngineerId New engineer user ID
     * @param int $reassignedBy User ID performing the reassignment
     * @return array Result with success status and data/errors
     * 
     * Requirements: 2.6
     */
    public function reassignEngineer(int $installationId, int $newEngineerId, int $reassignedBy): array {
        // Get installation
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if installation has a contractor
        if (empty($installation['contractor_id'])) {
            return [
                'success' => false,
                'message' => 'Installation has not been delegated to a contractor',
                'code' => 'NOT_DELEGATED'
            ];
        }
        
        // Validate new engineer
        $validation = $this->validateAssignment($installationId, $newEngineerId);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'code' => $validation['code']
            ];
        }
        
        $previousEngineerId = $installation['assigned_engineer_id'];
        
        try {
            // Update installation with new assignment data
            $updateData = [
                'assigned_engineer_id' => $newEngineerId,
                'assigned_by' => $reassignedBy,
                'assigned_at' => date('Y-m-d H:i:s')
            ];
            
            // If status is pending_assignment, update to pending_eta
            if ($installation['status'] === Installation::STATUS_PENDING_ASSIGNMENT) {
                $updateData['status'] = Installation::STATUS_PENDING_ETA;
            }
            
            $updatedInstallation = $this->installationRepository->update($installationId, $updateData);
            
            // Log audit
            $this->logAction($reassignedBy, $installationId, 'engineer_reassigned', [
                'previous_engineer_id' => $previousEngineerId,
                'new_engineer_id' => $newEngineerId,
                'contractor_id' => $installation['contractor_id']
            ]);
            
            // Send notification to new engineer
            $this->notificationService->notifyEngineerAssigned($updatedInstallation, $newEngineerId);
            
            return [
                'success' => true,
                'message' => 'Engineer reassigned successfully',
                'data' => $updatedInstallation
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to reassign engineer: ' . $e->getMessage(),
                'code' => 'REASSIGNMENT_ERROR'
            ];
        }
    }

    
    /**
     * Get available engineers for a contractor
     * Returns list of active users belonging to the contractor company
     * Any contractor employee can be assigned to an installation
     * 
     * @param int $contractorId Contractor company ID
     * @return array List of users (engineers/employees)
     * 
     * Requirements: 2.3
     */
    public function getAvailableEngineers(int $contractorId): array {
        try {
            // Get all active users belonging to the contractor company
            $sql = "SELECT id, first_name, last_name, email,
                           CONCAT(first_name, ' ', last_name) as full_name
                    FROM users
                    WHERE company_id = ? 
                    AND status = 1
                    ORDER BY first_name, last_name ASC";
            
            $result = $this->db->getResults($sql, [$contractorId], 'i');
            
            return $result ?: [];
        } catch (Exception $e) {
            error_log("Failed to get available engineers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get installations pending assignment for a contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array List of installations pending assignment
     * 
     * Requirements: 2.1
     */
    public function getPendingAssignments(int $contractorId): array {
        return $this->installationRepository->findPendingAssignment($contractorId);
    }
    
    /**
     * Check if assignment is allowed for an installation
     * 
     * @param int $installationId Installation ID
     * @param int $contractorId Contractor company ID
     * @return array Result with 'canAssign', 'reason', and 'code'
     * 
     * Requirements: 2.3, 2.4
     */
    public function canAssign(int $installationId, int $contractorId): array {
        // Get installation
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'canAssign' => false,
                'reason' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if installation is delegated to this contractor
        if ((int)$installation['contractor_id'] !== $contractorId) {
            return [
                'canAssign' => false,
                'reason' => 'Installation is not delegated to this contractor',
                'code' => 'WRONG_CONTRACTOR'
            ];
        }
        
        // Check if installation is in pending_assignment status (Requirement 2.3)
        if ($installation['status'] !== Installation::STATUS_PENDING_ASSIGNMENT) {
            return [
                'canAssign' => false,
                'reason' => 'Installation is not in pending_assignment status',
                'code' => 'WRONG_STATUS'
            ];
        }
        
        return [
            'canAssign' => true,
            'reason' => null,
            'code' => null
        ];
    }
    
    /**
     * Validate engineer assignment
     * 
     * @param int $installationId Installation ID
     * @param int $engineerId Engineer user ID
     * @return array Validation result with 'isValid', 'message', and 'code'
     * 
     * Requirements: 2.3, 2.4
     */
    public function validateAssignment(int $installationId, int $engineerId): array {
        // Get installation to find contractor
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'isValid' => false,
                'message' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        $contractorId = $installation['contractor_id'];
        if (!$contractorId) {
            return [
                'isValid' => false,
                'message' => 'Installation has not been delegated to a contractor',
                'code' => 'NOT_DELEGATED'
            ];
        }
        
        try {
            // Check if engineer exists and is active
            $sql = "SELECT id, company_id, status, role_id FROM users WHERE id = ?";
            $result = $this->db->getResults($sql, [$engineerId], 'i');
            
            if (empty($result)) {
                return [
                    'isValid' => false,
                    'message' => 'Engineer not found',
                    'code' => 'INVALID_ENGINEER'
                ];
            }
            
            $engineer = $result[0];
            
            // Check if engineer is active
            if ((int)$engineer['status'] !== 1) {
                return [
                    'isValid' => false,
                    'message' => 'Engineer is not active',
                    'code' => 'ENGINEER_INACTIVE'
                ];
            }
            
            // Check if engineer belongs to the contractor company
            if ((int)$engineer['company_id'] !== $contractorId) {
                return [
                    'isValid' => false,
                    'message' => 'Engineer does not belong to the contractor company',
                    'code' => 'WRONG_COMPANY'
                ];
            }
            
            return [
                'isValid' => true,
                'message' => 'Valid engineer',
                'code' => null,
                'engineer' => $engineer
            ];
        } catch (Exception $e) {
            return [
                'isValid' => false,
                'message' => 'Failed to validate engineer: ' . $e->getMessage(),
                'code' => 'VALIDATION_ERROR'
            ];
        }
    }
    
    /**
     * Log action for audit trail
     * 
     * @param int $userId User performing the action
     * @param int $installationId Installation ID
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logAction(int $userId, int $installationId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details['installation_id'] = $installationId;
            $details['entity_type'] = 'installation';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId,
                $action,
                json_encode($details),
                $userId,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log installation assignment action: " . $e->getMessage());
        }
    }
}

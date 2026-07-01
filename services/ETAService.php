<?php
/**
 * ETA Service
 * Handles business logic for ETA (Estimated Time of Arrival) operations
 * 
 * Requirements: 2.2, 2.3, 2.4, 2.5
 * - 2.2: Record ETA with timestamp and engineer ID
 * - 2.3: Reject past date/time submissions
 * - 2.4: Update feasibility status to eta_submitted
 * - 2.5: Maintain history of previous ETA submissions
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/FeasibilityETARepository.php';
require_once __DIR__ . '/../repositories/EngineerAssignmentRepository.php';

class ETAService {
    private $db;
    private $etaRepository;
    private $assignmentRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->etaRepository = new FeasibilityETARepository();
        $this->assignmentRepository = new EngineerAssignmentRepository();
    }
    
    /**
     * Submit a new ETA for an assignment
     * 
     * @param int $assignmentId Engineer assignment ID
     * @param string $etaDateTime ETA date and time (Y-m-d H:i:s format)
     * @param int $engineerId Engineer user ID submitting the ETA
     * @return array Result with success status and data/errors
     * 
     * Requirements: 2.2, 2.3, 2.4, 2.5
     */
    public function submitETA(int $assignmentId, string $etaDateTime, int $engineerId): array {
        // Validate ETA datetime (Requirement 2.3)
        $validation = $this->validateETADateTime($etaDateTime);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Verify assignment exists
        $assignment = $this->assignmentRepository->findById($assignmentId);
        if (!$assignment) {
            return [
                'success' => false,
                'message' => 'Assignment not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Verify engineer is assigned to this assignment
        if ((int)$assignment['engineer_id'] !== $engineerId) {
            return [
                'success' => false,
                'message' => 'You are not authorized to submit ETA for this assignment',
                'code' => 'UNAUTHORIZED'
            ];
        }
        
        try {
            // Create ETA record (Requirement 2.2, 2.5 - history is maintained by repository)
            $etaData = [
                'assignment_id' => $assignmentId,
                'eta_datetime' => $etaDateTime,
                'submitted_by' => $engineerId
            ];
            
            $eta = $this->etaRepository->create($etaData);
            
            // Update assignment feasibility_status to eta_submitted (Requirement 2.4)
            $this->updateAssignmentFeasibilityStatus($assignmentId, 'eta_submitted');
            
            // Log audit
            $this->logAction($engineerId, $assignmentId, 'eta_submitted', [
                'eta_id' => $eta['id'],
                'eta_datetime' => $etaDateTime
            ]);
            
            return [
                'success' => true,
                'message' => 'ETA submitted successfully',
                'data' => $eta
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to submit ETA: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing ETA for an assignment
     * Creates a new ETA record and marks previous as not current
     * 
     * @param int $assignmentId Engineer assignment ID
     * @param string $etaDateTime New ETA date and time
     * @param int $engineerId Engineer user ID updating the ETA
     * @return array Result with success status and data/errors
     * 
     * Requirements: 2.2, 2.3, 2.5
     */
    public function updateETA(int $assignmentId, string $etaDateTime, int $engineerId): array {
        // Validate ETA datetime (Requirement 2.3)
        $validation = $this->validateETADateTime($etaDateTime);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Verify assignment exists
        $assignment = $this->assignmentRepository->findById($assignmentId);
        if (!$assignment) {
            return [
                'success' => false,
                'message' => 'Assignment not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Verify engineer is assigned to this assignment
        if ((int)$assignment['engineer_id'] !== $engineerId) {
            return [
                'success' => false,
                'message' => 'You are not authorized to update ETA for this assignment',
                'code' => 'UNAUTHORIZED'
            ];
        }
        
        try {
            // Create new ETA record (previous ones are marked as not current by repository)
            $etaData = [
                'assignment_id' => $assignmentId,
                'eta_datetime' => $etaDateTime,
                'submitted_by' => $engineerId
            ];
            
            $eta = $this->etaRepository->create($etaData);
            
            // Log audit
            $this->logAction($engineerId, $assignmentId, 'eta_updated', [
                'eta_id' => $eta['id'],
                'eta_datetime' => $etaDateTime
            ]);
            
            return [
                'success' => true,
                'message' => 'ETA updated successfully',
                'data' => $eta
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update ETA: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Get current ETA for an assignment
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return array|null Current ETA record or null
     * 
     * Requirements: 2.2
     */
    public function getETA(int $assignmentId): ?array {
        return $this->etaRepository->findCurrentByAssignment($assignmentId);
    }
    
    /**
     * Get ETA history for an assignment
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return array ETA history records
     * 
     * Requirements: 2.5
     */
    public function getETAHistory(int $assignmentId): array {
        return $this->etaRepository->getHistory($assignmentId);
    }
    
    /**
     * Validate ETA datetime
     * Rejects past dates/times
     * 
     * @param string $dateTime DateTime string to validate
     * @return array Validation result with isValid, message, and errors
     * 
     * Requirements: 2.3
     */
    public function validateETADateTime(string $dateTime): array {
        $errors = [];
        
        // Check if datetime is empty
        if (trim($dateTime) === '') {
            $errors[] = [
                'field' => 'eta_datetime',
                'message' => 'ETA date and time is required',
                'code' => 'REQUIRED_FIELD_MISSING'
            ];
            return [
                'isValid' => false,
                'message' => 'ETA date and time is required',
                'errors' => $errors
            ];
        }
        
        // Parse the datetime
        $etaTimestamp = strtotime($dateTime);
        if ($etaTimestamp === false) {
            $errors[] = [
                'field' => 'eta_datetime',
                'message' => 'Invalid date/time format',
                'code' => 'INVALID_FORMAT'
            ];
            return [
                'isValid' => false,
                'message' => 'Invalid date/time format',
                'errors' => $errors
            ];
        }
        
        // Check if ETA is in the past (Requirement 2.3)
        $currentTimestamp = time();
        if ($etaTimestamp <= $currentTimestamp) {
            $errors[] = [
                'field' => 'eta_datetime',
                'message' => 'ETA must be a future date and time',
                'code' => 'PAST_DATE_NOT_ALLOWED'
            ];
            return [
                'isValid' => false,
                'message' => 'ETA must be a future date and time',
                'errors' => $errors
            ];
        }
        
        return [
            'isValid' => true,
            'message' => 'Valid ETA datetime',
            'errors' => []
        ];
    }
    
    /**
     * Check if an assignment has ETA submitted
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return bool True if ETA exists
     */
    public function hasETA(int $assignmentId): bool {
        return $this->etaRepository->hasETA($assignmentId);
    }
    
    /**
     * Get ETA count for an assignment
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return int Number of ETA submissions
     */
    public function getETACount(int $assignmentId): int {
        return $this->etaRepository->countByAssignment($assignmentId);
    }
    
    /**
     * Update assignment feasibility status
     * 
     * @param int $assignmentId Assignment ID
     * @param string $status New feasibility status
     * @return bool Success
     */
    private function updateAssignmentFeasibilityStatus(int $assignmentId, string $status): bool {
        $sql = "UPDATE `engineer_assignments` SET `feasibility_status` = ?, `updated_at` = NOW() WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$status, $assignmentId], 'si');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows > 0;
    }
    
    /**
     * Log action for audit trail
     * 
     * @param int $userId User performing the action
     * @param int $assignmentId Assignment ID
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logAction(int $userId, int $assignmentId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details['assignment_id'] = $assignmentId;
            $details['entity_type'] = 'feasibility_eta';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId,
                $action,
                json_encode($details),
                $userId,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log ETA action: " . $e->getMessage());
        }
    }
}

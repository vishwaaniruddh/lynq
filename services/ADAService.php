<?php
/**
 * ADA Service
 * Handles business logic for ADA (Actual Date of Arrival) operations
 * 
 * Requirements: 3.4, 3.5
 * - 3.4: Record ADA with current date/time, GPS coordinates, and engineer ID
 * - 3.5: Update feasibility status to ada_submitted and enable feasibility check button
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/FeasibilityADARepository.php';
require_once __DIR__ . '/../repositories/FeasibilityETARepository.php';
require_once __DIR__ . '/../repositories/EngineerAssignmentRepository.php';

class ADAService {
    private $db;
    private $adaRepository;
    private $etaRepository;
    private $assignmentRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->adaRepository = new FeasibilityADARepository();
        $this->etaRepository = new FeasibilityETARepository();
        $this->assignmentRepository = new EngineerAssignmentRepository();
    }
    
    /**
     * Submit ADA for an assignment
     * 
     * @param int $assignmentId Engineer assignment ID
     * @param float $latitude GPS latitude
     * @param float $longitude GPS longitude
     * @param int $engineerId Engineer user ID submitting the ADA
     * @return array Result with success status and data/errors
     * 
     * Requirements: 3.4, 3.5
     */
    public function submitADA(int $assignmentId, float $latitude, float $longitude, int $engineerId): array {
        // Validate coordinates
        $validation = $this->validateCoordinates($latitude, $longitude);
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
                'message' => 'You are not authorized to submit ADA for this assignment',
                'code' => 'UNAUTHORIZED'
            ];
        }
        
        // Verify ETA has been submitted first
        if (!$this->etaRepository->hasETA($assignmentId)) {
            return [
                'success' => false,
                'message' => 'ETA must be submitted before ADA',
                'code' => 'PREREQUISITE_NOT_MET'
            ];
        }
        
        // Check if ADA already exists
        if ($this->adaRepository->hasADA($assignmentId)) {
            return [
                'success' => false,
                'message' => 'ADA has already been submitted for this assignment',
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            // Create ADA record (Requirement 3.4)
            $adaData = [
                'assignment_id' => $assignmentId,
                'ada_datetime' => date('Y-m-d H:i:s'),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'submitted_by' => $engineerId
            ];
            
            $ada = $this->adaRepository->create($adaData);
            
            // Update assignment feasibility_status to ada_submitted (Requirement 3.5)
            $this->updateAssignmentFeasibilityStatus($assignmentId, 'ada_submitted');
            
            // Log audit
            $this->logAction($engineerId, $assignmentId, 'ada_submitted', [
                'ada_id' => $ada['id'],
                'latitude' => $latitude,
                'longitude' => $longitude
            ]);
            
            return [
                'success' => true,
                'message' => 'ADA submitted successfully',
                'data' => $ada
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to submit ADA: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Get ADA for an assignment
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return array|null ADA record or null
     * 
     * Requirements: 3.4
     */
    public function getADA(int $assignmentId): ?array {
        return $this->adaRepository->findByAssignment($assignmentId);
    }
    
    /**
     * Get ADA with full details including site information
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return array|null ADA record with details or null
     */
    public function getADAWithDetails(int $assignmentId): ?array {
        return $this->adaRepository->findByAssignmentWithDetails($assignmentId);
    }
    
    /**
     * Validate GPS coordinates
     * 
     * @param float $latitude Latitude value
     * @param float $longitude Longitude value
     * @return array Validation result with isValid, message, and errors
     */
    public function validateCoordinates(float $latitude, float $longitude): array {
        $errors = [];
        
        // Validate latitude range (-90 to 90)
        if ($latitude < -90 || $latitude > 90) {
            $errors[] = [
                'field' => 'latitude',
                'message' => 'Latitude must be between -90 and 90 degrees',
                'code' => 'INVALID_RANGE'
            ];
        }
        
        // Validate longitude range (-180 to 180)
        if ($longitude < -180 || $longitude > 180) {
            $errors[] = [
                'field' => 'longitude',
                'message' => 'Longitude must be between -180 and 180 degrees',
                'code' => 'INVALID_RANGE'
            ];
        }
        
        // Check for null island (0,0) which often indicates GPS error
        if ($latitude == 0 && $longitude == 0) {
            $errors[] = [
                'field' => 'coordinates',
                'message' => 'Invalid coordinates (0,0). Please ensure GPS is enabled and try again.',
                'code' => 'NULL_ISLAND'
            ];
        }
        
        if (!empty($errors)) {
            return [
                'isValid' => false,
                'message' => $errors[0]['message'],
                'errors' => $errors
            ];
        }
        
        return [
            'isValid' => true,
            'message' => 'Valid coordinates',
            'errors' => []
        ];
    }
    
    /**
     * Check if an assignment has ADA submitted
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return bool True if ADA exists
     */
    public function hasADA(int $assignmentId): bool {
        return $this->adaRepository->hasADA($assignmentId);
    }
    
    /**
     * Get all ADAs submitted by an engineer
     * 
     * @param int $engineerId Engineer user ID
     * @return array List of ADA records
     */
    public function getADAsByEngineer(int $engineerId): array {
        return $this->adaRepository->findByEngineer($engineerId);
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
            $details['entity_type'] = 'feasibility_ada';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId,
                $action,
                json_encode($details),
                $userId,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log ADA action: " . $e->getMessage());
        }
    }
}

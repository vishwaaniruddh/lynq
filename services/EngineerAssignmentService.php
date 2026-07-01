<?php
/**
 * Engineer Assignment Service
 * Handles business logic for engineer assignment operations
 * 
 * Requirements: 5.1, 5.2, 5.4, 5.5, 6.1
 * - 5.1: Create engineer assignment records for selected sites
 * - 5.2: Record assignment timestamp, assigning user, set status to assigned
 * - 5.4: Prevent duplicate active assignments for same site
 * - 5.5: Only allow assignment of sites accepted by contractor
 * - 6.1: Display only sites assigned to specific engineer
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/EngineerAssignmentRepository.php';
require_once __DIR__ . '/../repositories/DelegationRepository.php';
require_once __DIR__ . '/BulkOperationService.php';

class EngineerAssignmentService {
    private $db;
    private $assignmentRepository;
    private $delegationRepository;
    private $bulkOperationService;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->assignmentRepository = new EngineerAssignmentRepository();
        $this->delegationRepository = new DelegationRepository();
        $this->bulkOperationService = new BulkOperationService();
    }
    
    /**
     * Assign a site to an engineer
     * 
     * @param int $siteId Site ID
     * @param int $engineerId Engineer user ID
     * @param int $assignedBy User ID performing the assignment
     * @param int $contractorId Contractor company ID (for authorization check)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 5.1, 5.2, 5.4, 5.5
     */
    public function assignToEngineer(int $siteId, int $engineerId, int $assignedBy, int $contractorId): array {
        // Check if site has an accepted delegation for this contractor (Requirement 5.5)
        $acceptedDelegation = $this->delegationRepository->getAcceptedDelegation($siteId, $contractorId);
        if (!$acceptedDelegation) {
            return [
                'success' => false,
                'message' => 'Site does not have an accepted delegation for this contractor',
                'code' => 'AUTHORIZATION_ERROR'
            ];
        }
        
        // Check for duplicate active assignment (Requirement 5.4)
        if ($this->assignmentRepository->checkDuplicateAssignment($siteId)) {
            return [
                'success' => false,
                'message' => 'An active assignment already exists for this site',
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            // Create assignment record (Requirements 5.1, 5.2)
            $assignmentData = [
                'site_id' => $siteId,
                'delegation_id' => $acceptedDelegation['id'],
                'engineer_id' => $engineerId,
                'assigned_by' => $assignedBy
            ];
            
            $assignmentId = $this->assignmentRepository->create($assignmentData);
            
            // Log audit
            $this->logAction($assignedBy, $assignmentId, 'engineer_assigned', [
                'site_id' => $siteId,
                'engineer_id' => $engineerId,
                'contractor_id' => $contractorId
            ]);
            
            // Return created assignment
            $assignment = $this->assignmentRepository->findById($assignmentId);
            
            return [
                'success' => true,
                'message' => 'Site assigned to engineer successfully',
                'data' => $assignment
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to assign site: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Bulk assign multiple sites to an engineer
     * 
     * @param array $siteIds Array of site IDs
     * @param int $engineerId Engineer user ID
     * @param int $assignedBy User ID performing the assignment
     * @param int $contractorId Contractor company ID
     * @return array Result with success/error counts
     * 
     * Requirements: 5.1, 5.3
     */
    public function bulkAssignToEngineer(array $siteIds, int $engineerId, int $assignedBy, int $contractorId): array {
        $results = [
            'success' => true,
            'total' => count($siteIds),
            'successCount' => 0,
            'errorCount' => 0,
            'errors' => [],
            'createdIds' => []
        ];
        
        foreach ($siteIds as $siteId) {
            $result = $this->assignToEngineer($siteId, $engineerId, $assignedBy, $contractorId);
            
            if ($result['success']) {
                $results['successCount']++;
                $results['createdIds'][] = $result['data']['id'];
            } else {
                $results['errorCount']++;
                $results['errors'][] = [
                    'site_id' => $siteId,
                    'message' => $result['message']
                ];
            }
        }
        
        $results['success'] = $results['errorCount'] === 0;
        $results['message'] = "Assigned {$results['successCount']} of {$results['total']} sites";
        
        return $results;
    }
    
    /**
     * Get assignments by engineer with filters
     * 
     * @param int $engineerId Engineer user ID
     * @param array $filters Optional filters: status, city, state, search, page, limit
     * @return array Paginated result with data, total, page, limit, totalPages
     * 
     * Requirements: 6.1
     */
    public function getAssignmentsByEngineer(int $engineerId, array $filters = []): array {
        return $this->assignmentRepository->findByEngineer($engineerId, $filters);
    }
    
    /**
     * Get assignments by contractor with filters
     * 
     * @param int $contractorId Contractor company ID
     * @param array $filters Optional filters: status, engineer_id, page, limit
     * @return array Paginated result with data, total, page, limit, totalPages
     */
    public function getAssignmentsByContractor(int $contractorId, array $filters = []): array {
        return $this->assignmentRepository->findByContractor($contractorId, $filters);
    }
    
    /**
     * Get assignment by ID
     * 
     * @param int $assignmentId Assignment ID
     * @return array|null Assignment record or null if not found
     */
    public function getAssignment(int $assignmentId): ?array {
        return $this->assignmentRepository->findById($assignmentId);
    }
    
    /**
     * Update assignment status
     * 
     * @param int $assignmentId Assignment ID
     * @param string $status New status
     * @param int $updatedBy User ID performing the update
     * @return array Result with success status
     */
    public function updateAssignmentStatus(int $assignmentId, string $status, int $updatedBy): array {
        // Verify assignment exists
        $assignment = $this->assignmentRepository->findById($assignmentId);
        if (!$assignment) {
            return [
                'success' => false,
                'message' => 'Assignment not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        try {
            $this->assignmentRepository->updateStatus($assignmentId, $status);
            
            // Log audit
            $this->logAction($updatedBy, $assignmentId, 'assignment_status_updated', [
                'old_status' => $assignment['status'],
                'new_status' => $status
            ]);
            
            // Return updated assignment
            $updatedAssignment = $this->assignmentRepository->findById($assignmentId);
            
            return [
                'success' => true,
                'message' => 'Assignment status updated successfully',
                'data' => $updatedAssignment
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update assignment status: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Get assignment history for a site
     * 
     * @param int $siteId Site ID
     * @return array Array of assignment records
     */
    public function getAssignmentHistory(int $siteId): array {
        return $this->assignmentRepository->getAssignmentHistory($siteId);
    }
    
    /**
     * Get assignments by site
     * 
     * @param int $siteId Site ID
     * @return array Array of assignment records
     */
    public function getAssignmentsBySite(int $siteId): array {
        return $this->assignmentRepository->findBySite($siteId);
    }
    
    /**
     * Get assignments by delegation
     * 
     * @param int $delegationId Delegation ID
     * @return array Array of assignment records
     */
    public function getAssignmentsByDelegation(int $delegationId): array {
        return $this->assignmentRepository->findByDelegation($delegationId);
    }
    
    /**
     * Check if engineer can access an assignment
     * 
     * @param int $assignmentId Assignment ID
     * @param int $engineerId Engineer user ID
     * @return bool True if engineer can access
     * 
     * Requirements: 6.1
     */
    public function canEngineerAccess(int $assignmentId, int $engineerId): bool {
        return $this->assignmentRepository->canEngineerAccess($assignmentId, $engineerId);
    }
    
    /**
     * Get assignment counts by status for a contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array Array with status counts
     */
    public function getAssignmentCountsByStatusForContractor(int $contractorId): array {
        return $this->assignmentRepository->countByStatusForContractor($contractorId);
    }
    
    /**
     * Get assignment counts by status for an engineer
     * 
     * @param int $engineerId Engineer user ID
     * @return array Array with status counts
     */
    public function getAssignmentCountsByStatusForEngineer(int $engineerId): array {
        return $this->assignmentRepository->countByStatusForEngineer($engineerId);
    }
    
    /**
     * Get assignment counts by feasibility status for an engineer
     * 
     * @param int $engineerId Engineer user ID
     * @return array Array with feasibility status counts
     */
    public function getAssignmentCountsByFeasibilityStatusForEngineer(int $engineerId): array {
        return $this->assignmentRepository->countByFeasibilityStatusForEngineer($engineerId);
    }
    
    /**
     * Get distinct engineers for a contractor's assignments
     * 
     * @param int $contractorId Contractor company ID
     * @return array Array of engineer records
     */
    public function getDistinctEngineers(int $contractorId): array {
        return $this->assignmentRepository->getDistinctEngineers($contractorId);
    }
    
    /**
     * Export assignments for a contractor
     * 
     * @param int $contractorId Contractor company ID
     * @param array $filters Optional filters
     * @return array Array of assignment records
     */
    public function exportAssignments(int $contractorId, array $filters = []): array {
        return $this->assignmentRepository->findAllForExport($contractorId, $filters);
    }
    
    /**
     * Check if site has active assignment
     * 
     * @param int $siteId Site ID
     * @return bool True if site has active assignment
     * 
     * Requirements: 5.4
     */
    public function hasActiveAssignment(int $siteId): bool {
        return $this->assignmentRepository->checkDuplicateAssignment($siteId);
    }
    
    /**
     * Get active assignment for a site
     * 
     * @param int $siteId Site ID
     * @return array|null Active assignment or null
     */
    public function getActiveAssignment(int $siteId): ?array {
        return $this->assignmentRepository->getActiveAssignment($siteId);
    }
    
    /**
     * Get distinct LHOs for an engineer's assignments
     * 
     * @param int $engineerId Engineer user ID
     * @return array Array of distinct LHO values
     */
    public function getDistinctLHOsForEngineer(int $engineerId): array {
        return $this->assignmentRepository->getDistinctLHOsForEngineer($engineerId);
    }
    
    /**
     * Get distinct cities for an engineer's assignments
     * 
     * @param int $engineerId Engineer user ID
     * @return array Array of distinct city values
     */
    public function getDistinctCitiesForEngineer(int $engineerId): array {
        return $this->assignmentRepository->getDistinctCitiesForEngineer($engineerId);
    }
    
    /**
     * Get distinct states for an engineer's assignments
     * 
     * @param int $engineerId Engineer user ID
     * @return array Array of distinct state values
     */
    public function getDistinctStatesForEngineer(int $engineerId): array {
        return $this->assignmentRepository->getDistinctStatesForEngineer($engineerId);
    }
    
    /**
     * Check if site can be assigned (has accepted delegation for contractor)
     * 
     * @param int $siteId Site ID
     * @param int $contractorId Contractor company ID
     * @return bool True if site can be assigned
     * 
     * Requirements: 5.5
     */
    public function canAssignSite(int $siteId, int $contractorId): bool {
        $acceptedDelegation = $this->delegationRepository->getAcceptedDelegation($siteId, $contractorId);
        return $acceptedDelegation !== null;
    }
    
    /**
     * Import engineer assignments from Excel file
     * 
     * @param string $filePath Path to the Excel file
     * @param int $assignedBy User ID performing the assignment
     * @param int $contractorId Contractor company ID
     * @return array BulkOperationResult with success/error counts
     * 
     * Requirements: 5.3
     */
    public function importAssignmentsFromExcel(string $filePath, int $assignedBy, int $contractorId): array {
        // Get column mapping for engineer assignments
        $columnMapping = $this->bulkOperationService->getEngineerAssignmentColumnMapping();
        
        // Parse Excel file
        $parseResult = $this->bulkOperationService->parseExcelFile($filePath, $columnMapping);
        
        if (!$parseResult['success']) {
            return [
                'success' => false,
                'message' => $parseResult['message'],
                'totalRows' => 0,
                'successCount' => 0,
                'errorCount' => 0,
                'errors' => $parseResult['errors'],
                'createdIds' => []
            ];
        }
        
        // Validate bulk data
        $validationResult = $this->bulkOperationService->validateBulkData(
            $parseResult['data'],
            function($row) use ($contractorId) {
                return $this->validateAssignmentRowForImport($row, $contractorId);
            }
        );
        
        // Process valid rows
        $result = new BulkOperationResult();
        $result->totalRows = count($parseResult['data']);
        $result->errorCount = $validationResult['invalidCount'];
        $result->errors = $validationResult['errors'];
        
        foreach ($validationResult['validRows'] as $index => $rowData) {
            $rowNumber = $rowData['_row_number'] ?? ($index + 2);
            
            $siteId = (int)$rowData['site_id'];
            $engineerId = (int)$rowData['engineer_id'];
            
            $assignResult = $this->assignToEngineer($siteId, $engineerId, $assignedBy, $contractorId);
            
            if ($assignResult['success']) {
                $result->successCount++;
                $result->createdIds[] = $assignResult['data']['id'];
            } else {
                $result->errorCount++;
                $result->errors[$rowNumber] = [$assignResult['message']];
            }
        }
        
        $result->success = $result->errorCount === 0;
        $result->message = "Assigned {$result->successCount} of {$result->totalRows} sites";
        
        if ($result->errorCount > 0) {
            $result->message .= " ({$result->errorCount} errors)";
        }
        
        return $result->toArray();
    }
    
    /**
     * Validate a single row for assignment import
     * 
     * @param array $row Row data
     * @param int $contractorId Contractor company ID
     * @return array Validation result with 'isValid' and 'errors'
     */
    private function validateAssignmentRowForImport(array $row, int $contractorId): array {
        $errors = [];
        
        // Check required fields
        if (empty($row['site_id'])) {
            $errors[] = [
                'field' => 'site_id',
                'message' => 'Site ID is required',
                'code' => 'REQUIRED_FIELD_MISSING'
            ];
        } elseif (!is_numeric($row['site_id'])) {
            $errors[] = [
                'field' => 'site_id',
                'message' => 'Site ID must be a number',
                'code' => 'INVALID_FORMAT'
            ];
        }
        
        if (empty($row['engineer_id'])) {
            $errors[] = [
                'field' => 'engineer_id',
                'message' => 'Engineer ID is required',
                'code' => 'REQUIRED_FIELD_MISSING'
            ];
        } elseif (!is_numeric($row['engineer_id'])) {
            $errors[] = [
                'field' => 'engineer_id',
                'message' => 'Engineer ID must be a number',
                'code' => 'INVALID_FORMAT'
            ];
        }
        
        // Verify site has accepted delegation for contractor (if no other errors)
        if (empty($errors) && !empty($row['site_id'])) {
            $acceptedDelegation = $this->delegationRepository->getAcceptedDelegation((int)$row['site_id'], $contractorId);
            if (!$acceptedDelegation) {
                $errors[] = [
                    'field' => 'site_id',
                    'message' => 'Site does not have an accepted delegation for this contractor',
                    'code' => 'AUTHORIZATION_ERROR'
                ];
            }
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
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
            $details['entity_type'] = 'engineer_assignment';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId,
                $action,
                json_encode($details),
                $userId,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log assignment action: " . $e->getMessage());
        }
    }
}

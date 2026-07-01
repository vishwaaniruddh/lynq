<?php
/**
 * Delegation Service
 * Handles business logic for site delegation operations
 * 
 * Requirements: 2.1, 2.2, 2.4, 3.1, 3.2, 4.1, 4.2, 4.3
 * - 2.1: Create delegation records linking sites to contractors
 * - 2.2: Record delegation timestamp, delegating user, set status to pending
 * - 2.4: Prevent duplicate active delegations to same contractor
 * - 3.1: Display all delegations with status, contractor, dates
 * - 3.2: Filter delegations by status, contractor, date range
 * - 4.1: Display only sites delegated to contractor's company
 * - 4.2: Accept delegation and update status
 * - 4.3: Reject delegation with required notes
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/DelegationRepository.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';
require_once __DIR__ . '/BulkOperationService.php';

class DelegationService {
    private $db;
    private $delegationRepository;
    private $siteRepository;
    private $bulkOperationService;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->delegationRepository = new DelegationRepository();
        $this->siteRepository = new SiteRepository();
        $this->bulkOperationService = new BulkOperationService();
    }
    
    /**
     * Delegate a site to a contractor
     * 
     * @param int $siteId Site ID
     * @param int $contractorId Contractor company ID
     * @param int $delegatedBy User ID performing the delegation
     * @return array Result with success status and data/errors
     * 
     * Requirements: 2.1, 2.2, 2.4
     */
    public function delegateSite(int $siteId, int $contractorId, int $delegatedBy): array {
        // Verify site exists
        $site = $this->siteRepository->findById($siteId);
        if (!$site) {
            return [
                'success' => false,
                'message' => 'Site not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check for duplicate active delegation (Requirement 2.4)
        if ($this->delegationRepository->checkDuplicateDelegation($siteId, $contractorId)) {
            return [
                'success' => false,
                'message' => 'An active delegation already exists for this site and contractor',
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            // Create delegation record (Requirements 2.1, 2.2)
            $delegationData = [
                'site_id' => $siteId,
                'contractor_id' => $contractorId,
                'delegated_by' => $delegatedBy
            ];
            
            $delegationId = $this->delegationRepository->create($delegationData);
            
            // Log to delegation history
            $this->logDelegationHistory($delegationId, 'created', $delegatedBy, null);
            
            // Log audit
            $this->logAction($delegatedBy, $delegationId, 'delegation_created', [
                'site_id' => $siteId,
                'contractor_id' => $contractorId
            ]);
            
            // Return created delegation
            $delegation = $this->delegationRepository->findById($delegationId);
            
            return [
                'success' => true,
                'message' => 'Site delegated successfully',
                'data' => $delegation
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delegate site: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Bulk delegate multiple sites to a contractor
     * 
     * @param array $siteIds Array of site IDs
     * @param int $contractorId Contractor company ID
     * @param int $delegatedBy User ID performing the delegation
     * @return array Result with success/error counts
     * 
     * Requirements: 2.1, 2.3
     */
    public function bulkDelegateSites(array $siteIds, int $contractorId, int $delegatedBy): array {
        $results = [
            'success' => true,
            'total' => count($siteIds),
            'successCount' => 0,
            'errorCount' => 0,
            'errors' => [],
            'createdIds' => []
        ];
        
        foreach ($siteIds as $siteId) {
            $result = $this->delegateSite($siteId, $contractorId, $delegatedBy);
            
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
        $results['message'] = "Delegated {$results['successCount']} of {$results['total']} sites";
        
        return $results;
    }
    
    /**
     * Accept a delegation
     * 
     * @param int $delegationId Delegation ID
     * @param int $respondedBy User ID responding
     * @return array Result with success status
     * 
     * Requirements: 4.2
     */
    public function acceptDelegation(int $delegationId, int $respondedBy): array {
        // Verify delegation exists
        $delegation = $this->delegationRepository->findById($delegationId);
        if (!$delegation) {
            return [
                'success' => false,
                'message' => 'Delegation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Verify delegation is pending
        if ($delegation['status'] !== 'pending') {
            return [
                'success' => false,
                'message' => 'Delegation is not in pending status',
                'code' => 'INVALID_STATUS'
            ];
        }
        
        try {
            // Update status to accepted
            $this->delegationRepository->updateStatusAccepted($delegationId, $respondedBy);
            
            // Log to delegation history
            $this->logDelegationHistory($delegationId, 'accepted', $respondedBy, null);
            
            // Log audit
            $this->logAction($respondedBy, $delegationId, 'delegation_accepted', [
                'site_id' => $delegation['site_id'],
                'contractor_id' => $delegation['contractor_id']
            ]);
            
            // Return updated delegation
            $updatedDelegation = $this->delegationRepository->findById($delegationId);
            
            return [
                'success' => true,
                'message' => 'Delegation accepted successfully',
                'data' => $updatedDelegation
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to accept delegation: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Reject a delegation with notes
     * 
     * @param int $delegationId Delegation ID
     * @param string $notes Rejection notes (required)
     * @param int $respondedBy User ID responding
     * @return array Result with success status
     * 
     * Requirements: 4.3
     */
    public function rejectDelegation(int $delegationId, string $notes, int $respondedBy): array {
        // Validate rejection notes are provided (Requirement 4.3)
        if (trim($notes) === '') {
            return [
                'success' => false,
                'message' => 'Rejection notes are required',
                'errors' => [['field' => 'notes', 'message' => 'Rejection notes are required', 'code' => 'REQUIRED_FIELD_MISSING']],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Verify delegation exists
        $delegation = $this->delegationRepository->findById($delegationId);
        if (!$delegation) {
            return [
                'success' => false,
                'message' => 'Delegation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Verify delegation is pending
        if ($delegation['status'] !== 'pending') {
            return [
                'success' => false,
                'message' => 'Delegation is not in pending status',
                'code' => 'INVALID_STATUS'
            ];
        }
        
        try {
            // Update status to rejected with notes
            $this->delegationRepository->updateStatusRejected($delegationId, $notes, $respondedBy);
            
            // Log to delegation history
            $this->logDelegationHistory($delegationId, 'rejected', $respondedBy, $notes);
            
            // Log audit
            $this->logAction($respondedBy, $delegationId, 'delegation_rejected', [
                'site_id' => $delegation['site_id'],
                'contractor_id' => $delegation['contractor_id'],
                'notes' => $notes
            ]);
            
            // Return updated delegation
            $updatedDelegation = $this->delegationRepository->findById($delegationId);
            
            return [
                'success' => true,
                'message' => 'Delegation rejected successfully',
                'data' => $updatedDelegation
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to reject delegation: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Cancel a delegation (ADV only)
     * 
     * @param int $delegationId Delegation ID
     * @param int $cancelledBy User ID cancelling
     * @return array Result with success status
     */
    public function cancelDelegation(int $delegationId, int $cancelledBy): array {
        // Verify delegation exists
        $delegation = $this->delegationRepository->findById($delegationId);
        if (!$delegation) {
            return [
                'success' => false,
                'message' => 'Delegation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        try {
            // Update status to cancelled
            $this->delegationRepository->updateStatus($delegationId, 'cancelled');
            
            // Log to delegation history
            $this->logDelegationHistory($delegationId, 'cancelled', $cancelledBy, 'Delegation cancelled by ADV');
            
            // Log audit
            $this->logAction($cancelledBy, $delegationId, 'delegation_cancelled', [
                'site_id' => $delegation['site_id'],
                'contractor_id' => $delegation['contractor_id'],
                'previous_status' => $delegation['status']
            ]);
            
            return [
                'success' => true,
                'message' => 'Delegation cancelled successfully',
                'data' => null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to cancel delegation: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Get delegations by ADV company with filters
     * 
     * @param int $advCompanyId ADV company ID
     * @param array $filters Optional filters: status, contractor_id, date_from, date_to, page, limit
     * @return array Paginated result with data, total, page, limit, totalPages
     * 
     * Requirements: 3.1, 3.2
     */
    public function getDelegationsByADV(int $advCompanyId, array $filters = []): array {
        return $this->delegationRepository->findByADV($advCompanyId, $filters);
    }
    
    /**
     * Get delegations by contractor company with filters
     * 
     * @param int $contractorId Contractor company ID
     * @param array $filters Optional filters: status, date_from, date_to, page, limit
     * @return array Paginated result with data, total, page, limit, totalPages
     * 
     * Requirements: 4.1
     */
    public function getDelegationsByContractor(int $contractorId, array $filters = []): array {
        return $this->delegationRepository->findByContractor($contractorId, $filters);
    }
    
    /**
     * Get delegation by ID
     * 
     * @param int $delegationId Delegation ID
     * @return array|null Delegation record or null if not found
     */
    public function getDelegation(int $delegationId): ?array {
        return $this->delegationRepository->findById($delegationId);
    }
    
    /**
     * Get pending delegations for a contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array Array of pending delegation records
     */
    public function getPendingDelegations(int $contractorId): array {
        return $this->delegationRepository->findPendingByContractor($contractorId);
    }
    
    /**
     * Get accepted delegations for a contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array Array of accepted delegation records
     */
    public function getAcceptedDelegations(int $contractorId): array {
        return $this->delegationRepository->findAcceptedByContractor($contractorId);
    }
    
    /**
     * Get delegation history for a site
     * 
     * @param int $siteId Site ID
     * @return array Array of delegation records
     */
    public function getDelegationHistory(int $siteId): array {
        return $this->delegationRepository->findBySite($siteId);
    }
    
    /**
     * Get history entries for a specific delegation
     * 
     * @param int $delegationId Delegation ID
     * @return array Array of history records
     * 
     * Requirements: 3.3
     */
    public function getDelegationHistoryById(int $delegationId): array {
        return $this->delegationRepository->getHistoryByDelegationId($delegationId);
    }
    
    /**
     * Export delegations with filters
     * 
     * @param int $advCompanyId ADV company ID
     * @param array $filters Optional filters
     * @return array Array of delegation records
     * 
     * Requirements: 3.4
     */
    public function exportDelegations(int $advCompanyId, array $filters = []): array {
        return $this->delegationRepository->findAllForExport($advCompanyId, $filters);
    }
    
    /**
     * Generate Excel export of delegations
     * 
     * @param int $advCompanyId ADV company ID
     * @param array $filters Optional filters
     * @return string Path to generated file or empty string on failure
     * 
     * Requirements: 3.4
     */
    public function generateDelegationExport(int $advCompanyId, array $filters = []): string {
        // Get delegations for export
        $delegations = $this->exportDelegations($advCompanyId, $filters);
        
        // Prepare data for export with comprehensive site and delegation information
        $exportData = array_map(function($delegation) {
            return [
                'id' => $delegation['id'],
                'site_id' => $delegation['site_id'],
                'site_name' => $delegation['site_name'] ?? '',
                'lho' => $delegation['lho'] ?? '',
                'city' => $delegation['city'] ?? '',
                'state' => $delegation['state'] ?? '',
                'contractor_id' => $delegation['contractor_id'],
                'contractor_name' => $delegation['contractor_name'] ?? '',
                'status' => $delegation['status'],
                'delegated_by_name' => $delegation['delegated_by_name'] ?? '',
                'delegated_at' => $delegation['delegated_at'],
                'responded_by_name' => $delegation['responded_by_name'] ?? '',
                'responded_at' => $delegation['responded_at'] ?? '',
                'rejection_notes' => $delegation['rejection_notes'] ?? ''
            ];
        }, $delegations);
        
        // Enhanced headers with site location information
        $headers = [
            'ID', 'Site ID', 'Site Name', 'LHO', 'City', 'State',
            'Contractor ID', 'Contractor Name', 'Status',
            'Delegated By', 'Delegated At', 'Responded By', 'Responded At',
            'Rejection Notes'
        ];
        
        // Column mapping for export
        $columnMapping = [
            'id' => 'A',
            'site_id' => 'B',
            'site_name' => 'C',
            'lho' => 'D',
            'city' => 'E',
            'state' => 'F',
            'contractor_id' => 'G',
            'contractor_name' => 'H',
            'status' => 'I',
            'delegated_by_name' => 'J',
            'delegated_at' => 'K',
            'responded_by_name' => 'L',
            'responded_at' => 'M',
            'rejection_notes' => 'N'
        ];
        
        return $this->bulkOperationService->generateExcelExport($exportData, $headers, $columnMapping, 'delegations_export');
    }
    
    /**
     * Get delegation counts by status
     * 
     * @param int $advCompanyId ADV company ID
     * @return array Array with status counts
     */
    public function getDelegationCountsByStatus(int $advCompanyId): array {
        return $this->delegationRepository->countByStatus($advCompanyId);
    }
    
    /**
     * Get delegation counts by status for a contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array Array with status counts
     */
    public function getDelegationCountsByStatusForContractor(int $contractorId): array {
        return $this->delegationRepository->countByStatusForContractor($contractorId);
    }
    
    /**
     * Get distinct LHOs for a contractor's delegated sites
     * 
     * @param int $contractorId Contractor company ID
     * @return array Array of distinct LHO values
     */
    public function getDistinctLHOsForContractor(int $contractorId): array {
        return $this->delegationRepository->getDistinctLHOsForContractor($contractorId);
    }
    
    /**
     * Get distinct contractors for an ADV company's delegations
     * 
     * @param int $advCompanyId ADV company ID
     * @return array Array of contractor records
     */
    public function getDistinctContractors(int $advCompanyId): array {
        return $this->delegationRepository->getDistinctContractors($advCompanyId);
    }
    
    /**
     * Check if a site has any active delegation
     * 
     * @param int $siteId Site ID
     * @return bool True if site has active delegation
     */
    public function hasActiveDelegation(int $siteId): bool {
        return $this->delegationRepository->hasAnyActiveDelegation($siteId);
    }
    
    /**
     * Check if duplicate delegation exists
     * 
     * @param int $siteId Site ID
     * @param int $contractorId Contractor company ID
     * @return bool True if duplicate exists
     * 
     * Requirements: 2.4
     */
    public function isDuplicateDelegation(int $siteId, int $contractorId): bool {
        return $this->delegationRepository->checkDuplicateDelegation($siteId, $contractorId);
    }
    
    /**
     * Get accepted delegation for a site and contractor
     * 
     * @param int $siteId Site ID
     * @param int $contractorId Contractor company ID
     * @return array|null Accepted delegation or null
     */
    public function getAcceptedDelegation(int $siteId, int $contractorId): ?array {
        return $this->delegationRepository->getAcceptedDelegation($siteId, $contractorId);
    }
    
    /**
     * Import delegations from Excel file
     * 
     * @param string $filePath Path to the Excel file
     * @param int $delegatedBy User ID performing the delegation
     * @return array BulkOperationResult with success/error counts
     * 
     * Requirements: 2.3
     */
    public function importDelegationsFromExcel(string $filePath, int $delegatedBy): array {
        // Get column mapping for delegations
        $columnMapping = $this->bulkOperationService->getDelegationColumnMapping();
        
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
            function($row) {
                return $this->validateDelegationRowForImport($row);
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
            $contractorId = (int)$rowData['contractor_id'];
            
            $delegateResult = $this->delegateSite($siteId, $contractorId, $delegatedBy);
            
            if ($delegateResult['success']) {
                $result->successCount++;
                $result->createdIds[] = $delegateResult['data']['id'];
            } else {
                $result->errorCount++;
                $result->errors[$rowNumber] = [$delegateResult['message']];
            }
        }
        
        $result->success = $result->errorCount === 0;
        $result->message = "Delegated {$result->successCount} of {$result->totalRows} sites";
        
        if ($result->errorCount > 0) {
            $result->message .= " ({$result->errorCount} errors)";
        }
        
        return $result->toArray();
    }
    
    /**
     * Validate a single row for delegation import
     * 
     * @param array $row Row data
     * @return array Validation result with 'isValid' and 'errors'
     */
    private function validateDelegationRowForImport(array $row): array {
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
        
        if (empty($row['contractor_id'])) {
            $errors[] = [
                'field' => 'contractor_id',
                'message' => 'Contractor ID is required',
                'code' => 'REQUIRED_FIELD_MISSING'
            ];
        } elseif (!is_numeric($row['contractor_id'])) {
            $errors[] = [
                'field' => 'contractor_id',
                'message' => 'Contractor ID must be a number',
                'code' => 'INVALID_FORMAT'
            ];
        }
        
        // Verify site exists (if no other errors)
        if (empty($errors) && !empty($row['site_id'])) {
            $site = $this->siteRepository->findById((int)$row['site_id']);
            if (!$site) {
                $errors[] = [
                    'field' => 'site_id',
                    'message' => 'Site not found',
                    'code' => 'NOT_FOUND'
                ];
            }
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Log delegation history
     * 
     * @param int $delegationId Delegation ID
     * @param string $action Action type
     * @param int $performedBy User ID
     * @param string|null $notes Optional notes
     */
    private function logDelegationHistory(int $delegationId, string $action, int $performedBy, ?string $notes): void {
        try {
            $sql = "INSERT INTO delegation_history (delegation_id, action, performed_by, notes) 
                    VALUES (?, ?, ?, ?)";
            
            $stmt = $this->db->executeQuery($sql, [
                $delegationId,
                $action,
                $performedBy,
                $notes
            ], 'isis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log delegation history: " . $e->getMessage());
        }
    }
    
    /**
     * Log action for audit trail
     * 
     * @param int $userId User performing the action
     * @param int $delegationId Delegation ID
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logAction(int $userId, int $delegationId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details['delegation_id'] = $delegationId;
            $details['entity_type'] = 'delegation';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId,
                $action,
                json_encode($details),
                $userId,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log delegation action: " . $e->getMessage());
        }
    }
}

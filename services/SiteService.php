<?php
/**
 * Site Service
 * Handles business logic for site management operations
 * 
 * Requirements: 1.1, 1.4, 1.5
 * - 1.1: Create site records with all required fields
 * - 1.4: Automatically set created_at and created_by
 * - 1.5: Prevent duplicate site names within same LHO
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';
require_once __DIR__ . '/../models/Site.php';
require_once __DIR__ . '/BulkOperationService.php';

class SiteService {
    private $db;
    private $siteRepository;
    private $siteModel;
    private $bulkOperationService;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->siteRepository = new SiteRepository();
        $this->siteModel = new Site();
        $this->bulkOperationService = new BulkOperationService();
    }
    
    /**
     * Create a new site record
     * 
     * @param array $data Site data
     * @param int $createdBy User ID performing the action
     * @return array Result with success status and data/errors
     * 
     * Requirements: 1.1, 1.4, 1.5
     */
    public function createSite(array $data, int $createdBy): array {
        // Validate site data (Requirements 7.1, 7.2, 7.3)
        $validation = $this->siteModel->validate($data);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Check for duplicate site name within LHO (Requirement 1.5)
        if (!isset($data['company_id'])) {
            return [
                'success' => false,
                'message' => 'Company ID is required',
                'errors' => [['field' => 'company_id', 'message' => 'Company ID is required', 'code' => 'REQUIRED_FIELD_MISSING']],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        if ($this->siteRepository->checkDuplicateName($data['site_name'], $data['lho'], $data['company_id'])) {
            return [
                'success' => false,
                'message' => 'A site with this name already exists in the same LHO',
                'errors' => [['field' => 'site_name', 'message' => 'Site name must be unique within LHO', 'code' => 'DUPLICATE_ERROR']],
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            // Prepare site data with audit fields (Requirement 1.4)
            $siteData = $this->prepareSiteData($data);
            $siteData['created_by'] = $createdBy;
            
            // Create site
            $siteId = $this->siteRepository->create($siteData);
            
            // Log audit
            $this->logAction($createdBy, $siteId, 'site_created', [
                'site_name' => $siteData['site_name'],
                'lho' => $siteData['lho']
            ]);
            
            // Return created site
            $site = $this->siteRepository->findById($siteId);
            
            return [
                'success' => true,
                'message' => 'Site created successfully',
                'data' => $site
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create site: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing site record
     * 
     * @param int $siteId Site ID
     * @param array $data Data to update
     * @param int $updatedBy User ID performing the action
     * @return array Result with success status and data/errors
     * 
     * Requirements: 1.1, 1.5
     */
    public function updateSite(int $siteId, array $data, int $updatedBy): array {
        // Check if site exists
        $existing = $this->siteRepository->findById($siteId);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Site not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Merge existing data with updates for validation
        $mergedData = array_merge($existing, $data);
        
        // Validate updated data
        $validation = $this->siteModel->validate($mergedData);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        // Check for duplicate if site_name or lho is being changed (Requirement 1.5)
        $siteName = $data['site_name'] ?? $existing['site_name'];
        $lho = $data['lho'] ?? $existing['lho'];
        $companyId = $data['company_id'] ?? $existing['company_id'];
        
        if ($this->siteRepository->checkDuplicateName($siteName, $lho, $companyId, $siteId)) {
            return [
                'success' => false,
                'message' => 'A site with this name already exists in the same LHO',
                'errors' => [['field' => 'site_name', 'message' => 'Site name must be unique within LHO', 'code' => 'DUPLICATE_ERROR']],
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        try {
            // Prepare update data
            $updateData = $this->prepareSiteData($data);
            $updateData['updated_by'] = $updatedBy;
            
            // Update site
            $this->siteRepository->update($siteId, $updateData);
            
            // Log audit
            $this->logAction($updatedBy, $siteId, 'site_updated', [
                'changes' => array_keys($data),
                'old_name' => $existing['site_name'],
                'new_name' => $siteName
            ]);
            
            // Return updated site
            $site = $this->siteRepository->findById($siteId);
            
            return [
                'success' => true,
                'message' => 'Site updated successfully',
                'data' => $site
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update site: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Get site by ID
     * 
     * @param int $siteId Site ID
     * @return array|null Site record or null if not found
     */
    public function getSite(int $siteId): ?array {
        return $this->siteRepository->findById($siteId);
    }
    
    /**
     * Delete a site (soft delete)
     * 
     * @param int $siteId Site ID
     * @param int $deletedBy User ID performing the action
     * @return array Result with success status
     */
    public function deleteSite(int $siteId, int $deletedBy): array {
        // Check if site exists
        $existing = $this->siteRepository->findById($siteId);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Site not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        try {
            // Soft delete
            $this->siteRepository->delete($siteId, $deletedBy);
            
            // Log audit
            $this->logAction($deletedBy, $siteId, 'site_deleted', [
                'site_name' => $existing['site_name'],
                'lho' => $existing['lho']
            ]);
            
            return [
                'success' => true,
                'message' => 'Site deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete site: ' . $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Get sites by company with filters
     * 
     * @param int $companyId Company ID
     * @param array $filters Optional filters: status, lho, search, page, limit, orderBy, orderDir
     * @return array Paginated result with data, total, page, limit, totalPages
     * 
     * Requirements: 1.1
     */
    public function getSitesByCompany(int $companyId, array $filters = []): array {
        return $this->siteRepository->findByCompany($companyId, $filters);
    }
    
    /**
     * Get sites by LHO
     * 
     * @param string $lho LHO name
     * @param array $filters Optional filters: status, companyId
     * @return array Array of site records
     * 
     * Requirements: 1.1
     */
    public function getSitesByLHO(string $lho, array $filters = []): array {
        return $this->siteRepository->findByLHO($lho, $filters);
    }
    
    /**
     * Get all active sites for a company (for dropdowns)
     * 
     * @param int $companyId Company ID
     * @return array Array of active site records
     */
    public function getActiveSites(int $companyId): array {
        return $this->siteRepository->findAllActive($companyId);
    }
    
    /**
     * Get distinct LHOs for a company
     * 
     * @param int $companyId Company ID
     * @return array Array of distinct LHO values
     */
    public function getDistinctLHOs(int $companyId): array {
        return $this->siteRepository->getDistinctLHOs($companyId);
    }
    
    /**
     * Get sites for export
     * 
     * @param int $companyId Company ID
     * @param array $filters Optional filters
     * @return array Array of site records
     */
    public function exportSites(int $companyId, array $filters = []): array {
        return $this->siteRepository->findAllForExport($companyId, $filters);
    }
    
    /**
     * Get site counts by status
     * 
     * @param int $companyId Company ID
     * @return array Array with status counts
     */
    public function getSiteCountsByStatus(int $companyId): array {
        return $this->siteRepository->countByStatus($companyId);
    }
    
    /**
     * Get undelegated sites for a company
     * 
     * @param int $companyId Company ID
     * @return array Array of undelegated site records
     */
    public function getUndelegatedSites(int $companyId): array {
        return $this->siteRepository->findUndelegated($companyId);
    }
    
    /**
     * Check if site name exists within LHO
     * 
     * @param string $siteName Site name
     * @param string $lho LHO
     * @param int $companyId Company ID
     * @param int|null $excludeId Site ID to exclude (for updates)
     * @return bool True if duplicate exists
     * 
     * Requirements: 1.5
     */
    public function isDuplicateSiteName(string $siteName, string $lho, int $companyId, ?int $excludeId = null): bool {
        return $this->siteRepository->checkDuplicateName($siteName, $lho, $companyId, $excludeId);
    }
    
    /**
     * Validate site data
     * 
     * @param array $data Site data to validate
     * @return array Validation result with 'isValid' and 'errors'
     */
    public function validateSiteData(array $data): array {
        return $this->siteModel->validate($data);
    }
    
    /**
     * Prepare site data for storage
     * 
     * @param array $data Raw site data
     * @return array Prepared site data
     */
    private function prepareSiteData(array $data): array {
        $prepared = [];
        
        // Define allowed fields
        $allowedFields = [
            'site_name', 'lho', 'bank_name', 'customer_name',
            'city', 'state', 'country', 'zone', 'address',
            'latitude', 'longitude', 'company_id', 'status'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                // Trim string values
                if (is_string($data[$field])) {
                    $prepared[$field] = trim($data[$field]);
                } else {
                    $prepared[$field] = $data[$field];
                }
            }
        }
        
        // Set default status if not provided
        if (!isset($prepared['status'])) {
            $prepared['status'] = 'active';
        }
        
        return $prepared;
    }
    
    /**
     * Log action for audit trail
     * 
     * @param int $userId User performing the action
     * @param int $siteId Site ID
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logAction(int $userId, int $siteId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details['site_id'] = $siteId;
            $details['entity_type'] = 'site';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId,
                $action,
                json_encode($details),
                $userId,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log site action: " . $e->getMessage());
        }
    }
    
    /**
     * Bulk create multiple sites
     * 
     * @param array $sitesData Array of site data arrays
     * @param int $createdBy User ID performing the action
     * @return array BulkOperationResult with success/error counts
     * 
     * Requirements: 1.2, 1.3
     */
    public function bulkCreateSites(array $sitesData, int $createdBy): array {
        $result = new BulkOperationResult();
        $result->totalRows = count($sitesData);
        
        foreach ($sitesData as $index => $siteData) {
            $rowNumber = $siteData['_row_number'] ?? ($index + 2);
            
            // Remove internal row tracking field
            unset($siteData['_row_number']);
            
            // Attempt to create site
            $createResult = $this->createSite($siteData, $createdBy);
            
            if ($createResult['success']) {
                $result->successCount++;
                $result->createdIds[] = $createResult['data']['id'];
            } else {
                $result->errorCount++;
                $result->errors[$rowNumber] = $createResult['errors'] ?? [$createResult['message']];
            }
        }
        
        $result->success = $result->errorCount === 0;
        $result->message = "Created {$result->successCount} of {$result->totalRows} sites";
        
        if ($result->errorCount > 0) {
            $result->message .= " ({$result->errorCount} errors)";
        }
        
        return $result->toArray();
    }
    
    /**
     * Import sites from Excel file
     * 
     * @param string $filePath Path to the Excel file
     * @param int $companyId Company ID for the sites
     * @param int $createdBy User ID performing the action
     * @return array BulkOperationResult with success/error counts
     * 
     * Requirements: 1.2, 1.3
     */
    public function importFromExcel(string $filePath, int $companyId, int $createdBy, string $originalFilename = 'upload.xlsx'): array {
        // Get column mapping for sites
        $columnMapping = $this->bulkOperationService->getSiteColumnMapping();
        
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
                'createdIds' => [],
                'logId' => null
            ];
        }
        
        // Validate bulk data
        $validationResult = $this->bulkOperationService->validateBulkData(
            $parseResult['data'],
            function($row) use ($companyId) {
                return $this->validateSiteRowForImport($row, $companyId);
            }
        );
        
        // Track success and error records for logging
        $successRecords = [];
        $errorRecords = [];
        
        // Add company_id to valid rows
        $sitesData = array_map(function($row) use ($companyId) {
            $row['company_id'] = $companyId;
            return $row;
        }, $validationResult['validRows']);
        
        // Add validation errors to error records
        foreach ($validationResult['invalidRows'] as $invalidRow) {
            $rowNum = $invalidRow['_row_number'] ?? 0;
            $invalidRow['_errors'] = $validationResult['errors'][$rowNum] ?? [];
            $errorRecords[] = $invalidRow;
        }
        
        // Create sites from valid rows
        $result = new BulkOperationResult();
        $result->totalRows = count($parseResult['data']);
        $result->errorCount = $validationResult['invalidCount'];
        $result->errors = $validationResult['errors'];
        
        // Process valid rows
        foreach ($sitesData as $index => $siteData) {
            $rowNumber = $siteData['_row_number'] ?? ($index + 2);
            $originalRow = $siteData; // Keep original for logging
            unset($siteData['_row_number']);
            unset($siteData['company_id']); // Remove before create, will be added internally
            
            $createResult = $this->createSite(array_merge($siteData, ['company_id' => $companyId]), $createdBy);
            
            if ($createResult['success']) {
                $result->successCount++;
                $result->createdIds[] = $createResult['data']['id'];
                // Add to success records
                unset($originalRow['_row_number']);
                $successRecords[] = $originalRow;
            } else {
                $result->errorCount++;
                $result->errors[$rowNumber] = $createResult['errors'] ?? [$createResult['message']];
                // Add to error records
                $originalRow['_errors'] = $createResult['errors'] ?? [$createResult['message']];
                $errorRecords[] = $originalRow;
            }
        }
        
        $result->success = $result->errorCount === 0;
        $result->message = "Imported {$result->successCount} of {$result->totalRows} sites";
        
        if ($result->errorCount > 0) {
            $result->message .= " ({$result->errorCount} errors)";
        }
        
        // Log the upload with success/error files
        $logId = null;
        try {
            require_once __DIR__ . '/BulkUploadLogService.php';
            $logService = new BulkUploadLogService();
            
            $columnHeaders = ['site_name', 'lho', 'bank_name', 'customer_name', 'city', 'state', 'country', 'zone', 'address', 'latitude', 'longitude', 'status'];
            
            $log = $logService->logUpload([
                'upload_type' => 'sites',
                'original_filename' => $originalFilename,
                'total_rows' => $result->totalRows,
                'success_count' => $result->successCount,
                'error_count' => $result->errorCount,
                'success_records' => $successRecords,
                'error_records' => $errorRecords,
                'uploaded_by' => $createdBy,
                'company_id' => $companyId,
                'column_headers' => $columnHeaders
            ]);
            $logId = $log['id'] ?? null;
        } catch (Exception $e) {
            error_log("Failed to log bulk upload: " . $e->getMessage());
        }
        
        $resultArray = $result->toArray();
        $resultArray['logId'] = $logId;
        
        return $resultArray;
    }
    
    /**
     * Validate a single row for import
     * 
     * @param array $row Row data
     * @param int $companyId Company ID
     * @return array Validation result with 'isValid' and 'errors'
     */
    private function validateSiteRowForImport(array $row, int $companyId): array {
        $errors = [];
        
        // Check required fields
        $requiredFields = ['site_name', 'lho', 'city', 'state', 'country'];
        foreach ($requiredFields as $field) {
            if (empty($row[$field])) {
                $errors[] = [
                    'field' => $field,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required',
                    'code' => 'REQUIRED_FIELD_MISSING'
                ];
            }
        }
        
        // Only validate masters if required fields are present
        if (empty($errors)) {
            // Validate against master tables
            $masterErrors = $this->validateMasterReferences($row);
            $errors = array_merge($errors, $masterErrors);
        }
        
        // Validate coordinates if provided
        if (isset($row['latitude']) && $row['latitude'] !== '' && $row['latitude'] !== null) {
            $lat = floatval($row['latitude']);
            if ($lat < -90 || $lat > 90) {
                $errors[] = [
                    'field' => 'latitude',
                    'message' => 'Latitude must be between -90 and 90',
                    'code' => 'INVALID_COORDINATE'
                ];
            }
        }
        
        if (isset($row['longitude']) && $row['longitude'] !== '' && $row['longitude'] !== null) {
            $lng = floatval($row['longitude']);
            if ($lng < -180 || $lng > 180) {
                $errors[] = [
                    'field' => 'longitude',
                    'message' => 'Longitude must be between -180 and 180',
                    'code' => 'INVALID_COORDINATE'
                ];
            }
        }
        
        // Check for duplicate site name within LHO (if no other errors)
        if (empty($errors) && !empty($row['site_name']) && !empty($row['lho'])) {
            if ($this->siteRepository->checkDuplicateName($row['site_name'], $row['lho'], $companyId)) {
                $errors[] = [
                    'field' => 'site_name',
                    'message' => 'Site name already exists in this LHO',
                    'code' => 'DUPLICATE_ERROR'
                ];
            }
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate master table references
     * Checks if LHO, country, state, city, zone, bank, and customer exist in master tables
     * 
     * @param array $row Row data
     * @return array Array of validation errors
     */
    private function validateMasterReferences(array $row): array {
        $errors = [];
        
        // Get database connection
        $db = DatabaseConfig::getInstance();
        
        // Validate LHO
        if (!empty($row['lho'])) {
            $lhoSql = "SELECT id FROM lhos WHERE lho_name = ? AND status = 'active'";
            $lhoResult = $db->getResults($lhoSql, [trim($row['lho'])], 's');
            if (empty($lhoResult)) {
                $errors[] = [
                    'field' => 'lho',
                    'message' => "LHO '{$row['lho']}' not found in master. Please add it first.",
                    'code' => 'MASTER_NOT_FOUND'
                ];
            }
        }
        
        // Validate Country
        if (!empty($row['country'])) {
            $countrySql = "SELECT id FROM countries WHERE name = ? AND status = 'active'";
            $countryResult = $db->getResults($countrySql, [trim($row['country'])], 's');
            if (empty($countryResult)) {
                $errors[] = [
                    'field' => 'country',
                    'message' => "Country '{$row['country']}' not found in master. Please add it first.",
                    'code' => 'MASTER_NOT_FOUND'
                ];
            } else {
                $countryId = $countryResult[0]['id'];
                
                // Validate State (must belong to the country)
                if (!empty($row['state'])) {
                    $stateSql = "SELECT id FROM states WHERE name = ? AND country_id = ? AND status = 'active'";
                    $stateResult = $db->getResults($stateSql, [trim($row['state']), $countryId], 'si');
                    if (empty($stateResult)) {
                        $errors[] = [
                            'field' => 'state',
                            'message' => "State '{$row['state']}' not found in country '{$row['country']}'. Please add it first.",
                            'code' => 'MASTER_NOT_FOUND'
                        ];
                    } else {
                        $stateId = $stateResult[0]['id'];
                        
                        // Validate City (must belong to the state)
                        if (!empty($row['city'])) {
                            $citySql = "SELECT id FROM cities WHERE name = ? AND state_id = ? AND status = 'active'";
                            $cityResult = $db->getResults($citySql, [trim($row['city']), $stateId], 'si');
                            if (empty($cityResult)) {
                                $errors[] = [
                                    'field' => 'city',
                                    'message' => "City '{$row['city']}' not found in state '{$row['state']}'. Please add it first.",
                                    'code' => 'MASTER_NOT_FOUND'
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        // Validate Zone (optional)
        if (!empty($row['zone'])) {
            $zoneSql = "SELECT id FROM zones WHERE name = ? AND status = 'active'";
            $zoneResult = $db->getResults($zoneSql, [trim($row['zone'])], 's');
            if (empty($zoneResult)) {
                $errors[] = [
                    'field' => 'zone',
                    'message' => "Zone '{$row['zone']}' not found in master. Please add it first.",
                    'code' => 'MASTER_NOT_FOUND'
                ];
            }
        }
        
        // Validate Bank (optional) - banks table uses status = 1 for active
        if (!empty($row['bank_name'])) {
            $bankSql = "SELECT id FROM banks WHERE name = ? AND status = 1";
            $bankResult = $db->getResults($bankSql, [trim($row['bank_name'])], 's');
            if (empty($bankResult)) {
                $errors[] = [
                    'field' => 'bank_name',
                    'message' => "Bank '{$row['bank_name']}' not found in master. Please add it first.",
                    'code' => 'MASTER_NOT_FOUND'
                ];
            }
        }
        
        // Validate Customer (optional)
        if (!empty($row['customer_name'])) {
            $customerSql = "SELECT id FROM customers WHERE name = ? AND status = 1";
            $customerResult = $db->getResults($customerSql, [trim($row['customer_name'])], 's');
            if (empty($customerResult)) {
                $errors[] = [
                    'field' => 'customer_name',
                    'message' => "Customer '{$row['customer_name']}' not found in master. Please add it first.",
                    'code' => 'MASTER_NOT_FOUND'
                ];
            }
        }
        
        return $errors;
    }
    
    /**
     * Generate Excel export of sites
     * 
     * @param int $companyId Company ID
     * @param array $filters Optional filters
     * @return string Path to generated file or empty string on failure
     */
    public function generateSiteExport(int $companyId, array $filters = []): string {
        // Get sites for export
        $sites = $this->exportSites($companyId, $filters);
        
        // Prepare data for export
        $exportData = array_map(function($site) {
            return [
                'id' => $site['id'],
                'site_name' => $site['site_name'],
                'lho' => $site['lho'],
                'bank_name' => $site['bank_name'] ?? '',
                'customer_name' => $site['customer_name'] ?? '',
                'city' => $site['city'],
                'state' => $site['state'],
                'country' => $site['country'],
                'zone' => $site['zone'] ?? '',
                'address' => $site['address'] ?? '',
                'latitude' => $site['latitude'] ?? '',
                'longitude' => $site['longitude'] ?? '',
                'status' => $site['status'],
                'created_at' => $site['created_at'],
                'created_by' => $site['created_by']
            ];
        }, $sites);
        
        // Get headers
        $headers = $this->bulkOperationService->getSiteExportHeaders();
        
        // Column mapping for export
        $columnMapping = [
            'id' => 'A',
            'site_name' => 'B',
            'lho' => 'C',
            'bank_name' => 'D',
            'customer_name' => 'E',
            'city' => 'F',
            'state' => 'G',
            'country' => 'H',
            'zone' => 'I',
            'address' => 'J',
            'latitude' => 'K',
            'longitude' => 'L',
            'status' => 'M',
            'created_at' => 'N',
            'created_by' => 'O'
        ];
        
        return $this->bulkOperationService->generateExcelExport($exportData, $headers, $columnMapping, 'sites_export');
    }
}

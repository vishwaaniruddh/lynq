<?php
/**
 * Inventory Audit Service
 * Provides comprehensive audit logging for all inventory operations
 * 
 * Requirements: 12.1, 12.2, 12.3
 * - 12.1: Log user, action type, timestamp, source location, and destination location for all inventory actions
 * - 12.2: Display complete movement history from entry to current state
 * - 12.3: Include all status changes, transfers, and user actions in audit reports
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/InventoryAuditLogRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';

class InventoryAuditService {
    private $db;
    private $auditLogRepository;
    
    // Action type constants (matching InventoryAuditLogRepository)
    const ACTION_STOCK_ENTRY = 'stock_entry';
    const ACTION_DISPATCH = 'dispatch';
    const ACTION_TRANSFER = 'transfer';
    const ACTION_STATUS_CHANGE = 'status_change';
    const ACTION_REPAIR = 'repair';
    const ACTION_RETURN = 'return';
    const ACTION_SCRAP = 'scrap';
    const ACTION_LOST = 'lost';
    const ACTION_ACKNOWLEDGE = 'acknowledge';
    const ACTION_EXPORT = 'export';
    
    // File Manager action type constants
    const ACTION_FILE_CREATE = 'file_create';
    const ACTION_FILE_READ = 'file_read';
    const ACTION_FILE_WRITE = 'file_write';
    const ACTION_FILE_DELETE = 'file_delete';
    const ACTION_FILE_RENAME = 'file_rename';
    const ACTION_FILE_UPLOAD = 'file_upload';
    const ACTION_FILE_DOWNLOAD = 'file_download';
    const ACTION_FILE_SEARCH = 'file_search';
    const ACTION_DIR_CREATE = 'directory_create';
    const ACTION_DIR_DELETE = 'directory_delete';
    const ACTION_DIR_LIST = 'directory_list';
    
    // Entity type constants
    const ENTITY_ASSET = 'asset';
    const ENTITY_STOCK = 'stock';
    const ENTITY_DISPATCH = 'dispatch';
    const ENTITY_TRANSFER = 'transfer';
    const ENTITY_REPAIR = 'repair';
    const ENTITY_INVENTORY = 'inventory';
    const ENTITY_FILE = 'file';
    
    // Location type constants
    const LOCATION_WAREHOUSE = 'warehouse';
    const LOCATION_COMPANY = 'company';
    const LOCATION_USER = 'user';
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->auditLogRepository = new InventoryAuditLogRepository();
    }
    
    /**
     * Log an inventory action
     * Requirement 12.1: Log user, action type, timestamp, source location, and destination location
     * 
     * @param string $actionType Type of action performed
     * @param string $entityType Type of entity affected
     * @param int $entityId ID of the entity
     * @param int $userId ID of the user performing the action
     * @param array $data Additional data including locations and values
     * @return array Result with success status and log entry
     */
    public function logAction(string $actionType, string $entityType, int $entityId, int $userId, array $data = []): array {
        // Validate action type
        if (!$this->isValidActionType($actionType)) {
            return [
                'success' => false,
                'message' => "Invalid action type: $actionType",
                'code' => 'INVALID_ACTION_TYPE'
            ];
        }
        
        // Validate entity type
        if (!$this->isValidEntityType($entityType)) {
            return [
                'success' => false,
                'message' => "Invalid entity type: $entityType",
                'code' => 'INVALID_ENTITY_TYPE'
            ];
        }
        
        // Validate user ID
        if ($userId <= 0) {
            return [
                'success' => false,
                'message' => 'User ID is required',
                'code' => 'USER_ID_REQUIRED'
            ];
        }
        
        try {
            // Prepare log data
            $logData = [
                'action_type' => $actionType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'user_id' => $userId
            ];
            
            // Add location data if provided
            if (isset($data['from_location_type'])) {
                $logData['from_location_type'] = $data['from_location_type'];
            }
            if (isset($data['from_location_id'])) {
                $logData['from_location_id'] = $data['from_location_id'];
            }
            if (isset($data['to_location_type'])) {
                $logData['to_location_type'] = $data['to_location_type'];
            }
            if (isset($data['to_location_id'])) {
                $logData['to_location_id'] = $data['to_location_id'];
            }
            
            // Add old/new values if provided
            if (isset($data['old_values'])) {
                $logData['old_values'] = is_array($data['old_values']) 
                    ? json_encode($data['old_values']) 
                    : $data['old_values'];
            }
            if (isset($data['new_values'])) {
                $logData['new_values'] = is_array($data['new_values']) 
                    ? json_encode($data['new_values']) 
                    : $data['new_values'];
            }
            
            // Add IP address and user agent
            $logData['ip_address'] = $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'CLI');
            if (isset($data['user_agent'])) {
                $logData['user_agent'] = $data['user_agent'];
            }
            if (isset($data['notes'])) {
                $logData['notes'] = $data['notes'];
            }
            
            // Create the log entry
            $logEntry = $this->auditLogRepository->create($logData);
            
            return [
                'success' => true,
                'message' => 'Action logged successfully',
                'data' => $logEntry
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to log action: ' . $e->getMessage(),
                'code' => 'LOG_ERROR'
            ];
        }
    }

    
    /**
     * Log stock entry action
     * 
     * @param int $entityId Stock or Asset ID
     * @param int $userId User ID
     * @param int $warehouseId Warehouse ID
     * @param array $details Additional details
     * @return array Result
     */
    public function logStockEntry(int $entityId, int $userId, int $warehouseId, array $details = []): array {
        return $this->logAction(
            self::ACTION_STOCK_ENTRY,
            isset($details['serial_number']) ? self::ENTITY_ASSET : self::ENTITY_STOCK,
            $entityId,
            $userId,
            [
                'to_location_type' => self::LOCATION_WAREHOUSE,
                'to_location_id' => $warehouseId,
                'new_values' => $details
            ]
        );
    }
    
    /**
     * Log dispatch action
     * 
     * @param int $dispatchId Dispatch ID
     * @param int $userId User ID
     * @param int $fromWarehouseId Source warehouse ID
     * @param string $toLocationType Destination location type
     * @param int $toLocationId Destination location ID
     * @param array $details Additional details
     * @return array Result
     */
    public function logDispatch(int $dispatchId, int $userId, int $fromWarehouseId, string $toLocationType, int $toLocationId, array $details = []): array {
        return $this->logAction(
            self::ACTION_DISPATCH,
            self::ENTITY_DISPATCH,
            $dispatchId,
            $userId,
            [
                'from_location_type' => self::LOCATION_WAREHOUSE,
                'from_location_id' => $fromWarehouseId,
                'to_location_type' => $toLocationType,
                'to_location_id' => $toLocationId,
                'new_values' => $details
            ]
        );
    }
    
    /**
     * Log transfer action
     * 
     * @param int $transferId Transfer ID
     * @param int $userId User ID
     * @param int $fromWarehouseId Source warehouse ID
     * @param int $toWarehouseId Destination warehouse ID
     * @param array $details Additional details
     * @return array Result
     */
    public function logTransfer(int $transferId, int $userId, int $fromWarehouseId, int $toWarehouseId, array $details = []): array {
        return $this->logAction(
            self::ACTION_TRANSFER,
            self::ENTITY_TRANSFER,
            $transferId,
            $userId,
            [
                'from_location_type' => self::LOCATION_WAREHOUSE,
                'from_location_id' => $fromWarehouseId,
                'to_location_type' => self::LOCATION_WAREHOUSE,
                'to_location_id' => $toWarehouseId,
                'new_values' => $details
            ]
        );
    }
    
    /**
     * Log status change action
     * 
     * @param int $assetId Asset ID
     * @param int $userId User ID
     * @param string $oldStatus Previous status
     * @param string $newStatus New status
     * @param array $details Additional details
     * @return array Result
     */
    public function logStatusChange(int $assetId, int $userId, string $oldStatus, string $newStatus, array $details = []): array {
        return $this->logAction(
            self::ACTION_STATUS_CHANGE,
            self::ENTITY_ASSET,
            $assetId,
            $userId,
            [
                'old_values' => ['status' => $oldStatus],
                'new_values' => array_merge(['status' => $newStatus], $details)
            ]
        );
    }
    
    /**
     * Log repair action
     * 
     * @param int $repairId Repair ID
     * @param int $userId User ID
     * @param array $details Additional details
     * @return array Result
     */
    public function logRepair(int $repairId, int $userId, array $details = []): array {
        return $this->logAction(
            self::ACTION_REPAIR,
            self::ENTITY_REPAIR,
            $repairId,
            $userId,
            [
                'new_values' => $details
            ]
        );
    }
    
    /**
     * Log return action
     * 
     * @param int $entityId Entity ID (asset or dispatch)
     * @param string $entityType Entity type
     * @param int $userId User ID
     * @param string $fromLocationType Source location type
     * @param int $fromLocationId Source location ID
     * @param int $toWarehouseId Destination warehouse ID
     * @param array $details Additional details
     * @return array Result
     */
    public function logReturn(int $entityId, string $entityType, int $userId, string $fromLocationType, int $fromLocationId, int $toWarehouseId, array $details = []): array {
        return $this->logAction(
            self::ACTION_RETURN,
            $entityType,
            $entityId,
            $userId,
            [
                'from_location_type' => $fromLocationType,
                'from_location_id' => $fromLocationId,
                'to_location_type' => self::LOCATION_WAREHOUSE,
                'to_location_id' => $toWarehouseId,
                'new_values' => $details
            ]
        );
    }
    
    /**
     * Log scrap action
     * 
     * @param int $assetId Asset ID
     * @param int $userId User ID
     * @param array $details Additional details
     * @return array Result
     */
    public function logScrap(int $assetId, int $userId, array $details = []): array {
        return $this->logAction(
            self::ACTION_SCRAP,
            self::ENTITY_ASSET,
            $assetId,
            $userId,
            [
                'new_values' => array_merge(['status' => 'scrapped'], $details)
            ]
        );
    }
    
    /**
     * Log lost item action
     * 
     * @param int $assetId Asset ID
     * @param int $userId User ID
     * @param array $details Additional details
     * @return array Result
     */
    public function logLost(int $assetId, int $userId, array $details = []): array {
        return $this->logAction(
            self::ACTION_LOST,
            self::ENTITY_ASSET,
            $assetId,
            $userId,
            [
                'new_values' => array_merge(['status' => 'lost'], $details)
            ]
        );
    }
    
    /**
     * Log acknowledgment action
     * 
     * @param int $dispatchId Dispatch ID
     * @param int $userId User ID
     * @param array $details Additional details
     * @return array Result
     */
    public function logAcknowledgment(int $dispatchId, int $userId, array $details = []): array {
        return $this->logAction(
            self::ACTION_ACKNOWLEDGE,
            self::ENTITY_DISPATCH,
            $dispatchId,
            $userId,
            [
                'new_values' => array_merge(['acknowledged' => true], $details)
            ]
        );
    }

    
    /**
     * Get complete movement history for an asset
     * Requirement 12.2: Display complete movement history from entry to current state
     * 
     * @param int $assetId Asset ID
     * @return array Movement history
     */
    public function getAssetHistory(int $assetId): array {
        return $this->auditLogRepository->getAssetHistory($assetId);
    }
    
    /**
     * Get audit logs for an entity
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return array Audit logs
     */
    public function getEntityHistory(string $entityType, int $entityId): array {
        return $this->auditLogRepository->findByEntity($entityType, $entityId);
    }
    
    /**
     * Get audit logs by user
     * 
     * @param int $userId User ID
     * @return array Audit logs
     */
    public function getUserActivity(int $userId): array {
        return $this->auditLogRepository->findByUser($userId);
    }
    
    /**
     * Get audit logs by action type
     * 
     * @param string $actionType Action type
     * @return array Audit logs
     */
    public function getActionHistory(string $actionType): array {
        return $this->auditLogRepository->findByActionType($actionType);
    }
    
    /**
     * Search audit logs with filters
     * 
     * @param array $filters Search filters
     * @return array Matching audit logs
     */
    public function searchLogs(array $filters = []): array {
        return $this->auditLogRepository->search($filters);
    }
    
    /**
     * Get recent activity
     * 
     * @param int $limit Maximum number of entries
     * @return array Recent audit logs
     */
    public function getRecentActivity(int $limit = 50): array {
        return $this->auditLogRepository->getRecentActivity($limit);
    }
    
    /**
     * Generate audit report
     * Requirement 12.3: Include all status changes, transfers, and user actions in audit reports
     * 
     * @param array $filters Report filters
     * @return array Report data
     */
    public function generateReport(array $filters = []): array {
        try {
            // Get summary by action type
            $summary = $this->auditLogRepository->generateReport($filters);
            
            // Get detailed logs
            $logs = $this->searchLogs($filters);
            
            return [
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'logs' => $logs,
                    'total_count' => count($logs),
                    'generated_at' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage(),
                'code' => 'REPORT_ERROR'
            ];
        }
    }
    
    /**
     * Get item traceability information
     * Requirement 12.4: Provide answers to current location, holder, source warehouse, working status
     * 
     * @param int $assetId Asset ID
     * @return array Traceability information
     */
    public function getItemTraceability(int $assetId): array {
        try {
            $assetRepository = new AssetRepository();
            $asset = $assetRepository->findWithDetails($assetId);
            
            if (!$asset) {
                return [
                    'success' => false,
                    'message' => 'Asset not found',
                    'code' => 'ASSET_NOT_FOUND'
                ];
            }
            
            // Get movement history
            $history = $this->getAssetHistory($assetId);
            
            return [
                'success' => true,
                'data' => [
                    'asset' => $asset,
                    'current_location' => [
                        'type' => $asset['current_holder_type'] ?? 'warehouse',
                        'id' => $asset['current_holder_id'] ?? $asset['warehouse_id'],
                        'name' => $asset['warehouse_name'] ?? null
                    ],
                    'current_holder' => [
                        'type' => $asset['current_holder_type'] ?? null,
                        'id' => $asset['current_holder_id'] ?? null
                    ],
                    'source_warehouse' => [
                        'id' => $asset['source_warehouse_id'] ?? null
                    ],
                    'working_status' => $asset['working_condition'] ?? 'unknown',
                    'status' => $asset['status'] ?? 'unknown',
                    'history' => $history
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get traceability: ' . $e->getMessage(),
                'code' => 'TRACEABILITY_ERROR'
            ];
        }
    }
    
    /**
     * Check if action type is valid
     * 
     * @param string $actionType Action type to validate
     * @return bool True if valid
     */
    private function isValidActionType(string $actionType): bool {
        $validTypes = [
            // Inventory action types
            self::ACTION_STOCK_ENTRY,
            self::ACTION_DISPATCH,
            self::ACTION_TRANSFER,
            self::ACTION_STATUS_CHANGE,
            self::ACTION_REPAIR,
            self::ACTION_RETURN,
            self::ACTION_SCRAP,
            self::ACTION_LOST,
            self::ACTION_ACKNOWLEDGE,
            self::ACTION_EXPORT,
            // File Manager action types
            self::ACTION_FILE_CREATE,
            self::ACTION_FILE_READ,
            self::ACTION_FILE_WRITE,
            self::ACTION_FILE_DELETE,
            self::ACTION_FILE_RENAME,
            self::ACTION_FILE_UPLOAD,
            self::ACTION_FILE_DOWNLOAD,
            self::ACTION_FILE_SEARCH,
            self::ACTION_DIR_CREATE,
            self::ACTION_DIR_DELETE,
            self::ACTION_DIR_LIST
        ];
        
        return in_array($actionType, $validTypes);
    }
    
    /**
     * Check if entity type is valid
     * 
     * @param string $entityType Entity type to validate
     * @return bool True if valid
     */
    private function isValidEntityType(string $entityType): bool {
        $validTypes = [
            self::ENTITY_ASSET,
            self::ENTITY_STOCK,
            self::ENTITY_DISPATCH,
            self::ENTITY_TRANSFER,
            self::ENTITY_REPAIR,
            self::ENTITY_INVENTORY,
            self::ENTITY_FILE
        ];
        
        return in_array($entityType, $validTypes);
    }
    
    /**
     * Get all valid action types
     * 
     * @return array Valid action types
     */
    public static function getActionTypes(): array {
        return [
            // Inventory action types
            self::ACTION_STOCK_ENTRY,
            self::ACTION_DISPATCH,
            self::ACTION_TRANSFER,
            self::ACTION_STATUS_CHANGE,
            self::ACTION_REPAIR,
            self::ACTION_RETURN,
            self::ACTION_SCRAP,
            self::ACTION_LOST,
            self::ACTION_ACKNOWLEDGE,
            self::ACTION_EXPORT,
            // File Manager action types
            self::ACTION_FILE_CREATE,
            self::ACTION_FILE_READ,
            self::ACTION_FILE_WRITE,
            self::ACTION_FILE_DELETE,
            self::ACTION_FILE_RENAME,
            self::ACTION_FILE_UPLOAD,
            self::ACTION_FILE_DOWNLOAD,
            self::ACTION_FILE_SEARCH,
            self::ACTION_DIR_CREATE,
            self::ACTION_DIR_DELETE,
            self::ACTION_DIR_LIST
        ];
    }
    
    /**
     * Get all valid entity types
     * 
     * @return array Valid entity types
     */
    public static function getEntityTypes(): array {
        return [
            self::ENTITY_ASSET,
            self::ENTITY_STOCK,
            self::ENTITY_DISPATCH,
            self::ENTITY_TRANSFER,
            self::ENTITY_REPAIR,
            self::ENTITY_INVENTORY,
            self::ENTITY_FILE
        ];
    }
    
    /**
     * Log a file operation
     * Convenience method for file manager operations
     * 
     * @param string $actionType File action type (file_create, file_read, etc.)
     * @param string $filePath File path
     * @param int $userId User ID
     * @param array $details Additional details
     * @return array Result
     */
    public function logFileOperation(string $actionType, string $filePath, int $userId, array $details = []): array {
        return $this->logAction(
            $actionType,
            self::ENTITY_FILE,
            0, // Entity ID not applicable for files
            $userId,
            array_merge([
                'notes' => "File operation: $actionType on $filePath"
            ], $details, [
                'new_values' => array_merge(['path' => $filePath], $details)
            ])
        );
    }
    
    /**
     * Get file operation history
     * 
     * @param int $userId Optional user ID to filter by
     * @param int $limit Maximum number of entries
     * @return array File operation logs
     */
    public function getFileOperationHistory(?int $userId = null, int $limit = 100): array {
        $filters = [
            'entity_type' => self::ENTITY_FILE
        ];
        
        if ($userId !== null) {
            $filters['user_id'] = $userId;
        }
        
        return $this->searchLogs($filters);
    }
}

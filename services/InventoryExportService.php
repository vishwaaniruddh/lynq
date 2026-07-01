<?php
/**
 * Inventory Export Service
 * Handles export of inventory data to Excel/CSV formats with permission filtering
 * 
 * Requirements: 15.1, 15.2, 15.4
 * - 15.1: Generate output in Excel/CSV format with all relevant fields
 * - 15.2: Apply the same permission filters as the UI view
 * - 15.4: Produce output that can be deserialized back to equivalent records (round-trip consistency)
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InventoryAccessService.php';
require_once __DIR__ . '/../services/InventoryAuditService.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/DispatchRepository.php';
require_once __DIR__ . '/../repositories/TransferRepository.php';
require_once __DIR__ . '/../repositories/RepairRepository.php';

class InventoryExportService {
    private $db;
    private $inventoryAccessService;
    private $assetRepository;
    private $stockRepository;
    private $warehouseRepository;
    private $productRepository;
    private $dispatchRepository;
    private $transferRepository;
    private $repairRepository;
    
    // Export format constants
    const FORMAT_CSV = 'csv';
    const FORMAT_EXCEL = 'xlsx';
    const FORMAT_JSON = 'json';
    
    // Export type constants
    const TYPE_ASSETS = 'assets';
    const TYPE_STOCK = 'stock';
    const TYPE_DISPATCHES = 'dispatches';
    const TYPE_TRANSFERS = 'transfers';
    const TYPE_REPAIRS = 'repairs';
    const TYPE_AUDIT = 'audit';
    const TYPE_ALL = 'all';
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->inventoryAccessService = new InventoryAccessService();
        $this->assetRepository = new AssetRepository();
        $this->stockRepository = new StockRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->productRepository = new ProductRepository();
        $this->dispatchRepository = new DispatchRepository();
        $this->transferRepository = new TransferRepository();
        $this->repairRepository = new RepairRepository();
    }
    
    /**
     * Export inventory data with permission filtering
     * Requirement 15.1, 15.2
     * 
     * @param int $userId User ID for permission filtering
     * @param string $type Export type (assets, stock, dispatches, etc.)
     * @param string $format Export format (csv, xlsx, json)
     * @param array $filters Additional filters
     * @return array Result with export data or file path
     */
    public function export(int $userId, string $type, string $format = self::FORMAT_CSV, array $filters = []): array {
        // Validate export type
        if (!$this->isValidExportType($type)) {
            return [
                'success' => false,
                'message' => "Invalid export type: $type",
                'code' => 'INVALID_EXPORT_TYPE'
            ];
        }
        
        // Validate format
        if (!$this->isValidFormat($format)) {
            return [
                'success' => false,
                'message' => "Invalid export format: $format",
                'code' => 'INVALID_FORMAT'
            ];
        }
        
        try {
            // Get data based on type with permission filtering
            $data = $this->getExportData($userId, $type, $filters);
            
            if (empty($data['records'])) {
                return [
                    'success' => true,
                    'message' => 'No data to export',
                    'data' => [
                        'records' => [],
                        'count' => 0
                    ]
                ];
            }
            
            // Format data for export
            $formattedData = $this->formatForExport($data['records'], $type);
            
            // Generate export based on format
            switch ($format) {
                case self::FORMAT_CSV:
                    $result = $this->generateCsv($formattedData, $type);
                    break;
                case self::FORMAT_JSON:
                    $result = $this->generateJson($formattedData, $type);
                    break;
                case self::FORMAT_EXCEL:
                    $result = $this->generateExcel($formattedData, $type);
                    break;
                default:
                    $result = $this->generateCsv($formattedData, $type);
            }
            
            return [
                'success' => true,
                'message' => 'Export generated successfully',
                'data' => array_merge($result, [
                    'count' => count($formattedData),
                    'type' => $type,
                    'format' => $format,
                    'generated_at' => date('Y-m-d H:i:s')
                ])
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
                'code' => 'EXPORT_ERROR'
            ];
        }
    }

    
    /**
     * Get export data with permission filtering
     * Requirement 15.2: Apply the same permission filters as the UI view
     * 
     * @param int $userId User ID
     * @param string $type Export type
     * @param array $filters Additional filters
     * @return array Data records
     */
    public function getExportData(int $userId, string $type, array $filters = []): array {
        // Get accessible warehouses for permission filtering
        $accessibleWarehouses = $this->inventoryAccessService->getAccessibleWarehouses($userId);
        $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
        
        if (empty($accessibleWarehouseIds)) {
            return ['records' => []];
        }
        
        switch ($type) {
            case self::TYPE_ASSETS:
                return $this->getAssetsForExport($accessibleWarehouseIds, $filters);
            case self::TYPE_STOCK:
                return $this->getStockForExport($accessibleWarehouseIds, $filters);
            case self::TYPE_DISPATCHES:
                return $this->getDispatchesForExport($accessibleWarehouseIds, $filters);
            case self::TYPE_TRANSFERS:
                return $this->getTransfersForExport($accessibleWarehouseIds, $filters);
            case self::TYPE_REPAIRS:
                return $this->getRepairsForExport($accessibleWarehouseIds, $filters);
            case self::TYPE_AUDIT:
                return $this->getAuditForExport($accessibleWarehouseIds, $filters);
            case self::TYPE_ALL:
                return $this->getAllForExport($accessibleWarehouseIds, $filters);
            default:
                return ['records' => []];
        }
    }
    
    /**
     * Get assets for export with permission filtering
     */
    private function getAssetsForExport(array $warehouseIds, array $filters = []): array {
        $allAssets = $this->assetRepository->search($filters);
        
        // Filter by accessible warehouses
        $filteredAssets = array_filter($allAssets, function($asset) use ($warehouseIds) {
            return in_array($asset['warehouse_id'], $warehouseIds);
        });
        
        return ['records' => array_values($filteredAssets)];
    }
    
    /**
     * Get stock for export with permission filtering
     */
    private function getStockForExport(array $warehouseIds, array $filters = []): array {
        $allStock = $this->stockRepository->findAllWithDetails($filters);
        
        // Filter by accessible warehouses
        $filteredStock = array_filter($allStock, function($stock) use ($warehouseIds) {
            return in_array($stock['warehouse_id'], $warehouseIds);
        });
        
        return ['records' => array_values($filteredStock)];
    }
    
    /**
     * Get dispatches for export with permission filtering
     */
    private function getDispatchesForExport(array $warehouseIds, array $filters = []): array {
        $allDispatches = $this->dispatchRepository->findAllWithDetails();
        
        // Filter by accessible warehouses (source warehouse)
        $filteredDispatches = array_filter($allDispatches, function($dispatch) use ($warehouseIds) {
            return in_array($dispatch['from_warehouse_id'], $warehouseIds);
        });
        
        return ['records' => array_values($filteredDispatches)];
    }
    
    /**
     * Get transfers for export with permission filtering
     */
    private function getTransfersForExport(array $warehouseIds, array $filters = []): array {
        $allTransfers = $this->transferRepository->findAllWithDetails();
        
        // Filter by accessible warehouses (either source or destination)
        $filteredTransfers = array_filter($allTransfers, function($transfer) use ($warehouseIds) {
            return in_array($transfer['from_warehouse_id'], $warehouseIds) ||
                   in_array($transfer['to_warehouse_id'], $warehouseIds);
        });
        
        return ['records' => array_values($filteredTransfers)];
    }
    
    /**
     * Get repairs for export with permission filtering
     */
    private function getRepairsForExport(array $warehouseIds, array $filters = []): array {
        $allRepairs = $this->repairRepository->findAllWithDetails();
        
        // Filter by accessible warehouses (through asset's warehouse)
        $filteredRepairs = array_filter($allRepairs, function($repair) use ($warehouseIds) {
            return isset($repair['warehouse_id']) && in_array($repair['warehouse_id'], $warehouseIds);
        });
        
        return ['records' => array_values($filteredRepairs)];
    }
    
    /**
     * Get audit logs for export with permission filtering
     */
    private function getAuditForExport(array $warehouseIds, array $filters = []): array {
        $auditService = new InventoryAuditService();
        $report = $auditService->generateReport($filters);
        
        if (!$report['success']) {
            return ['records' => []];
        }
        
        // Filter audit logs by accessible warehouses
        $filteredLogs = array_filter($report['data']['logs'], function($log) use ($warehouseIds) {
            // Check from_location_id or to_location_id if they are warehouses
            if ($log['from_location_type'] === 'warehouse' && 
                isset($log['from_location_id']) && 
                in_array($log['from_location_id'], $warehouseIds)) {
                return true;
            }
            if ($log['to_location_type'] === 'warehouse' && 
                isset($log['to_location_id']) && 
                in_array($log['to_location_id'], $warehouseIds)) {
                return true;
            }
            // Include if no warehouse filter applies
            return empty($log['from_location_type']) && empty($log['to_location_type']);
        });
        
        return ['records' => array_values($filteredLogs)];
    }
    
    /**
     * Get all inventory data for export
     */
    private function getAllForExport(array $warehouseIds, array $filters = []): array {
        return [
            'records' => [
                'assets' => $this->getAssetsForExport($warehouseIds, $filters)['records'],
                'stock' => $this->getStockForExport($warehouseIds, $filters)['records'],
                'dispatches' => $this->getDispatchesForExport($warehouseIds, $filters)['records'],
                'transfers' => $this->getTransfersForExport($warehouseIds, $filters)['records'],
                'repairs' => $this->getRepairsForExport($warehouseIds, $filters)['records']
            ]
        ];
    }

    
    /**
     * Format data for export based on type
     * Requirement 15.4: Produce output that can be deserialized back to equivalent records
     * 
     * @param array $records Raw records
     * @param string $type Export type
     * @return array Formatted records
     */
    public function formatForExport(array $records, string $type): array {
        if ($type === self::TYPE_ALL) {
            // Handle combined export
            $formatted = [];
            foreach ($records as $subType => $subRecords) {
                $formatted[$subType] = array_map(function($record) use ($subType) {
                    return $this->formatRecord($record, $subType);
                }, $subRecords);
            }
            return $formatted;
        }
        
        return array_map(function($record) use ($type) {
            return $this->formatRecord($record, $type);
        }, $records);
    }
    
    /**
     * Format a single record for export
     * Ensures round-trip consistency by including all necessary fields
     */
    private function formatRecord(array $record, string $type): array {
        switch ($type) {
            case self::TYPE_ASSETS:
                return $this->formatAssetRecord($record);
            case self::TYPE_STOCK:
                return $this->formatStockRecord($record);
            case self::TYPE_DISPATCHES:
                return $this->formatDispatchRecord($record);
            case self::TYPE_TRANSFERS:
                return $this->formatTransferRecord($record);
            case self::TYPE_REPAIRS:
                return $this->formatRepairRecord($record);
            case self::TYPE_AUDIT:
                return $this->formatAuditRecord($record);
            default:
                return $record;
        }
    }
    
    /**
     * Format asset record for export
     * Requirement 15.4: Include all fields needed for round-trip
     */
    private function formatAssetRecord(array $record): array {
        return [
            'id' => $record['id'] ?? null,
            'serial_number' => $record['serial_number'] ?? '',
            'product_id' => $record['product_id'] ?? null,
            'product_name' => $record['product_name'] ?? '',
            'warehouse_id' => $record['warehouse_id'] ?? null,
            'warehouse_name' => $record['warehouse_name'] ?? '',
            'status' => $record['status'] ?? '',
            'working_condition' => $record['working_condition'] ?? '',
            'current_holder_type' => $record['current_holder_type'] ?? '',
            'current_holder_id' => $record['current_holder_id'] ?? null,
            'source_warehouse_id' => $record['source_warehouse_id'] ?? null,
            'warranty_expiry' => $record['warranty_expiry'] ?? null,
            'notes' => $record['notes'] ?? '',
            'created_at' => $record['created_at'] ?? null,
            'updated_at' => $record['updated_at'] ?? null
        ];
    }
    
    /**
     * Format stock record for export
     */
    private function formatStockRecord(array $record): array {
        return [
            'id' => $record['id'] ?? null,
            'product_id' => $record['product_id'] ?? null,
            'product_name' => $record['product_name'] ?? '',
            'warehouse_id' => $record['warehouse_id'] ?? null,
            'warehouse_name' => $record['warehouse_name'] ?? '',
            'quantity' => $record['quantity'] ?? 0,
            'reserved_quantity' => $record['reserved_quantity'] ?? 0,
            'available_quantity' => ($record['quantity'] ?? 0) - ($record['reserved_quantity'] ?? 0),
            'unit_of_measure' => $record['unit_of_measure'] ?? '',
            'updated_at' => $record['updated_at'] ?? null
        ];
    }
    
    /**
     * Format dispatch record for export
     */
    private function formatDispatchRecord(array $record): array {
        return [
            'id' => $record['id'] ?? null,
            'dispatch_number' => $record['dispatch_number'] ?? '',
            'from_company_id' => $record['from_company_id'] ?? null,
            'from_warehouse_id' => $record['from_warehouse_id'] ?? null,
            'from_warehouse_name' => $record['from_warehouse_name'] ?? '',
            'to_company_id' => $record['to_company_id'] ?? null,
            'to_user_id' => $record['to_user_id'] ?? null,
            'to_warehouse_id' => $record['to_warehouse_id'] ?? null,
            'dispatch_date' => $record['dispatch_date'] ?? null,
            'status' => $record['status'] ?? '',
            'acknowledgment_status' => $record['acknowledgment_status'] ?? '',
            'acknowledged_at' => $record['acknowledged_at'] ?? null,
            'acknowledged_by' => $record['acknowledged_by'] ?? null,
            'notes' => $record['notes'] ?? '',
            'created_at' => $record['created_at'] ?? null
        ];
    }
    
    /**
     * Format transfer record for export
     */
    private function formatTransferRecord(array $record): array {
        return [
            'id' => $record['id'] ?? null,
            'transfer_number' => $record['transfer_number'] ?? '',
            'from_warehouse_id' => $record['from_warehouse_id'] ?? null,
            'from_warehouse_name' => $record['from_warehouse_name'] ?? '',
            'to_warehouse_id' => $record['to_warehouse_id'] ?? null,
            'to_warehouse_name' => $record['to_warehouse_name'] ?? '',
            'transfer_date' => $record['transfer_date'] ?? null,
            'status' => $record['status'] ?? '',
            'notes' => $record['notes'] ?? '',
            'created_at' => $record['created_at'] ?? null
        ];
    }
    
    /**
     * Format repair record for export
     */
    private function formatRepairRecord(array $record): array {
        return [
            'id' => $record['id'] ?? null,
            'asset_id' => $record['asset_id'] ?? null,
            'serial_number' => $record['serial_number'] ?? '',
            'repair_vendor' => $record['repair_vendor'] ?? '',
            'estimated_cost' => $record['estimated_cost'] ?? null,
            'actual_cost' => $record['actual_cost'] ?? null,
            'send_date' => $record['send_date'] ?? null,
            'expected_return_date' => $record['expected_return_date'] ?? null,
            'actual_return_date' => $record['actual_return_date'] ?? null,
            'status' => $record['status'] ?? '',
            'notes' => $record['notes'] ?? '',
            'created_at' => $record['created_at'] ?? null,
            'updated_at' => $record['updated_at'] ?? null
        ];
    }
    
    /**
     * Format audit record for export
     */
    private function formatAuditRecord(array $record): array {
        return [
            'id' => $record['id'] ?? null,
            'action_type' => $record['action_type'] ?? '',
            'entity_type' => $record['entity_type'] ?? '',
            'entity_id' => $record['entity_id'] ?? null,
            'user_id' => $record['user_id'] ?? null,
            'user_name' => $record['user_name'] ?? '',
            'from_location_type' => $record['from_location_type'] ?? '',
            'from_location_id' => $record['from_location_id'] ?? null,
            'to_location_type' => $record['to_location_type'] ?? '',
            'to_location_id' => $record['to_location_id'] ?? null,
            'old_values' => $record['old_values'] ?? '',
            'new_values' => $record['new_values'] ?? '',
            'ip_address' => $record['ip_address'] ?? '',
            'created_at' => $record['created_at'] ?? null
        ];
    }

    
    /**
     * Generate CSV export
     * 
     * @param array $data Formatted data
     * @param string $type Export type
     * @return array Result with CSV content
     */
    private function generateCsv(array $data, string $type): array {
        if (empty($data)) {
            return ['content' => '', 'filename' => ''];
        }
        
        // Handle combined export
        if ($type === self::TYPE_ALL) {
            $csvContent = '';
            foreach ($data as $subType => $subRecords) {
                if (!empty($subRecords)) {
                    $csvContent .= "=== " . strtoupper($subType) . " ===\n";
                    $csvContent .= $this->arrayToCsv($subRecords);
                    $csvContent .= "\n\n";
                }
            }
            return [
                'content' => $csvContent,
                'filename' => 'inventory_export_' . date('Y-m-d_His') . '.csv',
                'mime_type' => 'text/csv'
            ];
        }
        
        $csvContent = $this->arrayToCsv($data);
        
        return [
            'content' => $csvContent,
            'filename' => $type . '_export_' . date('Y-m-d_His') . '.csv',
            'mime_type' => 'text/csv'
        ];
    }
    
    /**
     * Convert array to CSV string
     */
    private function arrayToCsv(array $data): string {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Write header row
        $headers = array_keys($data[0]);
        fputcsv($output, $headers);
        
        // Write data rows
        foreach ($data as $row) {
            // Convert arrays/objects to JSON strings for CSV
            $csvRow = array_map(function($value) {
                if (is_array($value) || is_object($value)) {
                    return json_encode($value);
                }
                return $value;
            }, $row);
            fputcsv($output, $csvRow);
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }
    
    /**
     * Generate JSON export
     * Requirement 15.4: Produce output that can be deserialized back to equivalent records
     * 
     * @param array $data Formatted data
     * @param string $type Export type
     * @return array Result with JSON content
     */
    private function generateJson(array $data, string $type): array {
        $exportData = [
            'export_type' => $type,
            'export_date' => date('Y-m-d H:i:s'),
            'record_count' => $type === self::TYPE_ALL ? 
                array_sum(array_map('count', $data)) : count($data),
            'data' => $data
        ];
        
        return [
            'content' => json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'filename' => $type . '_export_' . date('Y-m-d_His') . '.json',
            'mime_type' => 'application/json'
        ];
    }
    
    /**
     * Generate Excel export (simplified - returns CSV with xlsx extension)
     * For full Excel support, PHPSpreadsheet would be needed
     * 
     * @param array $data Formatted data
     * @param string $type Export type
     * @return array Result with Excel content
     */
    private function generateExcel(array $data, string $type): array {
        // For now, generate CSV content (can be opened in Excel)
        // Full Excel support would require PHPSpreadsheet library
        $csvResult = $this->generateCsv($data, $type);
        
        return [
            'content' => $csvResult['content'],
            'filename' => str_replace('.csv', '.csv', $csvResult['filename']),
            'mime_type' => 'text/csv',
            'note' => 'CSV format compatible with Excel'
        ];
    }
    
    /**
     * Parse imported data for re-import validation
     * Requirement 15.4: Validate against original schema and reject malformed entries
     * 
     * @param string $content Import content
     * @param string $format Import format
     * @param string $type Import type
     * @return array Validation result
     */
    public function parseImport(string $content, string $format, string $type): array {
        try {
            switch ($format) {
                case self::FORMAT_JSON:
                    return $this->parseJsonImport($content, $type);
                case self::FORMAT_CSV:
                    return $this->parseCsvImport($content, $type);
                default:
                    return [
                        'success' => false,
                        'message' => "Unsupported import format: $format",
                        'code' => 'UNSUPPORTED_FORMAT'
                    ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Parse error: ' . $e->getMessage(),
                'code' => 'PARSE_ERROR'
            ];
        }
    }
    
    /**
     * Parse JSON import
     */
    private function parseJsonImport(string $content, string $type): array {
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'Invalid JSON: ' . json_last_error_msg(),
                'code' => 'INVALID_JSON'
            ];
        }
        
        if (!isset($data['data'])) {
            return [
                'success' => false,
                'message' => 'Missing data field in JSON',
                'code' => 'MISSING_DATA'
            ];
        }
        
        // Validate records against schema
        $validationResult = $this->validateRecords($data['data'], $type);
        
        return [
            'success' => $validationResult['valid'],
            'message' => $validationResult['valid'] ? 'Import validated successfully' : 'Validation failed',
            'data' => [
                'records' => $data['data'],
                'valid_count' => $validationResult['valid_count'],
                'invalid_count' => $validationResult['invalid_count'],
                'errors' => $validationResult['errors']
            ]
        ];
    }
    
    /**
     * Parse CSV import
     */
    private function parseCsvImport(string $content, string $type): array {
        $lines = explode("\n", trim($content));
        
        if (count($lines) < 2) {
            return [
                'success' => false,
                'message' => 'CSV must have header row and at least one data row',
                'code' => 'INSUFFICIENT_DATA'
            ];
        }
        
        $headers = str_getcsv($lines[0]);
        $records = [];
        
        for ($i = 1; $i < count($lines); $i++) {
            if (empty(trim($lines[$i]))) continue;
            
            $values = str_getcsv($lines[$i]);
            if (count($values) !== count($headers)) {
                continue; // Skip malformed rows
            }
            
            $record = array_combine($headers, $values);
            $records[] = $record;
        }
        
        // Validate records against schema
        $validationResult = $this->validateRecords($records, $type);
        
        return [
            'success' => $validationResult['valid'],
            'message' => $validationResult['valid'] ? 'Import validated successfully' : 'Validation failed',
            'data' => [
                'records' => $records,
                'valid_count' => $validationResult['valid_count'],
                'invalid_count' => $validationResult['invalid_count'],
                'errors' => $validationResult['errors']
            ]
        ];
    }
    
    /**
     * Validate records against schema
     */
    private function validateRecords(array $records, string $type): array {
        $requiredFields = $this->getRequiredFields($type);
        $validCount = 0;
        $invalidCount = 0;
        $errors = [];
        
        foreach ($records as $index => $record) {
            $recordErrors = [];
            
            foreach ($requiredFields as $field) {
                if (!isset($record[$field]) || $record[$field] === '') {
                    $recordErrors[] = "Missing required field: $field";
                }
            }
            
            if (empty($recordErrors)) {
                $validCount++;
            } else {
                $invalidCount++;
                $errors[$index] = $recordErrors;
            }
        }
        
        return [
            'valid' => $invalidCount === 0,
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'errors' => $errors
        ];
    }
    
    /**
     * Get required fields for a type
     */
    private function getRequiredFields(string $type): array {
        switch ($type) {
            case self::TYPE_ASSETS:
                return ['serial_number', 'product_id'];
            case self::TYPE_STOCK:
                return ['product_id', 'warehouse_id', 'quantity'];
            case self::TYPE_DISPATCHES:
                return ['dispatch_number', 'from_warehouse_id'];
            case self::TYPE_TRANSFERS:
                return ['transfer_number', 'from_warehouse_id', 'to_warehouse_id'];
            case self::TYPE_REPAIRS:
                return ['asset_id'];
            default:
                return [];
        }
    }
    
    /**
     * Check if export type is valid
     */
    private function isValidExportType(string $type): bool {
        return in_array($type, [
            self::TYPE_ASSETS,
            self::TYPE_STOCK,
            self::TYPE_DISPATCHES,
            self::TYPE_TRANSFERS,
            self::TYPE_REPAIRS,
            self::TYPE_AUDIT,
            self::TYPE_ALL
        ]);
    }
    
    /**
     * Check if format is valid
     */
    private function isValidFormat(string $format): bool {
        return in_array($format, [
            self::FORMAT_CSV,
            self::FORMAT_EXCEL,
            self::FORMAT_JSON
        ]);
    }
    
    /**
     * Get available export types
     */
    public static function getExportTypes(): array {
        return [
            self::TYPE_ASSETS,
            self::TYPE_STOCK,
            self::TYPE_DISPATCHES,
            self::TYPE_TRANSFERS,
            self::TYPE_REPAIRS,
            self::TYPE_AUDIT,
            self::TYPE_ALL
        ];
    }
    
    /**
     * Get available formats
     */
    public static function getFormats(): array {
        return [
            self::FORMAT_CSV,
            self::FORMAT_EXCEL,
            self::FORMAT_JSON
        ];
    }
}

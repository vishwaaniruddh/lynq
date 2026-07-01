<?php
/**
 * Bulk Inventory Service
 * Handles bulk operations for inventory management
 * 
 * Requirements: 4.1, 4.2, 4.3, 4.4
 * - 4.1: Validate all rows before committing any changes
 * - 4.2: Generate error report listing failed rows with reasons while allowing partial success
 * - 4.3: Process multiple items (mixed serializable and non-serializable) in a single atomic transaction
 * - 4.4: Rollback all changes and report the failure reason on bulk operation failure
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/DispatchRepository.php';
require_once __DIR__ . '/../repositories/DispatchItemRepository.php';
require_once __DIR__ . '/../repositories/InventoryAuditLogRepository.php';
require_once __DIR__ . '/StockService.php';
require_once __DIR__ . '/DispatchService.php';
require_once __DIR__ . '/BulkOperationService.php';

/**
 * Result class for bulk inventory operations
 */
class BulkInventoryResult {
    public int $totalRows = 0;
    public int $successCount = 0;
    public int $errorCount = 0;
    public array $errors = [];           // [rowNumber => ['message' => string, 'field' => string|null]]
    public array $createdIds = [];
    public array $rowResults = [];       // [rowNumber => ['status' => 'success'|'error', 'message' => string, 'id' => int|null]]
    public bool $success = false;
    public string $message = '';
    public bool $isAtomic = false;       // Whether operation was atomic (all-or-nothing)
    public bool $wasRolledBack = false;  // Whether a rollback occurred
    
    public function toArray(): array {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'totalRows' => $this->totalRows,
            'successCount' => $this->successCount,
            'errorCount' => $this->errorCount,
            'errors' => $this->errors,
            'createdIds' => $this->createdIds,
            'rowResults' => $this->rowResults,
            'isAtomic' => $this->isAtomic,
            'wasRolledBack' => $this->wasRolledBack
        ];
    }
    
    public function addSuccess(int $rowNumber, int $id, string $message = 'Success'): void {
        $this->successCount++;
        $this->createdIds[] = $id;
        $this->rowResults[$rowNumber] = [
            'status' => 'success',
            'message' => $message,
            'id' => $id
        ];
    }
    
    public function addError(int $rowNumber, string $message, ?string $field = null): void {
        $this->errorCount++;
        $this->errors[$rowNumber] = [
            'message' => $message,
            'field' => $field
        ];
        $this->rowResults[$rowNumber] = [
            'status' => 'error',
            'message' => $message,
            'id' => null
        ];
    }
}

class BulkInventoryService {
    private $db;
    private $conn;
    private $productRepository;
    private $warehouseRepository;
    private $stockRepository;
    private $assetRepository;
    private $dispatchRepository;
    private $dispatchItemRepository;
    private $auditLogRepository;
    private $stockService;
    private $bulkOperationService;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->conn = $this->db->getConnection();
        $this->productRepository = new ProductRepository();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->stockRepository = new StockRepository();
        $this->assetRepository = new AssetRepository();
        $this->dispatchRepository = new DispatchRepository();
        $this->dispatchRepository->disableCompanyFilter();
        $this->dispatchItemRepository = new DispatchItemRepository();
        $this->auditLogRepository = new InventoryAuditLogRepository();
        $this->stockService = new StockService();
        $this->bulkOperationService = new BulkOperationService();
    }
    
    /**
     * Validate bulk upload data for stock entry
     * Requirement 4.1: Validate all rows before committing any changes
     * 
     * @param array $rows Array of row data with keys: warehouse_id, product_id, serial_number, quantity, is_repairable, notes
     * @return array Validation result with 'success', 'validRows', 'invalidRows', 'errors'
     */
    public function validateBulkUpload(array $rows): array {
        $validRows = [];
        $invalidRows = [];
        $errors = [];
        $seenSerialNumbers = []; // Track serial numbers within this batch
        
        foreach ($rows as $index => $row) {
            $rowNumber = $row['_row_number'] ?? ($index + 2);
            $rowErrors = [];
            
            // Validate warehouse_id first (required)
            if (empty($row['warehouse_id'])) {
                $rowErrors[] = ['field' => 'warehouse_id', 'message' => 'Warehouse ID is required'];
            } else {
                $warehouse = $this->warehouseRepository->find($row['warehouse_id']);
                if (!$warehouse) {
                    $rowErrors[] = ['field' => 'warehouse_id', 'message' => "Warehouse ID {$row['warehouse_id']} not found"];
                } elseif ($warehouse['status'] !== WarehouseRepository::STATUS_ACTIVE) {
                    $rowErrors[] = ['field' => 'warehouse_id', 'message' => "Warehouse '{$warehouse['name']}' is inactive"];
                } else {
                    $row['_warehouse'] = $warehouse; // Cache for later use
                }
            }
            
            // Validate product_id
            if (empty($row['product_id'])) {
                $rowErrors[] = ['field' => 'product_id', 'message' => 'Product ID is required'];
            } else {
                $product = $this->productRepository->find($row['product_id']);
                if (!$product) {
                    $rowErrors[] = ['field' => 'product_id', 'message' => "Product ID {$row['product_id']} not found"];
                } else {
                    $row['_product'] = $product; // Cache for later use
                    
                    // Validate serial number for serializable products
                    if ($product['is_serializable']) {
                        if (empty($row['serial_number'])) {
                            $rowErrors[] = ['field' => 'serial_number', 'message' => 'Serial number is required for serializable products'];
                        } else {
                            $serialNumber = trim($row['serial_number']);
                            
                            // Check for duplicate in database
                            if ($this->assetRepository->serialNumberExists($serialNumber)) {
                                $rowErrors[] = ['field' => 'serial_number', 'message' => "Serial number '$serialNumber' already exists in database"];
                            }
                            
                            // Check for duplicate within batch
                            if (isset($seenSerialNumbers[$serialNumber])) {
                                $rowErrors[] = ['field' => 'serial_number', 'message' => "Serial number '$serialNumber' is duplicated in row {$seenSerialNumbers[$serialNumber]}"];
                            } else {
                                $seenSerialNumbers[$serialNumber] = $rowNumber;
                            }
                        }
                    } else {
                        // Validate quantity for non-serializable products
                        if (!isset($row['quantity']) || $row['quantity'] === '' || $row['quantity'] === null) {
                            $rowErrors[] = ['field' => 'quantity', 'message' => 'Quantity is required for non-serializable products'];
                        } elseif (!is_numeric($row['quantity']) || intval($row['quantity']) <= 0) {
                            $rowErrors[] = ['field' => 'quantity', 'message' => 'Quantity must be a positive integer'];
                        }
                    }
                }
            }
            
            // Validate is_repairable (optional, must be 0 or 1 if provided)
            if (isset($row['is_repairable']) && $row['is_repairable'] !== '' && $row['is_repairable'] !== null) {
                if (!in_array($row['is_repairable'], ['0', '1', 0, 1], true)) {
                    $rowErrors[] = ['field' => 'is_repairable', 'message' => 'is_repairable must be 0 or 1'];
                }
            }
            
            // Notes is optional, no validation needed
            
            if (empty($rowErrors)) {
                $validRows[] = $row;
            } else {
                $invalidRows[] = $row;
                $errors[$rowNumber] = $rowErrors;
            }
        }
        
        return [
            'success' => empty($errors),
            'validRows' => $validRows,
            'invalidRows' => $invalidRows,
            'validCount' => count($validRows),
            'invalidCount' => count($invalidRows),
            'errors' => $errors
        ];
    }

    
    /**
     * Process bulk stock entry with partial success handling
     * Requirement 4.2: Generate error report listing failed rows with reasons while allowing partial success
     * 
     * @param array $rows Array of validated row data
     * @param int|null $userId User performing the action
     * @return BulkInventoryResult Result with success/error counts and details
     */
    public function processBulkStockEntry(array $rows, ?int $userId = null): BulkInventoryResult {
        $result = new BulkInventoryResult();
        $result->totalRows = count($rows);
        $result->isAtomic = false; // Partial success allowed
        
        if (empty($rows)) {
            $result->success = true;
            $result->message = 'No rows to process';
            return $result;
        }
        
        foreach ($rows as $index => $row) {
            $rowNumber = $row['_row_number'] ?? ($index + 2);
            
            try {
                // Get product (use cached or fetch)
                $product = $row['_product'] ?? $this->productRepository->find($row['product_id']);
                if (!$product) {
                    $result->addError($rowNumber, "Product ID {$row['product_id']} not found", 'product_id');
                    continue;
                }
                
                // Prepare additional data from row
                $additionalData = [];
                if (isset($row['notes']) && $row['notes'] !== '') {
                    $additionalData['notes'] = trim($row['notes']);
                }
                if (isset($row['is_repairable']) && $row['is_repairable'] !== '') {
                    $additionalData['is_repairable'] = (int)$row['is_repairable'];
                }
                
                if ($product['is_serializable']) {
                    // Add asset for serializable product
                    $serialNumber = trim($row['serial_number']);
                    $addResult = $this->stockService->addAsset(
                        $row['product_id'],
                        $row['warehouse_id'],
                        $serialNumber,
                        $userId,
                        $additionalData
                    );
                    
                    if ($addResult['success']) {
                        $result->addSuccess($rowNumber, $addResult['data']['id'], "Asset created with serial number '$serialNumber'");
                    } else {
                        $result->addError($rowNumber, $addResult['message'], 'serial_number');
                    }
                } else {
                    // Add stock for non-serializable product
                    $quantity = intval($row['quantity']);
                    $addResult = $this->stockService->addStock(
                        $row['product_id'],
                        $row['warehouse_id'],
                        $quantity,
                        $userId,
                        $additionalData['notes'] ?? null
                    );
                    
                    if ($addResult['success']) {
                        $result->addSuccess($rowNumber, $addResult['data']['id'] ?? 0, "Added $quantity units to stock");
                    } else {
                        $result->addError($rowNumber, $addResult['message'], 'quantity');
                    }
                }
                
            } catch (Exception $e) {
                $result->addError($rowNumber, 'Processing error: ' . $e->getMessage());
            }
        }
        
        $result->success = $result->errorCount === 0;
        $result->message = "Processed {$result->successCount} of {$result->totalRows} rows successfully";
        if ($result->errorCount > 0) {
            $result->message .= ". {$result->errorCount} rows failed.";
        }
        
        return $result;
    }
    
    /**
     * Process bulk dispatch as atomic transaction
     * Requirement 4.3: Process multiple items in a single atomic transaction
     * Requirement 4.4: Rollback all changes and report the failure reason on bulk operation failure
     * 
     * @param array $dispatchData Dispatch header data (from_warehouse_id, to_company_id, etc.)
     * @param array $items Array of items to dispatch
     * @param int|null $userId User performing the action
     * @return BulkInventoryResult Result with success status and details
     */
    public function processBulkDispatch(array $dispatchData, array $items, ?int $userId = null): BulkInventoryResult {
        $result = new BulkInventoryResult();
        $result->totalRows = count($items);
        $result->isAtomic = true; // All-or-nothing
        
        if (empty($items)) {
            $result->success = false;
            $result->message = 'No items to dispatch';
            return $result;
        }
        
        // Validate dispatch data
        $validation = $this->validateBulkDispatchData($dispatchData, $items);
        if (!$validation['success']) {
            $result->success = false;
            $result->message = $validation['message'];
            $result->errors = $validation['errors'];
            $result->errorCount = count($validation['errors']);
            return $result;
        }
        
        try {
            // Start transaction for atomic operation
            $this->conn->begin_transaction();
            
            // Get source warehouse
            $fromWarehouse = $this->warehouseRepository->find($dispatchData['from_warehouse_id']);
            
            // Create dispatch record
            $dispatchNumber = DispatchRepository::generateDispatchNumber();
            $dispatch = $this->dispatchRepository->create([
                'dispatch_number' => $dispatchNumber,
                'from_company_id' => $fromWarehouse['company_id'],
                'from_warehouse_id' => $dispatchData['from_warehouse_id'],
                'to_company_id' => $dispatchData['to_company_id'] ?? null,
                'to_user_id' => $dispatchData['to_user_id'] ?? null,
                'to_warehouse_id' => $dispatchData['to_warehouse_id'] ?? null,
                'dispatch_date' => $dispatchData['dispatch_date'] ?? date('Y-m-d'),
                'status' => DispatchRepository::STATUS_PENDING,
                'acknowledgment_status' => DispatchRepository::ACK_PENDING,
                'notes' => $dispatchData['notes'] ?? null,
                'created_by' => $userId
            ]);
            
            $dispatchId = $dispatch['id'];
            $createdItemIds = [];
            
            // Process each item
            foreach ($items as $index => $item) {
                $rowNumber = $item['_row_number'] ?? ($index + 2);
                
                $product = $this->productRepository->find($item['product_id']);
                
                $itemData = [
                    'dispatch_id' => $dispatchId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'] ?? 1,
                    'asset_id' => null
                ];
                
                if ($product['is_serializable']) {
                    // Handle serializable item
                    if (!empty($item['asset_id'])) {
                        $itemData['asset_id'] = $item['asset_id'];
                    } elseif (!empty($item['serial_number'])) {
                        $asset = $this->assetRepository->findBySerialNumber($item['serial_number']);
                        if (!$asset) {
                            throw new Exception("Row $rowNumber: Asset with serial number '{$item['serial_number']}' not found");
                        }
                        $itemData['asset_id'] = $asset['id'];
                    }
                    $itemData['quantity'] = 1;
                }
                
                $createdItem = $this->dispatchItemRepository->create($itemData);
                $createdItemIds[] = $createdItem['id'];
                $result->addSuccess($rowNumber, $createdItem['id'], 'Item added to dispatch');
            }
            
            // Log audit entry
            $this->logAuditEntry(
                'bulk_dispatch_created',
                'dispatch',
                $dispatchId,
                $userId,
                'warehouse',
                $dispatchData['from_warehouse_id'],
                $this->getDestinationType($dispatchData),
                $this->getDestinationId($dispatchData),
                ['dispatch_number' => $dispatchNumber, 'item_count' => count($items)]
            );
            
            // Commit transaction
            $this->conn->commit();
            
            $result->success = true;
            $result->message = "Bulk dispatch $dispatchNumber created successfully with " . count($items) . " items";
            $result->createdIds = [$dispatchId]; // Main dispatch ID
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->conn->rollback();
            
            $result->success = false;
            $result->wasRolledBack = true;
            $result->message = 'Bulk dispatch failed: ' . $e->getMessage();
            $result->errorCount = $result->totalRows;
            $result->successCount = 0;
            $result->createdIds = [];
            $result->rowResults = [];
            
            // Mark all rows as failed due to rollback
            foreach ($items as $index => $item) {
                $rowNumber = $item['_row_number'] ?? ($index + 2);
                $result->addError($rowNumber, 'Rolled back due to transaction failure: ' . $e->getMessage());
            }
        }
        
        return $result;
    }

    
    /**
     * Validate bulk dispatch data
     * 
     * @param array $dispatchData Dispatch header data
     * @param array $items Items to dispatch
     * @return array Validation result
     */
    private function validateBulkDispatchData(array $dispatchData, array $items): array {
        $errors = [];
        
        // Validate source warehouse
        if (empty($dispatchData['from_warehouse_id'])) {
            return [
                'success' => false,
                'message' => 'Source warehouse ID is required',
                'errors' => [0 => ['field' => 'from_warehouse_id', 'message' => 'Source warehouse ID is required']]
            ];
        }
        
        $fromWarehouse = $this->warehouseRepository->find($dispatchData['from_warehouse_id']);
        if (!$fromWarehouse) {
            return [
                'success' => false,
                'message' => 'Source warehouse not found',
                'errors' => [0 => ['field' => 'from_warehouse_id', 'message' => 'Source warehouse not found']]
            ];
        }
        
        if ($fromWarehouse['status'] !== WarehouseRepository::STATUS_ACTIVE) {
            return [
                'success' => false,
                'message' => 'Cannot dispatch from inactive warehouse',
                'errors' => [0 => ['field' => 'from_warehouse_id', 'message' => 'Cannot dispatch from inactive warehouse']]
            ];
        }
        
        // Validate destination
        $hasDestination = !empty($dispatchData['to_company_id']) || 
                          !empty($dispatchData['to_user_id']) || 
                          !empty($dispatchData['to_warehouse_id']);
        
        if (!$hasDestination) {
            return [
                'success' => false,
                'message' => 'At least one destination (company, user, or warehouse) is required',
                'errors' => [0 => ['field' => 'destination', 'message' => 'Destination is required']]
            ];
        }
        
        // Validate each item
        $productQuantities = []; // Track total quantities per product for stock validation
        
        foreach ($items as $index => $item) {
            $rowNumber = $item['_row_number'] ?? ($index + 2);
            
            // Validate product_id
            if (empty($item['product_id'])) {
                $errors[$rowNumber] = ['field' => 'product_id', 'message' => 'Product ID is required'];
                continue;
            }
            
            $product = $this->productRepository->find($item['product_id']);
            if (!$product) {
                $errors[$rowNumber] = ['field' => 'product_id', 'message' => "Product ID {$item['product_id']} not found"];
                continue;
            }
            
            if ($product['is_serializable']) {
                // Validate asset for serializable products
                if (empty($item['asset_id']) && empty($item['serial_number'])) {
                    $errors[$rowNumber] = ['field' => 'serial_number', 'message' => 'Asset ID or serial number is required for serializable products'];
                    continue;
                }
                
                $asset = !empty($item['asset_id']) 
                    ? $this->assetRepository->find($item['asset_id'])
                    : $this->assetRepository->findBySerialNumber($item['serial_number']);
                
                if (!$asset) {
                    $errors[$rowNumber] = ['field' => 'serial_number', 'message' => 'Asset not found'];
                    continue;
                }
                
                if ($asset['warehouse_id'] != $dispatchData['from_warehouse_id']) {
                    $errors[$rowNumber] = ['field' => 'serial_number', 'message' => 'Asset is not in the source warehouse'];
                    continue;
                }
                
                if ($asset['status'] !== AssetRepository::STATUS_IN_STOCK) {
                    $errors[$rowNumber] = ['field' => 'serial_number', 'message' => "Asset is not available (status: {$asset['status']})"];
                    continue;
                }
                
                if ($this->assetRepository->isLocked($asset['id'])) {
                    $errors[$rowNumber] = ['field' => 'serial_number', 'message' => 'Asset is locked and cannot be dispatched'];
                    continue;
                }
            } else {
                // Validate quantity for non-serializable products
                $quantity = $item['quantity'] ?? 1;
                if ($quantity <= 0) {
                    $errors[$rowNumber] = ['field' => 'quantity', 'message' => 'Quantity must be greater than zero'];
                    continue;
                }
                
                // Accumulate quantities for stock validation
                $productId = $item['product_id'];
                $productQuantities[$productId] = ($productQuantities[$productId] ?? 0) + $quantity;
            }
        }
        
        // Validate stock availability for non-serializable products
        foreach ($productQuantities as $productId => $totalQuantity) {
            $validation = $this->stockService->validateStockAvailability(
                $productId, 
                $dispatchData['from_warehouse_id'], 
                $totalQuantity
            );
            
            if (!$validation['success']) {
                $product = $this->productRepository->find($productId);
                $errors[0] = [
                    'field' => 'stock',
                    'message' => "Insufficient stock for product '{$product['name']}': requested $totalQuantity, available {$validation['available']}"
                ];
            }
        }
        
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Validation failed for ' . count($errors) . ' items',
                'errors' => $errors
            ];
        }
        
        return ['success' => true, 'message' => 'Validation passed', 'errors' => []];
    }
    
    /**
     * Generate detailed error report for bulk operation
     * Requirement 4.2: Generate error report listing failed rows with reasons
     * 
     * @param BulkInventoryResult $result The bulk operation result
     * @param string $filename Output filename (without extension)
     * @return array Result with 'success', 'path', 'message'
     */
    public function generateErrorReport(BulkInventoryResult $result, string $filename = 'bulk_inventory_errors'): array {
        if (empty($result->errors)) {
            return [
                'success' => true,
                'path' => '',
                'message' => 'No errors to report'
            ];
        }
        
        // Format errors for the bulk operation service
        $formattedErrors = [];
        foreach ($result->errors as $rowNumber => $error) {
            $message = is_array($error) ? ($error['message'] ?? json_encode($error)) : $error;
            $field = is_array($error) ? ($error['field'] ?? '') : '';
            $formattedErrors[$rowNumber] = $field ? "[$field] $message" : $message;
        }
        
        $path = $this->bulkOperationService->generateErrorReport($formattedErrors, $filename);
        
        if (empty($path)) {
            return [
                'success' => false,
                'path' => '',
                'message' => 'Failed to generate error report'
            ];
        }
        
        return [
            'success' => true,
            'path' => $path,
            'message' => 'Error report generated successfully'
        ];
    }
    
    /**
     * Generate detailed status report for bulk operation with row-level status
     * Requirement 4.2: Track partial success with row-level status
     * 
     * @param BulkInventoryResult $result The bulk operation result
     * @param string $filename Output filename (without extension)
     * @return array Result with 'success', 'path', 'message'
     */
    public function generateDetailedStatusReport(BulkInventoryResult $result, string $filename = 'bulk_inventory_status'): array {
        if (empty($result->rowResults)) {
            return [
                'success' => true,
                'path' => '',
                'message' => 'No results to report'
            ];
        }
        
        // Prepare data for export
        $reportData = [];
        foreach ($result->rowResults as $rowNumber => $rowResult) {
            $reportData[] = [
                'row_number' => $rowNumber,
                'status' => $rowResult['status'],
                'message' => $rowResult['message'],
                'created_id' => $rowResult['id'] ?? ''
            ];
        }
        
        // Generate Excel report
        $headers = ['Row Number', 'Status', 'Message', 'Created ID'];
        $columnMapping = [
            'row_number' => 'A',
            'status' => 'B',
            'message' => 'C',
            'created_id' => 'D'
        ];
        
        $path = $this->bulkOperationService->generateExcelExport(
            $reportData,
            $headers,
            $columnMapping,
            $filename
        );
        
        if (empty($path)) {
            return [
                'success' => false,
                'path' => '',
                'message' => 'Failed to generate status report'
            ];
        }
        
        return [
            'success' => true,
            'path' => $path,
            'message' => 'Status report generated successfully',
            'summary' => [
                'total' => $result->totalRows,
                'success' => $result->successCount,
                'errors' => $result->errorCount,
                'wasRolledBack' => $result->wasRolledBack
            ]
        ];
    }
    
    /**
     * Get summary of bulk operation result
     * 
     * @param BulkInventoryResult $result The bulk operation result
     * @return array Summary with counts and status
     */
    public function getResultSummary(BulkInventoryResult $result): array {
        return [
            'success' => $result->success,
            'message' => $result->message,
            'totalRows' => $result->totalRows,
            'successCount' => $result->successCount,
            'errorCount' => $result->errorCount,
            'successRate' => $result->totalRows > 0 
                ? round(($result->successCount / $result->totalRows) * 100, 2) 
                : 0,
            'isAtomic' => $result->isAtomic,
            'wasRolledBack' => $result->wasRolledBack,
            'createdIds' => $result->createdIds,
            'failedRows' => array_keys($result->errors)
        ];
    }
    
    /**
     * Get column mapping for bulk stock entry
     * 
     * @return array Column mapping
     */
    public function getStockEntryColumnMapping(): array {
        return [
            'A' => 'product_id',
            'B' => 'product_name',      // For reference only
            'C' => 'warehouse_id',
            'D' => 'warehouse_name',    // For reference only
            'E' => 'quantity',
            'F' => 'serial_number',
            'G' => 'notes'
        ];
    }
    
    /**
     * Get column mapping for bulk dispatch
     * 
     * @return array Column mapping
     */
    public function getBulkDispatchColumnMapping(): array {
        return [
            'A' => 'product_id',
            'B' => 'product_name',      // For reference only
            'C' => 'quantity',
            'D' => 'serial_number',
            'E' => 'asset_id',
            'F' => 'notes'
        ];
    }
    
    /**
     * Parse bulk stock entry file
     * 
     * @param string $filePath Path to the Excel/CSV file
     * @return array Parsed data with validation
     */
    public function parseBulkStockEntryFile(string $filePath): array {
        $parseResult = $this->bulkOperationService->parseExcelFile(
            $filePath,
            $this->getStockEntryColumnMapping()
        );
        
        if (!$parseResult['success']) {
            return $parseResult;
        }
        
        // Validate the parsed data
        return $this->validateBulkUpload($parseResult['data']);
    }
    
    /**
     * Get destination type from dispatch data
     */
    private function getDestinationType(array $data): string {
        if (!empty($data['to_warehouse_id'])) return 'warehouse';
        if (!empty($data['to_user_id'])) return 'user';
        if (!empty($data['to_company_id'])) return 'company';
        return 'unknown';
    }
    
    /**
     * Get destination ID from dispatch data
     */
    private function getDestinationId(array $data): ?int {
        if (!empty($data['to_warehouse_id'])) return $data['to_warehouse_id'];
        if (!empty($data['to_user_id'])) return $data['to_user_id'];
        if (!empty($data['to_company_id'])) return $data['to_company_id'];
        return null;
    }
    
    /**
     * Log audit entry for inventory actions
     */
    private function logAuditEntry(
        string $actionType,
        string $entityType,
        int $entityId,
        ?int $userId,
        ?string $fromLocationType,
        ?int $fromLocationId,
        ?string $toLocationType,
        ?int $toLocationId,
        ?array $details = null
    ): void {
        try {
            $this->auditLogRepository->create([
                'action_type' => $actionType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'user_id' => $userId ?? 0,
                'from_location_type' => $fromLocationType,
                'from_location_id' => $fromLocationId,
                'to_location_type' => $toLocationType,
                'to_location_id' => $toLocationId,
                'new_values' => $details ? json_encode($details) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'
            ]);
        } catch (Exception $e) {
            error_log("Failed to log audit entry: " . $e->getMessage());
        }
    }
}

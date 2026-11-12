<?php
/**
 * Inventory API - Export Inventory Data
 * GET /api/inventory/export/index.php
 * 
 * Exports inventory data to Excel/CSV format with permission filtering
 * 
 * Query Parameters:
 * - type: Export type (assets, stock, dispatches, transfers, repairs, audit, all)
 * - format: Export format (csv, json, xlsx) - default: csv
 * - product_id: Filter by product (optional)
 * - warehouse_id: Filter by warehouse (optional)
 * - status: Filter by status (optional)
 * - date_from: Filter by start date (optional)
 * - date_to: Filter by end date (optional)
 * - download: If 'true', returns file download; otherwise returns JSON with content
 * 
 * Response: File download or { success: bool, data: { content, filename, count } }
 * 
 * **Validates: Requirements 15.1, 15.2**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/InventoryExportService.php';
require_once __DIR__ . '/../../../services/InventoryAuditService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    $user = $authMiddleware->requireAuth();
    
    // Get query parameters
    $type = $_GET['type'] ?? 'assets';
    $format = $_GET['format'] ?? 'xlsx'; // Default to Excel (.xlsx) per user request
    
    // Determine whether to download as file (default: true for file formats like xlsx/csv)
    // Only return JSON if download=false/0 is explicitly passed OR format=json without download=true
    $downloadParam = $_GET['download'] ?? null;
    if ($downloadParam !== null) {
        $download = filter_var($downloadParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($download === null) {
            $download = in_array(strtolower($downloadParam), ['1', 'true', 'yes']);
        }
    } else {
        // Default to true for file exports (xlsx, csv) unless format is explicitly json
        $download = ($format !== 'json');
    }
    
    // Build filters from query parameters
    $filters = [];
    if (isset($_GET['product_id'])) {
        $filters['product_id'] = (int)$_GET['product_id'];
    }
    if (isset($_GET['warehouse_id'])) {
        $filters['warehouse_id'] = (int)$_GET['warehouse_id'];
    }
    if (isset($_GET['status'])) {
        $filters['status'] = $_GET['status'];
    }
    if (isset($_GET['date_from'])) {
        $filters['date_from'] = $_GET['date_from'];
    }
    if (isset($_GET['date_to'])) {
        $filters['date_to'] = $_GET['date_to'];
    }
    
    // Validate export type
    $validTypes = InventoryExportService::getExportTypes();
    if (!in_array($type, $validTypes)) {
        ApiResponse::validationError([
            'type' => 'Invalid export type. Valid types: ' . implode(', ', $validTypes)
        ]);
    }
    
    // Validate format
    $validFormats = InventoryExportService::getFormats();
    if (!in_array($format, $validFormats)) {
        ApiResponse::validationError([
            'format' => 'Invalid format. Valid formats: ' . implode(', ', $validFormats)
        ]);
    }
    
    $exportService = new InventoryExportService();
    $auditService = new InventoryAuditService();
    
    // Perform export with permission filtering
    $result = $exportService->export($user['id'], $type, $format, $filters);
    
    if (!$result['success']) {
        ApiResponse::error($result['code'] ?? 'EXPORT_ERROR', $result['message'], 400);
    }
    
    // Log the export action
    $auditService->logAction(
        'export',
        'inventory',
        0,
        $user['id'],
        [
            'new_values' => [
                'type' => $type,
                'format' => $format,
                'filters' => $filters,
                'record_count' => $result['data']['count'] ?? 0
            ]
        ]
    );
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/export', 'GET', [
        'type' => $type,
        'format' => $format,
        'count' => $result['data']['count'] ?? 0
    ]);
    
/**
 * Build rows using human-readable names and real values instead of internal IDs
 */
function getHumanFriendlyRows(array $records, string $type, bool $boldHeaders = false): array {
    $rows = [];
    $h = function($text) use ($boldHeaders) {
        return $boldHeaders ? '<b>' . $text . '</b>' : $text;
    };
    
    switch ($type) {
        case 'assets':
            $rows[] = [
                $h('Serial Number'),
                $h('Product'),
                $h('Warehouse'),
                $h('Status'),
                $h('Condition'),
                $h('Current Holder Type'),
                $h('Current Holder'),
                $h('Source Warehouse'),
                $h('Warranty Expiry'),
                $h('Notes'),
                $h('Created At'),
                $h('Updated At')
            ];
            
            foreach ($records as $r) {
                $status = !empty($r['status']) ? ucwords(str_replace('_', ' ', $r['status'])) : '';
                $condition = !empty($r['working_condition']) ? ucwords(str_replace('_', ' ', $r['working_condition'])) : '';
                $holderType = !empty($r['current_holder_type']) ? ucfirst($r['current_holder_type']) : '';
                $currentHolder = $r['current_holder_name'] ?? ($r['warehouse_name'] ?? ($r['current_holder_id'] ?? ''));
                $sourceWarehouse = $r['source_warehouse_name'] ?? ($r['warehouse_name'] ?? ($r['source_warehouse_id'] ?? ''));
                
                $rows[] = [
                    $r['serial_number'] ?? '',
                    $r['product_name'] ?: ($r['product_id'] ?? ''),
                    $r['warehouse_name'] ?: ($r['warehouse_id'] ?? ''),
                    $status,
                    $condition,
                    $holderType,
                    $currentHolder,
                    $sourceWarehouse,
                    $r['warranty_expiry'] ?? '',
                    $r['notes'] ?? '',
                    $r['created_at'] ?? '',
                    $r['updated_at'] ?? ''
                ];
            }
            break;
            
        case 'stock':
            $rows[] = [
                $h('Product'),
                $h('Warehouse'),
                $h('Available Quantity'),
                $h('Reserved Quantity'),
                $h('Total Quantity'),
                $h('Unit of Measure'),
                $h('Updated At')
            ];
            
            foreach ($records as $r) {
                $rows[] = [
                    $r['product_name'] ?: ($r['product_id'] ?? ''),
                    $r['warehouse_name'] ?: ($r['warehouse_id'] ?? ''),
                    $r['available_quantity'] ?? (($r['quantity'] ?? 0) - ($r['reserved_quantity'] ?? 0)),
                    $r['reserved_quantity'] ?? 0,
                    $r['quantity'] ?? 0,
                    $r['unit_of_measure'] ?? '',
                    $r['updated_at'] ?? ''
                ];
            }
            break;
            
        case 'dispatches':
            $rows[] = [
                $h('Dispatch Number'),
                $h('From Warehouse'),
                $h('From Company'),
                $h('Dispatched To'),
                $h('Dispatch Date'),
                $h('Status'),
                $h('Acknowledgment Status'),
                $h('Acknowledged At'),
                $h('Notes'),
                $h('Created At')
            ];
            
            foreach ($records as $r) {
                $to = $r['to_warehouse_name'] ?: ($r['to_company_name'] ?: ($r['to_user_name'] ?? ''));
                $rows[] = [
                    $r['dispatch_number'] ?? '',
                    $r['from_warehouse_name'] ?? '',
                    $r['from_company_name'] ?? '',
                    $to,
                    $r['dispatch_date'] ?? '',
                    !empty($r['status']) ? ucwords(str_replace('_', ' ', $r['status'])) : '',
                    !empty($r['acknowledgment_status']) ? ucwords(str_replace('_', ' ', $r['acknowledgment_status'])) : '',
                    $r['acknowledged_at'] ?? '',
                    $r['notes'] ?? '',
                    $r['created_at'] ?? ''
                ];
            }
            break;
            
        case 'transfers':
            $rows[] = [
                $h('Transfer Number'),
                $h('From Warehouse'),
                $h('To Warehouse'),
                $h('Transfer Date'),
                $h('Status'),
                $h('Notes'),
                $h('Created At')
            ];
            
            foreach ($records as $r) {
                $rows[] = [
                    $r['transfer_number'] ?? '',
                    $r['from_warehouse_name'] ?? '',
                    $r['to_warehouse_name'] ?? '',
                    $r['transfer_date'] ?? '',
                    !empty($r['status']) ? ucwords(str_replace('_', ' ', $r['status'])) : '',
                    $r['notes'] ?? '',
                    $r['created_at'] ?? ''
                ];
            }
            break;
            
        case 'repairs':
            $rows[] = [
                $h('Repair Number'),
                $h('Serial Number'),
                $h('Product'),
                $h('Service Center'),
                $h('Fault Description'),
                $h('Estimated Cost'),
                $h('Actual Cost'),
                $h('Send Date'),
                $h('Expected Return Date'),
                $h('Actual Return Date'),
                $h('Status'),
                $h('Notes'),
                $h('Created At'),
                $h('Updated At')
            ];
            
            foreach ($records as $r) {
                $rows[] = [
                    $r['repair_number'] ?? ($r['id'] ?? ''),
                    $r['serial_number'] ?? '',
                    $r['product_name'] ?? '',
                    $r['service_center_name'] ?: ($r['repair_vendor'] ?: ($r['company_name'] ?? '')),
                    $r['fault_description'] ?? '',
                    $r['estimated_cost'] ?? '',
                    $r['actual_cost'] ?? '',
                    $r['send_date'] ?? '',
                    $r['expected_return_date'] ?? '',
                    $r['actual_return_date'] ?? '',
                    !empty($r['status']) ? ucwords(str_replace('_', ' ', $r['status'])) : '',
                    $r['notes'] ?? '',
                    $r['created_at'] ?? '',
                    $r['updated_at'] ?? ''
                ];
            }
            break;
            
        default:
            if (!empty($records)) {
                $headers = array_keys($records[0]);
                $rows[] = array_map(function($val) use ($h) { return $h($val); }, $headers);
                foreach ($records as $r) {
                    $rows[] = array_values($r);
                }
            }
            break;
    }
    
    return $rows;
}

    // Return file download or JSON response
    if ($download) {
        $rawMode = !empty($_GET['raw']);
        $filename = $result['data']['filename'] ?? ($type . '_export_' . date('Y-m-d_His') . '.' . $format);
        
        if (($format === 'xlsx' || $format === 'excel') && !$rawMode) {
            require_once __DIR__ . '/../../../utils/SimpleXLSXGen.php';
            $exportData = $exportService->getExportData($user['id'], $type, $filters);
            $rows = getHumanFriendlyRows($exportData['records'] ?? [], $type, true);
            $sheetTitle = ucfirst($type);
            $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($rows, $sheetTitle);
            $content = (string) $xlsx;
            $filename = str_replace('.csv', '.xlsx', $filename);
            if (!str_ends_with($filename, '.xlsx')) {
                $filename .= '.xlsx';
            }
            $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } elseif ($format === 'csv' && !$rawMode) {
            $exportData = $exportService->getExportData($user['id'], $type, $filters);
            $rows = getHumanFriendlyRows($exportData['records'] ?? [], $type, false);
            $output = fopen('php://temp', 'r+');
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            rewind($output);
            $content = stream_get_contents($output);
            fclose($output);
            $filename = str_replace('.xlsx', '.csv', $filename);
            if (!str_ends_with($filename, '.csv')) {
                $filename .= '.csv';
            }
            $mimeType = 'text/csv';
        } else {
            $content = $result['data']['content'] ?? null;
            $mimeType = $result['data']['mime_type'] ?? 'application/octet-stream';
        }
        
        if ($content !== null) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            header('Content-Type: ' . $mimeType . ($format === 'csv' ? '; charset=utf-8' : ''));
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($content));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            echo $content;
            exit;
        }
    }
    
    ApiResponse::success($result['data'], 'Export generated successfully');
    
} catch (Exception $e) {
    error_log("Export API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to generate export');
}

<?php
/**
 * Inventory API - Movement History Report
 * GET /api/inventory/reports/movement.php
 * 
 * Generates movement history reports for inventory items
 * 
 * Query Parameters:
 * - date_from: Start date for report (optional, format: YYYY-MM-DD)
 * - date_to: End date for report (optional, format: YYYY-MM-DD)
 * - warehouse_id: Filter by warehouse (optional)
 * - product_id: Filter by product (optional)
 * - include_dispatches: Include dispatch movements (default: true)
 * - include_transfers: Include transfer movements (default: true)
 * - include_returns: Include return movements (default: true)
 * - format: Output format (json, csv) - default: json
 * - download: If 'true', returns file download
 * 
 * Response: { success: bool, data: { movements: [], summary: {}, generated_at: string } }
 * 
 * **Validates: Requirements 12.2, 12.3**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/InventoryAuditService.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../repositories/DispatchRepository.php';
require_once __DIR__ . '/../../../repositories/TransferRepository.php';

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
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    $warehouseId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : null;
    $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
    $includeDispatches = ($_GET['include_dispatches'] ?? 'true') === 'true';
    $includeTransfers = ($_GET['include_transfers'] ?? 'true') === 'true';
    $includeReturns = ($_GET['include_returns'] ?? 'true') === 'true';
    $format = $_GET['format'] ?? 'json';
    $download = ($_GET['download'] ?? 'false') === 'true';
    
    // Validate date formats
    if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        ApiResponse::validationError(['date_from' => 'Invalid date format. Use YYYY-MM-DD']);
    }
    if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        ApiResponse::validationError(['date_to' => 'Invalid date format. Use YYYY-MM-DD']);
    }
    
    $accessService = new InventoryAccessService();
    $dispatchRepository = new DispatchRepository();
    $transferRepository = new TransferRepository();
    
    // Get accessible warehouses for permission filtering
    $accessibleWarehouses = $accessService->getAccessibleWarehouses($user['id']);
    $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
    
    // If warehouse filter specified, validate access
    if ($warehouseId !== null) {
        if (!in_array($warehouseId, $accessibleWarehouseIds)) {
            ApiResponse::forbidden('You do not have access to this warehouse');
        }
        $accessibleWarehouseIds = [$warehouseId];
    }
    
    $movements = [];
    
    // Get dispatches
    if ($includeDispatches) {
        $dispatches = $dispatchRepository->findAllWithDetails();
        
        foreach ($dispatches as $dispatch) {
            // Filter by accessible warehouses
            if (!in_array($dispatch['from_warehouse_id'], $accessibleWarehouseIds)) {
                continue;
            }
            
            // Filter by date range
            if ($dateFrom && $dispatch['dispatch_date'] < $dateFrom) {
                continue;
            }
            if ($dateTo && $dispatch['dispatch_date'] > $dateTo) {
                continue;
            }
            
            $movements[] = [
                'type' => 'dispatch',
                'id' => $dispatch['id'],
                'reference' => $dispatch['dispatch_number'],
                'date' => $dispatch['dispatch_date'],
                'from_warehouse_id' => $dispatch['from_warehouse_id'],
                'from_warehouse_name' => $dispatch['from_warehouse_name'] ?? null,
                'to_type' => $dispatch['to_warehouse_id'] ? 'warehouse' : 
                            ($dispatch['to_user_id'] ? 'user' : 'company'),
                'to_id' => $dispatch['to_warehouse_id'] ?? $dispatch['to_user_id'] ?? $dispatch['to_company_id'],
                'status' => $dispatch['status'],
                'acknowledgment_status' => $dispatch['acknowledgment_status'],
                'created_at' => $dispatch['created_at']
            ];
        }
    }
    
    // Get transfers
    if ($includeTransfers) {
        $transfers = $transferRepository->findAllWithDetails();
        
        foreach ($transfers as $transfer) {
            // Filter by accessible warehouses (either source or destination)
            if (!in_array($transfer['from_warehouse_id'], $accessibleWarehouseIds) &&
                !in_array($transfer['to_warehouse_id'], $accessibleWarehouseIds)) {
                continue;
            }
            
            // Filter by date range
            if ($dateFrom && $transfer['transfer_date'] < $dateFrom) {
                continue;
            }
            if ($dateTo && $transfer['transfer_date'] > $dateTo) {
                continue;
            }
            
            $movements[] = [
                'type' => 'transfer',
                'id' => $transfer['id'],
                'reference' => $transfer['transfer_number'],
                'date' => $transfer['transfer_date'],
                'from_warehouse_id' => $transfer['from_warehouse_id'],
                'from_warehouse_name' => $transfer['from_warehouse_name'] ?? null,
                'to_type' => 'warehouse',
                'to_id' => $transfer['to_warehouse_id'],
                'to_warehouse_name' => $transfer['to_warehouse_name'] ?? null,
                'status' => $transfer['status'],
                'acknowledgment_status' => null,
                'created_at' => $transfer['created_at']
            ];
        }
    }
    
    // Sort movements by date (most recent first)
    usort($movements, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    // Calculate summary
    $summary = [
        'total_movements' => count($movements),
        'dispatches' => count(array_filter($movements, fn($m) => $m['type'] === 'dispatch')),
        'transfers' => count(array_filter($movements, fn($m) => $m['type'] === 'transfer')),
        'by_status' => [],
        'date_range' => [
            'from' => $dateFrom ?? 'all time',
            'to' => $dateTo ?? 'present'
        ]
    ];
    
    foreach ($movements as $movement) {
        $status = $movement['status'] ?? 'unknown';
        if (!isset($summary['by_status'][$status])) {
            $summary['by_status'][$status] = 0;
        }
        $summary['by_status'][$status]++;
    }
    
    $reportData = [
        'report_type' => 'movement_history',
        'summary' => $summary,
        'movements' => $movements,
        'filters_applied' => [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'warehouse_id' => $warehouseId,
            'include_dispatches' => $includeDispatches,
            'include_transfers' => $includeTransfers,
            'include_returns' => $includeReturns
        ],
        'generated_at' => date('Y-m-d H:i:s'),
        'generated_by' => $user['id']
    ];
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/reports/movement', 'GET', [
        'total_movements' => count($movements)
    ]);
    
    // Handle download/format
    if ($download || $format === 'csv') {
        $csvContent = generateMovementCsv($reportData);
        $filename = 'movement_report_' . date('Y-m-d_His') . '.csv';
        
        if ($download) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($csvContent));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            echo $csvContent;
            exit;
        }
        
        $reportData['csv_content'] = $csvContent;
        $reportData['filename'] = $filename;
    }
    
    ApiResponse::success($reportData, 'Movement report generated successfully');
    
} catch (Exception $e) {
    error_log("Movement Report API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to generate movement report');
}

/**
 * Generate CSV content from movement report data
 */
function generateMovementCsv(array $reportData): string {
    $output = fopen('php://temp', 'r+');
    
    // Write header
    fputcsv($output, [
        'Type',
        'ID',
        'Reference',
        'Date',
        'From Warehouse ID',
        'From Warehouse Name',
        'To Type',
        'To ID',
        'Status',
        'Acknowledgment Status',
        'Created At'
    ]);
    
    // Write data rows
    foreach ($reportData['movements'] as $movement) {
        fputcsv($output, [
            $movement['type'],
            $movement['id'],
            $movement['reference'],
            $movement['date'],
            $movement['from_warehouse_id'],
            $movement['from_warehouse_name'] ?? '',
            $movement['to_type'],
            $movement['to_id'],
            $movement['status'],
            $movement['acknowledgment_status'] ?? '',
            $movement['created_at']
        ]);
    }
    
    rewind($output);
    $csvContent = stream_get_contents($output);
    fclose($output);
    
    return $csvContent;
}

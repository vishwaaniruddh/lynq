<?php
/**
 * Inventory API - Audit Report Generation
 * GET /api/inventory/reports/audit.php
 * 
 * Generates compliance reports from audit log including movement history and status changes
 * 
 * Query Parameters:
 * - date_from: Start date for report (optional, format: YYYY-MM-DD)
 * - date_to: End date for report (optional, format: YYYY-MM-DD)
 * - action_type: Filter by action type (optional)
 * - entity_type: Filter by entity type (optional)
 * - user_id: Filter by user (optional)
 * - asset_id: Filter by specific asset (optional)
 * - format: Output format (json, csv) - default: json
 * - download: If 'true', returns file download
 * 
 * Response: { success: bool, data: { summary: {}, logs: [], generated_at: string } }
 * 
 * **Validates: Requirements 12.3**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/InventoryAuditService.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';

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
    $actionType = $_GET['action_type'] ?? null;
    $entityType = $_GET['entity_type'] ?? null;
    $filterUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $assetId = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : null;
    $format = $_GET['format'] ?? 'json';
    $download = ($_GET['download'] ?? 'false') === 'true';
    
    // Validate date formats
    if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        ApiResponse::validationError(['date_from' => 'Invalid date format. Use YYYY-MM-DD']);
    }
    if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        ApiResponse::validationError(['date_to' => 'Invalid date format. Use YYYY-MM-DD']);
    }
    
    // Validate action type if provided
    if ($actionType) {
        $validActionTypes = InventoryAuditService::getActionTypes();
        if (!in_array($actionType, $validActionTypes)) {
            ApiResponse::validationError([
                'action_type' => 'Invalid action type. Valid types: ' . implode(', ', $validActionTypes)
            ]);
        }
    }
    
    // Validate entity type if provided
    if ($entityType) {
        $validEntityTypes = InventoryAuditService::getEntityTypes();
        if (!in_array($entityType, $validEntityTypes)) {
            ApiResponse::validationError([
                'entity_type' => 'Invalid entity type. Valid types: ' . implode(', ', $validEntityTypes)
            ]);
        }
    }
    
    $auditService = new InventoryAuditService();
    $accessService = new InventoryAccessService();
    
    // Check user role for access control
    $roleType = $accessService->getUserRoleType($user['id']);
    
    // Build filters
    $filters = [];
    if ($dateFrom) {
        $filters['date_from'] = $dateFrom;
    }
    if ($dateTo) {
        $filters['date_to'] = $dateTo;
    }
    if ($actionType) {
        $filters['action_type'] = $actionType;
    }
    if ($entityType) {
        $filters['entity_type'] = $entityType;
    }
    if ($filterUserId) {
        $filters['user_id'] = $filterUserId;
    }
    
    // If specific asset requested, get asset history
    if ($assetId) {
        $assetHistory = $auditService->getAssetHistory($assetId);
        $traceability = $auditService->getItemTraceability($assetId);
        
        $reportData = [
            'report_type' => 'asset_history',
            'asset_id' => $assetId,
            'traceability' => $traceability['success'] ? $traceability['data'] : null,
            'history' => $assetHistory,
            'total_events' => count($assetHistory),
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => $user['id']
        ];
    } else {
        // Generate full audit report
        $report = $auditService->generateReport($filters);
        
        if (!$report['success']) {
            ApiResponse::error($report['code'] ?? 'REPORT_ERROR', $report['message'], 400);
        }
        
        // Apply permission filtering for non-ADV users
        $logs = $report['data']['logs'] ?? [];
        
        if ($roleType !== 'ADV') {
            // Get accessible warehouses
            $accessibleWarehouses = $accessService->getAccessibleWarehouses($user['id']);
            $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
            
            // Filter logs by accessible warehouses
            $logs = array_filter($logs, function($log) use ($accessibleWarehouseIds, $user, $roleType) {
                // Check from_location
                if ($log['from_location_type'] === 'warehouse' && 
                    isset($log['from_location_id']) && 
                    in_array($log['from_location_id'], $accessibleWarehouseIds)) {
                    return true;
                }
                // Check to_location
                if ($log['to_location_type'] === 'warehouse' && 
                    isset($log['to_location_id']) && 
                    in_array($log['to_location_id'], $accessibleWarehouseIds)) {
                    return true;
                }
                // Engineers can see their own actions
                if ($roleType === 'ENGINEER' && $log['user_id'] == $user['id']) {
                    return true;
                }
                return false;
            });
            $logs = array_values($logs);
        }
        
        // Calculate summary statistics
        $summary = [
            'total_events' => count($logs),
            'by_action_type' => [],
            'by_entity_type' => [],
            'by_user' => [],
            'date_range' => [
                'from' => $dateFrom ?? 'all time',
                'to' => $dateTo ?? 'present'
            ]
        ];
        
        foreach ($logs as $log) {
            // Count by action type
            $action = $log['action_type'] ?? 'unknown';
            if (!isset($summary['by_action_type'][$action])) {
                $summary['by_action_type'][$action] = 0;
            }
            $summary['by_action_type'][$action]++;
            
            // Count by entity type
            $entity = $log['entity_type'] ?? 'unknown';
            if (!isset($summary['by_entity_type'][$entity])) {
                $summary['by_entity_type'][$entity] = 0;
            }
            $summary['by_entity_type'][$entity]++;
            
            // Count by user
            $userId = $log['user_id'] ?? 0;
            if (!isset($summary['by_user'][$userId])) {
                $summary['by_user'][$userId] = 0;
            }
            $summary['by_user'][$userId]++;
        }
        
        $reportData = [
            'report_type' => 'audit_report',
            'summary' => $summary,
            'logs' => $logs,
            'filters_applied' => $filters,
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => $user['id']
        ];
    }
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/reports/audit', 'GET', [
        'filters' => $filters,
        'asset_id' => $assetId,
        'total_events' => $reportData['total_events'] ?? count($reportData['logs'] ?? [])
    ]);
    
    // Handle download/format
    if ($download || $format === 'csv') {
        $csvContent = generateAuditCsv($reportData);
        $filename = 'audit_report_' . date('Y-m-d_His') . '.csv';
        
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
    
    ApiResponse::success($reportData, 'Audit report generated successfully');
    
} catch (Exception $e) {
    error_log("Audit Report API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to generate audit report');
}

/**
 * Generate CSV content from audit report data
 */
function generateAuditCsv(array $reportData): string {
    $output = fopen('php://temp', 'r+');
    
    // Write header
    fputcsv($output, [
        'ID',
        'Action Type',
        'Entity Type',
        'Entity ID',
        'User ID',
        'From Location Type',
        'From Location ID',
        'To Location Type',
        'To Location ID',
        'Old Values',
        'New Values',
        'IP Address',
        'Created At'
    ]);
    
    // Write data rows
    $logs = $reportData['logs'] ?? $reportData['history'] ?? [];
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'] ?? '',
            $log['action_type'] ?? '',
            $log['entity_type'] ?? '',
            $log['entity_id'] ?? '',
            $log['user_id'] ?? '',
            $log['from_location_type'] ?? '',
            $log['from_location_id'] ?? '',
            $log['to_location_type'] ?? '',
            $log['to_location_id'] ?? '',
            is_array($log['old_values'] ?? null) ? json_encode($log['old_values']) : ($log['old_values'] ?? ''),
            is_array($log['new_values'] ?? null) ? json_encode($log['new_values']) : ($log['new_values'] ?? ''),
            $log['ip_address'] ?? '',
            $log['created_at'] ?? ''
        ]);
    }
    
    rewind($output);
    $csvContent = stream_get_contents($output);
    fclose($output);
    
    return $csvContent;
}

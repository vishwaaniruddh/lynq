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
    $format = $_GET['format'] ?? 'csv';
    $download = ($_GET['download'] ?? 'false') === 'true';
    
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
    
    // Return file download or JSON response
    if ($download && isset($result['data']['content'])) {
        $filename = $result['data']['filename'] ?? 'export.' . $format;
        $mimeType = $result['data']['mime_type'] ?? 'application/octet-stream';
        
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($result['data']['content']));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        echo $result['data']['content'];
        exit;
    }
    
    ApiResponse::success($result['data'], 'Export generated successfully');
    
} catch (Exception $e) {
    error_log("Export API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to generate export');
}

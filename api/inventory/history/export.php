<?php
/**
 * Item History Export API Endpoint
 * GET /api/inventory/history/export.php
 * 
 * Exports item history as JSON for an asset (serializable item)
 * Produces output that can be re-imported without data loss (round-trip consistency)
 * 
 * Query Parameters:
 * - asset_id: int (required) - Asset ID to export history for
 * - format: string (optional) - Export format, default 'json'
 * - download: bool (optional) - If true, returns as downloadable file
 * 
 * Response: 
 * - If download=false: { success: bool, data: { content: string, format: string, filename: string } }
 * - If download=true: JSON file download
 * 
 * Requirements: 9.5
 * - Export item history that can be re-imported without data loss (round-trip consistency)
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/DispatchChainService.php';

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
    $assetId = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : null;
    $format = isset($_GET['format']) ? trim($_GET['format']) : 'json';
    $download = isset($_GET['download']) && ($_GET['download'] === 'true' || $_GET['download'] === '1');
    
    // Validate required parameters
    if ($assetId === null || $assetId <= 0) {
        ApiResponse::validationError(['asset_id' => 'Asset ID is required and must be a positive integer']);
    }
    
    // Validate format
    $validFormats = ['json'];
    if (!in_array($format, $validFormats)) {
        ApiResponse::validationError(['format' => 'Invalid format. Supported formats: ' . implode(', ', $validFormats)]);
    }
    
    // Initialize service
    $dispatchChainService = new DispatchChainService();
    
    // Export item history
    $result = $dispatchChainService->exportHistory($assetId, $format);
    
    if (!$result['success']) {
        $statusCode = 400;
        if ($result['code'] === 'ASSET_NOT_FOUND') {
            $statusCode = 404;
        }
        ApiResponse::error($result['code'] ?? 'EXPORT_ERROR', $result['message'], $statusCode);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/history/export', 'GET', [
        'asset_id' => $assetId,
        'format' => $format,
        'download' => $download
    ]);
    
    // If download requested, send as file
    if ($download) {
        $filename = $result['data']['filename'];
        $content = $result['data']['content'];
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        echo $content;
        exit;
    }
    
    // Return as API response
    ApiResponse::success($result['data'], 'Item history exported successfully');
    
} catch (Exception $e) {
    error_log("Item History Export API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to export item history: ' . $e->getMessage());
}

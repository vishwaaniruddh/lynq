<?php
/**
 * Delegations Export API Endpoint
 * GET /api/delegations/export.php - Export delegations to Excel file
 * 
 * Query Parameters:
 * - search: Search term for site name, LHO, contractor (optional)
 * - status: Filter by status (pending, accepted, rejected) (optional)
 * - contractor_id: Filter by contractor ID (optional)
 * - date_from: Filter by delegation date from (optional)
 * - date_to: Filter by delegation date to (optional)
 * 
 * Returns: Excel file download
 * 
 * **Validates: Requirements 3.4**
 * - WHEN an ADV user exports delegation data THEN the System SHALL generate an Excel file 
 *   containing all filtered delegation records
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/DelegationService.php';

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authenticated user
    $user = $authMiddleware->requireAuth();
    
    // Only ADV users can export delegations
    if (!isAdvUser()) {
        ApiResponse::forbidden('Only ADV users can export delegations');
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::methodNotAllowed(['GET']);
        exit;
    }
    
    // Parse filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $contractorId = isset($_GET['contractor_id']) ? (int)$_GET['contractor_id'] : null;
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
    
    $filters = [];
    
    if ($search !== null && $search !== '') {
        $filters['search'] = $search;
    }
    
    if ($status !== null && $status !== '') {
        $filters['status'] = $status;
    }
    
    if ($contractorId !== null && $contractorId > 0) {
        $filters['contractor_id'] = $contractorId;
    }
    
    if ($dateFrom !== null && $dateFrom !== '') {
        $filters['date_from'] = $dateFrom . ' 00:00:00';
    }
    
    if ($dateTo !== null && $dateTo !== '') {
        $filters['date_to'] = $dateTo . ' 23:59:59';
    }
    
    $delegationService = new DelegationService();
    
    // Generate Excel export
    $filePath = $delegationService->generateDelegationExport($user['company_id'], $filters);
    
    if (empty($filePath) || !file_exists($filePath)) {
        ApiResponse::serverError('Failed to generate export file');
        exit;
    }
    
    // Log the export action
    $authMiddleware->logApiAccess($user['id'], '/api/delegations/export', 'GET', [
        'action' => 'excel_export',
        'filters' => $filters,
        'file' => basename($filePath)
    ]);
    
    // Send file as download
    $filename = basename($filePath);
    
    // Set headers for file download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Output file contents
    readfile($filePath);
    
    // Optionally delete the file after download (uncomment if needed)
    // unlink($filePath);
    
    exit;
    
} catch (Exception $e) {
    error_log("Delegations Export API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to export delegations');
}

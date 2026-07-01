<?php
/**
 * Installation Export API
 * 
 * GET /api/installation/export.php
 * Exports installation data to Excel/CSV format
 * 
 * Query Parameters:
 * - status: Filter by installation status
 * - date_from: Filter by date range start (YYYY-MM-DD)
 * - date_to: Filter by date range end (YYYY-MM-DD)
 * - format: Export format (csv, json) - default: csv
 * 
 * Response:
 * - File download (CSV/Excel) or JSON data
 * 
 * Requirements: 16.4
 * - 16.4: Generate an Excel file containing all installation records with complete details
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/InstallationExportService.php';
require_once __DIR__ . '/../../models/Installation.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authenticated user
    $user = $authMiddleware->requireAuth();
    
    // Only allow GET method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::methodNotAllowed(['GET']);
    }
    
    // Verify user has permission to export installation data
    if (!canExportInstallations($user)) {
        ApiResponse::forbidden('Access denied. You do not have permission to export installation data.');
    }
    
    // Parse filter parameters
    $filters = [];
    
    // Status filter
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $validStatuses = Installation::getValidStatuses();
        if (in_array($_GET['status'], $validStatuses)) {
            $filters['status'] = $_GET['status'];
        }
    }
    
    // Date range filters
    if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
        $filters['date_from'] = $_GET['date_from'];
    }
    
    if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
        $filters['date_to'] = $_GET['date_to'];
    }
    
    // Export format
    $format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';
    if (!in_array($format, ['csv', 'json', 'xlsx'])) {
        $format = 'csv';
    }
    
    // Apply user-based restrictions
    // Contractors can only export their own company's installations
    $companyType = strtoupper($user['company_type'] ?? '');
    if ($companyType === 'CONTRACTOR') {
        $filters['company_id'] = $user['company_id'];
    }
    
    // Initialize service and generate export
    $exportService = new InstallationExportService();
    $result = $exportService->exportToExcel($filters, $format);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/export', 'GET', [
        'filters' => $filters,
        'format' => $format,
        'success' => $result['success']
    ]);
    
    if (!$result['success']) {
        ApiResponse::error($result['code'] ?? 'EXPORT_ERROR', $result['message'], 400);
    }
    
    // If JSON format requested, return as API response
    if ($format === 'json') {
        ApiResponse::success([
            'export' => json_decode($result['data']['content'], true),
            'count' => $result['data']['count'],
            'generated_at' => $result['data']['generated_at']
        ], 'Export generated successfully');
    }
    
    // For CSV/Excel, send as file download
    $filename = $result['data']['filename'] ?? 'installations_export.csv';
    $mimeType = $result['data']['mime_type'] ?? 'text/csv';
    $content = $result['data']['content'] ?? '';
    
    // Set headers for file download
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $content;
    exit;
    
} catch (Exception $e) {
    error_log("Installation Export API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while generating export');
}

/**
 * Check if user can export installation data
 * 
 * @param array $user User data
 * @return bool True if user has permission
 */
function canExportInstallations($user) {
    // System admin can export all
    if (isset($user['is_system_admin']) && $user['is_system_admin']) {
        return true;
    }
    
    // Check role-based permissions
    $roleId = $user['role_id'] ?? 0;
    
    // Admin roles (typically role_id 1 or 2) can export
    if ($roleId <= 2) {
        return true;
    }
    
    // Check company_type
    $companyType = strtoupper($user['company_type'] ?? '');
    
    // ADV users can export
    if ($companyType === 'ADV') {
        return true;
    }
    
    // Contractor admins/managers can export their own data
    if ($companyType === 'CONTRACTOR') {
        return true;
    }
    
    return false;
}

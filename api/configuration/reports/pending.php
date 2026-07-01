<?php
/**
 * Pending Configuration Report API Endpoint
 * GET /api/configuration/reports/pending.php - Get pending configuration report
 * 
 * Query Parameters (GET):
 * - search: Search in router serial number or product name
 * - export: Export format (csv or json) - if set, returns downloadable file
 * 
 * Response includes:
 * - data: Array of routers without IP configuration
 * - summary: Summary statistics (total_pending, in_progress, waiting)
 * - filters_applied: Applied filters
 * - generated_at: Report generation timestamp
 * 
 * **Validates: Requirements 8.3, 8.4**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/ConfigurationReportService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access for report viewing
    $user = $authMiddleware->requireAdvUser();
    
    $reportService = new ConfigurationReportService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($reportService, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET']);
    }
    
} catch (Exception $e) {
    error_log("Pending Configuration Report API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - Get pending configuration report
 * 
 * Requirements: 8.3 - List all routers without IP configuration
 * Requirements: 8.4 - Generate output in Excel/CSV format with all relevant fields
 */
function handleGetRequest($reportService, $authMiddleware, $user) {
    // Get query parameters
    $filters = [];
    
    if (!empty($_GET['search'])) {
        $filters['search'] = trim($_GET['search']);
    }
    
    // Get report data
    $reportData = $reportService->getPendingReport($filters);
    
    // Check if export is requested
    $exportFormat = isset($_GET['export']) ? strtolower(trim($_GET['export'])) : null;
    
    if ($exportFormat === 'csv') {
        // Export as CSV
        $csvContent = $reportService->exportPendingReportToCSV($reportData);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="pending_configuration_report_' . date('Y-m-d_His') . '.csv"');
        header('Content-Length: ' . strlen($csvContent));
        
        echo $csvContent;
        exit;
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/reports/pending', 'GET', [
        'filters' => $filters,
        'export' => $exportFormat
    ]);
    
    // Format response
    $response = [
        'data' => formatPendingRecords($reportData['data']),
        'summary' => $reportData['summary'],
        'filters_applied' => $reportData['filters_applied'],
        'generated_at' => $reportData['generated_at']
    ];
    
    ApiResponse::success($response, 'Pending configuration report generated successfully');
}

/**
 * Format pending configuration records for API response
 * 
 * @param array $records Raw pending records
 * @return array Formatted records
 */
function formatPendingRecords($records) {
    return array_map(function($record) {
        $lock = null;
        
        if (!empty($record['lock_id'])) {
            $lock = [
                'lock_id' => (int)$record['lock_id'],
                'locked_by' => [
                    'id' => isset($record['locked_by']) ? (int)$record['locked_by'] : null,
                    'username' => $record['locked_by_username'] ?? null
                ],
                'locked_at' => $record['locked_at'] ?? null,
                'expires_at' => $record['expires_at'] ?? null
            ];
        }
        
        $configurationStatus = !empty($record['lock_id']) ? 'in_progress' : 'waiting';
        
        return [
            'serial_number' => $record['serial_number'],
            'asset_id' => isset($record['asset_id']) ? (int)$record['asset_id'] : null,
            'product' => [
                'id' => isset($record['product_id']) ? (int)$record['product_id'] : null,
                'name' => $record['product_name'] ?? null
            ],
            'warehouse' => [
                'id' => isset($record['warehouse_id']) ? (int)$record['warehouse_id'] : null,
                'name' => $record['warehouse_name'] ?? null
            ],
            'asset_status' => $record['asset_status'] ?? null,
            'configuration_status' => $configurationStatus,
            'configuration_status_label' => $configurationStatus === 'in_progress' ? 'In Progress' : 'Waiting',
            'active_lock' => $lock,
            'first_seen' => $record['first_seen'] ?? $record['asset_created_at'] ?? null
        ];
    }, $records);
}

<?php
/**
 * Configuration Report API Endpoint
 * GET /api/configuration/reports/configurations.php - Get configuration report
 * 
 * Query Parameters (GET):
 * - date_from: Start date filter (Y-m-d format)
 * - date_to: End date filter (Y-m-d format)
 * - configured_by: Filter by user ID who configured
 * - search: Search in router serial number or IP addresses
 * - export: Export format (csv or json) - if set, returns downloadable file
 * 
 * Response includes:
 * - data: Array of configuration records with router serial, IP details, date, user
 * - total: Total number of records
 * - filters_applied: Applied filters
 * - generated_at: Report generation timestamp
 * 
 * **Validates: Requirements 8.1, 8.4**
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
    error_log("Configuration Report API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - Get configuration report
 * 
 * Requirements: 8.1 - Include router serial number, IP_Master details, configuration date, and configured by user
 * Requirements: 8.4 - Generate output in Excel/CSV format with all relevant fields
 */
function handleGetRequest($reportService, $authMiddleware, $user) {
    // Get query parameters
    $filters = [];
    
    if (!empty($_GET['date_from'])) {
        $dateFrom = trim($_GET['date_from']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            ApiResponse::validationError(
                ['date_from' => ['Invalid date format. Expected: Y-m-d (e.g., 2024-12-30)']],
                'Invalid date format'
            );
        }
        $filters['date_from'] = $dateFrom;
    }
    
    if (!empty($_GET['date_to'])) {
        $dateTo = trim($_GET['date_to']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            ApiResponse::validationError(
                ['date_to' => ['Invalid date format. Expected: Y-m-d (e.g., 2024-12-30)']],
                'Invalid date format'
            );
        }
        $filters['date_to'] = $dateTo;
    }
    
    if (!empty($_GET['configured_by'])) {
        $filters['configured_by'] = (int)$_GET['configured_by'];
    }
    
    if (!empty($_GET['search'])) {
        $filters['search'] = trim($_GET['search']);
    }
    
    // Get report data
    $reportData = $reportService->getConfigurationReport($filters);
    
    // Check if export is requested
    $exportFormat = isset($_GET['export']) ? strtolower(trim($_GET['export'])) : null;
    
    if ($exportFormat === 'csv') {
        // Export as CSV
        $csvContent = $reportService->exportConfigurationReportToCSV($reportData);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="configuration_report_' . date('Y-m-d_His') . '.csv"');
        header('Content-Length: ' . strlen($csvContent));
        
        echo $csvContent;
        exit;
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/reports/configurations', 'GET', [
        'filters' => $filters,
        'export' => $exportFormat
    ]);
    
    // Format response
    $response = [
        'data' => formatConfigurationRecords($reportData['data']),
        'total' => $reportData['total'],
        'filters_applied' => $reportData['filters_applied'],
        'generated_at' => $reportData['generated_at']
    ];
    
    ApiResponse::success($response, 'Configuration report generated successfully');
}

/**
 * Format configuration records for API response
 * 
 * @param array $records Raw configuration records
 * @return array Formatted records
 */
function formatConfigurationRecords($records) {
    return array_map(function($record) {
        return [
            'binding_id' => (int)$record['binding_id'],
            'router_serial_number' => $record['router_serial_number'],
            'ip_details' => [
                'ip_master_id' => (int)$record['ip_master_id'],
                'network_ip' => $record['network_ip'],
                'router_ip' => $record['router_ip'],
                'site_ip' => $record['site_ip'],
                'subnet_mask' => $record['subnet_mask']
            ],
            'configured_at' => $record['configured_at'],
            'configured_by' => [
                'id' => isset($record['configured_by_id']) ? (int)$record['configured_by_id'] : null,
                'username' => $record['configured_by_username'] ?? null,
                'full_name' => trim(($record['configured_by_first_name'] ?? '') . ' ' . ($record['configured_by_last_name'] ?? '')) ?: null
            ],
            'notes' => $record['notes'] ?? null
        ];
    }, $records);
}

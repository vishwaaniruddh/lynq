<?php
/**
 * IP Usage Report API Endpoint
 * GET /api/configuration/reports/ip_usage.php - Get IP usage report
 * 
 * Query Parameters (GET):
 * - status: Filter by IP status (available, locked, configured)
 * - search: Search in IP addresses
 * - export: Export format (csv or json) - if set, returns downloadable file
 * 
 * Response includes:
 * - data: Array of IP_Master records with status and bound router if applicable
 * - summary: Summary statistics (total, available, locked, configured)
 * - filters_applied: Applied filters
 * - generated_at: Report generation timestamp
 * 
 * **Validates: Requirements 8.2, 8.4**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/ConfigurationReportService.php';
require_once __DIR__ . '/../../../models/IPMaster.php';

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
    error_log("IP Usage Report API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - Get IP usage report
 * 
 * Requirements: 8.2 - Show all IP_Master records with their current status and bound router if applicable
 * Requirements: 8.4 - Generate output in Excel/CSV format with all relevant fields
 */
function handleGetRequest($reportService, $authMiddleware, $user) {
    // Get query parameters
    $filters = [];
    
    if (!empty($_GET['status'])) {
        $status = trim($_GET['status']);
        $validStatuses = [IPMaster::STATUS_AVAILABLE, IPMaster::STATUS_LOCKED, IPMaster::STATUS_CONFIGURED];
        
        if (!in_array($status, $validStatuses)) {
            ApiResponse::validationError(
                ['status' => ['Invalid status. Valid values: ' . implode(', ', $validStatuses)]],
                'Invalid status filter'
            );
        }
        $filters['status'] = $status;
    }
    
    if (!empty($_GET['search'])) {
        $filters['search'] = trim($_GET['search']);
    }
    
    // Get report data
    $reportData = $reportService->getIPUsageReport($filters);
    
    // Check if export is requested
    $exportFormat = isset($_GET['export']) ? strtolower(trim($_GET['export'])) : null;
    
    if ($exportFormat === 'csv') {
        // Export as CSV
        $csvContent = $reportService->exportIPUsageReportToCSV($reportData);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="ip_usage_report_' . date('Y-m-d_His') . '.csv"');
        header('Content-Length: ' . strlen($csvContent));
        
        echo $csvContent;
        exit;
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/reports/ip_usage', 'GET', [
        'filters' => $filters,
        'export' => $exportFormat
    ]);
    
    // Format response
    $response = [
        'data' => formatIPUsageRecords($reportData['data']),
        'summary' => $reportData['summary'],
        'filters_applied' => $reportData['filters_applied'],
        'generated_at' => $reportData['generated_at']
    ];
    
    ApiResponse::success($response, 'IP usage report generated successfully');
}

/**
 * Format IP usage records for API response
 * 
 * @param array $records Raw IP usage records
 * @return array Formatted records
 */
function formatIPUsageRecords($records) {
    return array_map(function($record) {
        $binding = null;
        
        if (!empty($record['binding_id'])) {
            $binding = [
                'binding_id' => (int)$record['binding_id'],
                'router_serial_number' => $record['router_serial_number'],
                'configured_at' => $record['configured_at'],
                'configured_by' => [
                    'id' => isset($record['configured_by_id']) ? (int)$record['configured_by_id'] : null,
                    'username' => $record['configured_by_username'] ?? null
                ],
                'notes' => $record['binding_notes'] ?? null
            ];
        }
        
        return [
            'ip_master_id' => (int)$record['ip_master_id'],
            'network_ip' => $record['network_ip'],
            'router_ip' => $record['router_ip'],
            'site_ip' => $record['site_ip'],
            'subnet_mask' => $record['subnet_mask'],
            'status' => $record['status'],
            'status_label' => getStatusLabel($record['status']),
            'created_at' => $record['ip_created_at'],
            'binding' => $binding
        ];
    }, $records);
}

/**
 * Get human-readable status label
 * 
 * @param string $status Status code
 * @return string Status label
 */
function getStatusLabel($status) {
    switch ($status) {
        case IPMaster::STATUS_AVAILABLE:
            return 'Available';
        case IPMaster::STATUS_LOCKED:
            return 'Locked (In Progress)';
        case IPMaster::STATUS_CONFIGURED:
            return 'Configured';
        default:
            return ucfirst($status);
    }
}

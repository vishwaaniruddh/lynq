<?php
/**
 * Configuration Audit Log API Endpoint
 * GET /api/configuration/audit_log.php - List audit log entries with pagination, search, filters
 * 
 * Query Parameters (GET):
 * - action_type: Filter by action type (optional)
 * - user_id: Filter by user ID (optional)
 * - router_serial_number: Filter by router serial number (optional)
 * - ip_master_id: Filter by IP_Master ID (optional)
 * - date_from: Filter by start date (Y-m-d format) (optional)
 * - date_to: Filter by end date (Y-m-d format) (optional)
 * - search: General search term (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * - export: Set to 1 for export mode (returns all filtered records)
 * 
 * **Validates: Requirements 9.2, 9.4**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../repositories/ConfigurationAuditLogRepository.php';
require_once __DIR__ . '/../../models/ConfigurationAuditLog.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access for audit log viewing
    $user = $authMiddleware->requireAdvUser();
    
    $auditLogRepository = new ConfigurationAuditLogRepository();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // GET: List audit log entries with filters
        handleGetRequest($auditLogRepository, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET']);
    }
    
} catch (Exception $e) {
    error_log("Audit Log API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - List audit log entries
 * 
 * Requirements: 9.2 - View complete history with filtering
 * Requirements: 9.4 - Generate audit reports
 */
function handleGetRequest($auditLogRepository, $authMiddleware, $user) {
    // Get query parameters
    $actionType = isset($_GET['action_type']) ? trim($_GET['action_type']) : null;
    $userId = isset($_GET['user_id']) ? trim($_GET['user_id']) : null;
    $routerSerialNumber = isset($_GET['router_serial_number']) ? trim($_GET['router_serial_number']) : null;
    $ipMasterId = isset($_GET['ip_master_id']) ? trim($_GET['ip_master_id']) : null;
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $export = isset($_GET['export']) && $_GET['export'] == '1';
    
    // Validate action_type if provided
    if ($actionType !== null && $actionType !== '') {
        $validActionTypes = ConfigurationAuditLog::getActionTypes();
        if (!in_array($actionType, $validActionTypes)) {
            ApiResponse::validationError(
                ['action_type' => ['Invalid action type. Valid values: ' . implode(', ', $validActionTypes)]],
                'Invalid action type filter'
            );
        }
    }
    
    // Validate date formats if provided
    if ($dateFrom !== null && $dateFrom !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            ApiResponse::validationError(
                ['date_from' => ['Invalid date format. Expected: Y-m-d (e.g., 2024-12-30)']],
                'Invalid date format'
            );
        }
    }
    
    if ($dateTo !== null && $dateTo !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            ApiResponse::validationError(
                ['date_to' => ['Invalid date format. Expected: Y-m-d (e.g., 2024-12-30)']],
                'Invalid date format'
            );
        }
    }
    
    // Build filters
    $filters = [
        'page' => $page,
        'limit' => $limit
    ];
    
    if ($actionType !== null && $actionType !== '') {
        $filters['action_type'] = $actionType;
    }
    
    if ($userId !== null && $userId !== '') {
        $filters['user_id'] = (int)$userId;
    }
    
    if ($routerSerialNumber !== null && $routerSerialNumber !== '') {
        $filters['router_serial_number'] = $routerSerialNumber;
    }
    
    if ($ipMasterId !== null && $ipMasterId !== '') {
        $filters['ip_master_id'] = (int)$ipMasterId;
    }
    
    if ($dateFrom !== null && $dateFrom !== '') {
        $filters['date_from'] = $dateFrom;
    }
    
    if ($dateTo !== null && $dateTo !== '') {
        $filters['date_to'] = $dateTo;
    }
    
    if ($search !== null && $search !== '') {
        $filters['search'] = $search;
    }

    
    // Handle export mode
    if ($export) {
        $auditLogs = $auditLogRepository->findAllForExport($filters);
        
        // Log API access
        $authMiddleware->logApiAccess($user['id'], '/api/configuration/audit_log', 'GET', [
            'action' => 'export',
            'filters' => $filters
        ]);
        
        // Format response for export
        $formattedData = array_map(function($log) {
            return formatAuditLogEntry($log);
        }, $auditLogs);
        
        ApiResponse::success([
            'audit_logs' => $formattedData,
            'total' => count($formattedData)
        ], 'Audit log entries exported successfully');
        return;
    }
    
    // Get paginated list
    $result = $auditLogRepository->getHistory($filters);
    
    // Get stats with same filters (excluding action_type filter for stats)
    $statsFilters = [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'search' => $search,
        'router_serial_number' => $routerSerialNumber
    ];
    $stats = $auditLogRepository->getCountByActionTypeFiltered($statsFilters);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/audit_log', 'GET', [
        'action_type' => $actionType,
        'user_id' => $userId,
        'router_serial_number' => $routerSerialNumber,
        'ip_master_id' => $ipMasterId,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'search' => $search,
        'page' => $page
    ]);
    
    // Format response
    $formattedData = array_map(function($log) {
        return formatAuditLogEntry($log);
    }, $result['data']);
    
    // Get action type options for filtering
    $actionTypes = [];
    foreach (ConfigurationAuditLog::getActionTypes() as $type) {
        $actionTypes[] = [
            'value' => $type,
            'label' => ConfigurationAuditLog::getActionLabel($type)
        ];
    }
    
    ApiResponse::success([
        'audit_logs' => $formattedData,
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ],
        'stats' => $stats,
        'filter_options' => [
            'action_types' => $actionTypes
        ]
    ], 'Audit log entries retrieved successfully');
}

/**
 * Format audit log entry for API response
 * 
 * @param array $log Raw audit log entry
 * @return array Formatted audit log entry
 */
function formatAuditLogEntry($log) {
    return [
        'id' => (int)$log['id'],
        'action_type' => $log['action_type'],
        'action_label' => $log['action_label'] ?? ConfigurationAuditLog::getActionLabel($log['action_type']),
        'user_id' => isset($log['user_id']) ? (int)$log['user_id'] : null,
        'username' => $log['username'] ?? null,
        'router_serial_number' => $log['router_serial_number'] ?? null,
        'ip_master_id' => isset($log['ip_master_id']) ? (int)$log['ip_master_id'] : null,
        'ip_details' => [
            'network_ip' => $log['network_ip'] ?? null,
            'router_ip' => $log['router_ip'] ?? null,
            'site_ip' => $log['site_ip'] ?? null,
            'subnet_mask' => $log['subnet_mask'] ?? null
        ],
        'details' => $log['details_decoded'] ?? [],
        'created_at' => $log['created_at'] ?? null
    ];
}

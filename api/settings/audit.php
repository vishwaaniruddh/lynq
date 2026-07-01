<?php
/**
 * Settings API - Get Setting Audit Trail
 * GET /api/settings/audit.php
 * 
 * Retrieves audit trail for settings with filtering and pagination
 * 
 * Query Parameters:
 * - key: Setting key (optional, if not provided returns all audit entries)
 * - start_date: Start date filter (YYYY-MM-DD format, optional)
 * - end_date: End date filter (YYYY-MM-DD format, optional)
 * - user_id: User ID filter (optional)
 * - category: Category filter (optional)
 * - action: Action filter (CREATE, UPDATE, DELETE, RESET, optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 50, max: 200)
 * 
 * Response: { success: bool, data: { audit_entries: [], pagination: {} } }
 * 
 * **Validates: Requirements 5.2, 5.3**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication and system.manage permission with session re-verification
    $user = $authMiddleware->requirePermission('system.manage', true);
    
    // Get query parameters
    $key = isset($_GET['key']) ? trim($_GET['key']) : null;
    $startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : null;
    $endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : null;
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $category = isset($_GET['category']) ? trim($_GET['category']) : null;
    $action = isset($_GET['action']) ? trim($_GET['action']) : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    // Validate date formats if provided
    if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        ApiResponse::validationError(['start_date' => 'Invalid date format. Use YYYY-MM-DD'], 'Invalid date format');
    }
    
    if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        ApiResponse::validationError(['end_date' => 'Invalid date format. Use YYYY-MM-DD'], 'Invalid date format');
    }
    
    // Validate action if provided
    $validActions = ['CREATE', 'UPDATE', 'DELETE', 'RESET'];
    if ($action && !in_array(strtoupper($action), $validActions)) {
        ApiResponse::validationError(['action' => 'Invalid action. Must be one of: ' . implode(', ', $validActions)], 'Invalid action');
    }
    
    $settingsService = new SettingsService();
    
    // Build filters array
    $filters = [];
    if ($startDate) $filters['start_date'] = $startDate;
    if ($endDate) $filters['end_date'] = $endDate;
    if ($userId) $filters['user_id'] = $userId;
    if ($category) $filters['category'] = $category;
    if ($action) $filters['action'] = strtoupper($action);
    
    // Get audit trail
    if ($key) {
        // Get audit trail for specific setting
        $auditEntries = $settingsService->getSettingAuditTrail($key, $filters, $limit, $offset);
        $totalCount = $settingsService->getAuditTrailCount($key, $filters);
    } else {
        // Get all audit trail
        $auditEntries = $settingsService->getAllAuditTrail($filters, $limit, $offset);
        $totalCount = $settingsService->getAuditTrailCount(null, $filters);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/settings/audit', 'GET', [
        'key' => $key,
        'filters' => $filters,
        'page' => $page,
        'limit' => $limit
    ]);
    
    ApiResponse::success([
        'audit_entries' => $auditEntries,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ],
        'filters_applied' => $filters
    ], 'Audit trail retrieved successfully');
    
} catch (Exception $e) {
    error_log("Settings Audit API Error: " . $e->getMessage());
    
    // Check if it's a permission error
    if (strpos($e->getMessage(), 'permission') !== false || strpos($e->getMessage(), 'Authentication') !== false) {
        // Error already handled by middleware
        exit;
    }
    
    ApiResponse::serverError('Failed to retrieve audit trail');
}
<?php
/**
 * Settings Audit Integrity API Endpoint
 * Provides integrity verification and forensic analysis for settings audit trail
 * 
 * Endpoints:
 * GET /api/settings/integrity/verify - Verify integrity of audit entries
 * GET /api/settings/integrity/report - Get comprehensive integrity report
 * GET /api/settings/integrity/forensic - Get forensic audit trail
 * GET /api/settings/integrity/security-summary - Get security-focused summary
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';

// Initialize required components
$response = new ApiResponse();
$sessionService = new SessionService();
$permissionEngine = new PermissionEngine();
$settingsService = new SettingsService();

try {
    // Authenticate user
    $user = $sessionService->getCurrentUser();
    if (!$user) {
        $response->unauthorized('Authentication required');
        exit;
    }
    
    // Check permissions - require system.manage permission
    if (!$permissionEngine->can($user['id'], 'system.manage')) {
        $response->forbidden('system.manage permission required');
        exit;
    }
    
    // Get request method and path
    $method = $_SERVER['REQUEST_METHOD'];
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    $pathParts = array_filter(explode('/', $pathInfo));
    
    // Route to appropriate handler
    switch ($method) {
        case 'GET':
            handleGetRequest($pathParts, $settingsService, $response);
            break;
            
        default:
            $response->methodNotAllowed(['GET']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Settings Integrity API Error: " . $e->getMessage());
    $response->serverError('An error occurred while processing the request');
}

/**
 * Handle GET requests for integrity operations
 */
function handleGetRequest($pathParts, $settingsService, $response) {
    $action = $pathParts[0] ?? 'verify';
    
    switch ($action) {
        case 'verify':
            handleVerifyIntegrity($settingsService, $response);
            break;
            
        case 'report':
            handleIntegrityReport($settingsService, $response);
            break;
            
        case 'forensic':
            handleForensicAuditTrail($settingsService, $response);
            break;
            
        case 'security-summary':
            handleSecuritySummary($settingsService, $response);
            break;
            
        default:
            $response->notFound('Invalid integrity endpoint');
            break;
    }
}

/**
 * Handle integrity verification request
 */
function handleVerifyIntegrity($settingsService, $response) {
    $auditIds = [];
    
    // Get specific audit IDs if provided
    if (isset($_GET['audit_ids'])) {
        $auditIds = array_map('intval', explode(',', $_GET['audit_ids']));
        $auditIds = array_filter($auditIds); // Remove invalid IDs
    }
    
    try {
        $verificationResult = $settingsService->verifyAuditIntegrity($auditIds);
        
        $response->success([
            'verification_result' => $verificationResult,
            'summary' => [
                'total_verified' => $verificationResult['total'],
                'valid_entries' => $verificationResult['valid'],
                'invalid_entries' => $verificationResult['invalid'],
                'integrity_percentage' => $verificationResult['total'] > 0 
                    ? round(($verificationResult['valid'] / $verificationResult['total']) * 100, 2) 
                    : 100
            ],
            'verification_timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log("Integrity verification error: " . $e->getMessage());
        $response->serverError('Failed to verify audit integrity');
    }
}

/**
 * Handle integrity report request
 */
function handleIntegrityReport($settingsService, $response) {
    $filters = [];
    
    // Parse filters from query parameters
    if (isset($_GET['start_date'])) {
        $filters['start_date'] = $_GET['start_date'];
    }
    
    if (isset($_GET['end_date'])) {
        $filters['end_date'] = $_GET['end_date'];
    }
    
    if (isset($_GET['user_id'])) {
        $filters['user_id'] = (int)$_GET['user_id'];
    }
    
    if (isset($_GET['category'])) {
        $filters['category'] = $_GET['category'];
    }
    
    try {
        $integrityReport = $settingsService->getAuditIntegrityReport($filters);
        
        $response->success([
            'integrity_report' => $integrityReport,
            'filters_applied' => $filters
        ]);
        
    } catch (Exception $e) {
        error_log("Integrity report error: " . $e->getMessage());
        $response->serverError('Failed to generate integrity report');
    }
}

/**
 * Handle forensic audit trail request
 */
function handleForensicAuditTrail($settingsService, $response) {
    $filters = [];
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Parse filters from query parameters
    $filterParams = [
        'start_date', 'end_date', 'user_id', 'category', 'action', 
        'setting_key', 'ip_address', 'session_id', 'integrity_status'
    ];
    
    foreach ($filterParams as $param) {
        if (isset($_GET[$param])) {
            if ($param === 'user_id') {
                $filters[$param] = (int)$_GET[$param];
            } else {
                $filters[$param] = $_GET[$param];
            }
        }
    }
    
    try {
        $forensicTrail = $settingsService->getForensicAuditTrail($filters, $limit, $offset);
        $totalCount = $settingsService->getAuditTrailCount(null, $filters);
        
        $response->success([
            'forensic_audit_trail' => $forensicTrail,
            'pagination' => [
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount
            ],
            'filters_applied' => $filters
        ]);
        
    } catch (Exception $e) {
        error_log("Forensic audit trail error: " . $e->getMessage());
        $response->serverError('Failed to retrieve forensic audit trail');
    }
}

/**
 * Handle security summary request
 */
function handleSecuritySummary($settingsService, $response) {
    $filters = [];
    
    // Parse filters from query parameters
    if (isset($_GET['start_date'])) {
        $filters['start_date'] = $_GET['start_date'];
    }
    
    if (isset($_GET['end_date'])) {
        $filters['end_date'] = $_GET['end_date'];
    }
    
    if (isset($_GET['category'])) {
        $filters['category'] = $_GET['category'];
    }
    
    try {
        $securitySummary = $settingsService->getAuditSecuritySummary($filters);
        
        $response->success([
            'security_summary' => $securitySummary,
            'filters_applied' => $filters,
            'analysis_timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log("Security summary error: " . $e->getMessage());
        $response->serverError('Failed to generate security summary');
    }
}
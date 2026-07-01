<?php
/**
 * IP Master Edit API Endpoint
 * PUT /api/configuration/ip_master_edit.php - Update IP_Master record
 * DELETE /api/configuration/ip_master_edit.php - Delete IP_Master record
 * POST /api/configuration/ip_master_edit.php - Update or Delete via action parameter
 * 
 * PUT/POST (action=update) Body (JSON):
 * - id: IP_Master ID (required)
 * - network_ip: Network IP address (optional)
 * - router_ip: Router IP address (optional)
 * - site_ip: Site IP address (optional)
 * - subnet_mask: Subnet mask (optional)
 * 
 * DELETE/POST (action=delete) Body (JSON):
 * - id: IP_Master ID (required)
 * 
 * **Validates: Requirements 1.4, 1.5**
 * - 1.4: Prevent editing configured IPs
 * - 1.5: Prevent deletion of configured/locked IPs
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/IPMasterService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access for all IP configuration operations
    $user = $authMiddleware->requireAdvUser();
    
    $ipMasterService = new IPMasterService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // PUT: Update IP_Master record
        handleUpdateRequest($ipMasterService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // DELETE: Delete IP_Master record
        handleDeleteRequest($ipMasterService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST: Handle action-based requests (for browsers that don't support PUT/DELETE)
        handlePostRequest($ipMasterService, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['PUT', 'DELETE', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("IP Master Edit API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle PUT request - Update IP_Master record
 * 
 * Requirements: 1.4 - Prevent editing configured IPs
 */
function handleUpdateRequest($ipMasterService, $authMiddleware, $user) {
    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        ApiResponse::validationError(
            ['body' => ['Request body is required']],
            'Invalid request'
        );
    }
    
    processUpdate($ipMasterService, $authMiddleware, $user, $input);
}

/**
 * Handle DELETE request - Delete IP_Master record
 * 
 * Requirements: 1.5 - Prevent deletion of configured/locked IPs
 */
function handleDeleteRequest($ipMasterService, $authMiddleware, $user) {
    // Get request body or query parameter
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Also check query parameter for ID
    if (empty($input['id']) && isset($_GET['id'])) {
        $input['id'] = $_GET['id'];
    }
    
    if (empty($input['id'])) {
        ApiResponse::validationError(
            ['id' => ['IP_Master ID is required']],
            'Validation failed'
        );
    }
    
    processDelete($ipMasterService, $authMiddleware, $user, $input);
}

/**
 * Handle POST request - Action-based update or delete
 */
function handlePostRequest($ipMasterService, $authMiddleware, $user) {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Fallback to form data if JSON is empty
    if (empty($input)) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'update':
            processUpdate($ipMasterService, $authMiddleware, $user, $input);
            break;
            
        case 'delete':
            processDelete($ipMasterService, $authMiddleware, $user, $input);
            break;
            
        default:
            ApiResponse::validationError(
                ['action' => ['Invalid or missing action. Valid actions: update, delete']],
                'Invalid action'
            );
    }
}

/**
 * Process update operation
 * 
 * Requirements: 1.4 - Prevent editing configured IPs
 */
function processUpdate($ipMasterService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['IP_Master ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    
    // Build update data from allowed fields
    $data = [];
    $allowedFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field]) && trim($input[$field]) !== '') {
            $data[$field] = trim($input[$field]);
        }
    }
    
    if (empty($data)) {
        ApiResponse::validationError(
            ['data' => ['No data provided for update. Provide at least one of: network_ip, router_ip, site_ip, subnet_mask']],
            'Validation failed'
        );
    }
    
    // Perform update
    $result = $ipMasterService->update($id, $data, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/ip_master_edit', 'PUT', [
        'action' => 'update',
        'id' => $id,
        'changes' => array_keys($data)
    ]);
    
    if ($result['success']) {
        // Format response
        $ipMaster = $result['data'];
        $formattedData = [
            'id' => (int)$ipMaster['id'],
            'network_ip' => $ipMaster['network_ip'],
            'router_ip' => $ipMaster['router_ip'],
            'site_ip' => $ipMaster['site_ip'],
            'subnet_mask' => $ipMaster['subnet_mask'],
            'status' => $ipMaster['status'],
            'created_by' => isset($ipMaster['created_by']) ? (int)$ipMaster['created_by'] : null,
            'created_at' => $ipMaster['created_at'] ?? null,
            'updated_at' => $ipMaster['updated_at'] ?? null
        ];
        
        ApiResponse::success($formattedData, $result['message']);
    } else {
        // Handle different error types
        switch ($result['code']) {
            case 'NOT_FOUND':
                ApiResponse::notFound($result['message']);
                break;
            case 'CONFIGURED_ERROR':
                // Requirement 1.4: Cannot edit configured IP
                ApiResponse::error('CONFIGURED_ERROR', $result['message'], 400);
                break;
            case 'DUPLICATE_ERROR':
                ApiResponse::error('DUPLICATE_ERROR', $result['message'], 409, $result['errors'] ?? null);
                break;
            default:
                ApiResponse::validationError($result['errors'] ?? [], $result['message']);
        }
    }
}

/**
 * Process delete operation
 * 
 * Requirements: 1.5 - Prevent deletion of configured/locked IPs
 */
function processDelete($ipMasterService, $authMiddleware, $user, $input) {
    // Validate required fields
    if (!isset($input['id']) || (int)$input['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['IP_Master ID is required']],
            'Validation failed'
        );
    }
    
    $id = (int)$input['id'];
    
    // Perform delete
    $result = $ipMasterService->delete($id, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/ip_master_edit', 'DELETE', [
        'action' => 'delete',
        'id' => $id
    ]);
    
    if ($result['success']) {
        ApiResponse::success(null, $result['message']);
    } else {
        // Handle different error types
        switch ($result['code']) {
            case 'NOT_FOUND':
                ApiResponse::notFound($result['message']);
                break;
            case 'CONFIGURED_ERROR':
                // Requirement 1.5: Cannot delete configured IP
                ApiResponse::error('CONFIGURED_ERROR', $result['message'], 400, [
                    'reason' => 'IP is currently configured to a router. Unbind it first.'
                ]);
                break;
            case 'LOCKED_ERROR':
                // Requirement 1.5: Cannot delete locked IP
                ApiResponse::error('LOCKED_ERROR', $result['message'], 400, [
                    'reason' => 'IP is currently locked for configuration. Wait for the lock to expire or be released.'
                ]);
                break;
            default:
                ApiResponse::serverError($result['message']);
        }
    }
}

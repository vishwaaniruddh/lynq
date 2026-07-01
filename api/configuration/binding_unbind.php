<?php
/**
 * Binding Unbind API Endpoint
 * POST /api/configuration/binding_unbind.php - Unbind IP from router
 * 
 * Unbinds an IP_Master from a router, making the IP available for reassignment.
 * Requires confirmation and records the unbind action in the audit log.
 * 
 * POST Body (JSON):
 * - binding_id: Binding ID to unbind (required)
 * - reason: Reason for unbinding (required)
 * - confirm: Confirmation flag (required, must be true)
 * 
 * Response:
 * - binding_id: Unbound binding ID
 * - router_serial_number: Router that was unbound
 * - ip_master_id: IP_Master that was released
 * - ip_master: Object with IP details (network_ip, router_ip, site_ip, subnet_mask)
 * - unbound_by: User ID who performed the unbind
 * - unbound_at: Unbind timestamp
 * - unbind_reason: Reason for unbinding
 * 
 * **Validates: Requirements 6.1, 6.2, 6.3, 6.4**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/BindingService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access for unbind operations
    $user = $authMiddleware->requireAdvUser();
    
    // Only POST method is allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::methodNotAllowed(['POST']);
    }
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Fallback to form data if JSON is empty
    if (empty($input)) {
        $input = $_POST;
    }
    
    // Validate required fields
    $errors = [];
    
    if (!isset($input['binding_id']) || !is_numeric($input['binding_id'])) {
        $errors['binding_id'] = ['Binding ID is required and must be numeric'];
    }
    
    if (!isset($input['reason']) || empty(trim($input['reason']))) {
        $errors['reason'] = ['Unbind reason is required'];
    }
    
    // Requirement 6.1: Require confirmation before proceeding
    if (!isset($input['confirm']) || $input['confirm'] !== true && $input['confirm'] !== 'true' && $input['confirm'] !== 1 && $input['confirm'] !== '1') {
        $errors['confirm'] = ['Confirmation is required to unbind. Set confirm to true.'];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    $bindingId = (int)$input['binding_id'];
    $reason = trim($input['reason']);
    
    // Get binding service
    $bindingService = new BindingService();
    
    // Validate unbind operation first
    $validation = $bindingService->validateUnbind($bindingId);
    if (!$validation['valid']) {
        $statusCode = 400;
        switch ($validation['code'] ?? 'ERROR') {
            case 'NOT_FOUND':
                $statusCode = 404;
                break;
            case 'NOT_ACTIVE':
                $statusCode = 409; // Conflict - binding already unbound
                break;
        }
        
        ApiResponse::error(
            $validation['code'] ?? 'VALIDATION_ERROR',
            $validation['message'],
            $statusCode,
            $validation['data'] ?? null
        );
    }
    
    // Get binding details before unbind for response
    $bindingBefore = $bindingService->getById($bindingId);
    
    // Perform unbind operation
    $result = $bindingService->unbind(
        $bindingId,
        $user['id'],
        $reason
    );
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/binding_unbind', 'POST', [
        'binding_id' => $bindingId,
        'router_serial_number' => $bindingBefore['router_serial_number'] ?? null,
        'ip_master_id' => $bindingBefore['ip_master_id'] ?? null
    ]);
    
    if ($result['success']) {
        // Build response with full details
        $responseData = [
            'binding_id' => $bindingId,
            'router_serial_number' => $result['data']['router_serial_number'],
            'ip_master_id' => $result['data']['ip_master_id'],
            'ip_master' => [
                'network_ip' => $bindingBefore['network_ip'] ?? null,
                'router_ip' => $bindingBefore['router_ip'] ?? null,
                'site_ip' => $bindingBefore['site_ip'] ?? null,
                'subnet_mask' => $bindingBefore['subnet_mask'] ?? null
            ],
            'unbound_by' => $user['id'],
            'unbound_at' => date('Y-m-d H:i:s'),
            'unbind_reason' => $reason
        ];
        
        ApiResponse::success($responseData, $result['message']);
    } else {
        // Map error codes to appropriate HTTP status codes
        $statusCode = 400;
        switch ($result['code'] ?? 'ERROR') {
            case 'NOT_FOUND':
                $statusCode = 404; // Not Found
                break;
            case 'NOT_ACTIVE':
                $statusCode = 409; // Conflict
                break;
            case 'VALIDATION_ERROR':
                $statusCode = 400; // Bad Request
                break;
            case 'UNBIND_ERROR':
                $statusCode = 500; // Server Error
                break;
            default:
                $statusCode = 400;
        }
        
        ApiResponse::error(
            $result['code'] ?? 'ERROR',
            $result['message'],
            $statusCode,
            $result['data'] ?? null
        );
    }
    
} catch (Exception $e) {
    error_log("Binding Unbind API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to unbind IP from router');
}

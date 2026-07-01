<?php
/**
 * Installation Form Save API
 * 
 * POST /api/installation/save.php
 * Saves installation form data (partial save)
 * 
 * Request Body:
 * - installation_id: (required) Installation ID
 * - data: (required) Form data object with installation fields
 * 
 * Response:
 * - success: boolean
 * - message: string
 * - data: Updated installation record (on success)
 * 
 * Requirements: 3.3, 3.4
 * - 3.3: Validate all required fields before submission
 * - 3.4: Create installation record with all captured data
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/InstallationService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authenticated user
    $user = $authMiddleware->requireAuth();
    
    // Only allow POST method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::methodNotAllowed(['POST']);
    }
    
    // Parse request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::error('INVALID_REQUEST', 'Invalid JSON request body', 400);
    }
    
    // Validate required fields
    $installationId = isset($input['installation_id']) ? (int)$input['installation_id'] : 0;
    
    if (!$installationId) {
        ApiResponse::validationError([
            ['field' => 'installation_id', 'message' => 'Installation ID is required']
        ], 'Validation failed');
    }
    
    // Get form data (exclude installation_id from data)
    $formData = $input;
    unset($formData['installation_id']);
    
    if (empty($formData)) {
        ApiResponse::validationError([
            ['field' => 'data', 'message' => 'Form data is required']
        ], 'Validation failed');
    }
    
    // Sanitize form data - only allow known installation fields
    $allowedFields = [
        // Vendor/Engineer Information
        'vendor_name', 'engineer_name', 'engineer_number',
        // Router Section
        'router_serial', 'router_make', 'router_model', 'router_fixed', 
        'router_fixed_remarks', 'router_fixed_snaps', 'router_status', 
        'router_status_remarks', 'router_status_snaps',
        // Adaptor Section
        'adaptor_installed', 'adaptor_snaps', 'adaptor_status', 
        'adaptor_status_remarks', 'adaptor_status_snaps',
        // LAN Cable Section
        'lan_cable_installed', 'lan_cable_install_remark', 'lan_cable_install_snap',
        'lan_cable_status', 'lan_cable_status_not_working_reasons', 
        'lan_cable_status_remark', 'lan_cable_status_snap',
        // Antenna Section
        'antenna_installed', 'antenna_remarks', 'antenna_snaps',
        'antenna_status', 'antenna_status_remarks', 'antenna_status_snaps',
        // GPS Section
        'gps_installed', 'gps_remarks', 'gps_snaps',
        'gps_status', 'gps_status_remarks', 'gps_status_snaps',
        // WiFi Section
        'wifi_installed', 'wifi_remarks', 'wifi_snaps',
        'wifi_status', 'wifi_status_remarks', 'wifi_status_snaps',
        // Airtel SIM Section
        'airtel_sim_installed', 'airtel_sim_remarks', 'airtel_sim_snaps',
        'airtel_sim_status', 'airtel_sim_status_remarks', 'airtel_sim_status_snaps',
        // Vodafone SIM Section
        'vodafone_sim_installed', 'vodafone_sim_remarks', 'vodafone_sim_snaps',
        'vodafone_sim_status', 'vodafone_sim_status_remarks', 'vodafone_sim_status_snaps',
        // JIO SIM Section
        'jio_sim_installed', 'jio_sim_remarks', 'jio_sim_snaps',
        'jio_sim_status', 'jio_sim_status_remarks', 'jio_sim_status_snaps',
        // Verification Section
        'signature_image', 'vendor_stamp',
        // ATM Working status
        'atm_working_1', 'atm_working_2', 'atm_working_3'
    ];
    
    $sanitizedData = array_intersect_key($formData, array_flip($allowedFields));
    
    // Initialize service and save installation data
    $installationService = new InstallationService();
    $result = $installationService->saveInstallationData($installationId, $sanitizedData, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/save', 'POST', [
        'installation_id' => $installationId,
        'fields_updated' => array_keys($sanitizedData),
        'success' => $result['success']
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message']);
    } else {
        $statusCode = 400;
        if ($result['code'] === 'NOT_FOUND') {
            $statusCode = 404;
        } elseif ($result['code'] === 'FORM_ACCESS_DENIED' || $result['code'] === 'INSTALLATION_LOCKED') {
            $statusCode = 403;
        } elseif ($result['code'] === 'VALIDATION_ERROR') {
            ApiResponse::validationError($result['errors'] ?? [], $result['message']);
            return;
        }
        
        ApiResponse::error($result['code'], $result['message'], $statusCode);
    }
    
} catch (Exception $e) {
    error_log("Installation Save API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while saving installation data');
}

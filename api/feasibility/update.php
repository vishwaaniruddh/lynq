<?php
/**
 * Feasibility Update API
 * 
 * Allows ADV users and contractor admin/manager to update feasibility check data.
 * 
 * Methods:
 * - PUT: Update feasibility check data
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../../services/FeasibilityService.php';

header('Content-Type: application/json');

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentication required']
    ]);
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$companyType = strtoupper($currentUser['company_type'] ?? '');
$roleName = strtolower($currentUser['role_name'] ?? '');

// Determine user permissions
$isADV = $companyType === 'ADV';
$isContractor = $companyType === 'CONTRACTOR';
$isContractorAdmin = $isContractor && in_array($roleName, ['contractor_admin', 'contractor admin']);
$isContractorManager = $isContractor && in_array($roleName, ['contractor_manager', 'contractor manager']);

// Check if user can edit
$canEdit = $isADV || $isContractorAdmin || $isContractorManager;

if (!$canEdit) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to edit feasibility data']
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'PUT') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Only PUT method is allowed']
    ]);
    exit;
}

// Get feasibility ID
$feasibilityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($feasibilityId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'INVALID_ID', 'message' => 'Valid feasibility ID is required']
    ]);
    exit;
}

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'INVALID_INPUT', 'message' => 'Invalid JSON input']
    ]);
    exit;
}

try {
    $feasibilityService = new FeasibilityService();
    
    // Get existing feasibility to verify access
    $feasibility = $feasibilityService->getFeasibilityCheck($feasibilityId);
    
    if (!$feasibility) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => ['code' => 'NOT_FOUND', 'message' => 'Feasibility check not found']
        ]);
        exit;
    }
    
    // For contractors, verify they have access to this feasibility
    if ($isContractor) {
        $contractorId = $currentUser['company_id'] ?? 0;
        if (($feasibility['contractor_id'] ?? 0) != $contractorId) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to edit this feasibility check']
            ]);
            exit;
        }
    }
    
    // Define allowed fields for update
    $allowedFields = [
        'no_of_atm', 'atm_id_1', 'atm_1_status', 'atm_id_2', 'atm_2_status', 'atm_id_3', 'atm_3_status',
        'operator', 'signal_status', 'operator_2', 'signal_status_2', 'backroom_network_remark',
        'ups_available', 'no_of_ups', 'ups_battery_backup', 'ups_working_1', 'ups_working_2', 'ups_working_3',
        'power_socket_availability', 'power_socket_availability_ups',
        'earthing', 'earthing_voltage', 'power_fluctuation_en', 'power_fluctuation_pe', 'power_fluctuation_pn',
        'frequent_power_cut', 'frequent_power_cut_from', 'frequent_power_cut_to', 'frequent_power_cut_remark',
        'em_lock_available', 'em_lock_password', 'password_received',
        'backroom_key_name', 'backroom_key_number', 'backroom_key_status',
        'router_position', 'router_antenna_position', 'antenna_routing_detail',
        'nearest_shop_name', 'nearest_shop_number', 'nearest_shop_distance',
        'backroom_disturbing_material', 'backroom_disturbing_material_remark',
        'remarks'
    ];
    
    // Filter input to only allowed fields
    $updateData = [];
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $input)) {
            $updateData[$field] = $input[$field];
        }
    }
    
    if (empty($updateData)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => ['code' => 'NO_DATA', 'message' => 'No valid fields to update']
        ]);
        exit;
    }
    
    // Add updated_by and updated_at
    $updateData['updated_by'] = $currentUser['id'];
    $updateData['updated_at'] = date('Y-m-d H:i:s');
    
    // Update the feasibility check
    $result = $feasibilityService->updateFeasibility($feasibilityId, $updateData);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Feasibility check updated successfully',
            'data' => ['id' => $feasibilityId]
        ]);
    } else {
        throw new Exception('Failed to update feasibility check');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]
    ]);
}

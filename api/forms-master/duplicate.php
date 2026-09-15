<?php
/**
 * Forms Master API - Duplicate Form
 * POST /api/forms-master/duplicate.php
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/CustomFormService.php';

ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed(['POST']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $currentUser = $authMiddleware->requireAdvUser();
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['id'])) {
        ApiResponse::validationError(['id' => 'Source Form ID is required']);
    }
    
    $id = (int)$input['id'];
    $overrides = [];
    if (!empty($input['form_name'])) $overrides['form_name'] = trim($input['form_name']);
    if (!empty($input['purpose'])) $overrides['purpose'] = trim($input['purpose']);
    if (array_key_exists('project_id', $input)) {
        $overrides['project_id'] = !empty($input['project_id']) ? (int)$input['project_id'] : null;
    } elseif (array_key_exists('customer_id', $input)) {
        $overrides['project_id'] = !empty($input['customer_id']) ? (int)$input['customer_id'] : null;
    }
    
    $service = new CustomFormService();
    $result = $service->duplicate($id, $overrides, $currentUser['id']);
    
    if (!$result['success']) {
        ApiResponse::error($result['code'] ?? 'DUPLICATE_ERROR', $result['message'], 400);
    }
    
    ApiResponse::success($result['data'], $result['message'] ?? 'Form duplicated successfully', 201);
} catch (Exception $e) {
    error_log("Forms Master Duplicate API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to duplicate form: ' . $e->getMessage());
}

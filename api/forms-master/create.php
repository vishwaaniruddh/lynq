<?php
/**
 * Forms Master API - Create Form
 * POST /api/forms-master/create.php
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
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    $service = new CustomFormService();
    $result = $service->create($input, $currentUser['id'], $currentUser['company_id'] ?? null);
    
    if (!$result['success']) {
        if (($result['code'] ?? '') === 'VALIDATION_ERROR') {
            ApiResponse::validationError($result['errors'] ?? [], $result['message']);
        }
        ApiResponse::error($result['code'] ?? 'CREATE_ERROR', $result['message'], 400);
    }
    
    ApiResponse::success($result['data'], $result['message'] ?? 'Form created successfully', 201);
} catch (Exception $e) {
    error_log("Forms Master Create API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to create form: ' . $e->getMessage());
}

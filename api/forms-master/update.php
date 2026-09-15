<?php
/**
 * Forms Master API - Update Form
 * POST /api/forms-master/update.php
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
        ApiResponse::validationError(['id' => 'Form ID is required']);
    }
    
    $id = (int)$input['id'];
    $service = new CustomFormService();
    $result = $service->update($id, $input, $currentUser['id']);
    
    if (!$result['success']) {
        if (($result['code'] ?? '') === 'VALIDATION_ERROR') {
            ApiResponse::validationError($result['errors'] ?? [], $result['message']);
        } elseif (($result['code'] ?? '') === 'NOT_FOUND') {
            ApiResponse::notFound($result['message']);
        }
        ApiResponse::error($result['code'] ?? 'UPDATE_ERROR', $result['message'], 400);
    }
    
    ApiResponse::success($result['data'], $result['message'] ?? 'Form updated successfully');
} catch (Exception $e) {
    error_log("Forms Master Update API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to update form: ' . $e->getMessage());
}

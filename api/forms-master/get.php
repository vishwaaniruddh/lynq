<?php
/**
 * Forms Master API - Get Form by ID with Fields
 * GET /api/forms-master/get.php?id=1
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/CustomFormService.php';

ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $currentUser = $authMiddleware->requireAdvUser();
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        ApiResponse::validationError(['id' => 'Valid Form ID is required']);
    }
    
    $service = new CustomFormService();
    $result = $service->get($id);
    
    if (!$result['success']) {
        if (($result['code'] ?? '') === 'NOT_FOUND') {
            ApiResponse::notFound($result['message']);
        }
        ApiResponse::error($result['code'] ?? 'GET_ERROR', $result['message'], 400);
    }
    
    ApiResponse::success($result['data'], 'Form retrieved successfully');
} catch (Exception $e) {
    error_log("Forms Master Get API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve form: ' . $e->getMessage());
}

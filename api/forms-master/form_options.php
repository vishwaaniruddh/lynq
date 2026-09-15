<?php
/**
 * Forms Master API - Form Options & Dropdown Lookups
 * GET /api/forms-master/form_options.php
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
    
    $service = new CustomFormService();
    $result = $service->getFormOptions();
    
    ApiResponse::success($result['data'], 'Form options retrieved successfully');
} catch (Exception $e) {
    error_log("Forms Master Options API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve options: ' . $e->getMessage());
}

<?php
/**
 * Forms Master API - Delete Form
 * POST /api/forms-master/delete.php
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
    $id = !empty($input['id']) ? (int)$input['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
    
    if ($id <= 0) {
        ApiResponse::validationError(['id' => 'Valid Form ID is required']);
    }
    
    $service = new CustomFormService();
    $result = $service->delete($id, $currentUser['id']);
    
    if (!$result['success']) {
        if (($result['code'] ?? '') === 'NOT_FOUND') {
            ApiResponse::notFound($result['message']);
        }
        ApiResponse::error($result['code'] ?? 'DELETE_ERROR', $result['message'], 400);
    }
    
    ApiResponse::success(null, $result['message'] ?? 'Form deleted successfully');
} catch (Exception $e) {
    error_log("Forms Master Delete API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to delete form: ' . $e->getMessage());
}

<?php
/**
 * Forms Master API - List Forms
 * GET /api/forms-master/list.php
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
    
    $filters = [
        'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
        'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 10,
        'search' => $_GET['search'] ?? null,
        'purpose' => $_GET['purpose'] ?? null,
        'project_id' => $_GET['project_id'] ?? ($_GET['customer_id'] ?? null),
        'status' => $_GET['status'] ?? null,
        'orderBy' => $_GET['orderBy'] ?? 'id',
        'orderDir' => $_GET['orderDir'] ?? 'DESC'
    ];
    
    $service = new CustomFormService();
    $result = $service->list($filters, $currentUser['company_id'] ?? null);
    
    if (!$result['success']) {
        ApiResponse::error($result['code'] ?? 'LIST_ERROR', $result['message'], 400);
    }
    
    ApiResponse::success($result['data'], 'Forms retrieved successfully');
} catch (Exception $e) {
    error_log("Forms Master List API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve forms: ' . $e->getMessage());
}

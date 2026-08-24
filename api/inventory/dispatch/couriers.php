<?php
/**
 * GET /api/inventory/dispatch/couriers.php
 * 
 * Returns active couriers from couriers master table for dispatch forms.
 * Accessible to any authenticated user (ADV, Contractor, Engineer).
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../repositories/CourierRepository.php';

ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    $user = $authMiddleware->requireAuth();

    $courierRepo = new CourierRepository();
    $couriers = $courierRepo->findAllActive();

    ApiResponse::success([
        'couriers' => $couriers
    ], 'Couriers retrieved successfully');

} catch (Exception $e) {
    error_log("Dispatch Couriers API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve couriers');
}

<?php
/**
 * Pending Receives API
 * GET /api/inventory/receive/pending.php
 * 
 * Returns pending receives for current user/entity
 * Supports filtering by status and date range
 * 
 * Query Parameters:
 * - status: string (optional) - Filter by status (pending, accepted, rejected, partial)
 * - from_date: string (optional) - Filter by dispatch date from (Y-m-d format)
 * - to_date: string (optional) - Filter by dispatch date to (Y-m-d format)
 * - entity_type: string (optional) - Override entity type (warehouse, company, user)
 * - entity_id: int (optional) - Override entity ID
 * - count_only: int (optional) - If 1, return only the count (for badges)
 * 
 * Response: { success: bool, data: { pending_receives: array, count: int } }
 * 
 * Requirements: 2.1, 2.2, 2.3
 * - Display dispatch in contractor's pending receives list when items dispatched to contractor
 * - Display dispatch in engineer's pending receives list when items dispatched to engineer
 * - Show dispatch details including sender, items, quantities, and dispatch date
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/ReceiveService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    $user = $authMiddleware->requireAuth();
    
    // Get query parameters
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : null;
    $toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : null;
    $entityType = isset($_GET['entity_type']) ? trim($_GET['entity_type']) : null;
    $entityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : null;
    $countOnly = isset($_GET['count_only']) && $_GET['count_only'] == '1';
    
    // For count_only, default to pending status
    if ($countOnly && $status === null) {
        $status = 'pending';
    }
    
    // Validate status if provided
    $validStatuses = ['pending', 'accepted', 'rejected', 'partial'];
    if ($status !== null && !in_array($status, $validStatuses)) {
        ApiResponse::validationError(['status' => 'Invalid status. Must be one of: ' . implode(', ', $validStatuses)]);
    }
    
    // Validate date formats if provided
    if ($fromDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
        ApiResponse::validationError(['from_date' => 'Invalid date format. Use Y-m-d format']);
    }
    if ($toDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        ApiResponse::validationError(['to_date' => 'Invalid date format. Use Y-m-d format']);
    }
    
    // Determine entity type and ID based on user context
    if ($entityType === null || $entityId === null) {
        // Determine based on user's role/company type (case-insensitive comparison)
        $companyType = strtoupper($user['company_type'] ?? '');
        
        if ($companyType === 'ADV') {
            // ADV users - check if they want warehouse-specific or all
            if (isset($_GET['warehouse_id'])) {
                $entityType = 'warehouse';
                $entityId = (int)$_GET['warehouse_id'];
            } else {
                // Default to user's pending receives
                $entityType = 'user';
                $entityId = $user['id'];
            }
        } elseif ($companyType === 'CONTRACTOR') {
            // Contractor users - can view company or personal pending receives
            if (isset($_GET['view']) && $_GET['view'] === 'company') {
                $entityType = 'company';
                $entityId = $user['company_id'];
            } else {
                $entityType = 'user';
                $entityId = $user['id'];
            }
        } else {
            // Engineer or other users - personal pending receives
            $entityType = 'user';
            $entityId = $user['id'];
        }
    } else {
        // Validate user has access to requested entity
        $validEntityTypes = ['warehouse', 'company', 'user'];
        if (!in_array($entityType, $validEntityTypes)) {
            ApiResponse::validationError(['entity_type' => 'Invalid entity type. Must be one of: ' . implode(', ', $validEntityTypes)]);
        }
        
        // Check access permissions
        if ($entityType === 'user' && $entityId !== $user['id'] && $user['company_type'] !== 'ADV') {
            ApiResponse::forbidden('You can only view your own pending receives');
        }
        if ($entityType === 'company' && $entityId !== $user['company_id'] && $user['company_type'] !== 'ADV') {
            ApiResponse::forbidden('You can only view your company\'s pending receives');
        }
    }
    
    $receiveService = new ReceiveService();
    
    // Get pending receives
    $result = $receiveService->getPendingReceives($entityType, $entityId, $status);
    
    if (!$result['success']) {
        ApiResponse::error($result['code'] ?? 'GET_PENDING_ERROR', $result['message'], 400);
    }
    
    // Apply date filters if provided
    $pendingReceives = $result['data']['pending_receives'];
    
    if ($fromDate !== null || $toDate !== null) {
        $pendingReceives = array_filter($pendingReceives, function($receive) use ($fromDate, $toDate) {
            $dispatchDate = substr($receive['dispatch_date'] ?? $receive['created_at'], 0, 10);
            
            if ($fromDate !== null && $dispatchDate < $fromDate) {
                return false;
            }
            if ($toDate !== null && $dispatchDate > $toDate) {
                return false;
            }
            return true;
        });
        $pendingReceives = array_values($pendingReceives); // Re-index array
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/receive/pending', 'GET', [
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'status' => $status,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'count_only' => $countOnly,
        'result_count' => count($pendingReceives)
    ]);
    
    // Return count only if requested (for badges)
    if ($countOnly) {
        ApiResponse::success([
            'count' => count($pendingReceives)
        ], 'Pending receives count retrieved successfully');
    }
    
    ApiResponse::success([
        'pending_receives' => $pendingReceives,
        'count' => count($pendingReceives),
        'filters' => [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => $status,
            'from_date' => $fromDate,
            'to_date' => $toDate
        ]
    ], 'Pending receives retrieved successfully');
    
} catch (Exception $e) {
    error_log("Pending Receives API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve pending receives: ' . $e->getMessage());
}

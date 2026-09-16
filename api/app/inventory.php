<?php
/**
 * Mobile App Inventory & Pending Receives API Endpoint
 * 
 * GET /api/app/inventory.php - Get pending receives, field stock in engineer custody, and categories
 * POST /api/app/inventory.php - Actions: accept, partial_accept, reject pending receives, request material
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/ReceiveService.php';
require_once __DIR__ . '/../../services/InventoryCounterService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    $user = $authMiddleware->requireAuth();
    
    $userId = (int)$user['id'];
    $db = DatabaseConfig::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetInventory($db, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostRequest($db, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
} catch (Exception $e) {
    error_log("Mobile Inventory API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process inventory request: ' . $e->getMessage());
}

function handleGetInventory($db, $user) {
    $userId = (int)$user['id'];
    $search = trim($_GET['search'] ?? '');
    $categoryFilter = trim($_GET['category'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');
    
    $receiveService = new ReceiveService();
    $counterService = new InventoryCounterService();
    
    // 1. Fetch Real Pending Receives for this Engineer User
    $pendingReceives = [];
    $pendingRes = $receiveService->getPendingReceives('user', $userId, $statusFilter ?: null);
    if ($pendingRes['success'] && !empty($pendingRes['data']['pending_receives'])) {
        $pendingReceives = $pendingRes['data']['pending_receives'];
    }
    
    // 2. Fetch Custody Inventory Counters for this Engineer User
    $counterRes = $counterService->getAllCounters('user', $userId);
    $rawCounters = ($counterRes['success'] && !empty($counterRes['data'])) ? $counterRes['data'] : [];
    
    // 3. Fetch Serialized Assets held by this Engineer User
    $assetsSql = "SELECT a.id, a.product_id, a.serial_number, a.status, a.working_condition, p.name as product_name
                  FROM assets a
                  LEFT JOIN products p ON a.product_id = p.id
                  WHERE a.current_holder_type = 'user' AND a.current_holder_id = ?";
    $userAssets = $db->getResults($assetsSql, [$userId], 'i') ?: [];
    
    $assetsByProduct = [];
    foreach ($userAssets as $asset) {
        $pid = (int)$asset['product_id'];
        if (!isset($assetsByProduct[$pid])) {
            $assetsByProduct[$pid] = [];
        }
        $assetsByProduct[$pid][] = $asset['serial_number'];
    }
    
    // 4. Transform Counters into Standard Mobile Inventory Items
    $items = [];
    $allCategories = ['All'];
    
    if (!empty($rawCounters)) {
        foreach ($rawCounters as $cnt) {
            $cat = ($cnt['category_name'] ?? null) ?: 'General';
            if (!in_array($cat, $allCategories)) {
                $allCategories[] = $cat;
            }
            
            $pid = (int)($cnt['product_id'] ?? 0);
            $serials = $assetsByProduct[$pid] ?? [];
            
            // Icon mapping
            $icon = 'construct-outline';
            $lowerName = strtolower($cnt['product_name'] ?? '');
            if (strpos($lowerName, 'router') !== false) {
                $icon = 'hardware-chip-outline';
            } elseif (strpos($lowerName, 'antenna') !== false) {
                $icon = 'radio-outline';
            } elseif (strpos($lowerName, 'sim') !== false) {
                $icon = 'card-outline';
            } elseif (strpos($lowerName, 'cable') !== false || strpos($lowerName, 'cord') !== false) {
                $icon = 'git-commit-outline';
            } elseif (strpos($lowerName, 'power') !== false || strpos($lowerName, 'battery') !== false || strpos($lowerName, 'adaptor') !== false) {
                $icon = 'flash-outline';
            }
            
            $items[] = [
                'id' => (string)$pid,
                'product_id' => $pid,
                'sku' => 'SKU-' . str_pad($pid, 4, '0', STR_PAD_LEFT),
                'name' => ($cnt['product_name'] ?? null) ?: 'Product #' . $pid,
                'category' => $cat,
                'stock' => (int)($cnt['quantity'] ?? 0),
                'available_stock' => (int)($cnt['available_quantity'] ?? 0),
                'unit' => ($cnt['unit_of_measure'] ?? null) ?: 'pcs',
                'icon' => $icon,
                'serials' => $serials,
                'is_serializable' => !empty($cnt['is_serializable']),
            ];
        }
    }
    
    // Filter stock by category if requested
    if ($categoryFilter && $categoryFilter !== 'All') {
        $items = array_values(array_filter($items, function($item) use ($categoryFilter) {
            return strcasecmp($item['category'], $categoryFilter) === 0;
        }));
    }
    
    // Filter stock by search if requested
    if ($search !== '') {
        $q = strtolower($search);
        $items = array_values(array_filter($items, function($item) use ($q) {
            return strpos(strtolower($item['name']), $q) !== false ||
                   strpos(strtolower($item['sku']), $q) !== false ||
                   strpos(strtolower($item['category']), $q) !== false;
        }));
    }
    
    // Calculate counts
    $pendingCount = count(array_filter($pendingReceives, function($r) {
        return ($r['status'] ?? '') === 'pending';
    }));
    
    ApiResponse::success([
        'pending_receives' => $pendingReceives,
        'items' => $items,
        'categories' => $allCategories,
        'counts' => [
            'pending_receives' => $pendingCount,
            'total_receives' => count($pendingReceives),
            'stock_items' => count($items),
            'total_stock_qty' => array_sum(array_column($items, 'stock'))
        ]
    ], 'Inventory data retrieved successfully');
}

function handlePostRequest($db, $user) {
    $userId = (int)$user['id'];
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    $receiveService = new ReceiveService();
    
    // Action 1: Accept Pending Receive (Full Accept)
    if ($action === 'accept') {
        $pendingReceiveId = (int)($input['pending_receive_id'] ?? 0);
        if ($pendingReceiveId <= 0) {
            ApiResponse::validationError(['pending_receive_id' => ['Valid pending receive ID is required']]);
            return;
        }
        
        $res = $receiveService->acceptReceive($pendingReceiveId, $userId);
        if ($res['success']) {
            ApiResponse::success($res['data'] ?? [], $res['message'] ?? 'Materials accepted and added to your custody stock');
        } else {
            ApiResponse::error($res['code'] ?? 'ACCEPT_ERROR', $res['message'] ?? 'Failed to accept materials', 400);
        }
        return;
    }
    
    // Action 2: Partial Accept Pending Receive
    if ($action === 'partial_accept') {
        $pendingReceiveId = (int)($input['pending_receive_id'] ?? 0);
        $items = $input['items'] ?? [];
        $notes = trim($input['notes'] ?? '');
        
        if ($pendingReceiveId <= 0) {
            ApiResponse::validationError(['pending_receive_id' => ['Valid pending receive ID is required']]);
            return;
        }
        if (empty($items) || !is_array($items)) {
            ApiResponse::validationError(['items' => ['Items array with received quantities is required']]);
            return;
        }
        
        $res = $receiveService->partialAccept($pendingReceiveId, $userId, $items, $notes ?: null);
        if ($res['success']) {
            ApiResponse::success($res['data'] ?? [], $res['message'] ?? 'Partial acceptance recorded successfully');
        } else {
            ApiResponse::error($res['code'] ?? 'PARTIAL_ACCEPT_ERROR', $res['message'] ?? 'Failed to partially accept materials', 400);
        }
        return;
    }
    
    // Action 3: Reject Pending Receive
    if ($action === 'reject') {
        $pendingReceiveId = (int)($input['pending_receive_id'] ?? 0);
        $reason = trim($input['reason'] ?? '');
        
        if ($pendingReceiveId <= 0) {
            ApiResponse::validationError(['pending_receive_id' => ['Valid pending receive ID is required']]);
            return;
        }
        if (empty($reason)) {
            ApiResponse::validationError(['reason' => ['Rejection reason is required']]);
            return;
        }
        
        $res = $receiveService->rejectReceive($pendingReceiveId, $userId, $reason);
        if ($res['success']) {
            ApiResponse::success($res['data'] ?? [], $res['message'] ?? 'Materials rejected and returned to sender');
        } else {
            ApiResponse::error($res['code'] ?? 'REJECT_ERROR', $res['message'] ?? 'Failed to reject materials', 400);
        }
        return;
    }
    
    // Action 4: Material Requisition / Indent Request
    if ($action === 'request') {
        $itemId = $input['item_id'] ?? '';
        $quantity = (int)($input['quantity'] ?? 1);
        $reason = trim($input['reason'] ?? '');
        
        if ($quantity <= 0) {
            ApiResponse::validationError(['quantity' => ['Quantity must be greater than 0']]);
            return;
        }
        
        ApiResponse::success([
            'request_id' => 'REQ-' . date('Ymd') . '-' . rand(100, 999),
            'item_id' => $itemId,
            'quantity' => $quantity,
            'reason' => $reason,
            'status' => 'submitted',
            'created_at' => date('Y-m-d H:i:s')
        ], 'Material requisition submitted successfully');
        return;
    }
    
    ApiResponse::validationError(['action' => ['Invalid or missing action. Allowed: accept, partial_accept, reject, request']]);
}

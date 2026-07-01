<?php
/**
 * Engineer Dashboard API
 * GET /api/inventory/dashboard/engineer
 * 
 * Returns dashboard data for engineer users including:
 * - All items currently assigned to the engineer
 * - Status update actions available (In Use, Return, Working/Not Working)
 * - Repair request capability
 * 
 * Requirements: 11.1, 11.3, 11.4
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../services/ReceiveService.php';
require_once __DIR__ . '/../../../services/InventoryCounterService.php';
require_once __DIR__ . '/../../../repositories/AssetRepository.php';
require_once __DIR__ . '/../../../repositories/DispatchRepository.php';
require_once __DIR__ . '/../../../repositories/RepairRepository.php';
require_once __DIR__ . '/../../../repositories/ProductCategoryRepository.php';
require_once __DIR__ . '/../../../models/User.php';

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $db = DatabaseConfig::getInstance();
    $sessionService = new SessionService();
    $accessService = new InventoryAccessService();
    $assetRepository = new AssetRepository();
    $dispatchRepository = new DispatchRepository();
    $dispatchRepository->disableCompanyFilter();
    $repairRepository = new RepairRepository();
    $userModel = new User();
    $receiveService = new ReceiveService();
    $inventoryCounterService = new InventoryCounterService();
    $productCategoryRepository = new ProductCategoryRepository();
    
    // Get user ID from session
    $userId = $sessionService->getCurrentUserId();
    if (!$userId) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required.',
            'code' => 'AUTH_REQUIRED'
        ]);
        exit;
    }
    
    // Verify user is a contractor user (engineers are contractor users)
    if (!isContractorUser($userId)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Contractor/Engineer role required.',
            'code' => 'ACCESS_DENIED'
        ]);
        exit;
    }
    
    // Get user details
    $user = $userModel->findWithRelations($userId);
    
    // 1. Get all items assigned to this engineer (Requirement 11.1)
    $assignedItems = getAssignedItems($db, $userId);
    
    // 2. Get summary statistics
    $summary = getSummaryStats($assignedItems);
    
    // 3. Get items by status for quick actions
    $itemsByStatus = groupItemsByStatus($assignedItems);
    
    // 4. Get items requiring attention (not working)
    $itemsRequiringAttention = getItemsRequiringAttention($assignedItems);
    
    // 5. Get pending dispatches to this engineer
    $pendingDispatches = getPendingDispatchesToEngineer($dispatchRepository, $userId);
    
    // 6. Get active repairs for engineer's items
    $activeRepairs = getActiveRepairs($db, $userId);
    
    // 7. Get inventory counters for this engineer
    $inventoryCounters = getInventoryCounters($inventoryCounterService, $userId);
    
    // 8. Get pending receives for this engineer
    $pendingReceivesResult = getPendingReceivesForEngineer($receiveService, $userId);
    
    // 9. Get inventory breakdown by category
    $inventoryByCategory = getInventoryByCategory($assignedItems);
    
    // 10. Get pending action highlights
    $pendingActions = getPendingActionHighlights($pendingReceivesResult, $itemsRequiringAttention, $pendingDispatches);
    
    // 11. Get allowed status update actions (Requirement 11.2)
    $allowedActions = [
        'status_updates' => $accessService->getEngineerAllowedStatuses(),
        'condition_updates' => $accessService->getEngineerAllowedConditions()
    ];
    
    // Build response
    $response = [
        'success' => true,
        'data' => [
            'user_id' => $userId,
            'user_name' => $user['first_name'] . ' ' . $user['last_name'],
            'company_name' => $user['company_name'],
            'summary' => array_merge($summary, [
                'pending_receives_count' => $pendingReceivesResult['count']
            ]),
            'inventory_counters' => $inventoryCounters,
            'assigned_items' => [
                'total' => count($assignedItems),
                'items' => $assignedItems
            ],
            'items_by_status' => $itemsByStatus,
            'items_requiring_attention' => [
                'count' => count($itemsRequiringAttention),
                'items' => $itemsRequiringAttention
            ],
            'active_repairs' => [
                'count' => count($activeRepairs),
                'items' => $activeRepairs
            ],
            'pending_dispatches' => [
                'count' => count($pendingDispatches),
                'items' => $pendingDispatches
            ],
            'pending_receives' => [
                'count' => $pendingReceivesResult['count'],
                'items' => $pendingReceivesResult['items']
            ],
            'inventory_by_category' => $inventoryByCategory,
            'pending_actions' => $pendingActions,
            'allowed_actions' => $allowedActions,
            'quick_actions' => [
                'mark_in_use' => [
                    'action' => 'update_status',
                    'status' => 'in_use',
                    'description' => 'Mark item as currently in use'
                ],
                'mark_returned' => [
                    'action' => 'update_status',
                    'status' => 'returned',
                    'description' => 'Mark item as returned'
                ],
                'mark_working' => [
                    'action' => 'update_condition',
                    'condition' => 'working',
                    'description' => 'Mark item as working'
                ],
                'mark_not_working' => [
                    'action' => 'update_condition',
                    'condition' => 'not_working',
                    'description' => 'Mark item as not working (initiates repair workflow)'
                ],
                'request_repair' => [
                    'action' => 'request_repair',
                    'description' => 'Request repair for a non-working item'
                ]
            ]
        ],
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load dashboard data: ' . $e->getMessage(),
        'code' => 'DASHBOARD_ERROR'
    ]);
}

/**
 * Get all items assigned to this engineer (Requirement 11.1)
 */
function getAssignedItems($db, $userId) {
    $sql = "SELECT 
                a.id,
                a.serial_number,
                a.status,
                a.working_condition,
                a.notes,
                a.updated_at,
                p.id as product_id,
                p.name as product_name,
                p.is_repairable,
                sw.name as source_warehouse_name,
                sw.id as source_warehouse_id,
                d.dispatch_date as received_date,
                d.dispatch_number
            FROM assets a
            LEFT JOIN products p ON a.product_id = p.id
            LEFT JOIN warehouses sw ON a.source_warehouse_id = sw.id
            LEFT JOIN dispatch_items di ON di.asset_id = a.id
            LEFT JOIN dispatches d ON di.dispatch_id = d.id
            WHERE a.current_holder_type = 'user'
            AND a.current_holder_id = ?
            AND a.status NOT IN ('scrapped', 'lost')
            ORDER BY a.status, a.serial_number";
    
    return $db->getResults($sql, [$userId], 'i');
}

/**
 * Get summary statistics
 */
function getSummaryStats($assignedItems) {
    $total = count($assignedItems);
    $inUse = 0;
    $assigned = 0;
    $returned = 0;
    $underRepair = 0;
    $working = 0;
    $notWorking = 0;
    
    foreach ($assignedItems as $item) {
        switch ($item['status']) {
            case 'in_use':
                $inUse++;
                break;
            case 'assigned':
                $assigned++;
                break;
            case 'returned':
                $returned++;
                break;
            case 'under_repair':
                $underRepair++;
                break;
        }
        
        if ($item['working_condition'] === 'working') {
            $working++;
        } else {
            $notWorking++;
        }
    }
    
    return [
        'total_items' => $total,
        'by_status' => [
            'in_use' => $inUse,
            'assigned' => $assigned,
            'returned' => $returned,
            'under_repair' => $underRepair
        ],
        'by_condition' => [
            'working' => $working,
            'not_working' => $notWorking
        ]
    ];
}

/**
 * Group items by status for quick actions
 */
function groupItemsByStatus($assignedItems) {
    $grouped = [
        'in_use' => [],
        'assigned' => [],
        'returned' => [],
        'under_repair' => [],
        'other' => []
    ];
    
    foreach ($assignedItems as $item) {
        $status = $item['status'];
        if (isset($grouped[$status])) {
            $grouped[$status][] = $item;
        } else {
            $grouped['other'][] = $item;
        }
    }
    
    return $grouped;
}

/**
 * Get items requiring attention (not working)
 */
function getItemsRequiringAttention($assignedItems) {
    return array_values(array_filter($assignedItems, function($item) {
        return $item['working_condition'] === 'not_working' && 
               $item['status'] !== 'under_repair';
    }));
}

/**
 * Get pending dispatches to this engineer
 */
function getPendingDispatchesToEngineer($dispatchRepository, $userId) {
    $dispatches = $dispatchRepository->findToUser($userId);
    
    // Filter to only pending/in_transit dispatches
    return array_values(array_filter($dispatches, function($d) {
        return in_array($d['status'], ['pending', 'in_transit']) ||
               ($d['status'] === 'delivered' && $d['acknowledgment_status'] === 'pending');
    }));
}

/**
 * Get active repairs for engineer's items
 */
function getActiveRepairs($db, $userId) {
    $sql = "SELECT 
                r.id as repair_id,
                r.status as repair_status,
                r.repair_vendor,
                r.estimated_cost,
                r.send_date,
                r.expected_return_date,
                a.id as asset_id,
                a.serial_number,
                p.name as product_name
            FROM repairs r
            LEFT JOIN assets a ON r.asset_id = a.id
            LEFT JOIN products p ON a.product_id = p.id
            WHERE a.current_holder_type = 'user'
            AND a.current_holder_id = ?
            AND r.status IN ('pending', 'in_progress')
            ORDER BY r.send_date DESC";
    
    return $db->getResults($sql, [$userId], 'i');
}

/**
 * Get inventory counters for engineer
 * Requirements: 8.3 - Show assigned inventory
 */
function getInventoryCounters($inventoryCounterService, $userId) {
    $result = $inventoryCounterService->getAllCounters('user', $userId);
    if ($result['success']) {
        return $result['data']['counters'] ?? [];
    }
    return [];
}

/**
 * Get pending receives for engineer
 * Requirements: 8.3 - Show pending receives count
 */
function getPendingReceivesForEngineer($receiveService, $userId) {
    $result = $receiveService->getPendingReceives('user', $userId, 'pending');
    
    if ($result['success']) {
        $pendingReceives = $result['data']['pending_receives'] ?? [];
        return [
            'count' => count($pendingReceives),
            'items' => array_slice($pendingReceives, 0, 10)
        ];
    }
    
    return ['count' => 0, 'items' => []];
}

/**
 * Get inventory breakdown by category for engineer
 * Requirements: 8.4 - Show inventory breakdown by product category
 */
function getInventoryByCategory($assignedItems) {
    $byCategory = [];
    
    foreach ($assignedItems as $item) {
        $categoryName = $item['category_name'] ?? 'Uncategorized';
        
        if (!isset($byCategory[$categoryName])) {
            $byCategory[$categoryName] = [
                'category_name' => $categoryName,
                'total_count' => 0,
                'in_use_count' => 0,
                'working_count' => 0,
                'not_working_count' => 0
            ];
        }
        
        $byCategory[$categoryName]['total_count']++;
        
        if ($item['status'] === 'in_use') {
            $byCategory[$categoryName]['in_use_count']++;
        }
        
        if ($item['working_condition'] === 'working') {
            $byCategory[$categoryName]['working_count']++;
        } else {
            $byCategory[$categoryName]['not_working_count']++;
        }
    }
    
    return array_values($byCategory);
}

/**
 * Get pending action highlights for engineer
 * Requirements: 8.5 - Highlight items with pending actions requiring attention
 */
function getPendingActionHighlights($pendingReceives, $itemsRequiringAttention, $pendingDispatches) {
    $highlights = [];
    
    // Pending receives requiring acceptance
    if ($pendingReceives['count'] > 0) {
        $highlights[] = [
            'type' => 'pending_receives',
            'title' => 'Pending Receives',
            'count' => $pendingReceives['count'],
            'severity' => 'warning',
            'message' => $pendingReceives['count'] . ' dispatch(es) awaiting acceptance',
            'action_url' => 'engineer/pending-receives.php'
        ];
    }
    
    // Items requiring attention (not working)
    if (count($itemsRequiringAttention) > 0) {
        $highlights[] = [
            'type' => 'attention_required',
            'title' => 'Items Need Attention',
            'count' => count($itemsRequiringAttention),
            'severity' => 'danger',
            'message' => count($itemsRequiringAttention) . ' item(s) not working',
            'action_url' => '#attention-section'
        ];
    }
    
    // Pending dispatches (items being sent to engineer)
    if (count($pendingDispatches) > 0) {
        $highlights[] = [
            'type' => 'pending_dispatches',
            'title' => 'Incoming Items',
            'count' => count($pendingDispatches),
            'severity' => 'info',
            'message' => count($pendingDispatches) . ' dispatch(es) on the way',
            'action_url' => '#pending-dispatches'
        ];
    }
    
    return $highlights;
}

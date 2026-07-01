<?php
/**
 * Contractor Dashboard API
 * GET /api/inventory/dashboard/contractor
 * 
 * Returns dashboard data for contractor users including:
 * - Inventory received from ADV with receipt dates
 * - Items assigned to each engineer under the contractor
 * - Pending return items with expected dates
 * - Non-working items requiring attention
 * 
 * Requirements: 10.1, 10.2, 10.3, 10.4
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../services/ReceiveService.php';
require_once __DIR__ . '/../../../services/InventoryCounterService.php';
require_once __DIR__ . '/../../../repositories/AssetRepository.php';
require_once __DIR__ . '/../../../repositories/DispatchRepository.php';
require_once __DIR__ . '/../../../repositories/WarehouseRepository.php';
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
    $warehouseRepository = new WarehouseRepository();
    $warehouseRepository->disableCompanyFilter();
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
    
    // Verify user has Contractor role (contractor admin, not engineer)
    if (!isContractorAdmin($userId)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Contractor admin role required.',
            'code' => 'ACCESS_DENIED'
        ]);
        exit;
    }
    
    // Get user's company ID
    $user = $userModel->findWithRelations($userId);
    $companyId = $user['company_id'];
    
    // 1. Get inventory received from ADV (Requirement 10.1)
    $receivedInventory = getReceivedInventory($db, $companyId);
    
    // 2. Get items assigned to each engineer (Requirement 10.2)
    $engineerAssignments = getEngineerAssignments($db, $companyId);
    
    // 3. Get pending return items (Requirement 10.3)
    $pendingReturns = getPendingReturns($db, $companyId);
    
    // 4. Get non-working items (Requirement 10.4)
    $nonWorkingItems = getNonWorkingItems($db, $companyId);
    
    // 5. Get summary statistics
    $summary = getSummaryStats($db, $companyId);
    
    // 6. Get recent dispatches to this contractor
    $recentDispatches = getRecentDispatchesToContractor($dispatchRepository, $companyId);
    
    // 7. Get pending acknowledgments
    $pendingAcknowledgments = getPendingAcknowledgments($db, $companyId);
    
    // 8. Get inventory counters for this contractor
    $inventoryCounters = getInventoryCounters($inventoryCounterService, $companyId);
    
    // 9. Get pending receives for this contractor
    $pendingReceivesResult = getPendingReceivesForContractor($receiveService, $companyId, $userId);
    
    // 10. Get items dispatched to engineers
    $dispatchedToEngineers = getDispatchedToEngineers($db, $companyId);
    
    // 11. Get inventory breakdown by category
    $inventoryByCategory = getInventoryByCategory($db, $companyId);
    
    // 12. Get pending action highlights
    $pendingActions = getPendingActionHighlights($pendingReceivesResult, $pendingReturns, $nonWorkingItems, $pendingAcknowledgments);
    
    // Build response
    $response = [
        'success' => true,
        'data' => [
            'company_id' => $companyId,
            'company_name' => $user['company_name'],
            'summary' => array_merge($summary, [
                'pending_receives_count' => $pendingReceivesResult['count'],
                'dispatched_to_engineers_count' => count($dispatchedToEngineers)
            ]),
            'inventory_counters' => $inventoryCounters,
            'received_inventory' => [
                'total' => count($receivedInventory),
                'items' => $receivedInventory
            ],
            'engineer_assignments' => $engineerAssignments,
            'pending_returns' => [
                'count' => count($pendingReturns),
                'items' => $pendingReturns
            ],
            'non_working_items' => [
                'count' => count($nonWorkingItems),
                'items' => $nonWorkingItems
            ],
            'pending_receives' => [
                'count' => $pendingReceivesResult['count'],
                'items' => $pendingReceivesResult['items']
            ],
            'dispatched_to_engineers' => [
                'count' => count($dispatchedToEngineers),
                'items' => $dispatchedToEngineers
            ],
            'inventory_by_category' => $inventoryByCategory,
            'pending_actions' => $pendingActions,
            'recent_activity' => [
                'recent_dispatches' => $recentDispatches,
                'pending_acknowledgments' => [
                    'count' => count($pendingAcknowledgments),
                    'items' => array_slice($pendingAcknowledgments, 0, 5)
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
 * Get inventory received from ADV (Requirement 10.1)
 * Returns assets currently held by this contractor company
 * Includes assets with current_holder_type='company' and current_holder_id matching contractor's company
 * 
 * Requirements: 4.1, 4.3, 4.4
 * - Display accepted assets as available for dispatch
 * - Show asset serial number and product details
 */
function getReceivedInventory($db, $companyId) {
    // Get assets currently held by this company (current_holder_type='company')
    // This includes assets that have been accepted from dispatches
    $sql = "SELECT 
                a.id,
                a.serial_number,
                a.status,
                a.working_condition,
                a.current_holder_type,
                a.current_holder_id,
                p.name as product_name,
                p.id as product_id,
                p.is_serializable,
                sw.name as source_warehouse_name,
                d.dispatch_date as received_date,
                d.dispatch_number,
                d.acknowledged_at,
                CASE 
                    WHEN a.current_holder_type = 'user' THEN CONCAT(holder.first_name, ' ', holder.last_name)
                    WHEN a.current_holder_type = 'company' THEN c.name
                    ELSE NULL
                END as current_holder_name
            FROM assets a
            LEFT JOIN products p ON a.product_id = p.id
            LEFT JOIN warehouses sw ON a.source_warehouse_id = sw.id
            LEFT JOIN users holder ON a.current_holder_type = 'user' AND a.current_holder_id = holder.id
            LEFT JOIN companies c ON a.current_holder_type = 'company' AND a.current_holder_id = c.id
            LEFT JOIN dispatch_items di ON di.asset_id = a.id
            LEFT JOIN dispatches d ON di.dispatch_id = d.id AND d.acknowledgment_status = 'acknowledged'
            WHERE (
                (a.current_holder_type = 'company' AND a.current_holder_id = ?)
                OR (a.current_holder_type = 'user' AND holder.company_id = ?)
            )
            AND a.status NOT IN ('scrapped', 'lost')
            ORDER BY a.serial_number";
    
    return $db->getResults($sql, [$companyId, $companyId], 'ii');
}

/**
 * Get items assigned to each engineer (Requirement 10.2)
 */
function getEngineerAssignments($db, $companyId) {
    // Get engineers in this company
    // Engineers are identified by role name or level <= 50 (field-level roles)
    $engineersSql = "SELECT 
                        u.id,
                        CONCAT(u.first_name, ' ', u.last_name) as name,
                        u.email
                     FROM users u
                     LEFT JOIN roles r ON u.role_id = r.id
                     WHERE u.company_id = ?
                     AND u.status = 1
                     AND (
                         LOWER(r.name) LIKE '%engineer%'
                         OR LOWER(r.name) LIKE '%technician%'
                         OR LOWER(r.name) LIKE '%field%'
                         OR (r.level IS NOT NULL AND r.level <= 50 AND r.level > 0)
                     )
                     ORDER BY u.first_name, u.last_name";
    
    $engineers = $db->getResults($engineersSql, [$companyId], 'i');
    
    $result = [];
    foreach ($engineers as $engineer) {
        // Get assets assigned to this engineer
        $assetsSql = "SELECT 
                        a.id,
                        a.serial_number,
                        a.status,
                        a.working_condition,
                        p.name as product_name
                      FROM assets a
                      LEFT JOIN products p ON a.product_id = p.id
                      WHERE a.current_holder_type = 'user' 
                      AND a.current_holder_id = ?
                      AND a.status NOT IN ('scrapped', 'lost')
                      ORDER BY a.serial_number";
        
        $assets = $db->getResults($assetsSql, [$engineer['id']], 'i');
        
        $result[] = [
            'engineer_id' => $engineer['id'],
            'engineer_name' => $engineer['name'],
            'engineer_email' => $engineer['email'],
            'total_items' => count($assets),
            'in_use_count' => count(array_filter($assets, fn($a) => $a['status'] === 'in_use')),
            'not_working_count' => count(array_filter($assets, fn($a) => $a['working_condition'] === 'not_working')),
            'items' => $assets
        ];
    }
    
    return $result;
}

/**
 * Get pending return items (Requirement 10.3)
 */
function getPendingReturns($db, $companyId) {
    $sql = "SELECT 
                a.id,
                a.serial_number,
                a.status,
                p.name as product_name,
                CONCAT(u.first_name, ' ', u.last_name) as assigned_to,
                u.id as assigned_to_id
            FROM assets a
            LEFT JOIN products p ON a.product_id = p.id
            LEFT JOIN users u ON a.current_holder_type = 'user' AND a.current_holder_id = u.id
            WHERE a.status = 'returned'
            AND (
                (a.current_holder_type = 'company' AND a.current_holder_id = ?)
                OR (a.current_holder_type = 'user' AND u.company_id = ?)
            )
            ORDER BY a.updated_at DESC";
    
    return $db->getResults($sql, [$companyId, $companyId], 'ii');
}

/**
 * Get non-working items (Requirement 10.4)
 */
function getNonWorkingItems($db, $companyId) {
    $sql = "SELECT 
                a.id,
                a.serial_number,
                a.status,
                a.working_condition,
                p.name as product_name,
                p.is_repairable,
                CONCAT(u.first_name, ' ', u.last_name) as current_holder_name,
                u.id as current_holder_id,
                r.id as repair_id,
                r.status as repair_status,
                r.repair_vendor,
                r.expected_return_date
            FROM assets a
            LEFT JOIN products p ON a.product_id = p.id
            LEFT JOIN users u ON a.current_holder_type = 'user' AND a.current_holder_id = u.id
            LEFT JOIN repairs r ON r.asset_id = a.id AND r.status IN ('pending', 'in_progress')
            WHERE a.working_condition = 'not_working'
            AND a.status NOT IN ('scrapped', 'lost')
            AND (
                (a.current_holder_type = 'company' AND a.current_holder_id = ?)
                OR (a.current_holder_type = 'user' AND u.company_id = ?)
            )
            ORDER BY a.updated_at DESC";
    
    return $db->getResults($sql, [$companyId, $companyId], 'ii');
}

/**
 * Get summary statistics
 * Counts assets currently held by this contractor company
 * 
 * Requirements: 4.3, 4.4
 * - Display accepted assets as available for dispatch
 * - Show asset serial number and product details
 */
function getSummaryStats($db, $companyId) {
    // Total assets held by this company (current_holder_type='company' or users in this company)
    $totalSql = "SELECT COUNT(DISTINCT a.id) as count
                 FROM assets a
                 LEFT JOIN users u ON a.current_holder_type = 'user' AND a.current_holder_id = u.id
                 WHERE (
                     (a.current_holder_type = 'company' AND a.current_holder_id = ?)
                     OR (a.current_holder_type = 'user' AND u.company_id = ?)
                 )
                 AND a.status NOT IN ('scrapped', 'lost')";
    $totalResult = $db->getResults($totalSql, [$companyId, $companyId], 'ii');
    
    // Status breakdown for assets held by this company
    $statusSql = "SELECT 
                    a.status,
                    COUNT(DISTINCT a.id) as count
                  FROM assets a
                  LEFT JOIN users u ON a.current_holder_type = 'user' AND a.current_holder_id = u.id
                  WHERE (
                      (a.current_holder_type = 'company' AND a.current_holder_id = ?)
                      OR (a.current_holder_type = 'user' AND u.company_id = ?)
                  )
                  AND a.status NOT IN ('scrapped', 'lost')
                  GROUP BY a.status";
    $statusResults = $db->getResults($statusSql, [$companyId, $companyId], 'ii');
    
    $statusBreakdown = [];
    foreach ($statusResults as $row) {
        $statusBreakdown[$row['status']] = (int)$row['count'];
    }
    
    // Working condition breakdown for assets held by this company
    $conditionSql = "SELECT 
                        a.working_condition,
                        COUNT(DISTINCT a.id) as count
                     FROM assets a
                     LEFT JOIN users u ON a.current_holder_type = 'user' AND a.current_holder_id = u.id
                     WHERE (
                         (a.current_holder_type = 'company' AND a.current_holder_id = ?)
                         OR (a.current_holder_type = 'user' AND u.company_id = ?)
                     )
                     AND a.status NOT IN ('scrapped', 'lost')
                     GROUP BY a.working_condition";
    $conditionResults = $db->getResults($conditionSql, [$companyId, $companyId], 'ii');
    
    $conditionBreakdown = [];
    foreach ($conditionResults as $row) {
        $conditionBreakdown[$row['working_condition']] = (int)$row['count'];
    }
    
    // Engineer count
    $engineerSql = "SELECT COUNT(*) as count
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.company_id = ?
                    AND u.status = 1
                    AND (
                        LOWER(r.name) LIKE '%engineer%'
                        OR LOWER(r.name) LIKE '%technician%'
                        OR LOWER(r.name) LIKE '%field%'
                        OR (r.level IS NOT NULL AND r.level <= 50 AND r.level > 0)
                    )";
    $engineerResult = $db->getResults($engineerSql, [$companyId], 'i');
    
    return [
        'total_assets' => (int)($totalResult[0]['count'] ?? 0),
        'by_status' => $statusBreakdown,
        'by_condition' => $conditionBreakdown,
        'engineer_count' => (int)($engineerResult[0]['count'] ?? 0)
    ];
}

/**
 * Get recent dispatches to this contractor
 */
function getRecentDispatchesToContractor($dispatchRepository, $companyId) {
    $dispatches = $dispatchRepository->findToCompany($companyId);
    return array_slice($dispatches, 0, 10);
}

/**
 * Get pending acknowledgments for this contractor
 */
function getPendingAcknowledgments($db, $companyId) {
    $sql = "SELECT 
                d.id,
                d.dispatch_number,
                d.dispatch_date,
                d.status,
                d.acknowledgment_status,
                fw.name as from_warehouse_name,
                fc.name as from_company_name
            FROM dispatches d
            LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
            LEFT JOIN companies fc ON d.from_company_id = fc.id
            WHERE d.to_company_id = ?
            AND d.acknowledgment_status = 'pending'
            AND d.status = 'delivered'
            ORDER BY d.dispatch_date DESC";
    
    return $db->getResults($sql, [$companyId], 'i');
}

/**
 * Get inventory counters for contractor
 * Requirements: 8.2 - Show current inventory by product
 */
function getInventoryCounters($inventoryCounterService, $companyId) {
    $result = $inventoryCounterService->getAllCounters('company', $companyId);
    if ($result['success']) {
        // The data is returned directly, not nested under 'counters'
        return $result['data'] ?? [];
    }
    return [];
}

/**
 * Get pending receives for contractor
 * Requirements: 8.2 - Show pending receives count
 */
function getPendingReceivesForContractor($receiveService, $companyId, $userId) {
    $result = $receiveService->getPendingReceives('company', $companyId, 'pending');
    
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
 * Get items dispatched to engineers
 * Requirements: 8.2 - Show items dispatched to engineers
 */
function getDispatchedToEngineers($db, $companyId) {
    $sql = "SELECT 
                a.id,
                a.serial_number,
                a.status,
                a.working_condition,
                p.name as product_name,
                CONCAT(u.first_name, ' ', u.last_name) as engineer_name,
                u.id as engineer_id,
                d.dispatch_date,
                d.dispatch_number
            FROM assets a
            LEFT JOIN products p ON a.product_id = p.id
            LEFT JOIN users u ON a.current_holder_type = 'user' AND a.current_holder_id = u.id
            LEFT JOIN dispatch_items di ON di.asset_id = a.id
            LEFT JOIN dispatches d ON di.dispatch_id = d.id
            WHERE u.company_id = ?
            AND a.current_holder_type = 'user'
            AND a.status NOT IN ('scrapped', 'lost')
            ORDER BY d.dispatch_date DESC";
    
    return $db->getResults($sql, [$companyId], 'i');
}

/**
 * Get inventory breakdown by category for contractor
 * Requirements: 8.4 - Show inventory breakdown by product category
 * 
 * Requirements: 4.3, 4.4
 * - Display accepted assets as available for dispatch
 * - Show asset serial number and product details
 */
function getInventoryByCategory($db, $companyId) {
    // Get asset counts by category for assets held by this contractor
    $sql = "SELECT 
                pc.id as category_id,
                pc.name as category_name,
                COUNT(DISTINCT a.id) as asset_count,
                SUM(CASE WHEN a.status = 'assigned' AND a.current_holder_type = 'company' THEN 1 ELSE 0 END) as available_count,
                SUM(CASE WHEN a.status IN ('assigned', 'in_use') AND a.current_holder_type = 'user' THEN 1 ELSE 0 END) as in_use_count,
                SUM(CASE WHEN a.working_condition = 'not_working' THEN 1 ELSE 0 END) as not_working_count
            FROM assets a
            LEFT JOIN products p ON a.product_id = p.id
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            LEFT JOIN users u ON a.current_holder_type = 'user' AND a.current_holder_id = u.id
            WHERE (
                (a.current_holder_type = 'company' AND a.current_holder_id = ?)
                OR (a.current_holder_type = 'user' AND u.company_id = ?)
            )
            AND a.status NOT IN ('scrapped', 'lost')
            GROUP BY pc.id, pc.name
            HAVING asset_count > 0
            ORDER BY asset_count DESC";
    
    return $db->getResults($sql, [$companyId, $companyId], 'ii');
}

/**
 * Get pending action highlights for contractor
 * Requirements: 8.5 - Highlight items with pending actions requiring attention
 */
function getPendingActionHighlights($pendingReceives, $pendingReturns, $nonWorkingItems, $pendingAcknowledgments) {
    $highlights = [];
    
    // Pending receives requiring acceptance
    if ($pendingReceives['count'] > 0) {
        $highlights[] = [
            'type' => 'pending_receives',
            'title' => 'Pending Receives',
            'count' => $pendingReceives['count'],
            'severity' => 'warning',
            'message' => $pendingReceives['count'] . ' dispatch(es) awaiting acceptance',
            'action_url' => 'contractor/pending-receives.php'
        ];
    }
    
    // Pending returns from engineers
    if (count($pendingReturns) > 0) {
        $highlights[] = [
            'type' => 'pending_returns',
            'title' => 'Pending Returns',
            'count' => count($pendingReturns),
            'severity' => 'info',
            'message' => count($pendingReturns) . ' item(s) returned by engineers',
            'action_url' => 'contractor/pending-receives.php'
        ];
    }
    
    // Non-working items
    if (count($nonWorkingItems) > 0) {
        $highlights[] = [
            'type' => 'non_working',
            'title' => 'Non-Working Items',
            'count' => count($nonWorkingItems),
            'severity' => 'danger',
            'message' => count($nonWorkingItems) . ' item(s) need repair',
            'action_url' => 'contractor/stocks.php'
        ];
    }
    
    // Pending acknowledgments
    if (count($pendingAcknowledgments) > 0) {
        $highlights[] = [
            'type' => 'pending_acknowledgments',
            'title' => 'Pending Acknowledgments',
            'count' => count($pendingAcknowledgments),
            'severity' => 'warning',
            'message' => count($pendingAcknowledgments) . ' dispatch(es) to acknowledge',
            'action_url' => 'contractor/pending-receives.php'
        ];
    }
    
    return $highlights;
}

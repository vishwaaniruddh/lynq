<?php
/**
 * ADV Dashboard API
 * GET /api/inventory/dashboard/adv
 * 
 * Returns comprehensive dashboard data for ADV users including:
 * - Total stock across all warehouses with breakdown by status
 * - Dispatched vs available quantities
 * - Contractor-wise allocation summary
 * - Low stock alerts
 * - Overdue repair alerts
 * 
 * Requirements: 9.1, 9.2, 9.3, 9.4
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../services/InventoryAlertService.php';
require_once __DIR__ . '/../../../services/ReceiveService.php';
require_once __DIR__ . '/../../../services/InventoryCounterService.php';
require_once __DIR__ . '/../../../repositories/StockRepository.php';
require_once __DIR__ . '/../../../repositories/AssetRepository.php';
require_once __DIR__ . '/../../../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../../../repositories/DispatchRepository.php';
require_once __DIR__ . '/../../../repositories/RepairRepository.php';
require_once __DIR__ . '/../../../repositories/ProductRepository.php';
require_once __DIR__ . '/../../../repositories/PendingReceiveRepository.php';
require_once __DIR__ . '/../../../repositories/ProductCategoryRepository.php';

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
    $alertService = new InventoryAlertService();
    $stockRepository = new StockRepository();
    $assetRepository = new AssetRepository();
    $warehouseRepository = new WarehouseRepository();
    $warehouseRepository->disableCompanyFilter();
    $dispatchRepository = new DispatchRepository();
    $dispatchRepository->disableCompanyFilter();
    $repairRepository = new RepairRepository();
    $productRepository = new ProductRepository();
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
    
    // Verify user has ADV role
    if (!isAdvUser($userId)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. ADV role required.',
            'code' => 'ACCESS_DENIED'
        ]);
        exit;
    }
    
    // 1. Get total stock summary across all warehouses (Requirement 9.1)
    $stockSummary = getStockSummary($db);
    
    // 2. Get asset status breakdown (Requirement 9.1)
    $assetStatusBreakdown = getAssetStatusBreakdown($db);
    
    // 3. Get dispatched vs available quantities (Requirement 9.2)
    $dispatchedVsAvailable = getDispatchedVsAvailable($db);
    
    // 4. Get contractor-wise allocation summary (Requirement 9.2)
    $contractorAllocations = getContractorAllocations($db);
    
    // 5. Get low stock alerts (Requirement 9.3)
    $lowStockAlerts = $alertService->getLowStockAlerts();
    
    // 6. Get overdue repair alerts (Requirement 9.4)
    $overdueRepairs = $repairRepository->findOverdue();
    
    // 7. Get warehouse summary
    $warehouseSummary = getWarehouseSummary($db);
    
    // 8. Get recent dispatches
    $recentDispatches = getRecentDispatches($dispatchRepository);
    
    // 9. Get pending acknowledgments
    $pendingAcknowledgments = $dispatchRepository->findPendingAcknowledgment();
    
    // 10. Get pending receives awaiting ADV acceptance (returns from contractors/engineers)
    $pendingReceivesResult = getPendingReceivesForAdv($db, $receiveService);
    
    // 11. Get inventory breakdown by category
    $inventoryByCategory = getInventoryByCategory($db);
    
    // 12. Get pending action highlights
    $pendingActions = getPendingActionHighlights($db, $pendingReceivesResult, $lowStockAlerts, $overdueRepairs, $pendingAcknowledgments);
    
    // Build response
    $response = [
        'success' => true,
        'data' => [
            'summary' => [
                'total_products' => $stockSummary['total_products'],
                'total_stock_quantity' => $stockSummary['total_quantity'],
                'total_assets' => $assetStatusBreakdown['total'],
                'total_warehouses' => $warehouseSummary['total'],
                'active_warehouses' => $warehouseSummary['active'],
                'pending_receives_count' => $pendingReceivesResult['count']
            ],
            'stock_by_status' => $assetStatusBreakdown['by_status'],
            'dispatched_vs_available' => $dispatchedVsAvailable,
            'contractor_allocations' => $contractorAllocations,
            'warehouse_summary' => $warehouseSummary['warehouses'],
            'alerts' => [
                'low_stock' => [
                    'count' => count($lowStockAlerts),
                    'items' => array_slice($lowStockAlerts, 0, 10) // Top 10
                ],
                'overdue_repairs' => [
                    'count' => count($overdueRepairs),
                    'items' => array_slice($overdueRepairs, 0, 10) // Top 10
                ]
            ],
            'recent_activity' => [
                'recent_dispatches' => $recentDispatches,
                'pending_acknowledgments' => [
                    'count' => count($pendingAcknowledgments),
                    'items' => array_slice($pendingAcknowledgments, 0, 5)
                ]
            ],
            'pending_receives' => [
                'count' => $pendingReceivesResult['count'],
                'items' => $pendingReceivesResult['items']
            ],
            'inventory_by_category' => $inventoryByCategory,
            'pending_actions' => $pendingActions
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
 * Get stock summary for non-serializable items
 */
function getStockSummary($db) {
    $sql = "SELECT 
                COUNT(DISTINCT s.product_id) as total_products,
                COALESCE(SUM(s.quantity), 0) as total_quantity,
                COALESCE(SUM(s.reserved_quantity), 0) as reserved_quantity,
                COALESCE(SUM(s.quantity - s.reserved_quantity), 0) as available_quantity
            FROM stock s
            LEFT JOIN products p ON s.product_id = p.id
            WHERE p.is_serializable = 0";
    
    $result = $db->getResults($sql);
    return $result[0] ?? [
        'total_products' => 0,
        'total_quantity' => 0,
        'reserved_quantity' => 0,
        'available_quantity' => 0
    ];
}

/**
 * Get asset status breakdown for serializable items
 */
function getAssetStatusBreakdown($db) {
    $sql = "SELECT 
                status,
                COUNT(*) as count
            FROM assets
            GROUP BY status";
    
    $results = $db->getResults($sql);
    
    $breakdown = [
        'in_stock' => 0,
        'dispatched' => 0,
        'assigned' => 0,
        'in_use' => 0,
        'returned' => 0,
        'under_repair' => 0,
        'scrapped' => 0,
        'lost' => 0
    ];
    
    $total = 0;
    foreach ($results as $row) {
        $breakdown[$row['status']] = (int)$row['count'];
        $total += (int)$row['count'];
    }
    
    return [
        'total' => $total,
        'by_status' => $breakdown
    ];
}

/**
 * Get dispatched vs available quantities
 */
function getDispatchedVsAvailable($db) {
    // For serializable items
    $assetSql = "SELECT 
                    SUM(CASE WHEN status = 'in_stock' THEN 1 ELSE 0 END) as available,
                    SUM(CASE WHEN status IN ('dispatched', 'assigned', 'in_use') THEN 1 ELSE 0 END) as dispatched,
                    SUM(CASE WHEN status = 'under_repair' THEN 1 ELSE 0 END) as under_repair
                 FROM assets
                 WHERE status NOT IN ('scrapped', 'lost')";
    
    $assetResult = $db->getResults($assetSql);
    
    // For non-serializable items
    $stockSql = "SELECT 
                    COALESCE(SUM(quantity - reserved_quantity), 0) as available,
                    COALESCE(SUM(reserved_quantity), 0) as reserved
                 FROM stock";
    
    $stockResult = $db->getResults($stockSql);
    
    return [
        'serializable' => [
            'available' => (int)($assetResult[0]['available'] ?? 0),
            'dispatched' => (int)($assetResult[0]['dispatched'] ?? 0),
            'under_repair' => (int)($assetResult[0]['under_repair'] ?? 0)
        ],
        'non_serializable' => [
            'available' => (int)($stockResult[0]['available'] ?? 0),
            'reserved' => (int)($stockResult[0]['reserved'] ?? 0)
        ]
    ];
}

/**
 * Get contractor-wise allocation summary
 */
function getContractorAllocations($db) {
    // Get assets allocated to contractors (by company or user)
    $sql = "SELECT 
                c.id as company_id,
                c.name as company_name,
                COUNT(DISTINCT a.id) as asset_count,
                SUM(CASE WHEN a.status = 'assigned' THEN 1 ELSE 0 END) as assigned_count,
                SUM(CASE WHEN a.status = 'in_use' THEN 1 ELSE 0 END) as in_use_count,
                SUM(CASE WHEN a.working_condition = 'not_working' THEN 1 ELSE 0 END) as not_working_count
            FROM companies c
            LEFT JOIN users u ON u.company_id = c.id
            LEFT JOIN assets a ON (
                (a.current_holder_type = 'company' AND a.current_holder_id = c.id)
                OR (a.current_holder_type = 'user' AND a.current_holder_id = u.id)
            )
            WHERE c.type = 'CONTRACTOR'
            GROUP BY c.id, c.name
            HAVING asset_count > 0
            ORDER BY asset_count DESC";
    
    return $db->getResults($sql);
}

/**
 * Get warehouse summary
 */
function getWarehouseSummary($db) {
    $sql = "SELECT 
                w.id,
                w.name,
                w.location,
                w.status,
                c.name as company_name,
                (SELECT COUNT(*) FROM assets a WHERE a.warehouse_id = w.id AND a.status = 'in_stock') as asset_count,
                (SELECT COALESCE(SUM(s.quantity), 0) FROM stock s WHERE s.warehouse_id = w.id) as stock_quantity
            FROM warehouses w
            LEFT JOIN companies c ON w.company_id = c.id
            ORDER BY w.name";
    
    $warehouses = $db->getResults($sql);
    
    $total = count($warehouses);
    $active = 0;
    foreach ($warehouses as $w) {
        if ($w['status'] === 'active') {
            $active++;
        }
    }
    
    return [
        'total' => $total,
        'active' => $active,
        'warehouses' => $warehouses
    ];
}

/**
 * Get recent dispatches
 */
function getRecentDispatches($dispatchRepository) {
    $dispatches = $dispatchRepository->findAllWithDetails([], 'd.created_at DESC');
    return array_slice($dispatches, 0, 10);
}

/**
 * Get pending receives awaiting ADV acceptance (returns from contractors/engineers)
 * Requirements: 8.1 - Display pending dispatches awaiting acceptance
 */
function getPendingReceivesForAdv($db, $receiveService) {
    // Get all ADV warehouses
    $warehouseSql = "SELECT id FROM warehouses WHERE status = 'active'";
    $warehouses = $db->getResults($warehouseSql);
    
    $allPendingReceives = [];
    
    // Get pending receives for each warehouse
    foreach ($warehouses as $warehouse) {
        $result = $receiveService->getPendingReceives('warehouse', $warehouse['id'], 'pending');
        if ($result['success'] && !empty($result['data']['pending_receives'])) {
            foreach ($result['data']['pending_receives'] as $pr) {
                $allPendingReceives[] = $pr;
            }
        }
    }
    
    // Also check for pending receives to ADV company (company_id = 1 typically for ADV)
    $advCompanySql = "SELECT id FROM companies WHERE type = 'ADV' LIMIT 1";
    $advCompany = $db->getResults($advCompanySql);
    if (!empty($advCompany)) {
        $result = $receiveService->getPendingReceives('company', $advCompany[0]['id'], 'pending');
        if ($result['success'] && !empty($result['data']['pending_receives'])) {
            foreach ($result['data']['pending_receives'] as $pr) {
                $allPendingReceives[] = $pr;
            }
        }
    }
    
    // Sort by created_at descending
    usort($allPendingReceives, function($a, $b) {
        return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0');
    });
    
    return [
        'count' => count($allPendingReceives),
        'items' => array_slice($allPendingReceives, 0, 10)
    ];
}

/**
 * Get inventory breakdown by category
 * Requirements: 8.4 - Show inventory breakdown by product category
 */
function getInventoryByCategory($db) {
    // Get asset counts by category
    $assetSql = "SELECT 
                    pc.id as category_id,
                    pc.name as category_name,
                    COUNT(a.id) as asset_count,
                    SUM(CASE WHEN a.status = 'in_stock' THEN 1 ELSE 0 END) as in_stock_count,
                    SUM(CASE WHEN a.status IN ('dispatched', 'assigned', 'in_use') THEN 1 ELSE 0 END) as dispatched_count,
                    SUM(CASE WHEN a.status = 'under_repair' THEN 1 ELSE 0 END) as under_repair_count
                 FROM product_categories pc
                 LEFT JOIN products p ON p.category_id = pc.id
                 LEFT JOIN assets a ON a.product_id = p.id AND a.status NOT IN ('scrapped', 'lost')
                 GROUP BY pc.id, pc.name
                 HAVING asset_count > 0
                 ORDER BY asset_count DESC";
    
    $assetsByCategory = $db->getResults($assetSql);
    
    // Get stock quantities by category
    $stockSql = "SELECT 
                    pc.id as category_id,
                    pc.name as category_name,
                    COALESCE(SUM(s.quantity), 0) as total_quantity,
                    COALESCE(SUM(s.quantity - s.reserved_quantity), 0) as available_quantity
                 FROM product_categories pc
                 LEFT JOIN products p ON p.category_id = pc.id
                 LEFT JOIN stock s ON s.product_id = p.id
                 WHERE p.is_serializable = 0
                 GROUP BY pc.id, pc.name
                 HAVING total_quantity > 0
                 ORDER BY total_quantity DESC";
    
    $stockByCategory = $db->getResults($stockSql);
    
    return [
        'serializable' => $assetsByCategory,
        'non_serializable' => $stockByCategory
    ];
}

/**
 * Get pending action highlights
 * Requirements: 8.5 - Highlight items with pending actions requiring attention
 */
function getPendingActionHighlights($db, $pendingReceives, $lowStockAlerts, $overdueRepairs, $pendingAcknowledgments) {
    $highlights = [];
    
    // Pending receives requiring acceptance
    if ($pendingReceives['count'] > 0) {
        $highlights[] = [
            'type' => 'pending_receives',
            'title' => 'Pending Returns',
            'count' => $pendingReceives['count'],
            'severity' => 'warning',
            'message' => $pendingReceives['count'] . ' return(s) awaiting acceptance',
            'action_url' => '../inventory/pending-receives.php'
        ];
    }
    
    // Low stock alerts
    if (count($lowStockAlerts) > 0) {
        $highlights[] = [
            'type' => 'low_stock',
            'title' => 'Low Stock',
            'count' => count($lowStockAlerts),
            'severity' => 'danger',
            'message' => count($lowStockAlerts) . ' product(s) below threshold',
            'action_url' => '../inventory/stock.php'
        ];
    }
    
    // Overdue repairs
    if (count($overdueRepairs) > 0) {
        $highlights[] = [
            'type' => 'overdue_repairs',
            'title' => 'Overdue Repairs',
            'count' => count($overdueRepairs),
            'severity' => 'danger',
            'message' => count($overdueRepairs) . ' repair(s) past expected return date',
            'action_url' => '../inventory/repairs.php'
        ];
    }
    
    // Pending acknowledgments
    if (count($pendingAcknowledgments) > 0) {
        $highlights[] = [
            'type' => 'pending_acknowledgments',
            'title' => 'Pending Acknowledgments',
            'count' => count($pendingAcknowledgments),
            'severity' => 'info',
            'message' => count($pendingAcknowledgments) . ' dispatch(es) awaiting acknowledgment',
            'action_url' => '../inventory/dispatch.php'
        ];
    }
    
    return $highlights;
}

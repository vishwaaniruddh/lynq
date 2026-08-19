<?php
/**
 * Serial Number Configuration Status API
 * GET /api/configuration/serial_status.php
 * 
 * Returns the configuration status for a list of serial numbers.
 * Used by the dispatch serial picker to distinguish configured vs unconfigured routers.
 * Only applies to router products — SIM cards and other non-router products
 * return is_router_product=false with empty statuses.
 * 
 * Query Parameters:
 * - serials[]: Array of serial numbers to check (e.g. serials[]=SN001&serials[]=SN002)
 * - product_id: (optional) Product ID to determine if config check is needed
 * 
 * Response:
 * {
 *   success: true,
 *   data: {
 *     is_router_product: true|false,
 *     statuses: {
 *       "SN001": "configured",
 *       "SN002": "unconfigured"
 *     }
 *   }
 * }
 * 
 * Accessible to any authenticated user (not just ADV).
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';

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
    $user = $authMiddleware->requireAuth(); // Any authenticated user

    $db = DatabaseConfig::getInstance();

    // Get product_id to determine if this is a router product
    $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;

    // If product_id provided, check if it's a router product
    // Router products = serializable products in non-SIM categories (categories whose name doesn't contain 'SIM')
    $isRouterProduct = false;
    if ($productId) {
        $productRow = $db->getResults(
            "SELECT p.id, c.name as category_name FROM products p 
             LEFT JOIN product_categories c ON p.category_id = c.id 
             WHERE p.id = ? AND p.is_serializable = 1 LIMIT 1",
            [$productId], 'i'
        );
        if (!empty($productRow)) {
            $categoryName = strtolower($productRow[0]['category_name'] ?? '');
            // Not a router if category suggests SIM/telecom-only (no IP configuration needed)
            $nonRouterKeywords = ['sim', 'sim card', 'simcard'];
            $isNonRouter = false;
            foreach ($nonRouterKeywords as $kw) {
                if (strpos($categoryName, $kw) !== false) {
                    $isNonRouter = true;
                    break;
                }
            }
            $isRouterProduct = !$isNonRouter;
        }
    } else {
        // No product_id provided — check based on serials only (fall back to old behavior)
        $isRouterProduct = true;
    }

    // If not a router product, return immediately with no statuses
    if (!$isRouterProduct) {
        ApiResponse::success([
            'is_router_product' => false,
            'statuses' => []
        ], 'Not a router product — no configuration check needed');
    }

    // Get serial numbers from query params
    $serials = isset($_GET['serials']) && is_array($_GET['serials'])
        ? array_values(array_unique(array_filter(array_map('trim', $_GET['serials']))))
        : [];

    if (empty($serials)) {
        ApiResponse::success([
            'is_router_product' => true,
            'statuses' => []
        ], 'No serial numbers provided');
    }

    // Limit to 500 serials per request
    $serials = array_slice($serials, 0, 500);

    // Check which serial numbers have an active IP binding (i.e., are configured)
    $placeholders = implode(',', array_fill(0, count($serials), '?'));
    $types = str_repeat('s', count($serials));

    $sql = "SELECT rib.router_serial_number, rib.site_id, s.site_name 
            FROM router_ip_bindings rib
            LEFT JOIN sites s ON rib.site_id = s.id
            WHERE rib.status = 'active' 
            AND rib.router_serial_number IN ($placeholders)";

    $results = $db->getResults($sql, $serials, $types);

    // Build set of configured serials
    $configuredSet = [];
    if ($results) {
        foreach ($results as $row) {
            $configuredSet[$row['router_serial_number']] = [
                'status' => 'configured',
                'site_id' => $row['site_id'] !== null ? (int)$row['site_id'] : null,
                'site_name' => $row['site_name']
            ];
        }
    }

    // Build status map for all requested serials
    $statuses = [];
    foreach ($serials as $serial) {
        if (isset($configuredSet[$serial])) {
            $statuses[$serial] = $configuredSet[$serial];
        } else {
            $statuses[$serial] = [
                'status' => 'unconfigured',
                'site_id' => null,
                'site_name' => null
            ];
        }
    }

    $authMiddleware->logApiAccess($user['id'], '/api/configuration/serial_status', 'GET', [
        'product_id'   => $productId,
        'serial_count' => count($serials)
    ]);

    ApiResponse::success([
        'is_router_product' => true,
        'statuses' => $statuses
    ], 'Serial statuses retrieved successfully');

} catch (Exception $e) {
    error_log("Serial Status API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve serial statuses');
}

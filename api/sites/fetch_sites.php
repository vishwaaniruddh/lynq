<?php
/**
 * Fetch Active Sites API Endpoint
 * GET /api/sites/fetch_sites.php
 * 
 * Returns only id and site_name for active sites
 */

// Prevent PHP errors from outputting HTML and corrupting JSON responses
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';

// Discard any output generated during includes (e.g., PHP warnings)
ob_end_clean();

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication
    $user = $authMiddleware->requireAuth();
    
    $db = DatabaseConfig::getInstance();
    
    $isAdv = strtoupper($user['company_type'] ?? '') === 'ADV';
    $companyParam = isset($_GET['company']) ? strtolower(trim($_GET['company'])) : null;
    $contractorId = isset($_GET['contractor_id']) && (int)$_GET['contractor_id'] > 0 ? (int)$_GET['contractor_id'] : null;
    $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : (int)($user['company_id'] ?? 0);
    
    // Build query to fetch only id and site_name for active sites
    if ($companyParam === 'adv' || ($isAdv && $contractorId === null && !isset($_GET['company_id']))) {
        $sql = "SELECT id, site_name FROM sites WHERE status = 'active' ORDER BY site_name ASC";
        $params = [];
        $types = '';
    } elseif ($contractorId !== null) {
        $sql = "SELECT DISTINCT s.id, s.site_name FROM sites s 
                INNER JOIN site_delegations sd ON s.id = sd.site_id AND sd.status IN ('pending', 'accepted')
                WHERE s.status = 'active' AND sd.contractor_id = ? 
                ORDER BY s.site_name ASC";
        $params = [$contractorId];
        $types = 'i';
    } else {
        $sql = "SELECT id, site_name FROM sites WHERE status = 'active' AND company_id = ? ORDER BY site_name ASC";
        $params = [$companyId];
        $types = 'i';
    }

    
    $sites = $db->getResults($sql, $params, $types);
    
    // Format response to strictly return id and site_name
    $formattedSites = array_map(function($site) {
        return [
            'id' => (int)$site['id'],
            'site_name' => $site['site_name']
        ];
    }, $sites);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/sites/fetch_sites.php', 'GET', [
        'count' => count($formattedSites)
    ]);
    
    ApiResponse::success([
        'sites' => $formattedSites,
        'total' => count($formattedSites)
    ], 'Active sites fetched successfully');
    
} catch (Exception $e) {
    error_log("Fetch Sites API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to fetch sites');
}

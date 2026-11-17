<?php
/**
 * Mobile & App API Endpoint: Site Email Recipients
 * 
 * Endpoint:
 * GET  /api/app/site_emails.php?site_id={id}
 * POST /api/app/site_emails.php (json: {"site_id": 1, "mode": "combined"})
 * 
 * Query / Body Parameters:
 * - site_id (or id or site_name): Site identifier (required)
 * - mode: 'combined' (default: merges across individual > city > state > zone)
 *         'override' (strictly uses highest-weight active rule)
 * 
 * Precedence Order:
 * Individual Site (Weight 1) > City (Weight 2) > State (Weight 3) > Zone (Weight 4)
 */

// Disable direct HTML error display to keep JSON clean
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../models/SiteEmailSetting.php';
require_once __DIR__ . '/../../utils/LocationParser.php';

ob_end_clean();

// Set CORS and headers
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

header('Content-Type: application/json; charset=utf-8');

// Only allow GET and POST
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET or POST.'
    ]);
    exit;
}

try {
    // Optional rate limit and authentication check
    $authMiddleware = new ApiAuthMiddleware();
    try {
        $authMiddleware->checkRateLimit();
    } catch (Exception $e) {
        // Continue if rate limit store not available
    }

    // Authenticate if credentials provided (supports JWT, Bearer token, Cookie, Session)
    $user = null;
    try {
        $user = $authMiddleware->authenticate();
    } catch (Exception $e) {
        // Non-blocking: proceed with email lookup
    }

    // Extract site identifier from GET, JSON body, or form POST
    $siteIdentifier = null;
    $mode = 'combined';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $siteIdentifier = $_GET['site_id'] ?? $_GET['id'] ?? $_GET['site_name'] ?? null;
        if (isset($_GET['mode'])) {
            $mode = strtolower(trim($_GET['mode']));
        }
    } else {
        // POST: Check raw JSON body first
        $rawInput = file_get_contents('php://input');
        $json = json_decode($rawInput, true);

        if (is_array($json)) {
            $siteIdentifier = $json['site_id'] ?? $json['id'] ?? $json['site_name'] ?? null;
            if (isset($json['mode'])) {
                $mode = strtolower(trim($json['mode']));
            }
        }

        // Fallback to $_POST form parameters
        if (empty($siteIdentifier)) {
            $siteIdentifier = $_POST['site_id'] ?? $_POST['id'] ?? $_POST['site_name'] ?? null;
            if (isset($_POST['mode'])) {
                $mode = strtolower(trim($_POST['mode']));
            }
        }
    }

    // Validate site identifier
    if ($siteIdentifier === null || trim((string)$siteIdentifier) === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Site ID is required. Please provide site_id, id, or site_name via GET query parameter or POST body.',
            'example' => [
                'GET' => '/api/app/site_emails.php?site_id=1',
                'POST' => [
                    'site_id' => 1,
                    'mode' => 'combined'
                ]
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Resolve recipients using SiteEmailSetting model
    $emailSettingModel = new SiteEmailSetting();
    $resolution = $emailSettingModel->getEffectiveEmailsForSite($siteIdentifier);

    if (empty($resolution['found'])) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Site not found for identifier: ' . htmlspecialchars((string)$siteIdentifier),
            'site_identifier' => $siteIdentifier
        ], JSON_PRETTY_PRINT);
        exit;
    }

    $site = $resolution['site'];
    $isOverrideMode = ($mode === 'override');

    // Pick recipients based on mode
    if ($isOverrideMode) {
        $selectedTo = $resolution['override_recipients']['to'] ?? [];
        $selectedCc = $resolution['override_recipients']['cc'] ?? [];
        $selectedToString = $resolution['override_recipients']['to_string'] ?? '';
        $selectedCcString = $resolution['override_recipients']['cc_string'] ?? '';
    } else {
        $selectedTo = $resolution['effective_recipients']['to'] ?? [];
        $selectedCc = $resolution['effective_recipients']['cc'] ?? [];
        $selectedToString = $resolution['effective_recipients']['to_string'] ?? '';
        $selectedCcString = $resolution['effective_recipients']['cc_string'] ?? '';
    }

    // Log API access if user authenticated
    if ($user && isset($user['id'])) {
        try {
            $authMiddleware->logApiAccess($user['id'], '/api/app/site_emails.php', $_SERVER['REQUEST_METHOD'], [
                'site_id' => $site['id'],
                'mode' => $mode
            ]);
        } catch (Exception $e) {}
    }

    // Build structured output
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'site_id' => (int)$site['id'],
        'site_name' => $site['site_name'],
        'bank_name' => $site['bank_name'] ?? null,
        'customer_name' => $site['customer_name'] ?? null,
        'location' => [
            'city' => $site['city'] ?? '',
            'state' => $site['state'] ?? '',
            'zone' => $site['zone'] ?? '',
            'address' => $site['address'] ?? ''
        ],
        'mode' => $isOverrideMode ? 'override' : 'combined',
        'weight_hierarchy' => 'individual (1) > city (2) > state (3) > zone (4)',
        'primary_active_level' => $resolution['highest_priority_level'] ?? null,
        'to' => $selectedTo,
        'cc' => $selectedCc,
        'to_string' => $selectedToString,
        'cc_string' => $selectedCcString,
        'counts' => [
            'to_count' => count($selectedTo),
            'cc_count' => count($selectedCc)
        ],
        'details' => [
            'levels' => $resolution['levels'] ?? [],
            'override_recipients' => $resolution['override_recipients'] ?? [],
            'combined_recipients' => $resolution['effective_recipients'] ?? []
        ]
    ], JSON_PRETTY_PRINT);
    exit;

} catch (Exception $e) {
    error_log("Site Emails API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error resolving site email recipients',
        'error' => $e->getMessage()
    ]);
    exit;
}

<?php
/**
 * Sites Dropdown Masters API Endpoint
 * GET /api/sites/form_options.php - Retrieves master lists for form dropdowns
 * 
 * Query Parameters:
 * - country_id: Optional, fetches states for that country
 * - state_id: Optional, fetches cities for that state
 */

// Prevent PHP errors from outputting HTML and corrupting JSON responses
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/LocationService.php';
require_once __DIR__ . '/../../services/BankService.php';
require_once __DIR__ . '/../../services/ProjectService.php';
require_once __DIR__ . '/../../models/CustomForm.php';

// Discard any output generated during includes
ob_end_clean();

ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access
    $user = $authMiddleware->requireAdvUser();
    
    // Fetch dynamic custom form schema for a project & purpose
    if (isset($_GET['fetch_custom_form']) && !empty($_GET['purpose'])) {
        $purpose = trim($_GET['purpose']);
        $projectId = isset($_GET['project_id']) && (int)$_GET['project_id'] > 0 ? (int)$_GET['project_id'] : null;
        
        $customFormModel = new CustomForm();
        $formSchema = $customFormModel->findFormForProject($purpose, $projectId);
        
        ApiResponse::success(['form' => $formSchema]);
        exit;
    }
    
    $locationService = new LocationService();
    
    // Cascading: States by Country
    if (isset($_GET['country_id']) && (int)$_GET['country_id'] > 0) {
        $countryId = (int)$_GET['country_id'];
        $states = $locationService->getStatesByCountry($countryId);
        ApiResponse::success(['states' => $states]);
        exit;
    }
    
    // Cascading: Cities by State
    if (isset($_GET['state_id']) && (int)$_GET['state_id'] > 0) {
        $stateId = (int)$_GET['state_id'];
        $cities = $locationService->getCitiesByState($stateId);
        ApiResponse::success(['cities' => $cities]);
        exit;
    }
    
    // Load initial dropdown data
    $countries = $locationService->getActiveCountries();
    $lhos = $locationService->getActiveLhos();
    $zones = $locationService->getActiveZones();
    
    $bankService = new BankService();
    $banks = $bankService->getActiveList();
    
    $customerService = new CustomerService();
    $customers = $customerService->getActiveList();
    
    $projectService = new ProjectService();
    $projectsRes = $projectService->getAll(['status' => 1]);
    $projects = $projectsRes['data'] ?? [];
    
    // Get site counts per project
    $db = DatabaseConfig::getInstance();
    $pCountsRes = $db->getResults("SELECT project_id, COUNT(*) as site_count FROM sites WHERE status != 'deleted' GROUP BY project_id");
    $pCounts = [];
    foreach ($pCountsRes as $pc) {
        if ($pc['project_id']) $pCounts[$pc['project_id']] = (int)$pc['site_count'];
    }
    foreach ($projects as &$p) {
        $p['site_count'] = $pCounts[$p['id']] ?? 0;
    }
    unset($p);
    
    ApiResponse::success([
        'countries' => $countries,
        'lhos' => $lhos,
        'banks' => $banks,
        'customers' => $customers,
        'projects' => $projects,
        'zones' => $zones
    ]);
    
} catch (Exception $e) {
    error_log("Sites Form Options API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to load form options');
}

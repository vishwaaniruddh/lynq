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
require_once __DIR__ . '/../../services/CustomerService.php';

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
    
    ApiResponse::success([
        'countries' => $countries,
        'lhos' => $lhos,
        'banks' => $banks,
        'customers' => $customers,
        'zones' => $zones
    ]);
    
} catch (Exception $e) {
    error_log("Sites Form Options API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to load form options');
}

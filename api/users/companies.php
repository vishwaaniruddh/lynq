<?php
/**
 * Companies API
 * GET /api/users/companies.php
 * 
 * Returns list of companies for dispatch destination selection
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../repositories/CompanyRepository.php';

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
    
    $companyRepository = new CompanyRepository();
    $companyRepository->disableCompanyFilter();
    
    // Get query parameters
    $type = $_GET['type'] ?? null; // Filter by company type (ADV, CONTRACTOR)
    $status = $_GET['status'] ?? 'active';
    $limit = min(500, max(1, (int)($_GET['limit'] ?? 200)));
    
    // Build filters
    $filters = [];
    if ($type) {
        $filters['type'] = strtoupper($type);
    }
    if ($status) {
        $filters['status'] = $status;
    }
    
    // Get companies
    $companies = $companyRepository->findAll($filters, 'name ASC');
    
    // Limit results
    $companies = array_slice($companies, 0, $limit);
    
    // Format response
    $formattedCompanies = array_map(function($company) {
        return [
            'id' => $company['id'],
            'name' => $company['name'],
            'type' => $company['type'],
            'status' => $company['status'] ?? 'active',
            'email' => $company['email'] ?? null,
            'phone' => $company['phone'] ?? null
        ];
    }, $companies);
    
    ApiResponse::success([
        'companies' => $formattedCompanies,
        'total' => count($formattedCompanies)
    ], 'Companies retrieved successfully');
    
} catch (Exception $e) {
    error_log("Companies API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve companies');
}

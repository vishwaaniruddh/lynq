<?php
/**
 * Feasibility Details API
 * 
 * GET /api/feasibility/details.php?assignment_id={id}
 * Returns feasibility check details for a given assignment
 * 
 * Accessible by ADV users and contractors (for their own assignments)
 * 
 * Requirements: 8.1
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../services/FeasibilityService.php';
require_once __DIR__ . '/../../repositories/FeasibilityCheckRepository.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

header('Content-Type: application/json');

try {
    $sessionService = new SessionService();
    
    if (!$sessionService->isLoggedIn()) {
        ApiResponse::unauthorized('Authentication required');
    }
    
    $user = $sessionService->getCurrentUser();
    $userId = $user['id'];
    
    // Check if user is ADV or contractor admin
    $isAdv = isAdvUser($userId);
    $isContractorAdminUser = isContractorAdmin($userId);
    
    if (!$isAdv && !$isContractorAdminUser) {
        ApiResponse::forbidden('Access denied. ADV or Contractor Admin users only.');
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::methodNotAllowed(['GET']);
    }
    
    $assignmentId = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;
    
    if (!$assignmentId) {
        ApiResponse::error('INVALID_REQUEST', 'Assignment ID is required', 400);
    }
    
    $feasibilityRepo = new FeasibilityCheckRepository();
    
    // Get feasibility check by assignment
    $feasibility = $feasibilityRepo->findByAssignment($assignmentId);
    
    if (!$feasibility) {
        ApiResponse::notFound('Feasibility check not found for this assignment');
    }
    
    // For contractor admins, verify the assignment belongs to their company
    if ($isContractorAdminUser && !$isAdv) {
        $companyId = $user['company_id'];
        
        // Get assignment to check company - contractor_id is in site_delegations table
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT ea.id, sd.contractor_id 
            FROM engineer_assignments ea 
            LEFT JOIN site_delegations sd ON ea.delegation_id = sd.id
            WHERE ea.id = ?
        ");
        $stmt->execute([$assignmentId]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assignment || $assignment['contractor_id'] != $companyId) {
            ApiResponse::forbidden('Access denied. This assignment does not belong to your company.');
        }
    }
    
    // Get site information
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT 
            s.site_name, s.address, s.city, s.state, s.lho,
            s.latitude as site_latitude, s.longitude as site_longitude,
            s.bank_name, s.customer_name
        FROM sites s
        WHERE s.id = ?
    ");
    $stmt->execute([$feasibility['site_id']]);
    $siteInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Merge site info with feasibility data
    $result = array_merge($feasibility, $siteInfo ?: []);
    
    ApiResponse::success($result);
    
} catch (Exception $e) {
    error_log("Feasibility Details API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while fetching feasibility details: ' . $e->getMessage());
}

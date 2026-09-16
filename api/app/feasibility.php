<?php
/**
 * Mobile App Feasibility API Endpoint
 * GET /api/app/feasibility.php?assignment_id={id} - Get feasibility with full review and rejection feedback
 * POST /api/app/feasibility.php - Submit or resubmit feasibility check
 * POST /api/app/feasibility.php?action=upload - Upload feasibility snaps
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/FeasibilityService.php';
require_once __DIR__ . '/../../services/FeasibilityReviewService.php';
require_once __DIR__ . '/../../services/ImageUploadService.php';
require_once __DIR__ . '/../../services/SiteAccessService.php';
require_once __DIR__ . '/../../repositories/FeasibilityReviewRepository.php';
require_once __DIR__ . '/../../models/CustomForm.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    $user = $authMiddleware->requireAuth();
    
    // Verify user is an engineer
    if (!isEngineerUser($user['id'])) {
        ApiResponse::forbidden('Access denied. Engineer users only.');
    }
    
    $feasibilityService = new FeasibilityService();
    $imageUploadService = new ImageUploadService();
    $reviewRepo = new FeasibilityReviewRepository();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($feasibilityService, $reviewRepo, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        if ($action === 'upload') {
            handleUploadRequest($imageUploadService, $feasibilityService, $authMiddleware, $user);
        } else {
            handlePostRequest($feasibilityService, $authMiddleware, $user);
        }
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
} catch (Exception $e) {
    error_log("Mobile Feasibility API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request: ' . $e->getMessage());
}

/**
 * Handle GET request - Retrieve feasibility data and rejection feedback
 */
function handleGetRequest($feasibilityService, $reviewRepo, $authMiddleware, $user) {
    $assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
    
    if ($assignmentId <= 0 && isset($_GET['site_id'])) {
        $siteId = (int)$_GET['site_id'];
        $db = DatabaseConfig::getInstance();
        $sql = "SELECT id FROM engineer_assignments WHERE site_id = ? AND engineer_id = ? ORDER BY id DESC LIMIT 1";
        $res = $db->getResults($sql, [$siteId, $user['id']], 'ii');
        if (!empty($res)) {
            $assignmentId = (int)$res[0]['id'];
        }
    }
    
    if ($assignmentId <= 0) {
        ApiResponse::validationError(
            ['assignment_id' => ['Assignment ID or Site ID is required']],
            'Validation failed'
        );
    }
    
    // Validate engineer assignment access
    $siteAccessService = new SiteAccessService();
    $accessResult = $siteAccessService->validateEngineerAssignmentAccess($user['id'], $assignmentId);
    if (!$accessResult['success']) {
        ApiResponse::forbidden($accessResult['message']);
    }
    
    $feasibility = $feasibilityService->getFeasibilityByAssignment($assignmentId);
    $siteInfo = $feasibilityService->getMasterSiteInfo($assignmentId);
    $status = $feasibilityService->getFeasibilityStatus($assignmentId);
    
    // Check review and rejection feedback
    $rejectionInfo = null;
    $reviewHistory = [];
    
    if ($feasibility && !empty($feasibility['id'])) {
        $feasibilityId = (int)$feasibility['id'];
        $reviews = $reviewRepo->findByFeasibility($feasibilityId);
        $reviewHistory = $reviews;
        
        $latestRejection = $reviewRepo->getLatestRejection($feasibilityId);
        $currentApprovalStatus = $feasibility['approval_status'] ?? $status;
        $isRejected = in_array($currentApprovalStatus, ['contractor_rejected', 'adv_rejected']);
        
        if ($isRejected && $latestRejection) {
            $rejectedSections = $latestRejection['rejected_sections'] ?? [];
            if (is_string($rejectedSections)) {
                $rejectedSections = json_decode($rejectedSections, true) ?? [];
            }
            
            $rejectionInfo = [
                'is_rejected' => true,
                'approval_status' => $currentApprovalStatus,
                'reviewer_role' => $latestRejection['reviewer_role'] ?? 'contractor_admin',
                'reviewer_name' => $latestRejection['reviewer_name'] ?? 'Contractor Supervisor',
                'review_type' => $latestRejection['review_type'] ?? 'rejection',
                'rejection_type' => $latestRejection['rejection_type'] ?? 'section_specific',
                'rejected_sections' => $rejectedSections,
                'reason' => $latestRejection['reason'] ?? '',
                'comments' => $latestRejection['comments'] ?? '',
                'reviewed_at' => $latestRejection['reviewed_at'] ?? $latestRejection['created_at'] ?? null,
            ];
        }
    }
    
    // Resolve dynamic Custom Form for project
    $customFormModel = new CustomForm();
    $targetProjectId = !empty($siteInfo['project_id']) ? (int)$siteInfo['project_id'] : null;
    $customForm = $customFormModel->findFormForProject('feasibility', $targetProjectId);

    $authMiddleware->logApiAccess($user['id'], '/api/app/feasibility', 'GET', [
        'assignment_id' => $assignmentId
    ]);
    
    ApiResponse::success([
        'assignment_id' => $assignmentId,
        'feasibility_status' => $status,
        'site_info' => $siteInfo,
        'feasibility' => $feasibility,
        'custom_form' => $customForm,
        'rejection_info' => $rejectionInfo,
        'review_history' => $reviewHistory,
    ], 'Feasibility details retrieved successfully');
}

/**
 * Handle POST request - Submit or resubmit feasibility survey
 */
function handlePostRequest($feasibilityService, $authMiddleware, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input)) {
        $input = $_POST;
    }
    
    $assignmentId = isset($input['assignment_id']) ? (int)$input['assignment_id'] : 0;
    
    if ($assignmentId <= 0 && isset($input['site_id'])) {
        $siteId = (int)$input['site_id'];
        $db = DatabaseConfig::getInstance();
        $sql = "SELECT id FROM engineer_assignments WHERE site_id = ? AND engineer_id = ? ORDER BY id DESC LIMIT 1";
        $res = $db->getResults($sql, [$siteId, $user['id']], 'ii');
        if (!empty($res)) {
            $assignmentId = (int)$res[0]['id'];
        }
    }
    
    if ($assignmentId <= 0) {
        ApiResponse::validationError(
            ['assignment_id' => ['Assignment ID is required']],
            'Validation failed'
        );
    }
    
    $siteAccessService = new SiteAccessService();
    $accessResult = $siteAccessService->validateEngineerAssignmentAccess($user['id'], $assignmentId);
    if (!$accessResult['success']) {
        ApiResponse::forbidden($accessResult['message']);
    }
    
    $input['assignment_id'] = $assignmentId;
    $existing = $feasibilityService->getFeasibilityByAssignment($assignmentId);
    
    if ($existing && !empty($existing['id'])) {
        $feasibilityId = (int)$existing['id'];
        
        // Update existing record
        $feasibilityService->updateFeasibility($feasibilityId, $input);
        
        // If status was contractor_rejected or adv_rejected, reset to pending_contractor_review
        $db = DatabaseConfig::getInstance();
        $resetStatusSql = "UPDATE engineer_assignments SET feasibility_status = 'pending_contractor_review', updated_at = NOW() WHERE id = ?";
        $db->executeQuery($resetStatusSql, [$assignmentId], 'i');
        
        $resetCheckSql = "UPDATE feasibility_checks SET approval_status = 'pending_contractor_review', updated_at = NOW() WHERE id = ?";
        $db->executeQuery($resetCheckSql, [$feasibilityId], 'i');

        $updatedData = $feasibilityService->getFeasibilityCheck($feasibilityId);
        if (!$updatedData) {
            $updatedData = array_merge($existing, $input, ['id' => $feasibilityId, 'assignment_id' => $assignmentId]);
        }
        
        $authMiddleware->logApiAccess($user['id'], '/api/app/feasibility', 'POST', [
            'assignment_id' => $assignmentId,
            'feasibility_id' => $feasibilityId,
            'action' => 'resubmit'
        ]);
        
        ApiResponse::success($updatedData, 'Feasibility check updated and resubmitted successfully');
        return;
    } else {
        // Check if ADA exists, if not auto-create basic ADA for mobile app workflow
        $adaRepo = new FeasibilityADARepository();
        if (!$adaRepo->hasADA($assignmentId)) {
            try {
                $adaRepo->create([
                    'assignment_id' => $assignmentId,
                    'latitude' => isset($input['latitude']) ? (float)$input['latitude'] : 0.0,
                    'longitude' => isset($input['longitude']) ? (float)$input['longitude'] : 0.0,
                    'submitted_by' => $user['id'],
                    'ada_datetime' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                // Ignore if created concurrently or already exists
            }
        }

        // Create new feasibility check
        $result = $feasibilityService->createFeasibilityCheck($assignmentId, $input, $user['id']);
        
        if ($result['success']) {
            $feasibilityId = $result['data']['id'] ?? null;
            if ($feasibilityId) {
                $db = DatabaseConfig::getInstance();
                $updateStatusSql = "UPDATE engineer_assignments SET feasibility_status = 'pending_contractor_review', updated_at = NOW() WHERE id = ?";
                $db->executeQuery($updateStatusSql, [$assignmentId], 'i');
                
                $updateCheckSql = "UPDATE feasibility_checks SET approval_status = 'pending_contractor_review', updated_at = NOW() WHERE id = ?";
                $db->executeQuery($updateCheckSql, [$feasibilityId], 'i');
            }

            $authMiddleware->logApiAccess($user['id'], '/api/app/feasibility', 'POST', [
                'assignment_id' => $assignmentId,
                'feasibility_id' => $feasibilityId
            ]);
            
            ApiResponse::success($result['data'], $result['message'] ?? 'Feasibility check created successfully', 201);
            return;
        } else {
            ApiResponse::validationError(
                $result['errors'] ?? ['form' => [$result['message']]],
                $result['message'] ?? 'Failed to submit feasibility check'
            );
            return;
        }
    }
}

/**
 * Handle image upload for feasibility
 */
function handleUploadRequest($imageUploadService, $feasibilityService, $authMiddleware, $user) {
    $feasibilityId = isset($_POST['feasibility_id']) ? (int)$_POST['feasibility_id'] : 0;
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    
    if ($feasibilityId <= 0) {
        ApiResponse::validationError(['feasibility_id' => ['Feasibility ID is required']], 'Validation failed');
        return;
    }
    
    if (empty($category)) {
        ApiResponse::validationError(['category' => ['Image category is required']], 'Validation failed');
        return;
    }
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        ApiResponse::validationError(['file' => ['Image file is required']], 'Validation failed');
        return;
    }
    
    $uploadResult = $imageUploadService->uploadImage($_FILES['file'], $category, $feasibilityId);
    
    if ($uploadResult['success']) {
        $path = $uploadResult['data']['path'];
        
        $categoryColMap = [
            'backroom_network_snap' => 'backroom_network_snap',
            'ups_available_snap' => 'ups_available_snap',
            'no_of_ups_snap' => 'no_of_ups_snap',
            'ups_working_snap' => 'ups_working_snap',
            'power_socket_availability_snap' => 'power_socket_availability_snap',
            'earthing_snap' => 'earthing_snap',
            'power_fluctuation_snap' => 'power_fluctuation_snap',
            'router_antenna_snap' => 'router_antenna_snap',
            'antenna_routing_snap' => 'antenna_routing_snap',
            'remarks_snap' => 'remarks_snap',
            'backroom_network' => 'backroom_network_snap',
            'ups_available' => 'ups_available_snap',
            'earthing' => 'earthing_snap',
            'router_antenna' => 'router_antenna_snap',
            'antenna_routing' => 'antenna_routing_snap',
            'remarks' => 'remarks_snap'
        ];
        
        $colName = $categoryColMap[$category] ?? $category;
        
        $db = DatabaseConfig::getInstance();
        $db->executeQuery("UPDATE feasibility_checks SET `{$colName}` = ?, updated_at = NOW() WHERE id = ?", [$path, $feasibilityId], 'si');
        
        ApiResponse::success([
            'path' => $path,
            'filename' => $uploadResult['data']['filename'],
            'category' => $category,
            'column' => $colName
        ], 'Image uploaded and saved successfully');
    } else {
        ApiResponse::validationError(['file' => [$uploadResult['message']]], $uploadResult['message']);
    }
}

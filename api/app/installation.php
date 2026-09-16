<?php
/**
 * Mobile App Installation API Endpoint
 * 
 * GET /api/app/installation.php - List installations assigned to the logged-in engineer
 * GET /api/app/installation.php?installation_id={id} - Get single installation details with checklist & review remarks
 * POST /api/app/installation.php - Save or submit installation checklist, ETA, ADA, or materials confirmation
 * POST /api/app/installation.php?action=upload - Upload installation snaps
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/InstallationService.php';
require_once __DIR__ . '/../../services/InstallationETAService.php';
require_once __DIR__ . '/../../services/InstallationAssignmentService.php';
require_once __DIR__ . '/../../services/MaterialReceiptService.php';
require_once __DIR__ . '/../../services/ImageUploadService.php';
require_once __DIR__ . '/../../repositories/InstallationRepository.php';
require_once __DIR__ . '/../../repositories/InstallationSectionRemarkRepository.php';
require_once __DIR__ . '/../../models/Installation.php';
require_once __DIR__ . '/../../models/CustomForm.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    $user = $authMiddleware->requireAuth();
    
    // Verify user is an engineer or contractor/admin
    $engineerId = (int)$user['id'];
    
    $installationRepo = new InstallationRepository();
    $installationService = new InstallationService();
    $etaService = new InstallationETAService();
    $remarkRepo = new InstallationSectionRemarkRepository();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($installationRepo, $remarkRepo, $engineerId);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_GET['action'] ?? '';
        if ($action === 'upload') {
            handleUploadRequest($installationRepo, $engineerId);
        } else {
            handlePostRequest($installationService, $etaService, $installationRepo, $engineerId);
        }
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
} catch (Exception $e) {
    error_log("Mobile Installation API Error: " . $e->getMessage() . " | " . $e->getTraceAsString());
    ApiResponse::serverError('Failed to process installation request: ' . $e->getMessage());
}

/**
 * Handle GET requests
 */
function handleGetRequest($installationRepo, $remarkRepo, int $engineerId) {
    $installationId = isset($_GET['installation_id']) ? (int)$_GET['installation_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
    
    if ($installationId > 0) {
        // Fetch specific installation
        $installation = $installationRepo->findById($installationId);
        if (!$installation) {
            ApiResponse::notFound('Installation record not found');
            return;
        }
        
        // Ensure this installation is assigned to this engineer
        if ((int)$installation['assigned_engineer_id'] !== $engineerId) {
            ApiResponse::forbidden('This installation is not assigned to you');
            return;
        }
        
        $db = DatabaseConfig::getInstance();
        
        // Fetch site info with project details
        $siteInfo = null;
        if (!empty($installation['site_id'])) {
            $siteRes = $db->getResults("SELECT s.*, p.name as project_name, p.code as project_code 
                                       FROM sites s 
                                       LEFT JOIN projects p ON s.project_id = p.id 
                                       WHERE s.id = ? LIMIT 1", [(int)$installation['site_id']], 'i');
            if (!empty($siteRes)) {
                $siteInfo = $siteRes[0];
            }
        }
        
        // Fetch custom form schema for project
        $customFormModel = new CustomForm();
        $targetProjectId = !empty($siteInfo['project_id']) ? (int)$siteInfo['project_id'] : null;
        $customForm = $customFormModel->findFormForProject('installation', $targetProjectId);
        
        // Fetch section remarks / rejections if any
        $remarks = $remarkRepo->findByInstallationId($installationId);
        $installation['section_remarks'] = $remarks;
        
        // Check if there are any rejected sections
        $rejectedRemarks = array_filter($remarks, function($r) {
            return ($r['status'] ?? '') === 'rejected';
        });
        $hasRejections = !empty($rejectedRemarks) || in_array($installation['status'], ['contractor_rejected', 'adv_rejected']);
        $rejectedSections = array_values($rejectedRemarks);
        
        // Approval Locking Rule:
        // is_approved = true strictly when contractor_approved or adv_approved
        $isApproved = in_array($installation['status'], ['contractor_approved', 'adv_approved']);
        $canEdit = !$isApproved && !in_array($installation['status'], ['pending_assignment', 'pending_eta', 'pending_ada', 'pending_materials']);
        $isPendingMaterials = in_array($installation['status'], ['pending_materials', 'pending_assignment', 'pending_eta', 'pending_ada']);
        
        // Merge helper fields into installation
        $installation['site_info'] = $siteInfo;
        $installation['custom_form'] = $customForm;
        $installation['has_rejections'] = $hasRejections;
        $installation['rejected_sections'] = $rejectedSections;
        $installation['is_approved'] = $isApproved;
        $installation['can_edit'] = $canEdit;
        $installation['is_pending_materials'] = $isPendingMaterials;
        
        ApiResponse::success([
            'installation' => $installation,
            'site_info' => $siteInfo,
            'custom_form' => $customForm,
            'section_remarks' => $remarks,
            'has_rejections' => $hasRejections,
            'rejected_sections' => $rejectedSections,
            'is_approved' => $isApproved,
            'can_edit' => $canEdit,
            'is_pending_materials' => $isPendingMaterials,
        ], 'Installation details retrieved');
        return;
    }
    
    // List assigned installations for this engineer
    $db = DatabaseConfig::getInstance();
    
    $statusFilter = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $sql = "SELECT i.*, 
                   s.site_name, s.bank_name, s.customer_name, s.city as site_city, s.state as site_state, 
                   s.address as site_address, s.lho as site_lho,
                   c.name as contractor_name
            FROM installations i
            LEFT JOIN sites s ON i.site_id = s.id
            LEFT JOIN companies c ON i.contractor_id = c.id
            WHERE i.assigned_engineer_id = ?";
    
    $params = [$engineerId];
    $types = 'i';
    
    if ($statusFilter && $statusFilter !== 'all') {
        if ($statusFilter === 'pending') {
            $sql .= " AND i.status IN ('pending_assignment', 'pending_eta', 'pending_ada', 'pending_materials', 'materials_received')";
        } elseif ($statusFilter === 'in_progress') {
            $sql .= " AND i.status IN ('in_progress', 'materials_received')";
        } elseif ($statusFilter === 'completed') {
            $sql .= " AND i.status IN ('submitted', 'pending_contractor_review', 'contractor_approved', 'adv_approved')";
        } else {
            $sql .= " AND i.status = ?";
            $params[] = $statusFilter;
            $types .= 's';
        }
    }
    
    if ($search !== '') {
        $searchTerm = "%{$search}%";
        $sql .= " AND (i.atm_id LIKE ? OR s.site_name LIKE ? OR s.city LIKE ? OR s.bank_name LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ssss';
    }
    
    $sql .= " ORDER BY i.updated_at DESC, i.id DESC";
    
    $installations = $db->getResults($sql, $params, $types);
    
    // Also compute status counts for tabs
    $countsSql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('pending_eta', 'pending_ada', 'pending_materials') THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status IN ('materials_received', 'in_progress') THEN 1 ELSE 0 END) as in_progress_count,
                    SUM(CASE WHEN status = 'submitted' OR status = 'pending_contractor_review' THEN 1 ELSE 0 END) as submitted_count,
                    SUM(CASE WHEN status IN ('contractor_approved', 'adv_approved') THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN status IN ('contractor_rejected', 'adv_rejected') THEN 1 ELSE 0 END) as rejected_count
                  FROM installations 
                  WHERE assigned_engineer_id = ?";
    $countsRes = $db->getResults($countsSql, [$engineerId], 'i');
    $counts = !empty($countsRes) ? $countsRes[0] : [
        'total' => 0,
        'pending_count' => 0,
        'in_progress_count' => 0,
        'submitted_count' => 0,
        'completed_count' => 0,
        'rejected_count' => 0
    ];
    
    ApiResponse::success([
        'installations' => $installations,
        'counts' => $counts,
        'total' => count($installations)
    ], 'Assigned installations retrieved');
}

/**
 * Handle POST requests (Save, Submit, ETA, ADA, Materials)
 */
function handlePostRequest($installationService, $etaService, $installationRepo, int $engineerId) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? 'save';
    $installationId = isset($input['installation_id']) ? (int)$input['installation_id'] : 0;
    
    if ($installationId <= 0) {
        ApiResponse::validationError(['installation_id' => ['Installation ID is required']]);
        return;
    }
    
    // Verify assignment
    $installation = $installationRepo->findById($installationId);
    if (!$installation) {
        ApiResponse::notFound('Installation record not found');
        return;
    }
    
    if ((int)$installation['assigned_engineer_id'] !== $engineerId) {
        ApiResponse::forbidden('You are not authorized to modify this installation');
        return;
    }
    
    if ($action === 'eta') {
        $etaDate = $input['eta_date'] ?? '';
        if (empty($etaDate)) {
            ApiResponse::validationError(['eta_date' => ['ETA date is required']]);
            return;
        }
        $res = $etaService->submitETA($installationId, $etaDate, $engineerId);
        if ($res['success']) {
            ApiResponse::success($res['data'], 'Installation ETA updated successfully');
        } else {
            ApiResponse::error($res['code'] ?? 'ERROR', $res['message'] ?? 'Failed to submit ETA');
        }
        return;
    }
    
    if ($action === 'ada') {
        $adaDate = $input['ada_date'] ?? date('Y-m-d');
        $res = $etaService->submitADA($installationId, $adaDate, $engineerId);
        if ($res['success']) {
            ApiResponse::success($res['data'], 'Arrival (ADA) recorded successfully');
        } else {
            ApiResponse::error($res['code'] ?? 'ERROR', $res['message'] ?? 'Failed to submit ADA');
        }
        return;
    }
    
    if ($action === 'confirm_materials' || $action === 'materials') {
        $materialReceiptService = new MaterialReceiptService();
        $res = $materialReceiptService->confirmMaterialReceipt($installationId, $engineerId);
        if ($res['success']) {
            ApiResponse::success($res['data'], 'Material receipt confirmed successfully');
        } else {
            ApiResponse::error($res['code'] ?? 'ERROR', $res['message'] ?? 'Failed to confirm material receipt');
        }
        return;
    }
    
    if ($action === 'save' || $action === 'commission') {
        // Approval Locking Rule: Prevent modifications if already approved
        if (in_array($installation['status'], ['contractor_approved', 'adv_approved'])) {
            ApiResponse::forbidden('This installation has already been approved and cannot be modified.');
            return;
        }

        // Material Receipt Rule: Installation can only start after materials are received
        if (in_array($installation['status'], ['pending_assignment', 'pending_eta', 'pending_ada', 'pending_materials'])) {
            ApiResponse::forbidden('Installation form cannot be edited or saved until material receipt is confirmed.');
            return;
        }

        // Sanitize allowed fields
        $allowedFields = [
            'router_serial', 'router_make', 'router_model', 'router_fixed', 'router_fixed_remarks', 'router_fixed_snaps',
            'router_status', 'router_status_remarks', 'router_status_snaps',
            'adaptor_installed', 'adaptor_snaps', 'adaptor_status', 'adaptor_status_remarks', 'adaptor_status_snaps',
            'lan_cable_installed', 'lan_cable_install_remark', 'lan_cable_install_snap',
            'lan_cable_status', 'lan_cable_status_not_working_reasons', 'lan_cable_status_remark', 'lan_cable_status_snap',
            'antenna_installed', 'antenna_remarks', 'antenna_snaps', 'antenna_status', 'antenna_status_remarks', 'antenna_status_snaps',
            'gps_installed', 'gps_remarks', 'gps_snaps', 'gps_status', 'gps_status_remarks', 'gps_status_snaps',
            'wifi_installed', 'wifi_remarks', 'wifi_snaps', 'wifi_status', 'wifi_status_remarks', 'wifi_status_snaps',
            'airtel_sim_installed', 'airtel_sim_remarks', 'airtel_sim_snaps', 'airtel_sim_status', 'airtel_sim_status_remarks', 'airtel_sim_status_snaps',
            'vodafone_sim_installed', 'vodafone_sim_remarks', 'vodafone_sim_snaps', 'vodafone_sim_status', 'vodafone_sim_status_remarks', 'vodafone_sim_status_snaps',
            'jio_sim_installed', 'jio_sim_remarks', 'jio_sim_snaps', 'jio_sim_status', 'jio_sim_status_remarks', 'jio_sim_status_snaps',
            'signature_image', 'vendor_stamp', 'engineer_name', 'engineer_number', 'vendor_name'
        ];
        
        $formData = array_intersect_key($input, array_flip($allowedFields));
        
        // Also check if formData was passed nested inside $input['data']
        if (isset($input['data']) && is_array($input['data'])) {
            $nested = array_intersect_key($input['data'], array_flip($allowedFields));
            $formData = array_merge($formData, $nested);
        }
        
        // If status is materials_received, move to in_progress
        if ($installation['status'] === 'materials_received') {
            $installationRepo->update($installationId, ['status' => Installation::STATUS_IN_PROGRESS]);
        }
        
        $res = $installationService->saveInstallationData($installationId, $formData, $engineerId);
        if ($res['success']) {
            ApiResponse::success($res['data'], 'Installation data saved successfully');
        } else {
            ApiResponse::error($res['code'] ?? 'ERROR', $res['message'] ?? 'Failed to save installation');
        }
        return;
    }
    
    if ($action === 'submit') {
        // Approval Locking Rule: Prevent submissions if already approved
        if (in_array($installation['status'], ['contractor_approved', 'adv_approved'])) {
            ApiResponse::forbidden('This installation has already been approved and cannot be modified.');
            return;
        }

        // Material Receipt Rule: Installation can only be submitted after materials are received
        if (in_array($installation['status'], ['pending_assignment', 'pending_eta', 'pending_ada', 'pending_materials'])) {
            ApiResponse::forbidden('Installation cannot be submitted until material receipt is confirmed.');
            return;
        }

        // Save any latest form data provided during submit before transitioning status
        $allowedFields = [
            'router_serial', 'router_make', 'router_model', 'router_fixed', 'router_fixed_remarks', 'router_fixed_snaps',
            'router_status', 'router_status_remarks', 'router_status_snaps',
            'adaptor_installed', 'adaptor_snaps', 'adaptor_status', 'adaptor_status_remarks', 'adaptor_status_snaps',
            'lan_cable_installed', 'lan_cable_install_remark', 'lan_cable_install_snap',
            'lan_cable_status', 'lan_cable_status_not_working_reasons', 'lan_cable_status_remark', 'lan_cable_status_snap',
            'antenna_installed', 'antenna_remarks', 'antenna_snaps', 'antenna_status', 'antenna_status_remarks', 'antenna_status_snaps',
            'gps_installed', 'gps_remarks', 'gps_snaps', 'gps_status', 'gps_status_remarks', 'gps_status_snaps',
            'wifi_installed', 'wifi_remarks', 'wifi_snaps', 'wifi_status', 'wifi_status_remarks', 'wifi_status_snaps',
            'airtel_sim_installed', 'airtel_sim_remarks', 'airtel_sim_snaps', 'airtel_sim_status', 'airtel_sim_status_remarks', 'airtel_sim_status_snaps',
            'vodafone_sim_installed', 'vodafone_sim_remarks', 'vodafone_sim_snaps', 'vodafone_sim_status', 'vodafone_sim_status_remarks', 'vodafone_sim_status_snaps',
            'jio_sim_installed', 'jio_sim_remarks', 'jio_sim_snaps', 'jio_sim_status', 'jio_sim_status_remarks', 'jio_sim_status_snaps',
            'signature_image', 'vendor_stamp', 'engineer_name', 'engineer_number', 'vendor_name'
        ];
        $formData = array_intersect_key($input, array_flip($allowedFields));
        if (isset($input['data']) && is_array($input['data'])) {
            $nested = array_intersect_key($input['data'], array_flip($allowedFields));
            $formData = array_merge($formData, $nested);
        }
        if (!empty($formData)) {
            $installationService->saveInstallationData($installationId, $formData, $engineerId);
        }

        $res = $installationService->submitInstallation($installationId, $engineerId);
        if ($res['success']) {
            ApiResponse::success($res['data'], 'Installation submitted for supervisor review successfully');
        } else {
            ApiResponse::error($res['code'] ?? 'ERROR', $res['message'] ?? 'Failed to submit installation', 400);
        }
        return;
    }
    
    ApiResponse::validationError(['action' => ['Invalid action specified']]);
}

/**
 * Handle image / snaps upload for installation
 */
function handleUploadRequest($installationRepo, int $engineerId) {
    if (empty($_FILES['file'])) {
        ApiResponse::validationError(['file' => ['File is required']]);
        return;
    }
    
    $installationId = isset($_POST['installation_id']) ? (int)$_POST['installation_id'] : 0;
    $section = trim($_POST['section'] ?? 'router');
    
    if ($installationId <= 0) {
        ApiResponse::validationError(['installation_id' => ['Installation ID is required']]);
        return;
    }
    
    $installation = $installationRepo->findById($installationId);
    if (!$installation || (int)$installation['assigned_engineer_id'] !== $engineerId) {
        ApiResponse::forbidden('Invalid installation or unauthorized');
        return;
    }
    
    $file = $_FILES['file'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        ApiResponse::validationError(['file' => ['Invalid image file type']]);
        return;
    }
    
    $uploadDir = __DIR__ . '/../../uploads/installations/' . $installationId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $fileName = $section . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $webPath = 'uploads/installations/' . $installationId . '/' . $fileName;
        ApiResponse::success([
            'path' => $webPath,
            'filename' => $fileName,
            'section' => $section
        ], 'Image uploaded successfully');
    } else {
        ApiResponse::serverError('Failed to save uploaded file');
    }
}

<?php
/**
 * Dynamic Feasibility Check Form Page
 * 
 * Displays the dynamic feasibility check form driven by Custom Forms Master.
 * Shows read-only master site information and dynamically renders project-specific
 * or global custom form fields and sections.
 * Also handles rejection feedback display and resubmission workflow.
 * 
 * Requirements: 4.1, 4.2, 4.3, 5.1-5.6, 6.1, 7.1, 7.2, 7.3, 9.1, 9.2, 9.4, 12.1, 12.2, 12.3
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/FeasibilityService.php';
require_once __DIR__ . '/../services/FeasibilityReviewService.php';
require_once __DIR__ . '/../services/SiteAccessService.php';
require_once __DIR__ . '/../models/CustomForm.php';
require_once __DIR__ . '/../views/components/image_thumbnail.php';
require_once __DIR__ . '/../views/components/lightbox.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check engineer access - only contractor users can access this page
if (!isEngineerUser()) {
    $_SESSION['flash_error'] = 'Access denied. Engineer users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();

// Get assignment ID from query parameter
$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
$viewMode = isset($_GET['view']) && $_GET['view'] == '1';
$editMode = isset($_GET['edit']) && $_GET['edit'] == '1';

if ($assignmentId <= 0) {
    $_SESSION['flash_error'] = 'Invalid assignment ID.';
    header('Location: sites.php');
    exit;
}

// Verify engineer has access to this assignment
$siteAccessService = new SiteAccessService();
$accessResult = $siteAccessService->validateEngineerAssignmentAccess($currentUser['id'], $assignmentId);
if (!$accessResult['success']) {
    $_SESSION['flash_error'] = $accessResult['message'];
    header('Location: sites.php');
    exit;
}

// Get feasibility service
$feasibilityService = new FeasibilityService();
$reviewService = new FeasibilityReviewService();

// Get master site info (Requirement 4.2)
$siteInfo = $feasibilityService->getMasterSiteInfo($assignmentId);
if (!$siteInfo) {
    $_SESSION['flash_error'] = 'Site information not found.';
    header('Location: sites.php');
    exit;
}

// Get feasibility status
$feasibilityStatus = $feasibilityService->getFeasibilityStatus($assignmentId);

// Check if feasibility check already exists
$existingFeasibility = $feasibilityService->getFeasibilityByAssignment($assignmentId);

// Initialize rejection-related variables
$isRejected = false;
$rejectionInfo = null;
$editableSections = [];
$editableFields = [];
$reviewHistory = [];

// Check if feasibility is rejected and get rejection info (Requirements 12.1, 12.2, 12.3)
if ($existingFeasibility) {
    $approvalStatus = $existingFeasibility['approval_status'] ?? 'pending_contractor_review';
    $isRejected = in_array($approvalStatus, ['contractor_rejected', 'adv_rejected']);
    
    // Always get review history when feasibility exists (Requirement 12.5)
    $reviewHistory = $reviewService->getReviewHistory($existingFeasibility['id']);
    
    if ($isRejected) {
        // Get editable sections info (Requirement 12.3)
        $editableInfo = $reviewService->getEditableSections($existingFeasibility['id']);
        if ($editableInfo['success']) {
            $editableSections = $editableInfo['editableSections'];
            $editableFields = $editableInfo['editableFields'];
            $rejectionInfo = [
                'rejection_type' => $editableInfo['rejectionType'],
                'rejection_reason' => $editableInfo['rejectionReason'],
                'rejected_by' => $editableInfo['rejectedBy'],
                'rejected_at' => $editableInfo['rejectedAt']
            ];
        }
    }
}

// Determine if we're in resubmit mode (edit mode for rejected feasibility)
$resubmitMode = $editMode && $isRejected && $existingFeasibility;

// If not in view mode and feasibility already completed (and not rejected), redirect to view mode
if (!$viewMode && !$resubmitMode && $existingFeasibility && !$isRejected) {
    header("Location: feasibility_form.php?assignment_id={$assignmentId}&view=1");
    exit;
}

// If not in view mode and ADA not submitted (and not resubmitting), redirect back
if (!$viewMode && !$resubmitMode && $feasibilityStatus !== 'ada_submitted') {
    $_SESSION['flash_error'] = 'ADA must be submitted before completing feasibility check.';
    header('Location: sites.php');
    exit;
}

// ========================================================
// DYNAMIC FORM RESOLUTION & FIELD MAPPING
// ========================================================
$customFormModel = new CustomForm();
$targetProjectId = !empty($siteInfo['project_id']) ? (int)$siteInfo['project_id'] : null;
$customForm = $customFormModel->findFormForProject('feasibility', $targetProjectId);

$dynamicFields = $customForm['fields'] ?? [];

// Fallback: If no active custom form schema exists in database, use standard 7-section template
if (empty($dynamicFields)) {
    $dynamicFields = [
        // 1. ATM Information
        ['section_title' => 'ATM Information', 'field_key' => 'no_of_atm', 'field_label' => 'Number of ATMs', 'field_type' => 'select', 'is_required' => 1, 'grid_width' => 4, 'options' => [['label' => '0 ATMs', 'value' => '0'], ['label' => '1 ATM', 'value' => '1'], ['label' => '2 ATMs', 'value' => '2'], ['label' => '3 ATMs', 'value' => '3']]],
        ['section_title' => 'ATM Information', 'field_key' => 'atm_id_1', 'field_label' => 'ATM 1 ID', 'field_type' => 'text', 'placeholder' => 'e.g. S1AC00112', 'is_required' => 0, 'grid_width' => 4],
        ['section_title' => 'ATM Information', 'field_key' => 'atm_1_status', 'field_label' => 'ATM 1 Status', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 4, 'options' => [['label' => 'Working', 'value' => 'working'], ['label' => 'Not Working', 'value' => 'not_working'], ['label' => 'Under Maintenance', 'value' => 'maintenance']]],
        ['section_title' => 'ATM Information', 'field_key' => 'atm_id_2', 'field_label' => 'ATM 2 ID', 'field_type' => 'text', 'placeholder' => 'e.g. S1AC00113', 'is_required' => 0, 'grid_width' => 6],
        ['section_title' => 'ATM Information', 'field_key' => 'atm_2_status', 'field_label' => 'ATM 2 Status', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 6, 'options' => [['label' => 'Working', 'value' => 'working'], ['label' => 'Not Working', 'value' => 'not_working'], ['label' => 'Under Maintenance', 'value' => 'maintenance']]],
        ['section_title' => 'ATM Information', 'field_key' => 'atm_id_3', 'field_label' => 'ATM 3 ID', 'field_type' => 'text', 'placeholder' => 'e.g. S1AC00114', 'is_required' => 0, 'grid_width' => 6],
        ['section_title' => 'ATM Information', 'field_key' => 'atm_3_status', 'field_label' => 'ATM 3 Status', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 6, 'options' => [['label' => 'Working', 'value' => 'working'], ['label' => 'Not Working', 'value' => 'not_working'], ['label' => 'Under Maintenance', 'value' => 'maintenance']]],

        // 2. Network Information
        ['section_title' => 'Network Information', 'field_key' => 'operator', 'field_label' => 'Primary Network Operator', 'field_type' => 'select', 'is_required' => 1, 'grid_width' => 6, 'options' => [['label' => 'Airtel', 'value' => 'Airtel'], ['label' => 'Jio', 'value' => 'Jio'], ['label' => 'Vi', 'value' => 'Vi'], ['label' => 'BSNL', 'value' => 'BSNL'], ['label' => 'Other', 'value' => 'Other']]],
        ['section_title' => 'Network Information', 'field_key' => 'signal_status', 'field_label' => 'Primary Signal Status', 'field_type' => 'select', 'is_required' => 1, 'grid_width' => 6, 'options' => [['label' => 'Excellent', 'value' => 'excellent'], ['label' => 'Good', 'value' => 'good'], ['label' => 'Poor', 'value' => 'poor'], ['label' => 'No Signal', 'value' => 'no_signal']]],
        ['section_title' => 'Network Information', 'field_key' => 'operator_2', 'field_label' => 'Secondary Network Operator', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 6, 'options' => [['label' => 'None', 'value' => ''], ['label' => 'Airtel', 'value' => 'Airtel'], ['label' => 'Jio', 'value' => 'Jio'], ['label' => 'Vi', 'value' => 'Vi'], ['label' => 'BSNL', 'value' => 'BSNL'], ['label' => 'Other', 'value' => 'Other']]],
        ['section_title' => 'Network Information', 'field_key' => 'signal_status_2', 'field_label' => 'Secondary Signal Status', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 6, 'options' => [['label' => 'Excellent', 'value' => 'excellent'], ['label' => 'Good', 'value' => 'good'], ['label' => 'Poor', 'value' => 'poor'], ['label' => 'No Signal', 'value' => 'no_signal']]],
        ['section_title' => 'Network Information', 'field_key' => 'backroom_network_remark', 'field_label' => 'Backroom Network Remarks', 'field_type' => 'textarea', 'placeholder' => 'e.g. Signal drops inside the backroom...', 'is_required' => 0, 'grid_width' => 12],
        ['section_title' => 'Network Information', 'field_key' => 'backroom_network_snap', 'field_label' => 'Backroom Network Photo', 'field_type' => 'file', 'is_required' => 1, 'grid_width' => 6],

        // 3. Power & UPS Infrastructure
        ['section_title' => 'Power & UPS Infrastructure', 'field_key' => 'ups_available', 'field_label' => 'UPS Available', 'field_type' => 'select', 'is_required' => 1, 'grid_width' => 4, 'options' => [['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no']]],
        ['section_title' => 'Power & UPS Infrastructure', 'field_key' => 'no_of_ups', 'field_label' => 'Number of UPS', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 4, 'options' => [['label' => '1', 'value' => '1'], ['label' => '2', 'value' => '2'], ['label' => '3', 'value' => '3']]],
        ['section_title' => 'Power & UPS Infrastructure', 'field_key' => 'ups_battery_backup', 'field_label' => 'UPS Battery Backup', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 4, 'options' => [['label' => 'Less than 30 min', 'value' => 'less_than_30min'], ['label' => '30 min - 1 hour', 'value' => '30min_to_1hr'], ['label' => '1 - 2 hours', 'value' => '1hr_to_2hr'], ['label' => 'More than 2 hours', 'value' => 'more_than_2hr']]],
        ['section_title' => 'Power & UPS Infrastructure', 'field_key' => 'ups_working_1', 'field_label' => 'UPS 1 Working', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 4, 'options' => [['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no']]],
        ['section_title' => 'Power & UPS Infrastructure', 'field_key' => 'ups_working_2', 'field_label' => 'UPS 2 Working', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 4, 'options' => [['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no']]],
        ['section_title' => 'Power & UPS Infrastructure', 'field_key' => 'ups_working_3', 'field_label' => 'UPS 3 Working', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 4, 'options' => [['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no']]],
        ['section_title' => 'Power & UPS Infrastructure', 'field_key' => 'power_socket_availability', 'field_label' => 'Power Socket Availability', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 6, 'options' => [['label' => 'Available', 'value' => 'available'], ['label' => 'Not Available', 'value' => 'not_available']]],
        ['section_title' => 'Power & UPS Infrastructure', 'field_key' => 'power_socket_availability_ups', 'field_label' => 'Power Socket for UPS', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 6, 'options' => [['label' => 'Available', 'value' => 'available'], ['label' => 'Not Available', 'value' => 'not_available']]],
        ['section_title' => 'Power & UPS Infrastructure', 'field_key' => 'ups_available_snap', 'field_label' => 'UPS & Power Photo', 'field_type' => 'file', 'is_required' => 0, 'grid_width' => 6],

        // 4. Electrical Measurements
        ['section_title' => 'Electrical Measurements', 'field_key' => 'earthing', 'field_label' => 'Earthing Status', 'field_type' => 'select', 'is_required' => 1, 'grid_width' => 6, 'options' => [['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no']]],
        ['section_title' => 'Electrical Measurements', 'field_key' => 'earthing_voltage', 'field_label' => 'Earthing Voltage (Neutral - Earth / E-N)', 'field_type' => 'text', 'placeholder' => 'e.g., 0.5V', 'is_required' => 0, 'grid_width' => 6],
        ['section_title' => 'Electrical Measurements', 'field_key' => 'power_fluctuation_en', 'field_label' => 'Power Fluctuation E-N', 'field_type' => 'text', 'placeholder' => 'e.g., 220V', 'is_required' => 0, 'grid_width' => 4],
        ['section_title' => 'Electrical Measurements', 'field_key' => 'power_fluctuation_pe', 'field_label' => 'Power Fluctuation P-E', 'field_type' => 'text', 'placeholder' => 'e.g., 0V', 'is_required' => 0, 'grid_width' => 4],
        ['section_title' => 'Electrical Measurements', 'field_key' => 'power_fluctuation_pn', 'field_label' => 'Power Fluctuation P-N', 'field_type' => 'text', 'placeholder' => 'e.g., 220V', 'is_required' => 0, 'grid_width' => 4],
        ['section_title' => 'Electrical Measurements', 'field_key' => 'frequent_power_cut', 'field_label' => 'Frequent Power Cut', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 4, 'options' => [['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no']]],
        ['section_title' => 'Electrical Measurements', 'field_key' => 'frequent_power_cut_from', 'field_label' => 'Power Cut From Time', 'field_type' => 'text', 'placeholder' => 'e.g., 14:00', 'is_required' => 0, 'grid_width' => 4],
        ['section_title' => 'Electrical Measurements', 'field_key' => 'frequent_power_cut_to', 'field_label' => 'Power Cut To Time', 'field_type' => 'text', 'placeholder' => 'e.g., 16:00', 'is_required' => 0, 'grid_width' => 4],
        ['section_title' => 'Electrical Measurements', 'field_key' => 'frequent_power_cut_remark', 'field_label' => 'Power Cut Remarks', 'field_type' => 'textarea', 'placeholder' => 'e.g. Daily power cut during peak hours...', 'is_required' => 0, 'grid_width' => 12],
        ['section_title' => 'Electrical Measurements', 'field_key' => 'earthing_snap', 'field_label' => 'Earthing & Multimeter Photo', 'field_type' => 'file', 'is_required' => 0, 'grid_width' => 6],

        // 5. Site Access
        ['section_title' => 'Site Access', 'field_key' => 'em_lock_available', 'field_label' => 'EM Lock Available', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 4, 'options' => [['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no']]],
        ['section_title' => 'Site Access', 'field_key' => 'em_lock_password', 'field_label' => 'EM Lock Password', 'field_type' => 'text', 'placeholder' => 'Password / PIN', 'is_required' => 0, 'grid_width' => 4],
        ['section_title' => 'Site Access', 'field_key' => 'password_received', 'field_label' => 'Password Received', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 4, 'options' => [['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no']]],
        ['section_title' => 'Site Access', 'field_key' => 'backroom_key_name', 'field_label' => 'Backroom Key Contact Name', 'field_type' => 'text', 'placeholder' => 'Keyholder contact person', 'is_required' => 0, 'grid_width' => 4],
        ['section_title' => 'Site Access', 'field_key' => 'backroom_key_number', 'field_label' => 'Backroom Key Contact Number', 'field_type' => 'phone', 'placeholder' => 'e.g. 9876543210', 'is_required' => 0, 'grid_width' => 4],
        ['section_title' => 'Site Access', 'field_key' => 'backroom_key_status', 'field_label' => 'Backroom Key Status', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 4, 'options' => [['label' => 'Available', 'value' => 'available'], ['label' => 'Not Available', 'value' => 'not_available']]],

        // 6. Environmental Factors
        ['section_title' => 'Environmental Factors', 'field_key' => 'router_antenna_position', 'field_label' => 'Router / Antenna Position', 'field_type' => 'text', 'placeholder' => 'Proposed antenna mounting position', 'is_required' => 0, 'grid_width' => 6],
        ['section_title' => 'Environmental Factors', 'field_key' => 'router_position', 'field_label' => 'Router Position', 'field_type' => 'text', 'placeholder' => 'Proposed router placement in backroom', 'is_required' => 0, 'grid_width' => 6],
        ['section_title' => 'Environmental Factors', 'field_key' => 'antenna_routing_detail', 'field_label' => 'Antenna Routing Detail', 'field_type' => 'textarea', 'placeholder' => 'Cable pathway from antenna to router...', 'is_required' => 0, 'grid_width' => 12],
        ['section_title' => 'Environmental Factors', 'field_key' => 'nearest_shop_name', 'field_label' => 'Nearest Shop Name', 'field_type' => 'text', 'placeholder' => 'Nearby shop or landmark', 'is_required' => 0, 'grid_width' => 4],
        ['section_title' => 'Environmental Factors', 'field_key' => 'nearest_shop_number', 'field_label' => 'Nearest Shop Number', 'field_type' => 'phone', 'placeholder' => 'Shopkeeper phone', 'is_required' => 0, 'grid_width' => 4],
        ['section_title' => 'Environmental Factors', 'field_key' => 'nearest_shop_distance', 'field_label' => 'Nearest Shop Distance', 'field_type' => 'text', 'placeholder' => 'e.g., 100m', 'is_required' => 0, 'grid_width' => 4],
        ['section_title' => 'Environmental Factors', 'field_key' => 'backroom_disturbing_material', 'field_label' => 'Backroom Disturbing / Hazardous Material', 'field_type' => 'select', 'is_required' => 0, 'grid_width' => 6, 'options' => [['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no']]],
        ['section_title' => 'Environmental Factors', 'field_key' => 'backroom_disturbing_material_remark', 'field_label' => 'Disturbing Material Remarks', 'field_type' => 'text', 'placeholder' => 'e.g. Water leakage, exposed cables', 'is_required' => 0, 'grid_width' => 6],
        ['section_title' => 'Environmental Factors', 'field_key' => 'router_antenna_snap', 'field_label' => 'Proposed Router / Antenna Snapshot', 'field_type' => 'file', 'is_required' => 0, 'grid_width' => 6],
        ['section_title' => 'Environmental Factors', 'field_key' => 'antenna_routing_snap', 'field_label' => 'Antenna Routing Pathway Snapshot', 'field_type' => 'file', 'is_required' => 0, 'grid_width' => 6],

        // 7. Remarks & Final Assessment
        ['section_title' => 'Remarks & Final Assessment', 'field_key' => 'remarks', 'field_label' => 'General Inspection Remarks', 'field_type' => 'textarea', 'placeholder' => 'Provide comprehensive inspection observations, special access instructions, or notes for installation...', 'is_required' => 0, 'grid_width' => 12],
        ['section_title' => 'Remarks & Final Assessment', 'field_key' => 'remarks_snap', 'field_label' => 'Site Overall / External Snap', 'field_type' => 'file', 'is_required' => 0, 'grid_width' => 6]
    ];
}

// Group fields by Section Title
$groupedSections = [];
foreach ($dynamicFields as $field) {
    $secTitle = !empty($field['section_title']) ? trim($field['section_title']) : 'General Information';
    if (!isset($groupedSections[$secTitle])) {
        $groupedSections[$secTitle] = [];
    }
    $groupedSections[$secTitle][] = $field;
}

/**
 * Normalize section name to standard slug for rejection matching
 */
function normalizeSectionSlug($sectionTitle) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $sectionTitle), '_'));
    $map = [
        'atm_information' => 'atm_information',
        'network_information' => 'network_information',
        'power_ups_infrastructure' => 'power_infrastructure',
        'power_infrastructure' => 'power_infrastructure',
        'electrical_measurements' => 'electrical_measurements',
        'site_access' => 'site_access',
        'environmental_factors' => 'environmental_factors',
        'remarks_final_assessment' => 'remarks',
        'remarks' => 'remarks'
    ];
    return $map[$slug] ?? $slug;
}

/**
 * Helper function to check if a section is rejected
 */
function isSectionRejected($sectionName, $editableSections, $rejectionInfo) {
    if (!$rejectionInfo) return false;
    
    if ($rejectionInfo['rejection_type'] === 'overall') {
        return true;
    }
    
    return in_array($sectionName, $editableSections);
}

/**
 * Helper function to check if a field is editable in resubmit mode
 */
function isFieldEditable($fieldName, $resubmitMode, $editableFields) {
    if (!$resubmitMode) return true;
    return in_array($fieldName, $editableFields);
}

/**
 * Get CSS classes for a section based on rejection status
 */
function getSectionClasses($sectionName, $editableSections, $rejectionInfo, $isRejected) {
    if (!$isRejected) return '';
    
    if (isSectionRejected($sectionName, $editableSections, $rejectionInfo)) {
        return 'rejected-section';
    }
    return '';
}

/**
 * Get section visual style and icons
 */
function getSectionStyle($sectionTitle) {
    $slug = normalizeSectionSlug($sectionTitle);
    $styles = [
        'atm_information' => ['icon' => 'fa-credit-card', 'bg' => 'bg-yellow-50', 'text' => 'text-yellow-800', 'badge' => 'border-yellow-200 text-yellow-700 bg-yellow-100'],
        'network_information' => ['icon' => 'fa-wifi', 'bg' => 'bg-green-50', 'text' => 'text-green-800', 'badge' => 'border-green-200 text-green-700 bg-green-100'],
        'power_infrastructure' => ['icon' => 'fa-bolt', 'bg' => 'bg-orange-50', 'text' => 'text-orange-800', 'badge' => 'border-orange-200 text-orange-700 bg-orange-100'],
        'electrical_measurements' => ['icon' => 'fa-tachometer-alt', 'bg' => 'bg-blue-50', 'text' => 'text-blue-800', 'badge' => 'border-blue-200 text-blue-700 bg-blue-100'],
        'site_access' => ['icon' => 'fa-key', 'bg' => 'bg-purple-50', 'text' => 'text-purple-800', 'badge' => 'border-purple-200 text-purple-700 bg-purple-100'],
        'environmental_factors' => ['icon' => 'fa-leaf', 'bg' => 'bg-teal-50', 'text' => 'text-teal-800', 'badge' => 'border-teal-200 text-teal-700 bg-teal-100'],
        'remarks' => ['icon' => 'fa-clipboard-list', 'bg' => 'bg-slate-50', 'text' => 'text-slate-800', 'badge' => 'border-slate-200 text-slate-700 bg-slate-100']
    ];
    return $styles[$slug] ?? ['icon' => 'fa-layer-group', 'bg' => 'bg-indigo-50', 'text' => 'text-indigo-800', 'badge' => 'border-indigo-200 text-indigo-700 bg-indigo-100'];
}

$baseUrl = '..';
$pageTitle = $viewMode ? 'View Feasibility Check' : ($resubmitMode ? 'Resubmit Feasibility Check' : 'Feasibility Check Form');
$currentPage = 'engineer_sites';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'My Sites', 'url' => 'sites.php'],
    ['label' => $pageTitle]
];

ob_start();

// Include thumbnail and lightbox styles
echo getImageThumbnailStyles();
echo getLightboxStyles();
?>

<style>
/* Rejection highlight styles */
.rejected-section {
    border: 2px solid #ef4444 !important;
    background-color: #fef2f2 !important;
}

.rejected-section .section-header {
    background-color: #fee2e2 !important;
    border-color: #fca5a5 !important;
}

.rejection-banner {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border: 2px solid #ef4444;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}

.rejection-banner-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.rejection-banner-icon {
    width: 48px;
    height: 48px;
    background-color: #ef4444;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.rejection-banner-title {
    font-size: 18px;
    font-weight: 600;
    color: #991b1b;
}

.rejection-banner-subtitle {
    font-size: 14px;
    color: #b91c1c;
}

.rejection-reason-box {
    background-color: white;
    border: 1px solid #fca5a5;
    border-radius: 8px;
    padding: 16px;
    margin-top: 12px;
}

.rejection-reason-label {
    font-size: 12px;
    font-weight: 600;
    color: #991b1b;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.rejection-reason-text {
    font-size: 14px;
    color: #374151;
    line-height: 1.5;
}

.rejected-sections-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.rejected-section-tag {
    background-color: #fecaca;
    color: #991b1b;
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 500;
}

.rejection-meta {
    display: flex;
    gap: 16px;
    margin-top: 12px;
    font-size: 12px;
    color: #6b7280;
}

.field-disabled {
    opacity: 0.65;
    pointer-events: none;
    background-color: #f8fafc !important;
    cursor: not-allowed;
}

.resubmit-button {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.resubmit-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.review-history-item {
    border-left: 3px solid #e5e7eb;
    padding-left: 16px;
    margin-bottom: 16px;
}

.review-history-item.rejection {
    border-left-color: #ef4444;
}

.review-history-item.approval {
    border-left-color: #22c55e;
}

.rejection-panel-overlay {
    position: fixed;
    inset: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 40;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.rejection-panel-overlay.active {
    opacity: 1;
    visibility: visible;
}

.rejection-panel {
    position: fixed;
    top: 0;
    right: 0;
    width: 400px;
    max-width: 90vw;
    height: 100vh;
    background: white;
    box-shadow: -4px 0 20px rgba(0, 0, 0, 0.15);
    z-index: 50;
    transform: translateX(100%);
    transition: transform 0.3s ease;
    overflow-y: auto;
}

.rejection-panel.active {
    transform: translateX(0);
}

.rejection-panel-header {
    position: sticky;
    top: 0;
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border-bottom: 2px solid #ef4444;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 10;
}

.rejection-panel-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: white;
    border: 1px solid #fca5a5;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.rejection-panel-close:hover {
    background: #fef2f2;
}

.rejection-toggle-btn {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.rejection-toggle-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}
</style>

<!-- Slide-out Rejection Panel -->
<?php 
$hasRejectionSidebar = $isRejected && $rejectionInfo;
if ($hasRejectionSidebar || !empty($reviewHistory)): 
    $rejectedByLabel = 'Contractor Reviewer';
    if ($rejectionInfo && $rejectionInfo['rejected_by']) {
        $userModel = new User();
        $reviewer = $userModel->findById($rejectionInfo['rejected_by']);
        if ($reviewer) {
            $rejectedByLabel = $reviewer['first_name'] . ' ' . $reviewer['last_name'];
        }
    }
    $rejectedAtFormatted = $rejectionInfo && $rejectionInfo['rejected_at'] ? date('M d, Y h:i A', strtotime($rejectionInfo['rejected_at'])) : 'Recently';
?>
<div id="rejection-panel-overlay" class="rejection-panel-overlay" onclick="closeRejectionPanel()"></div>
<div id="rejection-panel" class="rejection-panel">
    <div class="rejection-panel-header">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center text-red-600">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <h3 class="font-semibold text-red-900">Rejection Details</h3>
                <p class="text-xs text-red-600">Action required</p>
            </div>
        </div>
        <button type="button" class="rejection-panel-close" onclick="closeRejectionPanel()">
            <i class="fas fa-times text-gray-500"></i>
        </button>
    </div>
    
    <div class="p-6 space-y-6">
        <?php if ($rejectionInfo): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="text-xs font-semibold text-red-800 uppercase mb-2">Rejection Reason</div>
            <div class="text-sm text-gray-700"><?php echo htmlspecialchars($rejectionInfo['rejection_reason'] ?? 'No reason provided'); ?></div>
        </div>
        
        <?php if ($rejectionInfo['rejection_type'] === 'section_specific' && !empty($editableSections)): ?>
        <div>
            <div class="text-xs font-semibold text-gray-600 uppercase mb-2">Rejected Sections</div>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($editableSections as $section): ?>
                    <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-medium">
                        <?php echo htmlspecialchars(FeasibilityReviewService::getSectionLabel($section)); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="border-t pt-4 space-y-2 text-sm text-gray-600">
            <div><i class="fas fa-user mr-2 text-gray-400"></i>Rejected by: <?php echo htmlspecialchars($rejectedByLabel); ?></div>
            <div><i class="fas fa-clock mr-2 text-gray-400"></i><?php echo $rejectedAtFormatted; ?></div>
        </div>
        
        <?php if ($viewMode && !$resubmitMode): ?>
        <div class="pt-2">
            <a href="feasibility_form.php?assignment_id=<?php echo $assignmentId; ?>&edit=1" 
               class="resubmit-button w-full justify-center">
                <i class="fas fa-edit"></i>
                Edit & Resubmit
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php if (!empty($reviewHistory)): ?>
        <div class="border-t pt-4">
            <div class="text-xs font-semibold text-gray-600 uppercase mb-3">Review History (<?php echo count($reviewHistory); ?> records)</div>
            <div class="space-y-4">
                <?php foreach ($reviewHistory as $review): 
                    $isRejectionReview = $review['review_type'] === 'rejection';
                    $reviewDate = date('M d, Y', strtotime($review['reviewed_at']));
                    $reviewTime = date('h:i A', strtotime($review['reviewed_at']));
                    $reviewerName = $review['reviewer_name'] ?? 'Unknown';
                    $reviewerRole = ucwords(str_replace('_', ' ', $review['reviewer_role'] ?? 'reviewer'));
                ?>
                <div class="review-history-item <?php echo $isRejectionReview ? 'rejection' : 'approval'; ?>">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $isRejectionReview ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                            <?php echo $isRejectionReview ? 'Rejected' : 'Approved'; ?>
                        </span>
                    </div>
                    <div class="text-sm text-gray-700 font-medium"><?php echo htmlspecialchars($reviewerName); ?></div>
                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($reviewerRole); ?> • <?php echo $reviewDate; ?></div>
                    
                    <?php if ($isRejectionReview && !empty($review['reason'])): ?>
                    <div class="text-xs text-gray-600 mt-2 bg-red-50 p-2 rounded">
                        <?php echo htmlspecialchars($review['reason']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!$isRejectionReview && !empty($review['comments'])): ?>
                    <div class="text-xs text-gray-600 mt-2 bg-green-50 p-2 rounded">
                        <?php echo htmlspecialchars($review['comments']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="max-w-6xl mx-auto pb-12">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm mb-6 border border-gray-100">
        <div class="p-6 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h3 class="text-lg font-bold text-gray-800"><?php echo $pageTitle; ?></h3>
                    <?php if ($customForm): ?>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        <i class="fas fa-magic mr-1"></i> Dynamic: <?php echo htmlspecialchars($customForm['form_name']); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    <?php 
                    if ($viewMode) {
                        echo 'View submitted feasibility check details';
                    } elseif ($resubmitMode) {
                        echo 'Correct the rejected sections and resubmit for review';
                    } else {
                        echo 'Complete the feasibility assessment for this site';
                    }
                    ?>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($hasRejectionSidebar || !empty($reviewHistory)): ?>
                <button type="button" onclick="openRejectionPanel()" class="rejection-toggle-btn text-xs">
                    <i class="fas fa-exclamation-circle"></i>
                    View Rejection Details
                </button>
                <?php endif; ?>
                <a href="sites.php" class="px-3.5 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-xs font-medium">
                    <i class="fas fa-arrow-left mr-1.5"></i>Back to Sites
                </a>
            </div>
        </div>
    </div>

    <!-- Master Site Information (Read-only) -->
    <div class="bg-white rounded-xl shadow-sm mb-6 border border-gray-100 overflow-hidden">
        <div class="p-4 border-b bg-gradient-to-r from-blue-50 to-indigo-50 flex items-center justify-between">
            <h4 class="font-bold text-blue-900 text-sm flex items-center">
                <i class="fas fa-map-marker-alt text-blue-600 mr-2"></i>Master Site Information
            </h4>
            <?php if (!empty($siteInfo['project_name'])): ?>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white border border-blue-200 text-blue-800">
                Project: <?php echo htmlspecialchars($siteInfo['project_name']); ?>
            </span>
            <?php endif; ?>
        </div>
        <div class="p-5 text-xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-gray-400 font-medium mb-0.5">Site Name</label>
                    <p class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($siteInfo['site_name'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-gray-400 font-medium mb-0.5">LHO / Bank Name</label>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($siteInfo['lho'] ?? 'N/A'); ?> • <?php echo htmlspecialchars($siteInfo['bank_name'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-gray-400 font-medium mb-0.5">Customer Name</label>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($siteInfo['customer_name'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-gray-400 font-medium mb-0.5">Location / City</label>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($siteInfo['city'] ?? ''); ?>, <?php echo htmlspecialchars($siteInfo['state'] ?? 'N/A'); ?></p>
                </div>
                <div class="sm:col-span-2 md:col-span-3">
                    <label class="block text-gray-400 font-medium mb-0.5">Address</label>
                    <p class="text-gray-700"><?php echo htmlspecialchars($siteInfo['address'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-gray-400 font-medium mb-0.5">Coordinates</label>
                    <p class="font-medium text-gray-800">
                        <?php if ($siteInfo['latitude'] && $siteInfo['longitude']): ?>
                            <a href="https://www.google.com/maps?q=<?php echo $siteInfo['latitude']; ?>,<?php echo $siteInfo['longitude']; ?>" 
                               target="_blank" class="text-indigo-600 hover:underline inline-flex items-center gap-1">
                                <i class="fas fa-location-arrow text-[10px]"></i> <?php echo $siteInfo['latitude']; ?>, <?php echo $siteInfo['longitude']; ?>
                            </a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Feasibility Form -->
    <form id="feasibility-form" enctype="multipart/form-data">
        <input type="hidden" name="assignment_id" value="<?php echo $assignmentId; ?>">
        <?php if ($resubmitMode && $existingFeasibility): ?>
        <input type="hidden" name="feasibility_id" value="<?php echo $existingFeasibility['id']; ?>">
        <input type="hidden" name="is_resubmit" value="1">
        <?php endif; ?>

        <!-- Render Dynamic Sections -->
        <?php foreach ($groupedSections as $secTitle => $fields): 
            $secSlug = normalizeSectionSlug($secTitle);
            $secRejected = isSectionRejected($secSlug, $editableSections, $rejectionInfo);
            $secClasses = getSectionClasses($secSlug, $editableSections, $rejectionInfo, $isRejected);
            $secStyle = getSectionStyle($secTitle);
            $secFieldsDisabled = $viewMode || ($resubmitMode && !$secRejected);
        ?>
        <div class="bg-white rounded-xl shadow-sm mb-6 border border-gray-100 overflow-hidden <?php echo $secClasses; ?>" data-section="<?php echo $secSlug; ?>" id="section-<?php echo $secSlug; ?>">
            <div class="p-4 border-b <?php echo $secStyle['bg']; ?> section-header flex items-center justify-between">
                <h4 class="font-bold text-sm <?php echo $secStyle['text']; ?> flex items-center gap-2">
                    <i class="fas <?php echo $secStyle['icon']; ?>"></i>
                    <?php echo htmlspecialchars($secTitle); ?>
                </h4>
                <?php if ($isRejected && $secRejected): ?>
                <span class="text-xs bg-red-600 text-white px-2.5 py-0.5 rounded-full font-bold animate-pulse">
                    <i class="fas fa-exclamation-circle mr-1"></i>Needs Correction
                </span>
                <?php endif; ?>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-12 gap-5">
                    <?php foreach ($fields as $field): 
                        $fieldKey = $field['field_key'];
                        $fieldLabel = $field['field_label'] ?? ucwords(str_replace('_', ' ', $fieldKey));
                        $fieldType = $field['field_type'] ?? 'text';
                        $isRequired = !empty($field['is_required']);
                        $placeholder = $field['placeholder'] ?? '';
                        $helpText = $field['help_text'] ?? '';
                        $gridWidth = (int)($field['grid_width'] ?? 12);
                        
                        // Determine responsive col-span
                        $colSpanClass = 'col-span-12';
                        if ($gridWidth <= 3) $colSpanClass = 'col-span-12 sm:col-span-6 md:col-span-3';
                        elseif ($gridWidth <= 4) $colSpanClass = 'col-span-12 sm:col-span-6 md:col-span-4';
                        elseif ($gridWidth <= 6) $colSpanClass = 'col-span-12 md:col-span-6';
                        elseif ($gridWidth <= 8) $colSpanClass = 'col-span-12 md:col-span-8';
                        
                        $val = $existingFeasibility[$fieldKey] ?? ($field['default_value'] ?? '');
                        $isFieldEdit = isFieldEditable($fieldKey, $resubmitMode, $editableFields);
                        $isFieldDisabled = $viewMode || ($resubmitMode && !$secRejected && !$isFieldEdit);
                        
                        $options = $field['resolved_options'] ?? $field['options'] ?? [];
                        if (is_string($options)) {
                            $options = json_decode($options, true) ?: [];
                        }
                    ?>
                    <div class="<?php echo $colSpanClass; ?>" id="field-wrapper-<?php echo $fieldKey; ?>">
                        <label for="<?php echo $fieldKey; ?>" class="block text-xs font-semibold text-gray-700 mb-1.5">
                            <?php echo htmlspecialchars($fieldLabel); ?>
                            <?php if ($isRequired && !$isFieldDisabled && $fieldType !== 'file'): ?>
                                <span class="text-red-500">*</span>
                            <?php endif; ?>
                        </label>

                        <?php if ($fieldType === 'select'): ?>
                            <select name="<?php echo $fieldKey; ?>" id="<?php echo $fieldKey; ?>"
                                <?php echo $isRequired ? 'required' : ''; ?>
                                <?php echo $isFieldDisabled ? 'disabled' : ''; ?>
                                class="w-full px-3.5 py-2.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition <?php echo $isFieldDisabled ? 'field-disabled' : 'bg-white hover:border-gray-400'; ?>"
                                onchange="onDynamicFieldChange('<?php echo $fieldKey; ?>', this.value)">
                                <option value="">-- Select --</option>
                                <?php foreach ($options as $opt): 
                                    $optVal = is_array($opt) ? ($opt['value'] ?? '') : $opt;
                                    $optLbl = is_array($opt) ? ($opt['label'] ?? $opt['name'] ?? $optVal) : $opt;
                                    $selected = ((string)$val === (string)$optVal) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($optVal); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($optLbl); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($fieldType === 'textarea'): ?>
                            <textarea name="<?php echo $fieldKey; ?>" id="<?php echo $fieldKey; ?>" rows="3"
                                placeholder="<?php echo htmlspecialchars($placeholder); ?>"
                                <?php echo $isRequired ? 'required' : ''; ?>
                                <?php echo $isFieldDisabled ? 'disabled' : ''; ?>
                                class="w-full px-3.5 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition <?php echo $isFieldDisabled ? 'field-disabled' : 'bg-white hover:border-gray-400'; ?>"
                                oninput="<?php echo $fieldKey === 'remarks' ? 'updateCharCount()' : ''; ?>"><?php echo htmlspecialchars($val); ?></textarea>
                            <?php if ($fieldKey === 'remarks'): ?>
                            <div class="flex justify-between text-[11px] text-gray-400 mt-1">
                                <span>Max 2000 characters</span>
                                <span><span id="char-count">0</span> / 2000</span>
                            </div>
                            <?php endif; ?>

                        <?php elseif ($fieldType === 'radio'): ?>
                            <div class="flex flex-wrap gap-4 pt-1.5">
                                <?php foreach ($options as $opt): 
                                    $optVal = is_array($opt) ? ($opt['value'] ?? '') : $opt;
                                    $optLbl = is_array($opt) ? ($opt['label'] ?? $opt['name'] ?? $optVal) : $opt;
                                    $checked = ((string)$val === (string)$optVal) ? 'checked' : '';
                                ?>
                                <label class="inline-flex items-center gap-2 cursor-pointer text-xs text-gray-700">
                                    <input type="radio" name="<?php echo $fieldKey; ?>" value="<?php echo htmlspecialchars($optVal); ?>" 
                                        <?php echo $checked; ?> <?php echo $isFieldDisabled ? 'disabled' : ''; ?> 
                                        class="text-primary focus:ring-primary">
                                    <span><?php echo htmlspecialchars($optLbl); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif ($fieldType === 'file'): ?>
                            <div class="space-y-2">
                                <?php if (!empty($val)): ?>
                                    <div class="mb-2">
                                        <?php echo renderImageThumbnail($val, $fieldLabel); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!$isFieldDisabled): ?>
                                    <div class="relative">
                                        <input type="file" name="<?php echo $fieldKey; ?>" id="<?php echo $fieldKey; ?>"
                                            accept="image/jpeg,image/png,image/jpg"
                                            <?php echo ($isRequired && empty($val)) ? 'required' : ''; ?>
                                            class="w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-300 rounded-lg cursor-pointer bg-white">
                                    </div>
                                    <p class="text-[11px] text-gray-400">Supported: JPG, PNG • Max 5MB</p>
                                <?php endif; ?>
                            </div>

                        <?php else: 
                            // Input type text, number, phone, email, date, etc.
                            $htmlInputType = in_array($fieldType, ['text', 'number', 'email', 'date', 'time']) ? $fieldType : ($fieldType === 'phone' ? 'tel' : 'text');
                        ?>
                            <input type="<?php echo $htmlInputType; ?>" name="<?php echo $fieldKey; ?>" id="<?php echo $fieldKey; ?>"
                                value="<?php echo htmlspecialchars($val); ?>"
                                placeholder="<?php echo htmlspecialchars($placeholder); ?>"
                                <?php echo $isRequired ? 'required' : ''; ?>
                                <?php echo $isFieldDisabled ? 'disabled' : ''; ?>
                                class="w-full px-3.5 py-2.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition <?php echo $isFieldDisabled ? 'field-disabled' : 'bg-white hover:border-gray-400'; ?>"
                                oninput="onDynamicFieldInput('<?php echo $fieldKey; ?>', this.value)">
                        <?php endif; ?>

                        <?php if (!empty($helpText)): ?>
                            <p class="text-[11px] text-gray-400 mt-1"><?php echo htmlspecialchars($helpText); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Form Actions -->
        <?php if (!$viewMode || $resubmitMode): ?>
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100 flex items-center justify-between">
            <a href="sites.php" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-xs font-semibold">
                Cancel
            </a>
            <button type="submit" id="submit-btn" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition duration-200 font-bold text-xs shadow-sm flex items-center gap-2">
                <i class="fas fa-paper-plane"></i>
                <?php echo $resubmitMode ? 'Resubmit Feasibility Check' : 'Submit Feasibility Assessment'; ?>
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
const API_URL = '../api/engineer/feasibility.php';
const RESUBMIT_API_URL = '../api/engineer/resubmit.php';
const viewMode = <?php echo $viewMode ? 'true' : 'false'; ?>;
const resubmitMode = <?php echo $resubmitMode ? 'true' : 'false'; ?>;
const feasibilityId = <?php echo $existingFeasibility['id'] ?? 'null'; ?>;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initDynamicFieldInteractions();
    updateCharCount();
    
    if (!viewMode || resubmitMode) {
        const formEl = document.getElementById('feasibility-form');
        if (formEl) {
            formEl.addEventListener('submit', handleSubmit);
        }
    }
});

// Dynamic Field Interactions (ATM counts, UPS dependencies, Power cut toggles)
function initDynamicFieldInteractions() {
    const noOfAtmEl = document.getElementById('no_of_atm');
    if (noOfAtmEl) toggleATMFields(noOfAtmEl.value);

    const upsAvailEl = document.getElementById('ups_available');
    if (upsAvailEl) toggleUPSFields(upsAvailEl.value);

    const powerCutEl = document.getElementById('frequent_power_cut');
    if (powerCutEl) togglePowerCutFields(powerCutEl.value);
}

function onDynamicFieldChange(fieldKey, value) {
    if (fieldKey === 'no_of_atm') {
        toggleATMFields(value);
    } else if (fieldKey === 'ups_available') {
        toggleUPSFields(value);
    } else if (fieldKey === 'no_of_ups') {
        toggleUPSWorkingFields(value);
    } else if (fieldKey === 'frequent_power_cut') {
        togglePowerCutFields(value);
    }
}

function onDynamicFieldInput(fieldKey, value) {
    if (fieldKey === 'remarks') {
        updateCharCount();
    }
}

function toggleATMFields(val) {
    const count = parseInt(val) || 0;
    ['atm_id_1', 'atm_1_status'].forEach(k => setFieldVisibility(k, count >= 1));
    ['atm_id_2', 'atm_2_status'].forEach(k => setFieldVisibility(k, count >= 2));
    ['atm_id_3', 'atm_3_status'].forEach(k => setFieldVisibility(k, count >= 3));
}

function toggleUPSFields(val) {
    const isYes = val === 'yes';
    ['no_of_ups', 'ups_battery_backup', 'ups_available_snap'].forEach(k => setFieldVisibility(k, isYes));
    if (isYes) {
        const count = parseInt(document.getElementById('no_of_ups')?.value || '1') || 1;
        toggleUPSWorkingFields(count);
    } else {
        ['ups_working_1', 'ups_working_2', 'ups_working_3'].forEach(k => setFieldVisibility(k, false));
    }
}

function toggleUPSWorkingFields(countVal) {
    const count = parseInt(countVal) || 0;
    setFieldVisibility('ups_working_1', count >= 1);
    setFieldVisibility('ups_working_2', count >= 2);
    setFieldVisibility('ups_working_3', count >= 3);
}

function togglePowerCutFields(val) {
    const isYes = val === 'yes';
    ['frequent_power_cut_from', 'frequent_power_cut_to', 'frequent_power_cut_remark'].forEach(k => setFieldVisibility(k, isYes));
}

function setFieldVisibility(fieldKey, visible) {
    const wrapper = document.getElementById('field-wrapper-' + fieldKey);
    if (wrapper) {
        if (visible) {
            wrapper.classList.remove('hidden');
        } else {
            wrapper.classList.add('hidden');
        }
    }
}

// Update character count for remarks
function updateCharCount() {
    const remarks = document.getElementById('remarks');
    const charCount = document.getElementById('char-count');
    if (remarks && charCount) {
        charCount.textContent = remarks.value.length;
        if (remarks.value.length > 2000) {
            charCount.classList.add('text-red-500');
        } else {
            charCount.classList.remove('text-red-500');
        }
    }
}

// Slide-out panel functions
function openRejectionPanel() {
    const panel = document.getElementById('rejection-panel');
    const overlay = document.getElementById('rejection-panel-overlay');
    if (panel) panel.classList.add('active');
    if (overlay) overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeRejectionPanel() {
    const panel = document.getElementById('rejection-panel');
    const overlay = document.getElementById('rejection-panel-overlay');
    if (panel) panel.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRejectionPanel();
    }
});

// Handle form submission
async function handleSubmit(e) {
    e.preventDefault();
    
    const form = document.getElementById('feasibility-form');
    const formData = new FormData(form);
    
    // Validate remarks length
    const remarks = document.getElementById('remarks');
    if (remarks && remarks.value.length > 2000) {
        showError('Remarks must not exceed 2000 characters');
        return;
    }
    
    const btn = document.getElementById('submit-btn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + (resubmitMode ? 'Resubmitting...' : 'Submitting...');
    
    try {
        if (resubmitMode) {
            await handleResubmit(formData);
        } else {
            await handleNewSubmit(formData);
        }
    } catch (error) {
        console.error('Error submitting feasibility check:', error);
        showError('Failed to submit feasibility check. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// Handle new feasibility submission
async function handleNewSubmit(formData) {
    const jsonData = {};
    formData.forEach((value, key) => {
        // Skip file inputs for JSON payload
        const inputEl = document.querySelector(`input[name="${key}"][type="file"]`);
        if (!inputEl) {
            jsonData[key] = value;
        }
    });
    
    const response = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(jsonData)
    });
    
    const data = await response.json();
    
    if (data.success) {
        const newFeasibilityId = data.data?.id;
        if (newFeasibilityId) {
            await uploadDynamicImages(formData, newFeasibilityId);
        }
        
        showToast('Feasibility check submitted successfully', 'success');
        setTimeout(() => {
            window.location.href = 'sites.php';
        }, 1500);
    } else {
        showError(data.error?.message || data.message || 'Failed to submit feasibility check');
    }
}

// Handle resubmission of rejected feasibility
async function handleResubmit(formData) {
    const jsonData = {};
    formData.forEach((value, key) => {
        const inputEl = document.querySelector(`input[name="${key}"][type="file"]`);
        if (!inputEl && key !== 'assignment_id' && key !== 'feasibility_id' && key !== 'is_resubmit') {
            jsonData[key] = value;
        }
    });
    
    const response = await fetch(`${RESUBMIT_API_URL}?id=${feasibilityId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(jsonData)
    });
    
    const data = await response.json();
    
    if (data.success) {
        await uploadDynamicImages(formData, feasibilityId);
        showToast('Feasibility check resubmitted successfully', 'success');
        setTimeout(() => {
            window.location.href = 'sites.php';
        }, 1500);
    } else {
        showError(data.error?.message || data.message || 'Failed to resubmit feasibility check');
    }
}

// Dynamic image uploader for all file inputs
async function uploadDynamicImages(formData, targetFeasibilityId) {
    const fileInputs = document.querySelectorAll('#feasibility-form input[type="file"]');
    
    for (const input of fileInputs) {
        const fieldName = input.name;
        const file = formData.get(fieldName);
        
        if (file && file.size > 0) {
            const uploadData = new FormData();
            uploadData.append('action', 'upload');
            uploadData.append('feasibility_id', targetFeasibilityId);
            uploadData.append('category', fieldName);
            uploadData.append('file', file);
            
            try {
                await fetch(API_URL + '?action=upload', {
                    method: 'POST',
                    credentials: 'include',
                    body: uploadData
                });
            } catch (error) {
                console.error(`Error uploading ${fieldName}:`, error);
            }
        }
    }
}

function showError(message) {
    showToast(message, 'error');
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'error' ? 'bg-red-500' : type === 'success' ? 'bg-green-500' : 'bg-blue-500';
    toast.className = `fixed bottom-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 text-xs font-semibold flex items-center gap-2`;
    toast.innerHTML = `<i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i> ${message}`;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3500);
}
</script>

<?php
// Include lightbox modal and script for image viewing
if ($viewMode || $existingFeasibility) {
    echo renderLightboxModal();
    echo getLightboxScript();
}

$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

<?php
/**
 * Feasibility Check Form Page
 * 
 * Displays the feasibility check form for engineers to complete site assessments.
 * Shows read-only master site information and allows input of feasibility data.
 * Also handles rejection feedback display and resubmission workflow.
 * 
 * Requirements: 4.1, 4.2, 4.3, 5.1-5.6, 6.1, 7.1, 7.2, 7.3, 9.1, 9.2, 9.4, 12.1, 12.2, 12.3
 * - 4.1: Redirect to feasibility form with master site information pre-populated
 * - 4.2: Display read-only master site information
 * - 4.3: Validate all required fields before submission
 * - 5.1-5.6: Capture comprehensive site infrastructure details
 * - 6.1: Include image upload fields for key infrastructure components
 * - 7.1-7.3: Include remarks text field with 2000 character limit
 * - 9.1: Display uploaded images as visible thumbnail previews (150x150 pixels)
 * - 9.2: Display full-size image in lightbox modal when thumbnail is clicked
 * - 9.4: Display all thumbnails in a grid layout when multiple images exist
 * - 12.1: Display rejection reason prominently
 * - 12.2: Highlight rejected sections with visual indicators (red border/background)
 * - 12.3: Allow modification only of rejected sections
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/FeasibilityService.php';
require_once __DIR__ . '/../services/FeasibilityReviewService.php';
require_once __DIR__ . '/../services/SiteAccessService.php';
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

/**
 * Helper function to check if a section is rejected
 * @param string $sectionName Section name to check
 * @param array $editableSections List of editable/rejected sections
 * @param array|null $rejectionInfo Rejection info array
 * @return bool True if section is rejected
 */
function isSectionRejected($sectionName, $editableSections, $rejectionInfo) {
    if (!$rejectionInfo) return false;
    
    // For overall rejection, all sections are considered rejected
    if ($rejectionInfo['rejection_type'] === 'overall') {
        return true;
    }
    
    // For section-specific rejection, check if this section is in the list
    return in_array($sectionName, $editableSections);
}

/**
 * Helper function to check if a field is editable in resubmit mode
 * @param string $fieldName Field name to check
 * @param bool $resubmitMode Whether we're in resubmit mode
 * @param array $editableFields List of editable fields
 * @return bool True if field is editable
 */
function isFieldEditable($fieldName, $resubmitMode, $editableFields) {
    if (!$resubmitMode) return true;
    return in_array($fieldName, $editableFields);
}

/**
 * Get CSS classes for a section based on rejection status
 * @param string $sectionName Section name
 * @param array $editableSections List of editable/rejected sections
 * @param array|null $rejectionInfo Rejection info
 * @param bool $isRejected Whether feasibility is rejected
 * @return string CSS classes
 */
function getSectionClasses($sectionName, $editableSections, $rejectionInfo, $isRejected) {
    if (!$isRejected) return '';
    
    if (isSectionRejected($sectionName, $editableSections, $rejectionInfo)) {
        return 'rejected-section';
    }
    return '';
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

// Add rejection highlight styles (Requirements 12.1, 12.2)
?>
<style>
/* Rejection highlight styles - Requirement 12.2 */
.rejected-section {
    border: 2px solid #ef4444 !important;
    background-color: #fef2f2 !important;
}

.rejected-section .p-4.border-b {
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
    opacity: 0.6;
    pointer-events: none;
    background-color: #f3f4f6 !important;
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

/* Review history styles */
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

/* Slide-out panel styles */
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
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    font-size: 14px;
}

.rejection-toggle-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}
</style>
<?php
// Prepare rejection info for sidebar (must be defined before panel HTML)
$hasRejectionSidebar = $isRejected && $rejectionInfo;
$rejectionTypeLabel = '';
$rejectedByLabel = '';
$rejectedAtFormatted = '';
if ($hasRejectionSidebar) {
    $rejectionTypeLabel = $rejectionInfo['rejection_type'] === 'overall' ? 'Overall Rejection' : 'Section-Specific Rejection';
    $rejectedByLabel = $rejectionInfo['rejected_by'] ?? 'Reviewer';
    $rejectedAtFormatted = $rejectionInfo['rejected_at'] ? date('M d, Y h:i A', strtotime($rejectionInfo['rejected_at'])) : 'N/A';
}
?>

<!-- Slide-out Rejection Panel -->
<?php if ($hasRejectionSidebar || !empty($reviewHistory)): ?>
<div id="rejection-panel-overlay" class="rejection-panel-overlay" onclick="closeRejectionPanel()"></div>
<div id="rejection-panel" class="rejection-panel">
    <div class="rejection-panel-header">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-red-500 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <div class="font-semibold text-red-800">Rejection Details</div>
                <div class="text-xs text-red-600"><?php echo htmlspecialchars($rejectionTypeLabel); ?></div>
            </div>
        </div>
        <button class="rejection-panel-close" onclick="closeRejectionPanel()">
            <i class="fas fa-times text-red-500"></i>
        </button>
    </div>
    
    <div class="p-5 space-y-5">
        <?php if ($hasRejectionSidebar): ?>
        <!-- Rejection Reason -->
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="text-xs font-semibold text-red-800 uppercase mb-2">Rejection Reason</div>
            <div class="text-sm text-gray-700"><?php echo htmlspecialchars($rejectionInfo['rejection_reason'] ?? 'No reason provided'); ?></div>
        </div>
        
        <?php if ($rejectionInfo['rejection_type'] === 'section_specific' && !empty($editableSections)): ?>
        <!-- Rejected Sections -->
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
        
        <!-- Rejection Meta -->
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
        <!-- Review History -->
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

<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800"><?php echo $pageTitle; ?></h3>
                <p class="text-sm text-gray-500">
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
                <button onclick="openRejectionPanel()" class="rejection-toggle-btn">
                    <i class="fas fa-exclamation-circle"></i>
                    View Rejection Details
                </button>
                <?php endif; ?>
                <a href="sites.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Sites
                </a>
            </div>
        </div>
    </div>

            <!-- Master Site Information (Read-only) - Requirement 4.2 -->
            <div class="bg-white rounded-xl shadow-sm mb-6">
                <div class="p-4 border-b bg-blue-50">
                    <h4 class="font-semibold text-blue-800"><i class="fas fa-info-circle mr-2"></i>Site Information</h4>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Site Name</label>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($siteInfo['site_name'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">LHO</label>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($siteInfo['lho'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Bank Name</label>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($siteInfo['bank_name'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Customer Name</label>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($siteInfo['customer_name'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">City</label>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($siteInfo['city'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">State</label>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($siteInfo['state'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Address</label>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($siteInfo['address'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Coordinates</label>
                            <p class="font-medium text-gray-800">
                                <?php if ($siteInfo['latitude'] && $siteInfo['longitude']): ?>
                                    <a href="https://www.google.com/maps?q=<?php echo $siteInfo['latitude']; ?>,<?php echo $siteInfo['longitude']; ?>" 
                                       target="_blank" class="text-blue-600 hover:underline">
                                        <?php echo $siteInfo['latitude']; ?>, <?php echo $siteInfo['longitude']; ?>
                                        <i class="fas fa-external-link-alt ml-1 text-xs"></i>
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
                
                <?php 
                // Helper variables for section editability
                $atmSectionRejected = isSectionRejected('atm_information', $editableSections, $rejectionInfo);
                $atmSectionClasses = getSectionClasses('atm_information', $editableSections, $rejectionInfo, $isRejected);
                $atmFieldsDisabled = $viewMode || ($resubmitMode && !$atmSectionRejected);
                ?>
                
                <!-- ATM Information - Requirement 5.1 -->
                <div class="bg-white rounded-xl shadow-sm mb-6 <?php echo $atmSectionClasses; ?>" data-section="atm_information">
                    <div class="p-4 border-b bg-yellow-50">
                        <h4 class="font-semibold text-yellow-800">
                            <i class="fas fa-credit-card mr-2"></i>ATM Information
                            <?php if ($isRejected && $atmSectionRejected): ?>
                            <span class="ml-2 text-xs bg-red-500 text-white px-2 py-1 rounded">Needs Correction</span>
                            <?php endif; ?>
                        </h4>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Number of ATMs <span class="text-red-500">*</span>
                                </label>
                                <select name="no_of_atm" id="no_of_atm" required <?php echo $atmFieldsDisabled ? 'disabled' : ''; ?>
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent <?php echo $atmFieldsDisabled ? 'field-disabled' : ''; ?>"
                                    onchange="toggleATMFields()">
                                    <option value="">Select</option>
                                    <option value="0" <?php echo ($existingFeasibility['no_of_atm'] ?? '') == '0' ? 'selected' : ''; ?>>0</option>
                                    <option value="1" <?php echo ($existingFeasibility['no_of_atm'] ?? '') == '1' ? 'selected' : ''; ?>>1</option>
                                    <option value="2" <?php echo ($existingFeasibility['no_of_atm'] ?? '') == '2' ? 'selected' : ''; ?>>2</option>
                                    <option value="3" <?php echo ($existingFeasibility['no_of_atm'] ?? '') == '3' ? 'selected' : ''; ?>>3</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- ATM 1 -->
                        <div id="atm-1-fields" class="mt-4 p-4 bg-gray-50 rounded-lg hidden">
                            <h5 class="font-medium text-gray-700 mb-3">ATM 1</h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">ATM ID</label>
                                    <input type="text" name="atm_id_1" <?php echo $atmFieldsDisabled ? 'disabled' : ''; ?>
                                        value="<?php echo htmlspecialchars($existingFeasibility['atm_id_1'] ?? ''); ?>"
                                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $atmFieldsDisabled ? 'field-disabled' : ''; ?>">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">ATM Status</label>
                                    <select name="atm_1_status" <?php echo $atmFieldsDisabled ? 'disabled' : ''; ?>
                                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $atmFieldsDisabled ? 'field-disabled' : ''; ?>">
                                        <option value="">Select</option>
                                        <option value="working" <?php echo ($existingFeasibility['atm_1_status'] ?? '') == 'working' ? 'selected' : ''; ?>>Working</option>
                                        <option value="not_working" <?php echo ($existingFeasibility['atm_1_status'] ?? '') == 'not_working' ? 'selected' : ''; ?>>Not Working</option>
                                        <option value="maintenance" <?php echo ($existingFeasibility['atm_1_status'] ?? '') == 'maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ATM 2 -->
                        <div id="atm-2-fields" class="mt-4 p-4 bg-gray-50 rounded-lg hidden">
                            <h5 class="font-medium text-gray-700 mb-3">ATM 2</h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">ATM ID</label>
                                    <input type="text" name="atm_id_2" <?php echo $atmFieldsDisabled ? 'disabled' : ''; ?>
                                value="<?php echo htmlspecialchars($existingFeasibility['atm_id_2'] ?? ''); ?>"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $atmFieldsDisabled ? 'field-disabled' : ''; ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ATM Status</label>
                            <select name="atm_2_status" <?php echo $atmFieldsDisabled ? 'disabled' : ''; ?>
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $atmFieldsDisabled ? 'field-disabled' : ''; ?>">
                                <option value="">Select</option>
                                <option value="working" <?php echo ($existingFeasibility['atm_2_status'] ?? '') == 'working' ? 'selected' : ''; ?>>Working</option>
                                <option value="not_working" <?php echo ($existingFeasibility['atm_2_status'] ?? '') == 'not_working' ? 'selected' : ''; ?>>Not Working</option>
                                <option value="maintenance" <?php echo ($existingFeasibility['atm_2_status'] ?? '') == 'maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- ATM 3 -->
                <div id="atm-3-fields" class="mt-4 p-4 bg-gray-50 rounded-lg hidden">
                    <h5 class="font-medium text-gray-700 mb-3">ATM 3</h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ATM ID</label>
                            <input type="text" name="atm_id_3" <?php echo $atmFieldsDisabled ? 'disabled' : ''; ?>
                                value="<?php echo htmlspecialchars($existingFeasibility['atm_id_3'] ?? ''); ?>"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $atmFieldsDisabled ? 'field-disabled' : ''; ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ATM Status</label>
                            <select name="atm_3_status" <?php echo $atmFieldsDisabled ? 'disabled' : ''; ?>
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $atmFieldsDisabled ? 'field-disabled' : ''; ?>"></select>
                                <option value="">Select</option>
                                <option value="working" <?php echo ($existingFeasibility['atm_3_status'] ?? '') == 'working' ? 'selected' : ''; ?>>Working</option>
                                <option value="not_working" <?php echo ($existingFeasibility['atm_3_status'] ?? '') == 'not_working' ? 'selected' : ''; ?>>Not Working</option>
                                <option value="maintenance" <?php echo ($existingFeasibility['atm_3_status'] ?? '') == 'maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php 
        // Network Information section variables
        $networkSectionRejected = isSectionRejected('network_information', $editableSections, $rejectionInfo);
        $networkSectionClasses = getSectionClasses('network_information', $editableSections, $rejectionInfo, $isRejected);
        $networkFieldsDisabled = $viewMode || ($resubmitMode && !$networkSectionRejected);
        ?>

        <!-- Network Information - Requirement 5.2 -->
        <div class="bg-white rounded-xl shadow-sm mb-6 <?php echo $networkSectionClasses; ?>" data-section="network_information">
            <div class="p-4 border-b bg-green-50">
                <h4 class="font-semibold text-green-800">
                    <i class="fas fa-wifi mr-2"></i>Network Information
                    <?php if ($isRejected && $networkSectionRejected): ?>
                    <span class="ml-2 text-xs bg-red-500 text-white px-2 py-1 rounded">Needs Correction</span>
                    <?php endif; ?>
                </h4>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Primary Operator <span class="text-red-500">*</span>
                        </label>
                        <select name="operator" required <?php echo $networkFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $networkFieldsDisabled ? 'field-disabled' : ''; ?>">
                            <option value="">Select</option>
                            <option value="Airtel" <?php echo ($existingFeasibility['operator'] ?? '') == 'Airtel' ? 'selected' : ''; ?>>Airtel</option>
                            <option value="Jio" <?php echo ($existingFeasibility['operator'] ?? '') == 'Jio' ? 'selected' : ''; ?>>Jio</option>
                            <option value="Vi" <?php echo ($existingFeasibility['operator'] ?? '') == 'Vi' ? 'selected' : ''; ?>>Vi</option>
                            <option value="BSNL" <?php echo ($existingFeasibility['operator'] ?? '') == 'BSNL' ? 'selected' : ''; ?>>BSNL</option>
                            <option value="Other" <?php echo ($existingFeasibility['operator'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Signal Status <span class="text-red-500">*</span>
                        </label>
                        <select name="signal_status" required <?php echo $networkFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $networkFieldsDisabled ? 'field-disabled' : ''; ?>">
                            <option value="">Select</option>
                            <option value="excellent" <?php echo ($existingFeasibility['signal_status'] ?? '') == 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                            <option value="good" <?php echo ($existingFeasibility['signal_status'] ?? '') == 'good' ? 'selected' : ''; ?>>Good</option>
                            <option value="poor" <?php echo ($existingFeasibility['signal_status'] ?? '') == 'poor' ? 'selected' : ''; ?>>Poor</option>
                            <option value="no_signal" <?php echo ($existingFeasibility['signal_status'] ?? '') == 'no_signal' ? 'selected' : ''; ?>>No Signal</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Secondary Operator</label>
                        <select name="operator_2" <?php echo $networkFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $networkFieldsDisabled ? 'field-disabled' : ''; ?>">
                            <option value="">Select</option>
                            <option value="Airtel" <?php echo ($existingFeasibility['operator_2'] ?? '') == 'Airtel' ? 'selected' : ''; ?>>Airtel</option>
                            <option value="Jio" <?php echo ($existingFeasibility['operator_2'] ?? '') == 'Jio' ? 'selected' : ''; ?>>Jio</option>
                            <option value="Vi" <?php echo ($existingFeasibility['operator_2'] ?? '') == 'Vi' ? 'selected' : ''; ?>>Vi</option>
                            <option value="BSNL" <?php echo ($existingFeasibility['operator_2'] ?? '') == 'BSNL' ? 'selected' : ''; ?>>BSNL</option>
                            <option value="Other" <?php echo ($existingFeasibility['operator_2'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Secondary Signal Status</label>
                        <select name="signal_status_2" <?php echo $networkFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $networkFieldsDisabled ? 'field-disabled' : ''; ?>">
                            <option value="">Select</option>
                            <option value="excellent" <?php echo ($existingFeasibility['signal_status_2'] ?? '') == 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                            <option value="good" <?php echo ($existingFeasibility['signal_status_2'] ?? '') == 'good' ? 'selected' : ''; ?>>Good</option>
                            <option value="poor" <?php echo ($existingFeasibility['signal_status_2'] ?? '') == 'poor' ? 'selected' : ''; ?>>Poor</option>
                            <option value="no_signal" <?php echo ($existingFeasibility['signal_status_2'] ?? '') == 'no_signal' ? 'selected' : ''; ?>>No Signal</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Backroom Network Remarks</label>
                    <textarea name="backroom_network_remark" rows="2" <?php echo $networkFieldsDisabled ? 'disabled' : ''; ?>
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $networkFieldsDisabled ? 'field-disabled' : ''; ?>"><?php echo htmlspecialchars($existingFeasibility['backroom_network_remark'] ?? ''); ?></textarea>
                </div>
                <?php if (!$viewMode && !$networkFieldsDisabled): ?>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Network Snapshot</label>
                    <input type="file" name="backroom_network_snap" accept="image/jpeg,image/png"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <p class="text-xs text-gray-500 mt-1">JPEG or PNG, max 5MB</p>
                </div>
                <?php elseif ($existingFeasibility['backroom_network_snap'] ?? ''): ?>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Network Snapshot</label>
                    <?php echo renderImageThumbnail($existingFeasibility['backroom_network_snap'], 'Network Snapshot'); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php 
        // Power Infrastructure section variables
        $powerSectionRejected = isSectionRejected('power_infrastructure', $editableSections, $rejectionInfo);
        $powerSectionClasses = getSectionClasses('power_infrastructure', $editableSections, $rejectionInfo, $isRejected);
        $powerFieldsDisabled = $viewMode || ($resubmitMode && !$powerSectionRejected);
        ?>

        <!-- Power Infrastructure - Requirement 5.3 -->
        <div class="bg-white rounded-xl shadow-sm mb-6 <?php echo $powerSectionClasses; ?>" data-section="power_infrastructure">
            <div class="p-4 border-b bg-orange-50">
                <h4 class="font-semibold text-orange-800"><i class="fas fa-bolt mr-2"></i>Power Infrastructure</h4>
                    <i class="fas fa-bolt mr-2"></i>Power Infrastructure
                    <?php if ($isRejected && $powerSectionRejected): ?>
                    <span class="ml-2 text-xs bg-red-500 text-white px-2 py-1 rounded">Needs Correction</span>
                    <?php endif; ?>
                </h4>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            UPS Available <span class="text-red-500">*</span>
                        </label>
                        <select name="ups_available" id="ups_available" required <?php echo $powerFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $powerFieldsDisabled ? 'field-disabled' : ''; ?>"
                            onchange="toggleUPSFields()">
                            <option value="">Select</option>
                            <option value="yes" <?php echo ($existingFeasibility['ups_available'] ?? '') == 'yes' ? 'selected' : ''; ?>>Yes</option>
                            <option value="no" <?php echo ($existingFeasibility['ups_available'] ?? '') == 'no' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                    <div id="ups-count-field" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Number of UPS</label>
                        <select name="no_of_ups" id="no_of_ups" <?php echo $powerFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $powerFieldsDisabled ? 'field-disabled' : ''; ?>"
                            onchange="toggleUPSWorkingFields()">
                            <option value="">Select</option>
                            <option value="1" <?php echo ($existingFeasibility['no_of_ups'] ?? '') == '1' ? 'selected' : ''; ?>>1</option>
                            <option value="2" <?php echo ($existingFeasibility['no_of_ups'] ?? '') == '2' ? 'selected' : ''; ?>>2</option>
                            <option value="3" <?php echo ($existingFeasibility['no_of_ups'] ?? '') == '3' ? 'selected' : ''; ?>>3</option>
                        </select>
                    </div>
                    <div id="ups-backup-field" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">UPS Battery Backup</label>
                        <select name="ups_battery_backup" <?php echo $powerFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $powerFieldsDisabled ? 'field-disabled' : ''; ?>">
                            <option value="">Select</option>
                            <option value="less_than_30min" <?php echo ($existingFeasibility['ups_battery_backup'] ?? '') == 'less_than_30min' ? 'selected' : ''; ?>>Less than 30 min</option>
                            <option value="30min_to_1hr" <?php echo ($existingFeasibility['ups_battery_backup'] ?? '') == '30min_to_1hr' ? 'selected' : ''; ?>>30 min - 1 hour</option>
                            <option value="1hr_to_2hr" <?php echo ($existingFeasibility['ups_battery_backup'] ?? '') == '1hr_to_2hr' ? 'selected' : ''; ?>>1 - 2 hours</option>
                            <option value="more_than_2hr" <?php echo ($existingFeasibility['ups_battery_backup'] ?? '') == 'more_than_2hr' ? 'selected' : ''; ?>>More than 2 hours</option>
                        </select>
                    </div>
                </div>
                
                <!-- UPS Working Status -->
                <div id="ups-working-fields" class="mt-4 hidden">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div id="ups-working-1-field" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">UPS 1 Working</label>
                            <select name="ups_working_1" <?php echo $powerFieldsDisabled ? 'disabled' : ''; ?>
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $powerFieldsDisabled ? 'field-disabled' : ''; ?>">
                                <option value="">Select</option>
                                <option value="yes" <?php echo ($existingFeasibility['ups_working_1'] ?? '') == 'yes' ? 'selected' : ''; ?>>Yes</option>
                                <option value="no" <?php echo ($existingFeasibility['ups_working_1'] ?? '') == 'no' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div id="ups-working-2-field" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">UPS 2 Working</label>
                            <select name="ups_working_2" <?php echo $powerFieldsDisabled ? 'disabled' : ''; ?>
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $powerFieldsDisabled ? 'field-disabled' : ''; ?>">
                                <option value="">Select</option>
                                <option value="yes" <?php echo ($existingFeasibility['ups_working_2'] ?? '') == 'yes' ? 'selected' : ''; ?>>Yes</option>
                                <option value="no" <?php echo ($existingFeasibility['ups_working_2'] ?? '') == 'no' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div id="ups-working-3-field" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">UPS 3 Working</label>
                            <select name="ups_working_3" <?php echo $powerFieldsDisabled ? 'disabled' : ''; ?>
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $powerFieldsDisabled ? 'field-disabled' : ''; ?>">
                                <option value="">Select</option>
                                <option value="yes" <?php echo ($existingFeasibility['ups_working_3'] ?? '') == 'yes' ? 'selected' : ''; ?>>Yes</option>
                                <option value="no" <?php echo ($existingFeasibility['ups_working_3'] ?? '') == 'no' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Power Socket Availability</label>
                        <select name="power_socket_availability" <?php echo $powerFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $powerFieldsDisabled ? 'field-disabled' : ''; ?>">
                            <option value="">Select</option>
                            <option value="available" <?php echo ($existingFeasibility['power_socket_availability'] ?? '') == 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="not_available" <?php echo ($existingFeasibility['power_socket_availability'] ?? '') == 'not_available' ? 'selected' : ''; ?>>Not Available</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Power Socket for UPS</label>
                        <select name="power_socket_availability_ups" <?php echo $powerFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $powerFieldsDisabled ? 'field-disabled' : ''; ?>">
                            <option value="">Select</option>
                            <option value="available" <?php echo ($existingFeasibility['power_socket_availability_ups'] ?? '') == 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="not_available" <?php echo ($existingFeasibility['power_socket_availability_ups'] ?? '') == 'not_available' ? 'selected' : ''; ?>>Not Available</option>
                        </select>
                    </div>
                </div>
                
                <?php if (!$viewMode && !$powerFieldsDisabled): ?>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">UPS Snapshot</label>
                    <input type="file" name="ups_available_snap" accept="image/jpeg,image/png"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <p class="text-xs text-gray-500 mt-1">JPEG or PNG, max 5MB</p>
                </div>
                <?php elseif ($existingFeasibility['ups_available_snap'] ?? ''): ?>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">UPS Snapshot</label>
                    <?php echo renderImageThumbnail($existingFeasibility['ups_available_snap'], 'UPS Snapshot'); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php 
        // Electrical Measurements section variables
        $electricalSectionRejected = isSectionRejected('electrical_measurements', $editableSections, $rejectionInfo);
        $electricalSectionClasses = getSectionClasses('electrical_measurements', $editableSections, $rejectionInfo, $isRejected);
        $electricalFieldsDisabled = $viewMode || ($resubmitMode && !$electricalSectionRejected);
        ?>

        <!-- Electrical Measurements - Requirement 5.4 -->
        <div class="bg-white rounded-xl shadow-sm mb-6 <?php echo $electricalSectionClasses; ?>" data-section="electrical_measurements">
            <div class="p-4 border-b bg-red-50">
                <h4 class="font-semibold text-red-800">
                    <i class="fas fa-plug mr-2"></i>Electrical Measurements
                    <?php if ($isRejected && $electricalSectionRejected): ?>
                    <span class="ml-2 text-xs bg-red-500 text-white px-2 py-1 rounded">Needs Correction</span>
                    <?php endif; ?>
                </h4>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Earthing Status <span class="text-red-500">*</span>
                        </label>
                        <select name="earthing" required <?php echo $electricalFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $electricalFieldsDisabled ? 'field-disabled' : ''; ?>">
                            <option value="">Select</option>
                            <option value="yes" <?php echo ($existingFeasibility['earthing'] ?? '') == 'yes' ? 'selected' : ''; ?>>Yes</option>
                            <option value="no" <?php echo ($existingFeasibility['earthing'] ?? '') == 'no' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Earthing Voltage</label>
                        <input type="text" name="earthing_voltage" <?php echo $electricalFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['earthing_voltage'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $electricalFieldsDisabled ? 'field-disabled' : ''; ?>"
                            placeholder="e.g., 0.5V">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Power Fluctuation E-N</label>
                        <input type="text" name="power_fluctuation_en" <?php echo $electricalFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['power_fluctuation_en'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $electricalFieldsDisabled ? 'field-disabled' : ''; ?>"
                            placeholder="e.g., 220V">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Power Fluctuation P-E</label>
                        <input type="text" name="power_fluctuation_pe" <?php echo $electricalFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['power_fluctuation_pe'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $electricalFieldsDisabled ? 'field-disabled' : ''; ?>"
                            placeholder="e.g., 0V">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Power Fluctuation P-N</label>
                        <input type="text" name="power_fluctuation_pn" <?php echo $electricalFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['power_fluctuation_pn'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $electricalFieldsDisabled ? 'field-disabled' : ''; ?>"
                            placeholder="e.g., 220V">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Frequent Power Cut</label>
                        <select name="frequent_power_cut" id="frequent_power_cut" <?php echo $electricalFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $electricalFieldsDisabled ? 'field-disabled' : ''; ?>"
                            onchange="togglePowerCutFields()">
                            <option value="">Select</option>
                            <option value="yes" <?php echo ($existingFeasibility['frequent_power_cut'] ?? '') == 'yes' ? 'selected' : ''; ?>>Yes</option>
                            <option value="no" <?php echo ($existingFeasibility['frequent_power_cut'] ?? '') == 'no' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                    <div id="power-cut-from-field" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Power Cut From</label>
                        <input type="time" name="frequent_power_cut_from" <?php echo $electricalFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['frequent_power_cut_from'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $electricalFieldsDisabled ? 'field-disabled' : ''; ?>">
                    </div>
                    <div id="power-cut-to-field" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Power Cut To</label>
                        <input type="time" name="frequent_power_cut_to" <?php echo $electricalFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['frequent_power_cut_to'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $electricalFieldsDisabled ? 'field-disabled' : ''; ?>">
                    </div>
                </div>
                <div id="power-cut-remark-field" class="mt-4 hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Power Cut Remarks</label>
                    <textarea name="frequent_power_cut_remark" rows="2" <?php echo $electricalFieldsDisabled ? 'disabled' : ''; ?>
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $electricalFieldsDisabled ? 'field-disabled' : ''; ?>"><?php echo htmlspecialchars($existingFeasibility['frequent_power_cut_remark'] ?? ''); ?></textarea>
                </div>
                
                <?php if (!$viewMode && !$electricalFieldsDisabled): ?>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Earthing Snapshot</label>
                    <input type="file" name="earthing_snap" accept="image/jpeg,image/png"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <p class="text-xs text-gray-500 mt-1">JPEG or PNG, max 5MB</p>
                </div>
                <?php elseif ($existingFeasibility['earthing_snap'] ?? ''): ?>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Earthing Snapshot</label>
                    <?php echo renderImageThumbnail($existingFeasibility['earthing_snap'], 'Earthing Snapshot'); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php 
        // Site Access section variables
        $accessSectionRejected = isSectionRejected('site_access', $editableSections, $rejectionInfo);
        $accessSectionClasses = getSectionClasses('site_access', $editableSections, $rejectionInfo, $isRejected);
        $accessFieldsDisabled = $viewMode || ($resubmitMode && !$accessSectionRejected);
        ?>

        <!-- Site Access - Requirement 5.5 -->
        <div class="bg-white rounded-xl shadow-sm mb-6 <?php echo $accessSectionClasses; ?>" data-section="site_access">
            <div class="p-4 border-b bg-purple-50">
                <h4 class="font-semibold text-purple-800">
                    <i class="fas fa-key mr-2"></i>Site Access
                    <?php if ($isRejected && $accessSectionRejected): ?>
                    <span class="ml-2 text-xs bg-red-500 text-white px-2 py-1 rounded">Needs Correction</span>
                    <?php endif; ?>
                </h4>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">EM Lock Available</label>
                        <select name="em_lock_available" <?php echo $accessFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $accessFieldsDisabled ? 'field-disabled' : ''; ?>">
                            <option value="">Select</option>
                            <option value="yes" <?php echo ($existingFeasibility['em_lock_available'] ?? '') == 'yes' ? 'selected' : ''; ?>>Yes</option>
                            <option value="no" <?php echo ($existingFeasibility['em_lock_available'] ?? '') == 'no' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">EM Lock Password</label>
                        <input type="text" name="em_lock_password" <?php echo $accessFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['em_lock_password'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $accessFieldsDisabled ? 'field-disabled' : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password Received</label>
                        <select name="password_received" <?php echo $accessFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $accessFieldsDisabled ? 'field-disabled' : ''; ?>">
                            <option value="">Select</option>
                            <option value="yes" <?php echo ($existingFeasibility['password_received'] ?? '') == 'yes' ? 'selected' : ''; ?>>Yes</option>
                            <option value="no" <?php echo ($existingFeasibility['password_received'] ?? '') == 'no' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Backroom Key Contact Name</label>
                        <input type="text" name="backroom_key_name" <?php echo $accessFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['backroom_key_name'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $accessFieldsDisabled ? 'field-disabled' : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Backroom Key Contact Number</label>
                        <input type="text" name="backroom_key_number" <?php echo $accessFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['backroom_key_number'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $accessFieldsDisabled ? 'field-disabled' : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Backroom Key Status</label>
                        <select name="backroom_key_status" <?php echo $accessFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $accessFieldsDisabled ? 'field-disabled' : ''; ?>">
                            <option value="">Select</option>
                            <option value="available" <?php echo ($existingFeasibility['backroom_key_status'] ?? '') == 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="not_available" <?php echo ($existingFeasibility['backroom_key_status'] ?? '') == 'not_available' ? 'selected' : ''; ?>>Not Available</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <?php 
        // Environmental Factors section variables
        $envSectionRejected = isSectionRejected('environmental_factors', $editableSections, $rejectionInfo);
        $envSectionClasses = getSectionClasses('environmental_factors', $editableSections, $rejectionInfo, $isRejected);
        $envFieldsDisabled = $viewMode || ($resubmitMode && !$envSectionRejected);
        ?>

        <!-- Environmental Factors - Requirement 5.6 -->
        <div class="bg-white rounded-xl shadow-sm mb-6 <?php echo $envSectionClasses; ?>" data-section="environmental_factors">
            <div class="p-4 border-b bg-teal-50">
                <h4 class="font-semibold text-teal-800">
                    <i class="fas fa-tree mr-2"></i>Environmental Factors
                    <?php if ($isRejected && $envSectionRejected): ?>
                    <span class="ml-2 text-xs bg-red-500 text-white px-2 py-1 rounded">Needs Correction</span>
                    <?php endif; ?>
                </h4>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Router/Antenna Position</label>
                        <input type="text" name="router_antenna_position" <?php echo $envFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['router_antenna_position'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $envFieldsDisabled ? 'field-disabled' : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Router Position</label>
                        <input type="text" name="router_position" <?php echo $envFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['router_position'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $envFieldsDisabled ? 'field-disabled' : ''; ?>">
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Antenna Routing Detail</label>
                    <textarea name="antenna_routing_detail" rows="2" <?php echo $envFieldsDisabled ? 'disabled' : ''; ?>
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $envFieldsDisabled ? 'field-disabled' : ''; ?>"><?php echo htmlspecialchars($existingFeasibility['antenna_routing_detail'] ?? ''); ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nearest Shop Name</label>
                        <input type="text" name="nearest_shop_name" <?php echo $envFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['nearest_shop_name'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $envFieldsDisabled ? 'field-disabled' : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nearest Shop Number</label>
                        <input type="text" name="nearest_shop_number" <?php echo $envFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['nearest_shop_number'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $envFieldsDisabled ? 'field-disabled' : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nearest Shop Distance</label>
                        <input type="text" name="nearest_shop_distance" <?php echo $envFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['nearest_shop_distance'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $envFieldsDisabled ? 'field-disabled' : ''; ?>"
                            placeholder="e.g., 100m">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Backroom Disturbing Material</label>
                        <select name="backroom_disturbing_material" <?php echo $envFieldsDisabled ? 'disabled' : ''; ?>
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $envFieldsDisabled ? 'field-disabled' : ''; ?>">
                            <option value="">Select</option>
                            <option value="yes" <?php echo ($existingFeasibility['backroom_disturbing_material'] ?? '') == 'yes' ? 'selected' : ''; ?>>Yes</option>
                            <option value="no" <?php echo ($existingFeasibility['backroom_disturbing_material'] ?? '') == 'no' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Disturbing Material Remarks</label>
                        <input type="text" name="backroom_disturbing_material_remark" <?php echo $envFieldsDisabled ? 'disabled' : ''; ?>
                            value="<?php echo htmlspecialchars($existingFeasibility['backroom_disturbing_material_remark'] ?? ''); ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $envFieldsDisabled ? 'field-disabled' : ''; ?>">
                    </div>
                </div>
                
                <?php if (!$viewMode && !$envFieldsDisabled): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Router/Antenna Snapshot</label>
                        <input type="file" name="router_antenna_snap" accept="image/jpeg,image/png"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <p class="text-xs text-gray-500 mt-1">JPEG or PNG, max 5MB</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Antenna Routing Snapshot</label>
                        <input type="file" name="antenna_routing_snap" accept="image/jpeg,image/png"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <p class="text-xs text-gray-500 mt-1">JPEG or PNG, max 5MB</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <?php if ($existingFeasibility['router_antenna_snap'] ?? ''): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Router/Antenna Snapshot</label>
                        <?php echo renderImageThumbnail($existingFeasibility['router_antenna_snap'], 'Router/Antenna Snapshot'); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($existingFeasibility['antenna_routing_snap'] ?? ''): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Antenna Routing Snapshot</label>
                        <?php echo renderImageThumbnail($existingFeasibility['antenna_routing_snap'], 'Antenna Routing Snapshot'); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php 
        // Remarks section variables
        $remarksSectionRejected = isSectionRejected('remarks', $editableSections, $rejectionInfo);
        $remarksSectionClasses = getSectionClasses('remarks', $editableSections, $rejectionInfo, $isRejected);
        $remarksFieldsDisabled = $viewMode || ($resubmitMode && !$remarksSectionRejected);
        ?>

        <!-- Remarks - Requirements 7.1, 7.2, 7.3 -->
        <div class="bg-white rounded-xl shadow-sm mb-6 <?php echo $remarksSectionClasses; ?>" data-section="remarks">
            <div class="p-4 border-b bg-gray-100">
                <h4 class="font-semibold text-gray-800">
                    <i class="fas fa-comment-alt mr-2"></i>Remarks
                    <?php if ($isRejected && $remarksSectionRejected): ?>
                    <span class="ml-2 text-xs bg-red-500 text-white px-2 py-1 rounded">Needs Correction</span>
                    <?php endif; ?>
                </h4>
            </div>
            <div class="p-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">General Remarks</label>
                    <textarea name="remarks" id="remarks" rows="4" maxlength="2000" <?php echo $remarksFieldsDisabled ? 'disabled' : ''; ?>
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary <?php echo $remarksFieldsDisabled ? 'field-disabled' : ''; ?>"
                        oninput="updateCharCount()"><?php echo htmlspecialchars($existingFeasibility['remarks'] ?? ''); ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">
                        <span id="char-count">0</span>/2000 characters
                    </p>
                </div>
                
                <?php if (!$viewMode && !$remarksFieldsDisabled): ?>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Remarks Snapshot</label>
                    <input type="file" name="remarks_snap" accept="image/jpeg,image/png"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <p class="text-xs text-gray-500 mt-1">JPEG or PNG, max 5MB</p>
                </div>
                <?php elseif ($existingFeasibility['remarks_snap'] ?? ''): ?>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Remarks Snapshot</label>
                    <?php echo renderImageThumbnail($existingFeasibility['remarks_snap'], 'Remarks Snapshot'); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Submit Button -->
        <?php if (!$viewMode || $resubmitMode): ?>
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex justify-end space-x-4">
                <a href="sites.php" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </a>
                <?php if ($resubmitMode): ?>
                <button type="submit" id="submit-btn" class="resubmit-button">
                    <i class="fas fa-paper-plane mr-2"></i>Resubmit for Review
                </button>
                <?php else: ?>
                <button type="submit" id="submit-btn" class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-check mr-2"></i>Submit Feasibility Check
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
// Slide-out panel functions
function openRejectionPanel() {
    document.getElementById('rejection-panel').classList.add('active');
    document.getElementById('rejection-panel-overlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeRejectionPanel() {
    document.getElementById('rejection-panel').classList.remove('active');
    document.getElementById('rejection-panel-overlay').classList.remove('active');
    document.body.style.overflow = '';
}

// Close panel on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRejectionPanel();
    }
});

const API_URL = '../api/engineer/feasibility.php';
const RESUBMIT_API_URL = '../api/engineer/resubmit.php';
const viewMode = <?php echo $viewMode ? 'true' : 'false'; ?>;
const resubmitMode = <?php echo $resubmitMode ? 'true' : 'false'; ?>;
const feasibilityId = <?php echo $existingFeasibility['id'] ?? 'null'; ?>;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleATMFields();
    toggleUPSFields();
    toggleUPSWorkingFields();
    togglePowerCutFields();
    updateCharCount();
    
    if (!viewMode || resubmitMode) {
        document.getElementById('feasibility-form').addEventListener('submit', handleSubmit);
    }
});

// Toggle ATM fields based on number of ATMs
function toggleATMFields() {
    const noOfAtm = parseInt(document.getElementById('no_of_atm').value) || 0;
    
    document.getElementById('atm-1-fields').classList.toggle('hidden', noOfAtm < 1);
    document.getElementById('atm-2-fields').classList.toggle('hidden', noOfAtm < 2);
    document.getElementById('atm-3-fields').classList.toggle('hidden', noOfAtm < 3);
}

// Toggle UPS fields based on UPS availability
function toggleUPSFields() {
    const upsAvailable = document.getElementById('ups_available').value;
    const showUPS = upsAvailable === 'yes';
    
    document.getElementById('ups-count-field').classList.toggle('hidden', !showUPS);
    document.getElementById('ups-backup-field').classList.toggle('hidden', !showUPS);
    document.getElementById('ups-working-fields').classList.toggle('hidden', !showUPS);
    
    if (showUPS) {
        toggleUPSWorkingFields();
    }
}

// Toggle UPS working fields based on number of UPS
function toggleUPSWorkingFields() {
    const noOfUps = parseInt(document.getElementById('no_of_ups').value) || 0;
    
    document.getElementById('ups-working-1-field').classList.toggle('hidden', noOfUps < 1);
    document.getElementById('ups-working-2-field').classList.toggle('hidden', noOfUps < 2);
    document.getElementById('ups-working-3-field').classList.toggle('hidden', noOfUps < 3);
}

// Toggle power cut fields
function togglePowerCutFields() {
    const frequentPowerCut = document.getElementById('frequent_power_cut').value;
    const showFields = frequentPowerCut === 'yes';
    
    document.getElementById('power-cut-from-field').classList.toggle('hidden', !showFields);
    document.getElementById('power-cut-to-field').classList.toggle('hidden', !showFields);
    document.getElementById('power-cut-remark-field').classList.toggle('hidden', !showFields);
}

// Update character count for remarks - Requirement 7.2
function updateCharCount() {
    const remarks = document.getElementById('remarks');
    const charCount = document.getElementById('char-count');
    charCount.textContent = remarks.value.length;
    
    if (remarks.value.length > 2000) {
        charCount.classList.add('text-red-500');
    } else {
        charCount.classList.remove('text-red-500');
    }
}

// Handle form submission
async function handleSubmit(e) {
    e.preventDefault();
    
    const form = document.getElementById('feasibility-form');
    const formData = new FormData(form);
    
    // Validate remarks length - Requirement 7.2
    const remarks = document.getElementById('remarks').value;
    if (remarks.length > 2000) {
        showError('Remarks must not exceed 2000 characters');
        return;
    }
    
    const btn = document.getElementById('submit-btn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + (resubmitMode ? 'Resubmitting...' : 'Submitting...');
    
    try {
        if (resubmitMode) {
            // Handle resubmission
            await handleResubmit(formData);
        } else {
            // Handle new submission
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
    // Convert FormData to JSON for the main submission
    const jsonData = {};
    formData.forEach((value, key) => {
        // Skip file inputs for JSON submission
        if (!key.endsWith('_snap')) {
            jsonData[key] = value;
        }
    });
    
    // Submit main form data
    const response = await fetch(API_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify(jsonData)
    });
    
    const data = await response.json();
    
    if (data.success) {
        // Upload images if any
        const feasibilityId = data.data.id;
        await uploadImages(formData, feasibilityId);
        
        showToast('Feasibility check submitted successfully', 'success');
        
        // Redirect to sites list after short delay
        setTimeout(() => {
            window.location.href = 'sites.php';
        }, 1500);
    } else {
        showError(data.error?.message || 'Failed to submit feasibility check');
    }
}

// Handle resubmission of rejected feasibility
async function handleResubmit(formData) {
    // Convert FormData to JSON for the resubmission
    const jsonData = {};
    formData.forEach((value, key) => {
        // Skip file inputs and hidden fields for JSON submission
        if (!key.endsWith('_snap') && key !== 'assignment_id' && key !== 'feasibility_id' && key !== 'is_resubmit') {
            jsonData[key] = value;
        }
    });
    
    // Submit resubmission data
    const response = await fetch(`${RESUBMIT_API_URL}?id=${feasibilityId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify(jsonData)
    });
    
    const data = await response.json();
    
    if (data.success) {
        // Upload images if any
        await uploadImages(formData, feasibilityId);
        
        showToast('Feasibility check resubmitted successfully', 'success');
        
        // Redirect to sites list after short delay
        setTimeout(() => {
            window.location.href = 'sites.php';
        }, 1500);
    } else {
        showError(data.error?.message || data.message || 'Failed to resubmit feasibility check');
    }
}

// Upload images
async function uploadImages(formData, feasibilityId) {
    const imageFields = [
        'backroom_network_snap',
        'router_antenna_snap',
        'antenna_routing_snap',
        'ups_available_snap',
        'earthing_snap',
        'remarks_snap'
    ];
    
    for (const field of imageFields) {
        const file = formData.get(field);
        if (file && file.size > 0) {
            const uploadData = new FormData();
            uploadData.append('action', 'upload');
            uploadData.append('feasibility_id', feasibilityId);
            uploadData.append('category', field);
            uploadData.append('file', file);
            
            try {
                await fetch(API_URL + '?action=upload', {
                    method: 'POST',
                    credentials: 'include',
                    body: uploadData
                });
            } catch (error) {
                console.error(`Error uploading ${field}:`, error);
            }
        }
    }
}

// Show error message
function showError(message) {
    showToast(message, 'error');
}

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'error' ? 'bg-red-500' : type === 'success' ? 'bg-green-500' : 'bg-blue-500';
    toast.className = `fixed bottom-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>

<?php
// Include lightbox modal and script for view mode
if ($viewMode) {
    echo renderLightboxModal();
    echo getLightboxScript();
}

$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

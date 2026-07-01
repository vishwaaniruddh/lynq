<?php
/**
 * Contractor Feasibility Review Page
 * 
 * Displays feasibility check details for contractor admin/manager review.
 * Allows approve/reject with section-specific rejection support.
 * 
 * Requirements: 10.1, 10.3, 10.4, 10.5
 * - 10.1: Display review panel with approve/reject options for contractor admin/manager
 * - 10.3: Require rejection type selection (overall or section-specific)
 * - 10.4: Allow selection of one or more sections for section-specific rejection
 * - 10.5: Require reason text (minimum 10 characters) for rejections
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/FeasibilityService.php';
require_once __DIR__ . '/../services/FeasibilityReviewService.php';
require_once __DIR__ . '/../views/components/image_thumbnail.php';
require_once __DIR__ . '/../views/components/lightbox.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check contractor access - only contractor admin/manager can access this page
if (!isContractorUser()) {
    $_SESSION['flash_error'] = 'Access denied. Contractor users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();

// Verify user has review permissions (contractor_admin or contractor_manager)
$reviewService = new FeasibilityReviewService();
$feasibilityService = new FeasibilityService();

// Get feasibility ID from query parameter
$feasibilityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($feasibilityId <= 0) {
    $_SESSION['flash_error'] = 'Invalid feasibility ID.';
    header('Location: pending_reviews.php');
    exit;
}

// Verify user can review this feasibility check
$canReview = $reviewService->canUserReview($currentUser['id'], $feasibilityId);
if (!$canReview['canReview']) {
    $_SESSION['flash_error'] = $canReview['reason'] ?? 'Access denied. You do not have permission to review this feasibility check.';
    header('Location: dashboard.php');
    exit;
}

// Get feasibility check details
$feasibility = $feasibilityService->getFeasibilityCheck($feasibilityId);
if (!$feasibility) {
    $_SESSION['flash_error'] = 'Feasibility check not found.';
    header('Location: pending_reviews.php');
    exit;
}

// Get review history
$reviewHistory = $reviewService->getReviewHistory($feasibilityId);

// Get latest review if any
$latestReview = $reviewService->getLatestReview($feasibilityId);

// Check if already reviewed by contractor
$approvalStatus = $feasibility['approval_status'] ?? 'pending_contractor_review';
$isAlreadyReviewed = in_array($approvalStatus, ['contractor_approved', 'contractor_rejected', 'adv_approved', 'adv_rejected']);

$baseUrl = '..';
$pageTitle = 'Review Feasibility Check';
$currentPage = 'contractor_pending_reviews';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'Pending Reviews', 'url' => 'pending_reviews.php'],
    ['label' => 'Review Feasibility']
];

// Valid sections for rejection
$validSections = FeasibilityReviewService::getValidSections();
$sectionLabels = [
    'atm_information' => 'ATM Information',
    'network_information' => 'Network Information',
    'power_infrastructure' => 'Power Infrastructure',
    'electrical_measurements' => 'Electrical Measurements',
    'site_access' => 'Site Access',
    'environmental_factors' => 'Environmental Factors',
    'remarks' => 'Remarks'
];

ob_start();

// Include thumbnail and lightbox styles
echo getImageThumbnailStyles();
echo getLightboxStyles();
?>

<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800"><?php echo $pageTitle; ?></h3>
                <p class="text-sm text-gray-500">
                    Review feasibility check for <?php echo htmlspecialchars($feasibility['site_name'] ?? 'Unknown Site'); ?>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <?php
                $statusColors = [
                    'pending_contractor_review' => 'bg-yellow-100 text-yellow-700',
                    'contractor_approved' => 'bg-green-100 text-green-700',
                    'contractor_rejected' => 'bg-red-100 text-red-700',
                    'adv_approved' => 'bg-blue-100 text-blue-700',
                    'adv_rejected' => 'bg-orange-100 text-orange-700'
                ];
                $statusLabels = [
                    'pending_contractor_review' => 'Pending Review',
                    'contractor_approved' => 'Contractor Approved',
                    'contractor_rejected' => 'Contractor Rejected',
                    'adv_approved' => 'ADV Approved',
                    'adv_rejected' => 'ADV Rejected'
                ];
                $statusColor = $statusColors[$approvalStatus] ?? 'bg-gray-100 text-gray-700';
                $statusLabel = $statusLabels[$approvalStatus] ?? ucwords(str_replace('_', ' ', $approvalStatus));
                ?>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $statusColor; ?>">
                    <?php echo $statusLabel; ?>
                </span>
                <a href="pending_reviews.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>
    </div>

    <!-- Site Information -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-4 border-b bg-blue-50">
            <h4 class="font-semibold text-blue-800"><i class="fas fa-info-circle mr-2"></i>Site Information</h4>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Site Name</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['site_name'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">LHO</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['lho'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Bank Name</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['bank_name'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Customer Name</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['customer_name'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">City</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['city'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">State</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['state'] ?? 'N/A'); ?></p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Address</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['address'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Submitted By</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['created_by_name'] ?? 'N/A'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ATM Information Section -->
    <div class="bg-white rounded-xl shadow-sm mb-6" id="section-atm_information">
        <div class="p-4 border-b bg-yellow-50">
            <h4 class="font-semibold text-yellow-800"><i class="fas fa-credit-card mr-2"></i>ATM Information</h4>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Number of ATMs</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['no_of_atm'] ?? '0'); ?></p>
                </div>
                <?php if (($feasibility['no_of_atm'] ?? 0) >= 1): ?>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">ATM 1 ID</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['atm_id_1'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">ATM 1 Status</label>
                    <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $feasibility['atm_1_status'] ?? 'N/A')); ?></p>
                </div>
                <?php endif; ?>
                <?php if (($feasibility['no_of_atm'] ?? 0) >= 2): ?>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">ATM 2 ID</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['atm_id_2'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">ATM 2 Status</label>
                    <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $feasibility['atm_2_status'] ?? 'N/A')); ?></p>
                </div>
                <?php endif; ?>
                <?php if (($feasibility['no_of_atm'] ?? 0) >= 3): ?>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">ATM 3 ID</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['atm_id_3'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">ATM 3 Status</label>
                    <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $feasibility['atm_3_status'] ?? 'N/A')); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Network Information Section -->
    <div class="bg-white rounded-xl shadow-sm mb-6" id="section-network_information">
        <div class="p-4 border-b bg-green-50">
            <h4 class="font-semibold text-green-800"><i class="fas fa-wifi mr-2"></i>Network Information</h4>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Primary Operator</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['operator'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Signal Status</label>
                    <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $feasibility['signal_status'] ?? 'N/A')); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Secondary Operator</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['operator_2'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Secondary Signal Status</label>
                    <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $feasibility['signal_status_2'] ?? 'N/A')); ?></p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Backroom Network Remarks</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['backroom_network_remark'] ?? 'N/A'); ?></p>
                </div>
            </div>
            <?php if (!empty($feasibility['backroom_network_snap'])): ?>
            <div class="mt-4">
                <label class="block text-xs text-gray-500 mb-2">Network Snapshot</label>
                <?php echo renderImageThumbnail($feasibility['backroom_network_snap'], 'Network Snapshot'); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Power Infrastructure Section -->
    <div class="bg-white rounded-xl shadow-sm mb-6" id="section-power_infrastructure">
        <div class="p-4 border-b bg-orange-50">
            <h4 class="font-semibold text-orange-800"><i class="fas fa-bolt mr-2"></i>Power Infrastructure</h4>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">UPS Available</label>
                    <p class="font-medium text-gray-800"><?php echo ucfirst($feasibility['ups_available'] ?? 'N/A'); ?></p>
                </div>
                <?php if (($feasibility['ups_available'] ?? '') === 'yes'): ?>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Number of UPS</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['no_of_ups'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">UPS Battery Backup</label>
                    <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $feasibility['ups_battery_backup'] ?? 'N/A')); ?></p>
                </div>
                <?php if (($feasibility['no_of_ups'] ?? 0) >= 1): ?>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">UPS 1 Working</label>
                    <p class="font-medium text-gray-800"><?php echo ucfirst($feasibility['ups_working_1'] ?? 'N/A'); ?></p>
                </div>
                <?php endif; ?>
                <?php if (($feasibility['no_of_ups'] ?? 0) >= 2): ?>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">UPS 2 Working</label>
                    <p class="font-medium text-gray-800"><?php echo ucfirst($feasibility['ups_working_2'] ?? 'N/A'); ?></p>
                </div>
                <?php endif; ?>
                <?php if (($feasibility['no_of_ups'] ?? 0) >= 3): ?>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">UPS 3 Working</label>
                    <p class="font-medium text-gray-800"><?php echo ucfirst($feasibility['ups_working_3'] ?? 'N/A'); ?></p>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Power Socket Availability</label>
                    <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $feasibility['power_socket_availability'] ?? 'N/A')); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Power Socket for UPS</label>
                    <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $feasibility['power_socket_availability_ups'] ?? 'N/A')); ?></p>
                </div>
            </div>
            <?php 
            $powerImages = [];
            if (!empty($feasibility['ups_available_snap'])) $powerImages[] = ['path' => $feasibility['ups_available_snap'], 'label' => 'UPS Available'];
            if (!empty($feasibility['no_of_ups_snap'])) $powerImages[] = ['path' => $feasibility['no_of_ups_snap'], 'label' => 'Number of UPS'];
            if (!empty($feasibility['ups_working_snap'])) $powerImages[] = ['path' => $feasibility['ups_working_snap'], 'label' => 'UPS Working'];
            if (!empty($feasibility['power_socket_availability_snap'])) $powerImages[] = ['path' => $feasibility['power_socket_availability_snap'], 'label' => 'Power Socket'];
            if (!empty($powerImages)): ?>
            <div class="mt-4">
                <label class="block text-xs text-gray-500 mb-2">Power Infrastructure Images</label>
                <?php echo renderImageThumbnailGrid($powerImages); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Electrical Measurements Section -->
    <div class="bg-white rounded-xl shadow-sm mb-6" id="section-electrical_measurements">
        <div class="p-4 border-b bg-purple-50">
            <h4 class="font-semibold text-purple-800"><i class="fas fa-plug mr-2"></i>Electrical Measurements</h4>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Earthing</label>
                    <p class="font-medium text-gray-800"><?php echo ucfirst($feasibility['earthing'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Earthing Voltage</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['earthing_voltage'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Power Fluctuation (E-N)</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['power_fluctuation_en'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Power Fluctuation (P-E)</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['power_fluctuation_pe'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Power Fluctuation (P-N)</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['power_fluctuation_pn'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Frequent Power Cut</label>
                    <p class="font-medium text-gray-800"><?php echo ucfirst($feasibility['frequent_power_cut'] ?? 'N/A'); ?></p>
                </div>
                <?php if (($feasibility['frequent_power_cut'] ?? '') === 'yes'): ?>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Power Cut From</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['frequent_power_cut_from'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Power Cut To</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['frequent_power_cut_to'] ?? 'N/A'); ?></p>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs text-gray-500 mb-1">Power Cut Remarks</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['frequent_power_cut_remark'] ?? 'N/A'); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php 
            $electricalImages = [];
            if (!empty($feasibility['earthing_snap'])) $electricalImages[] = ['path' => $feasibility['earthing_snap'], 'label' => 'Earthing'];
            if (!empty($feasibility['power_fluctuation_snap'])) $electricalImages[] = ['path' => $feasibility['power_fluctuation_snap'], 'label' => 'Power Fluctuation'];
            if (!empty($electricalImages)): ?>
            <div class="mt-4">
                <label class="block text-xs text-gray-500 mb-2">Electrical Measurement Images</label>
                <?php echo renderImageThumbnailGrid($electricalImages); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Site Access Section -->
    <div class="bg-white rounded-xl shadow-sm mb-6" id="section-site_access">
        <div class="p-4 border-b bg-cyan-50">
            <h4 class="font-semibold text-cyan-800"><i class="fas fa-key mr-2"></i>Site Access</h4>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">EM Lock Available</label>
                    <p class="font-medium text-gray-800"><?php echo ucfirst($feasibility['em_lock_available'] ?? 'N/A'); ?></p>
                </div>
                <?php if (($feasibility['em_lock_available'] ?? '') === 'yes'): ?>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">EM Lock Password</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['em_lock_password'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Password Received</label>
                    <p class="font-medium text-gray-800"><?php echo ucfirst($feasibility['password_received'] ?? 'N/A'); ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Backroom Key Name</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['backroom_key_name'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Backroom Key Number</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['backroom_key_number'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Backroom Key Status</label>
                    <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $feasibility['backroom_key_status'] ?? 'N/A')); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Environmental Factors Section -->
    <div class="bg-white rounded-xl shadow-sm mb-6" id="section-environmental_factors">
        <div class="p-4 border-b bg-teal-50">
            <h4 class="font-semibold text-teal-800"><i class="fas fa-leaf mr-2"></i>Environmental Factors</h4>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Router Position</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['router_position'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Router/Antenna Position</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['router_antenna_position'] ?? 'N/A'); ?></p>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs text-gray-500 mb-1">Antenna Routing Detail</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['antenna_routing_detail'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Nearest Shop Name</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['nearest_shop_name'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Nearest Shop Number</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['nearest_shop_number'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Nearest Shop Distance</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['nearest_shop_distance'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Backroom Disturbing Material</label>
                    <p class="font-medium text-gray-800"><?php echo ucfirst($feasibility['backroom_disturbing_material'] ?? 'N/A'); ?></p>
                </div>
                <?php if (($feasibility['backroom_disturbing_material'] ?? '') === 'yes'): ?>
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Disturbing Material Remarks</label>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['backroom_disturbing_material_remark'] ?? 'N/A'); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php 
            $envImages = [];
            if (!empty($feasibility['router_antenna_snap'])) $envImages[] = ['path' => $feasibility['router_antenna_snap'], 'label' => 'Router/Antenna'];
            if (!empty($feasibility['antenna_routing_snap'])) $envImages[] = ['path' => $feasibility['antenna_routing_snap'], 'label' => 'Antenna Routing'];
            if (!empty($envImages)): ?>
            <div class="mt-4">
                <label class="block text-xs text-gray-500 mb-2">Environmental Images</label>
                <?php echo renderImageThumbnailGrid($envImages); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Remarks Section -->
    <div class="bg-white rounded-xl shadow-sm mb-6" id="section-remarks">
        <div class="p-4 border-b bg-gray-50">
            <h4 class="font-semibold text-gray-800"><i class="fas fa-comment-alt mr-2"></i>Remarks</h4>
        </div>
        <div class="p-6">
            <div>
                <label class="block text-xs text-gray-500 mb-1">General Remarks</label>
                <p class="font-medium text-gray-800"><?php echo nl2br(htmlspecialchars($feasibility['remarks'] ?? 'No remarks provided')); ?></p>
            </div>
            <?php if (!empty($feasibility['remarks_snap'])): ?>
            <div class="mt-4">
                <label class="block text-xs text-gray-500 mb-2">Remarks Image</label>
                <?php echo renderImageThumbnail($feasibility['remarks_snap'], 'Remarks Image'); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Review History Section -->
    <?php if (!empty($reviewHistory)): ?>
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-4 border-b bg-indigo-50">
            <h4 class="font-semibold text-indigo-800"><i class="fas fa-history mr-2"></i>Review History</h4>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <?php foreach ($reviewHistory as $review): ?>
                <div class="border rounded-lg p-4 <?php echo $review['review_type'] === 'approval' ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'; ?>">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <span class="px-2 py-1 rounded text-xs font-medium <?php echo $review['review_type'] === 'approval' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                <?php echo ucfirst($review['review_type']); ?>
                            </span>
                            <span class="ml-2 text-sm text-gray-600">by <?php echo htmlspecialchars($review['reviewer_name'] ?? 'Unknown'); ?></span>
                            <span class="ml-2 text-xs text-gray-400">(<?php echo ucwords(str_replace('_', ' ', $review['reviewer_role'] ?? '')); ?>)</span>
                        </div>
                        <span class="text-xs text-gray-500"><?php echo date('M d, Y H:i', strtotime($review['reviewed_at'])); ?></span>
                    </div>
                    <?php if ($review['review_type'] === 'rejection'): ?>
                    <div class="mt-2">
                        <p class="text-sm text-gray-700"><strong>Type:</strong> <?php echo ucwords(str_replace('_', ' ', $review['rejection_type'] ?? 'overall')); ?></p>
                        <?php if ($review['rejection_type'] === 'section_specific' && !empty($review['rejected_sections'])): ?>
                        <p class="text-sm text-gray-700"><strong>Sections:</strong> 
                            <?php 
                            $sections = is_string($review['rejected_sections']) ? json_decode($review['rejected_sections'], true) : $review['rejected_sections'];
                            echo implode(', ', array_map(function($s) use ($sectionLabels) { return $sectionLabels[$s] ?? ucwords(str_replace('_', ' ', $s)); }, $sections ?? []));
                            ?>
                        </p>
                        <?php endif; ?>
                        <p class="text-sm text-gray-700 mt-1"><strong>Reason:</strong> <?php echo htmlspecialchars($review['reason'] ?? 'N/A'); ?></p>
                    </div>
                    <?php elseif (!empty($review['comments'])): ?>
                    <p class="text-sm text-gray-700 mt-2"><strong>Comments:</strong> <?php echo htmlspecialchars($review['comments']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Review Panel (Requirement 10.1) -->
    <?php if (!$isAlreadyReviewed || $approvalStatus === 'contractor_rejected'): ?>
    <div class="bg-white rounded-xl shadow-sm mb-6" id="review-panel">
        <div class="p-4 border-b bg-blue-50">
            <h4 class="font-semibold text-blue-800"><i class="fas fa-clipboard-check mr-2"></i>Review Panel</h4>
        </div>
        <div class="p-6">
            <form id="review-form">
                <input type="hidden" name="feasibility_id" value="<?php echo $feasibilityId; ?>">
                
                <!-- Review Type Selection -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Review Decision</label>
                    <div class="flex gap-4">
                        <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-green-50 transition review-option" data-type="approval">
                            <input type="radio" name="review_type" value="approval" class="w-4 h-4 text-green-600 mr-3">
                            <div>
                                <span class="font-medium text-green-700"><i class="fas fa-check-circle mr-2"></i>Approve</span>
                                <p class="text-xs text-gray-500">Approve this feasibility check for ADV review</p>
                            </div>
                        </label>
                        <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-red-50 transition review-option" data-type="rejection">
                            <input type="radio" name="review_type" value="rejection" class="w-4 h-4 text-red-600 mr-3">
                            <div>
                                <span class="font-medium text-red-700"><i class="fas fa-times-circle mr-2"></i>Reject</span>
                                <p class="text-xs text-gray-500">Reject and send back to engineer for corrections</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Approval Comments (shown when approval is selected) -->
                <div id="approval-section" class="hidden mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Comments (Optional)</label>
                    <textarea name="comments" rows="3" 
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        placeholder="Add any comments for this approval..."></textarea>
                </div>

                <!-- Rejection Section (shown when rejection is selected) -->
                <div id="rejection-section" class="hidden">
                    <!-- Rejection Type (Requirement 10.3) -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Type <span class="text-red-500">*</span></label>
                        <div class="flex gap-4">
                            <label class="flex items-center">
                                <input type="radio" name="rejection_type" value="overall" class="w-4 h-4 text-red-600 mr-2" checked>
                                <span class="text-sm">Overall Rejection</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="rejection_type" value="section_specific" class="w-4 h-4 text-red-600 mr-2">
                                <span class="text-sm">Section-Specific Rejection</span>
                            </label>
                        </div>
                    </div>

                    <!-- Section Selection (Requirement 10.4) -->
                    <div id="section-selection" class="hidden mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Sections to Reject <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <?php foreach ($sectionLabels as $sectionKey => $sectionLabel): ?>
                            <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-red-50 transition section-checkbox">
                                <input type="checkbox" name="rejected_sections[]" value="<?php echo $sectionKey; ?>" class="w-4 h-4 text-red-600 mr-2">
                                <span class="text-sm"><?php echo $sectionLabel; ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Select one or more sections that need corrections</p>
                    </div>

                    <!-- Rejection Reason (Requirement 10.5) -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Rejection Reason <span class="text-red-500">*</span>
                            <span class="text-xs text-gray-400 ml-2">(minimum 10 characters)</span>
                        </label>
                        <textarea name="reason" id="rejection-reason" rows="4" 
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Please provide a detailed reason for rejection..."
                            minlength="10"></textarea>
                        <div class="flex justify-between mt-1">
                            <p class="text-xs text-gray-500">Explain what needs to be corrected</p>
                            <p class="text-xs text-gray-500"><span id="char-count">0</span> / 10 minimum</p>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <a href="pending_reviews.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </a>
                    <button type="submit" id="submit-btn" disabled
                        class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-paper-plane mr-2"></i>Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <!-- Already Reviewed Message -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-6 text-center">
            <i class="fas fa-check-circle text-4xl text-green-500 mb-3"></i>
            <h4 class="text-lg font-semibold text-gray-800">Review Completed</h4>
            <p class="text-gray-500">This feasibility check has already been reviewed.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Lightbox Modal -->
<?php echo renderLightboxModal(); ?>
<?php echo getLightboxScript(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reviewForm = document.getElementById('review-form');
    const approvalSection = document.getElementById('approval-section');
    const rejectionSection = document.getElementById('rejection-section');
    const sectionSelection = document.getElementById('section-selection');
    const submitBtn = document.getElementById('submit-btn');
    const charCount = document.getElementById('char-count');
    const rejectionReason = document.getElementById('rejection-reason');
    
    // Review type selection
    document.querySelectorAll('input[name="review_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const isApproval = this.value === 'approval';
            
            // Update visual selection
            document.querySelectorAll('.review-option').forEach(opt => {
                opt.classList.remove('border-green-500', 'border-red-500', 'bg-green-50', 'bg-red-50');
            });
            
            if (isApproval) {
                this.closest('.review-option').classList.add('border-green-500', 'bg-green-50');
                approvalSection.classList.remove('hidden');
                rejectionSection.classList.add('hidden');
            } else {
                this.closest('.review-option').classList.add('border-red-500', 'bg-red-50');
                approvalSection.classList.add('hidden');
                rejectionSection.classList.remove('hidden');
            }
            
            validateForm();
        });
    });
    
    // Rejection type selection
    document.querySelectorAll('input[name="rejection_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'section_specific') {
                sectionSelection.classList.remove('hidden');
            } else {
                sectionSelection.classList.add('hidden');
                // Uncheck all sections
                document.querySelectorAll('input[name="rejected_sections[]"]').forEach(cb => cb.checked = false);
            }
            validateForm();
        });
    });
    
    // Section checkbox styling
    document.querySelectorAll('.section-checkbox input').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const label = this.closest('.section-checkbox');
            if (this.checked) {
                label.classList.add('border-red-500', 'bg-red-50');
            } else {
                label.classList.remove('border-red-500', 'bg-red-50');
            }
            validateForm();
        });
    });
    
    // Character count for rejection reason
    if (rejectionReason) {
        rejectionReason.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = count;
            
            if (count >= 10) {
                charCount.classList.remove('text-red-500');
                charCount.classList.add('text-green-500');
            } else {
                charCount.classList.remove('text-green-500');
                charCount.classList.add('text-red-500');
            }
            
            validateForm();
        });
    }
    
    // Form validation
    function validateForm() {
        const reviewType = document.querySelector('input[name="review_type"]:checked');
        
        if (!reviewType) {
            submitBtn.disabled = true;
            return;
        }
        
        if (reviewType.value === 'approval') {
            submitBtn.disabled = false;
            return;
        }
        
        // Rejection validation
        const rejectionType = document.querySelector('input[name="rejection_type"]:checked');
        const reason = rejectionReason ? rejectionReason.value.trim() : '';
        
        if (!rejectionType || reason.length < 10) {
            submitBtn.disabled = true;
            return;
        }
        
        if (rejectionType.value === 'section_specific') {
            const selectedSections = document.querySelectorAll('input[name="rejected_sections[]"]:checked');
            if (selectedSections.length === 0) {
                submitBtn.disabled = true;
                return;
            }
        }
        
        submitBtn.disabled = false;
    }

    // Form submission
    if (reviewForm) {
        reviewForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const reviewType = document.querySelector('input[name="review_type"]:checked');
            if (!reviewType) {
                showToast('Please select a review decision', 'error');
                return;
            }
            
            const formData = {
                feasibility_id: <?php echo $feasibilityId; ?>,
                review_type: reviewType.value
            };
            
            if (reviewType.value === 'approval') {
                const comments = document.querySelector('textarea[name="comments"]');
                if (comments && comments.value.trim()) {
                    formData.comments = comments.value.trim();
                }
            } else {
                const rejectionType = document.querySelector('input[name="rejection_type"]:checked');
                formData.rejection_type = rejectionType ? rejectionType.value : 'overall';
                formData.reason = rejectionReason.value.trim();
                
                if (formData.rejection_type === 'section_specific') {
                    const selectedSections = [];
                    document.querySelectorAll('input[name="rejected_sections[]"]:checked').forEach(cb => {
                        selectedSections.push(cb.value);
                    });
                    formData.rejected_sections = selectedSections;
                }
            }
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
            
            try {
                const response = await fetch('../api/feasibility/review.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message || 'Review submitted successfully', 'success');
                    setTimeout(() => {
                        window.location.href = 'pending_reviews.php';
                    }, 1500);
                } else {
                    showToast(result.message || 'Failed to submit review', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Submit Review';
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('An error occurred while submitting the review', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Submit Review';
            }
        });
    }
});

// Toast notification function
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
    toast.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-300`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/main.php';
?>

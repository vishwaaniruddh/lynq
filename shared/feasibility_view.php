<?php
/**
 * Shared Feasibility View Page
 * 
 * Displays feasibility check details in read-only mode for admin, contractor, and ADV users.
 * Allows editing for ADV users and contractor admin/manager roles.
 * 
 * Requirements: View feasibility data with role-based edit permissions
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/FeasibilityService.php';
require_once __DIR__ . '/../services/FeasibilityReviewService.php';
require_once __DIR__ . '/../services/InstallationService.php';
require_once __DIR__ . '/../views/components/image_thumbnail.php';
require_once __DIR__ . '/../views/components/lightbox.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$companyType = strtoupper($currentUser['company_type'] ?? '');
$roleName = strtolower($currentUser['role_name'] ?? '');

// Determine user type and permissions
$isADV = $companyType === 'ADV';
$isContractor = $companyType === 'CONTRACTOR';
$isContractorAdmin = $isContractor && in_array($roleName, ['contractor_admin', 'contractor admin']);
$isContractorManager = $isContractor && in_array($roleName, ['contractor_manager', 'contractor manager']);

// Check access - only ADV and Contractor users can access
if (!$isADV && !$isContractor) {
    $_SESSION['flash_error'] = 'Access denied.';
    header('Location: ../dashboard.php');
    exit;
}

// Determine if user can edit
$canEdit = $isADV || $isContractorAdmin || $isContractorManager;

// Get feasibility ID or assignment ID from query parameter
$feasibilityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
$editMode = isset($_GET['edit']) && $_GET['edit'] == '1' && $canEdit;

// Get feasibility service
$feasibilityService = new FeasibilityService();
$reviewService = new FeasibilityReviewService();

// Get feasibility check details
$feasibility = null;
if ($feasibilityId > 0) {
    $feasibility = $feasibilityService->getFeasibilityCheck($feasibilityId);
} elseif ($assignmentId > 0) {
    $feasibility = $feasibilityService->getFeasibilityByAssignment($assignmentId);
    if ($feasibility) {
        $feasibilityId = $feasibility['id'];
    }
}

if (!$feasibility) {
    $_SESSION['flash_error'] = 'Feasibility check not found.';
    header('Location: ' . ($isADV ? '../admin/feasibility_tracking.php' : '../contractor/feasibility_tracking.php'));
    exit;
}

// For contractors, verify they have access to this feasibility
if ($isContractor) {
    $contractorId = $currentUser['company_id'] ?? 0;
    if (($feasibility['contractor_id'] ?? 0) != $contractorId) {
        $_SESSION['flash_error'] = 'Access denied. You do not have permission to view this feasibility check.';
        header('Location: ../contractor/feasibility_tracking.php');
        exit;
    }
}

// Get review history
$reviewHistory = $reviewService->getReviewHistory($feasibilityId);

// Check for existing installation (Requirements 1.1, 1.5)
$installationService = new InstallationService();
$existingInstallation = null;
$canInitiateInstallation = false;
$siteId = $feasibility['site_id'] ?? 0;

if ($siteId > 0) {
    $existingInstallation = $installationService->getInstallationBySite($siteId);
}

// ADV users can initiate installation when feasibility is ADV-approved and no installation exists
$approvalStatusCheck = $feasibility['approval_status'] ?? '';
if ($isADV && $approvalStatusCheck === 'adv_approved' && !$existingInstallation) {
    $canInitiateInstallation = true;
}

// Determine back URL based on user type
$backUrl = $isADV ? '../admin/feasibility_tracking.php' : '../contractor/feasibility_tracking.php';

$baseUrl = '..';
$pageTitle = $editMode ? 'Edit Feasibility Check' : 'View Feasibility Check';
$currentPage = $isADV ? 'admin_feasibility_tracking' : 'contractor_feasibility_tracking';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'Feasibility Tracking', 'url' => $backUrl],
    ['label' => $pageTitle]
];

ob_start();

// Include thumbnail and lightbox styles
echo getImageThumbnailStyles();
echo getLightboxStyles();
?>

<style>
.field-editable {
    background-color: #fefce8 !important;
    border-color: #fbbf24 !important;
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
</style>

<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800"><?php echo $pageTitle; ?></h3>
                <p class="text-sm text-gray-500">
                    <?php echo htmlspecialchars($feasibility['site_name'] ?? 'Unknown Site'); ?>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <?php
                $approvalStatus = $feasibility['approval_status'] ?? 'pending_contractor_review';
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
                
                <?php 
                // Show "Initiate Installation" button for ADV users when feasibility is ADV-approved and no installation exists
                // Requirements: 1.1, 1.5
                if ($canInitiateInstallation): 
                ?>
                <button type="button" id="initiate-installation-btn"
                   class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition"
                   onclick="initiateInstallation(<?php echo $siteId; ?>, <?php echo $feasibilityId; ?>)">
                    <i class="fas fa-tools mr-2"></i>Initiate Installation
                </button>
                <?php endif; ?>
                
                <?php 
                // Show installation status if installation exists
                // Requirements: 1.2, 1.3
                if ($existingInstallation): 
                    $installationStatusColors = [
                        'pending_materials' => 'bg-yellow-100 text-yellow-700',
                        'materials_received' => 'bg-blue-100 text-blue-700',
                        'in_progress' => 'bg-purple-100 text-purple-700',
                        'submitted' => 'bg-indigo-100 text-indigo-700',
                        'pending_contractor_review' => 'bg-orange-100 text-orange-700',
                        'contractor_approved' => 'bg-teal-100 text-teal-700',
                        'contractor_rejected' => 'bg-red-100 text-red-700',
                        'adv_approved' => 'bg-green-100 text-green-700',
                        'adv_rejected' => 'bg-red-100 text-red-700'
                    ];
                    $installationStatusLabels = [
                        'pending_materials' => 'Installation: Pending Materials',
                        'materials_received' => 'Installation: Materials Received',
                        'in_progress' => 'Installation: In Progress',
                        'submitted' => 'Installation: Submitted',
                        'pending_contractor_review' => 'Installation: Pending Review',
                        'contractor_approved' => 'Installation: Contractor Approved',
                        'contractor_rejected' => 'Installation: Contractor Rejected',
                        'adv_approved' => 'Installation: ADV Approved',
                        'adv_rejected' => 'Installation: ADV Rejected'
                    ];
                    $instStatus = $existingInstallation['status'] ?? 'pending_materials';
                    $instStatusColor = $installationStatusColors[$instStatus] ?? 'bg-gray-100 text-gray-700';
                    $instStatusLabel = $installationStatusLabels[$instStatus] ?? 'Installation: ' . ucwords(str_replace('_', ' ', $instStatus));
                ?>
                <a href="../installation/view.php?id=<?php echo $existingInstallation['id']; ?>" 
                   class="px-3 py-1 rounded-full text-sm font-medium <?php echo $instStatusColor; ?> hover:opacity-80 transition"
                   title="View Installation">
                    <i class="fas fa-tools mr-1"></i><?php echo $instStatusLabel; ?>
                </a>
                <?php endif; ?>
                
                <?php if ($canEdit && !$editMode): ?>
                <a href="?id=<?php echo $feasibilityId; ?>&edit=1" 
                   class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition">
                    <i class="fas fa-edit mr-2"></i>Edit
                </a>
                <?php endif; ?>
                
                <a href="<?php echo $backUrl; ?>" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
            </div>
        </div>
    </div>

    <?php if ($editMode): ?>
    <!-- Edit Form -->
    <form id="edit-feasibility-form" enctype="multipart/form-data">
        <input type="hidden" name="feasibility_id" value="<?php echo $feasibilityId; ?>">
    <?php endif; ?>

    <!-- Site Information (Always Read-only) -->
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
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-4 border-b bg-yellow-50">
            <h4 class="font-semibold text-yellow-800"><i class="fas fa-credit-card mr-2"></i>ATM Information</h4>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Number of ATMs</label>
                    <?php if ($editMode): ?>
                    <select name="no_of_atm" class="w-full px-3 py-2 border rounded-lg field-editable" onchange="toggleATMFields()">
                        <option value="0" <?php echo ($feasibility['no_of_atm'] ?? '') == '0' ? 'selected' : ''; ?>>0</option>
                        <option value="1" <?php echo ($feasibility['no_of_atm'] ?? '') == '1' ? 'selected' : ''; ?>>1</option>
                        <option value="2" <?php echo ($feasibility['no_of_atm'] ?? '') == '2' ? 'selected' : ''; ?>>2</option>
                        <option value="3" <?php echo ($feasibility['no_of_atm'] ?? '') == '3' ? 'selected' : ''; ?>>3</option>
                    </select>
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['no_of_atm'] ?? '0'); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php for ($i = 1; $i <= 3; $i++): ?>
                <?php if (($feasibility['no_of_atm'] ?? 0) >= $i || $editMode): ?>
                <div id="atm-<?php echo $i; ?>-id" class="<?php echo ($feasibility['no_of_atm'] ?? 0) < $i ? 'hidden' : ''; ?>">
                    <label class="block text-xs text-gray-500 mb-1">ATM <?php echo $i; ?> ID</label>
                    <?php if ($editMode): ?>
                    <input type="text" name="atm_id_<?php echo $i; ?>" value="<?php echo htmlspecialchars($feasibility["atm_id_{$i}"] ?? ''); ?>" 
                           class="w-full px-3 py-2 border rounded-lg field-editable">
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility["atm_id_{$i}"] ?? 'N/A'); ?></p>
                    <?php endif; ?>
                </div>
                <div id="atm-<?php echo $i; ?>-status" class="<?php echo ($feasibility['no_of_atm'] ?? 0) < $i ? 'hidden' : ''; ?>">
                    <label class="block text-xs text-gray-500 mb-1">ATM <?php echo $i; ?> Status</label>
                    <?php if ($editMode): ?>
                    <select name="atm_<?php echo $i; ?>_status" class="w-full px-3 py-2 border rounded-lg field-editable">
                        <option value="">Select</option>
                        <option value="working" <?php echo ($feasibility["atm_{$i}_status"] ?? '') == 'working' ? 'selected' : ''; ?>>Working</option>
                        <option value="not_working" <?php echo ($feasibility["atm_{$i}_status"] ?? '') == 'not_working' ? 'selected' : ''; ?>>Not Working</option>
                        <option value="maintenance" <?php echo ($feasibility["atm_{$i}_status"] ?? '') == 'maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                    </select>
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $feasibility["atm_{$i}_status"] ?? 'N/A')); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Network Information Section -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-4 border-b bg-green-50">
            <h4 class="font-semibold text-green-800"><i class="fas fa-wifi mr-2"></i>Network Information</h4>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Primary Operator</label>
                    <?php if ($editMode): ?>
                    <input type="text" name="operator" value="<?php echo htmlspecialchars($feasibility['operator'] ?? ''); ?>" 
                           class="w-full px-3 py-2 border rounded-lg field-editable">
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['operator'] ?? 'N/A'); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Signal Status</label>
                    <?php if ($editMode): ?>
                    <select name="signal_status" class="w-full px-3 py-2 border rounded-lg field-editable">
                        <option value="">Select</option>
                        <option value="excellent" <?php echo ($feasibility['signal_status'] ?? '') == 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                        <option value="good" <?php echo ($feasibility['signal_status'] ?? '') == 'good' ? 'selected' : ''; ?>>Good</option>
                        <option value="average" <?php echo ($feasibility['signal_status'] ?? '') == 'average' ? 'selected' : ''; ?>>Average</option>
                        <option value="poor" <?php echo ($feasibility['signal_status'] ?? '') == 'poor' ? 'selected' : ''; ?>>Poor</option>
                    </select>
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $feasibility['signal_status'] ?? 'N/A')); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Secondary Operator</label>
                    <?php if ($editMode): ?>
                    <input type="text" name="operator_2" value="<?php echo htmlspecialchars($feasibility['operator_2'] ?? ''); ?>" 
                           class="w-full px-3 py-2 border rounded-lg field-editable">
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['operator_2'] ?? 'N/A'); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Secondary Signal Status</label>
                    <?php if ($editMode): ?>
                    <select name="signal_status_2" class="w-full px-3 py-2 border rounded-lg field-editable">
                        <option value="">Select</option>
                        <option value="excellent" <?php echo ($feasibility['signal_status_2'] ?? '') == 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                        <option value="good" <?php echo ($feasibility['signal_status_2'] ?? '') == 'good' ? 'selected' : ''; ?>>Good</option>
                        <option value="average" <?php echo ($feasibility['signal_status_2'] ?? '') == 'average' ? 'selected' : ''; ?>>Average</option>
                        <option value="poor" <?php echo ($feasibility['signal_status_2'] ?? '') == 'poor' ? 'selected' : ''; ?>>Poor</option>
                    </select>
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $feasibility['signal_status_2'] ?? 'N/A')); ?></p>
                    <?php endif; ?>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Backroom Network Remarks</label>
                    <?php if ($editMode): ?>
                    <textarea name="backroom_network_remark" rows="2" class="w-full px-3 py-2 border rounded-lg field-editable"><?php echo htmlspecialchars($feasibility['backroom_network_remark'] ?? ''); ?></textarea>
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['backroom_network_remark'] ?? 'N/A'); ?></p>
                    <?php endif; ?>
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
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-4 border-b bg-orange-50">
            <h4 class="font-semibold text-orange-800"><i class="fas fa-bolt mr-2"></i>Power Infrastructure</h4>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">UPS Available</label>
                    <?php if ($editMode): ?>
                    <select name="ups_available" class="w-full px-3 py-2 border rounded-lg field-editable" onchange="toggleUPSFields()">
                        <option value="">Select</option>
                        <option value="yes" <?php echo ($feasibility['ups_available'] ?? '') == 'yes' ? 'selected' : ''; ?>>Yes</option>
                        <option value="no" <?php echo ($feasibility['ups_available'] ?? '') == 'no' ? 'selected' : ''; ?>>No</option>
                    </select>
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo ucfirst($feasibility['ups_available'] ?? 'N/A'); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if (($feasibility['ups_available'] ?? '') === 'yes' || $editMode): ?>
                <div id="ups-count-field" class="<?php echo ($feasibility['ups_available'] ?? '') !== 'yes' ? 'hidden' : ''; ?>">
                    <label class="block text-xs text-gray-500 mb-1">Number of UPS</label>
                    <?php if ($editMode): ?>
                    <select name="no_of_ups" class="w-full px-3 py-2 border rounded-lg field-editable">
                        <option value="">Select</option>
                        <option value="1" <?php echo ($feasibility['no_of_ups'] ?? '') == '1' ? 'selected' : ''; ?>>1</option>
                        <option value="2" <?php echo ($feasibility['no_of_ups'] ?? '') == '2' ? 'selected' : ''; ?>>2</option>
                        <option value="3" <?php echo ($feasibility['no_of_ups'] ?? '') == '3' ? 'selected' : ''; ?>>3</option>
                    </select>
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['no_of_ups'] ?? 'N/A'); ?></p>
                    <?php endif; ?>
                </div>
                <div id="ups-backup-field" class="<?php echo ($feasibility['ups_available'] ?? '') !== 'yes' ? 'hidden' : ''; ?>">
                    <label class="block text-xs text-gray-500 mb-1">UPS Battery Backup</label>
                    <?php if ($editMode): ?>
                    <select name="ups_battery_backup" class="w-full px-3 py-2 border rounded-lg field-editable">
                        <option value="">Select</option>
                        <option value="less_than_30min" <?php echo ($feasibility['ups_battery_backup'] ?? '') == 'less_than_30min' ? 'selected' : ''; ?>>Less than 30 min</option>
                        <option value="30min_to_1hr" <?php echo ($feasibility['ups_battery_backup'] ?? '') == '30min_to_1hr' ? 'selected' : ''; ?>>30 min to 1 hr</option>
                        <option value="more_than_1hr" <?php echo ($feasibility['ups_battery_backup'] ?? '') == 'more_than_1hr' ? 'selected' : ''; ?>>More than 1 hr</option>
                    </select>
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $feasibility['ups_battery_backup'] ?? 'N/A')); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Power Socket Availability</label>
                    <?php if ($editMode): ?>
                    <select name="power_socket_availability" class="w-full px-3 py-2 border rounded-lg field-editable">
                        <option value="">Select</option>
                        <option value="available" <?php echo ($feasibility['power_socket_availability'] ?? '') == 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="not_available" <?php echo ($feasibility['power_socket_availability'] ?? '') == 'not_available' ? 'selected' : ''; ?>>Not Available</option>
                    </select>
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo ucwords(str_replace('_', ' ', $feasibility['power_socket_availability'] ?? 'N/A')); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Electrical Measurements Section -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-4 border-b bg-purple-50">
            <h4 class="font-semibold text-purple-800"><i class="fas fa-plug mr-2"></i>Electrical Measurements</h4>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Earthing</label>
                    <?php if ($editMode): ?>
                    <select name="earthing" class="w-full px-3 py-2 border rounded-lg field-editable">
                        <option value="">Select</option>
                        <option value="yes" <?php echo ($feasibility['earthing'] ?? '') == 'yes' ? 'selected' : ''; ?>>Yes</option>
                        <option value="no" <?php echo ($feasibility['earthing'] ?? '') == 'no' ? 'selected' : ''; ?>>No</option>
                    </select>
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo ucfirst($feasibility['earthing'] ?? 'N/A'); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Earthing Voltage</label>
                    <?php if ($editMode): ?>
                    <input type="text" name="earthing_voltage" value="<?php echo htmlspecialchars($feasibility['earthing_voltage'] ?? ''); ?>" 
                           class="w-full px-3 py-2 border rounded-lg field-editable">
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['earthing_voltage'] ?? 'N/A'); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Power Fluctuation (E-N)</label>
                    <?php if ($editMode): ?>
                    <input type="text" name="power_fluctuation_en" value="<?php echo htmlspecialchars($feasibility['power_fluctuation_en'] ?? ''); ?>" 
                           class="w-full px-3 py-2 border rounded-lg field-editable">
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['power_fluctuation_en'] ?? 'N/A'); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Power Fluctuation (P-E)</label>
                    <?php if ($editMode): ?>
                    <input type="text" name="power_fluctuation_pe" value="<?php echo htmlspecialchars($feasibility['power_fluctuation_pe'] ?? ''); ?>" 
                           class="w-full px-3 py-2 border rounded-lg field-editable">
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['power_fluctuation_pe'] ?? 'N/A'); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Power Fluctuation (P-N)</label>
                    <?php if ($editMode): ?>
                    <input type="text" name="power_fluctuation_pn" value="<?php echo htmlspecialchars($feasibility['power_fluctuation_pn'] ?? ''); ?>" 
                           class="w-full px-3 py-2 border rounded-lg field-editable">
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feasibility['power_fluctuation_pn'] ?? 'N/A'); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Frequent Power Cut</label>
                    <?php if ($editMode): ?>
                    <select name="frequent_power_cut" class="w-full px-3 py-2 border rounded-lg field-editable">
                        <option value="">Select</option>
                        <option value="yes" <?php echo ($feasibility['frequent_power_cut'] ?? '') == 'yes' ? 'selected' : ''; ?>>Yes</option>
                        <option value="no" <?php echo ($feasibility['frequent_power_cut'] ?? '') == 'no' ? 'selected' : ''; ?>>No</option>
                    </select>
                    <?php else: ?>
                    <p class="font-medium text-gray-800"><?php echo ucfirst($feasibility['frequent_power_cut'] ?? 'N/A'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Remarks Section -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-4 border-b bg-gray-50">
            <h4 class="font-semibold text-gray-800"><i class="fas fa-comment-alt mr-2"></i>Remarks</h4>
        </div>
        <div class="p-6">
            <div>
                <label class="block text-xs text-gray-500 mb-1">General Remarks</label>
                <?php if ($editMode): ?>
                <textarea name="remarks" rows="4" class="w-full px-3 py-2 border rounded-lg field-editable" maxlength="2000"><?php echo htmlspecialchars($feasibility['remarks'] ?? ''); ?></textarea>
                <?php else: ?>
                <p class="font-medium text-gray-800"><?php echo nl2br(htmlspecialchars($feasibility['remarks'] ?? 'No remarks provided')); ?></p>
                <?php endif; ?>
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
            <h4 class="font-semibold text-indigo-800"><i class="fas fa-history mr-2"></i>Review History (<?php echo count($reviewHistory); ?> records)</h4>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <?php foreach ($reviewHistory as $review): 
                    $isRejection = $review['review_type'] === 'rejection';
                ?>
                <div class="review-history-item <?php echo $isRejection ? 'rejection' : 'approval'; ?>">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $isRejection ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                            <?php echo $isRejection ? 'Rejected' : 'Approved'; ?>
                        </span>
                        <span class="text-sm text-gray-600">by <?php echo htmlspecialchars($review['reviewer_name'] ?? 'Unknown'); ?></span>
                        <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded"><?php echo ucwords(str_replace('_', ' ', $review['reviewer_role'] ?? '')); ?></span>
                    </div>
                    <div class="text-xs text-gray-500"><?php echo date('M d, Y h:i A', strtotime($review['reviewed_at'])); ?></div>
                    
                    <?php if ($isRejection && !empty($review['reason'])): ?>
                    <div class="text-sm text-gray-600 mt-2 bg-red-50 p-2 rounded">
                        <strong>Reason:</strong> <?php echo htmlspecialchars($review['reason']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!$isRejection && !empty($review['comments'])): ?>
                    <div class="text-sm text-gray-600 mt-2 bg-green-50 p-2 rounded">
                        <?php echo htmlspecialchars($review['comments']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($editMode): ?>
    <!-- Submit Button -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-6 flex justify-end gap-3">
            <a href="?id=<?php echo $feasibilityId; ?>" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                <i class="fas fa-save mr-2"></i>Save Changes
            </button>
        </div>
    </div>
    </form>
    <?php endif; ?>
</div>

<!-- Lightbox Modal -->
<?php echo renderLightboxModal(); ?>
<?php echo getLightboxScript(); ?>

<?php if ($editMode): ?>
<script>
const API_URL = '../api/feasibility/update.php';
const feasibilityId = <?php echo $feasibilityId; ?>;

document.getElementById('edit-feasibility-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const jsonData = {};
    formData.forEach((value, key) => {
        jsonData[key] = value;
    });
    
    try {
        const response = await fetch(`${API_URL}?id=${feasibilityId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(jsonData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Feasibility check updated successfully', 'success');
            setTimeout(() => {
                window.location.href = `?id=${feasibilityId}`;
            }, 1500);
        } else {
            showToast(data.error?.message || 'Failed to update', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to update feasibility check', 'error');
    }
});

function toggleATMFields() {
    const count = parseInt(document.querySelector('[name="no_of_atm"]').value) || 0;
    for (let i = 1; i <= 3; i++) {
        const idField = document.getElementById(`atm-${i}-id`);
        const statusField = document.getElementById(`atm-${i}-status`);
        if (idField) idField.classList.toggle('hidden', i > count);
        if (statusField) statusField.classList.toggle('hidden', i > count);
    }
}

function toggleUPSFields() {
    const available = document.querySelector('[name="ups_available"]').value === 'yes';
    document.getElementById('ups-count-field')?.classList.toggle('hidden', !available);
    document.getElementById('ups-backup-field')?.classList.toggle('hidden', !available);
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'error' ? 'bg-red-500' : type === 'success' ? 'bg-green-500' : 'bg-blue-500';
    toast.className = `fixed bottom-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>
<?php endif; ?>

<!-- Installation Initiation Script (Requirements: 1.1, 1.2, 1.3) -->
<?php if ($canInitiateInstallation): ?>
<script>
async function initiateInstallation(siteId, feasibilityId) {
    if (!confirm('Are you sure you want to initiate installation for this site?')) {
        return;
    }
    
    const btn = document.getElementById('initiate-installation-btn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Initiating...';
    
    try {
        const response = await fetch('../api/installation/initiate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                site_id: siteId,
                feasibility_id: feasibilityId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showInstallationToast('Installation initiated successfully! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = '../installation/view.php?id=' + result.data.id;
            }, 1500);
        } else {
            showInstallationToast(result.message || 'Failed to initiate installation', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Error:', error);
        showInstallationToast('An error occurred while initiating installation', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function showInstallationToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'error' ? 'bg-red-500' : type === 'success' ? 'bg-green-500' : 'bg-blue-500';
    toast.className = `fixed bottom-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

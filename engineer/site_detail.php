<?php
/**
 * Engineer Site Detail View
 * 
 * Displays complete site information
 * Shows assignment history
 * 
 * Requirements: 6.2
 * - Display complete site information including address, coordinates, and assignment history
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/InstallationService.php';

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

// Get assignment ID from query string
$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($assignmentId <= 0) {
    $_SESSION['flash_error'] = 'Invalid assignment ID.';
    header('Location: sites.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();

// Verify engineer has access to this assignment
$siteAccessService = new SiteAccessService();
$accessResult = $siteAccessService->validateEngineerAssignmentAccess($currentUser['id'], $assignmentId);
if (!$accessResult['success']) {
    $_SESSION['flash_error'] = 'Access denied. This assignment is not assigned to you.';
    header('Location: sites.php');
    exit;
}

// Get assignment details
$assignmentService = new EngineerAssignmentService();
$assignment = $assignmentService->getAssignment($assignmentId);

if (!$assignment) {
    $_SESSION['flash_error'] = 'Assignment not found.';
    header('Location: sites.php');
    exit;
}

// Get assignment history for this site
$history = $assignmentService->getAssignmentHistory($assignment['site_id']);

// Check for existing installation (Requirements: 1.2, 1.3)
$installationService = new InstallationService();
$existingInstallation = null;
$siteId = $assignment['site_id'] ?? 0;

if ($siteId > 0) {
    $existingInstallation = $installationService->getInstallationBySite($siteId);
}

$baseUrl = '..';
$pageTitle = 'Site Details - ' . ($assignment['site_name'] ?? 'Unknown');
$currentPage = 'engineer_sites';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'My Assigned Sites', 'url' => 'sites.php'],
    ['label' => $assignment['site_name'] ?? 'Site Details']
];

ob_start();
?>

<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-map-marker-alt text-blue-500 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($assignment['site_name'] ?? 'N/A'); ?></h3>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($assignment['lho'] ?? ''); ?></p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <?php
                $statusColors = [
                    'assigned' => 'bg-yellow-100 text-yellow-700',
                    'in_progress' => 'bg-purple-100 text-purple-700',
                    'completed' => 'bg-green-100 text-green-700'
                ];
                $statusIcons = [
                    'assigned' => 'fa-clock',
                    'in_progress' => 'fa-spinner',
                    'completed' => 'fa-check-circle'
                ];
                $statusClass = $statusColors[$assignment['status']] ?? 'bg-gray-100 text-gray-700';
                $statusIcon = $statusIcons[$assignment['status']] ?? 'fa-question';
                ?>
                <span class="px-3 py-1 <?php echo $statusClass; ?> rounded-full text-sm font-medium">
                    <i class="fas <?php echo $statusIcon; ?> mr-1"></i>
                    <?php echo ucfirst(str_replace('_', ' ', $assignment['status'])); ?>
                </span>
                
                <?php 
                // Show installation status if installation exists (Requirements: 1.2, 1.3)
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
                        'pending_materials' => 'Pending Materials',
                        'materials_received' => 'Materials Received',
                        'in_progress' => 'In Progress',
                        'submitted' => 'Submitted',
                        'pending_contractor_review' => 'Pending Review',
                        'contractor_approved' => 'Contractor Approved',
                        'contractor_rejected' => 'Contractor Rejected',
                        'adv_approved' => 'ADV Approved',
                        'adv_rejected' => 'ADV Rejected'
                    ];
                    $instStatus = $existingInstallation['status'] ?? 'pending_materials';
                    $instStatusColor = $installationStatusColors[$instStatus] ?? 'bg-gray-100 text-gray-700';
                    $instStatusLabel = $installationStatusLabels[$instStatus] ?? ucwords(str_replace('_', ' ', $instStatus));
                ?>
                <a href="../installation/form.php?id=<?php echo $existingInstallation['id']; ?>" 
                   class="px-3 py-1 rounded-full text-sm font-medium <?php echo $instStatusColor; ?> hover:opacity-80 transition"
                   title="View/Edit Installation">
                    <i class="fas fa-tools mr-1"></i>Installation: <?php echo $instStatusLabel; ?>
                </a>
                <?php endif; ?>
                
                <a href="sites.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Site Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Info -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4 border-b">
                    <h4 class="font-semibold text-gray-800"><i class="fas fa-info-circle mr-2 text-blue-500"></i>Site Information</h4>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wide">Site Name</label>
                            <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($assignment['site_name'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wide">LHO</label>
                            <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($assignment['lho'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wide">Bank Name</label>
                            <p class="text-gray-800"><?php echo htmlspecialchars($assignment['bank_name'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wide">Customer Name</label>
                            <p class="text-gray-800"><?php echo htmlspecialchars($assignment['customer_name'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wide">Zone</label>
                            <p class="text-gray-800"><?php echo htmlspecialchars($assignment['zone'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wide">Contractor</label>
                            <p class="text-gray-800"><?php echo htmlspecialchars($assignment['contractor_name'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location Info -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4 border-b">
                    <h4 class="font-semibold text-gray-800"><i class="fas fa-map mr-2 text-green-500"></i>Location Details</h4>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wide">City</label>
                            <p class="text-gray-800"><?php echo htmlspecialchars($assignment['city'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wide">State</label>
                            <p class="text-gray-800"><?php echo htmlspecialchars($assignment['state'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wide">Country</label>
                            <p class="text-gray-800"><?php echo htmlspecialchars($assignment['country'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-wide">Coordinates</label>
                            <?php if ($assignment['latitude'] && $assignment['longitude']): ?>
                                <p class="text-gray-800">
                                    <?php echo htmlspecialchars($assignment['latitude']); ?>, <?php echo htmlspecialchars($assignment['longitude']); ?>
                                    <a href="https://www.google.com/maps?q=<?php echo $assignment['latitude']; ?>,<?php echo $assignment['longitude']; ?>" 
                                       target="_blank" class="ml-2 text-blue-500 hover:text-blue-700">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </p>
                            <?php else: ?>
                                <p class="text-gray-400">Not available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-wide">Full Address</label>
                        <p class="text-gray-800"><?php echo htmlspecialchars($assignment['address'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <?php if ($assignment['latitude'] && $assignment['longitude']): ?>
                    <div class="mt-4">
                        <a href="https://www.google.com/maps?q=<?php echo $assignment['latitude']; ?>,<?php echo $assignment['longitude']; ?>" 
                           target="_blank" 
                           class="inline-flex items-center px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition">
                            <i class="fas fa-map-marked-alt mr-2"></i>Open in Google Maps
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assignment History -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4 border-b">
                    <h4 class="font-semibold text-gray-800"><i class="fas fa-history mr-2 text-purple-500"></i>Assignment History</h4>
                </div>
                <div class="p-6">
                    <?php if (empty($history)): ?>
                        <p class="text-gray-500 text-center py-4">No assignment history available</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($history as $index => $record): ?>
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 w-8 h-8 bg-<?php echo $record['id'] == $assignmentId ? 'blue' : 'gray'; ?>-100 rounded-full flex items-center justify-center mr-4">
                                        <i class="fas fa-user text-<?php echo $record['id'] == $assignmentId ? 'blue' : 'gray'; ?>-500 text-sm"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <p class="font-medium text-gray-800">
                                                <?php echo htmlspecialchars($record['engineer_name'] ?? 'Unknown'); ?>
                                                <?php if ($record['id'] == $assignmentId): ?>
                                                    <span class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">Current</span>
                                                <?php endif; ?>
                                            </p>
                                            <?php
                                            $historyStatusClass = $statusColors[$record['status']] ?? 'bg-gray-100 text-gray-700';
                                            ?>
                                            <span class="px-2 py-0.5 <?php echo $historyStatusClass; ?> rounded text-xs">
                                                <?php echo ucfirst(str_replace('_', ' ', $record['status'])); ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-500 mt-1">
                                            Assigned by <?php echo htmlspecialchars($record['assigned_by_name'] ?? 'Unknown'); ?>
                                            on <?php echo date('M d, Y H:i', strtotime($record['assigned_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php if ($index < count($history) - 1): ?>
                                    <div class="ml-4 border-l-2 border-gray-200 h-4"></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Assignment Info -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4 border-b">
                    <h4 class="font-semibold text-gray-800"><i class="fas fa-clipboard-list mr-2 text-yellow-500"></i>Assignment Details</h4>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-wide">Assignment ID</label>
                        <p class="text-gray-800 font-medium">#<?php echo $assignment['id']; ?></p>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-wide">Assigned At</label>
                        <p class="text-gray-800"><?php echo date('M d, Y H:i', strtotime($assignment['assigned_at'])); ?></p>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-wide">Assigned By</label>
                        <p class="text-gray-800"><?php echo htmlspecialchars($assignment['assigned_by_name'] ?? 'N/A'); ?></p>
                    </div>
                    
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4 border-b">
                    <h4 class="font-semibold text-gray-800"><i class="fas fa-bolt mr-2 text-orange-500"></i>Quick Actions</h4>
                </div>
                <div class="p-4 space-y-2">
                    <?php 
                    // Show installation link if installation exists (Requirements: 1.2, 1.3)
                    if ($existingInstallation): 
                        $instStatus = $existingInstallation['status'] ?? 'pending_materials';
                        // Determine the appropriate link based on installation status
                        $installationLink = '../installation/form.php?id=' . $existingInstallation['id'];
                        $installationBtnText = 'Continue Installation';
                        $installationBtnClass = 'bg-purple-500 hover:bg-purple-600';
                        
                        if ($instStatus === 'pending_materials') {
                            $installationBtnText = 'Confirm Materials Received';
                            $installationBtnClass = 'bg-yellow-500 hover:bg-yellow-600';
                        } elseif (in_array($instStatus, ['submitted', 'pending_contractor_review', 'contractor_approved', 'adv_approved'])) {
                            $installationLink = '../installation/view.php?id=' . $existingInstallation['id'];
                            $installationBtnText = 'View Installation';
                            $installationBtnClass = 'bg-indigo-500 hover:bg-indigo-600';
                        } elseif (in_array($instStatus, ['contractor_rejected', 'adv_rejected'])) {
                            $installationBtnText = 'Fix Rejected Sections';
                            $installationBtnClass = 'bg-red-500 hover:bg-red-600';
                        }
                    ?>
                        <a href="<?php echo $installationLink; ?>" 
                           class="block w-full px-4 py-2 <?php echo $installationBtnClass; ?> text-white rounded-lg transition text-left">
                            <i class="fas fa-tools mr-2"></i><?php echo $installationBtnText; ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($assignment['latitude'] && $assignment['longitude']): ?>
                        <a href="https://www.google.com/maps?q=<?php echo $assignment['latitude']; ?>,<?php echo $assignment['longitude']; ?>" 
                           target="_blank" 
                           class="block w-full px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-left">
                            <i class="fas fa-map-marked-alt mr-2"></i>Open in Maps
                        </a>
                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $assignment['latitude']; ?>,<?php echo $assignment['longitude']; ?>" 
                           target="_blank" 
                           class="block w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition text-left">
                            <i class="fas fa-directions mr-2"></i>Get Directions
                        </a>
                    <?php endif; ?>
                    <a href="sites.php" class="block w-full px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-left">
                        <i class="fas fa-arrow-left mr-2"></i>Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'error' ? 'bg-red-500' : type === 'success' ? 'bg-green-500' : type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500';
    toast.className = `fixed bottom-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

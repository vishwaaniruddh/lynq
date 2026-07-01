<?php
/**
 * Contractor Site Assignment Page
 * 
 * Allows contractor admins to assign accepted sites to engineers
 * Supports single and bulk assignment
 * 
 * Requirements: 5.1
 * - Create engineer assignment records for selected sites
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../services/EngineerAssignmentService.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check contractor access - only contractor users can access this page
if (!isContractorUser()) {
    $_SESSION['flash_error'] = 'Access denied. Contractor users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Assign Sites to Engineers';
$currentPage = 'contractor_assign';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Delegated Sites', 'url' => 'delegations.php'],
    ['label' => 'Assign to Engineer']
];

// Get services
$delegationService = new DelegationService();
$assignmentService = new EngineerAssignmentService();
$userRepository = new UserRepository();

// Get accepted delegations for this contractor
$acceptedDelegations = $delegationService->getAcceptedDelegations($currentUser['company_id']);

// Get engineers for this contractor company
$engineers = $userRepository->findByCompanyWithRelations($currentUser['company_id']);

// Pre-select delegation if passed via URL
$preSelectedDelegationId = isset($_GET['delegation_id']) ? (int)$_GET['delegation_id'] : null;

// Handle form submission
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteIds = isset($_POST['site_ids']) ? array_map('intval', $_POST['site_ids']) : [];
    $engineerId = isset($_POST['engineer_id']) ? (int)$_POST['engineer_id'] : 0;
    
    if (empty($siteIds)) {
        $result = ['success' => false, 'message' => 'Please select at least one site'];
    } elseif ($engineerId <= 0) {
        $result = ['success' => false, 'message' => 'Please select an engineer'];
    } else {
        $result = $assignmentService->bulkAssignToEngineer($siteIds, $engineerId, $currentUser['id'], $currentUser['company_id']);
    }
}

ob_start();
?>

<div class="max-w-6xl mx-auto">
    <?php if ($result): ?>
    <!-- Show toast notification for result -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($result['success']): ?>
            showToast('<?php echo addslashes($result['message']); ?><?php if (isset($result['successCount'])): ?> Successfully assigned <?php echo $result['successCount']; ?> site(s).<?php endif; ?>', 'success', 5000);
            <?php else: ?>
            showToast('<?php echo addslashes($result['message']); ?>', 'error', 5000);
            <?php endif; ?>
        });
    </script>
    <?php endif; ?>
    
    <div class="bg-white rounded-xl shadow-sm">
        <!-- Header -->
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Assign Sites to Engineer</h3>
                <p class="text-sm text-gray-500">Select accepted sites and assign them to an engineer for feasibility assessment</p>
            </div>
            <a href="delegations.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Delegations
            </a>
        </div>

        <form method="POST" id="assignment-form" class="p-6">
            <!-- Engineer Selection -->
            <div class="mb-6">
                <label for="engineer_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Select Engineer <span class="text-red-500">*</span>
                </label>
                <select id="engineer_id" name="engineer_id" required
                    class="w-full md:w-1/2 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">-- Select Engineer --</option>
                    <?php foreach ($engineers as $engineer): ?>
                    <option value="<?php echo $engineer['id']; ?>">
                        <?php echo htmlspecialchars($engineer['first_name'] . ' ' . $engineer['last_name'] . ' (' . $engineer['email'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($engineers)): ?>
                <p class="mt-2 text-sm text-yellow-600">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    No engineers available. Please add engineers to your company first.
                </p>
                <?php endif; ?>
            </div>
            
            <!-- Site Selection -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">
                        Select Sites <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center space-x-4">
                        <span id="selected-count" class="text-sm text-gray-500">0 sites selected</span>
                        <button type="button" onclick="selectAll()" class="text-sm text-primary hover:underline">Select All</button>
                        <button type="button" onclick="deselectAll()" class="text-sm text-gray-500 hover:underline">Deselect All</button>
                    </div>
                </div>
                
                <!-- Search Filter -->
                <div class="mb-4">
                    <input type="text" id="site-search" placeholder="Search sites by name, LHO, city..."
                        class="w-full md:w-1/2 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                
                <?php 
                // Filter out sites that already have active assignments
                $availableSites = array_filter($acceptedDelegations, function($delegation) use ($assignmentService) {
                    return !$assignmentService->hasActiveAssignment($delegation['site_id']);
                });
                ?>
                
                <?php if (empty($availableSites)): ?>
                <div class="bg-gray-50 rounded-lg p-8 text-center">
                    <i class="fas fa-check-circle text-4xl text-green-500 mb-3"></i>
                    <p class="text-gray-600">All accepted sites have been assigned to engineers.</p>
                    <a href="delegations.php" class="mt-4 inline-block text-primary hover:underline">View delegations</a>
                </div>
                <?php else: ?>
                <!-- Sites Table -->
                <div class="border rounded-lg overflow-hidden max-h-96 overflow-y-auto">
                    <table class="w-full" id="sites-table">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-4 py-3 text-left w-12">
                                    <input type="checkbox" id="select-all-checkbox" onchange="toggleSelectAll(this)">
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Site Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">LHO</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Location</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Bank</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($availableSites as $delegation): ?>
                            <tr class="hover:bg-gray-50 site-row" data-search="<?php echo strtolower(htmlspecialchars($delegation['site_name'] . ' ' . $delegation['lho'] . ' ' . $delegation['city'] . ' ' . ($delegation['bank_name'] ?? ''))); ?>">
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="site_ids[]" value="<?php echo $delegation['site_id']; ?>" 
                                        class="site-checkbox" onchange="updateSelectedCount()"
                                        <?php echo $preSelectedDelegationId === $delegation['id'] ? 'checked' : ''; ?>>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-map-marker-alt text-blue-500"></i>
                                        </div>
                                        <span class="font-medium text-gray-800"><?php echo htmlspecialchars($delegation['site_name']); ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs"><?php echo htmlspecialchars($delegation['lho']); ?></span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <?php echo htmlspecialchars($delegation['city'] . ', ' . $delegation['state']); ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <?php echo htmlspecialchars($delegation['bank_name'] ?? '-'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    Showing <?php echo count($availableSites); ?> accepted site(s) available for assignment
                </p>
                <?php endif; ?>
            </div>
            
            <!-- Form Actions -->
            <?php if (!empty($availableSites) && !empty($engineers)): ?>
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <a href="delegations.php" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </a>
                <button type="submit" id="assign-btn" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition" disabled>
                    <i class="fas fa-user-plus mr-2"></i>Assign Selected Sites
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

<!-- Confirmation Modal -->
<div id="confirm-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeConfirmModal()"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-2xl w-full max-w-md p-6 animate-modal-in">
        <div class="text-center">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-user-plus text-2xl text-blue-600"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Confirm Assignment</h3>
            <p id="confirm-message" class="text-gray-600 mb-6"></p>
            <div class="flex space-x-3 justify-center">
                <button onclick="closeConfirmModal()" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button onclick="submitAssignment()" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-check mr-2"></i>Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes modal-in {
    from { opacity: 0; transform: translate(-50%, -50%) scale(0.95); }
    to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
}
.animate-modal-in { animation: modal-in 0.2s ease-out; }

@keyframes toast-in {
    from { opacity: 0; transform: translateX(100%); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes toast-out {
    from { opacity: 1; transform: translateX(0); }
    to { opacity: 0; transform: translateX(100%); }
}
.toast-enter { animation: toast-in 0.3s ease-out; }
.toast-exit { animation: toast-out 0.3s ease-in forwards; }
</style>

<script>
// Toast notification system
function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    toast.className = `toast-enter flex items-center p-4 rounded-lg shadow-lg text-white ${colors[type]} min-w-[300px] max-w-md`;
    toast.innerHTML = `
        <i class="fas ${icons[type]} mr-3 text-lg"></i>
        <span class="flex-1">${message}</span>
        <button onclick="removeToast(this.parentElement)" class="ml-3 hover:opacity-75 transition">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(toast);
    
    // Auto remove after duration
    setTimeout(() => {
        removeToast(toast);
    }, duration);
}

function removeToast(toast) {
    if (!toast || toast.classList.contains('toast-exit')) return;
    toast.classList.remove('toast-enter');
    toast.classList.add('toast-exit');
    setTimeout(() => toast.remove(), 300);
}

// Confirmation modal
let pendingSubmit = false;

function showConfirmModal(message) {
    document.getElementById('confirm-message').textContent = message;
    document.getElementById('confirm-modal').classList.remove('hidden');
}

function closeConfirmModal() {
    document.getElementById('confirm-modal').classList.add('hidden');
}

function submitAssignment() {
    closeConfirmModal();
    pendingSubmit = true;
    
    const btn = document.getElementById('assign-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Assigning...';
    
    document.getElementById('assignment-form').submit();
}

// Update selected count on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
});

// Search filter
document.getElementById('site-search').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    document.querySelectorAll('.site-row').forEach(row => {
        const searchData = row.dataset.search;
        row.style.display = searchData.includes(search) ? '' : 'none';
    });
});

// Update selected count
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.site-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selected-count').textContent = count + ' site(s) selected';
    document.getElementById('assign-btn').disabled = count === 0;
    
    // Update select all checkbox state
    const allCheckboxes = document.querySelectorAll('.site-checkbox');
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = count > 0 && count === allCheckboxes.length;
        selectAllCheckbox.indeterminate = count > 0 && count < allCheckboxes.length;
    }
}

// Toggle select all
function toggleSelectAll(checkbox) {
    document.querySelectorAll('.site-checkbox').forEach(cb => {
        if (cb.closest('.site-row').style.display !== 'none') {
            cb.checked = checkbox.checked;
        }
    });
    updateSelectedCount();
}

// Select all visible
function selectAll() {
    document.querySelectorAll('.site-checkbox').forEach(cb => {
        if (cb.closest('.site-row').style.display !== 'none') {
            cb.checked = true;
        }
    });
    updateSelectedCount();
}

// Deselect all
function deselectAll() {
    document.querySelectorAll('.site-checkbox').forEach(cb => {
        cb.checked = false;
    });
    updateSelectedCount();
}

// Form submission with toast confirmation
document.getElementById('assignment-form').addEventListener('submit', function(e) {
    if (pendingSubmit) return; // Allow submission after confirmation
    
    e.preventDefault();
    
    const selectedCount = document.querySelectorAll('.site-checkbox:checked').length;
    const engineer = document.getElementById('engineer_id');
    
    if (selectedCount === 0) {
        showToast('Please select at least one site to assign', 'warning');
        return;
    }
    
    if (!engineer.value) {
        showToast('Please select an engineer', 'warning');
        return;
    }
    
    const engineerName = engineer.options[engineer.selectedIndex].text;
    showConfirmModal(`Are you sure you want to assign ${selectedCount} site(s) to ${engineerName}?`);
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

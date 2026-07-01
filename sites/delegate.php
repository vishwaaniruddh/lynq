<?php
/**
 * Site Delegation Page
 * 
 * Allows ADV users to delegate sites to contractors
 * Supports single and multi-select site delegation
 * 
 * Requirements: 2.1
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check ADV access
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'Access denied. ADV users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Delegate Sites';
$currentPage = 'sites';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites', 'url' => 'index.php'],
    ['label' => 'Delegate']
];

// Get services
$siteService = new SiteService();
$delegationService = new DelegationService();
$companyRepository = new CompanyRepository();

// Get undelegated sites
$undelegatedSites = $siteService->getUndelegatedSites($currentUser['company_id']);

// Get total sites count to determine appropriate message
$siteCounts = $siteService->getSiteCountsByStatus($currentUser['company_id']);
$totalSitesCount = ($siteCounts['active'] ?? 0) + ($siteCounts['inactive'] ?? 0) + ($siteCounts['pending'] ?? 0);

// Get contractors
$contractors = $companyRepository->findContractors();

// Pre-select site if passed via URL
$preSelectedSiteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : null;

// Handle form submission
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteIds = isset($_POST['site_ids']) ? array_map('intval', $_POST['site_ids']) : [];
    $contractorId = isset($_POST['contractor_id']) ? (int)$_POST['contractor_id'] : 0;
    
    if (empty($siteIds)) {
        $result = ['success' => false, 'message' => 'Please select at least one site'];
    } elseif ($contractorId <= 0) {
        $result = ['success' => false, 'message' => 'Please select a contractor'];
    } else {
        $result = $delegationService->bulkDelegateSites($siteIds, $contractorId, $currentUser['id']);
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
            showToast('<?php echo addslashes($result['message']); ?><?php if (isset($result['successCount'])): ?> Successfully delegated <?php echo $result['successCount']; ?> site(s).<?php endif; ?>', 'success', 5000);
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
                <h3 class="text-lg font-semibold text-gray-800">Delegate Sites to Contractor</h3>
                <p class="text-sm text-gray-500">Select sites and assign them to a contractor for service</p>
            </div>
            <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Sites
            </a>
        </div>

        <form method="POST" id="delegation-form" class="p-6">
            <!-- Contractor Selection -->
            <div class="mb-6">
                <label for="contractor_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Select Contractor <span class="text-red-500">*</span>
                </label>
                <select id="contractor_id" name="contractor_id" required
                    class="w-full md:w-1/2 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">-- Select Contractor --</option>
                    <?php foreach ($contractors as $contractor): ?>
                    <option value="<?php echo $contractor['id']; ?>">
                        <?php echo htmlspecialchars($contractor['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($contractors)): ?>
                <p class="mt-2 text-sm text-yellow-600">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    No contractors available. Please add contractors first.
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
                
                <?php if (empty($undelegatedSites)): ?>
                <div class="bg-gray-50 rounded-lg p-8 text-center">
                    <?php if ($totalSitesCount === 0): ?>
                    <!-- No sites exist at all -->
                    <i class="fas fa-map-marker-alt text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-600">No sites found in the system.</p>
                    <p class="text-sm text-gray-500 mt-2">Please add sites first before delegating.</p>
                    <a href="add.php" class="mt-4 inline-block px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-plus mr-2"></i>Add Site
                    </a>
                    <?php else: ?>
                    <!-- All sites have been delegated -->
                    <i class="fas fa-check-circle text-4xl text-green-500 mb-3"></i>
                    <p class="text-gray-600">All sites have been delegated.</p>
                    <a href="index.php" class="mt-4 inline-block text-primary hover:underline">View all sites</a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <!-- Sites Table -->
                <div class="border rounded-lg overflow-hidden max-h-96 overflow-y-auto">
                    <table class="w-full" id="sites-table">
                        <thead class="bg-gray-50/80 sticky top-0">
                            <tr>
                                <th class="px-4 py-2.5 text-left w-12">
                                    <input type="checkbox" id="select-all-checkbox" onchange="toggleSelectAll(this)" class="w-3.5 h-3.5 text-primary border-gray-300 rounded focus:ring-primary">
                                </th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Site Name</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">LHO</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Bank</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($undelegatedSites as $site): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors site-row" data-search="<?php echo strtolower(htmlspecialchars($site['site_name'] . ' ' . $site['lho'] . ' ' . $site['city'] . ' ' . ($site['bank_name'] ?? ''))); ?>">
                                <td class="px-4 py-2.5">
                                    <input type="checkbox" name="site_ids[]" value="<?php echo $site['id']; ?>" 
                                        class="site-checkbox w-3.5 h-3.5 text-primary border-gray-300 rounded focus:ring-primary" onchange="updateSelectedCount()"
                                        <?php echo $preSelectedSiteId === $site['id'] ? 'checked' : ''; ?>>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center">
                                        <div class="w-7 h-7 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg flex items-center justify-center mr-2.5 flex-shrink-0">
                                            <i class="fas fa-map-marker-alt text-blue-500 text-xs"></i>
                                        </div>
                                        <span class="font-medium text-gray-800 text-xs"><?php echo htmlspecialchars($site['site_name']); ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="px-2 py-0.5 bg-purple-50 text-purple-600 rounded text-[10px] font-medium"><?php echo htmlspecialchars($site['lho']); ?></span>
                                </td>
                                <td class="px-4 py-2.5 text-xs text-gray-600">
                                    <?php echo htmlspecialchars($site['city'] . ', ' . $site['state']); ?>
                                </td>
                                <td class="px-4 py-2.5 text-xs text-gray-600">
                                    <?php echo htmlspecialchars($site['bank_name'] ?? '-'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    Showing <?php echo count($undelegatedSites); ?> undelegated site(s)
                </p>
                <?php endif; ?>
            </div>
            
            <!-- Form Actions -->
            <?php if (!empty($undelegatedSites) && !empty($contractors)): ?>
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <a href="index.php" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </a>
                <button type="submit" id="delegate-btn" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition" disabled>
                    <i class="fas fa-share-alt mr-2"></i>Delegate Selected Sites
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
                <i class="fas fa-share-alt text-2xl text-blue-600"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Confirm Delegation</h3>
            <p id="confirm-message" class="text-gray-600 mb-6"></p>
            <div class="flex space-x-3 justify-center">
                <button onclick="closeConfirmModal()" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button onclick="submitDelegation()" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
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

function submitDelegation() {
    closeConfirmModal();
    pendingSubmit = true;
    
    const btn = document.getElementById('delegate-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Delegating...';
    
    document.getElementById('delegation-form').submit();
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
    document.getElementById('delegate-btn').disabled = count === 0;
    
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
document.getElementById('delegation-form').addEventListener('submit', function(e) {
    if (pendingSubmit) return; // Allow submission after confirmation
    
    e.preventDefault();
    
    const selectedCount = document.querySelectorAll('.site-checkbox:checked').length;
    const contractor = document.getElementById('contractor_id');
    
    if (selectedCount === 0) {
        showToast('Please select at least one site to delegate', 'warning');
        return;
    }
    
    if (!contractor.value) {
        showToast('Please select a contractor', 'warning');
        return;
    }
    
    const contractorName = contractor.options[contractor.selectedIndex].text;
    showConfirmModal(`Are you sure you want to delegate ${selectedCount} site(s) to ${contractorName}?`);
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

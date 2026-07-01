<?php
/**
 * Installation Delegation Page
 * 
 * Allows ADV users to delegate installation to a contractor
 * Displays site information and contractor selection dropdown
 * 
 * Requirements: 1.2, 1.3
 * - 1.2: Redirect to installation delegation page when clicking "Initiate Installation"
 * - 1.3: Display form to select contractor for installation
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check ADV access - only ADV users can delegate installations
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'Access denied. Only ADV users can delegate installations.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();

// Get site_id and feasibility_id from query params
$siteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : 0;
$feasibilityId = isset($_GET['feasibility_id']) ? (int)$_GET['feasibility_id'] : 0;

if (!$siteId) {
    $_SESSION['flash_error'] = 'Site ID is required.';
    header('Location: ../sites/index.php');
    exit;
}

// Get site details
$siteRepository = new SiteRepository();
$siteRepository->setCurrentUser($currentUser['id']);
$site = $siteRepository->findById($siteId);

if (!$site) {
    $_SESSION['flash_error'] = 'Site not found.';
    header('Location: ../sites/index.php');
    exit;
}

// Get feasibility check if not provided
if (!$feasibilityId) {
    $feasibilityRepository = new FeasibilityCheckRepository();
    $feasibilityChecks = $feasibilityRepository->findBySite($siteId);
    // Get the latest ADV-approved feasibility check
    foreach ($feasibilityChecks as $fc) {
        if (isset($fc['approval_status']) && $fc['approval_status'] === 'adv_approved') {
            $feasibilityId = $fc['id'];
            break;
        }
    }
    // If no ADV-approved, get the latest one
    if (!$feasibilityId && !empty($feasibilityChecks)) {
        $feasibilityId = $feasibilityChecks[0]['id'];
    }
}

// Validate delegation is allowed
$delegationService = new InstallationDelegationService();
$canDelegate = $delegationService->canDelegate($siteId, $feasibilityId);

$baseUrl = '..';
$pageTitle = 'Delegate Installation';
$currentPage = 'installation';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites', 'url' => '../sites/index.php'],
    ['label' => 'Delegate Installation']
];

ob_start();
?>

<div class="max-w-3xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <h2 class="text-2xl font-semibold text-gray-800">Delegate Installation</h2>
        <p class="text-gray-500 mt-1">Assign this site to a contractor for installation work</p>
    </div>

    <?php if (!$canDelegate['canDelegate']): ?>
    <!-- Error State -->
    <div class="bg-red-50 border border-red-200 rounded-xl p-6">
        <div class="flex items-start">
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-red-800">Cannot Delegate Installation</h3>
                <p class="text-red-600 mt-1"><?php echo htmlspecialchars($canDelegate['reason']); ?></p>
                <a href="../sites/index.php" class="inline-flex items-center mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Sites
                </a>
            </div>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Site Information Card -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-5 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800">Site Information</h3>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Site Name</p>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($site['site_name']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">LHO</p>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($site['lho']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">City</p>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($site['city']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">State</p>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($site['state']); ?></p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm text-gray-500">Address</p>
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($site['address'] ?? '-'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Delegation Form -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-5 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800">Select Contractor</h3>
            <p class="text-sm text-gray-500 mt-1">Choose a contractor to handle the installation for this site</p>
        </div>
        <form id="delegation-form" class="p-5">
            <input type="hidden" id="site-id" value="<?php echo $siteId; ?>">
            <input type="hidden" id="feasibility-id" value="<?php echo $feasibilityId; ?>">
            
            <div class="mb-6">
                <label for="contractor-select" class="block text-sm font-medium text-gray-700 mb-2">
                    Contractor <span class="text-red-500">*</span>
                </label>
                <select id="contractor-select" name="contractor_id" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">Loading contractors...</option>
                </select>
                <p id="contractor-error" class="mt-1 text-sm text-red-500 hidden"></p>
            </div>

            <!-- Contractor Details Preview -->
            <div id="contractor-details" class="hidden mb-6 p-4 bg-gray-50 rounded-lg">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Contractor Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-gray-500">Name:</span>
                        <span id="contractor-name" class="ml-2 text-gray-800"></span>
                    </div>
                    <div>
                        <span class="text-gray-500">Email:</span>
                        <span id="contractor-email" class="ml-2 text-gray-800"></span>
                    </div>
                    <div>
                        <span class="text-gray-500">Phone:</span>
                        <span id="contractor-phone" class="ml-2 text-gray-800"></span>
                    </div>
                    <div>
                        <span class="text-gray-500">Status:</span>
                        <span id="contractor-status" class="ml-2"></span>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                <a href="../sites/index.php" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Cancel
                </a>
                <button type="submit" id="submit-btn" 
                    class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-paper-plane mr-2"></i>Delegate Installation
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
// State
let contractors = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($canDelegate['canDelegate']): ?>
    loadContractors();
    setupEventListeners();
    <?php endif; ?>
});

// Load available contractors
async function loadContractors() {
    const select = document.getElementById('contractor-select');
    
    try {
        const response = await fetch('../api/installation/contractors.php', {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            contractors = data.data || [];
            
            if (contractors.length === 0) {
                select.innerHTML = '<option value="">No contractors available</option>';
                document.getElementById('submit-btn').disabled = true;
            } else {
                select.innerHTML = '<option value="">Select a contractor...</option>' +
                    contractors.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
            }
        } else {
            select.innerHTML = '<option value="">Failed to load contractors</option>';
            showError(data.message || 'Failed to load contractors');
        }
    } catch (error) {
        console.error('Error loading contractors:', error);
        select.innerHTML = '<option value="">Error loading contractors</option>';
        showError('Failed to load contractors. Please try again.');
    }
}

// Setup event listeners
function setupEventListeners() {
    // Contractor selection change
    document.getElementById('contractor-select').addEventListener('change', function(e) {
        const contractorId = parseInt(e.target.value);
        const detailsDiv = document.getElementById('contractor-details');
        
        if (contractorId) {
            const contractor = contractors.find(c => c.id === contractorId);
            if (contractor) {
                document.getElementById('contractor-name').textContent = contractor.name || '-';
                document.getElementById('contractor-email').textContent = contractor.email || '-';
                document.getElementById('contractor-phone').textContent = contractor.phone || '-';
                document.getElementById('contractor-status').innerHTML = 
                    contractor.status === 'ACTIVE' 
                        ? '<span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Active</span>'
                        : '<span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs">Inactive</span>';
                detailsDiv.classList.remove('hidden');
            }
        } else {
            detailsDiv.classList.add('hidden');
        }
        
        // Clear error
        document.getElementById('contractor-error').classList.add('hidden');
    });
    
    // Form submission
    document.getElementById('delegation-form').addEventListener('submit', handleSubmit);
}

// Handle form submission
async function handleSubmit(e) {
    e.preventDefault();
    
    const siteId = document.getElementById('site-id').value;
    const feasibilityId = document.getElementById('feasibility-id').value;
    const contractorId = document.getElementById('contractor-select').value;
    
    // Validate
    if (!contractorId) {
        document.getElementById('contractor-error').textContent = 'Please select a contractor';
        document.getElementById('contractor-error').classList.remove('hidden');
        return;
    }
    
    const submitBtn = document.getElementById('submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Delegating...';
    
    try {
        const response = await fetch('../api/installation/delegate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                site_id: parseInt(siteId),
                feasibility_id: parseInt(feasibilityId),
                contractor_id: parseInt(contractorId)
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Installation delegated successfully!');
            // Redirect to sites page after short delay
            setTimeout(() => {
                window.location.href = '../sites/index.php';
            }, 1500);
        } else {
            showError(data.message || 'Failed to delegate installation');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Delegate Installation';
        }
    } catch (error) {
        console.error('Error delegating installation:', error);
        showError('Failed to delegate installation. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Delegate Installation';
    }
}

// Show error message
function showError(message) {
    showToast(message, 'error');
}

// Show success message
function showSuccess(message) {
    showToast(message, 'success');
}

// Show toast notification
function showToast(message, type = 'info') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2`;
    toast.innerHTML = `
        <i class="fas ${icons[type]}"></i>
        <span>${escapeHtml(message)}</span>
        <button onclick="this.parentElement.remove()" class="ml-4 hover:opacity-75">
            <i class="fas fa-times"></i>
        </button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 5000);
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

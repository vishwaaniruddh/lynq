<?php
/**
 * Installation Review Page
 * 
 * Display installation data with section-wise approve/reject buttons
 * Show previous review comments and status
 * Include rejection reason input (min 10 chars)
 * Highlight rejected sections with visual indicators
 * 
 * Requirements: 12.1-12.7, 13.1-13.6, 14.1, 14.2
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Installation Review';
$currentPage = 'installation';
$isLoggedIn = true;

// Get installation ID from query string
$installationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$installationId) {
    $_SESSION['flash_error'] = 'Installation ID is required.';
    header('Location: index.php');
    exit;
}

$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Installation', 'url' => 'index.php'],
    ['label' => 'Review']
];

// Determine user's review level
$companyType = strtoupper($currentUser['company_type'] ?? '');
$roleId = $currentUser['role_id'] ?? 0;
$reviewerLevel = 'none';

if ($companyType === 'ADV' || (isset($currentUser['is_system_admin']) && $currentUser['is_system_admin'])) {
    $reviewerLevel = 'adv';
} elseif ($companyType === 'CONTRACTOR' && in_array($roleId, [1, 2, 3])) {
    $reviewerLevel = 'contractor';
}

ob_start();
?>

<div id="review-container">
    <!-- Loading State -->
    <div id="loading-state" class="bg-white rounded-xl shadow-sm p-8 text-center">
        <i class="fas fa-spinner fa-spin text-3xl text-primary mb-4"></i>
        <p class="text-gray-500">Loading installation data...</p>
    </div>
    
    <!-- Review Content (hidden until loaded) -->
    <div id="review-content" class="hidden space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Installation Review</h3>
                    <p class="text-sm text-gray-500">Review and approve/reject installation sections</p>
                </div>
                <div class="flex items-center gap-3">
                    <span id="status-badge" class="px-3 py-1 rounded-full text-sm"></span>
                    <span id="reviewer-level-badge" class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm">
                        <?php echo $reviewerLevel === 'adv' ? 'ADV Reviewer' : 'Contractor Reviewer'; ?>
                    </span>
                    <a href="view.php?id=<?php echo $installationId; ?>" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        <i class="fas fa-eye mr-2"></i>View Details
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Review Summary -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h4 class="font-semibold text-gray-800 mb-4">Review Summary</h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-gray-50 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-gray-800" id="total-sections">0</p>
                    <p class="text-sm text-gray-500">Total Sections</p>
                </div>
                <div class="bg-green-50 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-green-600" id="approved-sections">0</p>
                    <p class="text-sm text-gray-500">Approved</p>
                </div>
                <div class="bg-red-50 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-red-600" id="rejected-sections">0</p>
                    <p class="text-sm text-gray-500">Rejected</p>
                </div>
                <div class="bg-yellow-50 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-yellow-600" id="pending-sections">0</p>
                    <p class="text-sm text-gray-500">Pending</p>
                </div>
            </div>
        </div>
        
        <!-- Site Information (Read-only) -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b bg-gray-50 rounded-t-xl">
                <h4 class="font-semibold text-gray-800"><i class="fas fa-map-marker-alt mr-2 text-primary"></i>Site Information</h4>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">ATM ID</label>
                        <p id="review-atm_id" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">LHO</label>
                        <p id="review-lho" class="text-gray-800">-</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">City / State</label>
                        <p id="review-location" class="text-gray-800">-</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sections for Review -->
        <div id="sections-container" class="space-y-6">
            <!-- Sections will be dynamically rendered here -->
        </div>
        
        <!-- Bulk Actions -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h4 class="font-semibold text-gray-800">Bulk Actions</h4>
                    <p class="text-sm text-gray-500">Approve or reject all pending sections at once</p>
                </div>
                <div class="flex gap-3">
                    <button onclick="approveAllSections()" id="approve-all-btn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-check-double mr-2"></i>Approve All Pending
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div id="rejection-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeRejectionModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Reject Section</h3>
                <p class="text-sm text-gray-500 mt-1" id="rejection-section-name">Section Name</p>
            </div>
            <div class="p-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason <span class="text-red-500">*</span></label>
                <textarea id="rejection-reason" rows="4" 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="Please provide a detailed reason for rejection (minimum 10 characters)"></textarea>
                <p class="text-xs text-gray-400 mt-1">Minimum 10 characters required</p>
                <p id="rejection-error" class="text-sm text-red-500 mt-2 hidden"></p>
            </div>
            <div class="flex justify-end space-x-3 p-6 border-t bg-gray-50 rounded-b-2xl">
                <button onclick="closeRejectionModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button onclick="submitRejection()" id="submit-rejection-btn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-times mr-2"></i>Reject Section
                </button>
            </div>
        </div>
    </div>
</div>


<script>
const installationId = <?php echo $installationId; ?>;
const reviewerLevel = '<?php echo $reviewerLevel; ?>';
const API_BASE = '../api/installation';

// State
const state = {
    installation: null,
    checkpoints: {},
    currentRejectionSection: null
};

// Section definitions
const sections = [
    { id: 'router_fixed_snaps', name: 'Router Fixed', icon: 'fa-wifi' },
    { id: 'router_status_snaps', name: 'Router Status', icon: 'fa-wifi' },
    { id: 'adaptor_snaps', name: 'Adaptor Installed', icon: 'fa-plug' },
    { id: 'adaptor_status_snaps', name: 'Adaptor Status', icon: 'fa-plug' },
    { id: 'lan_cable_install_snap', name: 'LAN Cable Installed', icon: 'fa-ethernet' },
    { id: 'lan_cable_status_snap', name: 'LAN Cable Status', icon: 'fa-ethernet' },
    { id: 'antenna_snaps', name: 'Antenna Installed', icon: 'fa-broadcast-tower' },
    { id: 'antenna_status_snaps', name: 'Antenna Status', icon: 'fa-broadcast-tower' },
    { id: 'gps_snaps', name: 'GPS Installed', icon: 'fa-satellite' },
    { id: 'gps_status_snaps', name: 'GPS Status', icon: 'fa-satellite' },
    { id: 'wifi_snaps', name: 'WiFi Installed', icon: 'fa-wifi' },
    { id: 'wifi_status_snaps', name: 'WiFi Status', icon: 'fa-wifi' },
    { id: 'airtel_sim_snaps', name: 'Airtel SIM Installed', icon: 'fa-sim-card' },
    { id: 'airtel_sim_status_snaps', name: 'Airtel SIM Status', icon: 'fa-sim-card' },
    { id: 'vodafone_sim_snaps', name: 'Vodafone SIM Installed', icon: 'fa-sim-card' },
    { id: 'vodafone_sim_status_snaps', name: 'Vodafone SIM Status', icon: 'fa-sim-card' },
    { id: 'jio_sim_snaps', name: 'JIO SIM Installed', icon: 'fa-sim-card' },
    { id: 'jio_sim_status_snaps', name: 'JIO SIM Status', icon: 'fa-sim-card' },
    { id: 'vendor_stamp', name: 'Verification', icon: 'fa-signature' }
];

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadInstallation();
});

// Load installation data
async function loadInstallation() {
    try {
        const response = await fetch(`${API_BASE}/get.php?id=${installationId}&include_checkpoints=1`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.installation = data.data;
            state.checkpoints = data.data.checkpoints || {};
            populateReview(data.data);
            document.getElementById('loading-state').classList.add('hidden');
            document.getElementById('review-content').classList.remove('hidden');
        } else {
            showError(data.message || 'Failed to load installation');
        }
    } catch (error) {
        console.error('Error loading installation:', error);
        showError('Failed to load installation data');
    }
}

// Populate review page
function populateReview(data) {
    // Update status badge
    const statusBadge = document.getElementById('status-badge');
    statusBadge.textContent = getStatusLabel(data.status);
    statusBadge.className = `px-3 py-1 rounded-full text-sm ${getStatusClass(data.status)}`;
    
    // Site information
    document.getElementById('review-atm_id').textContent = data.atm_id || '-';
    document.getElementById('review-lho').textContent = data.lho || '-';
    document.getElementById('review-location').textContent = `${data.city || '-'}, ${data.state || '-'}`;
    
    // Render sections
    renderSections(data);
    
    // Update summary
    updateSummary();
}

// Render sections for review
function renderSections(data) {
    const container = document.getElementById('sections-container');
    container.innerHTML = '';
    
    sections.forEach(section => {
        const checkpoint = state.checkpoints[section.id] || {};
        const statusField = reviewerLevel === 'adv' ? 'adv_status' : 'contractor_status';
        const sectionStatus = checkpoint[statusField] || 'pending';
        
        // Determine if section is rejected (Requirements 14.1, 14.2)
        const isRejected = sectionStatus === 'rejected';
        const borderClass = isRejected ? 'border-2 border-red-500' : '';
        const bgClass = isRejected ? 'bg-red-50' : 'bg-white';
        
        const sectionHtml = `
            <div class="rounded-xl shadow-sm ${bgClass} ${borderClass}" id="section-${section.id}">
                <div class="p-4 border-b ${isRejected ? 'bg-red-100' : 'bg-gray-50'} rounded-t-xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas ${section.icon} mr-2 ${isRejected ? 'text-red-500' : 'text-primary'}"></i>
                            <h4 class="font-semibold ${isRejected ? 'text-red-800' : 'text-gray-800'}">${section.name}</h4>
                        </div>
                        <div class="flex items-center gap-2">
                            ${getSectionStatusBadge(sectionStatus)}
                            ${canReviewSection(data.status, sectionStatus) ? `
                                <button onclick="approveSection('${section.id}')" class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">
                                    <i class="fas fa-check mr-1"></i>Approve
                                </button>
                                <button onclick="openRejectionModal('${section.id}', '${section.name}')" class="px-3 py-1 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm">
                                    <i class="fas fa-times mr-1"></i>Reject
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    ${renderSectionContent(section, data)}
                    ${renderReviewHistory(section.id)}
                </div>
            </div>
        `;
        
        container.innerHTML += sectionHtml;
    });
}

// Render section content
function renderSectionContent(section, data) {
    // Get relevant data for this section
    const sectionId = section.id;
    let content = '';
    
    // Get image paths for this section
    const imagePaths = data[sectionId];
    if (imagePaths) {
        const paths = imagePaths.split(',').filter(p => p.trim());
        if (paths.length > 0) {
            content += '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">';
            paths.forEach(path => {
                const fullPath = path.startsWith('data:') ? path : '../' + path;
                content += `
                    <img src="${escapeHtml(fullPath)}" 
                         alt="${section.name}" 
                         class="w-full h-32 object-cover rounded-lg border cursor-pointer hover:opacity-80"
                         onclick="openLightbox('${escapeHtml(fullPath)}')"
                         onerror="this.src='../assets/placeholder.png'">
                `;
            });
            content += '</div>';
        }
    }
    
    if (!content) {
        content = '<p class="text-gray-400 text-sm">No images uploaded for this section</p>';
    }
    
    return content;
}

// Render review history for a section
function renderReviewHistory(sectionId) {
    const checkpoint = state.checkpoints[sectionId];
    if (!checkpoint || !checkpoint.remarks || checkpoint.remarks.length === 0) {
        return '';
    }
    
    let html = '<div class="mt-4 border-t pt-4"><h5 class="text-sm font-medium text-gray-700 mb-2">Review History</h5>';
    
    checkpoint.remarks.forEach(remark => {
        const isRejection = remark.review_type === 'rejection';
        const bgClass = isRejection ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200';
        const textClass = isRejection ? 'text-red-700' : 'text-green-700';
        
        html += `
            <div class="p-3 rounded-lg border ${bgClass} mb-2">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium ${textClass}">
                        ${remark.reviewer_level === 'adv' ? 'ADV' : 'Contractor'} - ${isRejection ? 'Rejected' : 'Approved'}
                    </span>
                    <span class="text-xs text-gray-400">${formatDateTime(remark.created_at)}</span>
                </div>
                ${remark.remark ? `<p class="text-sm text-gray-600">${escapeHtml(remark.remark)}</p>` : ''}
            </div>
        `;
    });
    
    html += '</div>';
    return html;
}

// Check if user can review this section
function canReviewSection(installationStatus, sectionStatus) {
    if (reviewerLevel === 'none') return false;
    
    // Contractor can review submitted installations
    if (reviewerLevel === 'contractor') {
        return ['submitted', 'pending_contractor_review'].includes(installationStatus) && sectionStatus === 'pending';
    }
    
    // ADV can review contractor-approved installations
    if (reviewerLevel === 'adv') {
        return installationStatus === 'contractor_approved' && sectionStatus === 'pending';
    }
    
    return false;
}

// Get section status badge
function getSectionStatusBadge(status) {
    const badges = {
        pending: '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs">Pending</span>',
        approved: '<span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">Approved</span>',
        rejected: '<span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs">Rejected</span>'
    };
    return badges[status] || badges.pending;
}

// Update summary counts
function updateSummary() {
    let approved = 0, rejected = 0, pending = 0;
    const statusField = reviewerLevel === 'adv' ? 'adv_status' : 'contractor_status';
    
    sections.forEach(section => {
        const checkpoint = state.checkpoints[section.id] || {};
        const status = checkpoint[statusField] || 'pending';
        
        if (status === 'approved') approved++;
        else if (status === 'rejected') rejected++;
        else pending++;
    });
    
    document.getElementById('total-sections').textContent = sections.length;
    document.getElementById('approved-sections').textContent = approved;
    document.getElementById('rejected-sections').textContent = rejected;
    document.getElementById('pending-sections').textContent = pending;
}

// Approve section
async function approveSection(sectionId) {
    try {
        const response = await fetch(`${API_BASE}/review-section.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                installation_id: installationId,
                section: sectionId,
                action: 'approve'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Section approved successfully', 'success');
            loadInstallation(); // Reload to update UI
        } else {
            showToast(data.message || 'Failed to approve section', 'error');
        }
    } catch (error) {
        console.error('Error approving section:', error);
        showToast('Failed to approve section', 'error');
    }
}

// Open rejection modal
function openRejectionModal(sectionId, sectionName) {
    state.currentRejectionSection = sectionId;
    document.getElementById('rejection-section-name').textContent = sectionName;
    document.getElementById('rejection-reason').value = '';
    document.getElementById('rejection-error').classList.add('hidden');
    document.getElementById('rejection-modal').classList.remove('hidden');
}

// Close rejection modal
function closeRejectionModal() {
    state.currentRejectionSection = null;
    document.getElementById('rejection-modal').classList.add('hidden');
}

// Submit rejection
async function submitRejection() {
    const reason = document.getElementById('rejection-reason').value.trim();
    const errorEl = document.getElementById('rejection-error');
    
    // Validate minimum 10 characters (Requirements 12.3)
    if (reason.length < 10) {
        errorEl.textContent = 'Rejection reason must be at least 10 characters';
        errorEl.classList.remove('hidden');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/review-section.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                installation_id: installationId,
                section: state.currentRejectionSection,
                action: 'reject',
                reason: reason
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Section rejected', 'success');
            closeRejectionModal();
            loadInstallation(); // Reload to update UI
        } else {
            errorEl.textContent = data.message || 'Failed to reject section';
            errorEl.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error rejecting section:', error);
        errorEl.textContent = 'Failed to reject section';
        errorEl.classList.remove('hidden');
    }
}

// Approve all pending sections
async function approveAllSections() {
    if (!confirm('Are you sure you want to approve all pending sections?')) {
        return;
    }
    
    const statusField = reviewerLevel === 'adv' ? 'adv_status' : 'contractor_status';
    const pendingSections = sections.filter(section => {
        const checkpoint = state.checkpoints[section.id] || {};
        return (checkpoint[statusField] || 'pending') === 'pending';
    });
    
    if (pendingSections.length === 0) {
        showToast('No pending sections to approve', 'info');
        return;
    }
    
    let successCount = 0;
    for (const section of pendingSections) {
        try {
            const response = await fetch(`${API_BASE}/review-section.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    installation_id: installationId,
                    section: section.id,
                    action: 'approve'
                })
            });
            
            const data = await response.json();
            if (data.success) successCount++;
        } catch (error) {
            console.error('Error approving section:', error);
        }
    }
    
    showToast(`Approved ${successCount} of ${pendingSections.length} sections`, 'success');
    loadInstallation();
}

// Helper functions
function getStatusLabel(status) {
    const labels = {
        pending_materials: 'Pending Materials',
        materials_received: 'Materials Received',
        in_progress: 'In Progress',
        submitted: 'Submitted',
        pending_contractor_review: 'Pending Review',
        contractor_approved: 'Contractor Approved',
        contractor_rejected: 'Contractor Rejected',
        adv_approved: 'ADV Approved',
        adv_rejected: 'ADV Rejected'
    };
    return labels[status] || status;
}

function getStatusClass(status) {
    const classes = {
        pending_materials: 'bg-yellow-100 text-yellow-700',
        materials_received: 'bg-blue-100 text-blue-700',
        in_progress: 'bg-indigo-100 text-indigo-700',
        submitted: 'bg-purple-100 text-purple-700',
        pending_contractor_review: 'bg-orange-100 text-orange-700',
        contractor_approved: 'bg-teal-100 text-teal-700',
        contractor_rejected: 'bg-red-100 text-red-700',
        adv_approved: 'bg-green-100 text-green-700',
        adv_rejected: 'bg-red-100 text-red-700'
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
}

function formatDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    return date.toLocaleString('en-IN', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

function openLightbox(src) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/80';
    modal.onclick = () => modal.remove();
    modal.innerHTML = `
        <img src="${src}" class="max-w-[90vw] max-h-[90vh] object-contain">
        <button class="absolute top-4 right-4 text-white text-2xl" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    document.body.appendChild(modal);
}

function showError(message) {
    document.getElementById('loading-state').innerHTML = `
        <div class="text-red-500">
            <i class="fas fa-exclamation-circle text-3xl mb-4"></i>
            <p>${escapeHtml(message)}</p>
            <a href="index.php" class="mt-4 inline-block px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">Back to List</a>
        </div>
    `;
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
    toast.className = `fixed bottom-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

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

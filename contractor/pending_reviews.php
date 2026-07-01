<?php
/**
 * Contractor Pending Reviews List
 * 
 * Displays list of feasibility checks pending contractor review.
 * 
 * Requirements: 10.1
 * - Display feasibility checks pending contractor admin/manager review
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/FeasibilityReviewService.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check contractor access
if (!isContractorUser()) {
    $_SESSION['flash_error'] = 'Access denied. Contractor users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$companyId = $currentUser['company_id'];

$reviewService = new FeasibilityReviewService();

// Get pending reviews for this contractor
$pendingReviews = $reviewService->getPendingContractorReviews($companyId);

$baseUrl = '..';
$pageTitle = 'Pending Feasibility Reviews';
$currentPage = 'contractor_pending_reviews';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'Pending Reviews']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Pending Feasibility Reviews</h3>
            <p class="text-sm text-gray-500">Review and approve/reject feasibility checks submitted by engineers</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm font-medium">
                <?php echo count($pendingReviews); ?> Pending
            </span>
        </div>
    </div>

    <!-- Filters -->
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search by site name, LHO, city..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    onkeyup="filterTable()">
            </div>
            <button onclick="clearFilters()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                <i class="fas fa-times mr-1"></i>Clear
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="reviews-table" class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Site</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Location</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Engineer</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Submitted</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php if (empty($pendingReviews)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                        <i class="fas fa-clipboard-check text-4xl text-gray-300 mb-3"></i>
                        <p>No pending reviews</p>
                        <p class="text-sm">All feasibility checks have been reviewed</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($pendingReviews as $review): ?>
                <tr class="hover:bg-gray-50 transition review-row">
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center mr-3">
                                <i class="fas fa-map-marker-alt text-blue-500"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800 site-name"><?php echo htmlspecialchars($review['site_name'] ?? 'N/A'); ?></p>
                                <p class="text-xs text-gray-500 lho"><?php echo htmlspecialchars($review['lho'] ?? ''); ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm text-gray-800 city"><?php echo htmlspecialchars($review['city'] ?? 'N/A'); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($review['address'] ?? ''); ?></p>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm text-gray-800"><?php echo htmlspecialchars($review['engineer_name'] ?? 'N/A'); ?></p>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm text-gray-800"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></p>
                        <p class="text-xs text-gray-500"><?php echo date('H:i', strtotime($review['created_at'])); ?></p>
                    </td>
                    <td class="px-6 py-4">
                        <a href="feasibility_review.php?id=<?php echo $review['id']; ?>" 
                           class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition inline-flex items-center">
                            <i class="fas fa-eye mr-2"></i>Review
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterTable() {
    const searchValue = document.getElementById('search-input').value.toLowerCase();
    const rows = document.querySelectorAll('.review-row');
    
    rows.forEach(row => {
        const siteName = row.querySelector('.site-name')?.textContent.toLowerCase() || '';
        const lho = row.querySelector('.lho')?.textContent.toLowerCase() || '';
        const city = row.querySelector('.city')?.textContent.toLowerCase() || '';
        
        const matches = siteName.includes(searchValue) || 
                       lho.includes(searchValue) || 
                       city.includes(searchValue);
        
        row.style.display = matches ? '' : 'none';
    });
}

function clearFilters() {
    document.getElementById('search-input').value = '';
    filterTable();
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/main.php';
?>

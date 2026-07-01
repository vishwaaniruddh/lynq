<?php
/**
 * ADV Pending Reviews List
 * 
 * Displays list of feasibility checks pending ADV final approval.
 * Shows contractor-approved feasibility checks ready for ADV review.
 * 
 * Requirements: 11.1
 * - Display feasibility checks pending ADV final approval
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/FeasibilityReviewService.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check ADV access - only ADV users can access this page
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'Access denied. ADV users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();

$reviewService = new FeasibilityReviewService();

// Get pending ADV reviews (contractor-approved feasibility checks)
$pendingReviews = $reviewService->getPendingADVReviews();

$baseUrl = '..';
$pageTitle = 'Pending Final Approval';
$currentPage = 'adv_pending_reviews';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Pending Final Approval']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Pending Final Approval</h3>
            <p class="text-sm text-gray-500">Review contractor-approved feasibility checks for final approval</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">
                <?php echo count($pendingReviews); ?> Pending
            </span>
        </div>
    </div>

    <!-- Filters -->
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search by site name, LHO, city, contractor..." 
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
        <table id="reviews-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-12">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Site</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Location</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Engineer</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Contractor Approved</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($pendingReviews)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-clipboard-check text-4xl text-gray-300 mb-3"></i>
                        <p>No pending reviews</p>
                        <p class="text-xs">All contractor-approved feasibility checks have been reviewed</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($pendingReviews as $index => $review): ?>
                <tr class="hover:bg-gray-50/50 transition-colors review-row">
                    <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#<?php echo $index + 1; ?></td>
                    <td class="px-4 py-2.5">
                        <div class="flex items-center">
                            <div class="w-7 h-7 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg flex items-center justify-center mr-2.5 flex-shrink-0">
                                <i class="fas fa-map-marker-alt text-blue-500 text-xs"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800 text-xs site-name"><?php echo htmlspecialchars($review['site_name'] ?? 'N/A'); ?></p>
                                <p class="text-[10px] text-gray-500 lho"><?php echo htmlspecialchars($review['lho'] ?? ''); ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-2.5">
                        <p class="text-xs text-gray-800 city"><?php echo htmlspecialchars($review['city'] ?? 'N/A'); ?></p>
                        <p class="text-[10px] text-gray-500"><?php echo htmlspecialchars($review['address'] ?? ''); ?></p>
                    </td>
                    <td class="px-4 py-2.5">
                        <p class="text-xs text-gray-800"><?php echo htmlspecialchars($review['engineer_name'] ?? 'N/A'); ?></p>
                    </td>
                    <td class="px-4 py-2.5">
                        <?php if (!empty($review['contractor_approved_at'])): ?>
                        <p class="text-xs text-gray-800"><?php echo date('M d, Y', strtotime($review['contractor_approved_at'])); ?></p>
                        <p class="text-[10px] text-gray-500"><?php echo date('H:i', strtotime($review['contractor_approved_at'])); ?></p>
                        <?php else: ?>
                        <p class="text-xs text-gray-500">N/A</p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        <a href="feasibility_review.php?id=<?php echo $review['id']; ?>" 
                           class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors inline-flex" title="Review">
                            <i class="fas fa-eye text-xs"></i>
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

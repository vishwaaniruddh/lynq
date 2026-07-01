<?php
/**
 * Engineer Feasibility List Page
 * 
 * Displays all feasibility checks for the logged-in engineer.
 * Shows pending, approved, rejected status with remarks.
 * 
 * Requirements: 12.1, 12.5
 * - View all feasibility checks submitted by the engineer
 * - See approval status (pending, approved, rejected)
 * - View rejection reasons and remarks
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/FeasibilityService.php';
require_once __DIR__ . '/../services/FeasibilityReviewService.php';

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
$engineerId = $currentUser['id'];

$feasibilityService = new FeasibilityService();
$reviewService = new FeasibilityReviewService();

// Get all feasibility checks for this engineer
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT 
        fc.*,
        ea.id as assignment_id,
        ea.feasibility_status,
        ea.assigned_at,
        s.site_name, s.lho, s.city, s.state, s.address,
        s.customer_name, s.bank_name,
        COALESCE(fc.approval_status, 'pending_contractor_review') as approval_status,
        DATEDIFF(NOW(), ea.assigned_at) as aging_days
    FROM feasibility_checks fc
    JOIN engineer_assignments ea ON fc.assignment_id = ea.id
    JOIN sites s ON fc.site_id = s.id
    WHERE ea.engineer_id = ?
    ORDER BY fc.created_at DESC
");
$stmt->execute([$engineerId]);
$feasibilityChecks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts by status
$counts = [
    'total' => count($feasibilityChecks),
    'pending_contractor_review' => 0,
    'contractor_approved' => 0,
    'contractor_rejected' => 0,
    'adv_approved' => 0,
    'adv_rejected' => 0
];

foreach ($feasibilityChecks as $fc) {
    $status = $fc['approval_status'] ?? 'pending_contractor_review';
    if (isset($counts[$status])) {
        $counts[$status]++;
    }
}

$baseUrl = '..';
$pageTitle = 'My Feasibility Checks';
$currentPage = 'engineer_feasibility';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'My Feasibility Checks']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">My Feasibility Checks</h3>
            <p class="text-sm text-gray-500">View all your submitted feasibility assessments and their approval status</p>
        </div>
        <a href="sites.php" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition inline-flex items-center">
            <i class="fas fa-plus mr-2"></i>New Feasibility Check
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="p-4 border-b bg-gray-50">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('')">
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-800"><?php echo $counts['total']; ?></p>
                    <p class="text-xs text-gray-500">Total</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('pending_contractor_review')">
                <div class="text-center">
                    <p class="text-2xl font-bold text-yellow-600"><?php echo $counts['pending_contractor_review']; ?></p>
                    <p class="text-xs text-gray-500">Pending Review</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('contractor_approved')">
                <div class="text-center">
                    <p class="text-2xl font-bold text-blue-600"><?php echo $counts['contractor_approved']; ?></p>
                    <p class="text-xs text-gray-500">Contractor Approved</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('contractor_rejected')">
                <div class="text-center">
                    <p class="text-2xl font-bold text-red-600"><?php echo $counts['contractor_rejected']; ?></p>
                    <p class="text-xs text-gray-500">Contractor Rejected</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('adv_approved')">
                <div class="text-center">
                    <p class="text-2xl font-bold text-green-600"><?php echo $counts['adv_approved']; ?></p>
                    <p class="text-xs text-gray-500">ADV Approved</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('adv_rejected')">
                <div class="text-center">
                    <p class="text-2xl font-bold text-orange-600"><?php echo $counts['adv_rejected']; ?></p>
                    <p class="text-xs text-gray-500">ADV Rejected</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="p-4 border-b">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search by site name, LHO, city..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    onkeyup="filterTable()">
            </div>
            <div>
                <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" onchange="filterTable()">
                    <option value="">All Statuses</option>
                    <option value="pending_contractor_review">Pending Review</option>
                    <option value="contractor_approved">Contractor Approved</option>
                    <option value="contractor_rejected">Contractor Rejected</option>
                    <option value="adv_approved">ADV Approved</option>
                    <option value="adv_rejected">ADV Rejected</option>
                </select>
            </div>
            <button onclick="clearFilters()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                <i class="fas fa-times mr-1"></i>Clear
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="feasibility-table" class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Site</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Customer/Bank</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Location</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Aging</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php if (empty($feasibilityChecks)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                        <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-3"></i>
                        <p>No feasibility checks found</p>
                        <p class="text-sm mt-2">Go to <a href="sites.php" class="text-primary hover:underline">My Assigned Sites</a> to submit a feasibility check</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($feasibilityChecks as $fc): 
                    $agingDays = (int)($fc['aging_days'] ?? 0);
                    $agingColor = $agingDays <= 3 ? 'text-green-600' : ($agingDays <= 7 ? 'text-yellow-600' : 'text-red-600');
                ?>
                <tr class="hover:bg-gray-50 transition feasibility-row" 
                    data-search="<?php echo strtolower(htmlspecialchars($fc['site_name'] . ' ' . $fc['lho'] . ' ' . $fc['city'] . ' ' . $fc['customer_name'] . ' ' . $fc['bank_name'])); ?>"
                    data-status="<?php echo htmlspecialchars($fc['approval_status']); ?>">
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center mr-3">
                                <i class="fas fa-map-marker-alt text-blue-500"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($fc['site_name'] ?? 'N/A'); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($fc['lho'] ?? ''); ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-sm text-gray-800"><?php echo htmlspecialchars($fc['customer_name'] ?? 'N/A'); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($fc['bank_name'] ?? '-'); ?></p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-sm text-gray-800"><?php echo htmlspecialchars($fc['city'] ?? 'N/A'); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($fc['state'] ?? ''); ?></p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-sm font-medium <?php echo $agingColor; ?>"><?php echo $agingDays; ?> days</p>
                        <p class="text-xs text-gray-400"><?php echo date('M d', strtotime($fc['assigned_at'])); ?></p>
                    </td>
                    <td class="px-4 py-3">
                        <?php echo getApprovalStatusBadge($fc['approval_status']); ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center space-x-2">
                            <a href="feasibility_form.php?assignment_id=<?php echo $fc['assignment_id']; ?>&view=1" 
                               class="px-3 py-1.5 text-xs rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 transition">
                                <i class="fas fa-eye mr-1"></i>View
                            </a>
                            <?php if (in_array($fc['approval_status'], ['contractor_rejected', 'adv_rejected'])): ?>
                            <a href="feasibility_form.php?assignment_id=<?php echo $fc['assignment_id']; ?>&edit=1" 
                               class="px-3 py-1.5 text-xs rounded-lg bg-orange-100 text-orange-700 hover:bg-orange-200 transition">
                                <i class="fas fa-edit mr-1"></i>Resubmit
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
function getApprovalStatusBadge($status) {
    $badges = [
        'pending_contractor_review' => '<span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-700"><i class="fas fa-clock mr-1"></i>Pending Review</span>',
        'contractor_approved' => '<span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700"><i class="fas fa-check mr-1"></i>Contractor Approved</span>',
        'contractor_rejected' => '<span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-700"><i class="fas fa-times mr-1"></i>Contractor Rejected</span>',
        'adv_approved' => '<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700"><i class="fas fa-check-double mr-1"></i>ADV Approved</span>',
        'adv_rejected' => '<span class="px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-700"><i class="fas fa-times mr-1"></i>ADV Rejected</span>'
    ];
    return $badges[$status] ?? '<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700">Unknown</span>';
}
?>

<script>
function filterTable() {
    const searchValue = document.getElementById('search-input').value.toLowerCase();
    const statusValue = document.getElementById('status-filter').value;
    const rows = document.querySelectorAll('.feasibility-row');
    
    rows.forEach(row => {
        const searchData = row.dataset.search || '';
        const rowStatus = row.dataset.status || '';
        
        const matchesSearch = searchData.includes(searchValue);
        const matchesStatus = !statusValue || rowStatus === statusValue;
        
        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
}

function filterByStatus(status) {
    document.getElementById('status-filter').value = status;
    filterTable();
}

function clearFilters() {
    document.getElementById('search-input').value = '';
    document.getElementById('status-filter').value = '';
    filterTable();
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/main.php';
?>

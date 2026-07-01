<?php
/**
 * Contractor Dashboard
 * 
 * Comprehensive overview for contractors including:
 * - Site delegation statistics
 * - Engineer assignments
 * - Inventory overview
 * - Recent activity
 * - Performance metrics
 */

require_once __DIR__ . '/../config/autoload.php';

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
$baseUrl = '..';
$pageTitle = 'Contractor Dashboard';
$currentPage = 'contractor_dashboard';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard']
];

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];

// Initialize statistics
$stats = [
    'delegations' => ['total' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0],
    'engineers' => ['total' => 0, 'active' => 0, 'with_sites' => 0],
    'assignments' => ['total' => 0, 'in_progress' => 0, 'completed' => 0, 'pending' => 0],
    'inventory' => [
        'total_received' => 0, 
        'pending_acknowledgment' => 0, 
        'acknowledged' => 0,
        'with_engineers' => 0, 
        'pending_return' => 0, 
        'not_working' => 0
    ]
];

$recentDelegations = [];
$recentActivity = [];
$engineerPerformance = [];
$monthlyTrend = [];

try {
    // Delegation Statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM site_delegations
        WHERE contractor_id = ?
    ");
    $stmt->execute([$companyId]);
    $delegationStats = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($delegationStats) {
        $stats['delegations'] = [
            'total' => (int)$delegationStats['total'],
            'pending' => (int)$delegationStats['pending'],
            'accepted' => (int)$delegationStats['accepted'],
            'rejected' => (int)$delegationStats['rejected']
        ];
    }

    // Engineer Statistics
    $stmt = $db->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active
        FROM users 
        WHERE company_id = ? AND role_id = (SELECT id FROM roles WHERE name = 'Engineer' LIMIT 1)
    ");
    $stmt->execute([$companyId]);
    $engineerStats = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($engineerStats) {
        $stats['engineers']['total'] = (int)$engineerStats['total'];
        $stats['engineers']['active'] = (int)$engineerStats['active'];
    }

    // Engineers with assigned sites
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT ea.engineer_id) as with_sites
        FROM engineer_assignments ea
        INNER JOIN site_delegations sd ON ea.delegation_id = sd.id
        WHERE sd.contractor_id = ?
    ");
    $stmt->execute([$companyId]);
    $withSites = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['engineers']['with_sites'] = (int)($withSites['with_sites'] ?? 0);

    // Assignment Statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN ea.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN ea.status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN ea.status = 'pending' OR ea.status = 'assigned' THEN 1 ELSE 0 END) as pending
        FROM engineer_assignments ea
        INNER JOIN site_delegations sd ON ea.delegation_id = sd.id
        WHERE sd.contractor_id = ?
    ");
    $stmt->execute([$companyId]);
    $assignmentStats = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($assignmentStats) {
        $stats['assignments'] = [
            'total' => (int)$assignmentStats['total'],
            'in_progress' => (int)$assignmentStats['in_progress'],
            'completed' => (int)$assignmentStats['completed'],
            'pending' => (int)$assignmentStats['pending']
        ];
    }

    // Recent Delegations
    $stmt = $db->prepare("
        SELECT sd.*, s.site_name, s.lho, s.city, s.state,
               u.username as delegated_by_name
        FROM site_delegations sd
        INNER JOIN sites s ON sd.site_id = s.id
        LEFT JOIN users u ON sd.delegated_by = u.id
        WHERE sd.contractor_id = ?
        ORDER BY sd.delegated_at DESC
        LIMIT 5
    ");
    $stmt->execute([$companyId]);
    $recentDelegations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Engineer Performance (top 5)
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.email,
               COUNT(ea.id) as total_assignments,
               SUM(CASE WHEN ea.status = 'completed' THEN 1 ELSE 0 END) as completed,
               SUM(CASE WHEN ea.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress
        FROM users u
        LEFT JOIN engineer_assignments ea ON u.id = ea.engineer_id
        LEFT JOIN site_delegations sd ON ea.delegation_id = sd.id AND sd.contractor_id = ?
        WHERE u.company_id = ? AND u.role_id = (SELECT id FROM roles WHERE name = 'Engineer' LIMIT 1)
        GROUP BY u.id, u.username, u.email
        ORDER BY completed DESC, total_assignments DESC
        LIMIT 5
    ");
    $stmt->execute([$companyId, $companyId]);
    $engineerPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly Trend (last 6 months)
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(delegated_at, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM site_delegations
        WHERE contractor_id = ? AND delegated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(delegated_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$companyId]);
    $monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Tables may not exist yet - continue with defaults
}

ob_start();
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="space-y-6">
    <!-- Welcome Header -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold">Welcome back, <?php echo htmlspecialchars($currentUser['username']); ?>!</h1>
                <p class="text-indigo-100 mt-1">Here's your contractor overview for today</p>
            </div>
            <div class="mt-4 md:mt-0 flex items-center space-x-3">
                <a href="delegations.php" class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg transition flex items-center">
                    <i class="fas fa-inbox mr-2"></i>View Delegations
                </a>
                <a href="assign.php" class="px-4 py-2 bg-white text-indigo-600 rounded-lg hover:bg-indigo-50 transition flex items-center">
                    <i class="fas fa-user-plus mr-2"></i>Assign Sites
                </a>
            </div>
        </div>
    </div>

    <!-- Main Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Delegations -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition cursor-pointer" onclick="window.location.href='delegations.php'">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Delegations</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1"><?php echo $stats['delegations']['total']; ?></p>
                    <div class="flex items-center mt-2 text-xs">
                        <span class="text-yellow-600 mr-3"><i class="fas fa-clock mr-1"></i><?php echo $stats['delegations']['pending']; ?> pending</span>
                    </div>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/30">
                    <i class="fas fa-inbox text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Accepted Sites -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition cursor-pointer" onclick="window.location.href='delegations.php?status=accepted'">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Accepted Sites</p>
                    <p class="text-3xl font-bold text-green-600 mt-1"><?php echo $stats['delegations']['accepted']; ?></p>
                    <div class="flex items-center mt-2 text-xs">
                        <span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Ready to assign</span>
                    </div>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-green-500/30">
                    <i class="fas fa-check-circle text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Engineers -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition cursor-pointer" onclick="window.location.href='../users/index.php'">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Engineers</p>
                    <p class="text-3xl font-bold text-purple-600 mt-1"><?php echo $stats['engineers']['total']; ?></p>
                    <div class="flex items-center mt-2 text-xs">
                        <span class="text-purple-600"><i class="fas fa-user-check mr-1"></i><?php echo $stats['engineers']['with_sites']; ?> with sites</span>
                    </div>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center shadow-lg shadow-purple-500/30">
                    <i class="fas fa-hard-hat text-white text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Assignments -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition cursor-pointer" onclick="window.location.href='assign.php'">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Site Assignments</p>
                    <p class="text-3xl font-bold text-cyan-600 mt-1"><?php echo $stats['assignments']['total']; ?></p>
                    <div class="flex items-center mt-2 text-xs">
                        <span class="text-cyan-600"><i class="fas fa-spinner mr-1"></i><?php echo $stats['assignments']['in_progress']; ?> in progress</span>
                    </div>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-cyan-500 to-teal-600 flex items-center justify-center shadow-lg shadow-cyan-500/30">
                    <i class="fas fa-tasks text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Delegation Status Breakdown -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-center cursor-pointer hover:bg-yellow-100 transition" onclick="window.location.href='delegations.php?status=pending'">
            <i class="fas fa-clock text-yellow-500 text-2xl mb-2"></i>
            <p class="text-2xl font-bold text-yellow-700"><?php echo $stats['delegations']['pending']; ?></p>
            <p class="text-sm text-yellow-600">Pending</p>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center cursor-pointer hover:bg-green-100 transition" onclick="window.location.href='delegations.php?status=accepted'">
            <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
            <p class="text-2xl font-bold text-green-700"><?php echo $stats['delegations']['accepted']; ?></p>
            <p class="text-sm text-green-600">Accepted</p>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center cursor-pointer hover:bg-red-100 transition" onclick="window.location.href='delegations.php?status=rejected'">
            <i class="fas fa-times-circle text-red-500 text-2xl mb-2"></i>
            <p class="text-2xl font-bold text-red-700"><?php echo $stats['delegations']['rejected']; ?></p>
            <p class="text-sm text-red-600">Rejected</p>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center">
            <i class="fas fa-percentage text-blue-500 text-2xl mb-2"></i>
            <p class="text-2xl font-bold text-blue-700">
                <?php 
                $acceptRate = $stats['delegations']['total'] > 0 
                    ? round(($stats['delegations']['accepted'] / $stats['delegations']['total']) * 100) 
                    : 0;
                echo $acceptRate . '%';
                ?>
            </p>
            <p class="text-sm text-blue-600">Accept Rate</p>
        </div>
    </div>

    <!-- Inventory/Material Section - Loaded via API -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-boxes text-amber-500 mr-2"></i>Material & Inventory Overview
            </h3>
            <a href="../inventory/dashboard_contractor.php" class="text-sm text-primary hover:underline">View Details →</a>
        </div>
        
        <!-- Inventory Stats Cards - Loaded via JS -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center cursor-pointer hover:bg-blue-100 transition" onclick="window.location.href='../inventory/dashboard_contractor.php'">
                <i class="fas fa-truck-loading text-blue-500 text-xl mb-2"></i>
                <p id="inv-total-received" class="text-2xl font-bold text-blue-700">-</p>
                <p class="text-xs text-blue-600">Total Received</p>
            </div>
            <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 text-center cursor-pointer hover:bg-orange-100 transition" onclick="scrollToAcknowledgments()">
                <i class="fas fa-clipboard-check text-orange-500 text-xl mb-2"></i>
                <p id="inv-pending-ack" class="text-2xl font-bold text-orange-700">-</p>
                <p class="text-xs text-orange-600">Pending Ack.</p>
            </div>
            <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center cursor-pointer hover:bg-green-100 transition" onclick="window.location.href='../inventory/dashboard_contractor.php'">
                <i class="fas fa-check-double text-green-500 text-xl mb-2"></i>
                <p id="inv-acknowledged" class="text-2xl font-bold text-green-700">-</p>
                <p class="text-xs text-green-600">Acknowledged</p>
            </div>
            <div class="bg-purple-50 border border-purple-200 rounded-xl p-4 text-center cursor-pointer hover:bg-purple-100 transition" onclick="window.location.href='../inventory/dashboard_contractor.php'">
                <i class="fas fa-user-hard-hat text-purple-500 text-xl mb-2"></i>
                <p id="inv-with-engineers" class="text-2xl font-bold text-purple-700">-</p>
                <p class="text-xs text-purple-600">With Engineers</p>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center cursor-pointer hover:bg-amber-100 transition" onclick="window.location.href='../inventory/dashboard_contractor.php'">
                <i class="fas fa-undo text-amber-500 text-xl mb-2"></i>
                <p id="inv-pending-return" class="text-2xl font-bold text-amber-700">-</p>
                <p class="text-xs text-amber-600">Pending Return</p>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center cursor-pointer hover:bg-red-100 transition" onclick="window.location.href='../inventory/dashboard_contractor.php'">
                <i class="fas fa-exclamation-triangle text-red-500 text-xl mb-2"></i>
                <p id="inv-not-working" class="text-2xl font-bold text-red-700">-</p>
                <p class="text-xs text-red-600">Not Working</p>
            </div>
        </div>

        <!-- Pending Acknowledgments Alert - Loaded via JS -->
        <div id="pending-ack-section" class="hidden bg-orange-50 border border-orange-200 rounded-xl p-4 mb-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-bell text-white"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-orange-800">Pending Material Acknowledgments</h4>
                        <p class="text-sm text-orange-600">You have <span id="pending-ack-count">0</span> dispatch(es) awaiting acknowledgment with photo/video proof</p>
                    </div>
                </div>
            </div>
            <div id="pending-ack-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3"></div>
        </div>

        <!-- Recent Material Received - Loaded via JS -->
        <div id="recent-dispatches-section">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-medium text-gray-700">Recent Material Received</h4>
                <a href="../inventory/dashboard_contractor.php" class="text-xs text-primary hover:underline">View All →</a>
            </div>
            <div id="recent-dispatches-list" class="space-y-2">
                <div class="flex items-center justify-center py-4">
                    <i class="fas fa-spinner fa-spin text-gray-300 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Delegation Trend Chart -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-chart-line text-indigo-500 mr-2"></i>Delegation Trend (6 Months)
            </h3>
            <div class="h-64">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <!-- Status Distribution Chart -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-chart-pie text-purple-500 mr-2"></i>Delegation Status Distribution
            </h3>
            <div class="h-64 flex items-center justify-center">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Delegations & Engineer Performance -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Delegations -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-history text-blue-500 mr-2"></i>Recent Delegations
                </h3>
                <a href="delegations.php" class="text-sm text-primary hover:underline">View All</a>
            </div>
            <div class="p-4">
                <?php if (empty($recentDelegations)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-inbox text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No delegations yet</p>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recentDelegations as $delegation): ?>
                    <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center mr-3">
                                <i class="fas fa-map-marker-alt text-blue-500"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($delegation['site_name'] ?? 'N/A'); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($delegation['city'] ?? ''); ?>, <?php echo htmlspecialchars($delegation['state'] ?? ''); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <?php
                            $statusColors = [
                                'pending' => 'bg-yellow-100 text-yellow-700',
                                'accepted' => 'bg-green-100 text-green-700',
                                'rejected' => 'bg-red-100 text-red-700'
                            ];
                            $statusColor = $statusColors[$delegation['status']] ?? 'bg-gray-100 text-gray-700';
                            ?>
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusColor; ?>">
                                <?php echo ucfirst($delegation['status']); ?>
                            </span>
                            <p class="text-xs text-gray-400 mt-1"><?php echo date('M d', strtotime($delegation['delegated_at'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Engineer Performance -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-trophy text-yellow-500 mr-2"></i>Engineer Performance
                </h3>
                <a href="../users/index.php" class="text-sm text-primary hover:underline">View All</a>
            </div>
            <div class="p-4">
                <?php if (empty($engineerPerformance)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-users text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No engineers found</p>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($engineerPerformance as $index => $engineer): ?>
                    <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition">
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center mr-3 text-white text-sm font-bold">
                                <?php echo $index + 1; ?>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($engineer['username']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($engineer['email'] ?? ''); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-800"><?php echo $engineer['total_assignments']; ?> sites</p>
                            <p class="text-xs text-green-600"><?php echo $engineer['completed']; ?> completed</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-bolt text-orange-500 mr-2"></i>Quick Actions
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="delegations.php?status=pending" class="flex flex-col items-center p-4 rounded-xl bg-yellow-50 hover:bg-yellow-100 transition">
                <div class="w-12 h-12 rounded-xl bg-yellow-500 flex items-center justify-center mb-2">
                    <i class="fas fa-clock text-white text-xl"></i>
                </div>
                <span class="text-sm font-medium text-yellow-700">Review Pending</span>
                <span class="text-xs text-yellow-600"><?php echo $stats['delegations']['pending']; ?> sites</span>
            </a>
            <a href="assign.php" class="flex flex-col items-center p-4 rounded-xl bg-purple-50 hover:bg-purple-100 transition">
                <div class="w-12 h-12 rounded-xl bg-purple-500 flex items-center justify-center mb-2">
                    <i class="fas fa-user-plus text-white text-xl"></i>
                </div>
                <span class="text-sm font-medium text-purple-700">Assign Engineers</span>
                <span class="text-xs text-purple-600">Manage assignments</span>
            </a>
            <a href="bulk_assign.php" class="flex flex-col items-center p-4 rounded-xl bg-blue-50 hover:bg-blue-100 transition">
                <div class="w-12 h-12 rounded-xl bg-blue-500 flex items-center justify-center mb-2">
                    <i class="fas fa-users-cog text-white text-xl"></i>
                </div>
                <span class="text-sm font-medium text-blue-700">Bulk Assign</span>
                <span class="text-xs text-blue-600">Multiple sites</span>
            </a>
            <a href="../inventory/dashboard_contractor.php" class="flex flex-col items-center p-4 rounded-xl bg-green-50 hover:bg-green-100 transition">
                <div class="w-12 h-12 rounded-xl bg-green-500 flex items-center justify-center mb-2">
                    <i class="fas fa-boxes text-white text-xl"></i>
                </div>
                <span class="text-sm font-medium text-green-700">Inventory</span>
                <span class="text-xs text-green-600">View materials</span>
            </a>
        </div>
    </div>
</div>

<script>
// Load Inventory Data via API
async function loadInventoryData() {
    try {
        const response = await fetch('../api/inventory/dashboard/contractor.php', { credentials: 'include' });
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            // Update summary stats
            const summary = data.summary || {};
            const pendingReturns = data.pending_returns || {};
            const nonWorkingItems = data.non_working_items || {};
            const recentActivity = data.recent_activity || {};
            
            // Calculate total received from dispatches
            const recentDispatches = recentActivity.recent_dispatches || [];
            const pendingAck = recentActivity.pending_acknowledgments || {};
            
            document.getElementById('inv-total-received').textContent = summary.total_assets || 0;
            document.getElementById('inv-pending-ack').textContent = pendingAck.count || 0;
            document.getElementById('inv-acknowledged').textContent = recentDispatches.filter(d => d.acknowledgment_status === 'acknowledged').length || 0;
            document.getElementById('inv-with-engineers').textContent = summary.engineer_count || 0;
            document.getElementById('inv-pending-return').textContent = pendingReturns.count || 0;
            document.getElementById('inv-not-working').textContent = nonWorkingItems.count || 0;
            
            // Render pending acknowledgments
            renderPendingAcknowledgments(pendingAck);
            
            // Render recent dispatches
            renderRecentDispatches(recentDispatches);
        }
    } catch (error) {
        console.error('Error loading inventory data:', error);
        document.getElementById('recent-dispatches-list').innerHTML = '<p class="text-center text-gray-400 py-4">Failed to load inventory data</p>';
    }
}

function renderPendingAcknowledgments(pendingAck) {
    const section = document.getElementById('pending-ack-section');
    const list = document.getElementById('pending-ack-list');
    const countEl = document.getElementById('pending-ack-count');
    const items = pendingAck.items || [];
    
    countEl.textContent = pendingAck.count || 0;
    
    if (items.length > 0) {
        section.classList.remove('hidden');
        list.innerHTML = items.map(d => `
            <div class="bg-white rounded-lg p-3 border border-orange-200">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-medium text-primary text-sm">${escapeHtml(d.dispatch_number)}</span>
                    <span class="px-2 py-0.5 bg-orange-100 text-orange-700 rounded text-xs">Pending</span>
                </div>
                <p class="text-xs text-gray-600 mb-2">From: ${escapeHtml(d.from_warehouse_name || d.from_company_name || 'ADV')}</p>
                <p class="text-xs text-gray-400 mb-3">${formatDate(d.dispatch_date)}</p>
                <button onclick="openAcknowledgeModal(${d.id}, '${escapeHtml(d.dispatch_number)}')" 
                    class="w-full px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm transition">
                    <i class="fas fa-camera mr-1"></i>Acknowledge with Proof
                </button>
            </div>
        `).join('');
    } else {
        section.classList.add('hidden');
    }
}

function renderRecentDispatches(dispatches) {
    const list = document.getElementById('recent-dispatches-list');
    
    if (!dispatches || dispatches.length === 0) {
        list.innerHTML = '<p class="text-center text-gray-400 py-4">No material received yet</p>';
        return;
    }
    
    const statusColors = {
        pending: 'bg-yellow-100 text-yellow-700',
        in_transit: 'bg-blue-100 text-blue-700',
        delivered: 'bg-green-100 text-green-700',
        cancelled: 'bg-red-100 text-red-700'
    };
    
    list.innerHTML = dispatches.slice(0, 5).map(d => `
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition cursor-pointer" onclick="window.location.href='../inventory/dashboard_contractor.php'">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center mr-3">
                    <i class="fas fa-truck text-blue-500 text-sm"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800 text-sm">${escapeHtml(d.dispatch_number)}</p>
                    <p class="text-xs text-gray-500">From: ${escapeHtml(d.from_warehouse_name || d.from_company_name || 'ADV')}</p>
                </div>
            </div>
            <div class="text-right">
                <span class="px-2 py-1 rounded-full text-xs font-medium ${statusColors[d.status] || 'bg-gray-100 text-gray-700'}">
                    ${(d.status || '').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                </span>
                ${d.acknowledgment_status === 'acknowledged' ? '<p class="text-xs text-green-600 mt-1"><i class="fas fa-check mr-1"></i>Acknowledged</p>' : ''}
                <p class="text-xs text-gray-400 mt-1">${formatDate(d.dispatch_date)}</p>
            </div>
        </div>
    `).join('');
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Chart.js Configuration
document.addEventListener('DOMContentLoaded', function() {
    // Load inventory data via API
    loadInventoryData();
    
    // Monthly Trend Data
    const monthlyData = <?php echo json_encode($monthlyTrend); ?>;
    const labels = monthlyData.map(d => {
        const date = new Date(d.month + '-01');
        return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
    });
    const totalData = monthlyData.map(d => parseInt(d.total));
    const acceptedData = monthlyData.map(d => parseInt(d.accepted));
    const rejectedData = monthlyData.map(d => parseInt(d.rejected));

    // Trend Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: labels.length > 0 ? labels : ['No Data'],
            datasets: [
                {
                    label: 'Total',
                    data: totalData.length > 0 ? totalData : [0],
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Accepted',
                    data: acceptedData.length > 0 ? acceptedData : [0],
                    borderColor: '#10b981',
                    backgroundColor: 'transparent',
                    tension: 0.4
                },
                {
                    label: 'Rejected',
                    data: rejectedData.length > 0 ? rejectedData : [0],
                    borderColor: '#ef4444',
                    backgroundColor: 'transparent',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Status Distribution Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Accepted', 'Rejected'],
            datasets: [{
                data: [
                    <?php echo $stats['delegations']['pending']; ?>,
                    <?php echo $stats['delegations']['accepted']; ?>,
                    <?php echo $stats['delegations']['rejected']; ?>
                ],
                backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            cutout: '60%'
        }
    });
});

// Scroll to acknowledgments section
function scrollToAcknowledgments() {
    const section = document.getElementById('pending-ack-section');
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'center' });
        section.classList.add('ring-2', 'ring-orange-400');
        setTimeout(() => section.classList.remove('ring-2', 'ring-orange-400'), 2000);
    }
}

// Acknowledgment Modal State
let currentDispatchId = null;
let uploadedFiles = [];

// Open Acknowledge Modal
function openAcknowledgeModal(dispatchId, dispatchNumber) {
    currentDispatchId = dispatchId;
    uploadedFiles = [];
    document.getElementById('ack-dispatch-number').textContent = dispatchNumber;
    document.getElementById('ack-notes').value = '';
    document.getElementById('ack-condition').value = 'good';
    document.getElementById('file-preview-container').innerHTML = '';
    document.getElementById('acknowledge-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close Acknowledge Modal
function closeAcknowledgeModal() {
    document.getElementById('acknowledge-modal').classList.add('hidden');
    document.body.style.overflow = '';
    currentDispatchId = null;
    uploadedFiles = [];
}

// Handle file selection
function handleFileSelect(event) {
    const files = event.target.files;
    const previewContainer = document.getElementById('file-preview-container');
    
    for (let file of files) {
        // Validate file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/webm', 'video/quicktime'];
        if (!validTypes.includes(file.type)) {
            showToast('Invalid file type. Please upload images or videos only.', 'error');
            continue;
        }
        
        // Validate file size (max 50MB for videos, 10MB for images)
        const maxSize = file.type.startsWith('video/') ? 50 * 1024 * 1024 : 10 * 1024 * 1024;
        if (file.size > maxSize) {
            showToast(`File too large. Max size: ${file.type.startsWith('video/') ? '50MB' : '10MB'}`, 'error');
            continue;
        }
        
        uploadedFiles.push(file);
        
        // Create preview
        const previewDiv = document.createElement('div');
        previewDiv.className = 'relative group';
        
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewDiv.innerHTML = `
                    <img src="${e.target.result}" class="w-20 h-20 object-cover rounded-lg border">
                    <button type="button" onclick="removeFile(${uploadedFiles.length - 1})" 
                        class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs hover:bg-red-600">
                        <i class="fas fa-times"></i>
                    </button>
                    <span class="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-xs p-1 rounded-b-lg truncate">${file.name}</span>
                `;
            };
            reader.readAsDataURL(file);
        } else {
            previewDiv.innerHTML = `
                <div class="w-20 h-20 bg-gray-100 rounded-lg border flex flex-col items-center justify-center">
                    <i class="fas fa-video text-gray-400 text-xl"></i>
                    <span class="text-xs text-gray-500 mt-1">Video</span>
                </div>
                <button type="button" onclick="removeFile(${uploadedFiles.length - 1})" 
                    class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs hover:bg-red-600">
                    <i class="fas fa-times"></i>
                </button>
                <span class="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-xs p-1 rounded-b-lg truncate">${file.name}</span>
            `;
        }
        
        previewContainer.appendChild(previewDiv);
    }
    
    // Clear input
    event.target.value = '';
}

// Remove file from upload list
function removeFile(index) {
    uploadedFiles.splice(index, 1);
    // Re-render previews
    const previewContainer = document.getElementById('file-preview-container');
    previewContainer.innerHTML = '';
    uploadedFiles.forEach((file, i) => {
        const previewDiv = document.createElement('div');
        previewDiv.className = 'relative group';
        
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewDiv.innerHTML = `
                    <img src="${e.target.result}" class="w-20 h-20 object-cover rounded-lg border">
                    <button type="button" onclick="removeFile(${i})" 
                        class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs hover:bg-red-600">
                        <i class="fas fa-times"></i>
                    </button>
                `;
            };
            reader.readAsDataURL(file);
        } else {
            previewDiv.innerHTML = `
                <div class="w-20 h-20 bg-gray-100 rounded-lg border flex flex-col items-center justify-center">
                    <i class="fas fa-video text-gray-400 text-xl"></i>
                </div>
                <button type="button" onclick="removeFile(${i})" 
                    class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs hover:bg-red-600">
                    <i class="fas fa-times"></i>
                </button>
            `;
        }
        previewContainer.appendChild(previewDiv);
    });
}

// Submit acknowledgment
async function submitAcknowledgment() {
    if (!currentDispatchId) return;
    
    if (uploadedFiles.length === 0) {
        showToast('Please upload at least one photo or video as proof of receipt', 'error');
        return;
    }
    
    const notes = document.getElementById('ack-notes').value.trim();
    const condition = document.getElementById('ack-condition').value;
    
    const submitBtn = document.getElementById('ack-submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
    
    try {
        const formData = new FormData();
        formData.append('dispatch_id', currentDispatchId);
        formData.append('notes', notes);
        formData.append('condition', condition);
        
        uploadedFiles.forEach((file, index) => {
            formData.append(`proof_files[${index}]`, file);
        });
        
        const response = await fetch('../api/inventory/dispatch/acknowledge.php', {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Material acknowledged successfully! ADV has been notified.', 'success');
            closeAcknowledgeModal();
            // Reload page to update stats
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(result.message || 'Failed to acknowledge dispatch', 'error');
        }
    } catch (error) {
        console.error('Error acknowledging dispatch:', error);
        showToast('Failed to submit acknowledgment. Please try again.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Confirm Receipt';
    }
}

// Toast notification
function showToast(message, type = 'info') {
    const colors = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500' };
    const icons = { success: 'check-circle', error: 'exclamation-circle', warning: 'exclamation-triangle', info: 'info-circle' };
    
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-[100] ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2 animate-fade-in`;
    toast.innerHTML = `
        <i class="fas fa-${icons[type]}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="ml-4 hover:opacity-75"><i class="fas fa-times"></i></button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}
</script>

<!-- Acknowledge Modal -->
<div id="acknowledge-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeAcknowledgeModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Acknowledge Material Receipt</h3>
                    <p class="text-sm text-gray-500">Dispatch: <span id="ack-dispatch-number" class="font-medium text-primary"></span></p>
                </div>
                <button onclick="closeAcknowledgeModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-5 space-y-4">
                <!-- Photo/Video Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Upload Proof (Photo/Video) <span class="text-red-500">*</span>
                    </label>
                    <p class="text-xs text-gray-500 mb-2">Upload photos or videos showing the received materials and their condition</p>
                    
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-primary transition cursor-pointer" 
                         onclick="document.getElementById('proof-files').click()">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                        <p class="text-sm text-gray-600">Click to upload or drag and drop</p>
                        <p class="text-xs text-gray-400">Images (JPG, PNG) or Videos (MP4, WebM)</p>
                        <input type="file" id="proof-files" multiple accept="image/*,video/*" class="hidden" onchange="handleFileSelect(event)">
                    </div>
                    
                    <!-- File Previews -->
                    <div id="file-preview-container" class="flex flex-wrap gap-2 mt-3"></div>
                </div>
                
                <!-- Material Condition -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Material Condition</label>
                    <select id="ack-condition" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="good">Good - All items in perfect condition</option>
                        <option value="minor_damage">Minor Damage - Some items have minor issues</option>
                        <option value="damaged">Damaged - Significant damage to items</option>
                        <option value="missing">Missing Items - Some items are missing</option>
                    </select>
                </div>
                
                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                    <textarea id="ack-notes" rows="3" placeholder="Add any notes about the received materials..."
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                </div>
                
                <!-- Info Box -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-2"></i>
                        <div class="text-sm text-blue-700">
                            <p class="font-medium">Important:</p>
                            <ul class="list-disc list-inside text-xs mt-1 space-y-1">
                                <li>Your acknowledgment will be visible to ADV immediately</li>
                                <li>Photos/videos serve as proof of material condition at receipt</li>
                                <li>Report any discrepancies or damages in the notes</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="closeAcknowledgeModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button type="button" id="ack-submit-btn" onclick="submitAcknowledgment()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-check mr-2"></i>Confirm Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

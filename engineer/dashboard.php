<?php
/**
 * Engineer Dashboard
 * 
 * Dedicated dashboard for engineers showing only their assigned sites
 * No contractor-level statistics - focused on engineer's work
 * 
 * Requirements: 6.1
 * - Display only sites assigned to specific engineer
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/EngineerAssignmentRepository.php';

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
$baseUrl = '..';
$pageTitle = 'Engineer Dashboard';
$currentPage = 'engineer_dashboard';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard']
];

// Get engineer assignment statistics
$assignmentRepo = new EngineerAssignmentRepository();
$counts = $assignmentRepo->countByStatusForEngineer($currentUser['id']);

// Get recent assignments (last 5)
$recentAssignments = $assignmentRepo->findByEngineer($currentUser['id'], [
    'page' => 1,
    'limit' => 5
]);

ob_start();
?>

<!-- Welcome Section -->
<div class="bg-gradient-to-r from-primary to-blue-600 rounded-2xl p-6 mb-6 text-white shadow-lg">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold">Welcome, <?php echo htmlspecialchars($currentUser['first_name'] ?? $currentUser['username']); ?>!</h2>
            <p class="text-blue-100 mt-1">Here's an overview of your assigned sites</p>
        </div>
        <div class="hidden md:block">
            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center">
                <i class="fas fa-hard-hat text-3xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6">
    <!-- Total Assigned Sites -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition cursor-pointer" onclick="window.location.href='sites.php'">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm font-medium">Total Sites</p>
                <p class="text-3xl font-bold text-gray-800 mt-1"><?php echo $counts['total']; ?></p>
                <p class="text-xs text-blue-600 mt-2"><i class="fas fa-map-marker-alt mr-1"></i>Assigned to you</p>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/30">
                <i class="fas fa-map-marker-alt text-white text-xl"></i>
            </div>
        </div>
    </div>
    
    <!-- New Assignments -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition cursor-pointer" onclick="window.location.href='sites.php?status=assigned'">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm font-medium">New</p>
                <p class="text-3xl font-bold text-yellow-600 mt-1"><?php echo $counts['assigned']; ?></p>
                <p class="text-xs text-yellow-600 mt-2"><i class="fas fa-clock mr-1"></i>Awaiting action</p>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-yellow-500 to-amber-600 flex items-center justify-center shadow-lg shadow-yellow-500/30">
                <i class="fas fa-clock text-white text-xl"></i>
            </div>
        </div>
    </div>
    
    <!-- In Progress -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition cursor-pointer" onclick="window.location.href='sites.php?status=in_progress'">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm font-medium">In Progress</p>
                <p class="text-3xl font-bold text-purple-600 mt-1"><?php echo $counts['in_progress']; ?></p>
                <p class="text-xs text-purple-600 mt-2"><i class="fas fa-spinner mr-1"></i>Working on</p>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center shadow-lg shadow-purple-500/30">
                <i class="fas fa-spinner text-white text-xl"></i>
            </div>
        </div>
    </div>
    
    <!-- Completed -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition cursor-pointer" onclick="window.location.href='sites.php?status=completed'">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm font-medium">Completed</p>
                <p class="text-3xl font-bold text-green-600 mt-1"><?php echo $counts['completed']; ?></p>
                <p class="text-xs text-green-600 mt-2"><i class="fas fa-check-circle mr-1"></i>Finished</p>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-green-500/30">
                <i class="fas fa-check-circle text-white text-xl"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Recent Assignments -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">Recent Assignments</h3>
                <a href="sites.php" class="text-sm text-primary hover:underline">View All</a>
            </div>
        </div>
        <div class="p-6">
            <?php if (empty($recentAssignments['data'])): ?>
            <div class="text-center py-8">
                <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-map-marker-alt text-2xl text-gray-400"></i>
                </div>
                <p class="text-gray-500">No sites assigned yet</p>
                <p class="text-sm text-gray-400 mt-1">Sites will appear here once assigned to you</p>
            </div>
            <?php else: ?>
            <div class="space-y-3 max-h-80 overflow-y-auto">
                <?php foreach ($recentAssignments['data'] as $assignment): ?>
                <a href="site_detail.php?id=<?php echo $assignment['id']; ?>" class="flex items-start space-x-4 p-3 rounded-xl hover:bg-gray-50 transition block">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-map-marker-alt text-blue-600"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-800 font-medium"><?php echo htmlspecialchars($assignment['site_name'] ?? 'N/A'); ?></p>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo htmlspecialchars($assignment['lho'] ?? ''); ?> • 
                            <?php echo htmlspecialchars($assignment['city'] ?? ''); ?>, <?php echo htmlspecialchars($assignment['state'] ?? ''); ?>
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <?php
                        $statusColors = [
                            'assigned' => 'bg-yellow-100 text-yellow-700',
                            'in_progress' => 'bg-purple-100 text-purple-700',
                            'completed' => 'bg-green-100 text-green-700'
                        ];
                        $statusColor = $statusColors[$assignment['status']] ?? 'bg-gray-100 text-gray-700';
                        ?>
                        <span class="px-2 py-1 rounded-lg text-xs font-medium <?php echo $statusColor; ?>">
                            <?php echo ucwords(str_replace('_', ' ', $assignment['status'])); ?>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Actions & Profile -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="p-6 text-center border-b border-gray-100">
            <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center mx-auto mb-4 shadow-lg shadow-primary/30">
                <span class="text-3xl font-bold text-white"><?php echo strtoupper(substr($currentUser['first_name'] ?? $currentUser['username'], 0, 1)); ?></span>
            </div>
            <h4 class="text-xl font-semibold text-gray-800">
                <?php echo htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')); ?>
            </h4>
            <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></p>
            <div class="mt-3 flex items-center justify-center space-x-2">
                <span class="px-3 py-1 rounded-lg text-xs font-medium bg-purple-100 text-purple-600">
                    <i class="fas fa-hard-hat mr-1"></i>Engineer
                </span>
            </div>
        </div>
        
        <div class="p-6 space-y-4">
            <div class="flex items-center justify-between py-2">
                <span class="text-gray-500 text-sm">Company</span>
                <span class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($currentUser['company_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-gray-500 text-sm">Active Sites</span>
                <span class="font-medium text-gray-800 text-sm"><?php echo $counts['assigned'] + $counts['in_progress']; ?></span>
            </div>
            
            <div class="pt-4 border-t border-gray-100">
                <p class="text-sm font-medium text-gray-700 mb-3">Quick Actions</p>
                <div class="space-y-2">
                    <a href="sites.php?status=assigned" class="flex items-center p-3 rounded-xl bg-yellow-50 hover:bg-yellow-100 transition">
                        <div class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center mr-3">
                            <i class="fas fa-clock text-yellow-600"></i>
                        </div>
                        <span class="text-sm text-gray-700">View New Assignments</span>
                    </a>
                    <a href="sites.php?status=in_progress" class="flex items-center p-3 rounded-xl bg-purple-50 hover:bg-purple-100 transition">
                        <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center mr-3">
                            <i class="fas fa-spinner text-purple-600"></i>
                        </div>
                        <span class="text-sm text-gray-700">Continue Work</span>
                    </a>
                    <a href="sites.php" class="flex items-center p-3 rounded-xl bg-blue-50 hover:bg-blue-100 transition">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center mr-3">
                            <i class="fas fa-list text-blue-600"></i>
                        </div>
                        <span class="text-sm text-gray-700">View All Sites</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

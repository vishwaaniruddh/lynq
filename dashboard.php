<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);


require_once __DIR__ . '/config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: views/auth/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();

if (isEngineerUser() && !isContractorAdmin()) {
    header('Location: engineer/dashboard.php');
    exit;
}

if (isContractorUser() && !isAdvUser()) {
    header('Location: contractor/dashboard.php');
    exit;
}

$baseUrl = BASE_URL;
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
$isLoggedIn = true;

$db = Database::getInstance()->getConnection();

// Initialize all statistics
$userCount = 0;
$companyCount = 0;
$roleCount = 0;
$activeUsers = 0;
$inactiveUsers = 0;
$users = [];
$companies = [];
$roles = [];

$projectCount = 0;
$activeProjects = 0;

$siteCount = 0;
$activeSites = 0;
$inactiveSites = 0;
$delegationStats = ['total' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0];
$contractorStats = ['total_delegated' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0, 'assigned_to_engineers' => 0];

$ipStats = ['total' => 0, 'available' => 0, 'locked' => 0, 'configured' => 0];

$inventoryStats = [
    'total_products' => 0, 'total_assets' => 0, 'in_stock' => 0, 'dispatched' => 0,
    'under_repair' => 0, 'working' => 0, 'not_working' => 0, 'assigned' => 0, 'scrapped' => 0
];

$dispatchStats = ['total' => 0, 'pending' => 0, 'in_transit' => 0, 'delivered' => 0, 'cancelled' => 0];
$warehouseStats = ['total' => 0, 'active' => 0];
$stockStats = ['total_quantity' => 0, 'low_stock' => 0];
$transferStats = ['total' => 0, 'pending' => 0, 'completed' => 0];
$repairStats = ['total' => 0, 'pending' => 0, 'completed' => 0];
$categoryStats = [];
$recentDispatches = [];
$delegationByContractor = [];

try {
    if (isAdvUser()) {
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE status = 1");
        $userCount = $stmt->fetchColumn();
        $activeUsers = $userCount;
        
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE status = 0");
        $inactiveUsers = $stmt->fetchColumn();
        
        $stmt = $db->query("SELECT COUNT(*) FROM companies WHERE status = 'ACTIVE'");
        $companyCount = $stmt->fetchColumn();

        try {
            $stmt = $db->query("SELECT COUNT(*) FROM projects WHERE status = 1 AND deleted_at IS NULL");
            $projectCount = (int)$stmt->fetchColumn();
            $activeProjects = $projectCount;

            $stmt = $db->prepare("
                SELECT COUNT(*) FROM sites s 
                INNER JOIN projects p ON s.project_id = p.id 
                WHERE s.company_id = ? AND s.status = 'active' AND p.status = 1 AND p.deleted_at IS NULL
            ");
            $stmt->execute([$currentUser['company_id']]);
            $siteCount = (int)$stmt->fetchColumn();
            $activeSites = $siteCount;
            
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM sites s 
                INNER JOIN projects p ON s.project_id = p.id 
                WHERE s.company_id = ? AND s.status = 'inactive' AND p.status = 1 AND p.deleted_at IS NULL
            ");
            $stmt->execute([$currentUser['company_id']]);
            $inactiveSites = (int)$stmt->fetchColumn();

            $stmt = $db->prepare("
                SELECT COUNT(*) as total,
                    SUM(CASE WHEN sd.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN sd.status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN sd.status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM site_delegations sd 
                INNER JOIN sites s ON sd.site_id = s.id 
                INNER JOIN projects p ON s.project_id = p.id 
                WHERE s.company_id = ? AND p.status = 1 AND p.deleted_at IS NULL
            ");
            $stmt->execute([$currentUser['company_id']]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($stats) {
                $delegationStats = ['total' => (int)$stats['total'], 'pending' => (int)$stats['pending'], 
                    'accepted' => (int)$stats['accepted'], 'rejected' => (int)$stats['rejected']];
            }
            
            $stmt = $db->prepare("
                SELECT c.name as contractor_name, COUNT(*) as count
                FROM site_delegations sd 
                INNER JOIN sites s ON sd.site_id = s.id
                INNER JOIN projects p ON s.project_id = p.id
                INNER JOIN companies c ON sd.contractor_id = c.id
                WHERE s.company_id = ? AND p.status = 1 AND p.deleted_at IS NULL 
                GROUP BY sd.contractor_id ORDER BY count DESC LIMIT 5
            ");
            $stmt->execute([$currentUser['company_id']]);
            $delegationByContractor = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        try {
            $stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = 'locked' THEN 1 ELSE 0 END) as locked,
                SUM(CASE WHEN status = 'configured' THEN 1 ELSE 0 END) as configured FROM ip_master");
            $ipStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $ipStats;
        } catch (Exception $e) {}

        try {
            $stmt = $db->query("SELECT COUNT(*) FROM products WHERE status = 'active'");
            $inventoryStats['total_products'] = (int)$stmt->fetchColumn();
            
            $stmt = $db->query("SELECT COUNT(*) as total,
                SUM(CASE WHEN status = 'in_stock' THEN 1 ELSE 0 END) as in_stock,
                SUM(CASE WHEN status = 'dispatched' THEN 1 ELSE 0 END) as dispatched,
                SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned,
                SUM(CASE WHEN status = 'under_repair' THEN 1 ELSE 0 END) as under_repair,
                SUM(CASE WHEN status = 'scrapped' THEN 1 ELSE 0 END) as scrapped,
                SUM(CASE WHEN working_condition = 'working' THEN 1 ELSE 0 END) as working,
                SUM(CASE WHEN working_condition = 'not_working' THEN 1 ELSE 0 END) as not_working FROM assets");
            $assetStats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($assetStats) {
                $inventoryStats = array_merge($inventoryStats, [
                    'total_assets' => (int)$assetStats['total'], 'in_stock' => (int)$assetStats['in_stock'],
                    'dispatched' => (int)$assetStats['dispatched'], 'assigned' => (int)$assetStats['assigned'],
                    'under_repair' => (int)$assetStats['under_repair'], 'scrapped' => (int)$assetStats['scrapped'],
                    'working' => (int)$assetStats['working'], 'not_working' => (int)$assetStats['not_working']
                ]);
            }
            
            $stmt = $db->query("SELECT SUM(quantity) as total FROM stock");
            $stockStats['total_quantity'] = (int)$stmt->fetchColumn();
            
            $stmt = $db->query("SELECT COUNT(*) FROM stock WHERE quantity < 10");
            $stockStats['low_stock'] = (int)$stmt->fetchColumn();
            
            $stmt = $db->query("SELECT pc.name, COUNT(p.id) as count FROM product_categories pc 
                LEFT JOIN products p ON pc.id = p.category_id WHERE p.status = 'active' 
                GROUP BY pc.id ORDER BY count DESC LIMIT 5");
            $categoryStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        try {
            $stmt = $db->query("SELECT COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_transit' THEN 1 ELSE 0 END) as in_transit,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled FROM dispatches");
            $dispatchStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $dispatchStats;
            
            $stmt = $db->query("SELECT d.*, w.name as warehouse_name FROM dispatches d 
                LEFT JOIN warehouses w ON d.from_warehouse_id = w.id ORDER BY d.created_at DESC LIMIT 5");
            $recentDispatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        try {
            $stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active FROM warehouses");
            $warehouseStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $warehouseStats;
        } catch (Exception $e) {}

        try {
            $stmt = $db->query("SELECT COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed FROM transfers");
            $transferStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $transferStats;
        } catch (Exception $e) {}

        try {
            $stmt = $db->query("SELECT COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed FROM repairs");
            $repairStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $repairStats;
        } catch (Exception $e) {}

        $stmt = $db->query("SELECT u.*, c.name as company_name, r.name as role_name FROM users u 
            LEFT JOIN companies c ON u.company_id = c.id LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.status = 1 ORDER BY u.created_at DESC LIMIT 5");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $db->query("SELECT * FROM companies WHERE status = 'ACTIVE' ORDER BY name LIMIT 5");
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE company_id = ? AND status = 1");
        $stmt->execute([$currentUser['company_id']]);
        $userCount = $stmt->fetchColumn();
        $companyCount = 1;
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as total,
                SUM(CASE WHEN sd.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN sd.status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN sd.status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM site_delegations sd
                INNER JOIN sites s ON sd.site_id = s.id
                INNER JOIN projects p ON s.project_id = p.id
                WHERE sd.contractor_id = ? AND p.status = 1 AND p.deleted_at IS NULL");
            $stmt->execute([$currentUser['company_id']]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($stats) {
                $contractorStats = ['total_delegated' => (int)$stats['total'], 'pending' => (int)$stats['pending'],
                    'accepted' => (int)$stats['accepted'], 'rejected' => (int)$stats['rejected']];
            }
            
            $stmt = $db->prepare("SELECT COUNT(DISTINCT ea.site_id) FROM engineer_assignments ea
                INNER JOIN site_delegations sd ON ea.delegation_id = sd.id
                INNER JOIN sites s ON sd.site_id = s.id
                INNER JOIN projects p ON s.project_id = p.id
                WHERE sd.contractor_id = ? AND p.status = 1 AND p.deleted_at IS NULL");
            $stmt->execute([$currentUser['company_id']]);
            $contractorStats['assigned_to_engineers'] = (int)$stmt->fetchColumn();
        } catch (Exception $e) {}

        $stmt = $db->prepare("SELECT u.*, c.name as company_name, r.name as role_name FROM users u 
            LEFT JOIN companies c ON u.company_id = c.id LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.company_id = ? AND u.status = 1 ORDER BY u.created_at DESC LIMIT 5");
        $stmt->execute([$currentUser['company_id']]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $stmt = $db->query("SELECT COUNT(*) FROM roles");
    $roleCount = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT * FROM roles ORDER BY level DESC LIMIT 5");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$recentActivity = [];
try {
    if (isAdvUser()) {
        $stmt = $db->query("SELECT ual.*, u.username as performed_by_name FROM user_audit_log ual 
            LEFT JOIN users u ON ual.performed_by = u.id ORDER BY ual.timestamp DESC LIMIT 8");
    } else {
        $stmt = $db->prepare("SELECT ual.*, u.username as performed_by_name FROM user_audit_log ual 
            LEFT JOIN users u ON ual.performed_by = u.id 
            WHERE ual.performed_by IN (SELECT id FROM users WHERE company_id = ?)
            ORDER BY ual.timestamp DESC LIMIT 8");
        $stmt->execute([$currentUser['company_id']]);
    }
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* Custom CSS Design System & Micro-animations */
.card-hover {
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}
.card-hover:hover {
    transform: translateY(-5px) scale(1.015);
    box-shadow: 0 20px 30px -10px rgba(79, 70, 229, 0.18), 0 10px 15px -5px rgba(0, 0, 0, 0.05);
}

.card-hover-subtle {
    transition: all 0.3s ease;
}
.card-hover-subtle:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.1);
}

/* Glassmorphism Classes */
.glass-panel {
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(229, 231, 235, 0.8);
}

.glass-card-dark {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

/* Gradient Tokens */
.grad-indigo { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); }
.grad-cyan { background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%); }
.grad-emerald { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.grad-rose { background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%); }
.grad-amber { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.grad-purple { background: linear-gradient(135deg, #8b5cf6 0%, #d946ef 100%); }
.grad-sunset { background: linear-gradient(135deg, #ff7e5f 0%, #feb47b 100%); }
.grad-dark { background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%); }
.grad-blue-violet { background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); }
.grad-teal-cyan { background: linear-gradient(135deg, #0d9488 0%, #06b6d4 100%); }
.grad-orange-red { background: linear-gradient(135deg, #f97316 0%, #ef4444 100%); }

/* Shimmer Effect */
.shimmer-bg {
    position: relative;
    overflow: hidden;
}
.shimmer-bg::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        60deg,
        transparent 30%,
        rgba(255, 255, 255, 0.15) 50%,
        transparent 70%
    );
    transform: rotate(30deg);
    animation: shimmer 6s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%) rotate(30deg); }
    20% { transform: translateX(100%) rotate(30deg); }
    100% { transform: translateX(100%) rotate(30deg); }
}

/* Chart Canvas Wrapper */
.chart-container-adv {
    position: relative;
    height: 180px;
    width: 100%;
}
</style>

<?php if (isAdvUser()): ?>

<!-- Hero Telemetry Header Banner -->
<div class="relative overflow-hidden rounded-2xl grad-dark p-6 mb-6 text-white shadow-xl shimmer-bg">
    <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute right-40 -top-10 w-48 h-48 bg-cyan-500/20 rounded-full blur-2xl pointer-events-none"></div>
    
    <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-3 mb-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 backdrop-blur-md">
                    <span class="w-2 h-2 mr-2 rounded-full bg-emerald-400 animate-pulse"></span>System Operational
                </span>
                <span class="text-xs text-slate-300/80 flex items-center">
                    <i class="far fa-clock mr-1 text-indigo-400"></i>
                    <span id="live-time">--:--:--</span>
                </span>
            </div>
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-white via-slate-100 to-indigo-200">
                Welcome back, <?php echo htmlspecialchars($currentUser['username']); ?> 👋
            </h1>
            <p class="text-xs md:text-sm text-slate-300 mt-1 max-w-xl">
                Real-time operational network telemetry, inventory flows, site delegations, and asset performance metrics.
            </p>
        </div>
        
        <div class="flex flex-wrap items-center gap-2 bg-white/10 p-2.5 rounded-xl border border-white/10 backdrop-blur-md">
            <div class="text-center px-3 py-1 border-r border-white/10">
                <p class="text-[10px] uppercase text-slate-300 font-semibold tracking-wider">Projects</p>
                <p class="text-lg font-bold text-amber-300"><?php echo $activeProjects; ?></p>
            </div>
            <div class="text-center px-3 py-1 border-r border-white/10">
                <p class="text-[10px] uppercase text-slate-300 font-semibold tracking-wider">Active Sites</p>
                <p class="text-lg font-bold text-emerald-400"><?php echo $activeSites; ?></p>
            </div>
            <div class="text-center px-3 py-1 border-r border-white/10">
                <p class="text-[10px] uppercase text-slate-300 font-semibold tracking-wider">Dispatches</p>
                <p class="text-lg font-bold text-cyan-400"><?php echo (int)$dispatchStats['total']; ?></p>
            </div>
            <div class="text-center px-3 py-1">
                <p class="text-[10px] uppercase text-slate-300 font-semibold tracking-wider">Total Assets</p>
                <p class="text-lg font-bold text-indigo-300"><?php echo $inventoryStats['total_assets']; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Primary Interactive Gradient Stat Cards Grid (9 Columns Responsive) -->
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 xl:grid-cols-9 gap-3.5 mb-6">
    <!-- Projects Card -->
    <div class="card-hover grad-amber text-white rounded-2xl p-3.5 shadow-lg cursor-pointer relative overflow-hidden group" onclick="window.location='masters/projects.php'">
        <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-white/10 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-layer-group text-white text-xs"></i>
            </div>
            <span class="text-[10px] font-bold bg-white/20 text-white px-2 py-0.5 rounded-full backdrop-blur-md">
                <?php echo $activeProjects; ?> active
            </span>
        </div>
        <p class="text-2xl font-black tracking-tight"><?php echo $projectCount; ?></p>
        <p class="text-[10px] font-semibold text-white/80 uppercase tracking-wider mt-0.5">Projects</p>
    </div>

    <!-- Sites Card -->
    <div class="card-hover grad-indigo text-white rounded-2xl p-3.5 shadow-lg cursor-pointer relative overflow-hidden group" onclick="window.location='sites/index.php'">
        <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-white/10 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-map-marker-alt text-white text-xs"></i>
            </div>
            <span class="text-[10px] font-bold bg-white/20 text-white px-2 py-0.5 rounded-full backdrop-blur-md">
                <?php echo $activeSites; ?> active
            </span>
        </div>
        <p class="text-2xl font-black tracking-tight"><?php echo $siteCount + $inactiveSites; ?></p>
        <p class="text-[10px] font-semibold text-white/80 uppercase tracking-wider mt-0.5">Sites</p>
    </div>
    
    <!-- Delegations Card -->
    <div class="card-hover grad-cyan text-white rounded-2xl p-3.5 shadow-lg cursor-pointer relative overflow-hidden group" onclick="window.location='delegations/index.php'">
        <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-white/10 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-share-alt text-white text-xs"></i>
            </div>
            <span class="text-[10px] font-bold bg-white/20 text-white px-2 py-0.5 rounded-full backdrop-blur-md">
                <?php echo $delegationStats['pending']; ?> pend
            </span>
        </div>
        <p class="text-2xl font-black tracking-tight"><?php echo $delegationStats['total']; ?></p>
        <p class="text-[10px] font-semibold text-white/80 uppercase tracking-wider mt-0.5">Delegations</p>
    </div>
    
    <!-- Users Card -->
    <div class="card-hover grad-emerald text-white rounded-2xl p-3.5 shadow-lg cursor-pointer relative overflow-hidden group" onclick="showModal('usersModal')">
        <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-white/10 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-users text-white text-xs"></i>
            </div>
            <span class="text-[10px] font-bold bg-white/20 text-white px-2 py-0.5 rounded-full backdrop-blur-md">
                <?php echo $activeUsers; ?> active
            </span>
        </div>
        <p class="text-2xl font-black tracking-tight"><?php echo $userCount; ?></p>
        <p class="text-[10px] font-semibold text-white/80 uppercase tracking-wider mt-0.5">Users</p>
    </div>
    
    <!-- Companies Card -->
    <div class="card-hover grad-purple text-white rounded-2xl p-3.5 shadow-lg cursor-pointer relative overflow-hidden group" onclick="showModal('companiesModal')">
        <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-white/10 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-building text-white text-xs"></i>
            </div>
            <span class="text-[10px] font-bold bg-white/20 text-white px-2 py-0.5 rounded-full backdrop-blur-md">
                View
            </span>
        </div>
        <p class="text-2xl font-black tracking-tight"><?php echo $companyCount; ?></p>
        <p class="text-[10px] font-semibold text-white/80 uppercase tracking-wider mt-0.5">Companies</p>
    </div>
    
    <!-- Products Card -->
    <div class="card-hover grad-sunset text-white rounded-2xl p-3.5 shadow-lg cursor-pointer relative overflow-hidden group" onclick="window.location='inventory/products.php'">
        <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-white/10 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-box-open text-white text-xs"></i>
            </div>
            <span class="text-[10px] font-bold bg-white/20 text-white px-2 py-0.5 rounded-full backdrop-blur-md">
                Catalog
            </span>
        </div>
        <p class="text-2xl font-black tracking-tight"><?php echo $inventoryStats['total_products']; ?></p>
        <p class="text-[10px] font-semibold text-white/80 uppercase tracking-wider mt-0.5">Products</p>
    </div>
    
    <!-- Assets Card -->
    <div class="card-hover grad-blue-violet text-white rounded-2xl p-3.5 shadow-lg cursor-pointer relative overflow-hidden group" onclick="window.location='inventory/assets.php'">
        <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-white/10 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-microchip text-white text-xs"></i>
            </div>
            <span class="text-[10px] font-bold bg-white/20 text-white px-2 py-0.5 rounded-full backdrop-blur-md">
                <?php echo $inventoryStats['working']; ?> ok
            </span>
        </div>
        <p class="text-2xl font-black tracking-tight"><?php echo $inventoryStats['total_assets']; ?></p>
        <p class="text-[10px] font-semibold text-white/80 uppercase tracking-wider mt-0.5">Assets</p>
    </div>
    
    <!-- Warehouses Card -->
    <div class="card-hover grad-teal-cyan text-white rounded-2xl p-3.5 shadow-lg cursor-pointer relative overflow-hidden group" onclick="window.location='inventory/warehouses.php'">
        <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-white/10 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-warehouse text-white text-xs"></i>
            </div>
            <span class="text-[10px] font-bold bg-white/20 text-white px-2 py-0.5 rounded-full backdrop-blur-md">
                <?php echo (int)($warehouseStats['active'] ?? 0); ?> active
            </span>
        </div>
        <p class="text-2xl font-black tracking-tight"><?php echo (int)($warehouseStats['total'] ?? 0); ?></p>
        <p class="text-[10px] font-semibold text-white/80 uppercase tracking-wider mt-0.5">Warehouses</p>
    </div>
    
    <!-- IP Configs Card -->
    <div class="card-hover grad-rose text-white rounded-2xl p-3.5 shadow-lg cursor-pointer relative overflow-hidden group" onclick="window.location='configuration/ip_master.php'">
        <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-white/10 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-network-wired text-white text-xs"></i>
            </div>
            <span class="text-[10px] font-bold bg-white/20 text-white px-2 py-0.5 rounded-full backdrop-blur-md">
                <?php echo (int)($ipStats['available'] ?? 0); ?> free
            </span>
        </div>
        <p class="text-2xl font-black tracking-tight"><?php echo (int)($ipStats['total'] ?? 0); ?></p>
        <p class="text-[10px] font-semibold text-white/80 uppercase tracking-wider mt-0.5">IP Master</p>
    </div>
</div>

<!-- Secondary KPI Stat Cards & Visual Breakdown -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Delegation Overview Panel -->
    <div class="glass-panel rounded-2xl p-4 shadow-sm hover:shadow-md transition">
        <div class="flex items-center justify-between mb-3 border-b border-gray-100 pb-2">
            <div class="flex items-center space-x-2">
                <div class="w-6 h-6 rounded-lg bg-indigo-100 flex items-center justify-center">
                    <i class="fas fa-sitemap text-indigo-600 text-xs"></i>
                </div>
                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Delegation State</h4>
            </div>
            <a href="delegations/index.php" class="text-[10px] font-semibold text-indigo-600 hover:text-indigo-800 transition">View All →</a>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center mb-3">
            <div class="p-2 bg-amber-50/80 rounded-xl border border-amber-100">
                <p class="text-lg font-black text-amber-600"><?php echo $delegationStats['pending']; ?></p>
                <p class="text-[10px] font-semibold text-gray-500">Pending</p>
            </div>
            <div class="p-2 bg-emerald-50/80 rounded-xl border border-emerald-100">
                <p class="text-lg font-black text-emerald-600"><?php echo $delegationStats['accepted']; ?></p>
                <p class="text-[10px] font-semibold text-gray-500">Accepted</p>
            </div>
            <div class="p-2 bg-rose-50/80 rounded-xl border border-rose-100">
                <p class="text-lg font-black text-rose-600"><?php echo $delegationStats['rejected']; ?></p>
                <p class="text-[10px] font-semibold text-gray-500">Rejected</p>
            </div>
        </div>
        <?php 
        $delTotal = max(1, $delegationStats['total']);
        $accPct = round(($delegationStats['accepted'] / $delTotal) * 100);
        ?>
        <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden flex">
            <div class="bg-amber-400 h-full" style="width: <?php echo round(($delegationStats['pending']/$delTotal)*100); ?>%"></div>
            <div class="bg-emerald-500 h-full" style="width: <?php echo $accPct; ?>%"></div>
            <div class="bg-rose-500 h-full" style="width: <?php echo round(($delegationStats['rejected']/$delTotal)*100); ?>%"></div>
        </div>
        <p class="text-[10px] text-gray-400 text-right mt-1 font-medium"><?php echo $accPct; ?>% Acceptance Rate</p>
    </div>

    <!-- Dispatch Pipeline Panel -->
    <div class="glass-panel rounded-2xl p-4 shadow-sm hover:shadow-md transition">
        <div class="flex items-center justify-between mb-3 border-b border-gray-100 pb-2">
            <div class="flex items-center space-x-2">
                <div class="w-6 h-6 rounded-lg bg-cyan-100 flex items-center justify-center">
                    <i class="fas fa-truck-loading text-cyan-600 text-xs"></i>
                </div>
                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Dispatch Pipeline</h4>
            </div>
            <a href="inventory/dispatches/index.php" class="text-[10px] font-semibold text-cyan-600 hover:text-cyan-800 transition">View All →</a>
        </div>
        <div class="grid grid-cols-4 gap-1.5 text-center">
            <div class="p-2 bg-amber-50/80 rounded-xl border border-amber-100">
                <p class="text-base font-black text-amber-600"><?php echo (int)$dispatchStats['pending']; ?></p>
                <p class="text-[9px] font-semibold text-gray-500">Pending</p>
            </div>
            <div class="p-2 bg-blue-50/80 rounded-xl border border-blue-100">
                <p class="text-base font-black text-blue-600"><?php echo (int)$dispatchStats['in_transit']; ?></p>
                <p class="text-[9px] font-semibold text-gray-500">Transit</p>
            </div>
            <div class="p-2 bg-emerald-50/80 rounded-xl border border-emerald-100">
                <p class="text-base font-black text-emerald-600"><?php echo (int)$dispatchStats['delivered']; ?></p>
                <p class="text-[9px] font-semibold text-gray-500">Delivered</p>
            </div>
            <div class="p-2 bg-rose-50/80 rounded-xl border border-rose-100">
                <p class="text-base font-black text-rose-600"><?php echo (int)$dispatchStats['cancelled']; ?></p>
                <p class="text-[9px] font-semibold text-gray-500">Cancel</p>
            </div>
        </div>
    </div>

    <!-- IP Config Allocation Panel -->
    <div class="glass-panel rounded-2xl p-4 shadow-sm hover:shadow-md transition">
        <div class="flex items-center justify-between mb-3 border-b border-gray-100 pb-2">
            <div class="flex items-center space-x-2">
                <div class="w-6 h-6 rounded-lg bg-emerald-100 flex items-center justify-center">
                    <i class="fas fa-network-wired text-emerald-600 text-xs"></i>
                </div>
                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">IP Master Allocation</h4>
            </div>
            <a href="configuration/ip_master.php" class="text-[10px] font-semibold text-emerald-600 hover:text-emerald-800 transition">Manage →</a>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center">
            <div class="p-2 bg-emerald-50/80 rounded-xl border border-emerald-100">
                <p class="text-lg font-black text-emerald-600"><?php echo (int)$ipStats['available']; ?></p>
                <p class="text-[10px] font-semibold text-gray-500">Available</p>
            </div>
            <div class="p-2 bg-amber-50/80 rounded-xl border border-amber-100">
                <p class="text-lg font-black text-amber-600"><?php echo (int)$ipStats['locked']; ?></p>
                <p class="text-[10px] font-semibold text-gray-500">Locked</p>
            </div>
            <div class="p-2 bg-indigo-50/80 rounded-xl border border-indigo-100">
                <p class="text-lg font-black text-indigo-600"><?php echo (int)$ipStats['configured']; ?></p>
                <p class="text-[10px] font-semibold text-gray-500">Configured</p>
            </div>
        </div>
    </div>

    <!-- Stock Monitor & Low Stock Alert Panel -->
    <div class="glass-panel rounded-2xl p-4 shadow-sm hover:shadow-md transition">
        <div class="flex items-center justify-between mb-3 border-b border-gray-100 pb-2">
            <div class="flex items-center space-x-2">
                <div class="w-6 h-6 rounded-lg bg-purple-100 flex items-center justify-center">
                    <i class="fas fa-cubes text-purple-600 text-xs"></i>
                </div>
                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Stock Health</h4>
            </div>
            <a href="inventory/stock.php" class="text-[10px] font-semibold text-purple-600 hover:text-purple-800 transition">Stock Hub →</a>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center">
            <div class="p-2 bg-indigo-50/80 rounded-xl border border-indigo-100">
                <p class="text-lg font-black text-indigo-600"><?php echo $stockStats['total_quantity']; ?></p>
                <p class="text-[10px] font-semibold text-gray-500">Total Qty</p>
            </div>
            <div class="p-2 bg-orange-50/80 rounded-xl border border-orange-100 relative">
                <?php if ($stockStats['low_stock'] > 0): ?>
                    <span class="absolute -top-1 -right-1 flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                    </span>
                <?php endif; ?>
                <p class="text-lg font-black text-orange-600"><?php echo $stockStats['low_stock']; ?></p>
                <p class="text-[10px] font-semibold text-gray-500">Low Stock</p>
            </div>
            <div class="p-2 bg-purple-50/80 rounded-xl border border-purple-100">
                <p class="text-lg font-black text-purple-600"><?php echo $inventoryStats['in_stock']; ?></p>
                <p class="text-[10px] font-semibold text-gray-500">In Stock</p>
            </div>
        </div>
    </div>
</div>

<!-- Advanced Interactive Visualizations (Chart.js 2x2 Grid) -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Chart 1: Delegations by Contractor (Bar with Gradient Fill) -->
    <div class="glass-panel rounded-2xl p-4 shadow-sm hover:shadow-lg transition">
        <div class="flex items-center justify-between mb-2">
            <div>
                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Contractor Delegations</h4>
                <p class="text-[10px] text-gray-400 font-medium">Top delegated partners</p>
            </div>
            <span class="p-1.5 bg-indigo-50 rounded-lg text-indigo-600 text-xs"><i class="fas fa-chart-bar"></i></span>
        </div>
        <div class="chart-container-adv">
            <canvas id="delegationChart"></canvas>
        </div>
    </div>
    
    <!-- Chart 2: Asset Status Breakdown (Cutout Donut with Center Total Text) -->
    <div class="glass-panel rounded-2xl p-4 shadow-sm hover:shadow-lg transition">
        <div class="flex items-center justify-between mb-2">
            <div>
                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Asset Distribution</h4>
                <p class="text-[10px] text-gray-400 font-medium">Status allocation</p>
            </div>
            <span class="p-1.5 bg-emerald-50 rounded-lg text-emerald-600 text-xs"><i class="fas fa-chart-pie"></i></span>
        </div>
        <div class="chart-container-adv">
            <canvas id="assetChart"></canvas>
        </div>
    </div>
    
    <!-- Chart 3: Asset Condition Health (Working vs Repair Donut) -->
    <div class="glass-panel rounded-2xl p-4 shadow-sm hover:shadow-lg transition">
        <div class="flex items-center justify-between mb-2">
            <div>
                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Condition Ratio</h4>
                <p class="text-[10px] text-gray-400 font-medium">Operational vs Defective</p>
            </div>
            <span class="p-1.5 bg-purple-50 rounded-lg text-purple-600 text-xs"><i class="fas fa-heartbeat"></i></span>
        </div>
        <div class="chart-container-adv">
            <canvas id="conditionChart"></canvas>
        </div>
    </div>
    
    <!-- Chart 4: Dispatch Performance Area Line Chart -->
    <div class="glass-panel rounded-2xl p-4 shadow-sm hover:shadow-lg transition">
        <div class="flex items-center justify-between mb-2">
            <div>
                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Dispatch Overview</h4>
                <p class="text-[10px] text-gray-400 font-medium">Logistics status flow</p>
            </div>
            <span class="p-1.5 bg-cyan-50 rounded-lg text-cyan-600 text-xs"><i class="fas fa-chart-line"></i></span>
        </div>
        <div class="chart-container-adv">
            <canvas id="dispatchChart"></canvas>
        </div>
    </div>
</div>

<!-- Vibrant Full-Gradient Logistics Summary Row (6 Columns) -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5 mb-6">
    <div class="card-hover grad-rose rounded-2xl p-4 text-white shadow-lg relative overflow-hidden group">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-2xl font-black tracking-tight"><?php echo $inventoryStats['dispatched']; ?></p>
                <p class="text-[10px] text-white/80 font-bold uppercase tracking-wider mt-0.5">Dispatched</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-truck text-white text-sm"></i>
            </div>
        </div>
    </div>
    
    <div class="card-hover grad-amber rounded-2xl p-4 text-white shadow-lg relative overflow-hidden group">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-2xl font-black tracking-tight"><?php echo $inventoryStats['under_repair']; ?></p>
                <p class="text-[10px] text-white/80 font-bold uppercase tracking-wider mt-0.5">Under Repair</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-tools text-white text-sm"></i>
            </div>
        </div>
    </div>
    
    <div class="card-hover grad-orange-red rounded-2xl p-4 text-white shadow-lg relative overflow-hidden group">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-2xl font-black tracking-tight"><?php echo $inventoryStats['not_working']; ?></p>
                <p class="text-[10px] text-white/80 font-bold uppercase tracking-wider mt-0.5">Defective</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-exclamation-triangle text-white text-sm"></i>
            </div>
        </div>
    </div>
    
    <div class="card-hover grad-blue-violet rounded-2xl p-4 text-white shadow-lg relative overflow-hidden group">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-2xl font-black tracking-tight"><?php echo (int)($transferStats['total'] ?? 0); ?></p>
                <p class="text-[10px] text-white/80 font-bold uppercase tracking-wider mt-0.5">Transfers</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-exchange-alt text-white text-sm"></i>
            </div>
        </div>
    </div>
    
    <div class="card-hover grad-teal-cyan rounded-2xl p-4 text-white shadow-lg relative overflow-hidden group">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-2xl font-black tracking-tight"><?php echo (int)($repairStats['total'] ?? 0); ?></p>
                <p class="text-[10px] text-white/80 font-bold uppercase tracking-wider mt-0.5">Repairs</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-wrench text-white text-sm"></i>
            </div>
        </div>
    </div>
    
    <div class="card-hover grad-dark rounded-2xl p-4 text-white shadow-lg relative overflow-hidden group">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-2xl font-black tracking-tight"><?php echo $inventoryStats['scrapped']; ?></p>
                <p class="text-[10px] text-white/80 font-bold uppercase tracking-wider mt-0.5">Scrapped</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
                <i class="fas fa-trash text-white text-sm"></i>
            </div>
        </div>
    </div>
</div>

<!-- Activity Stream & Quick Action Hub (3 Columns Layout) -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Recent Activity Stream (2 Columns) -->
    <div class="lg:col-span-2 glass-panel rounded-2xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 mb-3 border-b border-gray-100 gap-2">
            <div class="flex items-center space-x-2">
                <div class="w-7 h-7 rounded-lg grad-indigo flex items-center justify-center text-white text-xs">
                    <i class="fas fa-history"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-800">System Activity Stream</h3>
                    <p class="text-[10px] text-gray-400 font-medium">Real-time user operational audit log</p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <input type="text" placeholder="Search activity..." oninput="filterActivity(this.value)" 
                    class="px-2.5 py-1 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50/50">
                <?php if (can('system.audit')): ?>
                    <a href="permissions/audit.php" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition whitespace-nowrap">View All →</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php
        if (!function_exists('renderAuditDetailsTabular')) {
            function renderAuditDetailsTabular($jsonStr, $action) {
                if (empty($jsonStr)) {
                    return '<span class="text-gray-400 italic text-[11px]">No details</span>';
                }
                $data = json_decode($jsonStr, true);
                if (!is_array($data)) {
                    return '<span class="text-gray-600 font-mono text-[10px]">' . htmlspecialchars(substr($jsonStr, 0, 60)) . '</span>';
                }

                $items = [];
                
                // Primary Entity Target
                if (!empty($data['new_name'])) {
                    $items[] = '<span class="font-bold text-gray-800">' . htmlspecialchars($data['new_name']) . '</span>';
                } elseif (!empty($data['old_name'])) {
                    $items[] = '<span class="font-bold text-gray-800">' . htmlspecialchars($data['old_name']) . '</span>';
                } elseif (!empty($data['lho_name'])) {
                    $items[] = '<span class="font-bold text-gray-800">' . htmlspecialchars($data['lho_name']) . '</span>';
                } elseif (!empty($data['site_id'])) {
                    $items[] = '<span class="font-semibold text-indigo-700">Site #' . (int)$data['site_id'] . '</span>';
                } elseif (!empty($data['entity_id'])) {
                    $items[] = '<span class="font-semibold text-purple-700">Entity #' . (int)$data['entity_id'] . '</span>';
                }

                // Field Changes List
                if (!empty($data['changes']) && is_array($data['changes'])) {
                    $badges = array_map(function($field) {
                        return '<span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 text-[9px] font-mono border border-slate-200">' . htmlspecialchars(str_replace('_', ' ', $field)) . '</span>';
                    }, array_slice($data['changes'], 0, 3));
                    
                    $moreCount = count($data['changes']) - 3;
                    $moreBadge = $moreCount > 0 ? '<span class="text-[9px] text-gray-400 font-medium">+' . $moreCount . ' more</span>' : '';
                    
                    $items[] = '<div class="inline-flex flex-wrap items-center gap-1">' . implode(' ', $badges) . ' ' . $moreBadge . '</div>';
                } else {
                    $keyPairs = [];
                    foreach ($data as $k => $v) {
                        if (in_array($k, ['changes', 'site_id', 'entity_id', 'new_name', 'old_name', 'lho_name'])) continue;
                        if (is_scalar($v) && !empty($v)) {
                            $keyPairs[] = '<span class="text-[10px] text-gray-500"><b class="text-gray-700">' . htmlspecialchars($k) . ':</b> ' . htmlspecialchars($v) . '</span>';
                        }
                        if (count($keyPairs) >= 2) break;
                    }
                    if (!empty($keyPairs)) {
                        $items[] = implode(' • ', $keyPairs);
                    }
                }

                return empty($items) ? '<span class="text-gray-400 italic text-[11px]">Empty payload</span>' : implode(' — ', $items);
            }
        }
        ?>

        <div class="overflow-x-auto max-h-72 overflow-y-auto pr-1 rounded-xl border border-gray-100">
            <?php if (empty($recentActivity)): ?>
                <div class="text-center py-10">
                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-2 text-gray-400">
                        <i class="fas fa-history text-xl"></i>
                    </div>
                    <p class="text-gray-500 text-xs font-medium">No recent activity recorded</p>
                </div>
            <?php else: ?>
                <table class="w-full text-xs text-left" id="activityTable">
                    <thead class="bg-gray-50/90 text-gray-500 uppercase text-[10px] font-bold tracking-wider sticky top-0 backdrop-blur-md border-b border-gray-100 z-10">
                        <tr>
                            <th class="px-3 py-2.5">User</th>
                            <th class="px-3 py-2.5">Action</th>
                            <th class="px-3 py-2.5">Target / Changes</th>
                            <th class="px-3 py-2.5 text-right">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($recentActivity as $activity): 
                            $actLower = strtolower($activity['action']);
                            $badgeStyle = 'bg-slate-100 text-slate-700 border-slate-200';
                            if (strpos($actLower, 'create') !== false || strpos($actLower, 'add') !== false) {
                                $badgeStyle = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                            } elseif (strpos($actLower, 'update') !== false || strpos($actLower, 'edit') !== false) {
                                $badgeStyle = 'bg-indigo-50 text-indigo-700 border-indigo-200';
                            } elseif (strpos($actLower, 'delete') !== false || strpos($actLower, 'remove') !== false) {
                                $badgeStyle = 'bg-rose-50 text-rose-700 border-rose-200';
                            } elseif (strpos($actLower, 'login') !== false) {
                                $badgeStyle = 'bg-cyan-50 text-cyan-700 border-cyan-200';
                            }
                        ?>
                        <tr class="activity-row hover:bg-gray-50/80 transition">
                            <td class="px-3 py-2.5 whitespace-nowrap font-medium text-gray-800">
                                <div class="flex items-center space-x-2">
                                    <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-[10px]">
                                        <?php echo strtoupper(substr($activity['performed_by_name'] ?? 'S', 0, 1)); ?>
                                    </div>
                                    <span class="text-xs font-semibold"><?php echo htmlspecialchars($activity['performed_by_name'] ?? 'System'); ?></span>
                                </div>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border <?php echo $badgeStyle; ?> inline-block">
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $activity['action']))); ?>
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-gray-600">
                                <?php echo renderAuditDetailsTabular($activity['details'] ?? '', $activity['action']); ?>
                            </td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap text-[10px] font-medium text-gray-400">
                                <?php echo date('M d, H:i', strtotime($activity['timestamp'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    
    <!-- Quick Actions Dock & Profile Card (1 Column) -->
    <div class="space-y-4">
        <!-- Quick Action Dock -->
        <div class="glass-panel rounded-2xl shadow-sm border border-gray-100 p-5">
            <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider mb-3">Quick Actions Dock</h4>
            <div class="grid grid-cols-2 gap-2.5">
                <?php if (can('sites.create')): ?>
                <a href="sites/site_add_custome_form.php" class="flex items-center p-2.5 rounded-xl bg-indigo-50/80 hover:bg-indigo-100/80 text-indigo-700 transition group border border-indigo-100">
                    <div class="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center mr-2 shadow-sm group-hover:scale-110 transition">
                        <i class="fas fa-plus text-xs"></i>
                    </div>
                    <span class="text-xs font-bold">Add Site</span>
                </a>
                <?php endif; ?>
                
                <?php if (can('users.create')): ?>
                <a href="users/create.php" class="flex items-center p-2.5 rounded-xl bg-cyan-50/80 hover:bg-cyan-100/80 text-cyan-700 transition group border border-cyan-100">
                    <div class="w-7 h-7 rounded-lg bg-cyan-600 text-white flex items-center justify-center mr-2 shadow-sm group-hover:scale-110 transition">
                        <i class="fas fa-user-plus text-xs"></i>
                    </div>
                    <span class="text-xs font-bold">Add User</span>
                </a>
                <?php endif; ?>
                
                <?php if (can('inventory.dispatch')): ?>
                <a href="inventory/dispatches/create.php" class="flex items-center p-2.5 rounded-xl bg-purple-50/80 hover:bg-purple-100/80 text-purple-700 transition group border border-purple-100">
                    <div class="w-7 h-7 rounded-lg bg-purple-600 text-white flex items-center justify-center mr-2 shadow-sm group-hover:scale-110 transition">
                        <i class="fas fa-shipping-fast text-xs"></i>
                    </div>
                    <span class="text-xs font-bold">Dispatch</span>
                </a>
                <?php endif; ?>
                
                <a href="inventory/stock.php" class="flex items-center p-2.5 rounded-xl bg-emerald-50/80 hover:bg-emerald-100/80 text-emerald-700 transition group border border-emerald-100">
                    <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center mr-2 shadow-sm group-hover:scale-110 transition">
                        <i class="fas fa-boxes text-xs"></i>
                    </div>
                    <span class="text-xs font-bold">Stock Hub</span>
                </a>
            </div>
        </div>
        
        <!-- User Profile Spotlight -->
        <div class="grad-dark rounded-2xl p-4 text-white shadow-lg relative overflow-hidden">
            <div class="flex items-center space-x-3">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center shadow-lg font-black text-lg border border-white/20">
                    <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold truncate text-white"><?php echo htmlspecialchars($currentUser['username']); ?></p>
                    <p class="text-[11px] text-slate-300 truncate"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></p>
                </div>
            </div>
            <div class="mt-3 pt-3 border-t border-white/10 flex items-center justify-between text-xs">
                <span class="px-2.5 py-0.5 rounded-full bg-white/10 text-indigo-200 font-semibold text-[10px]">
                    <?php echo $currentUser['company_type'] ?? 'Operator'; ?>
                </span>
                <a href="profile.php" class="text-xs text-white/80 hover:text-white font-medium flex items-center">
                    <i class="fas fa-cog mr-1.5"></i> Settings
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Dispatches Glass Table -->
<?php if (!empty($recentDispatches)): ?>
<div class="glass-panel rounded-2xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
    <div class="p-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-gray-50/50">
        <div class="flex items-center space-x-2">
            <div class="w-7 h-7 rounded-lg bg-cyan-500 text-white flex items-center justify-center text-xs shadow-sm">
                <i class="fas fa-truck"></i>
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Recent Dispatches</h4>
                <p class="text-[10px] text-gray-400 font-medium">Latest physical product shipments</p>
            </div>
        </div>
        <div class="flex items-center space-x-2">
            <input type="text" placeholder="Filter dispatches..." oninput="filterDispatches(this.value)" 
                class="px-2.5 py-1 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 bg-white">
            <a href="inventory/dispatches/index.php" class="text-xs font-semibold text-cyan-600 hover:text-cyan-800 transition">View All →</a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs" id="dispatchesTable">
            <thead class="bg-gray-50/80 text-gray-500 uppercase text-[10px] font-bold tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Dispatch #</th>
                    <th class="px-4 py-3 text-left">Warehouse</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($recentDispatches as $dispatch): 
                    $statusColors = [
                        'pending' => 'bg-amber-100 text-amber-800 border-amber-200', 
                        'in_transit' => 'bg-blue-100 text-blue-800 border-blue-200', 
                        'delivered' => 'bg-emerald-100 text-emerald-800 border-emerald-200', 
                        'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200'
                    ];
                    $color = $statusColors[$dispatch['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                ?>
                <tr class="hover:bg-gray-50/80 transition">
                    <td class="px-4 py-2.5 font-bold text-gray-800"><?php echo htmlspecialchars($dispatch['dispatch_number']); ?></td>
                    <td class="px-4 py-2.5 text-gray-600 font-medium"><?php echo htmlspecialchars($dispatch['warehouse_name'] ?? 'Main Warehouse'); ?></td>
                    <td class="px-4 py-2.5 text-gray-500"><?php echo date('M d, Y', strtotime($dispatch['dispatch_date'])); ?></td>
                    <td class="px-4 py-2.5">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?php echo $color; ?> inline-flex items-center">
                            <span class="w-1.5 h-1.5 rounded-full mr-1.5 bg-current"></span>
                            <?php echo ucfirst(str_replace('_', ' ', $dispatch['status'])); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php else: ?>

<!-- Contractor Dashboard View -->
<div class="relative overflow-hidden rounded-2xl grad-emerald p-6 mb-6 text-white shadow-xl shimmer-bg">
    <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/20 text-white border border-white/30 backdrop-blur-md mb-2">
                <i class="fas fa-hard-hat mr-1.5"></i> Contractor Portal
            </span>
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">Welcome, <?php echo htmlspecialchars($currentUser['username']); ?>! 👋</h1>
            <p class="text-xs md:text-sm text-emerald-100 mt-1">Manage site delegations, operational requests, and field engineer assignments.</p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="contractor/delegations.php" class="px-4 py-2 bg-white text-emerald-800 font-bold text-xs rounded-xl shadow hover:bg-emerald-50 transition">
                Manage Delegations →
            </a>
        </div>
    </div>
</div>

<!-- Contractor Stat Cards Grid -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
    <div class="card-hover grad-indigo text-white rounded-2xl p-4 shadow-lg cursor-pointer" onclick="window.location='contractor/delegations.php'">
        <div class="w-9 h-9 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center mb-3">
            <i class="fas fa-inbox text-white text-sm"></i>
        </div>
        <p class="text-3xl font-black"><?php echo $contractorStats['total_delegated']; ?></p>
        <p class="text-xs font-bold text-white/80 uppercase tracking-wider mt-1">Total Delegated</p>
    </div>
    
    <div class="card-hover grad-amber text-white rounded-2xl p-4 shadow-lg cursor-pointer" onclick="window.location='contractor/delegations.php?status=pending'">
        <div class="w-9 h-9 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center mb-3">
            <i class="fas fa-clock text-white text-sm"></i>
        </div>
        <p class="text-3xl font-black"><?php echo $contractorStats['pending']; ?></p>
        <p class="text-xs font-bold text-white/80 uppercase tracking-wider mt-1">Pending Review</p>
    </div>
    
    <div class="card-hover grad-emerald text-white rounded-2xl p-4 shadow-lg cursor-pointer" onclick="window.location='contractor/delegations.php?status=accepted'">
        <div class="w-9 h-9 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center mb-3">
            <i class="fas fa-check-circle text-white text-sm"></i>
        </div>
        <p class="text-3xl font-black"><?php echo $contractorStats['accepted']; ?></p>
        <p class="text-xs font-bold text-white/80 uppercase tracking-wider mt-1">Accepted</p>
    </div>
    
    <div class="card-hover grad-rose text-white rounded-2xl p-4 shadow-lg cursor-pointer" onclick="window.location='contractor/delegations.php?status=rejected'">
        <div class="w-9 h-9 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center mb-3">
            <i class="fas fa-times-circle text-white text-sm"></i>
        </div>
        <p class="text-3xl font-black"><?php echo $contractorStats['rejected']; ?></p>
        <p class="text-xs font-bold text-white/80 uppercase tracking-wider mt-1">Rejected</p>
    </div>
    
    <div class="card-hover grad-purple text-white rounded-2xl p-4 shadow-lg cursor-pointer" onclick="window.location='contractor/assign.php'">
        <div class="w-9 h-9 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center mb-3">
            <i class="fas fa-user-cog text-white text-sm"></i>
        </div>
        <p class="text-3xl font-black"><?php echo $contractorStats['assigned_to_engineers']; ?></p>
        <p class="text-xs font-bold text-white/80 uppercase tracking-wider mt-1">Engineers Assigned</p>
    </div>
</div>

<?php endif; ?>

<!-- Modals with Interactive Live Search -->
<!-- Users Modal -->
<div id="usersModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[80vh] overflow-hidden flex flex-col">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/80">
            <div class="flex items-center space-x-2">
                <div class="w-7 h-7 rounded-lg grad-emerald text-white flex items-center justify-center text-xs">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="font-bold text-gray-800 text-sm">Active System Users (<?php echo $userCount; ?>)</h3>
            </div>
            <button onclick="hideModal('usersModal')" class="w-7 h-7 rounded-lg bg-gray-200 text-gray-500 hover:bg-gray-300 transition flex items-center justify-center">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <div class="p-3 border-b border-gray-100">
            <input type="text" placeholder="Filter users..." oninput="filterModalList(this, 'usersModalList')" 
                class="w-full px-3 py-1.5 text-xs border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50">
        </div>
        <div class="p-4 overflow-y-auto flex-1" id="usersModalList">
            <?php if (empty($users)): ?>
                <p class="text-gray-400 text-xs text-center py-6">No users found</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($users as $user): ?>
                    <div class="modal-item flex items-center justify-between p-3 rounded-xl hover:bg-gray-50 border border-gray-100 transition">
                        <div class="flex items-center space-x-3">
                            <div class="w-9 h-9 rounded-xl grad-emerald text-white font-bold flex items-center justify-center text-xs shadow-sm">
                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                            </div>
                            <div>
                                <p class="font-bold text-gray-800 text-xs"><?php echo htmlspecialchars($user['username']); ?></p>
                                <p class="text-[10px] text-gray-400"><?php echo htmlspecialchars($user['email'] ?? 'No email'); ?></p>
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                            <?php echo htmlspecialchars($user['role_name'] ?? 'User'); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="p-3 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
            <?php if (can('users.read')): ?>
                <a href="users/index.php" class="text-xs font-bold text-emerald-600 hover:text-emerald-800 transition">Full Users Directory →</a>
            <?php else: ?><span></span><?php endif; ?>
            <button onclick="hideModal('usersModal')" class="px-4 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold rounded-xl text-xs transition">Close</button>
        </div>
    </div>
</div>

<!-- Companies Modal -->
<div id="companiesModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[80vh] overflow-hidden flex flex-col">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/80">
            <div class="flex items-center space-x-2">
                <div class="w-7 h-7 rounded-lg grad-purple text-white flex items-center justify-center text-xs">
                    <i class="fas fa-building"></i>
                </div>
                <h3 class="font-bold text-gray-800 text-sm">Registered Companies (<?php echo $companyCount; ?>)</h3>
            </div>
            <button onclick="hideModal('companiesModal')" class="w-7 h-7 rounded-lg bg-gray-200 text-gray-500 hover:bg-gray-300 transition flex items-center justify-center">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <div class="p-3 border-b border-gray-100">
            <input type="text" placeholder="Filter companies..." oninput="filterModalList(this, 'companiesModalList')" 
                class="w-full px-3 py-1.5 text-xs border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 bg-gray-50">
        </div>
        <div class="p-4 overflow-y-auto flex-1" id="companiesModalList">
            <?php if (empty($companies)): ?>
                <p class="text-gray-400 text-xs text-center py-6">No companies found</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($companies as $company): ?>
                    <div class="modal-item flex items-center justify-between p-3 rounded-xl hover:bg-gray-50 border border-gray-100 transition">
                        <div class="flex items-center space-x-3">
                            <div class="w-9 h-9 rounded-xl grad-purple text-white flex items-center justify-center text-xs shadow-sm">
                                <i class="fas fa-building"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-800 text-xs"><?php echo htmlspecialchars($company['name']); ?></p>
                                <p class="text-[10px] text-gray-400"><?php echo htmlspecialchars($company['type']); ?></p>
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?php echo $company['status'] === 'ACTIVE' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-gray-100 text-gray-600'; ?>">
                            <?php echo $company['status']; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="p-3 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
            <?php if (can('companies.read')): ?>
                <a href="companies/index.php" class="text-xs font-bold text-purple-600 hover:text-purple-800 transition">Full Company Directory →</a>
            <?php else: ?><span></span><?php endif; ?>
            <button onclick="hideModal('companiesModal')" class="px-4 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold rounded-xl text-xs transition">Close</button>
        </div>
    </div>
</div>

<!-- Chart & Modal Interactive JavaScript Engine -->
<script>
// Modal Toggle Functions
function showModal(id) { 
    document.getElementById(id).classList.remove('hidden'); 
    document.body.style.overflow = 'hidden'; 
}
function hideModal(id) { 
    document.getElementById(id).classList.add('hidden'); 
    document.body.style.overflow = 'auto'; 
}
document.addEventListener('keydown', e => { 
    if (e.key === 'Escape') { 
        document.querySelectorAll('[id$="Modal"]').forEach(m => m.classList.add('hidden')); 
        document.body.style.overflow = 'auto'; 
    }
});
document.querySelectorAll('[id$="Modal"]').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) hideModal(m.id); });
});

// Live Digital Clock
function updateClock() {
    const el = document.getElementById('live-time');
    if (el) {
        const now = new Date();
        el.textContent = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
}
updateClock();
setInterval(updateClock, 1000);

// Filter Activity Table
function filterActivity(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('#activityTable tbody tr.activity-row').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}


// Filter Dispatches Table
function filterDispatches(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('#dispatchesTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// Filter Modal Lists
function filterModalList(input, containerId) {
    const q = input.value.toLowerCase();
    document.querySelectorAll('#' + containerId + ' .modal-item').forEach(item => {
        item.style.display = item.textContent.toLowerCase().includes(q) ? 'flex' : 'none';
    });
}

// Advanced Chart.js Engine
<?php if (isAdvUser()): ?>
document.addEventListener('DOMContentLoaded', () => {
    // Global Chart.js Defaults
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size = 10;
    Chart.defaults.color = '#64748b';

    // Donut Center Text Plugin Definition
    const doughnutCenterText = {
        id: 'doughnutCenterText',
        beforeDraw(chart) {
            if (chart.config.type !== 'doughnut') return;
            const { ctx, width, height } = chart;
            const text = chart.config.options.plugins.centerText?.text || '';
            const subtext = chart.config.options.plugins.centerText?.subtext || '';
            if (!text) return;
            
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            
            // Value
            ctx.font = 'bold 16px Inter, sans-serif';
            ctx.fillStyle = '#1e293b';
            ctx.fillText(text, width / 2, height / 2 - (subtext ? 7 : 0));
            
            // Label
            if (subtext) {
                ctx.font = '500 9px Inter, sans-serif';
                ctx.fillStyle = '#94a3b8';
                ctx.fillText(subtext, width / 2, height / 2 + 10);
            }
            ctx.restore();
        }
    };
    Chart.register(doughnutCenterText);

    // 1. Delegation Bar Chart (Gradient Fill)
    const delCanvas = document.getElementById('delegationChart');
    if (delCanvas) {
        const delCtx = delCanvas.getContext('2d');
        const delData = <?php echo json_encode($delegationByContractor); ?>;
        if (delData && delData.length > 0) {
            const gradBar = delCtx.createLinearGradient(0, 0, 0, 180);
            gradBar.addColorStop(0, '#6366f1');
            gradBar.addColorStop(1, '#a855f7');

            new Chart(delCtx, {
                type: 'bar',
                data: {
                    labels: delData.map(d => d.contractor_name ? d.contractor_name.substring(0, 12) : 'Partner'),
                    datasets: [{
                        data: delData.map(d => d.count),
                        backgroundColor: gradBar,
                        borderRadius: 6,
                        barThickness: 16
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 9 } } },
                        y: { grid: { borderDash: [3, 3], color: '#f1f5f9' }, ticks: { precision: 0 } }
                    }
                }
            });
        } else {
            delCanvas.parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400 text-xs font-medium">No delegation data</div>';
        }
    }

    // 2. Asset Distribution Doughnut Chart
    const assetCanvas = document.getElementById('assetChart');
    if (assetCanvas) {
        const assetData = [
            <?php echo $inventoryStats['in_stock']; ?>, 
            <?php echo $inventoryStats['dispatched']; ?>, 
            <?php echo $inventoryStats['assigned']; ?>, 
            <?php echo $inventoryStats['under_repair']; ?>
        ];
        const totalAssets = assetData.reduce((a, b) => a + b, 0);
        if (totalAssets > 0) {
            new Chart(assetCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['In Stock', 'Dispatched', 'Assigned', 'Under Repair'],
                    datasets: [{
                        data: assetData,
                        backgroundColor: ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b'],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 8, padding: 6, font: { size: 8 } } },
                        centerText: { text: totalAssets.toString(), subtext: 'Total Assets' }
                    }
                }
            });
        } else {
            assetCanvas.parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400 text-xs font-medium">No asset data</div>';
        }
    }

    // 3. Asset Condition Doughnut Chart
    const condCanvas = document.getElementById('conditionChart');
    if (condCanvas) {
        const condData = [<?php echo $inventoryStats['working']; ?>, <?php echo $inventoryStats['not_working']; ?>];
        const totalCond = condData.reduce((a, b) => a + b, 0);
        if (totalCond > 0) {
            const workingPct = Math.round((condData[0] / totalCond) * 100);
            new Chart(condCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Working', 'Defective'],
                    datasets: [{
                        data: condData,
                        backgroundColor: ['#10b981', '#ef4444'],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 8, padding: 6, font: { size: 8 } } },
                        centerText: { text: workingPct + '%', subtext: 'Working' }
                    }
                }
            });
        } else {
            condCanvas.parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400 text-xs font-medium">No condition data</div>';
        }
    }

    // 4. Dispatch Area Line Chart (Gradient Wave)
    const dispCanvas = document.getElementById('dispatchChart');
    if (dispCanvas) {
        const dispCtx = dispCanvas.getContext('2d');
        const dispData = [
            <?php echo (int)$dispatchStats['pending']; ?>, 
            <?php echo (int)$dispatchStats['in_transit']; ?>, 
            <?php echo (int)$dispatchStats['delivered']; ?>, 
            <?php echo (int)$dispatchStats['cancelled']; ?>
        ];
        if (dispData.reduce((a, b) => a + b, 0) > 0) {
            const gradWave = dispCtx.createLinearGradient(0, 0, 0, 160);
            gradWave.addColorStop(0, 'rgba(6, 182, 212, 0.35)');
            gradWave.addColorStop(1, 'rgba(6, 182, 212, 0.0)');

            new Chart(dispCtx, {
                type: 'line',
                data: {
                    labels: ['Pending', 'Transit', 'Delivered', 'Cancelled'],
                    datasets: [{
                        label: 'Dispatches',
                        data: dispData,
                        borderColor: '#06b6d4',
                        borderWidth: 2.5,
                        backgroundColor: gradWave,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#06b6d4',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 8 } } },
                        y: { grid: { borderDash: [3, 3], color: '#f1f5f9' }, ticks: { precision: 0, font: { size: 8 } } }
                    }
                }
            });
        } else {
            dispCanvas.parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400 text-xs font-medium">No dispatch data</div>';
        }
    }
});
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layouts/base.php';
?>

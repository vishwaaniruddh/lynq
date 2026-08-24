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

$baseUrl = '';
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
            $stmt = $db->prepare("SELECT COUNT(*) FROM sites WHERE company_id = ? AND status = 'active'");
            $stmt->execute([$currentUser['company_id']]);
            $siteCount = $stmt->fetchColumn();
            $activeSites = $siteCount;
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM sites WHERE company_id = ? AND status = 'inactive'");
            $stmt->execute([$currentUser['company_id']]);
            $inactiveSites = $stmt->fetchColumn();

            $stmt = $db->prepare("
                SELECT COUNT(*) as total,
                    SUM(CASE WHEN sd.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN sd.status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN sd.status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM site_delegations sd INNER JOIN sites s ON sd.site_id = s.id WHERE s.company_id = ?
            ");
            $stmt->execute([$currentUser['company_id']]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($stats) {
                $delegationStats = ['total' => (int)$stats['total'], 'pending' => (int)$stats['pending'], 
                    'accepted' => (int)$stats['accepted'], 'rejected' => (int)$stats['rejected']];
            }
            
            $stmt = $db->prepare("
                SELECT c.name as contractor_name, COUNT(*) as count
                FROM site_delegations sd INNER JOIN sites s ON sd.site_id = s.id
                INNER JOIN companies c ON sd.contractor_id = c.id
                WHERE s.company_id = ? GROUP BY sd.contractor_id ORDER BY count DESC LIMIT 5
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
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM site_delegations WHERE contractor_id = ?");
            $stmt->execute([$currentUser['company_id']]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($stats) {
                $contractorStats = ['total_delegated' => (int)$stats['total'], 'pending' => (int)$stats['pending'],
                    'accepted' => (int)$stats['accepted'], 'rejected' => (int)$stats['rejected']];
            }
            
            $stmt = $db->prepare("SELECT COUNT(DISTINCT ea.site_id) FROM engineer_assignments ea
                INNER JOIN site_delegations sd ON ea.delegation_id = sd.id WHERE sd.contractor_id = ?");
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
.stat-card { transition: all 0.3s ease; }
.stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -8px rgba(0,0,0,0.15); }
.gradient-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.gradient-2 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.gradient-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.gradient-4 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
.gradient-5 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
.gradient-6 { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
.mini-chart { height: 120px !important; }
</style>

<?php if (isAdvUser()): ?>


<!-- Primary Stats Row -->
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 mb-5">
    <div class="stat-card bg-white rounded-xl p-3 shadow-sm border border-gray-100 cursor-pointer" onclick="window.location='sites/index.php'">
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-lg gradient-1 flex items-center justify-center"><i class="fas fa-map-marker-alt text-white text-xs"></i></div>
            <span class="text-[10px] text-green-600 font-semibold bg-green-50 px-1.5 py-0.5 rounded"><?php echo $activeSites; ?> active</span>
        </div>
        <p class="text-xl font-bold text-gray-800"><?php echo $siteCount + $inactiveSites; ?></p>
        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Sites</p>
    </div>
    
    <div class="stat-card bg-white rounded-xl p-3 shadow-sm border border-gray-100 cursor-pointer" onclick="window.location='delegations/index.php'">
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-lg gradient-3 flex items-center justify-center"><i class="fas fa-share-alt text-white text-xs"></i></div>
            <span class="text-[10px] text-yellow-600 font-semibold bg-yellow-50 px-1.5 py-0.5 rounded"><?php echo $delegationStats['pending']; ?> pending</span>
        </div>
        <p class="text-xl font-bold text-gray-800"><?php echo $delegationStats['total']; ?></p>
        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Delegations</p>
    </div>
    
    <div class="stat-card bg-white rounded-xl p-3 shadow-sm border border-gray-100 cursor-pointer" onclick="showModal('usersModal')">
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-lg gradient-4 flex items-center justify-center"><i class="fas fa-users text-white text-xs"></i></div>
        </div>
        <p class="text-xl font-bold text-gray-800"><?php echo $userCount; ?></p>
        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Users</p>
    </div>
    
    <div class="stat-card bg-white rounded-xl p-3 shadow-sm border border-gray-100 cursor-pointer" onclick="showModal('companiesModal')">
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-lg gradient-2 flex items-center justify-center"><i class="fas fa-building text-white text-xs"></i></div>
        </div>
        <p class="text-xl font-bold text-gray-800"><?php echo $companyCount; ?></p>
        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Companies</p>
    </div>
    
    <div class="stat-card bg-white rounded-xl p-3 shadow-sm border border-gray-100 cursor-pointer" onclick="window.location='inventory/products.php'">
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-lg gradient-5 flex items-center justify-center"><i class="fas fa-box text-white text-xs"></i></div>
        </div>
        <p class="text-xl font-bold text-gray-800"><?php echo $inventoryStats['total_products']; ?></p>
        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Products</p>
    </div>
    
    <div class="stat-card bg-white rounded-xl p-3 shadow-sm border border-gray-100 cursor-pointer" onclick="window.location='inventory/assets.php'">
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center"><i class="fas fa-microchip text-white text-xs"></i></div>
            <span class="text-[10px] text-green-600 font-semibold bg-green-50 px-1.5 py-0.5 rounded"><?php echo $inventoryStats['working']; ?> ok</span>
        </div>
        <p class="text-xl font-bold text-gray-800"><?php echo $inventoryStats['total_assets']; ?></p>
        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Assets</p>
    </div>
    
    <div class="stat-card bg-white rounded-xl p-3 shadow-sm border border-gray-100 cursor-pointer" onclick="window.location='inventory/warehouses.php'">
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center"><i class="fas fa-warehouse text-white text-xs"></i></div>
        </div>
        <p class="text-xl font-bold text-gray-800"><?php echo (int)$warehouseStats['total']; ?></p>
        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Warehouses</p>
    </div>
    
    <div class="stat-card bg-white rounded-xl p-3 shadow-sm border border-gray-100 cursor-pointer" onclick="window.location='configuration/ip_master.php'">
        <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center"><i class="fas fa-network-wired text-white text-xs"></i></div>
            <span class="text-[10px] text-blue-600 font-semibold bg-blue-50 px-1.5 py-0.5 rounded"><?php echo (int)$ipStats['available']; ?> free</span>
        </div>
        <p class="text-xl font-bold text-gray-800"><?php echo (int)$ipStats['total']; ?></p>
        <p class="text-[10px] text-gray-500 uppercase tracking-wide">IP Configs</p>
    </div>
</div>

<!-- Secondary Stats & Charts Row -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <!-- Delegation Status Mini Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-gray-700">Delegation Status</h4>
            <a href="delegations/index.php" class="text-xs text-primary hover:underline">View</a>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center">
            <div class="p-2 bg-yellow-50 rounded-lg">
                <p class="text-lg font-bold text-yellow-600"><?php echo $delegationStats['pending']; ?></p>
                <p class="text-[10px] text-gray-500">Pending</p>
            </div>
            <div class="p-2 bg-green-50 rounded-lg">
                <p class="text-lg font-bold text-green-600"><?php echo $delegationStats['accepted']; ?></p>
                <p class="text-[10px] text-gray-500">Accepted</p>
            </div>
            <div class="p-2 bg-red-50 rounded-lg">
                <p class="text-lg font-bold text-red-600"><?php echo $delegationStats['rejected']; ?></p>
                <p class="text-[10px] text-gray-500">Rejected</p>
            </div>
        </div>
    </div>
    
    <!-- Dispatch Status Mini Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-gray-700">Dispatch Status</h4>
            <a href="inventory/dispatches/index.php" class="text-xs text-primary hover:underline">View</a>
        </div>
        <div class="grid grid-cols-4 gap-1 text-center">
            <div class="p-1.5 bg-yellow-50 rounded-lg">
                <p class="text-sm font-bold text-yellow-600"><?php echo (int)$dispatchStats['pending']; ?></p>
                <p class="text-[9px] text-gray-500">Pending</p>
            </div>
            <div class="p-1.5 bg-blue-50 rounded-lg">
                <p class="text-sm font-bold text-blue-600"><?php echo (int)$dispatchStats['in_transit']; ?></p>
                <p class="text-[9px] text-gray-500">Transit</p>
            </div>
            <div class="p-1.5 bg-green-50 rounded-lg">
                <p class="text-sm font-bold text-green-600"><?php echo (int)$dispatchStats['delivered']; ?></p>
                <p class="text-[9px] text-gray-500">Done</p>
            </div>
            <div class="p-1.5 bg-red-50 rounded-lg">
                <p class="text-sm font-bold text-red-600"><?php echo (int)$dispatchStats['cancelled']; ?></p>
                <p class="text-[9px] text-gray-500">Cancel</p>
            </div>
        </div>
    </div>
    
    <!-- IP Config Mini Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-gray-700">IP Configuration</h4>
            <a href="ip-config/index.php" class="text-xs text-primary hover:underline">View</a>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center">
            <div class="p-2 bg-green-50 rounded-lg">
                <p class="text-lg font-bold text-green-600"><?php echo (int)$ipStats['available']; ?></p>
                <p class="text-[10px] text-gray-500">Available</p>
            </div>
            <div class="p-2 bg-yellow-50 rounded-lg">
                <p class="text-lg font-bold text-yellow-600"><?php echo (int)$ipStats['locked']; ?></p>
                <p class="text-[10px] text-gray-500">Locked</p>
            </div>
            <div class="p-2 bg-blue-50 rounded-lg">
                <p class="text-lg font-bold text-blue-600"><?php echo (int)$ipStats['configured']; ?></p>
                <p class="text-[10px] text-gray-500">Configured</p>
            </div>
        </div>
    </div>
    
    <!-- Stock & Inventory Mini Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-gray-700">Stock Overview</h4>
            <a href="inventory/stock/index.php" class="text-xs text-primary hover:underline">View</a>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center">
            <div class="p-2 bg-indigo-50 rounded-lg">
                <p class="text-lg font-bold text-indigo-600"><?php echo $stockStats['total_quantity']; ?></p>
                <p class="text-[10px] text-gray-500">Total Qty</p>
            </div>
            <div class="p-2 bg-orange-50 rounded-lg">
                <p class="text-lg font-bold text-orange-600"><?php echo $stockStats['low_stock']; ?></p>
                <p class="text-[10px] text-gray-500">Low Stock</p>
            </div>
            <div class="p-2 bg-purple-50 rounded-lg">
                <p class="text-lg font-bold text-purple-600"><?php echo $inventoryStats['in_stock']; ?></p>
                <p class="text-[10px] text-gray-500">In Stock</p>
            </div>
        </div>
    </div>
</div>

<!-- Charts & Activity Row -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-5">
    <!-- Delegation Chart (Smaller) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <h4 class="text-sm font-semibold text-gray-700 mb-2">By Contractor</h4>
        <div class="mini-chart"><canvas id="delegationChart"></canvas></div>
    </div>
    
    <!-- Asset Status Chart (Smaller) -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <h4 class="text-sm font-semibold text-gray-700 mb-2">Asset Status</h4>
        <div class="mini-chart"><canvas id="assetChart"></canvas></div>
    </div>
    
    <!-- Asset Condition Chart -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <h4 class="text-sm font-semibold text-gray-700 mb-2">Asset Condition</h4>
        <div class="mini-chart"><canvas id="conditionChart"></canvas></div>
    </div>
    
    <!-- Dispatch Trend Chart -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <h4 class="text-sm font-semibold text-gray-700 mb-2">Dispatch Overview</h4>
        <div class="mini-chart"><canvas id="dispatchChart"></canvas></div>
    </div>
</div>

<!-- Additional Stats Row -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
    <div class="stat-card bg-gradient-to-br from-rose-500 to-pink-600 rounded-xl p-3 text-white shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-2xl font-bold"><?php echo $inventoryStats['dispatched']; ?></p>
                <p class="text-[10px] text-white/80 uppercase">Dispatched</p>
            </div>
            <i class="fas fa-truck text-white/30 text-2xl"></i>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-amber-500 to-orange-600 rounded-xl p-3 text-white shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-2xl font-bold"><?php echo $inventoryStats['under_repair']; ?></p>
                <p class="text-[10px] text-white/80 uppercase">Under Repair</p>
            </div>
            <i class="fas fa-tools text-white/30 text-2xl"></i>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-red-500 to-rose-600 rounded-xl p-3 text-white shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-2xl font-bold"><?php echo $inventoryStats['not_working']; ?></p>
                <p class="text-[10px] text-white/80 uppercase">Not Working</p>
            </div>
            <i class="fas fa-exclamation-triangle text-white/30 text-2xl"></i>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-indigo-500 to-violet-600 rounded-xl p-3 text-white shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-2xl font-bold"><?php echo (int)$transferStats['total']; ?></p>
                <p class="text-[10px] text-white/80 uppercase">Transfers</p>
            </div>
            <i class="fas fa-exchange-alt text-white/30 text-2xl"></i>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-teal-500 to-cyan-600 rounded-xl p-3 text-white shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-2xl font-bold"><?php echo (int)$repairStats['total']; ?></p>
                <p class="text-[10px] text-white/80 uppercase">Repairs</p>
            </div>
            <i class="fas fa-wrench text-white/30 text-2xl"></i>
        </div>
    </div>
    
    <div class="stat-card bg-gradient-to-br from-gray-600 to-gray-800 rounded-xl p-3 text-white shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-2xl font-bold"><?php echo $inventoryStats['scrapped']; ?></p>
                <p class="text-[10px] text-white/80 uppercase">Scrapped</p>
            </div>
            <i class="fas fa-trash text-white/30 text-2xl"></i>
        </div>
    </div>
</div>

<!-- Activity, Quick Actions & Profile Row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
    <!-- Recent Activity -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-700">Recent Activity</h4>
            <?php if (can('system.audit')): ?><a href="permissions/audit.php" class="text-xs text-primary hover:underline">View All</a><?php endif; ?>
        </div>
        <div class="p-3 max-h-64 overflow-y-auto">
            <?php if (empty($recentActivity)): ?>
            <div class="text-center py-6"><i class="fas fa-history text-3xl text-gray-300 mb-2"></i><p class="text-gray-500 text-sm">No recent activity</p></div>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($recentActivity as $activity): ?>
                <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition">
                    <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
                        <?php
                        $icon = 'fa-clock';
                        if (strpos($activity['action'], 'create') !== false) $icon = 'fa-plus';
                        elseif (strpos($activity['action'], 'update') !== false) $icon = 'fa-edit';
                        elseif (strpos($activity['action'], 'delete') !== false) $icon = 'fa-trash';
                        elseif (strpos($activity['action'], 'login') !== false) $icon = 'fa-sign-in-alt';
                        ?>
                        <i class="fas <?php echo $icon; ?> text-primary text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-gray-800 font-medium truncate"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $activity['action']))); ?></p>
                        <p class="text-[10px] text-gray-500"><?php echo htmlspecialchars($activity['performed_by_name'] ?? 'System'); ?> • <?php echo date('M d, H:i', strtotime($activity['timestamp'])); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Actions & Profile -->
    <div class="space-y-4">
        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Quick Actions</h4>
            <div class="grid grid-cols-2 gap-2">
                <?php if (can('sites.create')): ?>
                <a href="sites/add.php" class="flex items-center p-2 rounded-lg bg-indigo-50 hover:bg-indigo-100 transition">
                    <i class="fas fa-plus text-indigo-600 text-xs mr-2"></i><span class="text-xs text-gray-700">Add Site</span>
                </a>
                <?php endif; ?>
                <?php if (can('users.create')): ?>
                <a href="users/create.php" class="flex items-center p-2 rounded-lg bg-blue-50 hover:bg-blue-100 transition">
                    <i class="fas fa-user-plus text-blue-600 text-xs mr-2"></i><span class="text-xs text-gray-700">Add User</span>
                </a>
                <?php endif; ?>
                <?php if (can('inventory.dispatch')): ?>
                <a href="inventory/dispatches/create.php" class="flex items-center p-2 rounded-lg bg-purple-50 hover:bg-purple-100 transition">
                    <i class="fas fa-truck text-purple-600 text-xs mr-2"></i><span class="text-xs text-gray-700">Dispatch</span>
                </a>
                <?php endif; ?>
                <a href="inventory/stock.php" class="flex items-center p-2 rounded-lg bg-green-50 hover:bg-green-100 transition">
                    <i class="fas fa-boxes text-green-600 text-xs mr-2"></i><span class="text-xs text-gray-700">Inventory</span>
                </a>
            </div>
        </div>
        
        <!-- Profile Mini Card -->
        <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-xl p-4 text-white">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center">
                    <span class="text-lg font-bold"><?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold truncate"><?php echo htmlspecialchars($currentUser['username']); ?></p>
                    <p class="text-xs text-white/60 truncate"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></p>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs">
                <span class="px-2 py-1 bg-white/10 rounded"><?php echo $currentUser['company_type'] ?? ''; ?></span>
                <a href="profile.php" class="text-white/80 hover:text-white"><i class="fas fa-cog mr-1"></i>Profile</a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Dispatches Table -->
<?php if (!empty($recentDispatches)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-5">
    <div class="p-4 border-b border-gray-100 flex items-center justify-between">
        <h4 class="text-sm font-semibold text-gray-700">Recent Dispatches</h4>
        <a href="inventory/dispatch.php" class="text-xs text-primary hover:underline">View All</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Dispatch #</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Warehouse</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Date</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($recentDispatches as $dispatch): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 font-medium text-gray-800"><?php echo htmlspecialchars($dispatch['dispatch_number']); ?></td>
                    <td class="px-4 py-2 text-gray-600"><?php echo htmlspecialchars($dispatch['warehouse_name'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-2 text-gray-600"><?php echo date('M d, Y', strtotime($dispatch['dispatch_date'])); ?></td>
                    <td class="px-4 py-2">
                        <?php
                        $statusColors = ['pending' => 'bg-yellow-100 text-yellow-700', 'in_transit' => 'bg-blue-100 text-blue-700', 
                            'delivered' => 'bg-green-100 text-green-700', 'cancelled' => 'bg-red-100 text-red-700'];
                        $color = $statusColors[$dispatch['status']] ?? 'bg-gray-100 text-gray-700';
                        ?>
                        <span class="px-2 py-1 rounded text-xs font-medium <?php echo $color; ?>"><?php echo ucfirst($dispatch['status']); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- Contractor Dashboard -->
<div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 rounded-2xl p-5 mb-5 text-white shadow-xl relative overflow-hidden">
    <div class="absolute right-0 top-0 w-48 h-48 bg-white/10 rounded-full -mr-24 -mt-24"></div>
    <div class="relative">
        <h1 class="text-xl font-bold">Welcome, <?php echo htmlspecialchars($currentUser['username']); ?>! 👋</h1>
        <p class="text-white/80 text-sm mt-1">Manage your delegated sites and engineer assignments</p>
    </div>
</div>

<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
    <div class="stat-card bg-white rounded-xl p-4 shadow-sm border border-gray-100 cursor-pointer" onclick="window.location='contractor/delegations.php'">
        <div class="w-10 h-10 rounded-lg gradient-1 flex items-center justify-center mb-2"><i class="fas fa-inbox text-white"></i></div>
        <p class="text-2xl font-bold text-gray-800"><?php echo $contractorStats['total_delegated']; ?></p>
        <p class="text-xs text-gray-500">Total Delegated</p>
    </div>
    <div class="stat-card bg-white rounded-xl p-4 shadow-sm border border-gray-100 cursor-pointer" onclick="window.location='contractor/delegations.php?status=pending'">
        <div class="w-10 h-10 rounded-lg bg-yellow-500 flex items-center justify-center mb-2"><i class="fas fa-clock text-white"></i></div>
        <p class="text-2xl font-bold text-yellow-600"><?php echo $contractorStats['pending']; ?></p>
        <p class="text-xs text-gray-500">Pending</p>
    </div>
    <div class="stat-card bg-white rounded-xl p-4 shadow-sm border border-gray-100 cursor-pointer" onclick="window.location='contractor/delegations.php?status=accepted'">
        <div class="w-10 h-10 rounded-lg bg-green-500 flex items-center justify-center mb-2"><i class="fas fa-check-circle text-white"></i></div>
        <p class="text-2xl font-bold text-green-600"><?php echo $contractorStats['accepted']; ?></p>
        <p class="text-xs text-gray-500">Accepted</p>
    </div>
    <div class="stat-card bg-white rounded-xl p-4 shadow-sm border border-gray-100 cursor-pointer" onclick="window.location='contractor/delegations.php?status=rejected'">
        <div class="w-10 h-10 rounded-lg bg-red-500 flex items-center justify-center mb-2"><i class="fas fa-times-circle text-white"></i></div>
        <p class="text-2xl font-bold text-red-600"><?php echo $contractorStats['rejected']; ?></p>
        <p class="text-xs text-gray-500">Rejected</p>
    </div>
    <div class="stat-card bg-white rounded-xl p-4 shadow-sm border border-gray-100 cursor-pointer" onclick="window.location='contractor/assign.php'">
        <div class="w-10 h-10 rounded-lg bg-purple-500 flex items-center justify-center mb-2"><i class="fas fa-hard-hat text-white"></i></div>
        <p class="text-2xl font-bold text-purple-600"><?php echo $contractorStats['assigned_to_engineers']; ?></p>
        <p class="text-xs text-gray-500">Assigned</p>
    </div>
</div>
<?php endif; ?>

<!-- Modals -->
<div id="usersModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full max-h-[70vh] overflow-hidden">
        <div class="p-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800"><i class="fas fa-users mr-2 text-blue-500"></i>Users (<?php echo $userCount; ?>)</h3>
            <button onclick="hideModal('usersModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 overflow-y-auto max-h-[50vh]">
            <?php if (empty($users)): ?><p class="text-gray-500 text-center py-6">No users found</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($users as $user): ?>
                <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center"><span class="text-blue-600 font-medium text-sm"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span></div>
                        <div><p class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($user['username']); ?></p><p class="text-xs text-gray-500"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p></div>
                    </div>
                    <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-600"><?php echo htmlspecialchars($user['role_name'] ?? 'N/A'); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="p-3 border-t bg-gray-50 flex justify-between">
            <?php if (can('users.read')): ?><a href="users/index.php" class="text-primary text-sm hover:underline">View all →</a><?php else: ?><span></span><?php endif; ?>
            <button onclick="hideModal('usersModal')" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 rounded text-gray-700 text-sm">Close</button>
        </div>
    </div>
</div>

<div id="companiesModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[70vh] overflow-hidden">
        <div class="p-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800"><i class="fas fa-building mr-2 text-green-500"></i>Companies (<?php echo $companyCount; ?>)</h3>
            <button onclick="hideModal('companiesModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 overflow-y-auto max-h-[50vh]">
            <?php if (empty($companies)): ?><p class="text-gray-500 text-center py-6">No companies found</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($companies as $company): ?>
                <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 border border-gray-100">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center"><i class="fas fa-building text-green-600 text-sm"></i></div>
                        <div><p class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($company['name']); ?></p><p class="text-xs text-gray-500"><?php echo $company['type']; ?></p></div>
                    </div>
                    <span class="px-2 py-1 rounded text-xs <?php echo $company['status'] === 'ACTIVE' ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-600'; ?>"><?php echo $company['status']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="p-3 border-t bg-gray-50 flex justify-between">
            <?php if (can('companies.read')): ?><a href="companies/index.php" class="text-primary text-sm hover:underline">View all →</a><?php else: ?><span></span><?php endif; ?>
            <button onclick="hideModal('companiesModal')" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 rounded text-gray-700 text-sm">Close</button>
        </div>
    </div>
</div>

<div id="rolesModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[70vh] overflow-hidden">
        <div class="p-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800"><i class="fas fa-user-tag mr-2 text-purple-500"></i>Roles (<?php echo $roleCount; ?>)</h3>
            <button onclick="hideModal('rolesModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 overflow-y-auto max-h-[50vh]">
            <?php if (empty($roles)): ?><p class="text-gray-500 text-center py-6">No roles found</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($roles as $role): ?>
                <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 border border-gray-100">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center"><i class="fas fa-user-tag text-purple-600 text-sm"></i></div>
                        <div><p class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($role['name']); ?></p><p class="text-xs text-gray-500"><?php echo htmlspecialchars($role['description'] ?? ''); ?></p></div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-600">Lvl <?php echo $role['level']; ?></span>
                        <span class="px-2 py-1 rounded text-xs <?php echo $role['company_type'] === 'ADV' ? 'bg-blue-100 text-blue-600' : 'bg-orange-100 text-orange-600'; ?>"><?php echo $role['company_type']; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="p-3 border-t bg-gray-50 flex justify-between">
            <?php if (can('roles.read')): ?><a href="roles/index.php" class="text-primary text-sm hover:underline">View all →</a><?php else: ?><span></span><?php endif; ?>
            <button onclick="hideModal('rolesModal')" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 rounded text-gray-700 text-sm">Close</button>
        </div>
    </div>
</div>

<script>
function showModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
function hideModal(id) { document.getElementById(id).classList.add('hidden'); document.body.style.overflow = 'auto'; }
document.addEventListener('keydown', e => { if (e.key === 'Escape') { document.querySelectorAll('[id$="Modal"]').forEach(m => m.classList.add('hidden')); document.body.style.overflow = 'auto'; }});
document.querySelectorAll('[id$="Modal"]').forEach(m => m.addEventListener('click', e => { if (e.target === m) hideModal(m.id); }));

// Live time
setInterval(() => { document.getElementById('live-time') && (document.getElementById('live-time').textContent = new Date().toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit', hour12: false})); }, 1000);

<?php if (isAdvUser()): ?>
// Charts
const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } };

// Delegation Chart
const delData = <?php echo json_encode($delegationByContractor); ?>;
if (delData.length > 0) {
    new Chart(document.getElementById('delegationChart'), {
        type: 'bar', data: { labels: delData.map(d => d.contractor_name.substring(0,10)), datasets: [{ data: delData.map(d => d.count), backgroundColor: ['#6366f1', '#8b5cf6', '#a855f7', '#d946ef', '#ec4899'], borderRadius: 4 }] },
        options: { ...chartOptions, scales: { y: { display: false }, x: { grid: { display: false }, ticks: { font: { size: 9 } } } } }
    });
} else { document.getElementById('delegationChart').parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400 text-xs">No data</div>'; }

// Asset Status Chart
const assetData = [<?php echo $inventoryStats['in_stock']; ?>, <?php echo $inventoryStats['dispatched']; ?>, <?php echo $inventoryStats['assigned']; ?>, <?php echo $inventoryStats['under_repair']; ?>];
if (assetData.reduce((a,b) => a+b, 0) > 0) {
    new Chart(document.getElementById('assetChart'), {
        type: 'doughnut', data: { labels: ['In Stock', 'Dispatched', 'Assigned', 'Repair'], datasets: [{ data: assetData, backgroundColor: ['#10b981', '#6366f1', '#8b5cf6', '#f59e0b'], borderWidth: 0, cutout: '65%' }] },
        options: { ...chartOptions, plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 8, padding: 4, font: { size: 9 } } } } }
    });
} else { document.getElementById('assetChart').parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400 text-xs">No data</div>'; }

// Condition Chart
const condData = [<?php echo $inventoryStats['working']; ?>, <?php echo $inventoryStats['not_working']; ?>];
if (condData.reduce((a,b) => a+b, 0) > 0) {
    new Chart(document.getElementById('conditionChart'), {
        type: 'doughnut', data: { labels: ['Working', 'Not Working'], datasets: [{ data: condData, backgroundColor: ['#10b981', '#ef4444'], borderWidth: 0, cutout: '65%' }] },
        options: { ...chartOptions, plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 8, padding: 4, font: { size: 9 } } } } }
    });
} else { document.getElementById('conditionChart').parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400 text-xs">No data</div>'; }

// Dispatch Chart
const dispData = [<?php echo (int)$dispatchStats['pending']; ?>, <?php echo (int)$dispatchStats['in_transit']; ?>, <?php echo (int)$dispatchStats['delivered']; ?>, <?php echo (int)$dispatchStats['cancelled']; ?>];
if (dispData.reduce((a,b) => a+b, 0) > 0) {
    new Chart(document.getElementById('dispatchChart'), {
        type: 'bar', data: { labels: ['Pending', 'Transit', 'Done', 'Cancel'], datasets: [{ data: dispData, backgroundColor: ['#eab308', '#3b82f6', '#10b981', '#ef4444'], borderRadius: 4 }] },
        options: { ...chartOptions, indexAxis: 'y', scales: { x: { display: false }, y: { grid: { display: false }, ticks: { font: { size: 9 } } } } }
    });
} else { document.getElementById('dispatchChart').parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400 text-xs">No data</div>'; }
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layouts/base.php';
?>
